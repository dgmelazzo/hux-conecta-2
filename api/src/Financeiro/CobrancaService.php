<?php

declare(strict_types=1);

namespace CRM\Financeiro;

use CRM\Gateway\GatewayFactory;
use CRM\Shared\Exceptions\GatewayException;

/**
 * CobrancaService
 *
 * Orquestra a criação, cancelamento e atualização de cobranças.
 * É o único ponto do sistema que fala com a PAL —
 * nunca acesse GatewayFactory diretamente de um Controller.
 */
class CobrancaService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly int  $tenantId,
    ) {}

    // ── Criar ─────────────────────────────────────────────

    /**
     * Cria uma cobrança no gateway e persiste no banco.
     *
     * @param  array{
     *   associado_id: int,
     *   plano_id?:    int,
     *   modalidade:   string,
     *   valor:        float,
     *   vencimento:   string,
     *   referencia:   string,
     *   descricao?:   string,
     *   criado_por?:  int,
     * } $params
     *
     * @return array<string, mixed>  Registro da cobrança com dados do gateway
     * @throws GatewayException
     */
    public function criar(array $params): array
    {
        $associado = $this->fetchAssociado($params['associado_id']);
        $gateway   = GatewayFactory::make($this->pdo, $this->tenantId);

        // Montar dados do cliente para o gateway
        $clientePayload = [
            'nome'     => $associado['razao_social'] ?? $associado['nome_responsavel'],
            'cpf_cnpj' => $associado['cnpj']         ?? $associado['cpf'],
            'email'    => $associado['email'],
            'telefone' => $associado['telefone'],
        ];

        // Inserir cobrança como pendente antes de chamar o gateway
        $cobrancaId = $this->insertCobranca([
            'tenant_id'       => $this->tenantId,
            'associado_id'    => $params['associado_id'],
            'plano_id'        => $params['plano_id'] ?? null,
            'gateway'         => $this->resolveGatewayName(),
            'modalidade'      => $params['modalidade'],
            'valor'           => $params['valor'],
            'data_vencimento' => $params['vencimento'],
            'referencia'      => $params['referencia'],
            'descricao'       => $params['descricao'] ?? null,
            'status'          => 'pendente',
            'criado_por'      => $params['criado_por'] ?? null,
        ]);

        try {
            // Chamar PAL
            $result = $gateway->createCharge([
                'modalidade' => $params['modalidade'],
                'valor'      => $params['valor'],
                'vencimento' => $params['vencimento'],
                'referencia' => $params['referencia'],
                'cliente'    => $clientePayload,
                'cartao'     => $params['cartao'] ?? null,
                'metadata'   => ['cobranca_id' => $cobrancaId],
            ]);

            // Atualizar com dados do gateway
            $this->updateCobranca($cobrancaId, [
                'gateway_charge_id' => $result['gateway_charge_id'],
                'gateway_url'       => $result['gateway_url'] ?? null,
                'status'            => $result['status'],
            ]);

            return array_merge(['id' => $cobrancaId], $result);

        } catch (GatewayException $e) {
            // Marcar como falhou — não deletar para manter auditoria
            $this->updateCobranca($cobrancaId, ['status' => 'falhou']);
            throw $e;
        }
    }

    // ── Cancelar ──────────────────────────────────────────

    /**
     * Cancela ou estorna uma cobrança.
     */
    public function cancelar(int $cobrancaId, bool $estorno = false, ?string $motivo = null): array
    {
        $cobranca = $this->fetchCobranca($cobrancaId);

        if (empty($cobranca['gateway_charge_id'])) {
            throw new GatewayException('Cobrança sem ID no gateway — não é possível cancelar.');
        }

        $gateway = GatewayFactory::make($this->pdo, $this->tenantId);

        $result = $gateway->cancelCharge($cobranca['gateway_charge_id'], [
            'estorno' => $estorno,
            'motivo'  => $motivo,
        ]);

        $this->updateCobranca($cobrancaId, ['status' => $result['status']]);

        return $result;
    }

    // ── Sincronizar status ────────────────────────────────

    /**
     * Consulta o gateway e atualiza o status no banco.
     * Útil para reconciliação periódica.
     */
    public function sincronizarStatus(int $cobrancaId): array
    {
        $cobranca = $this->fetchCobranca($cobrancaId);

        if (empty($cobranca['gateway_charge_id'])) {
            throw new GatewayException('Cobrança sem ID no gateway.');
        }

        $gateway = GatewayFactory::make($this->pdo, $this->tenantId);
        $result  = $gateway->getStatus($cobranca['gateway_charge_id']);

        $updates = ['status' => $result['status']];

        if ($result['status'] === 'pago') {
            $updates['valor_pago']       = $result['valor_pago'];
            $updates['data_pagamento']   = $result['data_pagamento']
                ? date('Y-m-d H:i:s', strtotime($result['data_pagamento']))
                : date('Y-m-d H:i:s');
        }

        $this->updateCobranca($cobrancaId, $updates);

        // Se pagou, atualizar status do associado
        if ($result['status'] === 'pago') {
            $this->onPagamentoConfirmado((int) $cobranca['associado_id'], $cobranca);
        }

        return $result;
    }

    // ── Processar webhook ─────────────────────────────────

    /**
     * Ponto de entrada para webhooks — chamado pelo WebhookController.
     *
     * @param  array<string, mixed> $payload
     * @param  array<string, string> $headers
     */
    public function processarWebhook(array $payload, array $headers, string $gatewayNome): void
    {
        $gateway = GatewayFactory::makeDriver(
            gateway:   $gatewayNome,
            apiKey:    '',              // webhook não precisa de key para validar token
            ambiente:  'producao',
            webhookToken: $this->fetchWebhookToken($gatewayNome),
        );

        $event = $gateway->handleWebhook($payload, $headers);

        // Encontrar cobrança pelo gateway_charge_id
        $cobranca = $this->fetchCobrancaByGatewayId($event['gateway_charge_id']);

        if (!$cobranca) {
            // Pode ser de outro tenant — logar e ignorar silenciosamente
            return;
        }

        $updates = [
            'status'          => $event['status'],
            'webhook_payload' => json_encode($payload),
            'webhook_em'      => date('Y-m-d H:i:s'),
        ];

        if ($event['evento'] === 'pagamento_confirmado') {
            $updates['valor_pago']     = $event['valor_pago'];
            $updates['data_pagamento'] = $event['data_pagamento']
                ? date('Y-m-d H:i:s', strtotime($event['data_pagamento']))
                : date('Y-m-d H:i:s');

            $this->onPagamentoConfirmado((int) $cobranca['associado_id'], $cobranca);
        }

        $this->updateCobranca((int) $cobranca['id'], $updates);
    }

    // ── Hooks de negócio ──────────────────────────────────

    /**
     * Executado quando um pagamento é confirmado.
     * Atualiza status do associado e registra renovação.
     */
    private function onPagamentoConfirmado(int $associadoId, array $cobranca): void
    {
        // Reativar associado se estava inadimplente
        $stmt = $this->pdo->prepare(
            "UPDATE associados
                SET status = 'ativo',
                    atualizado_em = NOW()
              WHERE id = :id
                AND tenant_id = :tenant_id
                AND status IN ('inadimplente', 'suspenso')"
        );
        $stmt->execute([':id' => $associadoId, ':tenant_id' => $this->tenantId]);

        // Registrar renovação se vinculado a plano
        if (!empty($cobranca['plano_id'])) {
            $stmt = $this->pdo->prepare(
                "UPDATE renovacoes
                    SET status = 'pago', renovado_em = NOW()
                  WHERE cobranca_id = :cobranca_id
                    AND tenant_id   = :tenant_id"
            );
            $stmt->execute([
                ':cobranca_id' => $cobranca['id'],
                ':tenant_id'   => $this->tenantId,
            ]);
        }
    }

    // ── DB Helpers ────────────────────────────────────────

    private function insertCobranca(array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $vals = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));

        $stmt = $this->pdo->prepare("INSERT INTO cobrancas ({$cols}) VALUES ({$vals})");
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    private function updateCobranca(int $id, array $data): void
    {
        $set  = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $stmt = $this->pdo->prepare(
            "UPDATE cobrancas SET {$set}, atualizado_em = NOW()
              WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute(array_merge($data, [':id' => $id, ':tenant_id' => $this->tenantId]));
    }

    /** @return array<string, mixed> */
    private function fetchCobranca(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cobrancas WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $this->tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \RuntimeException("Cobrança #{$id} não encontrada.");
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    private function fetchCobrancaByGatewayId(string $gatewayChargeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cobrancas WHERE gateway_charge_id = :gid AND tenant_id = :tid LIMIT 1'
        );
        $stmt->execute([':gid' => $gatewayChargeId, ':tid' => $this->tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed> */
    private function fetchAssociado(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM associados WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':tenant_id' => $this->tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \RuntimeException("Associado #{$id} não encontrado.");
        }

        return $row;
    }

    private function resolveGatewayName(): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT gateway FROM gateway_configs
              WHERE tenant_id = :tid AND ativo = 1 LIMIT 1"
        );
        $stmt->execute([':tid' => $this->tenantId]);

        return (string) ($stmt->fetchColumn() ?: 'asaas');
    }

    private function fetchWebhookToken(string $gateway): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT webhook_token FROM gateway_configs
              WHERE tenant_id = :tid AND gateway = :gw LIMIT 1"
        );
        $stmt->execute([':tid' => $this->tenantId, ':gw' => $gateway]);
        $row = $stmt->fetchColumn();

        return $row ?: null;
    }
}
