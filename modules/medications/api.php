<?php
/**
 * medications/api.php
 * Endpoint AJAX per:
 *   action=add_farmaco          → inserisce nuovo farmaco + cadenza
 *   action=add_somministrazione → registra somministrazione effettiva
 *   action=del_somministrazione → elimina somministrazione
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');

// Deve essere loggato
if (empty($_SESSION['utente_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Non autenticato.']);
    exit;
}

$membro = membroSelezionato();
if (!$membro) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Nessun membro selezionato.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Vincolo UNIQUE su farmaci (membro_id, nome_farmaco) ──────
// Aggiunge l'indice solo se non esiste già (ignora l'errore se già presente)
try {
    db()->exec(
        "ALTER TABLE `farmaci`
         ADD UNIQUE KEY `uq_farmaco_membro_nome` (`membro_id`, `nome_farmaco`(191))"
    );
} catch (PDOException $e) {
    // Errore 1061 = indice già esistente → ok
    if ((int)$e->getCode() !== 42000 && strpos($e->getMessage(), '1061') === false
        && strpos($e->getMessage(), 'Duplicate key') === false
        && strpos($e->getMessage(), 'already exists') === false) {
        // Altro errore inatteso: lo ignoriamo comunque per non bloccare
    }
}

// ── Crea tabella somministrazioni se non esiste ──────────────
db()->exec("CREATE TABLE IF NOT EXISTS `farmaci_somministrazioni` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `farmaco_id`  INT UNSIGNED NOT NULL,
  `data_ora`    DATETIME     NOT NULL,
  `quantita`    VARCHAR(50)  NOT NULL DEFAULT '1',
  `modalita`    VARCHAR(100) DEFAULT NULL,
  `zona`        VARCHAR(100) DEFAULT NULL,
  `note`        VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fs_farmaco` (`farmaco_id`),
  KEY `idx_fs_data_ora` (`data_ora`),
  CONSTRAINT `fk_fs_farmaco`
    FOREIGN KEY (`farmaco_id`) REFERENCES `farmaci` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Aggiunge colonne se la tabella esisteva già senza di esse
foreach (['modalita VARCHAR(100) DEFAULT NULL', 'zona VARCHAR(100) DEFAULT NULL'] as $colDef) {
    $col = explode(' ', $colDef)[0];
    try { db()->exec("ALTER TABLE `farmaci_somministrazioni` ADD COLUMN `$col` $colDef"); }
    catch (PDOException $e) { /* già presente */ }
}

// ── Helper risposta ──────────────────────────────────────────
function ok(array $data = []): void {
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}
function err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'msg' => $msg]);
    exit;
}

