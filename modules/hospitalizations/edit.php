<?php
/**
 * hospitalizations/edit.php
 * Modifica ricovero e lettera di dimissione.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) { header('Location: '.APP_URL.'/index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM ricoveri WHERE id=? AND membro_id=?');
$stmt->execute([$id, $membro['id']]);
$r = $stmt->fetch();
if (!$r) { setFlash('danger','Ricovero non trovato.'); header('Location: index.php'); exit; }

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $struttura = trim($_POST['struttura']    ?? '');
    $dataIng   = trim($_POST['data_ingresso']?? '');
    if (!$struttura || !$dataIng) { $errore = 'Struttura e Data Ingresso obbligatorie.'; }
    else {
        $letNome = $r['lettera_file_nome'];
        $letMime = $r['lettera_file_mime'];
        $letDati = $r['lettera_file_dati'];

        if (!empty($_FILES['lettera']['name'])) {
            $file = $_FILES['lettera'];
            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, unserialize(ALLOWED_MIME))) { $errore = 'Tipo file non consentito.'; }
            elseif ($file['size'] > MAX_UPLOAD_BYTE) { $errore = 'File troppo grande.'; }
            else {
                $letNome = $file['name'];
                $letMime = $mime;
                $letDati = file_get_contents($file['tmp_name']);
            }
        }

        if (!$errore) {
            db()->prepare(
                'UPDATE ricoveri SET struttura=?, reparto=?, data_ingresso=?, data_dimissione=?,
                 diagnosi_ingresso=?, motivo_ricovero=?, diagnosi_dimissione=?, terapia_dimissione=?,
                 medico_responsabile=?, note=?,
                 lettera_file_nome=?, lettera_file_mime=?, lettera_file_dati=?,
                 updated_at=NOW()
                 WHERE id=?'
            )->execute([
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
                $id,
            ]);
            setFlash('success', 'Ricovero aggiornato.');
            header('Location: view.php?id='.$id); exit;
        }
    }
}

$pageTitle = 'Modifica Ricovero';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-hospital me-2"></i>Modifica Ricovero</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Indietro</a>
</div>
<div class="row justify-content-center">
<div class="col-lg-10">
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-hospital"></i> <?= h($r['struttura']) ?></div>
  <div class="win-card-body">
    <?php if ($errore): ?><div class="alert alert-danger py-2"><?= h($errore) ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Struttura *</label>
          <input type="text" name="struttura" class="form-control" required value="<?= h($r['struttura']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Reparto</label>
          <input type="text" name="reparto" class="form-control" value="<?= h($r['reparto'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data Ingresso *</label>
          <input type="date" name="data_ingresso" class="form-control" required value="<?= h($r['data_ingresso']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data Dimissione</label>
          <input type="date" name="data_dimissione" class="form-control" value="<?= h($r['data_dimissione'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Medico Responsabile</label>
          <input type="text" name="medico_responsabile" class="form-control" value="<?= h($r['medico_responsabile'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Diagnosi Ingresso</label>
          <textarea name="diagnosi_ingresso" class="form-control" rows="3"><?= h($r['diagnosi_ingresso'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Motivo Ricovero</label>
          <textarea name="motivo_ricovero" class="form-control" rows="3"><?= h($r['motivo_ricovero'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Diagnosi Dimissione</label>
          <textarea name="diagnosi_dimissione" class="form-control" rows="3"><?= h($r['diagnosi_dimissione'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Terapia Consigliata</label>
          <textarea name="terapia_dimissione" class="form-control" rows="3"><?= h($r['terapia_dimissione'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Lettera di Dimissione</label>
          <?php if ($r['lettera_file_nome']): ?>
          <div class="mb-2 d-flex align-items-center gap-2">
            <i class="bi bi-paperclip text-muted"></i>
            <span class="small"><?= h($r['lettera_file_nome']) ?></span>
            <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=ricovero&id=<?= $r['id'] ?>&campo=lettera"
               target="_blank" class="btn btn-xs btn-outline-info btn-sm">Visualizza</a>
          </div>
          <?php endif; ?>
          <input type="file" name="lettera" id="file_allegato" class="form-control"
                 accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
          <div class="form-text" id="file_nome_preview"><?= $r['lettera_file_nome'] ? 'Carica un nuovo file per sostituire quello esistente.' : '' ?></div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Note</label>
          <textarea name="note" class="form-control" rows="2"><?= h($r['note'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salva</button>
        <a href="index.php" class="btn btn-outline-secondary">Annulla</a>
      </div>
    </form>
  </div>
</div>
</div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
