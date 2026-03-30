<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://crm.acicdf.org.br');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Tenant');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

date_default_timezone_set('America/Sao_Paulo');

function pdo(): PDO {
    static $conn = null;
    if ($conn) return $conn;
    $conn = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_NAME'] ?? 'conecta_crm'
        ),
        $_ENV['DB_USER'] ?? 'root',
        $_ENV['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]
    );
    return $conn;
}

function ok(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function err(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function body(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $raw = file_get_contents('php://input');
    $cache = json_decode($raw ?: '{}', true) ?? [];
    return $cache;
}

function one(string $sql, array $params = []): array|false {
    $st = pdo()->prepare($sql);
    $st->execute($params);
    return $st->fetch();
}

function many(string $sql, array $params = []): array {
    $st = pdo()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function run(string $sql, array $params = []): int {
    $st = pdo()->prepare($sql);
    $st->execute($params);
    return (int) pdo()->lastInsertId();
}

function paginate(): array {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = min(100, max(10, (int)($_GET['per'] ?? $_GET['limit'] ?? 20)));
    return [$page, $per, ($page - 1) * $per];
}

function jwtSecret(): string {
    return $_ENV['JWT_SECRET'] ?? 'changeme_secret_32chars_minimum!!';
}

function jwtEncode(array $payload): string {
    $header  = base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url(json_encode($payload));
    $sig     = base64url(hash_hmac('sha256', "$header.$payload", jwtSecret(), true));
    return "$header.$payload.$sig";
}

function jwtDecode(string $token): array|false {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    [$header, $payload, $sig] = $parts;
    $expected = base64url(hash_hmac('sha256', "$header.$payload", jwtSecret(), true));
    if (!hash_equals($expected, $sig)) return false;
    $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    if (!$data || ($data['exp'] ?? 0) < time()) return false;
    return $data;
}

function base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function auth(): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($header, 'Bearer ')) err('Token ausente', 401);
    $token = substr($header, 7);
    $data  = jwtDecode($token);
    if (!$data) err('Token inválido ou expirado', 401);
    return $data;
}

function gestor(): array {
    $a = auth();
    if (!in_array($a['role'], ['gestor', 'superadmin'])) err('Sem permissão', 403);
    return $a;
}

function asaasReq(string $method, string $path, array $data = []): array {
    static $cfg = null;
    if (!$cfg) {
        $cfg = one("SELECT * FROM gateway_configs WHERE gateway = 'asaas' AND ativo = 1 LIMIT 1");
        if (!$cfg) err('Gateway Asaas não configurado', 500);
    }
    $sandbox = ($cfg['ambiente'] ?? 'sandbox') !== 'producao';
    $baseUrl = $sandbox ? 'https://sandbox.asaas.com/api/v3' : 'https://api.asaas.com/v3';
    $apiKey  = $cfg['api_key'];
    $ch  = curl_init();
    $url = $baseUrl . $path;
    curl_setopt_array($ch, [
        CURLOPT_URL=>$url, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','access_token: '.$apiKey,'User-Agent: ConectaCRM/1.0'],
        CURLOPT_SSL_VERIFYPEER=>true,
    ]);
    switch (strtoupper($method)) {
        case 'POST': curl_setopt($ch,CURLOPT_POST,true); curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data)); break;
        case 'PUT':  curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'PUT'); curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data)); break;
        case 'DELETE': curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'DELETE'); break;
        default: if(!empty($data)) curl_setopt($ch,CURLOPT_URL,$url.'?'.http_build_query($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    if ($curlErr) err('Erro na comunicação com Asaas: '.$curlErr, 502);
    $result = json_decode($response, true);
    if ($httpCode >= 400) {
        $msg = $result['errors'][0]['description'] ?? $result['message'] ?? 'Erro desconhecido no Asaas';
        err('Asaas: '.$msg, $httpCode >= 500 ? 502 : 422);
    }
    return $result ?? [];
}

function docAssociado(array $a): string { return preg_replace('/\D/','', $a['cnpj'] ?? $a['cpf'] ?? ''); }
function nomeAssociado(array $a): string { return $a['razao_social'] ?? $a['nome_fantasia'] ?? $a['nome_responsavel'] ?? 'Associado'; }

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];
$uri    = preg_replace('#^/api#', '', $uri) ?: '/';

