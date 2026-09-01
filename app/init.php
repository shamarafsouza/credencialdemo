<?php
declare(strict_types=1);

/**
 * Caminhos
 */
function rootPath(): string {
  return dirname(__DIR__);
}

function dataPath(string $sub=''): string {
  $base = rootPath() . DIRECTORY_SEPARATOR . 'data';
  return $sub ? $base . DIRECTORY_SEPARATOR . ltrim($sub, DIRECTORY_SEPARATOR) : $base;
}

function storagePath(string $sub=''): string {
  return dataPath('generated' . ($sub ? DIRECTORY_SEPARATOR . ltrim($sub, DIRECTORY_SEPARATOR) : ''));
}

/**
 * Conexão PDO (um ÚNICO arquivo de banco para o sistema inteiro)
 */
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  // Garante diretórios
  @is_dir(dataPath())        || @mkdir(dataPath(),        0777, true);
  @is_dir(storagePath())     || @mkdir(storagePath(),     0777, true);
  @is_dir(dataPath('signatures'))   || @mkdir(dataPath('signatures'),   0777, true);
  @is_dir(dataPath('signed_pdfs'))  || @mkdir(dataPath('signed_pdfs'),  0777, true);

  // UM ÚNICO arquivo de DB
  $dsn = 'sqlite:' . dataPath('credencial.db');

  $pdo = new PDO($dsn, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ]);

  // PRAGMAs úteis
  $pdo->exec("PRAGMA foreign_keys = ON;");
  $pdo->exec("PRAGMA busy_timeout = 5000;"); // reduz lock em operações rápidas

  return $pdo;
}

/**
 * Inicialização / Migrações
 * - Cria a base de 'records'
 * - Adiciona colunas que possam faltar (idempotente)
 * - Cria índices
 */
function bootstrap(): void {
  $pdo = db();

  // Tabela base mínima (não remova colunas já existentes no seu projeto)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS records (
      id                INTEGER PRIMARY KEY AUTOINCREMENT,
      tipo              TEXT     NOT NULL,
      numero            INTEGER  NOT NULL,
      ano               INTEGER  NOT NULL,
      numero_formatado  TEXT     NOT NULL,
      nome              TEXT     NOT NULL,
      cid               TEXT     NULL,
      validade_anos     INTEGER  NOT NULL,
      data_emissao      TEXT     NOT NULL,
      data_validade     TEXT     NOT NULL,
      pdf_path          TEXT     NOT NULL,
      created_at        TEXT     NOT NULL DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_records_ano_tipo ON records(ano, tipo);
  ");

  // Descobre colunas atuais
  $cols    = $pdo->query("PRAGMA table_info(records)")->fetchAll();
  $have    = array_map(fn($c) => $c['name'], $cols);
  $toAlter = [];

  // (Campos extras antigos do seu projeto — mantenha se você usa)
  if (!in_array('idade',            $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN idade INTEGER NULL";
  if (!in_array('endereco',         $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN endereco TEXT NULL";
  if (!in_array('is_menor',         $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN is_menor INTEGER NOT NULL DEFAULT 0";
  if (!in_array('responsavel',      $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN responsavel TEXT NULL";
  if (!in_array('data_nascimento',  $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN data_nascimento TEXT NULL"; // YYYY-MM-DD

  // >>> Campos para ASSINATURA <<<
  if (!in_array('status',               $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN status TEXT NOT NULL DEFAULT 'PENDENTE'";
  if (!in_array('signed_by',            $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN signed_by TEXT NULL";
  if (!in_array('signed_at',            $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN signed_at TEXT NULL";
  if (!in_array('signed_ip',            $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN signed_ip TEXT NULL";
  if (!in_array('signature_image_path', $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN signature_image_path TEXT NULL";
  if (!in_array('signature_hash',       $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN signature_hash TEXT NULL";
  if (!in_array('pdf_signed_path',      $have, true)) $toAlter[] = "ALTER TABLE records ADD COLUMN pdf_signed_path TEXT NULL";

  // Executa migrações idempotentes
  foreach ($toAlter as $sql) {
    try { $pdo->exec($sql); } catch (Throwable $e) { /* ignora se já existe */ }
  }

  // Índices auxiliares
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_records_status ON records(status)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_records_signed ON records(signed_by, signed_at)");

  // Normaliza status de registros antigos
  try {
    $pdo->exec("UPDATE records SET status='PENDENTE' WHERE status IS NULL OR TRIM(status)=''");
  } catch (Throwable $e) {
    // se a coluna já existia ok
  }
}

/**
 * Próximo número sequencial por (tipo, ano)
 */
function nextNumber(string $tipo, int $ano): int {
  $s = db()->prepare("SELECT COALESCE(MAX(numero), 0) FROM records WHERE ano = ? AND tipo = ?");
  $s->execute([$ano, $tipo]);
  return ((int)$s->fetchColumn()) + 1;
}
