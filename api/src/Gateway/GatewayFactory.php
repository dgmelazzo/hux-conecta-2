<?php

declare(strict_types=1);

namespace CRM\Gateway;

use CRM\Gateway\Contracts\GatewayInterface;
use CRM\Gateway\Drivers\AsaasDriver;
use CRM\Gateway\Drivers\PagSeguroDriver;
use CRM\Gateway\Drivers\MonexDriver;
use CRM\Shared\Exceptions\GatewayException;
use CRM\Shared\Helpers\Crypto;

/**
 * GatewayFactory
 *
 * Resolve o driver de gateway ativo para o tenant corrente.
 * Lê a configuração de `gateway_configs` (banco), descriptografa
 * as credenciais e instancia o driver correto.
 *
 * Uso:
 *   $gateway = GatewayFactory::make($pdo, $tenantId);
 *   $charge  = $gateway->createCharge([...]);
 */
class GatewayFactory
{
    /** @var array<string, class-string<GatewayInterface>> */
    private static array $drivers = [
        'asaas'       => AsaasDriver::class,
        'pagseguro'   => PagSeguroDriver::class,
        'monex'       => MonexDriver::class,
    ];

    /**
     * Instancia o driver ativo para o tenant.
     *
     * @throws GatewayException  Nenhum gateway ativo ou driver desconhecido
     */
    public static function make(\PDO $pdo, int $tenantId): GatewayInterface
    {
        $config = self::fetchActiveConfig($pdo, $tenantId);

        if (!$config) {
            throw new GatewayException(
                "Nenhum gateway de pagamento ativo para o tenant #{$tenantId}. " .
                "Configure um gateway no painel de configurações."
            );
        }

        $gateway = $config['gateway'];

        if (!isset(self::$drivers[$gateway])) {
            throw new GatewayException("Driver '{$gateway}' não registrado na PAL.");
        }

        $credentials = self::decryptCredentials($config);
        $driverClass  = self::$drivers[$gateway];

        return new $driverClass(
            apiKey:    $credentials['api_key'],
            apiSecret: $credentials['api_secret'] ?? null,
            ambiente:  $config['ambiente'],
            webhookToken: $config['webhook_token'] ?? null,
        );
    }

    /**
     * Instancia um driver específico — usado ao configurar/testar gateway.
     *
     * @throws GatewayException
     */
    public static function makeDriver(
        string $gateway,
        string $apiKey,
        ?string $apiSecret = null,
        string $ambiente = 'sandbox',
        ?string $webhookToken = null,
    ): GatewayInterface {
        if (!isset(self::$drivers[$gateway])) {
            throw new GatewayException("Driver '{$gateway}' desconhecido.");
        }

        $driverClass = self::$drivers[$gateway];

        return new $driverClass(
            apiKey:       $apiKey,
            apiSecret:    $apiSecret,
            ambiente:     $ambiente,
            webhookToken: $webhookToken,
        );
    }

    /**
     * Lista os gateways disponíveis com metadados.
     *
     * @return array<string, array{label: string, modalidades: string[], fase: int}>
     */
    public static function available(): array
    {
        return [
            'asaas' => [
                'label'       => 'Asaas',
                'modalidades' => ['boleto', 'pix', 'cartao'],
                'fase'        => 1,
            ],
            'pagseguro' => [
                'label'       => 'PagSeguro',
                'modalidades' => ['pix', 'cartao'],
                'fase'        => 1,
            ],
            'stripe' => [
                'label'       => 'Stripe',
                'modalidades' => ['cartao'],
                'fase'        => 2,
            ],
            'mercadopago' => [
                'label'       => 'Mercado Pago',
                'modalidades' => ['pix', 'cartao'],
                'fase'        => 2,
            ],
            'monex' => [
                'label'       => 'Monex (HUX)',
                'modalidades' => [],            // a definir na fase 3
                'fase'        => 3,
            ],
        ];
    }

    // ── Privados ───────────────────────────────────────────

    /**
     * Busca a configuração de gateway ativa para o tenant.
     *
     * @return array<string, mixed>|null
     */
    private static function fetchActiveConfig(\PDO $pdo, int $tenantId): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM gateway_configs
              WHERE tenant_id = :tenant_id
                AND ativo     = 1
              LIMIT 1'
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Descriptografa api_key e api_secret usando AES-256.
     *
     * @param  array<string, mixed> $config
     * @return array{api_key: string, api_secret: ?string}
     */
    private static function decryptCredentials(array $config): array
    {
        return [
            'api_key'    => Crypto::decrypt((string) $config['api_key']),
            'api_secret' => $config['api_secret']
                ? Crypto::decrypt((string) $config['api_secret'])
                : null,
        ];
    }
}
