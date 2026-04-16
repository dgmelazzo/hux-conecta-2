<?php
/**
 * Conecta 2.0 — API Proxy (CNPJ, CEP)
 * Resolve CORS fazendo a consulta server-side.
 */
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit; }

$action = $_GET["action"] ?? "";

// CNPJ lookup via BrasilAPI (server-side, sem CORS)
if ($action === "cnpj") {
    $doc = preg_replace("/\D/", "", $_GET["doc"] ?? "");
    if (strlen($doc) !== 14) { http_response_code(400); echo json_encode(["error" => "CNPJ invalido"]); exit; }

    $ch = curl_init("https://brasilapi.com.br/api/cnpj/v1/" . $doc);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => "ConectaACIC/1.0",
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    http_response_code($code >= 200 && $code < 400 ? 200 : $code);
    echo $resp ?: json_encode(["error" => "CNPJ nao encontrado"]);
    exit;
}

// CEP lookup via ViaCEP (fallback server-side)
if ($action === "cep") {
    $cep = preg_replace("/\D/", "", $_GET["doc"] ?? "");
    if (strlen($cep) !== 8) { http_response_code(400); echo json_encode(["error" => "CEP invalido"]); exit; }

    $ch = curl_init("https://viacep.com.br/ws/" . $cep . "/json/");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $resp = curl_exec($ch);
    curl_close($ch);

    echo $resp ?: json_encode(["error" => "CEP nao encontrado"]);
    exit;
}

http_response_code(400);
echo json_encode(["error" => "Acao invalida"]);
