<?php
/**
 * hospitalizations/add.php
 * Aggiunta nuovo ricovero con lettera di dimissione.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $struttura = trim($_POST['struttura'] ?? '');
    $dataIng   = trim($_POST['data_ingresso'] ?? '');

    if (!$struttura || !$dataIng) {
        $errore = 'Struttura e Data di Ingresso sono obbligatorie.';
    } else {
        // Gestione file lettera di dimissione
        $letNome = $letMime = $letDati = null;
        if (!empty($_FILES['lettera']['name'])) {
            $file   = $_FILES['lettera'];
            $mime   = mime_content_type($file['tmp_name']);
            $allowedMimes = unserialize(ALLOWED_MIME);
            if (!in_array($mime, $allowedMimes)) {
                $errore = 'Tipo file non consentito per la lettera di dimissione.';
            } elseif ($file['size'] > MAX_UPLOAD_BYTE) {
                $errore = 'File troppo grande (max ' . MAX_UPLOAD_MB . ' MB).';
            } else {
                $letNome = $file['name'];
                $letMime = $mime;
                $letDati = file_get_contents($file['tmp_name']);
            }
        }

        if (!$errore) {
            db()->prepare(
                'INSERT INTO ricoveri
                 (membro_id, struttura, reparto, data_ingresso, data_dimissione,
                  diagnosi_ingresso, motivo_ricovero, diagnosi_dimissione, terapia_dimissione,
                  medico_responsabile, note, lettera_file_nome, lettera_file_mime, lettera_file_dati)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $membro['id'],
                $struttura,
                trim($_POST['reparto']              ?? '') ?: null,
                $dataIng,
                trim($_POST['data_dimissione']      ?? '') ?: null,
                trim($_POST['diagnosi_ingresso']    ?? '') ?: null,
                trim($_POST['motivo_ricovero']      ?? '') ?: null,
                trim($_POST['diagnosi_dimissione']  ?? '') ?: null,
                trim($_POST['terapia_dimissione']   ?? '') ?: null,
                trim($_POST['medico_responsabile']  ?? '') ?: null,
                trim($_POST['note']                 ?? '') ?: null,
                $letNome, $letMime, $letDati,
            ]);

            setFlash('success', 'Ricovero registrato correttamente.');
            header('Location: index.php'); exit;
        }
    }
}

$pageTitle = 'Nuovo Ricovero';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-hospital me-2"></i>Nuovo Ricovero</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Indietro</a>
</div>

<div class="row justify-content-center">
<div class="col-lg-10">
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-hospital"></i> Dati Ricovero — <?= h($membro['nome'].' '.$membro['cognome']) ?></div>
  <div class="win-card-body">
    <?php if ($errore): ?><div class="alert alert-danger py-2"><?= h($errore) ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <h6 class="text-muted text-uppercase small fw-semibold mb-3">Dati Ospedalieri</h6>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Struttura Ospedaliera *</label>
          <input type="text" name="struttura" class="form-control" required
                 value="<?= h($_POST['struttura'] ?? '') ?>" placeholder="es. Ospedale Civile di...">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Reparto</label>
          <input type="text" name="reparto" class="form-control"
                 value="<?= h($_POST['reparto'] ?? '') ?>" placeholder="es. Cardiologia, Ortopedia">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data Ingresso *</label>
          <input type="date" name="data_ingresso" class="form-control" required
                 value="<?= h($_POST['data_ingresso'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data Dimissione</label>
          <input type="date" name="data_dimissione" class="form-control"
                 value="<?= h($_POST['data_dimissione'] ?? '') ?>">
          <div class="form-text">Lascia vuoto se il ricovero è ancora in corso.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Medico Responsabile</label>
          <input type="text" name="medico_responsabile" class="form-control"
                 value="<?= h($_POST['medico_responsabile'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Diagnosi di Ingresso</label>
          <textarea name="diagnosi_ingresso" class="form-control" rows="3"><?= h($_POST['diagnosi_ingresso'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Motivo del Ricovero</label>
          <textarea name="motivo_ricovero" class="form-control" rows="3"><?= h($_POST['motivo_ricovero'] ?? '') ?></textarea>
        </div>
      </div>

      <hr>
      <h6 class="text-muted text-uppercase small fw-semibold mb-3">Lettera di Dimissione</h6>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Diagnosi alla Dimissione</label>
          <textarea name="diagnosi_dimissione" class="form-control" rows="3"><?= h($_POST['diagnosi_dimissione'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Terapia Consigliata al Rientro</label>
          <textarea name="terapia_dimissione" class="form-control" rows="3"><?= h($_POST['terapia_dimissione'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">
            <i class="bi bi-paperclip me-1"></i>Allega Lettera di Dimissione
            <small class="text-muted">(PDF o immagine, max <?= MAX_UPLOAD_MB ?> MB)</small>
          </label>
          <input type="file" name="lettera" id="file_allegato" class="form-control"
                 accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
          <div class="form-text" id="file_nome_preview"></div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Note</label>
          <textarea name="note" class="form-control" rows="2"><?= h($_POST['note'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salva Ricovero</button>
        <a href="index.php" class="btn btn-outline-secondary">Annulla</a>
      </div>
    </form>
  </div>
</div>
</div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
