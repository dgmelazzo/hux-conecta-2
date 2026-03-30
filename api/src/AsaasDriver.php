<?php
class AsaasDriver {
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $ambiente = 'sandbox') {
        $this->apiKey  = $apiKey;
        $this->baseUrl = $ambiente === 'producao'
            ? 'https://api.asaas.com/v3'
            : 'https://sandbox.asaas.com/api/v3';
    }

    private function req(string $method, string $path, array $data = []): array {
        $ch = curl_init();
        $url = $this->baseUrl . $path;
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'access_token: ' . $this->apiKey,
                'Content-Type: application/json',
                'User-Agent: ConectaCRM/1.0'
            ],
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($response, true) ?? [];
        $result['_httpCode'] = $httpCode;
        return $result;
    }

    // Criar ou buscar cliente no Asaas
    public function upsertCliente(array $associado): string {
        // Buscar por CPF/CNPJ
        $doc = preg_replace('/\D/', '', $associado['cnpj'] ?? $associado['cpf'] ?? '');
        if ($doc) {
            $r = $this->req('GET', "/customers?cpfCnpj=$doc");
            if (!empty($r['data'][0]['id'])) return $r['data'][0]['id'];
        }
        // Criar
        $r = $this->req('POST', '/customers', [
            'name'     => $associado['razao_social'] ?? $associado['nome_responsavel'] ?? 'Associado',
            'cpfCnpj'  => $doc,
            'email'    => $associado['email'] ?? '',
            'phone'    => preg_replace('/\D/', '', $associado['telefone'] ?? $associado['whatsapp'] ?? ''),
        ]);
        return $r['id'] ?? '';
    }

    // Criar cobrança PIX
    public function criarPix(string $clienteId, float $valor, string $descricao, string $vencimento): array {
        return $this->req('POST', '/payments', [
            'customer'    => $clienteId,
            'billingType' => 'PIX',
            'value'       => $valor,
            'dueDate'     => $vencimento,
            'description' => $descricao,
        ]);
    }

    // Criar cobrança Boleto
    public function criarBoleto(string $clienteId, float $valor, string $descricao, string $vencimento): array {
        return $this->req('POST', '/payments', [
            'customer'    => $clienteId,
            'billingType' => 'BOLETO',
            'value'       => $valor,
            'dueDate'     => $vencimento,
            'description' => $descricao,
        ]);
    }

    // Buscar QR Code PIX
    public function getPixQrCode(string $paymentId): array {
        return $this->req('GET', "/payments/$paymentId/pixQrCode");
    }

    // Status da cobrança
    public function getStatus(string $paymentId): array {
        return $this->req('GET', "/payments/$paymentId");
    }

    // Cancelar cobrança
    public function cancelar(string $paymentId): array {
        return $this->req('DELETE', "/payments/$paymentId");
    }

    // Webhook: processar evento
    public static function processarWebhook(array $payload): array {
        return [
            'evento'     => $payload['event'] ?? '',
            'payment_id' => $payload['payment']['id'] ?? '',
            'status'     => $payload['payment']['status'] ?? '',
            'valor'      => $payload['payment']['value'] ?? 0,
            'pago_em'    => $payload['payment']['paymentDate'] ?? null,
        ];
    }
}
