<?php

declare(strict_types=1);

namespace CRM\Gateway\Drivers;

use CRM\Gateway\Contracts\GatewayInterface;
use CRM\Shared\Exceptions\GatewayException;
use CRM\Shared\Exceptions\WebhookAuthException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * PagSeguroDriver — Implementação PAL para o PagSeguro (API v4)
 *
 * Documentação: https://dev.pagbank.uol.com.br/reference
 * Suporta: pix, cartão
 */
class PagSeguroDriver implements GatewayInterface
{
    private Client $http;

    private const BASE_URL_PROD    = 'https://api.pagseguro.com';
    private const BASE_URL_SANDBOX = 'https://sandbox.api.pagseguro.com';

    /** Mapa status PagSeguro → status interno CRM */
    private const STATUS_MAP = [
        'AUTHORIZED'        => 'pendente',
        'IN_ANALYSIS'       => 'pendente',
        'PAID'              => 'pago',
        'AVAILABLE'         => 'pago',
        'DISPUTED'          => 'cancelado',
        'REFUNDED'          => 'estornado',
        'CANCELLED'         => 'cancelado',
        'DECLINED'          => 'falhou',
        'WAITING'           => 'pendente',
    ];

    /** Mapa evento webhook PagSeguro → evento interno CRM */
    private const EVENT_MAP = [
        'CHARGE_PAID'       => 'pagamento_confirmado',
        'CHARGE_CANCELED'   => 'pagamento_cancelado',
        'CHARGE_REFUNDED'   => 'estorno',
        'CHARGE_EXPIRED'    => 'vencido',
        'CHARGE_DECLINED'   => 'pagamento_cancelado',
    ];

    public function __construct(
        private readonly string  $apiKey,
        private readonly ?string $apiSecret,
        private readonly string  $ambiente,
        private readonly ?string $webhookToken,
    ) {
        $baseUrl = $ambiente === 'producao'
            ? self::BASE_URL_PROD
            : self::BASE_URL_SANDBOX;

        $this->http = new Client([
            'base_uri' => $baseUrl . '/',
            'timeout'  => 30,
            'headers'  => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ]);
    }

    // ── createCharge ──────────────────────────────────────

    public function createCharge(array $params): array
    {
        $modalidade = $params['modalidade'];
        $cliente    = $params['cliente'];

        $payload = [
            'reference_id'    => $params['metadata']['cobranca_id'] ?? uniqid('crm_'),
            'description'     => $params['referencia'] ?? ($params['descricao'] ?? 'Cobrança'),
            'amount'          => [
                'value'    => (int) round($params['valor'] * 100),  // PagSeguro usa centavos
                'currency' => 'BRL',
            ],
            'payment_method'  => $this->buildPaymentMethod($modalidade, $params),
            'customer'        => [
                'name'    => $cliente['nome'],
                'tax_id'  => preg_replace('/\D/', '', $cliente['cpf_cnpj']),
                'email'   => $cliente['email'] ?? null,
                'phones'  => $cliente['telefone']
                    ? [['country' => '55', 'area' => substr($cliente['telefone'], 0, 2),
                         'number' => substr($cliente['telefone'], 2), 'type' => 'MOBILE']]
                    : [],
            ],
            'expiration_date' => $this->formatExpiration($params['vencimento']),
        ];

        $raw = $this->request('POST', 'charges', $payload);

        return $this->normalizeCharge($raw, $modalidade);
    }

    // ── cancelCharge ─────────────────────────────────────

    public function cancelCharge(string $gatewayChargeId, array $params = []): array
    {
        $estorno = $params['estorno'] ?? false;

        if ($estorno) {
            $body = ['amount' => ['value' => (int) round(($params['valor'] ?? 0) * 100)]];
            $raw  = $this->request('POST', "charges/{$gatewayChargeId}/cancel", $body);
            $status = 'estornado';
        } else {
            $raw    = $this->request('POST', "charges/{$gatewayChargeId}/cancel");
            $status = 'cancelado';
        }

        return [
            'success' => true,
            'status'  => $status,
            'raw'     => $raw,
        ];
    }

    // ── getStatus ────────────────────────────────────────

    public function getStatus(string $gatewayChargeId): array
    {
        $raw = $this->request('GET', "charges/{$gatewayChargeId}");

        $valorPago = null;
        if (isset($raw['amount']['value'])) {
            $valorPago = round($raw['amount']['value'] / 100, 2);
        }

        return [
            'gateway_charge_id' => $raw['id'],
            'status'            => self::STATUS_MAP[$raw['status']] ?? 'pendente',
            'valor_pago'        => $valorPago,
            'data_pagamento'    => $raw['paid_at'] ?? null,
            'raw'               => $raw,
        ];
    }

