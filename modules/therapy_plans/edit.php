<?php
/**
 * therapy_plans/edit.php
 * Modifica piano terapeutico esistente con sostituzione file opzionale.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM piani_terapeutici WHERE id = ? AND membro_id = ?');
$stmt->execute([$id, $membro['id']]);
$piano = $stmt->fetch();
if (!$piano) {
    setFlash('danger', 'Piano terapeutico non trovato.');
    header('Location: index.php');
    exit;
}

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $titolo     = trim($_POST['titolo']      ?? '');
    $dataInizio = trim($_POST['data_inizio'] ?? '');

    if (!$titolo) {
        $errore = 'Il titolo è obbligatorio.';
    } elseif (!$dataInizio) {
        $errore = 'La data di inizio è obbligatoria.';
    } else {
        // Mantieni file esistente di default, sostituisci se caricato nuovo
        $fileNome = $piano['file_nome'];
        $fileMime = $piano['file_mime'];
        $fileDati = $piano['file_dati'];

        if (!empty($_FILES['file_allegato']['name'])) {
            $file      = $_FILES['file_allegato'];
            $uploadErr = $file['error'];

            if ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE) {
                $errore = 'File troppo grande. Il limite del server è '
                    . ini_get('upload_max_filesize') . '. Massimo consentito dall\'app: ' . MAX_UPLOAD_MB . ' MB.';
            } elseif ($uploadErr === UPLOAD_ERR_PARTIAL) {
                $errore = 'Upload incompleto. Riprova.';
            } elseif ($uploadErr !== UPLOAD_ERR_OK) {
                $errore = 'Errore durante l\'upload (codice: ' . $uploadErr . '). Riprova.';
            } else {
                $mime         = mime_content_type($file['tmp_name']);
                $allowedMimes = unserialize(ALLOWED_MIME);

                if (!in_array($mime, $allowedMimes)) {
                    $errore = 'Tipo file non consentito (rilevato: ' . $mime . '). Usa PDF o immagine (JPG, PNG, GIF, WEBP).';
                } elseif ($file['size'] > MAX_UPLOAD_BYTE) {
                    $errore = 'File troppo grande (' . round($file['size'] / 1048576, 1) . ' MB). Massimo: ' . MAX_UPLOAD_MB . ' MB.';
                } else {
                    $fileNome = $file['name'];
                    $fileMime = $mime;
                    $fileDati = file_get_contents($file['tmp_name']);
                }
            }
        }

        // Rimuovi file se checkbox "elimina allegato" spuntata
        if (!empty($_POST['rimuovi_file'])) {
            $fileNome = $fileMime = $fileDati = null;
        }

        if (!$errore) {
            $dataFine = (!empty($_POST['indefinito']) || trim($_POST['data_fine'] ?? '') === '')
                        ? null
                        : trim($_POST['data_fine']);

            db()->prepare(
                'UPDATE piani_terapeutici SET
                    titolo = ?, tipo = ?, medico_redattore = ?, struttura = ?,
                    data_inizio = ?, data_fine = ?, obiettivo = ?, descrizione = ?,
                    farmaci_previsti = ?, note = ?, stato = ?,
                    file_nome = ?, file_mime = ?, file_dati = ?,
                    updated_at = NOW()
                 WHERE id = ?'
            )->execute([
                $titolo,
                trim($_POST['tipo']              ?? '') ?: null,
                trim($_POST['medico_redattore']  ?? '') ?: null,
                trim($_POST['struttura']         ?? '') ?: null,
                $dataInizio,
                $dataFine,
                trim($_POST['obiettivo']         ?? '') ?: null,
                trim($_POST['descrizione']       ?? '') ?: null,
                trim($_POST['farmaci_previsti']  ?? '') ?: null,
                trim($_POST['note']              ?? '') ?: null,
                $_POST['stato']                        ?? 'attivo',
                $fileNome, $fileMime, $fileDati,
                $id,
            ]);

            setFlash('success', 'Piano terapeutico aggiornato.');
            header('Location: view.php?id=' . $id);
            exit;
        }
    }
}

$pageTitle = 'Modifica Piano Terapeutico';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-journal-check me-2"></i>Modifica Piano Terapeutico</h1>
  <a href="view.php?id=<?= $piano['id'] ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Indietro
  </a>
</div>

<div class="row justify-content-center">
<div class="col-lg-10">
<div class="win-card">
  <div class="win-card-header">
    <i class="bi bi-journal-medical"></i>
    <?= h($piano['titolo']) ?>
  </div>
  <div class="win-card-body">

    <?php if ($errore): ?>
    <div class="alert alert-danger py-2">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($errore) ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <!-- ── Identificazione ──────────────────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-semibold mb-3">Identificazione</h6>
      <div class="row g-3 mb-4">
        <div class="col-md-7">
          <label class="form-label fw-semibold">Titolo *</label>
          <input type="text" name="titolo" class="form-control" required
                 value="<?= h($piano['titolo']) ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label fw-semibold">Tipo / Categoria</label>
          <select name="tipo" class="form-select">
            <option value="">— Seleziona —</option>
            <?php
            $tipi = ['Farmacologica','Riabilitativa','Oncologica','Psicologica',
                     'Nutrizionale','Fisioterapica','Palliativa','Post-operatoria',
                     'Preventiva','Cronica','Altra'];
            foreach ($tipi as $t):
            ?>
            <option value="<?= $t ?>" <?= ($piano['tipo'] === $t) ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Medico Redattore</label>
          <input type="text" name="medico_redattore" class="form-control"
                 value="<?= h($piano['medico_redattore'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Struttura / Reparto</label>
          <input type="text" name="struttura" class="form-control"
                 value="<?= h($piano['struttura'] ?? '') ?>">
        </div>
      </div>

      <hr class="my-3">

      <!-- ── Date e Stato ─────────────────────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-semibold mb-3">Durata e Stato</h6>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data Inizio *</label>
          <input type="date" name="data_inizio" class="form-control" required
                 value="<?= h($piano['data_inizio']) ?>">
        </div>
        <div class="col-md-4" id="col_data_fine">
          <label class="form-label fw-semibold">Data Fine</label>
          <input type="date" name="data_fine" id="data_fine" class="form-control"
                 value="<?= h($piano['data_fine'] ?? '') ?>"
                 <?= $piano['data_fine'] === null ? 'disabled' : '' ?>>
        </div>
        <div class="col-md-4 d-flex align-items-center pt-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="indefinito"
                   id="indefinito" value="1"
                   <?= ($piano['data_fine'] === null) ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="indefinito">
              <i class="bi bi-infinity me-1 text-info"></i>Durata Indefinita
            </label>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Stato</label>
          <select name="stato" class="form-select">
            <?php
            $stati = ['attivo'=>'Attivo','sospeso'=>'Sospeso',
                      'completato'=>'Completato','revocato'=>'Revocato'];
            foreach ($stati as $v => $l):
            ?>
            <option value="<?= $v ?>" <?= ($piano['stato'] === $v) ? 'selected' : '' ?>>
              <?= $l ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr class="my-3">

      <!-- ── Contenuto ─────────────────────────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-semibold mb-3">Contenuto del Piano</h6>
      <div class="row g-3 mb-4">
        <div class="col-12">
          <label class="form-label fw-semibold">Obiettivo Terapeutico</label>
          <textarea name="obiettivo" class="form-control" rows="2"
                    ><?= h($piano['obiettivo'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Descrizione / Indicazioni</label>
          <textarea name="descrizione" class="form-control" rows="4"
                    ><?= h($piano['descrizione'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Farmaci Previsti</label>
          <textarea name="farmaci_previsti" class="form-control" rows="4"
                    ><?= h($piano['farmaci_previsti'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Note</label>
          <textarea name="note" class="form-control" rows="2"
                    ><?= h($piano['note'] ?? '') ?></textarea>
        </div>
      </div>

      <hr class="my-3">

      <!-- ── Allegato ──────────────────────────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-semibold mb-3">Documento Allegato</h6>
      <div class="row g-3 mb-4">
        <div class="col-md-8">
          <?php if ($piano['file_nome']): ?>
          <!-- File attualmente allegato -->
          <div class="d-flex align-items-center gap-2 mb-2 p-2 border rounded bg-light">
            <i class="bi bi-<?= str_contains($piano['file_mime'] ?? '', 'pdf') ? 'file-earmark-pdf text-danger' : 'file-earmark-image text-info' ?> fs-5"></i>
            <span class="small flex-grow-1"><?= h($piano['file_nome']) ?></span>
            <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=piano_terapeutico&id=<?= $piano['id'] ?>"
               target="_blank" class="btn btn-sm btn-outline-info">
              <i class="bi bi-eye me-1"></i>Visualizza
            </a>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="rimuovi_file"
                   id="rimuovi_file" value="1">
            <label class="form-check-label text-danger small" for="rimuovi_file">
              <i class="bi bi-trash me-1"></i>Rimuovi file allegato
            </label>
          </div>
          <label class="form-label fw-semibold">Sostituisci con nuovo file</label>
          <?php else: ?>
          <label class="form-label fw-semibold">
            Allega Piano firmato
            <small class="text-muted fw-normal">(PDF o immagine — max <?= MAX_UPLOAD_MB ?> MB)</small>
          </label>
          <?php endif; ?>
          <input type="file" name="file_allegato" id="file_allegato"
                 class="form-control"
                 accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
          <div class="form-text" id="file_nome_preview"></div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-save me-1"></i>Salva Modifiche
        </button>
        <a href="view.php?id=<?= $piano['id'] ?>" class="btn btn-outline-secondary">Annulla</a>
      </div>
    </form>
  </div>
</div>
</div>
</div>

<script>
document.getElementById('indefinito').addEventListener('change', function () {
    var df = document.getElementById('data_fine');
    df.disabled = this.checked;
    if (this.checked) df.value = '';
    document.getElementById('col_data_fine').style.opacity = this.checked ? '.4' : '1';
});
(function () {
    var cb = document.getElementById('indefinito');
    if (cb && cb.checked) {
        document.getElementById('data_fine').disabled = true;
        document.getElementById('col_data_fine').style.opacity = '.4';
    }
})();
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
