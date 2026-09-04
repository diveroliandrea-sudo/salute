<?php
/**
 * appointments/add.php — Nuovo appuntamento
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();
$membro = membroSelezionato();
if (!$membro) { header('Location: '.APP_URL.'/index.php'); exit; }

$errore = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $titolo  = trim($_POST['titolo']   ?? '');
    $dataOra = trim($_POST['data_ora'] ?? '');
    if (!$titolo || !$dataOra) { $errore = 'Titolo e Data/Ora sono obbligatori.'; }
    else {
        db()->prepare(
            'INSERT INTO appuntamenti (membro_id,titolo,tipo,data_ora,luogo,medico,struttura,stato,note,promemoria)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $membro['id'], $titolo,
            trim($_POST['tipo']     ?? '') ?: null,
            $dataOra,
            trim($_POST['luogo']    ?? '') ?: null,
            trim($_POST['medico']   ?? '') ?: null,
            trim($_POST['struttura']?? '') ?: null,
            $_POST['stato'] ?? 'programmato',
            trim($_POST['note']     ?? '') ?: null,
            isset($_POST['promemoria']) ? 1 : 0,
        ]);
        setFlash('success', 'Appuntamento aggiunto.');
        header('Location: index.php'); exit;
    }
}
$pageTitle = 'Nuovo Appuntamento';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-calendar-plus me-2"></i>Nuovo Appuntamento</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Indietro</a>
</div>
<div class="row justify-content-center"><div class="col-lg-8">
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-calendar-check"></i> Dati Appuntamento</div>
  <div class="win-card-body">
    <?php if ($errore): ?><div class="alert alert-danger py-2"><?= h($errore) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label fw-semibold">Titolo *</label>
          <input type="text" name="titolo" class="form-control" required value="<?= h($_POST['titolo']??'') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Tipo</label>
          <select name="tipo" class="form-select">
            <option value="">— Seleziona —</option>
            <?php foreach (['Visita','Analisi','Controllo','Vaccinazione','Intervento','Altro'] as $t): ?>
            <option value="<?= $t ?>" <?= (($_POST['tipo']??'')===$t)?'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data e Ora *</label>
          <input type="datetime-local" name="data_ora" class="form-control" required value="<?= h($_POST['data_ora']??'') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Stato</label>
          <select name="stato" class="form-select">
            <?php foreach (['programmato','completato','annullato','rinviato'] as $s): ?>
            <option value="<?= $s ?>" <?= (($_POST['stato']??'programmato')===$s)?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Luogo</label>
          <input type="text" name="luogo" class="form-control" value="<?= h($_POST['luogo']??'') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Medico</label>
          <input type="text" name="medico" class="form-control" value="<?= h($_POST['medico']??'') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Struttura</label>
          <input type="text" name="struttura" class="form-control" value="<?= h($_POST['struttura']??'') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Note</label>
          <textarea name="note" class="form-control" rows="3"><?= h($_POST['note']??'') ?></textarea>
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
