<?php
declare(strict_types=1);

// === caminhos auxiliares ===
function rootPath(): string { return dirname(__DIR__); }
function dataPath(string $sub=''): string {
  $base = rootPath() . DIRECTORY_SEPARATOR . 'data';
  return $sub ? $base . DIRECTORY_SEPARATOR . ltrim($sub, DIRECTORY_SEPARATOR) : $base;
}
function storagePath(string $sub=''): string {
  return dataPath('generated' . ($sub ? DIRECTORY_SEPARATOR . ltrim($sub, DIRECTORY_SEPARATOR) : ''));
}

/**
 * Retorna um PDO apontando para o SEU banco antigo: data/credencial.db
 * (Sem criar outro arquivo db.sqlite)
 */
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;

  if (!is_dir(dataPath())) mkdir(dataPath(),0777,true);
  if (!is_dir(storagePath())) mkdir(storagePath(),0777,true);

  // >>> AQUI ESTÁ O PULO DO GATO: usar credencial.db <<<
  $dsn = 'sqlite:' . dataPath('credencial.db');

  $pdo = new PDO($dsn, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec("PRAGMA busy_timeout = 3000;");
  $pdo->exec("PRAGMA foreign_keys = ON;");

  return $pdo;
}

/**
 * Mantém migrações idempotentes (não apaga nada).
 * Se suas colunas já existem, nada muda. 
 */
function bootstrap(): void {
  $pdo = db();

  // garante tabela base (idempotente)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS records (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      tipo TEXT NOT NULL,
      numero INTEGER NOT NULL,
      ano INTEGER NOT NULL,
      numero_formatado TEXT NOT NULL,
      nome TEXT NOT NULL,
      cid TEXT NULL,
      validade_anos INTEGER NOT NULL,
      data_emissao TEXT NOT NULL,
      data_validade TEXT NOT NULL,
      pdf_path TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
    CREATE INDEX IF NOT EXISTS idx_records_ano_tipo ON records(ano,tipo);
  ");

  // adiciona colunas que faltem (idempotente; se já existem, ele ignora)
  $cols = $pdo->query("PRAGMA table_info(records)")->fetchAll();
  $have = array_column($cols, 'name');

  $toAdd = [];
  // campos de cadastro
  if (!in_array('idade', $have, true))            $toAdd[]="ALTER TABLE records ADD COLUMN idade INTEGER NULL";
  if (!in_array('endereco', $have, true))         $toAdd[]="ALTER TABLE records ADD COLUMN endereco TEXT NULL";
  if (!in_array('is_menor', $have, true))         $toAdd[]="ALTER TABLE records ADD COLUMN is_menor INTEGER NOT NULL DEFAULT 0";
  if (!in_array('responsavel', $have, true))      $toAdd[]="ALTER TABLE records ADD COLUMN responsavel TEXT NULL";
  if (!in_array('data_nascimento', $have, true))  $toAdd[]="ALTER TABLE records ADD COLUMN data_nascimento TEXT NULL";

  // campos da assinatura
  if (!in_array('status', $have, true))                 $toAdd[]="ALTER TABLE records ADD COLUMN status TEXT";
  if (!in_array('signed_by', $have, true))              $toAdd[]="ALTER TABLE records ADD COLUMN signed_by TEXT";
  if (!in_array('signed_at', $have, true))              $toAdd[]="ALTER TABLE records ADD COLUMN signed_at TEXT";
  if (!in_array('signed_ip', $have, true))              $toAdd[]="ALTER TABLE records ADD COLUMN signed_ip TEXT";
  if (!in_array('signature_image_path', $have, true))   $toAdd[]="ALTER TABLE records ADD COLUMN signature_image_path TEXT";
  if (!in_array('signature_hash', $have, true))         $toAdd[]="ALTER TABLE records ADD COLUMN signature_hash TEXT";
  if (!in_array('pdf_signed_path', $have, true))        $toAdd[]="ALTER TABLE records ADD COLUMN pdf_signed_path TEXT";

  foreach ($toAdd as $sql) {
    try { $pdo->exec($sql); } catch (Throwable $e) { /* ignora se já existe */ }
  }

  // status padrão para registros antigos
  try {
    $pdo->exec("UPDATE records SET status='PENDENTE' WHERE status IS NULL OR TRIM(status)=''");
  } catch (Throwable $e) { /* silencioso */ }

  // índice útil
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_records_status ON records(status)");
}

/** Próximo número do ano/tipo (continua igual) */
function nextNumber(string $tipo, int $ano): int {
  $s = db()->prepare("SELECT COALESCE(MAX(numero),0) FROM records WHERE ano=? AND tipo=?");
  $s->execute([$ano, $tipo]);
  return ((int)$s->fetchColumn()) + 1;
}
