<?php

declare(strict_types=1);

namespace CRM\Gateway\Drivers;

use CRM\Gateway\Contracts\GatewayInterface;
use CRM\Shared\Exceptions\GatewayException;
use CRM\Shared\Exceptions\WebhookAuthException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * AsaasDriver — Implementação PAL para o gateway Asaas
 *
 * Documentação Asaas: https://docs.asaas.com/reference
 * Suporta: boleto, pix, cartão
 */
class AsaasDriver implements GatewayInterface
{
    private Client $http;

    private const BASE_URL_PROD    = 'https://api.asaas.com/v3';
    private const BASE_URL_SANDBOX = 'https://sandbox.asaas.com/api/v3';

    /** Mapa status Asaas → status interno CRM */
    private const STATUS_MAP = [
        'PENDING'           => 'pendente',
        'RECEIVED'          => 'pago',
        'CONFIRMED'         => 'pago',
        'OVERDUE'           => 'pendente',      // vencida mas ainda recuperável
        'REFUNDED'          => 'estornado',
        'RECEIVED_IN_CASH'  => 'pago',
        'REFUND_REQUESTED'  => 'estornado',
        'REFUND_IN_PROGRESS'=> 'estornado',
        'CHARGEBACK_REQUESTED' => 'cancelado',
        'CHARGEBACK_DISPUTE'=> 'cancelado',
        'AWAITING_CHARGEBACK_REVERSAL' => 'cancelado',
        'DUNNING_REQUESTED' => 'cancelado',
        'DUNNING_RECEIVED'  => 'pago',
        'AWAITING_RISK_ANALYSIS' => 'pendente',
    ];

    /** Mapa evento webhook Asaas → evento interno CRM */
    private const EVENT_MAP = [
        'PAYMENT_RECEIVED'          => 'pagamento_confirmado',
        'PAYMENT_CONFIRMED'         => 'pagamento_confirmado',
        'PAYMENT_OVERDUE'           => 'vencido',
        'PAYMENT_DELETED'           => 'pagamento_cancelado',
        'PAYMENT_REFUNDED'          => 'estorno',
        'PAYMENT_CHARGEBACK_REQUESTED' => 'pagamento_cancelado',
    ];

