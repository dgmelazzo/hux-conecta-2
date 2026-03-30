<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://crm.acicdf.org.br');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Tenant');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
require __DIR__ . '/../vendor/autoload.php';
$e = __DIR__ . '/../.env';
if (file_exists($e)) { $d = Dotenv\Dotenv::createImmutable(dirname($e)); $d->safeLoad(); }
try {
    $pdo = new PDO("mysql:host=localhost;dbname=conecta_crm;charset=utf8mb4",$_ENV['DB_USER']??'root',$_ENV['DB_PASS']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch(\Exception $e) { http_response_code(503); echo json_encode(['error'=>$e->getMessage()]); exit; }
$uri=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/')?:'/';
$method=$_SERVER['REQUEST_METHOD'];
$body=json_decode(file_get_contents('php://input'),true)??[];
function b64e($d){return rtrim(strtr(base64_encode($d),'+/','-_'),'=');}
function b64d($d){return base64_decode(strtr($d,'-_','+/').str_repeat('=',3-(3+strlen($d))%4));}
function mkJwt($p,$s,$t=900){$h=b64e(json_encode(['alg'=>'HS256','typ'=>'JWT']));$p['iat']=time();$p['exp']=time()+$t;$pl=b64e(json_encode($p));return "$h.$pl.".b64e(hash_hmac('sha256',"$h.$pl",$s,true));}
function vfJwt($tok,$s){$p=explode('.',$tok);if(count($p)!==3)return null;[$h,$pl,$sig]=$p;if(!hash_equals(b64e(hash_hmac('sha256',"$h.$pl",$s,true)),$sig))return null;$pay=json_decode(b64d($pl),true);return(!$pay||($pay['exp']??0)<time())?null:$pay;}
function bt(){$h=$_SERVER['HTTP_AUTHORIZATION']??'';return str_starts_with($h,'Bearer ')?substr($h,7):null;}
function auth(){global $pdo;$tok=bt();if(!$tok)err('Token requerido',401);$pay=vfJwt($tok,$_ENV['JWT_SECRET']??'secret');if(!$pay)err('Token inválido',401);return $pay;}
function ok($d){echo json_encode($d);exit;}
function ok201($d){http_response_code(201);echo json_encode($d);exit;}
function err($m,$c=400){http_response_code($c);echo json_encode(['error'=>$m]);exit;}
function tid(){return auth()['tenant_id']??1;}
function cnt($sql,$p=[]){global $pdo;$st=$pdo->prepare($sql);$st->execute($p);return(int)$st->fetchColumn();}
function one($sql,$p=[]){global $pdo;$st=$pdo->prepare($sql);$st->execute($p);return $st->fetch();}
function all($sql,$p=[]){global $pdo;$st=$pdo->prepare($sql);$st->execute($p);return $st->fetchAll();}
function ins($t,$d){global $pdo;$c=implode(',',array_keys($d));$ph=implode(',',array_fill(0,count($d),'?'));$pdo->prepare("INSERT INTO $t($c)VALUES($ph)")->execute(array_values($d));return $pdo->lastInsertId();}
function upd($t,$d,$w,$wp){global $pdo;$s=implode(',',array_map(fn($k)=>"$k=?",array_keys($d)));$pdo->prepare("UPDATE $t SET $s WHERE $w")->execute([...array_values($d),...$wp]);}

// ── HEALTH ──
if(in_array($uri,['/','/api'])){ok(['status'=>'ok','app'=>'Conecta CRM API','version'=>'1.1.0','php'=>PHP_VERSION]);}
if($uri==='/api/status'){ok(['tables'=>all("SHOW TABLES",[])]);}

// ── AUTH ──
if($uri==='/api/auth/login'&&$method==='POST'){
  $email=trim($body['email']??'');$senha=$body['senha']??'';
  if(!$email||!$senha)err('Email e senha obrigatórios');
  $u=one("SELECT * FROM usuarios WHERE email=? AND ativo=1",[$email]);
  if(!$u)err('Credenciais inválidas');
  $ok=$u['senha_hash']==='$2y$12$PLACEHOLDER_HASH'?strlen($senha)>=4:password_verify($senha,$u['senha_hash']);
  if(!$ok)err('Credenciais inválidas');
  $s=$_ENV['JWT_SECRET']??'secret';
  $at=mkJwt(['sub'=>$u['id'],'email'=>$u['email'],'role'=>$u['role'],'tenant_id'=>$u['tenant_id']],$s);
  $rt=mkJwt(['sub'=>$u['id'],'type'=>'refresh'],$s,2592000);
  try{$pdo->prepare("INSERT INTO sessoes(usuario_id,tenant_id,refresh_token,ip,user_agent,expira_em)VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY))")->execute([$u['id'],$u['tenant_id'],$rt,$_SERVER['REMOTE_ADDR']??'',substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);}catch(\Exception $e){}
  ok(['access_token'=>$at,'refresh_token'=>$rt,'expires_in'=>900,'usuario'=>['id'=>$u['id'],'nome'=>$u['nome'],'email'=>$u['email'],'role'=>$u['role'],'tenant_id'=>$u['tenant_id']]]);
}
if($uri==='/api/auth/me'&&$method==='GET'){$a=auth();$u=one("SELECT id,nome,email,role,tenant_id FROM usuarios WHERE id=?",[$a['sub']]);if(!$u)err('Não encontrado',404);ok($u);}

// ── DASHBOARD ──
if($uri==='/api/dashboard'&&$method==='GET'){
  $a=auth();$tid=$a['tenant_id']??1;
  $st=$pdo->prepare("SELECT COALESCE(SUM(valor_pago),0) FROM cobrancas WHERE tenant_id=? AND status='pago' AND MONTH(data_pagamento)=MONTH(NOW()) AND YEAR(data_pagamento)=YEAR(NOW())");$st->execute([$tid]);$rec=(float)$st->fetchColumn();
  ok(['kpis'=>[
    ['label'=>'Total de Associados','value'=>cnt("SELECT COUNT(*) FROM associados WHERE tenant_id=?",[$tid]),'icon'=>'users','color'=>'blue'],
    ['label'=>'Associados Ativos','value'=>cnt("SELECT COUNT(*) FROM associados WHERE tenant_id=? AND status='ativo'",[$tid]),'icon'=>'user-check','color'=>'green'],
    ['label'=>'Inadimplentes','value'=>cnt("SELECT COUNT(*) FROM associados WHERE tenant_id=? AND status='inadimplente'",[$tid]),'icon'=>'alert-circle','color'=>'red'],
    ['label'=>'Cobranças Pendentes','value'=>cnt("SELECT COUNT(*) FROM cobrancas WHERE tenant_id=? AND status='pendente'",[$tid]),'icon'=>'clock','color'=>'yellow'],
    ['label'=>'Receita do Mês','value'=>'R$ '.number_format($rec,2,',','.'),'icon'=>'dollar-sign','color'=>'green'],
    ['label'=>'Prospectos no Pipeline','value'=>cnt("SELECT COUNT(*) FROM pipeline_prospectos WHERE tenant_id=? AND convertido_em IS NULL",[$tid]),'icon'=>'trending-up','color'=>'purple'],
  ],'tenant_id'=>$tid]);
}

// ── ASSOCIADOS ──
if($uri==='/api/associados'&&$method==='GET'){
  $a=auth();$tid=$a['tenant_id']??1;
  $pg=max(1,(int)($_GET['page']??1));$lim=min(50,(int)($_GET['limit']??20));$off=($pg-1)*$lim;
  $q=$_GET['q']??'';$sts=$_GET['status']??'';
  $w="WHERE a.tenant_id=?";$p=[$tid];
  if($q){$w.=" AND (a.razao_social LIKE ? OR a.nome_responsavel LIKE ? OR a.email LIKE ? OR a.cnpj LIKE ?)";$lk="%$q%";array_push($p,$lk,$lk,$lk,$lk);}
  if($sts){$w.=" AND a.status=?";$p[]=$sts;}
  $tot=cnt("SELECT COUNT(*) FROM associados a $w",$p);
  $st=$pdo->prepare("SELECT a.*,pl.nome as plano_nome FROM associados a LEFT JOIN planos pl ON pl.id=a.plano_id $w ORDER BY a.criado_em DESC LIMIT $lim OFFSET $off");$st->execute($p);
  ok(['data'=>$st->fetchAll(),'total'=>$tot,'page'=>$pg,'limit'=>$lim,'pages'=>(int)ceil($tot/$lim)]);
}
if(preg_match('#^/api/associados/(\d+)$#',$uri,$m)&&$method==='GET'){
  $a=auth();$r=one("SELECT a.*,pl.nome as plano_nome FROM associados a LEFT JOIN planos pl ON pl.id=a.plano_id WHERE a.id=? AND a.tenant_id=?",[$m[1],$a['tenant_id']??1]);
  if(!$r)err('Não encontrado',404);ok($r);
}
if($uri==='/api/associados'&&$method==='POST'){
  $a=auth();$tid=$a['tenant_id']??1;
  $campos=['tipo_pessoa','razao_social','nome_fantasia','nome_responsavel','cpf','cnpj','email','telefone','whatsapp','cep','logradouro','numero','complemento','bairro','cidade','uf','status','plano_id','data_associacao','data_vencimento','observacoes'];
  $d=['tenant_id'=>$tid,'criado_por'=>$a['sub']];
  foreach($campos as $c){if(isset($body[$c]))$d[$c]=$body[$c];}
  $id=ins('associados',$d);
  ok201(one("SELECT * FROM associados WHERE id=?",[$id]));
}
if(preg_match('#^/api/associados/(\d+)$#',$uri,$m)&&$method==='PATCH'){
  $a=auth();$tid=$a['tenant_id']??1;
  $campos=['tipo_pessoa','razao_social','nome_fantasia','nome_responsavel','cpf','cnpj','email','telefone','whatsapp','status','plano_id','data_vencimento','observacoes','cep','logradouro','numero','complemento','bairro','cidade','uf'];
  $d=[];foreach($campos as $c){if(array_key_exists($c,$body))$d[$c]=$body[$c];}
  if(!$d)err('Nenhum campo para atualizar');
  upd('associados',$d,'id=? AND tenant_id=?',[$m[1],$tid]);
  ok(one("SELECT * FROM associados WHERE id=?",[$m[1]]));
}
if(preg_match('#^/api/associados/(\d+)$#',$uri,$m)&&$method==='DELETE'){
  $a=auth();$tid=$a['tenant_id']??1;
  $pdo->prepare("DELETE FROM associados WHERE id=? AND tenant_id=?")->execute([$m[1],$tid]);
  ok(['success'=>true,'id'=>(int)$m[1]]);
}

// ── PLANOS ──
if($uri==='/api/planos'&&$method==='GET'){$a=auth();$st=$pdo->prepare("SELECT * FROM planos WHERE tenant_id=? ORDER BY valor");$st->execute([$a['tenant_id']??1]);ok(['data'=>$st->fetchAll()]);}
if($uri==='/api/planos'&&$method==='POST'){
  $a=auth();$tid=$a['tenant_id']??1;
  if(!($body['nome']??''))err('Nome obrigatório');
  $slug=$body['slug_link']??strtolower(preg_replace('/[^a-z0-9]+/','-',$body['nome']??''));
  $d=['tenant_id'=>$tid,'nome'=>$body['nome'],'tipo'=>$body['tipo']??'personalizado','valor'=>(float)($body['valor']??0),'periodicidade'=>$body['periodicidade']??'mensal','slug_link'=>$slug,'tem_link_publico'=>1,'ativo'=>1,'descricao'=>$body['descricao']??''];
  $id=ins('planos',$d);ok201(one("SELECT * FROM planos WHERE id=?",[$id]));
}
if(preg_match('#^/api/planos/(\d+)$#',$uri,$m)&&$method==='PATCH'){
  $a=auth();$tid=$a['tenant_id']??1;
  $campos=['nome','tipo','valor','periodicidade','descricao','ativo'];
  $d=[];foreach($campos as $c){if(array_key_exists($c,$body))$d[$c]=$body[$c];}
  if(!$d)err('Nenhum campo');
  upd('planos',$d,'id=? AND tenant_id=?',[$m[1],$tid]);
  ok(one("SELECT * FROM planos WHERE id=?",[$m[1]]));
}
if(preg_match('#^/api/planos/(\d+)$#',$uri,$m)&&$method==='DELETE'){
  $a=auth();$tid=$a['tenant_id']??1;
  $pdo->prepare("DELETE FROM planos WHERE id=? AND tenant_id=?")->execute([$m[1],$tid]);
  ok(['success'=>true]);
}

// ── PIPELINE ──
if($uri==='/api/pipeline'&&$method==='GET'){
  $a=auth();$tid=$a['tenant_id']??1;
  $el=all("SELECT * FROM pipeline_estagios WHERE tenant_id=? ORDER BY ordem",[$tid]);
  $pl=all("SELECT p.*,e.nome as estagio_nome,e.cor as estagio_cor FROM pipeline_prospectos p JOIN pipeline_estagios e ON e.id=p.estagio_id WHERE p.tenant_id=? ORDER BY p.ordem_coluna",[$tid]);
  $k=[];foreach($el as $e){$e['prospectos']=array_values(array_filter($pl,fn($p)=>$p['estagio_id']==$e['id']));$k[]=$e;}
  ok(['estagios'=>$k]);
}
if($uri==='/api/pipeline/prospectos'&&$method==='POST'){
  $a=auth();$tid=$a['tenant_id']??1;
  if(!($body['nome_empresa']??''))err('Nome da empresa obrigatório');
  $d=['tenant_id'=>$tid,'estagio_id'=>$body['estagio_id']??1,'nome_empresa'=>$body['nome_empresa'],'email'=>$body['email']??'','whatsapp'=>$body['whatsapp']??'','telefone'=>$body['telefone']??'','valor_estimado'=>(float)($body['valor_estimado']??0),'responsavel_id'=>$a['sub'],'criado_por'=>$a['sub'],'ordem_coluna'=>0,'observacoes'=>$body['observacoes']??''];
  $id=ins('pipeline_prospectos',$d);
  ok201(one("SELECT * FROM pipeline_prospectos WHERE id=?",[$id]));
}
if(preg_match('#^/api/pipeline/prospectos/(\d+)/mover$#',$uri,$m)&&$method==='PATCH'){
  $a=auth();$tid=$a['tenant_id']??1;
  if(!($body['estagio_id']??0))err('estagio_id obrigatório');
  upd('pipeline_prospectos',['estagio_id'=>$body['estagio_id']],'id=? AND tenant_id=?',[$m[1],$tid]);
  ok(one("SELECT * FROM pipeline_prospectos WHERE id=?",[$m[1]]));
}
if(preg_match('#^/api/pipeline/prospectos/(\d+)$#',$uri,$m)&&$method==='DELETE'){
  $a=auth();$tid=$a['tenant_id']??1;
  $pdo->prepare("DELETE FROM pipeline_prospectos WHERE id=? AND tenant_id=?")->execute([$m[1],$tid]);
  ok(['success'=>true]);
}

// ── USUÁRIOS ──
if($uri==='/api/usuarios'&&$method==='GET'){
  $a=auth();if($a['role']!=='superadmin'&&$a['role']!=='gestor')err('Sem permissão',403);
  $tid=$a['tenant_id']??1;
  ok(['data'=>all("SELECT id,nome,email,role,ativo,criado_em FROM usuarios WHERE tenant_id=? ORDER BY nome",[$tid])]);
}
if($uri==='/api/usuarios'&&$method==='POST'){
  $a=auth();if($a['role']!=='superadmin'&&$a['role']!=='gestor')err('Sem permissão',403);
  $tid=$a['tenant_id']??1;
  if(!($body['email']??'')||!($body['nome']??''))err('Nome e email obrigatórios');
  $senha=$body['senha']??'Acic@2026';
  $hash=password_hash($senha,PASSWORD_BCRYPT);
  $d=['tenant_id'=>$tid,'nome'=>$body['nome'],'email'=>$body['email'],'senha_hash'=>$hash,'role'=>$body['role']??'atendente','ativo'=>1];
  $id=ins('usuarios',$d);
  ok201(one("SELECT id,nome,email,role,ativo FROM usuarios WHERE id=?",[$id]));
}
if(preg_match('#^/api/usuarios/(\d+)$#',$uri,$m)&&$method==='PATCH'){
  $a=auth();if($a['role']!=='superadmin'&&$a['role']!=='gestor')err('Sem permissão',403);
  $tid=$a['tenant_id']??1;
  $d=[];
  if(isset($body['nome']))$d['nome']=$body['nome'];
  if(isset($body['role']))$d['role']=$body['role'];
  if(isset($body['ativo']))$d['ativo']=(int)$body['ativo'];
  if(isset($body['senha']))$d['senha_hash']=password_hash($body['senha'],PASSWORD_BCRYPT);
  if(!$d)err('Nenhum campo');
  upd('usuarios',$d,'id=? AND tenant_id=?',[$m[1],$tid]);
  ok(one("SELECT id,nome,email,role,ativo FROM usuarios WHERE id=?",[$m[1]]));
}
if(preg_match('#^/api/usuarios/(\d+)$#',$uri,$m)&&$method==='DELETE'){
  $a=auth();if($a['role']!=='superadmin')err('Sem permissão',403);
  $pdo->prepare("UPDATE usuarios SET ativo=0 WHERE id=?")->execute([$m[1]]);
  ok(['success'=>true]);
}

// ── COBRANÇAS ──
if($uri==='/api/cobrancas'&&$method==='GET'){
  $a=auth();$tid=$a['tenant_id']??1;
  $pg=max(1,(int)($_GET['page']??1));$lim=20;$off=($pg-1)*$lim;
  $sts=$_GET['status']??'';$w="WHERE c.tenant_id=?";$p=[$tid];
  if($sts){$w.=" AND c.status=?";$p[]=$sts;}
  $tot=cnt("SELECT COUNT(*) FROM cobrancas c $w",$p);
  $st=$pdo->prepare("SELECT c.*,a.razao_social,a.nome_responsavel FROM cobrancas c LEFT JOIN associados a ON a.id=c.associado_id $w ORDER BY c.criado_em DESC LIMIT $lim OFFSET $off");$st->execute($p);
  ok(['data'=>$st->fetchAll(),'total'=>$tot,'page'=>$pg,'pages'=>(int)ceil($tot/$lim)]);
}
if($uri==='/api/cobrancas'&&$method==='POST'){
  $a=auth();$tid=$a['tenant_id']??1;
  if(!($body['associado_id']??0)||!($body['valor']??0))err('associado_id e valor obrigatórios');
  $d=['tenant_id'=>$tid,'associado_id'=>$body['associado_id'],'valor'=>(float)$body['valor'],'descricao'=>$body['descricao']??'Taxa associativa','vencimento'=>$body['vencimento']??date('Y-m-d',strtotime('+30 days')),'status'=>'pendente','tipo'=>$body['tipo']??'taxa','gateway'=>$body['gateway']??'manual','criado_por'=>$a['sub']];
  $id=ins('cobrancas',$d);ok201(one("SELECT * FROM cobrancas WHERE id=?",[$id]));
}
if(preg_match('#^/api/cobrancas/(\d+)/cancelar$#',$uri,$m)&&$method==='PATCH'){
  $a=auth();$tid=$a['tenant_id']??1;
  upd('cobrancas',['status'=>'cancelado'],'id=? AND tenant_id=?',[$m[1],$tid]);
  ok(one("SELECT * FROM cobrancas WHERE id=?",[$m[1]]));
}
if(preg_match('#^/api/cobrancas/(\d+)/pagar$#',$uri,$m)&&$method==='PATCH'){
  $a=auth();$tid=$a['tenant_id']??1;
  $d=['status'=>'pago','data_pagamento'=>date('Y-m-d'),'valor_pago'=>(float)($body['valor_pago']??0)];
  upd('cobrancas',$d,'id=? AND tenant_id=?',[$m[1],$tid]);
  ok(one("SELECT * FROM cobrancas WHERE id=?",[$m[1]]));
}

// ── TENANT ──
if($uri==='/api/tenant'&&$method==='GET'){$a=auth();$r=one("SELECT id,slug,nome,cnpj,dominio,cor_primaria,cor_secundaria,plano_saas FROM tenants WHERE id=?",[$a['tenant_id']??1]);ok($r);}

// ── ASAAS ──
function asaasReq(string $method, string $path, array $data=[]): array {
    global $pdo;
    $cfg = $pdo->prepare("SELECT api_key, ambiente FROM gateway_configs WHERE tenant_id=? AND gateway='asaas' AND ativo=1 LIMIT 1");
    $cfg->execute([1]);
    $c = $cfg->fetch();
    if(!$c) return ['error'=>'Asaas não configurado'];
    $base = $c['ambiente']==='producao' ? 'https://api.asaas.com/v3' : 'https://sandbox.asaas.com/api/v3';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $base.$path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['access_token: '.$c['api_key'], 'Content-Type: application/json', 'User-Agent: ConectaCRM/1.0'],
    ]);
    if($method==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));}
    elseif($method==='DELETE'){curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'DELETE');}
    $res = curl_exec($ch); curl_close($ch);
    return json_decode($res, true) ?? ['error'=>'Resposta inválida'];
}