// ROTAS PÚBLICAS
if ($uri === '/health' && $method === 'GET') ok(['status'=>'ok','ts'=>date('c')]);

if ($uri === '/auth/login' && $method === 'POST') {
    $b = body();
    $email = trim($b['email'] ?? ''); $senha = trim($b['senha'] ?? '');
    if (!$email || !$senha) err('E-mail e senha são obrigatórios');
    $u = one('SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1', [$email]);
    if (!$u || !password_verify($senha, $u['senha_hash'])) err('Credenciais inválidas', 401);
    run('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?', [$u['id']]);
    $token = jwtEncode(['sub'=>$u['id'],'nome'=>$u['nome'],'email'=>$u['email'],'role'=>$u['role'],'tenant_id'=>$u['tenant_id'],'iat'=>time(),'exp'=>time()+86400]);
    ok(['token'=>$token,'user'=>['id'=>$u['id'],'nome'=>$u['nome'],'email'=>$u['email'],'role'=>$u['role'],'tenant_id'=>$u['tenant_id']]]);
}

if ($uri === '/auth/refresh' && $method === 'POST') {
    $a = auth();
    $u = one('SELECT id,nome,email,role,tenant_id FROM usuarios WHERE id = ? AND ativo = 1', [$a['sub']]);
    if (!$u) err('Usuário não encontrado', 401);
    ok(['token'=>jwtEncode(['sub'=>$u['id'],'nome'=>$u['nome'],'email'=>$u['email'],'role'=>$u['role'],'tenant_id'=>$u['tenant_id'],'iat'=>time(),'exp'=>time()+86400])]);
}

if ($uri === '/auth/me' && $method === 'GET') {
    $a = auth();
    $u = one('SELECT id,nome,email,role,tenant_id FROM usuarios WHERE id = ?', [$a['sub']]);
    if (!$u) err('Não encontrado', 404);
    ok($u);
}

// DASHBOARD
if ($uri === '/dashboard' && $method === 'GET') {
    $a = auth(); $tid = $a['tenant_id'];
    $totalAssoc = one("SELECT COUNT(*) n FROM associados WHERE tenant_id=? AND status='ativo'",[$tid])['n']??0;
    $totalPago  = one("SELECT COALESCE(SUM(valor),0) n FROM cobrancas WHERE tenant_id=? AND status='pago' AND MONTH(criado_em)=MONTH(NOW()) AND YEAR(criado_em)=YEAR(NOW())",[$tid])['n']??0;
    $vencidas   = one("SELECT COUNT(*) n FROM cobrancas WHERE tenant_id=? AND status IN ('vencido','expirado')",[$tid])['n']??0;
    $pendentes  = one("SELECT COUNT(*) n FROM cobrancas WHERE tenant_id=? AND status='pendente'",[$tid])['n']??0;
    $prospecto  = one("SELECT COUNT(*) n FROM associados WHERE tenant_id=? AND status='prospecto'",[$tid])['n']??0;
    ok(['kpis'=>[
        ['label'=>'Associados Ativos','value'=>(int)$totalAssoc,'color'=>'b','icon'=>'users'],
        ['label'=>'Receita do Mês','value'=>'R$ '.number_format((float)$totalPago,2,',','.'),'color'=>'g','icon'=>'dollar-sign'],
        ['label'=>'Cobranças Vencidas','value'=>(int)$vencidas,'color'=>'r','icon'=>'alert-circle'],
        ['label'=>'Cobranças Pendentes','value'=>(int)$pendentes,'color'=>'y','icon'=>'clock'],
        ['label'=>'Prospectos','value'=>(int)$prospecto,'color'=>'p','icon'=>'user-plus'],
    ],'associados_ativos'=>(int)$totalAssoc,'receita_mes'=>(float)$totalPago,'cobrancas_vencidas'=>(int)$vencidas,'cobrancas_pendentes'=>(int)$pendentes]);
}

