<?php declare(strict_types=1);

// ============================================================
// PlanoService.php
// ============================================================

namespace CRM\Financeiro;

use CRM\Shared\Exceptions\ApiException;

class PlanoService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly int  $tenantId,
    ) {}

    public function listar(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, COUNT(a.id) AS total_associados
               FROM planos p
               LEFT JOIN associados a ON a.plano_id = p.id AND a.status = 'ativo'
              WHERE p.tenant_id = :tid
              GROUP BY p.id
              ORDER BY p.tipo, p.valor"
        );
        $stmt->execute([':tid' => $this->tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function criar(array $data): int
    {
        $slug = $this->gerarSlug($data['nome']);

        $this->pdo->prepare(
            "INSERT INTO planos
               (tenant_id, nome, tipo, descricao, valor, periodicidade,
                desconto_avista, tem_link_publico, slug_link, ativo)
             VALUES
               (:tid, :nome, :tipo, :desc, :valor, :period,
                :desc_avista, :link, :slug, 1)"
        )->execute([
            ':tid'         => $this->tenantId,
            ':nome'        => $data['nome'],
            ':tipo'        => $data['tipo']            ?? 'personalizado',
            ':desc'        => $data['descricao']       ?? null,
            ':valor'       => $data['valor']           ?? 0,
            ':period'      => $data['periodicidade']   ?? 'mensal',
            ':desc_avista' => $data['desconto_avista'] ?? 0,
            ':link'        => $data['tem_link_publico'] ?? 1,
            ':slug'        => $slug,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function atualizar(int $id, array $data): void
    {
        $this->pdo->prepare(
            "UPDATE planos
                SET nome = :nome, descricao = :desc, valor = :valor,
                    periodicidade = :period, desconto_avista = :desc_avista,
                    tem_link_publico = :link, ativo = :ativo,
                    atualizado_em = NOW()
              WHERE id = :id AND tenant_id = :tid"
        )->execute([
            ':nome'        => $data['nome'],
            ':desc'        => $data['descricao']       ?? null,
            ':valor'       => $data['valor'],
            ':period'      => $data['periodicidade'],
            ':desc_avista' => $data['desconto_avista'] ?? 0,
            ':link'        => $data['tem_link_publico'] ?? 1,
            ':ativo'       => $data['ativo'] ?? 1,
            ':id'          => $id,
            ':tid'         => $this->tenantId,
        ]);
    }

    // ── Link público de inscrição ──────────────────────────

    /** Retorna dados do plano pelo slug (rota pública, sem auth) */
    public function buscarPorSlug(string $slug, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, t.nome AS tenant_nome, t.logo_url, t.cor_primaria
               FROM planos p
               JOIN tenants t ON t.id = p.tenant_id
              WHERE p.slug_link = :slug
                AND p.tenant_id = :tid
                AND p.ativo     = 1
                AND p.tem_link_publico = 1
              LIMIT 1"
        );
        $stmt->execute([':slug' => $slug, ':tid' => $tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) throw new ApiException('Plano não encontrado ou inativo.', 404);
        return $row;
    }

    /** Recebe submissão do formulário público e cria prospecto no pipeline */
    public function processarInscricao(array $data, string $slug, int $tenantId): array
    {
        $plano = $this->buscarPorSlug($slug, $tenantId);

        // Salvar inscrição
        $this->pdo->prepare(
            "INSERT INTO inscricoes_publicas
               (tenant_id, plano_id, nome_empresa, cnpj, nome_contato, email, whatsapp, ip_origem)
             VALUES (:tid, :pid, :empresa, :cnpj, :contato, :email, :whatsapp, :ip)"
        )->execute([
            ':tid'      => $tenantId,
            ':pid'      => $plano['id'],
            ':empresa'  => $data['nome_empresa']  ?? null,
            ':cnpj'     => $data['cnpj']          ?? null,
            ':contato'  => $data['nome_contato'],
            ':email'    => $data['email'],
            ':whatsapp' => $data['whatsapp']      ?? null,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
        $inscricaoId = (int) $this->pdo->lastInsertId();

        // Criar prospecto automaticamente no primeiro estágio do pipeline
        $estagioId = $this->primeiroEstagio($tenantId);

        if ($estagioId) {
            $this->pdo->prepare(
                "INSERT INTO pipeline_prospectos
                   (tenant_id, estagio_id, plano_id, nome_empresa, cnpj,
                    nome_contato, email, whatsapp, origem, origem_slug)
                 VALUES
                   (:tid, :eid, :pid, :empresa, :cnpj,
                    :contato, :email, :whatsapp, 'link_publico', :slug)"
            )->execute([
                ':tid'      => $tenantId,
                ':eid'      => $estagioId,
                ':pid'      => $plano['id'],
                ':empresa'  => $data['nome_empresa']  ?? ($data['nome_contato'] ?? ''),
                ':cnpj'     => $data['cnpj']          ?? null,
                ':contato'  => $data['nome_contato'],
                ':email'    => $data['email'],
                ':whatsapp' => $data['whatsapp']      ?? null,
                ':slug'     => $slug,
            ]);

            $prospectoId = (int) $this->pdo->lastInsertId();

            // Vincular inscrição ao prospecto
            $this->pdo->prepare(
                "UPDATE inscricoes_publicas SET prospecto_id = :pid WHERE id = :id"
            )->execute([':pid' => $prospectoId, ':id' => $inscricaoId]);
        }

        return [
            'ok'          => true,
            'message'     => 'Inscrição recebida! Em breve nossa equipe entrará em contato.',
            'plano'       => $plano['nome'],
        ];
    }

    private function primeiroEstagio(int $tenantId): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM pipeline_estagios
              WHERE tenant_id = :tid AND eh_final_ganho = 0 AND eh_final_perdido = 0
              ORDER BY ordem LIMIT 1"
        );
        $stmt->execute([':tid' => $tenantId]);
        $row = $stmt->fetchColumn();
        return $row ? (int)$row : null;
    }

    private function gerarSlug(string $nome): string
    {
        $slug = strtolower(transliterator_transliterate('Any-Latin; Latin-ASCII', $nome));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return substr($slug, 0, 80);
    }
}


// ============================================================
// PlanoController.php
// ============================================================

namespace CRM\Financeiro;

use CRM\Shared\Http\Request;
use CRM\Shared\Http\Response;

class PlanoController
{
    private function service(Request $r): PlanoService
    {
        return new PlanoService($GLOBALS['pdo'], $r->tenantId());
    }

    // GET /api/planos
    public function index(Request $request): void
    {
        Response::json($this->service($request)->listar());
    }

    // POST /api/planos
    public function store(Request $request): void
    {
        $id = $this->service($request)->criar($request->json());
        Response::json(['id' => $id, 'message' => 'Plano criado.'], 201);
    }

    // PUT /api/planos/{id}
    public function update(Request $request, string $id): void
    {
        $this->service($request)->atualizar((int)$id, $request->json());
        Response::json(['ok' => true]);
    }

    // POST /api/publica/inscricao/{tenantSlug}/{planSlug}  (sem auth)
    public function inscricaoPublica(Request $request, string $tenantSlug, string $planSlug): void
    {
        $tenantId = $this->resolveTenantId($tenantSlug);
        if (!$tenantId) { Response::notFound('Associação não encontrada.'); return; }

        $service = new PlanoService($GLOBALS['pdo'], $tenantId);
        $result  = $service->processarInscricao($request->json(), $planSlug, $tenantId);
        Response::json($result, 201);
    }

    // GET /api/publica/plano/{tenantSlug}/{planSlug}  (sem auth)
    public function planoPublico(Request $request, string $tenantSlug, string $planSlug): void
    {
        $tenantId = $this->resolveTenantId($tenantSlug);
        if (!$tenantId) { Response::notFound(); return; }

        $service = new PlanoService($GLOBALS['pdo'], $tenantId);
        Response::json($service->buscarPorSlug($planSlug, $tenantId));
    }

    private function resolveTenantId(string $slug): ?int
    {
        $stmt = $GLOBALS['pdo']->prepare(
            "SELECT id FROM tenants WHERE slug = :slug AND ativo = 1 LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetchColumn();
        return $row ? (int)$row : null;
    }
}
