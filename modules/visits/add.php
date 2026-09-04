<?php
/**
 * visits/add.php — Nuova visita / analisi
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();
$membro = membroSelezionato();
if (!$membro) { header('Location: '.APP_URL.'/index.php'); exit; }

$errore = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $dataV = trim($_POST['data_visita']??'');
    if (!$dataV) { $errore = 'La data della visita è obbligatoria.'; }
    else {
        $fileNome=$fileMime=$fileDati=null;
        if (!empty($_FILES['file_allegato']['name'])) {
            $file=$_FILES['file_allegato'];
            $mime=mime_content_type($file['tmp_name']);
            if (!in_array($mime,unserialize(ALLOWED_MIME))) { $errore='Tipo file non consentito.'; }
            elseif ($file['size']>MAX_UPLOAD_BYTE) { $errore='File troppo grande.'; }
            else { $fileNome=$file['name'];$fileMime=$mime;$fileDati=file_get_contents($file['tmp_name']); }
        }
        if (!$errore) {
            db()->prepare(
                'INSERT INTO visite_specialistiche
                 (membro_id,tipo,specialita,data_visita,medico,struttura,diagnosi,esito,terapia_consigliata,note,file_nome,file_mime,file_dati)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $membro['id'],
                $_POST['tipo']??'specialistica',
                trim($_POST['specialita']??'')?: null,
                $dataV,
                trim($_POST['medico']??'')?: null,
                trim($_POST['struttura']??'')?: null,
                trim($_POST['diagnosi']??'')?: null,
                trim($_POST['esito']??'')?: null,
                trim($_POST['terapia_consigliata']??'')?: null,
                trim($_POST['note']??'')?: null,
                $fileNome,$fileMime,$fileDati,
            ]);
            setFlash('success','Registrazione aggiunta.');
            header('Location: index.php'); exit;
        }
    }
}
$pageTitle = 'Nuova Visita / Analisi';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-stethoscope me-2"></i>Nuova Registrazione</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Indietro</a>
</div>
<div class="row justify-content-center"><div class="col-lg-9">
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-stethoscope"></i> Dati Visita / Analisi</div>
  <div class="win-card-body">
    <?php if ($errore): ?><div class="alert alert-danger py-2"><?= h($errore) ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Tipo *</label>
          <select name="tipo" class="form-select" required>
            <?php foreach (['specialistica'=>'Visita Specialistica','analisi'=>'Analisi','strumentale'=>'Analisi Strumentale'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= (($_POST['tipo']??'')===$v)?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data *</label>
          <input type="date" name="data_visita" class="form-control" required value="<?= h($_POST['data_visita']??date('Y-m-d')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Specialità</label>
          <input type="text" name="specialita" class="form-control" value="<?= h($_POST['specialita']??'') ?>" placeholder="es. Cardiologia">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Medico</label>
          <input type="text" name="medico" class="form-control" value="<?= h($_POST['medico']??'') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Struttura</label>
          <input type="text" name="struttura" class="form-control" value="<?= h($_POST['struttura']??'') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Diagnosi</label>
          <textarea name="diagnosi" class="form-control" rows="3"><?= h($_POST['diagnosi']??'') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Esito</label>
          <textarea name="esito" class="form-control" rows="3"><?= h($_POST['esito']??'') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Terapia Consigliata</label>
          <textarea name="terapia_consigliata" class="form-control" rows="2"><?= h($_POST['terapia_consigliata']??'') ?></textarea>
        </div>
        <div class="col-md-8">
          <label class="form-label fw-semibold">Note</label>
          <textarea name="note" class="form-control" rows="2"><?= h($_POST['note']??'') ?></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Allega Referto <small class="text-muted">(PDF/img)</small></label>
          <input type="file" name="file_allegato" id="file_allegato" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
          <div class="form-text" id="file_nome_preview"></div>
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