    public function __construct(
        private readonly string $apiKey,
        private readonly ?string $apiSecret,
        private readonly string $ambiente,
        private readonly ?string $webhookToken,
    ) {
        $baseUrl = $ambiente === 'producao'
            ? self::BASE_URL_PROD
            : self::BASE_URL_SANDBOX;

        $this->http = new Client([
            'base_uri' => $baseUrl . '/',
            'timeout'  => 30,
            'headers'  => [
                'access_token' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ]);
    }

    // ── createCharge ───────────────────────────────────────

    public function createCharge(array $params): array
    {
        $modalidade = $params['modalidade'];
        $cliente    = $params['cliente'];

        // 1. Garantir que o cliente existe no Asaas
        $customerId = $this->upsertCustomer($cliente);

        // 2. Montar payload da cobrança
        $payload = [
            'customer'    => $customerId,
            'billingType' => $this->mapModalidade($modalidade),
            'value'       => $params['valor'],
            'dueDate'     => $params['vencimento'],
            'description' => $params['referencia'] ?? ($params['descricao'] ?? ''),
            'externalReference' => $params['metadata']['cobranca_id'] ?? null,
        ];

        // Cartão de crédito
        if ($modalidade === 'cartao' && isset($params['cartao'])) {
            $payload['creditCard'] = [
                'holderName'  => $params['cartao']['nome_titular'],
                'number'      => $params['cartao']['numero'],
                'expiryMonth' => explode('/', $params['cartao']['validade'])[0],
                'expiryYear'  => '20' . explode('/', $params['cartao']['validade'])[1],
                'ccv'         => $params['cartao']['cvv'],
            ];
            $payload['creditCardHolderInfo'] = [
                'name'          => $cliente['nome'],
                'cpfCnpj'       => $cliente['cpf_cnpj'],
                'email'         => $cliente['email'] ?? '',
                'phone'         => $cliente['telefone'] ?? '',
                'postalCode'    => $cliente['endereco']['cep'] ?? '',
                'addressNumber' => $cliente['endereco']['numero'] ?? '',
            ];
            $payload['installmentCount'] = $params['cartao']['parcelas'] ?? 1;
        }

        $raw = $this->request('POST', 'payments', $payload);

        return $this->normalizeCharge($raw, $modalidade);
    }

    // ── cancelCharge ──────────────────────────────────────

    public function cancelCharge(string $gatewayChargeId, array $params = []): array
    {
        $estorno = $params['estorno'] ?? false;

        if ($estorno) {
            $raw = $this->request('POST', "payments/{$gatewayChargeId}/refund", [
                'value'       => $params['valor'] ?? null,
                'description' => $params['motivo'] ?? 'Estorno solicitado',
            ]);
            $status = 'estornado';
        } else {
            $raw = $this->request('DELETE', "payments/{$gatewayChargeId}");
            $status = 'cancelado';
        }

        return [
            'success' => true,
            'status'  => $status,
            'raw'     => $raw,
        ];
    }

    // ── getStatus ─────────────────────────────────────────

    public function getStatus(string $gatewayChargeId): array
    {
        $raw = $this->request('GET', "payments/{$gatewayChargeId}");

        return [
            'gateway_charge_id' => $raw['id'],
            'status'            => self::STATUS_MAP[$raw['status']] ?? 'pendente',
            'valor_pago'        => $raw['value'] ?? null,
            'data_pagamento'    => $raw['paymentDate'] ?? null,
            'raw'               => $raw,
        ];
    }

    // ── handleWebhook ─────────────────────────────────────

    public function handleWebhook(array $payload, array $headers): array
    {
        // Validar token do webhook
        $tokenHeader = $headers['asaas-access-token']
            ?? $headers['HTTP_ASAAS_ACCESS_TOKEN']
            ?? '';

        if ($this->webhookToken && $tokenHeader !== $this->webhookToken) {
            throw new WebhookAuthException('Token de webhook Asaas inválido.');
        }

        $event   = $payload['event']   ?? '';
        $payment = $payload['payment'] ?? [];

        if (empty($payment['id'])) {
            throw new GatewayException('Webhook Asaas sem payment.id.');
        }

        return [
            'evento'            => self::EVENT_MAP[$event] ?? 'outro',
            'gateway_charge_id' => $payment['id'],
            'status'            => self::STATUS_MAP[$payment['status'] ?? ''] ?? 'pendente',
            'valor_pago'        => $payment['value'] ?? null,
            'data_pagamento'    => $payment['paymentDate'] ?? null,
            'raw'               => $payload,
        ];
    }

    // ── modalidadesSuportadas ─────────────────────────────

    public function modalidadesSuportadas(): array
    {
        return ['boleto', 'pix', 'cartao'];
    }

    // ── testarConexao ─────────────────────────────────────

    public function testarConexao(): array
    {
        $start = microtime(true);

        try {
            $this->request('GET', 'myAccount');
            $ms = (int) round((microtime(true) - $start) * 1000);

            return [
                'success'    => true,
                'message'    => 'Conexão com Asaas estabelecida com sucesso.',
                'latency_ms' => $ms,
            ];
        } catch (GatewayException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    // ── Privados ──────────────────────────────────────────

    /**
     * Cria ou atualiza cliente no Asaas e retorna o customerId.
     */
    private function upsertCustomer(array $cliente): string
    {
        // Buscar por CPF/CNPJ
        $cpfCnpj = preg_replace('/\D/', '', $cliente['cpf_cnpj']);
        $result  = $this->request('GET', "customers?cpfCnpj={$cpfCnpj}&limit=1");

        if (!empty($result['data'][0]['id'])) {
            return $result['data'][0]['id'];
        }

        // Criar novo
        $novo = $this->request('POST', 'customers', [
            'name'    => $cliente['nome'],
            'cpfCnpj' => $cpfCnpj,
            'email'   => $cliente['email']   ?? null,
            'phone'   => $cliente['telefone'] ?? null,
        ]);

        return $novo['id'];
    }

    /**
     * Normaliza a resposta de criação de cobrança.
     *
     * @param  array<string, mixed> $raw
     */
    private function normalizeCharge(array $raw, string $modalidade): array
    {
        $result = [
            'gateway_charge_id' => $raw['id'],
            'status'            => self::STATUS_MAP[$raw['status']] ?? 'pendente',
            'vencimento'        => $raw['dueDate'],
            'gateway_url'       => $raw['invoiceUrl'] ?? null,
            'raw'               => $raw,
        ];

        // Pix
        if ($modalidade === 'pix') {
            $result['pix_copia_cola']  = $raw['pixCopiaECola']    ?? null;
            $result['pix_qrcode_url']  = $raw['pixQrCodeUrl']     ?? null;
        }

        // Boleto
        if ($modalidade === 'boleto') {
            $result['boleto_linha'] = $raw['bankSlipUrl'] ?? null;
            $result['boleto_url']   = $raw['bankSlipUrl'] ?? null;
        }

        return $result;
    }

    /**
     * Mapeia modalidade interna → billingType do Asaas.
     */
    private function mapModalidade(string $modalidade): string
    {
        return match ($modalidade) {
            'boleto' => 'BOLETO',
            'pix'    => 'PIX',
            'cartao' => 'CREDIT_CARD',
            'ted'    => 'TRANSFER',
            default  => throw new GatewayException("Modalidade '{$modalidade}' não suportada pelo Asaas."),
        };
    }

    /**
     * Executa requisição HTTP contra a API do Asaas.
     *
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     * @throws GatewayException
     */
    private function request(string $method, string $endpoint, array $body = []): array
    {
        try {
            $options = [];
            if (!empty($body)) {
                $options['json'] = $body;
            }

            $response = $this->http->request($method, $endpoint, $options);
            $content  = $response->getBody()->getContents();

            return json_decode($content, true) ?? [];

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $message  = 'Erro na comunicação com o Asaas.';

            if ($response) {
                $body = json_decode($response->getBody()->getContents(), true);
                $message = $body['errors'][0]['description']
                    ?? $body['message']
                    ?? $message;
            }

            throw new GatewayException("[Asaas] {$message}", (int) ($response?->getStatusCode() ?? 0), $e);
        }
    }
}
