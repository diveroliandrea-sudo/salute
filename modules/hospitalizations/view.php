<?php
/**
 * hospitalizations/view.php
 * Dettaglio ricovero con lettera di dimissione.
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

$pageTitle = 'Dettaglio Ricovero';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-hospital me-2"></i>Dettaglio Ricovero</h1>
  <div class="d-flex gap-2">
    <a href="edit.php?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>Modifica</a>
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Elenco</a>
  </div>
</div>

<div class="row g-4">
  <!-- Dati ricovero -->
  <div class="col-md-6">
    <div class="win-card h-100">
      <div class="win-card-header"><i class="bi bi-building-fill-check"></i> Dati Ospedalieri</div>
      <div class="win-card-body">
        <dl class="row mb-0">
          <dt class="col-sm-5">Struttura</dt>       <dd class="col-sm-7"><?= h($r['struttura']) ?></dd>
          <dt class="col-sm-5">Reparto</dt>         <dd class="col-sm-7"><?= h($r['reparto'] ?? '—') ?></dd>
          <dt class="col-sm-5">Data Ingresso</dt>   <dd class="col-sm-7"><?= dataITA($r['data_ingresso']) ?></dd>
          <dt class="col-sm-5">Data Dimissione</dt> <dd class="col-sm-7"><?= $r['data_dimissione'] ? dataITA($r['data_dimissione']) : '<span class="badge bg-warning">In corso</span>' ?></dd>
          <dt class="col-sm-5">Medico</dt>          <dd class="col-sm-7"><?= h($r['medico_responsabile'] ?? '—') ?></dd>
        </dl>
        <?php if ($r['diagnosi_ingresso']): ?>
        <hr>
        <h6 class="small fw-semibold text-muted">Diagnosi di Ingresso</h6>
        <p class="mb-0"><?= nl2br(h($r['diagnosi_ingresso'])) ?></p>
        <?php endif; ?>
        <?php if ($r['motivo_ricovero']): ?>
        <hr>
        <h6 class="small fw-semibold text-muted">Motivo del Ricovero</h6>
        <p class="mb-0"><?= nl2br(h($r['motivo_ricovero'])) ?></p>
        <?php endif; ?>
        <?php if ($r['note']): ?>
        <hr>
        <h6 class="small fw-semibold text-muted">Note</h6>
        <p class="mb-0"><?= nl2br(h($r['note'])) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Lettera di dimissione -->
  <div class="col-md-6">
    <div class="win-card h-100">
      <div class="win-card-header"><i class="bi bi-file-earmark-text"></i> Lettera di Dimissione</div>
      <div class="win-card-body">
        <?php if ($r['diagnosi_dimissione']): ?>
        <h6 class="small fw-semibold text-muted">Diagnosi alla Dimissione</h6>
        <p><?= nl2br(h($r['diagnosi_dimissione'])) ?></p>
        <?php endif; ?>
        <?php if ($r['terapia_dimissione']): ?>
        <h6 class="small fw-semibold text-muted">Terapia Consigliata</h6>
        <p><?= nl2br(h($r['terapia_dimissione'])) ?></p>
        <?php endif; ?>
        <?php if ($r['lettera_file_nome']): ?>
        <hr>
        <h6 class="small fw-semibold text-muted">File Allegato</h6>
        <div class="d-flex gap-2 align-items-center">
          <i class="bi bi-paperclip text-muted"></i>
          <span><?= h($r['lettera_file_nome']) ?></span>
          <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=ricovero&id=<?= $r['id'] ?>&campo=lettera"
             class="btn btn-sm btn-outline-info ms-auto" target="_blank">
            <i class="bi bi-eye me-1"></i>Visualizza
          </a>
          <a href="<?= APP_URL ?>/modules/documents/download.php?modulo=ricovero&id=<?= $r['id'] ?>&campo=lettera"
             class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i>Scarica
          </a>
        </div>
        <?php else: ?>
        <p class="text-muted small">Nessuna lettera di dimissione allegata.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
