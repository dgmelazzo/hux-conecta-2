<?php
require_once __DIR__ . '/auth-helper.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $pdo;
}

function ok($d)          { echo json_encode(['success'=>true,'data'=>$d]); exit; }
function err($c,$m)      { http_response_code($c); echo json_encode(['success'=>false,'message'=>$m]); exit; }
// FIX: php://input só pode ser lido uma vez — static faz cache na primeira leitura
function input()         { static $c=null; if($c===null) $c=json_decode(file_get_contents('php://input'),true)??[]; return $c; }

function slugify($s) {
    $s=mb_strtolower($s,'UTF-8');
    $s=str_replace(['ã','â','á','à','ê','é','è','î','í','ì','õ','ô','ó','ò','ú','ù','û','ü','ç'],
                   ['a','a','a','a','e','e','e','i','i','i','o','o','o','o','u','u','u','u','c'],$s);
    $s=preg_replace('/[^a-z0-9\s-]/','',$s);
    return preg_replace('/[\s-]+/','-',trim($s));
}

function uniqueSlug($nome,$id=null) {
    $db=$base=slugify($nome); $slug=$base; $i=1;
    while(true){ $st=getDB()->prepare('SELECT id FROM conecta_produtos WHERE slug=?'); $st->execute([$slug]); $f=$st->fetchColumn(); if(!$f||$f==$id) break; $slug=$base.'-'.$i++; }
    return $slug;
}

function requireAdmin() {
    $user = requireCrmAdmin();
    return $user['documento'] ?? ADMIN_DOC;
}

function requireAuth() {
    $user = requireCrmAuth();
    if (!$user) return null;
    $user['plano_nome'] = $user['plano'] ?? '';
    return $user;
}

function hgGet($path) {
    $ch=curl_init(HIGESTOR_URL.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,
        CURLOPT_HTTPHEADER=>['Auth-Token: '.HIGESTOR_TOKEN,'Content-Type: application/json']]);
    $res=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($code!==200) return null;
    return json_decode($res,true);
}

