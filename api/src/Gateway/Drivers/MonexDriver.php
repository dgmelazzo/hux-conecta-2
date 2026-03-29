<?php

declare(strict_types=1);

namespace CRM\Gateway\Drivers;

use CRM\Gateway\Contracts\GatewayInterface;
use CRM\Shared\Exceptions\GatewayException;

/**
 * MonexDriver — Driver PAL para o gateway próprio HUX (Monex)
 *
 * STATUS: Fase 3 — em negociação com a equipe HUX.
 *
 * A estrutura já está preparada para implementação quando
 * as especificações da API Monex estiverem definidas.
 * O contrato da PAL garante que nenhuma linha do core
 * do CRM precisará ser alterada quando este driver for ativado.
 */
class MonexDriver implements GatewayInterface
{
    public function __construct(
        private readonly string  $apiKey,
        private readonly ?string $apiSecret,
        private readonly string  $ambiente,
        private readonly ?string $webhookToken,
    ) {
        // Implementação pendente — aguardando especificações Monex/HUX
    }

    public function createCharge(array $params): array
    {
        $this->notImplemented();
    }

    public function cancelCharge(string $gatewayChargeId, array $params = []): array
    {
        $this->notImplemented();
    }

    public function getStatus(string $gatewayChargeId): array
    {
        $this->notImplemented();
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        $this->notImplemented();
    }

    public function modalidadesSuportadas(): array
    {
        return [];     // a definir com a equipe Monex
    }

    public function testarConexao(): array
    {
        return [
            'success' => false,
            'message' => 'Driver Monex ainda não implementado (Fase 3). Aguardando especificações da API.',
        ];
    }

    private function notImplemented(): never
    {
        throw new GatewayException(
            'O driver Monex (HUX) ainda não está disponível. ' .
            'Previsto para a Fase 3 do Conecta CRM. ' .
            'Selecione Asaas ou PagSeguro como gateway ativo.'
        );
    }
}