// ASSOCIADOS
if ($uri === '/associados' && $method === 'GET') {
    $a = auth(); [$page,$per,$off] = paginate();
    $q = '%'.($_GET['q']??'').'%'; $st = $_GET['status']??''; $tid = $a['tenant_id'];
    $where = 'tenant_id=? AND (razao_social LIKE ? OR nome_fantasia LIKE ? OR nome_responsavel LIKE ? OR email LIKE ? OR cnpj LIKE ? OR cpf LIKE ?)';
    $params = [$tid,$q,$q,$q,$q,$q,$q];
    if ($st) { $where .= ' AND status=?'; $params[] = $st; }
    $cat = $_GET['categoria']??''; if ($cat) { $where .= ' AND categoria=?'; $params[] = $cat; }
    $total = one("SELECT COUNT(*) n FROM associados WHERE $where",$params)['n']??0;
    $params[] = $per; $params[] = $off;
    $rows = many("SELECT id,tipo_pessoa,categoria,vinculo_id,razao_social,nome_fantasia,nome_responsavel,cpf,cnpj,email,telefone,whatsapp,status,data_vencimento,plano_id,criado_em FROM associados WHERE $where ORDER BY COALESCE(razao_social,nome_responsavel) LIMIT ? OFFSET ?",$params);
    ok(['data'=>$rows,'total'=>(int)$total,'page'=>$page,'per'=>$per]);
}

if (preg_match('#^/associados/(\d+)$#',$uri,$m) && $method==='GET') {
    $a = auth();
    $row = one('SELECT * FROM associados WHERE id=? AND tenant_id=?',[$m[1],$a['tenant_id']]);
    if (!$row) err('Não encontrado',404);
    ok($row);
}

if ($uri === '/associados' && $method === 'POST') {
    $a = auth(); $b = body();
    $cpf = preg_replace('/\D/','',$b['cpf']??''); $cnpj = preg_replace('/\D/','',$b['cnpj']??'');
    if (!$cpf && !$cnpj) err('CPF ou CNPJ é obrigatório');
    if ($cnpj && one('SELECT id FROM associados WHERE cnpj=? AND tenant_id=?',[$cnpj,$a['tenant_id']])) err('CNPJ já cadastrado');
    if ($cpf  && one('SELECT id FROM associados WHERE cpf=? AND tenant_id=?', [$cpf, $a['tenant_id']])) err('CPF já cadastrado');
    $cat_val = $b['categoria']??'empresa';
    $vinc_val = isset($b['vinculo_id'])&&$b['vinculo_id'] ? (int)$b['vinculo_id'] : null;
    $id = run('INSERT INTO associados (tenant_id,plano_id,tipo_pessoa,categoria,vinculo_id,razao_social,nome_fantasia,nome_responsavel,cpf,cnpj,email,telefone,whatsapp,status,criado_por,criado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
        [$a['tenant_id'],$b['plano_id']??null,$b['tipo_pessoa']??'pj',$cat_val,$vinc_val,$b['razao_social']??null,$b['nome_fantasia']??null,$b['nome_responsavel']??null,
         $cpf?:null,$cnpj?:null,trim($b['email']??'')?:null,$b['telefone']??null,preg_replace('/\D/','',$b['whatsapp']??'')?:null,$b['status']??'prospecto',$a['sub']]);
    ok(one('SELECT * FROM associados WHERE id=?',[$id]),201);
}