// ── SETUP TABELAS ────────────────────────────────────────────
$db=getDB();
try {
$db->exec("CREATE TABLE IF NOT EXISTS conecta_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE, parent_id INT DEFAULT NULL,
    ordem INT DEFAULT 0, ativo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS conecta_produtos (
    id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE, descricao TEXT, descricao_curta VARCHAR(500),
    categoria_id INT DEFAULT NULL, associado_id VARCHAR(20) NOT NULL DEFAULT '0',
    associado_nome VARCHAR(200), tipo ENUM('produto','servico','beneficio') DEFAULT 'produto',
    status ENUM('ativo','inativo','pendente') DEFAULT 'pendente',
    marca VARCHAR(100), imagem VARCHAR(500), destaque TINYINT(1) DEFAULT 0,
    link_venda_tipo ENUM('whatsapp','externo') DEFAULT NULL,
    link_venda_url VARCHAR(500), whatsapp_numero VARCHAR(20),
    views INT DEFAULT 0, clicks INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS conecta_subprodutos (
    id INT AUTO_INCREMENT PRIMARY KEY, produto_id INT NOT NULL,
    nome VARCHAR(200) NOT NULL, descricao TEXT,
    preco DECIMAL(10,2) DEFAULT NULL,
    tipo_cobranca ENUM('unico','mensal','anual','recorrente') DEFAULT 'unico',
    status ENUM('ativo','inativo') DEFAULT 'ativo',
    link_venda_tipo ENUM('whatsapp','externo') DEFAULT NULL,
    link_venda_url VARCHAR(500), whatsapp_numero VARCHAR(20), ordem INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES conecta_produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS conecta_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY, produto_id INT NOT NULL,
    subproduto_id INT DEFAULT NULL,
    tipo_evento ENUM('view','click_whatsapp','click_externo') NOT NULL,
    session_id VARCHAR(64), ip VARCHAR(45), user_agent VARCHAR(500),
    referrer VARCHAR(500), created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES conecta_produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->exec("CREATE TABLE IF NOT EXISTS conecta_produto_imagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    nome VARCHAR(200),
    ordem INT DEFAULT 0,
    principal TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES conecta_produtos(id) ON DELETE CASCADE,
    INDEX idx_produto_img (produto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Tabela de parceiros
$db->exec("CREATE TABLE IF NOT EXISTS conecta_parceiros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(200),
    cnpj VARCHAR(20),
    email VARCHAR(200),
    telefone VARCHAR(30),
    site VARCHAR(300),
    logo_url VARCHAR(500),
    categoria VARCHAR(100),
    descricao TEXT,
    split_percentual DECIMAL(5,2) DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Adicionar parceiro_id em conecta_produtos (idempotente)
try { $db->exec("ALTER TABLE conecta_produtos ADD COLUMN parceiro_id INT DEFAULT NULL"); } catch(Exception $e) {}

// Adicionar split_percentual em conecta_parceiros caso tabela já exista sem ela
try { $db->exec("ALTER TABLE conecta_parceiros ADD COLUMN split_percentual DECIMAL(5,2) DEFAULT 0"); } catch(Exception $e) {}

// Tabela de combos (vínculo plano CRM → produtos)
$db->exec("CREATE TABLE IF NOT EXISTS conecta_combos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    plano_crm VARCHAR(200) NOT NULL,
    descricao TEXT,
    ativo TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plano_crm (plano_crm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("CREATE TABLE IF NOT EXISTS conecta_combo_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    combo_id INT NOT NULL,
    produto_id INT NOT NULL,
    subproduto_id INT DEFAULT NULL,
    desconto_percentual DECIMAL(5,2) DEFAULT 0,
    incluido TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (combo_id) REFERENCES conecta_combos(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES conecta_produtos(id) ON DELETE CASCADE,
    UNIQUE KEY uk_combo_produto_sub (combo_id, produto_id, subproduto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Categorias padrão
$db->exec("INSERT IGNORE INTO conecta_categorias (nome,slug,ordem) VALUES
('Saúde','saude',1),('Educação','educacao',2),('Financeiro','financeiro',3),
('Jurídico','juridico',4),('Tecnologia','tecnologia',5),('Marketing','marketing',6),
('Seguros','seguros',7),('Gestão','gestao',8),('Alimentação','alimentacao',9),('Outros','outros',99);"); } catch(Exception $e) { error_log('ACIC setup: '.$e->getMessage()); }

// ── ROTAS ────────────────────────────────────────────────────
$action=trim($_GET['action']??'');
$in=input();
if(!$action&&isset($in['action'])) $action=$in['action'];

switch($action){

    case 'listar':
        $where=['p.status="ativo"']; $params=[];
        if(!empty($_GET['categoria'])){ $where[]='c.slug=?'; $params[]=$_GET['categoria']; }
        if(!empty($_GET['tipo'])){ $where[]='p.tipo=?'; $params[]=$_GET['tipo']; }
        if(!empty($_GET['busca'])){ $where[]='(p.nome LIKE ? OR p.descricao_curta LIKE ? OR p.associado_nome LIKE ?)'; $b='%'.$_GET['busca'].'%'; array_push($params,$b,$b,$b); }
        if(!empty($_GET['destaque'])) $where[]='p.destaque=1';
        $ws=implode(' AND ',$where);
        $lim=min((int)($_GET['limit']??50),200); $off=(int)($_GET['offset']??0);
        $st=getDB()->prepare("SELECT p.id,p.nome,p.slug,p.descricao_curta,p.tipo,p.marca,COALESCE((SELECT url FROM conecta_produto_imagens WHERE produto_id=p.id AND principal=1 LIMIT 1),(SELECT url FROM conecta_produto_imagens WHERE produto_id=p.id ORDER BY ordem LIMIT 1),p.imagem) AS imagem,p.destaque,p.link_venda_tipo,p.views,p.clicks,p.associado_id,p.associado_nome,p.parceiro_id,c.nome AS categoria_nome,c.slug AS categoria_slug,par.nome AS parceiro_nome,par.nome_fantasia AS parceiro_fantasia,par.logo_url AS parceiro_logo,(SELECT COUNT(*) FROM conecta_subprodutos s WHERE s.produto_id=p.id AND s.status='ativo') AS total_subprodutos FROM conecta_produtos p LEFT JOIN conecta_categorias c ON c.id=p.categoria_id LEFT JOIN conecta_parceiros par ON par.id=p.parceiro_id WHERE $ws ORDER BY p.destaque DESC,p.created_at DESC LIMIT $lim OFFSET $off");
        $st->execute($params); $lista=$st->fetchAll(PDO::FETCH_ASSOC);
        $sc=getDB()->prepare("SELECT COUNT(*) FROM conecta_produtos p LEFT JOIN conecta_categorias c ON c.id=p.categoria_id WHERE $ws"); $sc->execute($params);
        // Enriquecer com info de combo se usuário logado
        $user=requireAuth();
        $comboItens=[];
        if($user&&!empty($user['plano_nome'])){
            $ci=getDB()->prepare("SELECT ci.produto_id,ci.subproduto_id,ci.desconto_percentual,ci.incluido FROM conecta_combo_itens ci JOIN conecta_combos cb ON cb.id=ci.combo_id WHERE LOWER(cb.plano_crm)=LOWER(?) AND cb.ativo=1");
            $ci->execute([trim($user['plano_nome'])]);
            foreach($ci->fetchAll(PDO::FETCH_ASSOC) as $r) $comboItens[(int)$r['produto_id']]=$r;
        }
        foreach($lista as &$p){
            $pid=(int)$p['id'];
            if(isset($comboItens[$pid])){
                $p['no_meu_plano']=true;
                $p['combo_incluido']=(bool)$comboItens[$pid]['incluido'];
                $p['combo_desconto']=(float)$comboItens[$pid]['desconto_percentual'];
            } else {
                $p['no_meu_plano']=false;
                $p['combo_incluido']=false;
                $p['combo_desconto']=0;
            }
        }
        // Filtro: só produtos do meu plano
        if(!empty($_GET['meu_plano'])&&$user) $lista=array_values(array_filter($lista,fn($p)=>$p['no_meu_plano']));
        ok(['produtos'=>$lista,'total'=>(int)$sc->fetchColumn()]);
        break;

    case 'detalhe':
        $v=$_GET['id']??$_GET['slug']??'';
        if(!$v) err(400,'ID ou slug obrigatório.');
        $c=is_numeric($v)?'p.id':'p.slug';
        $st=getDB()->prepare("SELECT p.*,c.nome AS categoria_nome,c.slug AS categoria_slug,par.nome AS parceiro_nome,par.nome_fantasia AS parceiro_fantasia,par.logo_url AS parceiro_logo,par.site AS parceiro_site FROM conecta_produtos p LEFT JOIN conecta_categorias c ON c.id=p.categoria_id LEFT JOIN conecta_parceiros par ON par.id=p.parceiro_id WHERE $c=? AND p.status='ativo'");
        $st->execute([$v]); $p=$st->fetch(PDO::FETCH_ASSOC);
        if(!$p) err(404,'Produto não encontrado.');
        $ss=getDB()->prepare("SELECT * FROM conecta_subprodutos WHERE produto_id=? AND status='ativo' ORDER BY ordem,id");
        $ss->execute([$p['id']]); $p['subprodutos']=$ss->fetchAll(PDO::FETCH_ASSOC);
        // Galeria de imagens
        $sg=getDB()->prepare("SELECT id,url,nome,ordem,principal FROM conecta_produto_imagens WHERE produto_id=? ORDER BY principal DESC,ordem,id");
        $sg->execute([$p['id']]); $p['galeria']=$sg->fetchAll(PDO::FETCH_ASSOC);
        // Usa imagem principal da galeria se disponível
        if(!empty($p['galeria'])) $p['imagem']=$p['galeria'][0]['url'];
        ok($p);
        break;

    case 'categorias':
        $st=getDB()->query("SELECT c.*,COUNT(p.id) AS total_produtos FROM conecta_categorias c LEFT JOIN conecta_produtos p ON p.categoria_id=c.id AND p.status='ativo' WHERE c.ativo=1 GROUP BY c.id ORDER BY c.ordem,c.nome");
        ok($st->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'tracking':
        $pid=(int)($in['produto_id']??0); $sid=(int)($in['subproduto_id']??0)||null;
        $te=$in['tipo_evento']??''; $sess=substr(preg_replace('/[^a-zA-Z0-9]/','', $in['session_id']??''),0,64);
        if(!$pid||!in_array($te,['view','click_whatsapp','click_externo'])) ok(['registered'=>false]);
        if($te==='view'&&$sess){ $ck=getDB()->prepare("SELECT id FROM conecta_tracking WHERE produto_id=? AND session_id=? AND tipo_evento='view' AND created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)"); $ck->execute([$pid,$sess]); if($ck->fetchColumn()) ok(['registered'=>false]); }
        $ip=$_SERVER['HTTP_X_FORWARDED_FOR']??$_SERVER['REMOTE_ADDR']??null;
        getDB()->prepare("INSERT INTO conecta_tracking(produto_id,subproduto_id,tipo_evento,session_id,ip,user_agent,referrer)VALUES(?,?,?,?,?,?,?)")->execute([$pid,$sid??null,$te,$sess?:null,$ip,substr($_SERVER['HTTP_USER_AGENT']??'',0,500),substr($in['referrer']??'',0,500)]);
        if($te==='view') getDB()->prepare("UPDATE conecta_produtos SET views=views+1 WHERE id=?")->execute([$pid]);
        else getDB()->prepare("UPDATE conecta_produtos SET clicks=clicks+1 WHERE id=?")->execute([$pid]);
        ok(['registered'=>true]);
        break;

    case 'criar':
        requireAdmin();
        $in=input();
        foreach(['nome','tipo','link_venda_tipo'] as $f) if(empty($in[$f])) err(400,"Campo obrigatório: $f");
        if(empty($in['associado_id'])) $in['associado_id'] = '0';
        if($in['link_venda_tipo']==='externo'&&empty($in['link_venda_url'])) err(400,'Informe a URL de venda.');
        if($in['link_venda_tipo']==='whatsapp'&&empty($in['whatsapp_numero'])) err(400,'Informe o número do WhatsApp.');
        $slug=uniqueSlug($in['nome']);
        getDB()->prepare("INSERT INTO conecta_produtos(nome,slug,descricao,descricao_curta,categoria_id,associado_id,associado_nome,tipo,status,marca,imagem,destaque,link_venda_tipo,link_venda_url,whatsapp_numero,parceiro_id)VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([$in['nome'],$slug,$in['descricao']??null,$in['descricao_curta']??null,$in['categoria_id']??null,$in['associado_id'],$in['associado_nome']??null,$in['tipo'],$in['status']??'pendente',$in['marca']??null,$in['imagem']??null,!empty($in['destaque'])?1:0,$in['link_venda_tipo'],$in['link_venda_url']??null,$in['whatsapp_numero']??null,!empty($in['parceiro_id'])?(int)$in['parceiro_id']:null]);
        ok(['id'=>getDB()->lastInsertId(),'slug'=>$slug]);
        break;

    case 'editar':
        requireAdmin();
        $in=input();
        if(empty($in['id'])) err(400,'ID obrigatório.');
        $slug=uniqueSlug($in['nome']??'',$in['id']);
        getDB()->prepare("UPDATE conecta_produtos SET nome=?,slug=?,descricao=?,descricao_curta=?,categoria_id=?,associado_id=?,associado_nome=?,tipo=?,status=?,marca=?,imagem=?,destaque=?,link_venda_tipo=?,link_venda_url=?,whatsapp_numero=?,parceiro_id=? WHERE id=?")->execute([$in['nome'],$slug,$in['descricao']??null,$in['descricao_curta']??null,$in['categoria_id']??null,$in['associado_id'],$in['associado_nome']??null,$in['tipo'],$in['status']??'ativo',$in['marca']??null,$in['imagem']??null,!empty($in['destaque'])?1:0,$in['link_venda_tipo']??null,$in['link_venda_url']??null,$in['whatsapp_numero']??null,!empty($in['parceiro_id'])?(int)$in['parceiro_id']:null,$in['id']]);
        ok(['id'=>$in['id'],'slug'=>$slug]);
        break;

    case 'excluir':
        requireAdmin();
        $in=input();
        if(empty($in['id'])) err(400,'ID obrigatório.');
        getDB()->prepare("DELETE FROM conecta_produtos WHERE id=?")->execute([$in['id']]);
        ok(['deleted'=>true]);
        break;

    case 'sub_criar':
        requireAdmin();
        $in=input();
        if(empty($in['produto_id'])||empty($in['nome'])) err(400,'produto_id e nome obrigatórios.');
        getDB()->prepare("INSERT INTO conecta_subprodutos(produto_id,nome,descricao,preco,tipo_cobranca,status,link_venda_tipo,link_venda_url,whatsapp_numero,ordem)VALUES(?,?,?,?,?,?,?,?,?,?)")->execute([$in['produto_id'],$in['nome'],$in['descricao']??null,$in['preco']??null,$in['tipo_cobranca']??'unico',$in['status']??'ativo',$in['link_venda_tipo']??null,$in['link_venda_url']??null,$in['whatsapp_numero']??null,$in['ordem']??0]);
        ok(['id'=>getDB()->lastInsertId()]);
        break;

    case 'sub_editar':
        requireAdmin();
        $in=input();
        if(empty($in['id'])) err(400,'ID obrigatório.');
        getDB()->prepare("UPDATE conecta_subprodutos SET nome=?,descricao=?,preco=?,tipo_cobranca=?,status=?,link_venda_tipo=?,link_venda_url=?,whatsapp_numero=?,ordem=? WHERE id=?")->execute([$in['nome'],$in['descricao']??null,$in['preco']??null,$in['tipo_cobranca']??'unico',$in['status']??'ativo',$in['link_venda_tipo']??null,$in['link_venda_url']??null,$in['whatsapp_numero']??null,$in['ordem']??0,$in['id']]);
        ok(['id'=>$in['id']]);
        break;

    case 'sub_excluir':
        requireAdmin();
        $in=input();
        if(empty($in['id'])) err(400,'ID obrigatório.');
        getDB()->prepare("DELETE FROM conecta_subprodutos WHERE id=?")->execute([$in['id']]);
        ok(['deleted'=>true]);
        break;

    case 'admin_listar':
        requireAdmin();
        $st=getDB()->query("SELECT p.id,p.nome,p.slug,p.tipo,p.status,p.destaque,p.associado_nome,p.views,p.clicks,p.created_at,c.nome AS categoria_nome FROM conecta_produtos p LEFT JOIN conecta_categorias c ON c.id=p.categoria_id ORDER BY p.created_at DESC LIMIT 500");
        ok($st->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'buscar_associado':
        requireAdmin();
        $q=$_GET['q']??(input()['q']??'');
        if(strlen($q)<2) err(400,'Mínimo 2 caracteres.');
        $raw=hgGet('/empresas?filter[razao_social]='.urlencode($q));
        $lista=$raw['data']??(is_array($raw)?$raw:[]);
        $res=[];
        foreach(array_slice($lista,0,20) as $item){ $a=$item['attributes']??$item; if(!empty($a['associado'])) $res[]=['id'=>$item['id']??'','razao_social'=>$a['razao_social']??'','nome'=>$a['nome']??'','cnpj'=>$a['cnpj']??'']; }
        ok($res);
        break;

    // ── ADMIN: gerenciar galeria de imagens ──
    case 'imagem_add':
        requireAdmin();
        $in=input();
        if(empty($in['produto_id'])||empty($in['url'])) err(400,'produto_id e url obrigatórios.');
        // Se for a primeira imagem, marca como principal
        $cnt=getDB()->prepare('SELECT COUNT(*) FROM conecta_produto_imagens WHERE produto_id=?');
        $cnt->execute([$in['produto_id']]); $isPrimary=($cnt->fetchColumn()==0)?1:0;
        getDB()->prepare('INSERT INTO conecta_produto_imagens(produto_id,url,nome,ordem,principal)VALUES(?,?,?,?,?)')->execute([$in['produto_id'],$in['url'],$in['nome']??null,$in['ordem']??0,$in['principal']??$isPrimary]);
        ok(['id'=>getDB()->lastInsertId()]);
        break;

    case 'imagem_principal':
        requireAdmin();
        $in=input();
        if(empty($in['id'])||empty($in['produto_id'])) err(400,'id e produto_id obrigatórios.');
        getDB()->prepare('UPDATE conecta_produto_imagens SET principal=0 WHERE produto_id=?')->execute([$in['produto_id']]);
        getDB()->prepare('UPDATE conecta_produto_imagens SET principal=1 WHERE id=?')->execute([$in['id']]);
        ok(['updated'=>true]);
        break;

    case 'imagem_excluir':
        requireAdmin();
        $in=input();
        if(empty($in['id'])) err(400,'id obrigatório.');
        getDB()->prepare('DELETE FROM conecta_produto_imagens WHERE id=?')->execute([$in['id']]);
        ok(['deleted'=>true]);
        break;

    case 'imagem_listar':
        requireAdmin();
        $in=input();
        $pid=(int)($_GET['produto_id']??$in['produto_id']??0);
        if(!$pid) err(400,'produto_id obrigatório.');
        $st=getDB()->prepare('SELECT * FROM conecta_produto_imagens WHERE produto_id=? ORDER BY principal DESC,ordem,id');
        $st->execute([$pid]); ok($st->fetchAll(PDO::FETCH_ASSOC));
        break;

    // Endpoint dedicado: verifica se o token pertence ao admin
    // Retorna apenas { is_admin: true/false } sem expor dados
    case 'admin_check':
        $in  = input();
        $tok = str_replace('Bearer ','',trim($in['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? ''));
        if (!$tok) { ok(['is_admin' => false]); }
        $db  = getDB();
        $st  = $db->prepare('SELECT u.cpf_cnpj FROM conecta_sessions s JOIN conecta_users u ON u.id=s.user_id WHERE s.token=? AND s.expires_at>NOW() AND u.ativo=1');
        $st->execute([$tok]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) { ok(['is_admin' => false]); }
        $isAdmin = (preg_replace('/\D/','',$row['cpf_cnpj']) === preg_replace('/\D/','',ADMIN_DOC));
        ok(['is_admin' => $isAdmin]);
        break;

    // ── COMBOS: vínculo plano CRM → produtos ─────────────────

    case 'combo_listar':
        requireAdmin();
        $st=getDB()->query("SELECT c.*,(SELECT COUNT(*) FROM conecta_combo_itens ci WHERE ci.combo_id=c.id) AS total_itens FROM conecta_combos c ORDER BY c.nome");
        ok($st->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'combo_criar':
        requireAdmin();
        $in=input();
        if(empty($in['nome'])||empty($in['plano_crm'])) err(400,'nome e plano_crm obrigatórios.');
        getDB()->prepare("INSERT INTO conecta_combos(nome,plano_crm,descricao)VALUES(?,?,?)")->execute([trim($in['nome']),trim($in['plano_crm']),$in['descricao']??null]);
        ok(['id'=>getDB()->lastInsertId()]);
        break;

    case 'combo_editar':
        requireAdmin();
        $in=input();
        if(empty($in['id'])) err(400,'ID obrigatório.');
        getDB()->prepare("UPDATE conecta_combos SET nome=?,plano_crm=?,descricao=?,ativo=? WHERE id=?")->execute([trim($in['nome']??''),trim($in['plano_crm']??''),$in['descricao']??null,isset($in['ativo'])?(int)$in['ativo']:1,(int)$in['id']]);
        ok(['id'=>$in['id']]);
        break;

    case 'combo_excluir':
        requireAdmin();
        $in=input();
        if(empty($in['id'])) err(400,'ID obrigatório.');
        getDB()->prepare("DELETE FROM conecta_combos WHERE id=?")->execute([(int)$in['id']]);
        ok(['deleted'=>true]);
        break;

    case 'combo_itens':
        requireAdmin();
        $cid=(int)($_GET['combo_id']??input()['combo_id']??0);
        if(!$cid) err(400,'combo_id obrigatório.');
        $st=getDB()->prepare("SELECT ci.*,p.nome AS produto_nome,p.slug AS produto_slug,p.tipo AS produto_tipo,p.imagem AS produto_imagem,s.nome AS subproduto_nome,s.preco AS subproduto_preco FROM conecta_combo_itens ci JOIN conecta_produtos p ON p.id=ci.produto_id LEFT JOIN conecta_subprodutos s ON s.id=ci.subproduto_id WHERE ci.combo_id=? ORDER BY p.nome,s.nome");
        $st->execute([$cid]); ok($st->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'combo_item_add':
        requireAdmin();
        $in=input();
        if(empty($in['combo_id'])||empty($in['produto_id'])) err(400,'combo_id e produto_id obrigatórios.');
        $subId=!empty($in['subproduto_id'])?(int)$in['subproduto_id']:null;
        try {
            getDB()->prepare("INSERT INTO conecta_combo_itens(combo_id,produto_id,subproduto_id,desconto_percentual,incluido)VALUES(?,?,?,?,?)")->execute([(int)$in['combo_id'],(int)$in['produto_id'],$subId,(float)($in['desconto_percentual']??0),isset($in['incluido'])?(int)$in['incluido']:1]);
            ok(['id'=>getDB()->lastInsertId()]);
        } catch(PDOException $e) {
            if(strpos($e->getMessage(),'Duplicate')!==false) err(409,'Este produto já está vinculado ao combo.');
            throw $e;
        }
        break;

    case 'combo_item_remove':
        requireAdmin();
        $in=input();
        if(empty($in['id'])) err(400,'ID obrigatório.');
        getDB()->prepare("DELETE FROM conecta_combo_itens WHERE id=?")->execute([(int)$in['id']]);
        ok(['deleted'=>true]);
        break;

    case 'meus_beneficios':
        $user=requireAuth();
        if(!$user) err(401,'Token ausente ou inválido.');
        $plano=trim($user['plano_nome']);
        if(!$plano) ok(['combo'=>null,'itens'=>[]]);
        $st=getDB()->prepare("SELECT * FROM conecta_combos WHERE LOWER(plano_crm)=LOWER(?) AND ativo=1 LIMIT 1");
        $st->execute([$plano]); $combo=$st->fetch(PDO::FETCH_ASSOC);
        if(!$combo) ok(['combo'=>null,'itens'=>[]]);
        $si=getDB()->prepare("SELECT ci.*,p.nome AS produto_nome,p.slug AS produto_slug,p.tipo AS produto_tipo,COALESCE((SELECT url FROM conecta_produto_imagens WHERE produto_id=p.id AND principal=1 LIMIT 1),p.imagem) AS produto_imagem,p.descricao_curta AS produto_descricao,s.nome AS subproduto_nome,s.preco AS subproduto_preco,s.tipo_cobranca AS subproduto_cobranca FROM conecta_combo_itens ci JOIN conecta_produtos p ON p.id=ci.produto_id AND p.status='ativo' LEFT JOIN conecta_subprodutos s ON s.id=ci.subproduto_id WHERE ci.combo_id=? ORDER BY p.nome");
        $si->execute([$combo['id']]);
        ok(['combo'=>$combo,'itens'=>$si->fetchAll(PDO::FETCH_ASSOC)]);
        break;


    // -- CRUD Categorias (superadmin) ---
    case "categoria_criar":
        requireAdmin();
        $nome = trim($in["nome"] ?? "");
        if (!$nome) err(400, "Nome da categoria eh obrigatorio.");
        $slug = slugify($nome);
        $parentId = !empty($in["parent_id"]) ? (int)$in["parent_id"] : null;
        $icone = trim($in["icone"] ?? "");
        $maxOrdem = (int)getDB()->query("SELECT COALESCE(MAX(ordem),0) FROM conecta_categorias")->fetchColumn();
        $st = getDB()->prepare("INSERT INTO conecta_categorias (nome, icone, slug, parent_id, ordem, ativo) VALUES (?, ?, ?, ?, ?, 1)");
        $st->execute([$nome, $icone ?: null, $slug, $parentId, $maxOrdem + 1]);
        ok(["id" => (int)getDB()->lastInsertId(), "nome" => $nome, "slug" => $slug]);
        break;

    case "categoria_editar":
        requireAdmin();
        $id = (int)($in["id"] ?? 0);
        if (!$id) err(400, "ID obrigatorio.");
        $fields = []; $vals = [];
        if (isset($in["nome"]))      { $fields[] = "nome=?";      $vals[] = trim($in["nome"]); $fields[] = "slug=?"; $vals[] = slugify(trim($in["nome"])); }
        if (isset($in["parent_id"])) { $fields[] = "parent_id=?"; $vals[] = $in["parent_id"] ? (int)$in["parent_id"] : null; }
        if (isset($in["icone"]))     { $fields[] = "icone=?";     $vals[] = trim($in["icone"]); }
        if (isset($in["ativo"]))     { $fields[] = "ativo=?";     $vals[] = (int)$in["ativo"]; }
        if (empty($fields)) err(400, "Nenhum campo para atualizar.");
        $vals[] = $id;
        getDB()->prepare("UPDATE conecta_categorias SET " . implode(",", $fields) . " WHERE id=?")->execute($vals);
        ok(["updated" => true]);
        break;

    case "categoria_excluir":
        requireAdmin();
        $id = (int)($in["id"] ?? 0);
        $definitivo = (bool)($in["definitivo"] ?? false);
        if (!$id) err(400, "ID obrigatorio.");
        if ($definitivo) {
            $st = getDB()->prepare("SELECT COUNT(*) FROM conecta_produtos WHERE categoria_id=?");
            $st->execute([$id]);
            $total = (int)$st->fetchColumn();
            if ($total > 0) err(409, "Impossivel excluir: $total produto(s) vinculado(s). Reatribua os produtos antes de excluir.");
            getDB()->prepare("DELETE FROM conecta_categorias WHERE id=?")->execute([$id]);
            ok(["deleted" => true]);
        } else {
            getDB()->prepare("UPDATE conecta_categorias SET ativo=0 WHERE id=?")->execute([$id]);
            ok(["archived" => true]);
        }
        break;

    case "categoria_reordenar":
        requireAdmin();
        $ids = $in["ids"] ?? [];
        if (!is_array($ids) || empty($ids)) err(400, "Lista de IDs obrigatoria.");
        $st = getDB()->prepare("UPDATE conecta_categorias SET ordem=? WHERE id=?");
        foreach ($ids as $i => $catId) { $st->execute([$i + 1, (int)$catId]); }
        ok(["reordered" => true]);
        break;

    case "categorias_admin":
        requireAdmin();
        $st = getDB()->query("SELECT c.*, COUNT(p.id) AS total_produtos FROM conecta_categorias c LEFT JOIN conecta_produtos p ON p.categoria_id=c.id AND p.status=\"ativo\" GROUP BY c.id ORDER BY c.ordem, c.nome");
        ok($st->fetchAll(PDO::FETCH_ASSOC));
        break;

    default: err(400,'Ação inválida.');
}
