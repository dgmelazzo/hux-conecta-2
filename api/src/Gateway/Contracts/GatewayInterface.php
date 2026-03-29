<?php

declare(strict_types=1);

namespace CRM\Gateway\Contracts;

/**
 * Payment Abstraction Layer — Contrato principal
 *
 * Todo driver de gateway DEVE implementar esta interface.
 * O core do CRM nunca fala diretamente com um gateway —
 * apenas com esta interface. Trocar de gateway = trocar o driver.
 *
 * Métodos obrigatórios (conforme planejamento HUX):
 *   createCharge()   — cria cobrança (boleto, pix, cartão)
 *   cancelCharge()   — cancela ou estorna
 *   getStatus()      — consulta status atual
 *   handleWebhook()  — processa notificação assíncrona
 */
interface GatewayInterface
{
    // ── Criação ────────────────────────────────────────────

    /**
     * Cria uma nova cobrança no gateway.
     *
     * @param  array{
     *   modalidade:      'boleto'|'pix'|'cartao'|'ted',
     *   valor:           float,
     *   vencimento:      string,          // Y-m-d
     *   referencia:      string,          // ex: "Taxa MEI Jan/2026"
     *   descricao?:      string,
     *   cliente: array{
     *     nome:          string,
     *     cpf_cnpj:      string,
     *     email?:        string,
     *     telefone?:     string,
     *     endereco?:     array<string, string>,
     *   },
     *   cartao?: array{  // apenas modalidade=cartao
     *     numero:        string,
     *     nome_titular:  string,
     *     validade:      string,          // MM/YY
     *     cvv:           string,
     *     parcelas:      int,
     *   },
     *   metadata?:       array<string, mixed>,
     * } $params
     *
     * @return array{
     *   gateway_charge_id: string,        // ID único no gateway
     *   status:            string,        // pendente|pago|falhou
     *   gateway_url?:      string,        // link de pagamento (boleto/pix)
     *   pix_copia_cola?:   string,        // código Pix copia e cola
     *   pix_qrcode_url?:   string,        // URL da imagem QR Code
     *   boleto_linha?:     string,        // linha digitável
     *   boleto_url?:       string,        // URL do PDF do boleto
     *   vencimento:        string,        // Y-m-d
     *   raw:               array<string, mixed>, // payload bruto do gateway
     * }
     *
     * @throws \CRM\Shared\Exceptions\GatewayException
     */
    public function createCharge(array $params): array;

    // ── Cancelamento ───────────────────────────────────────

    /**
     * Cancela ou estorna uma cobrança existente.
     *
     * @param  string $gatewayChargeId   ID retornado pelo gateway em createCharge()
     * @param  array{
     *   motivo?:   string,
     *   estorno?:  bool,               // true = estorno de valor já pago
     *   valor?:    float,              // estorno parcial (se suportado)
     * } $params
     *
     * @return array{
     *   success:   bool,
     *   status:    string,             // cancelado|estornado
     *   raw:       array<string, mixed>,
     * }
     *
     * @throws \CRM\Shared\Exceptions\GatewayException
     */
    public function cancelCharge(string $gatewayChargeId, array $params = []): array;

    // ── Consulta ───────────────────────────────────────────

    /**
     * Consulta o status atual de uma cobrança no gateway.
     *
     * @param  string $gatewayChargeId
     *
     * @return array{
     *   gateway_charge_id: string,
     *   status:            'pendente'|'pago'|'cancelado'|'estornado'|'falhou'|'expirado',
     *   valor_pago?:       float,
     *   data_pagamento?:   string,     // Y-m-d H:i:s
     *   raw:               array<string, mixed>,
     * }
     *
     * @throws \CRM\Shared\Exceptions\GatewayException
     */
    public function getStatus(string $gatewayChargeId): array;

    // ── Webhook ────────────────────────────────────────────

    /**
     * Valida e processa um webhook recebido do gateway.
     *
     * Responsabilidades:
     *   1. Validar autenticidade (assinatura/token)
     *   2. Normalizar o payload para formato interno
     *   3. Retornar evento padronizado
     *
     * @param  array<string, mixed> $payload   Payload decodificado (JSON → array)
     * @param  array<string, string> $headers  Headers HTTP da requisição
     *
     * @return array{
     *   evento:            'pagamento_confirmado'|'pagamento_cancelado'|'estorno'|'vencido'|'outro',
     *   gateway_charge_id: string,
     *   status:            string,
     *   valor_pago?:       float,
     *   data_pagamento?:   string,
     *   raw:               array<string, mixed>,
     * }
     *
     * @throws \CRM\Shared\Exceptions\WebhookAuthException  Assinatura inválida
     * @throws \CRM\Shared\Exceptions\GatewayException      Payload malformado
     */
    public function handleWebhook(array $payload, array $headers): array;

    // ── Suporte ────────────────────────────────────────────

    /**
     * Retorna as modalidades suportadas por este driver.
     *
     * @return array<'boleto'|'pix'|'cartao'|'ted'>
     */
    public function modalidadesSuportadas(): array;

    /**
     * Testa a conexão com o gateway usando as credenciais configuradas.
     * Usado no painel de configuração de gateway.
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   latency_ms?: int,
     * }
     */
    public function testarConexao(): array;
}
