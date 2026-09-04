<?php
/**
 * medications/edit.php
 * Modifica farmaco e relativi orari di somministrazione.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM farmaci WHERE id=? AND membro_id=?');
$stmt->execute([$id, $membro['id']]);
$f = $stmt->fetch();
if (!$f) { setFlash('danger','Farmaco non trovato.'); header('Location: index.php'); exit; }

// Orari esistenti
$orariEsistenti = db()->prepare('SELECT * FROM farmaci_orari WHERE farmaco_id=? ORDER BY ora');
$orariEsistenti->execute([$id]);
$orariEsistenti = $orariEsistenti->fetchAll();

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $nome = trim($_POST['nome_farmaco'] ?? '');
    if (!$nome) { $errore = 'Il nome del farmaco è obbligatorio.'; }
    else {
        // ── Gestione upload scontrino ─────────────────────────
        $nuovoNome = null;
        $nuovoMime = null;
        $nuovoDati = null;
        $rimuovi   = !empty($_POST['rimuovi_scontrino']);

        if (!empty($_FILES['scontrino']['tmp_name'])) {
            $file = $_FILES['scontrino'];
            if ($file['size'] > MAX_UPLOAD_BYTE) {
                $errore = 'Il file scontrino supera il limite di ' . MAX_UPLOAD_MB . ' MB.';
            } else {
                $dati = file_get_contents($file['tmp_name']);
                $mime = getMimeFromContent($dati);
                $allowed = unserialize(ALLOWED_MIME);
                if (!in_array($mime, $allowed)) {
                    $errore = 'Tipo di file non consentito. Usa PDF o immagine.';
                } else {
                    $nuovoNome = basename($file['name']);
                    $nuovoMime = $mime;
                    $nuovoDati = $dati;
                }
            }
        }

        if (!$errore) {
        $cronico  = isset($_POST['cronico']) ? 1 : 0;
        $dataFine = (!$cronico && !empty($_POST['data_fine'])) ? $_POST['data_fine'] : null;

        // Costruisce la parte scontrino dell'UPDATE
        if ($nuovoDati !== null) {
            // Nuovo file caricato: sostituisce
            $sqlScontrino = ', scontrino_file_nome=?, scontrino_file_mime=?, scontrino_file_dati=?';
            $paramsScontrino = [$nuovoNome, $nuovoMime, $nuovoDati];
        } elseif ($rimuovi) {
            // Rimozione esplicita
            $sqlScontrino = ', scontrino_file_nome=NULL, scontrino_file_mime=NULL, scontrino_file_dati=NULL';
            $paramsScontrino = [];
        } else {
            // Nessuna modifica al file
            $sqlScontrino = '';
            $paramsScontrino = [];
        }

        $params = [
            $nome,
            trim($_POST['principio_attivo'] ?? '') ?: null,
            trim($_POST['dosaggio']         ?? '') ?: null,
            trim($_POST['forma']            ?? '') ?: null,
            trim($_POST['note_assunzione']  ?? '') ?: null,
            $_POST['data_inizio']           ?: null,
            $dataFine,
            $cronico,
            isset($_POST['attivo']) ? 1 : 0,
            trim($_POST['note'] ?? '') ?: null,
            ...$paramsScontrino,
            $id,
        ];

        db()->prepare(
            'UPDATE farmaci SET nome_farmaco=?, principio_attivo=?, dosaggio=?, forma=?,
             note_assunzione=?, data_inizio=?, data_fine=?, cronico=?, attivo=?, note=?'
            . $sqlScontrino .
            ', updated_at=NOW() WHERE id=?'
        )->execute($params);

        // Riscrive tutti gli orari
        db()->prepare('DELETE FROM farmaci_orari WHERE farmaco_id=?')->execute([$id]);
        $insOra = db()->prepare('INSERT INTO farmaci_orari (farmaco_id, ora, quantita, note) VALUES (?,?,?,?)');
        foreach ($_POST['orari'] ?? [] as $o) {
            $ora = trim($o['ora'] ?? '');
            if ($ora) $insOra->execute([$id, $ora, trim($o['quantita']??'1')?:'1', trim($o['note']??'')?:null]);
        }

        setFlash('success', 'Farmaco aggiornato.');
        header('Location: index.php'); exit;
        } // end if (!$errore)
    }
}

$pageTitle = 'Modifica Farmaco';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-capsule me-2"></i>Modifica Farmaco</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Indietro</a>
</div>
<div class="row justify-content-center">
<div class="col-lg-9">
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-capsule"></i> <?= h($f['nome_farmaco']) ?></div>
  <div class="win-card-body">
    <?php if ($errore): ?><div class="alert alert-danger py-2"><?= h($errore) ?></div><?php endif; ?>
    <form method="POST" id="formFarmaco" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" id="orario_count" name="orario_count" value="<?= count($orariEsistenti) ?>">

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Nome Farmaco *</label>
          <input type="text" name="nome_farmaco" class="form-control" required value="<?= h($f['nome_farmaco']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Principio Attivo</label>
          <input type="text" name="principio_attivo" class="form-control" value="<?= h($f['principio_attivo'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Dosaggio</label>
          <input type="text" name="dosaggio" class="form-control" value="<?= h($f['dosaggio'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Forma</label>
          <select name="forma" class="form-select">
            <option value="">— Seleziona —</option>
            <?php foreach (['Compressa','Capsula','Sciroppo','Fiala','Gocce','Crema','Supposte','Spray','Cerotto','Altro'] as $fo): ?>
            <option value="<?= $fo ?>" <?= ($f['forma']==$fo)?'selected':'' ?>><?= $fo ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Note di Assunzione</label>
          <input type="text" name="note_assunzione" class="form-control" value="<?= h($f['note_assunzione'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data Inizio</label>
          <input type="date" name="data_inizio" class="form-control" value="<?= h($f['data_inizio'] ?? '') ?>">
        </div>
        <div class="col-md-4" id="data_fine_row">
          <label class="form-label fw-semibold">Data Fine</label>
          <input type="date" name="data_fine" id="data_fine" class="form-control"
                 value="<?= h($f['data_fine'] ?? '') ?>" <?= $f['cronico'] ? 'disabled' : '' ?>>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div>
            <div class="form-check mb-1">
              <input class="form-check-input" type="checkbox" name="cronico" id="cronico" value="1"
                     <?= $f['cronico'] ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="cronico">Terapia Cronica</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="attivo" id="attivo" value="1"
                     <?= $f['attivo'] ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="attivo">Farmaco Attivo</label>
            </div>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Note</label>
          <textarea name="note" class="form-control" rows="2"><?= h($f['note'] ?? '') ?></textarea>
        </div>
        <!-- ── Scontrino / Ricevuta ───────────────────────── -->
        <div class="col-12">
          <label class="form-label fw-semibold"><i class="bi bi-receipt me-1"></i>Scontrino / Ricevuta</label>
          <?php if (!empty($f['scontrino_file_nome'])): ?>
          <div class="d-flex align-items-center gap-3 mb-2 p-2 bg-light rounded border">
            <i class="bi bi-file-earmark-<?= str_contains($f['scontrino_file_mime']??'','pdf') ? 'pdf text-danger' : 'image text-info' ?> fs-4"></i>
            <div class="flex-grow-1">
              <div class="fw-semibold small"><?= h($f['scontrino_file_nome']) ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= h($f['scontrino_file_mime']) ?></div>
            </div>
            <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=farmaco&id=<?= $f['id'] ?>"
               target="_blank" class="btn btn-sm btn-outline-info">
              <i class="bi bi-eye me-1"></i>Visualizza
            </a>
            <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=farmaco&id=<?= $f['id'] ?>&mode=download"
               class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-download me-1"></i>Scarica
            </a>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="rimuovi_scontrino" id="rimuovi_scontrino" value="1">
            <label class="form-check-label text-danger small" for="rimuovi_scontrino">Rimuovi scontrino esistente</label>
          </div>
          <label class="form-label small text-muted">Oppure carica un nuovo file per sostituire quello esistente:</label>
          <?php else: ?>
          <p class="text-muted small mb-1">Nessuno scontrino allegato.</p>
          <?php endif; ?>
          <input type="file" name="scontrino" class="form-control"
                 accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
          <div class="form-text">Formati accettati: PDF, JPG, PNG, GIF, WEBP (max <?= MAX_UPLOAD_MB ?> MB).</div>
        </div>
      </div>

      <hr>
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="mb-0"><i class="bi bi-clock me-2"></i>Orari di Somministrazione</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn_add_orario">
          <i class="bi bi-plus-circle me-1"></i>Aggiungi Orario
        </button>
      </div>
      <div class="row g-2 mb-1">
        <div class="col-md-3"><small class="text-muted fw-semibold">Ora</small></div>
        <div class="col-md-3"><small class="text-muted fw-semibold">Quantità</small></div>
        <div class="col-md-4"><small class="text-muted fw-semibold">Note Orario</small></div>
        <div class="col-md-2"></div>
      </div>
      <div id="orari_container">
        <!-- Orari pre-esistenti pre-caricati -->
        <?php foreach ($orariEsistenti as $i => $o): ?>
        <div class="row g-2 align-items-center orario-row mb-2" id="orario_row_<?= $i ?>">
          <div class="col-md-3">
            <input type="time" name="orari[<?= $i ?>][ora]" class="form-control form-control-sm"
                   value="<?= h(substr($o['ora'],0,5)) ?>" required>
          </div>
          <div class="col-md-3">
            <input type="text" name="orari[<?= $i ?>][quantita]" class="form-control form-control-sm"
                   placeholder="Quantità es. 1" value="<?= h($o['quantita']) ?>">
          </div>
          <div class="col-md-4">
            <input type="text" name="orari[<?= $i ?>][note]" class="form-control form-control-sm"
                   placeholder="Note orario" value="<?= h($o['note'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-orario" data-row="<?= $i ?>">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salva Modifiche</button>
        <a href="index.php" class="btn btn-outline-secondary">Annulla</a>
      </div>
    </form>
  </div>
</div>
</div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