if (preg_match('#^/associados/(\d+)$#',$uri,$m) && $method==='PUT') {
    $a = auth(); $b = body();
    if (!one('SELECT id FROM associados WHERE id=? AND tenant_id=?',[$m[1],$a['tenant_id']])) err('Não encontrado',404);
    run('UPDATE associados SET razao_social=COALESCE(?,razao_social),nome_fantasia=COALESCE(?,nome_fantasia),nome_responsavel=COALESCE(?,nome_responsavel),email=COALESCE(?,email),telefone=COALESCE(?,telefone),whatsapp=COALESCE(?,whatsapp),status=COALESCE(?,status),plano_id=COALESCE(?,plano_id) WHERE id=? AND tenant_id=?',
        [$b['razao_social']??null,$b['nome_fantasia']??null,$b['nome_responsavel']??null,$b['email']??null,$b['telefone']??null,$b['whatsapp']??null,$b['status']??null,$b['plano_id']??null,$m[1],$a['tenant_id']]);
    ok(one('SELECT * FROM associados WHERE id=?',[$m[1]]));
}

if (preg_match('#^/associados/(\d+)$#',$uri,$m) && $method==='DELETE') {
    $a = gestor();
    if (!one('SELECT id FROM associados WHERE id=? AND tenant_id=?',[$m[1],$a['tenant_id']])) err('Não encontrado',404);
    run('DELETE FROM associados WHERE id=? AND tenant_id=?',[$m[1],$a['tenant_id']]);
    ok(['deletado'=>true]);
}

// PLANOS
if ($uri === '/planos' && $method === 'GET') {
    $a = auth();
    $rows = many('SELECT * FROM planos WHERE tenant_id=? ORDER BY nome',[$a['tenant_id']]);
    ok(['data'=>$rows,'total'=>count($rows)]);
}

if ($uri === '/planos' && $method === 'POST') {
    $a = gestor(); $b = body();
    $nome = trim($b['nome']??'');
    if (!$nome) err('Nome do plano é obrigatório');
    $token = bin2hex(random_bytes(16));
    $id = run('INSERT INTO planos (tenant_id,nome,tipo,valor_mensal,valor_anual,periodicidade,ativo,link_token,criado_em) VALUES (?,?,?,?,?,?,1,?,NOW())',
        [$a['tenant_id'],$nome,$b['tipo']??'custom',$b['valor']??$b['valor_mensal']??0,$b['valor_anual']??null,$b['periodicidade']??'mensal',$token]);
    ok(one('SELECT * FROM planos WHERE id=?',[$id]),201);
}

if (preg_match('#^/planos/(\d+)$#',$uri,$m) && $method==='DELETE') {
    $a = gestor();
    if (!one('SELECT id FROM planos WHERE id=? AND tenant_id=?',[$m[1],$a['tenant_id']])) err('Não encontrado',404);
    run('DELETE FROM planos WHERE id=? AND tenant_id=?',[$m[1],$a['tenant_id']]);
    ok(['deletado'=>true]);
}

// COBRANÇAS
if ($uri === '/cobrancas' && $method === 'GET') {
    $a = auth(); [$page,$per,$off] = paginate();
    $st = $_GET['status']??''; $tid = $a['tenant_id'];
    $where = 'c.tenant_id=?'; $params = [$tid];
    if ($st) { $where .= ' AND c.status=?'; $params[] = $st; }
    $total = one("SELECT COUNT(*) n FROM cobrancas c WHERE $where",$params)['n']??0;
    $params[] = $per; $params[] = $off;
    $rows = many("SELECT c.*,COALESCE(a.razao_social,a.nome_fantasia,a.nome_responsavel) AS associado_nome FROM cobrancas c LEFT JOIN associados a ON a.id=c.associado_id WHERE $where ORDER BY c.criado_em DESC LIMIT ? OFFSET ?",$params);
    ok(['data'=>$rows,'total'=>(int)$total,'page'=>$page,'per'=>$per]);
}

