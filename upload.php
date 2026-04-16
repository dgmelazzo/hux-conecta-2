<?php
/**
 * ACIC CONECTA 2.0 — Upload de Imagens
 * Arquivo: upload.php
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

function ok($d)     { echo json_encode(['success'=>true,'data'=>$d]); exit; }
function err($c,$m) { http_response_code($c); echo json_encode(['success'=>false,'message'=>$m]); exit; }

function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $pdo;
}

// Verifica se é admin
function requireAdmin() {
    $db  = getDB();
    $tok = str_replace('Bearer ','',trim($_SERVER['HTTP_AUTHORIZATION'] ?? ($_POST['token'] ?? '')));
    if (!$tok) err(401,'Token ausente.');
    $st  = $db->prepare('SELECT u.cpf_cnpj FROM conecta_sessions s JOIN conecta_users u ON u.id=s.user_id WHERE s.token=? AND s.expires_at>NOW() AND u.ativo=1');
    $st->execute([$tok]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) err(401,'Sessão inválida.');
    if (preg_replace('/\D/','',$row['cpf_cnpj']) !== preg_replace('/\D/','',ADMIN_DOC)) err(403,'Acesso restrito.');
}

requireAdmin();

// Configuração
$UPLOAD_DIR = __DIR__ . '/uploads/';
// URL dinâmica baseada no domínio e caminho atual
$UPLOAD_URL = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/uploads/';
$MAX_SIZE   = 5 * 1024 * 1024; // 5MB
$ALLOWED    = ['image/jpeg','image/png','image/webp','image/gif'];
$ALLOWED_EXT= ['jpg','jpeg','png','webp','gif'];

// Cria pasta com proteção
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
    file_put_contents($UPLOAD_DIR.'.htaccess',
        "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh\nRemoveHandler .php\nphp_flag engine off");
}

// Valida arquivo
if (empty($_FILES['imagem'])) err(400, 'Nenhum arquivo enviado.');

$file    = $_FILES['imagem'];
$tmpPath = $file['tmp_name'];
$origName= $file['name'];
$size    = $file['size'];
$mime    = mime_content_type($tmpPath);

if ($size > $MAX_SIZE)            err(400, 'Arquivo muito grande. Máximo 5MB.');
if (!in_array($mime, $ALLOWED))   err(400, 'Tipo de arquivo não permitido. Use JPG, PNG ou WEBP.');

// Valida extensão
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $ALLOWED_EXT)) err(400, 'Extensão não permitida.');

// Nome único
$newName  = uniqid('img_', true) . '.' . $ext;
$destPath = $UPLOAD_DIR . $newName;
$destUrl  = $UPLOAD_URL . $newName;

if (!move_uploaded_file($tmpPath, $destPath)) err(500, 'Erro ao salvar arquivo.');

// Redimensiona se necessário (mantém proporção, máx 1200px)
if (in_array($mime, ['image/jpeg','image/png','image/webp'])) {
    $info = getimagesize($destPath);
    if ($info && $info[0] > 1200) {
        $src = match($mime) {
            'image/jpeg' => imagecreatefromjpeg($destPath),
            'image/png'  => imagecreatefrompng($destPath),
            'image/webp' => imagecreatefromwebp($destPath),
            default      => null,
        };
        if ($src) {
            $ratio  = 1200 / $info[0];
            $newW   = 1200;
            $newH   = (int)($info[1] * $ratio);
            $dest   = imagecreatetruecolor($newW, $newH);
            // Preserva transparência para PNG
            if ($mime === 'image/png') {
                imagealphablending($dest, false);
                imagesavealpha($dest, true);
            }
            imagecopyresampled($dest, $src, 0, 0, 0, 0, $newW, $newH, $info[0], $info[1]);
            match($mime) {
                'image/jpeg' => imagejpeg($dest, $destPath, 85),
                'image/png'  => imagepng($dest, $destPath, 6),
                'image/webp' => imagewebp($dest, $destPath, 85),
                default      => null,
            };
            imagedestroy($src);
            imagedestroy($dest);
        }
    }
}

ok(['url' => $destUrl, 'nome' => $newName]);