    // ── handleWebhook ────────────────────────────────────

    public function handleWebhook(array $payload, array $headers): array
    {
        // Validar token
        $tokenHeader = $headers['x-webhook-token']
            ?? $headers['HTTP_X_WEBHOOK_TOKEN']
            ?? '';

        if ($this->webhookToken && $tokenHeader !== $this->webhookToken) {
            throw new WebhookAuthException('Token de webhook PagSeguro inválido.');
        }

        $event  = $payload['type'] ?? '';
        $charge = $payload['data']['charge'] ?? $payload['data'] ?? [];
        $id     = $charge['id'] ?? '';

        if (empty($id)) {
            throw new GatewayException('Webhook PagSeguro sem charge.id.');
        }

        $valorPago = null;
        if (isset($charge['amount']['paid']['value'])) {
            $valorPago = round($charge['amount']['paid']['value'] / 100, 2);
        }

        return [
            'evento'            => self::EVENT_MAP[$event] ?? 'outro',
            'gateway_charge_id' => $id,
            'status'            => self::STATUS_MAP[$charge['status'] ?? ''] ?? 'pendente',
            'valor_pago'        => $valorPago,
            'data_pagamento'    => $charge['paid_at'] ?? null,
            'raw'               => $payload,
        ];
    }

    // ── modalidadesSuportadas ────────────────────────────

    public function modalidadesSuportadas(): array
    {
        return ['pix', 'cartao'];
    }

    // ── testarConexao ────────────────────────────────────

    public function testarConexao(): array
    {
        $start = microtime(true);

        try {
            // Endpoint leve para validar autenticação
            $this->request('GET', 'charges?limit=1');
            $ms = (int) round((microtime(true) - $start) * 1000);

            return [
                'success'    => true,
                'message'    => 'Conexão com PagSeguro estabelecida com sucesso.',
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
     * Monta o objeto payment_method conforme modalidade.
     *
     * @param  array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function buildPaymentMethod(string $modalidade, array $params): array
    {
        return match ($modalidade) {
            'pix' => [
                'type'           => 'PIX',
                'installments'   => 1,
                'capture'        => true,
            ],
            'cartao' => [
                'type'         => 'CREDIT_CARD',
                'installments' => $params['cartao']['parcelas'] ?? 1,
                'capture'      => true,
                'card'         => [
                    'number'          => $params['cartao']['numero'],
                    'exp_month'       => explode('/', $params['cartao']['validade'])[0],
                    'exp_year'        => '20' . explode('/', $params['cartao']['validade'])[1],
                    'security_code'   => $params['cartao']['cvv'],
                    'holder'          => [
                        'name'        => $params['cartao']['nome_titular'],
                    ],
                ],
            ],
            default => throw new GatewayException("Modalidade '{$modalidade}' não suportada pelo PagSeguro."),
        };
    }

    /**
     * Converte Y-m-d para ISO 8601 (PagSeguro exige T23:59:59-03:00).
     */
    private function formatExpiration(string $date): string
    {
        return $date . 'T23:59:59-03:00';
    }

    /**
     * Normaliza resposta de criação de cobrança.
     *
     * @param  array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function normalizeCharge(array $raw, string $modalidade): array
    {
        $result = [
            'gateway_charge_id' => $raw['id'],
            'status'            => self::STATUS_MAP[$raw['status'] ?? ''] ?? 'pendente',
            'vencimento'        => substr($raw['expiration_date'] ?? '', 0, 10),
            'gateway_url'       => $raw['links'][0]['href'] ?? null,
            'raw'               => $raw,
        ];

        // Pix
        if ($modalidade === 'pix') {
            $qr = $raw['qr_codes'][0] ?? [];
            $result['pix_copia_cola'] = $qr['text']         ?? null;
            $result['pix_qrcode_url'] = $qr['links'][0]['href'] ?? null;
        }

        return $result;
    }

    /**
     * Executa requisição HTTP.
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
            $message  = 'Erro na comunicação com o PagSeguro.';

            if ($response) {
                $body    = json_decode($response->getBody()->getContents(), true);
                $message = $body['error_messages'][0]['description']
                    ?? $body['message']
                    ?? $message;
            }

            throw new GatewayException("[PagSeguro] {$message}", (int) ($response?->getStatusCode() ?? 0), $e);
        }
    }
}
