<?php declare(strict_types=1);

// ============================================================
// AssociadoService.php
// ============================================================

namespace CRM\Associado;

use CRM\Shared\Exceptions\ApiException;

class AssociadoService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly int  $tenantId,
    ) {}

    // ── Listar ────────────────────────────────────────────

    public function listar(array $filtros = []): array
    {
        $where  = ['a.tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];

        if (!empty($filtros['status'])) {
            $where[] = 'a.status = :status';
            $params[':status'] = $filtros['status'];
        }
        if (!empty($filtros['busca'])) {
            $where[] = '(a.razao_social LIKE :busca OR a.cnpj LIKE :busca OR a.email LIKE :busca)';
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }
        if (!empty($filtros['plano_id'])) {
            $where[] = 'a.plano_id = :plano_id';
            $params[':plano_id'] = $filtros['plano_id'];
        }

        $page    = max(1, (int)($filtros['page'] ?? 1));
        $limit   = min(100, (int)($filtros['limit'] ?? 25));
        $offset  = ($page - 1) * $limit;
        $whereStr = implode(' AND ', $where);

        $total = $this->pdo->prepare("SELECT COUNT(*) FROM associados a WHERE {$whereStr}");
        $total->execute($params);
        $totalRows = (int) $total->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT a.*, p.nome AS plano_nome
               FROM associados a
               LEFT JOIN planos p ON p.id = a.plano_id
              WHERE {$whereStr}
              ORDER BY a.criado_em DESC
              LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);

        return [
            'data'       => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total'      => $totalRows,
            'page'       => $page,
            'per_page'   => $limit,
            'last_page'  => (int) ceil($totalRows / $limit),
        ];
    }

    // ── Buscar por ID ─────────────────────────────────────

    public function buscar(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, p.nome AS plano_nome, p.valor AS plano_valor
               FROM associados a
               LEFT JOIN planos p ON p.id = a.plano_id
              WHERE a.id = :id AND a.tenant_id = :tid LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':tid' => $this->tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) throw new ApiException("Associado #{$id} não encontrado.", 404);

        return $row;
    }

    // ── Criar ─────────────────────────────────────────────

    public function criar(array $data, int $criadoPor): int
    {
        $this->validar($data);
        $this->verificarDuplicata($data);

        $campos = [
            'tenant_id', 'plano_id', 'tipo_pessoa', 'razao_social', 'nome_fantasia',
            'nome_responsavel', 'cpf', 'cnpj', 'email', 'telefone', 'whatsapp',
            'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
            'status', 'data_associacao', 'data_vencimento', 'campos_extras', 'criado_por',
        ];

        $insert = [];
        foreach ($campos as $c) {
            $insert[$c] = match ($c) {
                'tenant_id'  => $this->tenantId,
                'criado_por' => $criadoPor,
                'status'     => $data['status'] ?? 'ativo',
                'campos_extras' => isset($data['campos_extras'])
                    ? json_encode($data['campos_extras'])
                    : null,
                default => $data[$c] ?? null,
            };
        }

        $cols = implode(', ', array_keys($insert));
        $vals = implode(', ', array_map(fn($k) => ":{$k}", array_keys($insert)));
        $this->pdo->prepare("INSERT INTO associados ({$cols}) VALUES ({$vals})")->execute($insert);

        $id = (int) $this->pdo->lastInsertId();
        $this->audit($id, 'criado', $criadoPor);

        return $id;
    }

    // ── Atualizar ─────────────────────────────────────────

    public function atualizar(int $id, array $data, int $userId): array
    {
        $antes = $this->buscar($id);

        $permitidos = [
            'plano_id', 'razao_social', 'nome_fantasia', 'nome_responsavel',
            'email', 'telefone', 'whatsapp', 'cep', 'logradouro', 'numero',
            'complemento', 'bairro', 'cidade', 'uf', 'status',
            'data_vencimento', 'campos_extras',
        ];

        $set    = [];
        $params = [':id' => $id, ':tid' => $this->tenantId];

        foreach ($permitidos as $campo) {
            if (!array_key_exists($campo, $data)) continue;
            $set[]           = "{$campo} = :{$campo}";
            $params[":{$campo}"] = $campo === 'campos_extras'
                ? json_encode($data[$campo])
                : $data[$campo];
        }

        if (empty($set)) return $antes;

        $setStr = implode(', ', $set);
        $this->pdo->prepare(
            "UPDATE associados SET {$setStr}, atualizado_em = NOW()
              WHERE id = :id AND tenant_id = :tid"
        )->execute($params);

        // Audit apenas campos alterados
        foreach ($permitidos as $campo) {
            if (!array_key_exists($campo, $data)) continue;
            if ($data[$campo] == $antes[$campo]) continue;
            $this->audit($id, 'atualizado', $userId, $campo, (string)$antes[$campo], (string)$data[$campo]);
        }

        return $this->buscar($id);
    }

    // ── CSV Import ────────────────────────────────────────

    public function importarCsv(string $caminhoArquivo, int $userId): array
    {
        $csv     = \League\Csv\Reader::createFromPath($caminhoArquivo, 'r');
        $csv->setHeaderOffset(0);

        $ok = $erros = 0;

        foreach ($csv->getRecords() as $linha => $row) {
            try {
                $this->criar(array_merge($row, ['importado_csv' => 1]), $userId);
                $ok++;
            } catch (\Exception $e) {
                $erros++;
            }
        }

        return ['importados' => $ok, 'erros' => $erros];
    }

    // ── Validação ─────────────────────────────────────────

    private function validar(array $data): void
    {
        if (empty($data['razao_social']) && empty($data['nome_responsavel'])) {
            throw new ApiException('Nome ou razão social obrigatório.', 422);
        }
        if (empty($data['cnpj']) && empty($data['cpf'])) {
            throw new ApiException('CPF ou CNPJ obrigatório.', 422);
        }
    }

    private function verificarDuplicata(array $data): void
    {
        $doc   = $data['cnpj'] ?? $data['cpf'] ?? null;
        $campo = isset($data['cnpj']) ? 'cnpj' : 'cpf';

        if (!$doc) return;

        $stmt = $this->pdo->prepare(
            "SELECT id FROM associados WHERE {$campo} = :doc AND tenant_id = :tid LIMIT 1"
        );
        $stmt->execute([':doc' => $doc, ':tid' => $this->tenantId]);

        if ($stmt->fetchColumn()) {
            throw new ApiException("Já existe um associado com este {$campo}.", 409);
        }
    }

    private function audit(int $associadoId, string $acao, int $userId,
                           ?string $campo = null, ?string $antes = null, ?string $depois = null): void
    {
        $this->pdo->prepare(
            "INSERT INTO associados_audit
               (tenant_id, associado_id, usuario_id, acao, campo, valor_antes, valor_depois, ip)
             VALUES (:tid, :aid, :uid, :acao, :campo, :antes, :depois, :ip)"
        )->execute([
            ':tid'    => $this->tenantId,
            ':aid'    => $associadoId,
            ':uid'    => $userId,
            ':acao'   => $acao,
            ':campo'  => $campo,
            ':antes'  => $antes,
            ':depois' => $depois,
            ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}


// ============================================================
// AssociadoController.php
// ============================================================

namespace CRM\Associado;

use CRM\Shared\Http\Request;
use CRM\Shared\Http\Response;

class AssociadoController
{
    private function service(Request $r): AssociadoService
    {
        return new AssociadoService($GLOBALS['pdo'], $r->tenantId());
    }

    // GET /api/associados
    public function index(Request $request): void
    {
        $filtros = [
            'status'   => $request->query('status'),
            'busca'    => $request->query('busca'),
            'plano_id' => $request->query('plano_id'),
            'page'     => $request->query('page', 1),
            'limit'    => $request->query('limit', 25),
        ];
        Response::json($this->service($request)->listar($filtros));
    }

    // GET /api/associados/{id}
    public function show(Request $request, string $id): void
    {
        Response::json($this->service($request)->buscar((int)$id));
    }

    // POST /api/associados
    public function store(Request $request): void
    {
        $data = $request->json();
        $id   = $this->service($request)->criar($data, $request->user()['id']);
        Response::json(['id' => $id, 'message' => 'Associado criado com sucesso.'], 201);
    }

    // PUT /api/associados/{id}
    public function update(Request $request, string $id): void
    {
        $data   = $request->json();
        $result = $this->service($request)->atualizar((int)$id, $data, $request->user()['id']);
        Response::json($result);
    }

    // POST /api/associados/importar-csv
    public function importarCsv(Request $request): void
    {
        if (empty($_FILES['arquivo'])) {
            Response::json(['error' => 'Arquivo CSV não enviado.'], 422);
            return;
        }

        $tmp    = $_FILES['arquivo']['tmp_name'];
        $result = $this->service($request)->importarCsv($tmp, $request->user()['id']);
        Response::json($result);
    }
}