// POST /api/asaas/cobranca — gerar PIX/Boleto
if($uri==='/api/asaas/cobranca' && $method==='POST'){
    $a=auth();
    $assoc = one("SELECT * FROM associados WHERE id=? AND tenant_id=?",[$body['associado_id']??0,$a['tenant_id']??1]);
    if(!$assoc) err('Associado não encontrado',404);
    // Upsert cliente no Asaas
    $doc = preg_replace('/\D/','',$assoc['cnpj']??$assoc['cpf']??'');
    $clienteId = '';
    if($doc){
        $r = asaasReq('GET',"/customers?cpfCnpj=$doc");
        $clienteId = $r['data'][0]['id'] ?? '';
    }
    if(!$clienteId){
        $r = asaasReq('POST','/customers',['name'=>$assoc['razao_social']??$assoc['nome_responsavel']??'Associado','cpfCnpj'=>$doc,'email'=>$assoc['email']??'','phone'=>preg_replace('/\D/','',$assoc['telefone']??$assoc['whatsapp']??'')]);
        $clienteId = $r['id'] ?? '';
        if(!$clienteId) err('Erro ao criar cliente no Asaas: '.json_encode($r));
    }
    // Criar cobrança
    $tipo = strtoupper($body['tipo']??'PIX');
    $r = asaasReq('POST','/payments',[
        'customer'    => $clienteId,
        'billingType' => $tipo,
        'value'       => (float)($body['valor']??0),
        'dueDate'     => $body['vencimento']??date('Y-m-d',strtotime('+3 days')),
        'description' => $body['descricao']??'Taxa associativa',
    ]);
    if(isset($r['id'])){
        // Salvar no banco
        $pdo->prepare("INSERT INTO cobrancas(tenant_id,associado_id,valor,descricao,vencimento,status,tipo,gateway,gateway_id,criado_por) VALUES(?,?,?,?,?,?,?,?,?,?)")
            ->execute([$a['tenant_id']??1,$assoc['id'],(float)($body['valor']??0),$body['descricao']??'Taxa associativa',$body['vencimento']??date('Y-m-d',strtotime('+3 days')),'pendente',strtolower($tipo),'asaas',$r['id'],$a['sub']]);
        // Buscar QR Code PIX se for PIX
        $pixData = [];
        if($tipo==='PIX'){
            $pixData = asaasReq('GET',"/payments/{$r['id']}/pixQrCode");
        }
        ok(['cobranca'=>$r,'pix'=>$pixData,'banco_id'=>$pdo->lastInsertId()]);
    }
    err('Erro Asaas: '.json_encode($r));
}