// ============================================================
switch ($action) {

// ── Toggle attivo/sospeso ────────────────────────────────────
case 'toggle_attivo': {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Metodo non consentito.', 405);
    verifyCsrf();

    $farmId = (int)($_POST['farmaco_id'] ?? 0);
    if (!$farmId) err('ID mancante.');

    $chk = db()->prepare('SELECT id, attivo FROM farmaci WHERE id=? AND membro_id=?');
    $chk->execute([$farmId, $membro['id']]);
    $row = $chk->fetch();
    if (!$row) err('Farmaco non trovato.', 404);

    $nuovoStato = $row['attivo'] ? 0 : 1;
    db()->prepare('UPDATE farmaci SET attivo=?, updated_at=NOW() WHERE id=?')
        ->execute([$nuovoStato, $farmId]);

    ok(['attivo' => $nuovoStato]);
}

// ── Aggiungi farmaco ─────────────────────────────────────────
case 'add_farmaco': {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Metodo non consentito.', 405);
    verifyCsrf();

    $nome = trim($_POST['nome_farmaco'] ?? '');
    if (!$nome) err('Il nome del farmaco è obbligatorio.');

    // Controlla duplicato
    $dup = db()->prepare(
        'SELECT id FROM farmaci WHERE membro_id=? AND nome_farmaco=?'
    );
    $dup->execute([$membro['id'], $nome]);
    if ($dup->fetch()) {
        err('Il farmaco "' . $nome . '" è già presente nell\'elenco.');
    }

    try {
        $ins = db()->prepare(
            'INSERT INTO farmaci
             (membro_id, nome_farmaco, principio_attivo, dosaggio, forma,
              note_assunzione, data_inizio, cronico, attivo, note,
              scontrino_file_nome, scontrino_file_mime, scontrino_file_dati)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)'
        );

        // ── Gestione upload scontrino/prescrizione ────────────
        $sNome = null; $sMime = null; $sDati = null;
        if (!empty($_FILES['scontrino']['tmp_name']) && $_FILES['scontrino']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['scontrino'];
            if ($file['size'] > MAX_UPLOAD_BYTE) {
                err('Il file supera il limite di ' . MAX_UPLOAD_MB . ' MB.');
            }
            $dati = file_get_contents($file['tmp_name']);
            $mime = getMimeFromContent($dati);
            if (!in_array($mime, unserialize(ALLOWED_MIME))) {
                err('Tipo di file non consentito. Usa PDF o immagine.');
            }
            $sNome = basename($file['name']);
            $sMime = $mime;
            $sDati = $dati;
        }

        $ins->execute([
            $membro['id'],
            $nome,
            trim($_POST['principio_attivo'] ?? '') ?: null,
            trim($_POST['dosaggio']         ?? '') ?: null,
            trim($_POST['forma']            ?? '') ?: null,
            trim($_POST['note_assunzione']  ?? '') ?: null,
            date('Y-m-d'),
            isset($_POST['cronico']) ? 1 : 0,
            trim($_POST['note'] ?? '') ?: null,
            $sNome, $sMime, $sDati,
        ]);
    } catch (PDOException $e) {
        // Violazione UNIQUE (codice 23000)
        if (strpos($e->getCode(), '23') === 0 || strpos($e->getMessage(), '1062') !== false) {
            err('Il farmaco "' . $nome . '" è già presente nell\'elenco.');
        }
        throw $e;
    }
    $farmId = (int)db()->lastInsertId();

    // Salva cadenza (orari + giorni)
    $orari = $_POST['orari'] ?? [];
    $insOra = db()->prepare(
        'INSERT INTO farmaci_orari (farmaco_id, ora, quantita, giorni, note)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($orari as $o) {
        $ora = trim($o['ora'] ?? '');
        if (!$ora) continue;
        $giorniSel = $o['giorni'] ?? [];
        $giorniStr = empty($giorniSel) ? 'tutti' : implode(',', array_map('trim', (array)$giorniSel));
        $insOra->execute([
            $farmId,
            $ora,
            trim($o['quantita'] ?? '1') ?: '1',
            $giorniStr,
            trim($o['note'] ?? '') ?: null,
        ]);
    }

    // Rileggi il farmaco appena inserito con i suoi orari
    $f = db()->prepare('SELECT * FROM farmaci WHERE id=?');
    $f->execute([$farmId]);
    $farmaco = $f->fetch();

    $stmtO = db()->prepare('SELECT * FROM farmaci_orari WHERE farmaco_id=? ORDER BY ora');
    $stmtO->execute([$farmId]);
    $farmaco['orari'] = $stmtO->fetchAll();

    ok(['farmaco' => $farmaco]);
}

// ── Registra somministrazione ────────────────────────────────
case 'add_somministrazione': {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Metodo non consentito.', 405);
    verifyCsrf();

    $farmId  = (int)($_POST['farmaco_id'] ?? 0);
    $dataOra = trim($_POST['data_ora'] ?? '');
    if (!$farmId || !$dataOra) err('Dati mancanti.');

    // Verifica ownership
    $chk = db()->prepare('SELECT id FROM farmaci WHERE id=? AND membro_id=?');
    $chk->execute([$farmId, $membro['id']]);
    if (!$chk->fetch()) err('Farmaco non trovato.', 404);

    $quantita = trim($_POST['quantita'] ?? '1') ?: '1';
    $modalita = trim($_POST['modalita'] ?? '') ?: null;
    $zona     = trim($_POST['zona']     ?? '') ?: null;
    $note     = trim($_POST['note']     ?? '') ?: null;

    $ins = db()->prepare(
        'INSERT INTO farmaci_somministrazioni (farmaco_id, data_ora, quantita, modalita, zona, note)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$farmId, $dataOra, $quantita, $modalita, $zona, $note]);
    $somId = (int)db()->lastInsertId();

    ok([
        'somministrazione' => [
            'id'       => $somId,
            'data_ora' => $dataOra,
            'quantita' => $quantita,
            'modalita' => $modalita,
            'zona'     => $zona,
            'note'     => $note,
        ]
    ]);
}

// ── Elimina somministrazione ─────────────────────────────────
case 'del_somministrazione': {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Metodo non consentito.', 405);
    verifyCsrf();

    $somId = (int)($_POST['som_id'] ?? 0);
    if (!$somId) err('ID mancante.');

    // Verifica ownership tramite join
    $chk = db()->prepare(
        'SELECT fs.id FROM farmaci_somministrazioni fs
         JOIN farmaci f ON f.id = fs.farmaco_id
         WHERE fs.id=? AND f.membro_id=?'
    );
    $chk->execute([$somId, $membro['id']]);
    if (!$chk->fetch()) err('Somministrazione non trovata.', 404);

    db()->prepare('DELETE FROM farmaci_somministrazioni WHERE id=?')->execute([$somId]);
    ok();
}

default:
    err('Azione non riconosciuta.', 400);
}