if ($uri === '/cobrancas' && $method === 'POST') {
    $a = auth(); $b = body();
    $assocId = (int)($b['associado_id']??0);
    $assoc = one('SELECT * FROM associados WHERE id=? AND tenant_id=?',[$assocId,$a['tenant_id']]);
    if (!$assoc) err('Associado não encontrado',404);
    $doc = docAssociado($assoc);
    if (!$doc) err('Associado sem CPF/CNPJ');
    $existente = asaasReq('GET','/customers',['cpfCnpj'=>$doc]);
    $customerId = $existente['data'][0]['id'] ?? null;
    if (!$customerId) {
        $novo = asaasReq('POST','/customers',['name'=>nomeAssociado($assoc),'cpfCnpj'=>$doc,'email'=>$assoc['email']??'','phone'=>preg_replace('/\D/','',$assoc['telefone']??$assoc['whatsapp']??'')]);
        $customerId = $novo['id'];
    }
    $modalidade = strtolower($b['modalidade']??'pix');
    $billingType = match($modalidade){'boleto'=>'BOLETO','cartao'=>'CREDIT_CARD',default=>'PIX'};
    $valor = (float)($b['valor']??0); $dataVenc = $b['data_vencimento']??date('Y-m-d',strtotime('+3 days'));
    $descricao = $b['descricao']??'Taxa associativa';
    if ($valor <= 0) err('Valor inválido');
    $cob = asaasReq('POST','/payments',['customer'=>$customerId,'billingType'=>$billingType,'value'=>$valor,'dueDate'=>$dataVenc,'description'=>$descricao]);
    $pixQr = $pixCopia = null;
    if ($billingType==='PIX') { $px=asaasReq('GET',"/payments/{$cob['id']}/pixQrCode"); $pixQr=$px['encodedImage']??null; $pixCopia=$px['payload']??null; }
    $gatewayUrl = $cob['invoiceUrl']??$cob['bankSlipUrl']??null;
    $cobId = run('INSERT INTO cobrancas (tenant_id,associado_id,plano_id,gateway,gateway_charge_id,gateway_url,valor,modalidade,data_vencimento,status,descricao,criado_por,criado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
        [$a['tenant_id'],$assocId,$b['plano_id']??null,'asaas',$cob['id'],$gatewayUrl,$valor,$modalidade,$dataVenc,'pendente',$descricao,$a['sub']]);
    ok(['cobranca_id'=>$cobId,'gateway_id'=>$cob['id'],'status'=>'pendente','modalidade'=>$modalidade,'valor'=>$valor,'data_vencimento'=>$dataVenc,'pix_qr_code'=>$pixQr,'pix_copia_cola'=>$pixCopia,'gateway_url'=>$gatewayUrl]);
}

if (preg_match('#^/cobrancas/(\d+)/pagar$#',$uri,$m) && $method==='PATCH') {
    $a = gestor(); $b = body();
    $cob = one('SELECT * FROM cobrancas WHERE id=? AND tenant_id=?',[$m[1],$a['tenant_id']]);
    if (!$cob) err('Não encontrado',404);
    run('UPDATE cobrancas SET status="pago",valor_pago=?,data_pagamento=NOW() WHERE id=?',[(float)($b['valor_pago']??$cob['valor']),$m[1]]);
    ok(one('SELECT * FROM cobrancas WHERE id=?',[$m[1]]));
}

if (preg_match('#^/cobrancas/(\d+)/cancelar$#',$uri,$m) && $method==='PATCH') {
    $a = gestor();
    $cob = one('SELECT * FROM cobrancas WHERE id=? AND tenant_id=?',[$m[1],$a['tenant_id']]);
    if (!$cob) err('Não encontrado',404);
    if ($cob['gateway_charge_id']) { try { asaasReq('DELETE','/payments/'.$cob['gateway_charge_id']); } catch(\Throwable $e){} }
    run("UPDATE cobrancas SET status='cancelado' WHERE id=?",[$m[1]]);
    ok(['cancelado'=>true]);
}