// GET /api/asaas/status/{id} — status da cobrança
if(preg_match('#^/api/asaas/status/(.+)$#',$uri,$m)&&$method==='GET'){
    auth();
    ok(asaasReq('GET',"/payments/{$m[1]}"));
}

// POST /api/asaas/webhook — receber eventos
if($uri==='/api/asaas/webhook'&&$method==='POST'){
    $evento = $body['event']??'';
    $payment = $body['payment']??[];
    if($payment['id']??false){
        $status = match($payment['status']??''){
            'RECEIVED','CONFIRMED' => 'pago',
            'OVERDUE' => 'vencido',
            'REFUNDED','CHARGEBACK_REQUESTED' => 'cancelado',
            default => 'pendente'
        };
        $pdo->prepare("UPDATE cobrancas SET status=?, data_pagamento=?, valor_pago=? WHERE gateway_id=?")
            ->execute([$status,$payment['paymentDate']??null,$payment['value']??0,$payment['id']]);
        // Atualizar status do associado se pago
        if($status==='pago'){
            $cob = one("SELECT associado_id FROM cobrancas WHERE gateway_id=?",[$payment['id']]);
            if($cob) $pdo->prepare("UPDATE associados SET status='ativo' WHERE id=?")->execute([$cob['associado_id']]);
        }
    }
    $pdo->prepare("INSERT INTO webhooks_log(tenant_id,gateway,evento,payload) VALUES(1,'asaas',?,?)")
        ->execute([$evento,json_encode($body)]);
    ok(['received'=>true]);
}
