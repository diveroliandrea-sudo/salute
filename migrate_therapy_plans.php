<?php
/**
 * migrate_therapy_plans.php
 * ============================================================
 * Migrazione one-shot: crea la tabella piani_terapeutici
 * nel database salute (se non esiste già).
 *
 * COME USARLO:
 *   1. Apri http://localhost/salute/migrate_therapy_plans.php
 *   2. Leggi il risultato
 *   3. Elimina (o rinomina) questo file dopo l'esecuzione
 * ============================================================
 */

require_once __DIR__ . '/includes/config.php';

$lockFile = __DIR__ . '/migrate_therapy_plans.lock';
if (file_exists($lockFile)) {
    die('<div style="font-family:sans-serif;padding:2rem;color:#856404;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;max-width:600px;margin:2rem auto">
        <h3>⚠ Migrazione già eseguita</h3>
        <p>Il file <code>migrate_therapy_plans.lock</code> è presente.</p>
        <p>Elimina il file lock solo se vuoi rieseguire la migrazione.</p>
        <a href="' . APP_URL . '/index.php" style="color:#0078d4">← Torna alla dashboard</a>
    </div>');
}

$pdo = db();
$log = [];
$ok  = true;

// ── DDL tabella piani_terapeutici ────────────────────────────
$sql = "
CREATE TABLE IF NOT EXISTS `piani_terapeutici` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `membro_id`         INT UNSIGNED  NOT NULL,
  `titolo`            VARCHAR(255)  NOT NULL COMMENT 'es. Terapia post-operatoria, Piano BPCO',
  `tipo`              VARCHAR(100)  DEFAULT NULL COMMENT 'es. Farmacologica, Riabilitativa, Oncologica',
  `medico_redattore`  VARCHAR(200)  DEFAULT NULL,
  `struttura`         VARCHAR(255)  DEFAULT NULL,
  `data_inizio`       DATE          NOT NULL,
  `data_fine`         DATE          DEFAULT NULL COMMENT 'NULL = durata indefinita',
  `obiettivo`         TEXT          DEFAULT NULL,
  `descrizione`       TEXT          DEFAULT NULL,
  `farmaci_previsti`  TEXT          DEFAULT NULL,
  `note`              TEXT          DEFAULT NULL,
  `stato`             ENUM('attivo','completato','sospeso','revocato') NOT NULL DEFAULT 'attivo',
  `file_nome`         VARCHAR(255)  DEFAULT NULL,
  `file_mime`         VARCHAR(100)  DEFAULT NULL,
  `file_dati`         LONGBLOB      DEFAULT NULL,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pt_membro` (`membro_id`),
  CONSTRAINT `fk_pt_membro` FOREIGN KEY (`membro_id`)
    REFERENCES `membri_famiglia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

try {
    $pdo->exec($sql);
    $log[] = ['ok', '✔ Tabella <code>piani_terapeutici</code> creata (o già esistente)'];
} catch (PDOException $e) {
    $log[] = ['err', '✘ Errore: ' . htmlspecialchars($e->getMessage())];
    $ok = false;
}

// ── Verifica che la tabella sia effettivamente accessibile ───
if ($ok) {
    try {
        $pdo->query('SELECT 1 FROM piani_terapeutici LIMIT 1');
        $log[] = ['ok', '✔ Verifica accesso tabella: OK'];
    } catch (PDOException $e) {
        $log[] = ['err', '✘ Verifica fallita: ' . htmlspecialchars($e->getMessage())];
        $ok = false;
    }
}

// ── Scrivi lock solo se tutto OK ─────────────────────────────
if ($ok) {
    file_put_contents($lockFile, 'Migrazione eseguita il ' . date('Y-m-d H:i:s') . "\n");
    $log[] = ['ok', '✔ File <code>migrate_therapy_plans.lock</code> creato'];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Migrazione — Piani Terapeutici</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    body { background:#f0f2f5; font-family:'Segoe UI',system-ui,sans-serif; }
    .card { max-width:640px; margin:3rem auto; border-radius:8px; box-shadow:0 2px 12px rgba(0,0,0,.1); }
    .card-header { background:#0078d4; color:#fff; border-radius:8px 8px 0 0; }
    pre { background:#1e1e1e; color:#d4d4d4; border-radius:6px; padding:1rem; font-size:.82rem; }
    .ok  { color:#4ec9b0 }
    .err { color:#f44747 }
  </style>
</head>
<body>
<div class="card">
  <div class="card-header py-3 px-4">
    <h5 class="mb-0 fw-bold">
      <i class="bi bi-journal-medical me-2"></i>
      Migrazione — Piani Terapeutici
    </h5>
  </div>
  <div class="card-body p-4">

    <?php if ($ok): ?>
    <div class="alert alert-success">
      <strong>✔ Migrazione completata con successo!</strong><br>
      La tabella <code>piani_terapeutici</code> è pronta.
    </div>
    <?php else: ?>
    <div class="alert alert-danger">
      <strong>✘ Migrazione fallita.</strong><br>
      Controlla il log qui sotto e verifica la connessione al database.
    </div>
    <?php endif; ?>

    <h6 class="fw-semibold mt-3 mb-2">Log esecuzione</h6>
    <pre><?php foreach ($log as [$tipo, $msg]): ?>
<span class="<?= $tipo ?>"><?= $msg ?></span>
<?php endforeach; ?></pre>

    <div class="d-flex gap-2 mt-3">
      <?php if ($ok): ?>
      <a href="<?= APP_URL ?>/modules/therapy_plans/index.php"
         class="btn btn-primary">
        <i class="bi bi-journal-medical me-1"></i>Vai ai Piani Terapeutici
      </a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/index.php" class="btn btn-outline-secondary">
        ← Dashboard
      </a>
    </div>

    <?php if ($ok): ?>
    <div class="alert alert-warning mt-3 mb-0 py-2" style="font-size:.85rem">
      <i class="bi bi-shield-exclamation me-1"></i>
      Puoi eliminare il file <code>migrate_therapy_plans.php</code> dal server dopo questa operazione.
    </div>
    <?php endif; ?>

  </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>