// PIPELINE
if ($uri === '/pipeline' && $method === 'GET') {
    $a = auth(); $tid = $a['tenant_id'];
    $estagios = many('SELECT * FROM pipeline_estagios WHERE tenant_id=? ORDER BY ordem',[$tid]);
    foreach ($estagios as &$e) {
        $e['prospectos'] = many('SELECT pp.*,p.nome AS plano_nome FROM pipeline_prospectos pp LEFT JOIN planos p ON p.id=pp.plano_id WHERE pp.tenant_id=? AND pp.estagio_id=? ORDER BY pp.ordem_coluna',[$tid,$e['id']]);
    }
    ok(['estagios'=>$estagios,'total'=>count($estagios)]);
}

if ($uri === '/pipeline/prospectos' && $method === 'POST') {
    $a = auth(); $b = body();
    $nome = trim($b['nome_empresa']??'');
    if (!$nome) err('Nome da empresa é obrigatório');
    $estagioId = $b['estagio_id']??null;
    if (!$estagioId) {
        $primeiro = one('SELECT id FROM pipeline_estagios WHERE tenant_id=? ORDER BY ordem LIMIT 1',[$a['tenant_id']]);
        $estagioId = $primeiro['id']??null;
    }
    if (!$estagioId) err('Nenhum estágio configurado');
    $id = run('INSERT INTO pipeline_prospectos (tenant_id,estagio_id,plano_id,nome_empresa,cnpj,nome_contato,email,whatsapp,valor_estimado,origem,responsavel_id,criado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())',
        [$a['tenant_id'],$estagioId,$b['plano_id']??null,$nome,preg_replace('/\D/','',$b['cnpj']??'')?:null,$b['nome_contato']??null,$b['email']??null,preg_replace('/\D/','',$b['whatsapp']??'')?:null,$b['valor_estimado']??null,$b['origem']??'outro',$a['sub']]);
    ok(one('SELECT * FROM pipeline_prospectos WHERE id=?',[$id]),201);
}

if (preg_match('#^/pipeline/prospectos/(\d+)/mover$#',$uri,$m) && $method==='PATCH') {
    $a = auth(); $b = body();
    $estagioId = $b['estagio_id']??null;
    if (!$estagioId) err('estagio_id é obrigatório');
    if (!one('SELECT id FROM pipeline_estagios WHERE id=? AND tenant_id=?',[$estagioId,$a['tenant_id']])) err('Estágio inválido',404);
    run('UPDATE pipeline_prospectos SET estagio_id=? WHERE id=? AND tenant_id=?',[$estagioId,$m[1],$a['tenant_id']]);
    ok(one('SELECT * FROM pipeline_prospectos WHERE id=?',[$m[1]]));
}

if (preg_match('#^/pipeline/prospectos/(\d+)$#',$uri,$m) && $method==='DELETE') {
    $a = auth();
    if (!one('SELECT id FROM pipeline_prospectos WHERE id=? AND tenant_id=?',[$m[1],$a['tenant_id']])) err('Não encontrado',404);
    run('DELETE FROM pipeline_prospectos WHERE id=? AND tenant_id=?',[$m[1],$a['tenant_id']]);
    ok(['deletado'=>true]);
}

// USUÁRIOS
if ($uri === '/usuarios' && $method === 'GET') {
    $a = gestor();
    $rows = many('SELECT id,nome,email,role,ativo,ultimo_login,criado_em FROM usuarios WHERE tenant_id=? ORDER BY nome',[$a['tenant_id']]);
    ok(['data'=>$rows,'total'=>count($rows)]);
}

if ($uri === '/usuarios' && $method === 'POST') {
    $a = gestor(); $b = body();
    $email = trim($b['email']??''); $nome = trim($b['nome']??'');
    $role = $b['role']??'atendente'; $senha = $b['senha']??bin2hex(random_bytes(8));
    if (!$email || !$nome) err('Nome e e-mail são obrigatórios');
    if (one('SELECT id FROM usuarios WHERE email=?',[$email])) err('E-mail já cadastrado');
    $id = run('INSERT INTO usuarios (tenant_id,nome,email,senha_hash,role,ativo,criado_em) VALUES (?,?,?,?,?,1,NOW())',
        [$a['tenant_id'],$nome,$email,password_hash($senha,PASSWORD_BCRYPT),$role]);
    ok(['id'=>$id,'nome'=>$nome,'email'=>$email,'role'=>$role,'senha_temp'=>$senha],201);
}

