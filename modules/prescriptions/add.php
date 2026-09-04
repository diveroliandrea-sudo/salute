<?php
/**
 * prescriptions/add.php — Nuova impegnativa
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();
$membro = membroSelezionato();
if (!$membro) { header('Location: '.APP_URL.'/index.php'); exit; }

$errore = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fileNome = $fileMime = $fileDati = null;
    if (!empty($_FILES['file_allegato']['name'])) {
        $file = $_FILES['file_allegato'];
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, unserialize(ALLOWED_MIME))) { $errore = 'Tipo file non consentito.'; }
        elseif ($file['size'] > MAX_UPLOAD_BYTE) { $errore = 'File troppo grande.'; }
        else { $fileNome=$file['name']; $fileMime=$mime; $fileDati=file_get_contents($file['tmp_name']); }
    }
    if (!$errore) {
        db()->prepare(
            'INSERT INTO impegnative (membro_id,codice_nre,tipo_priorita,medico_prescrivente,prestazione,
             data_prescrizione,data_scadenza,stato,note,file_nome,file_mime,file_dati)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $membro['id'],
            trim($_POST['codice_nre']          ?? '') ?: null,
            $_POST['tipo_priorita']            ?? 'D',
            trim($_POST['medico_prescrivente'] ?? '') ?: null,
            trim($_POST['prestazione']         ?? '') ?: null,
            trim($_POST['data_prescrizione']   ?? '') ?: null,
            trim($_POST['data_scadenza']       ?? '') ?: null,
            $_POST['stato']                    ?? 'da_utilizzare',
            trim($_POST['note']                ?? '') ?: null,
            $fileNome, $fileMime, $fileDati,
        ]);
        setFlash('success','Impegnativa aggiunta.');
        header('Location: index.php'); exit;
    }
}
$pageTitle = 'Nuova Impegnativa';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-file-medical-fill me-2"></i>Nuova Impegnativa</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Indietro</a>
</div>
<div class="row justify-content-center"><div class="col-lg-8">
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-file-medical"></i> Dati Impegnativa</div>
  <div class="win-card-body">
    <?php if ($errore): ?><div class="alert alert-danger py-2"><?= h($errore) ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Codice NRE</label>
          <input type="text" name="codice_nre" class="form-control" value="<?= h($_POST['codice_nre']??'') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Priorità</label>
          <select name="tipo_priorita" class="form-select">
            <option value="U">U — Urgente</option>
            <option value="B">B — Breve</option>
            <option value="P">P — Programmabile</option>
            <option value="D" selected>D — Differibile</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Stato</label>
          <select name="stato" class="form-select">
            <option value="da_utilizzare">Da Utilizzare</option>
            <option value="utilizzata">Utilizzata</option>
            <option value="scaduta">Scaduta</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Medico Prescrivente</label>
          <input type="text" name="medico_prescrivente" class="form-control" value="<?= h($_POST['medico_prescrivente']??'') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Prestazione</label>
          <input type="text" name="prestazione" class="form-control" value="<?= h($_POST['prestazione']??'') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data Prescrizione</label>
          <input type="date" name="data_prescrizione" class="form-control" value="<?= h($_POST['data_prescrizione']??date('Y-m-d')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data Scadenza</label>
          <input type="date" name="data_scadenza" class="form-control" value="<?= h($_POST['data_scadenza']??'') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Allega File</label>
          <input type="file" name="file_allegato" id="file_allegato" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
          <div class="form-text" id="file_nome_preview"></div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Note</label>
          <textarea name="note" class="form-control" rows="2"><?= h($_POST['note']??'') ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salva</button>
        <a href="index.php" class="btn btn-outline-secondary">Annulla</a>
      </div>
    </form>
  </div>
</div>
</div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
