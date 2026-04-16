<?php
// ════════════════════════════════════════════════════════════
// LINKS IMPORTANTES — adicionar ao admin.php
// Cole este bloco DENTRO do switch($action) do seu admin.php,
// antes do "default:" final.
// ════════════════════════════════════════════════════════════

// Criar tabela se não existir (rode uma vez ou deixe no setup)
// $db->exec("CREATE TABLE IF NOT EXISTS conecta_links (
//   id INT AUTO_INCREMENT PRIMARY KEY,
//   titulo VARCHAR(200) NOT NULL,
//   url VARCHAR(500) NOT NULL,
//   icone VARCHAR(10) DEFAULT '🔗',
//   ordem INT DEFAULT 0,
//   cliques INT DEFAULT 0,
//   ativo TINYINT(1) DEFAULT 1,
//   created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
//   INDEX idx_ordem (ordem)
// ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

case 'links_listar':
    // Público — qualquer associado logado pode ver
    requireAuth(); // sua função de validação de token
    $links = getDB()->query(
        "SELECT id, titulo, url, icone, ordem, cliques FROM conecta_links WHERE ativo=1 ORDER BY ordem ASC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    ok($links);
    break;

case 'links_criar':
    requireAdmin();
    $in = input();
    if (empty($in['titulo']) || empty($in['url'])) err(400, 'Título e URL obrigatórios.');
    getDB()->prepare(
        "INSERT INTO conecta_links (titulo, url, icone, ordem) VALUES (?, ?, ?, ?)"
    )->execute([
        trim($in['titulo']),
        trim($in['url']),
        trim($in['icone'] ?? '🔗'),
        (int)($in['ordem'] ?? 0),
    ]);
    ok(['id' => getDB()->lastInsertId()]);
    break;

case 'links_editar':
    requireAdmin();
    $in = input();
    if (empty($in['id'])) err(400, 'ID obrigatório.');
    getDB()->prepare(
        "UPDATE conecta_links SET titulo=?, url=?, icone=?, ordem=? WHERE id=?"
    )->execute([
        trim($in['titulo']),
        trim($in['url']),
        trim($in['icone'] ?? '🔗'),
        (int)($in['ordem'] ?? 0),
        (int)$in['id'],
    ]);
    ok(true);
    break;

case 'links_excluir':
    requireAdmin();
    $in = input();
    if (empty($in['id'])) err(400, 'ID obrigatório.');
    getDB()->prepare("DELETE FROM conecta_links WHERE id=?")->execute([(int)$in['id']]);
    ok(true);
    break;

case 'links_clique':
    // Registra clique — sem exigir admin
    requireAuth();
    $in = input();
    if (!empty($in['id'])) {
        getDB()->prepare("UPDATE conecta_links SET cliques = cliques + 1 WHERE id=?")->execute([(int)$in['id']]);
    }
    ok(true);
    break;