if (preg_match('#^/usuarios/(\d+)$#',$uri,$m) && $method==='PATCH') {
    $a = gestor(); $b = body();
    if (!one('SELECT id FROM usuarios WHERE id=? AND tenant_id=?',[$m[1],$a['tenant_id']])) err('Não encontrado',404);
    $sets=[]; $params=[];
    if (isset($b['ativo']))  { $sets[]='ativo=?';      $params[]=(int)$b['ativo']; }
    if (isset($b['role']))   { $sets[]='role=?';       $params[]=$b['role']; }
    if (isset($b['senha']))  { $sets[]='senha_hash=?'; $params[]=password_hash($b['senha'],PASSWORD_BCRYPT); }
    if (empty($sets)) err('Nada para atualizar');
    $params[]=$m[1];
    run('UPDATE usuarios SET '.implode(',',$sets).' WHERE id=?',$params);
    ok(one('SELECT id,nome,email,role,ativo FROM usuarios WHERE id=?',[$m[1]]));
}

// GATEWAY
if ($uri === '/gateway/config' && $method === 'GET') {
    $a = auth();
    ok(one('SELECT id,gateway,ambiente,ativo,testado_em,criado_em FROM gateway_configs WHERE tenant_id=? AND ativo=1 LIMIT 1',[$a['tenant_id']]) ?: null);
}

if ($uri === '/gateway/config' && $method === 'POST') {
    $a = gestor(); $b = body();
    $apiKey = trim($b['api_key']??'');
    if (!$apiKey) err('API key obrigatória');
    run('UPDATE gateway_configs SET ativo=0 WHERE tenant_id=?',[$a['tenant_id']]);
    $id = run('INSERT INTO gateway_configs (tenant_id,gateway,api_key,ambiente,ativo,criado_em) VALUES (?,?,?,?,1,NOW())',
        [$a['tenant_id'],$b['gateway']??'asaas',$apiKey,$b['ambiente']??'sandbox']);
    ok(['id'=>$id,'gateway'=>$b['gateway']??'asaas','ambiente'=>$b['ambiente']??'sandbox']);
}

if ($uri === '/gateway/testar' && $method === 'POST') {
    $a = auth();
    asaasReq('GET','/customers',['limit'=>1]);
    run('UPDATE gateway_configs SET testado_em=NOW() WHERE tenant_id=? AND ativo=1',[$a['tenant_id']]);
    ok(['conectado'=>true]);
}

// WEBHOOK
if ($uri === '/webhook/asaas' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw,true)??[];
    $eventId = $payload['id']??($payload['payment']['id']??uniqid());
    $existe = one('SELECT id FROM webhooks_log WHERE gateway=? AND event_id=?',['asaas',$eventId]);
    if ($existe) { http_response_code(200); echo json_encode(['ok'=>true,'skipped'=>true]); exit; }
    run('INSERT INTO webhooks_log (gateway,event_id,event_type,payload,recebido_em) VALUES (?,?,?,?,NOW())',['asaas',$eventId,$payload['event']??'unknown',$raw]);
    $gatewayId = $payload['payment']['id']??null;
    if ($gatewayId) {
        $statusMap=['PAYMENT_RECEIVED'=>'pago','PAYMENT_CONFIRMED'=>'pago','PAYMENT_OVERDUE'=>'expirado','PAYMENT_DELETED'=>'cancelado','PAYMENT_REFUNDED'=>'estornado'];
        $novoStatus = $statusMap[$payload['event']??'']??null;
        if ($novoStatus) run('UPDATE cobrancas SET status=?,webhook_em=NOW(),webhook_payload=? WHERE gateway_charge_id=?',[$novoStatus,$raw,$gatewayId]);
    }
    http_response_code(200); echo json_encode(['ok'=>true]); exit;
}

err("Rota não encontrada: {$method} {$uri}", 404);
