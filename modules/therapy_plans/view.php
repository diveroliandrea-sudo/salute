<?php
/**
 * therapy_plans/view.php
 * Dettaglio completo di un piano terapeutico con viewer documento integrato.
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

// Helper colori stato
$statoCls = [
    'attivo'     => 'success',
    'completato' => 'secondary',
    'sospeso'    => 'warning',
    'revocato'   => 'danger',
][$piano['stato']] ?? 'secondary';

// Calcola giorni rimanenti (positivo = futuro, negativo = scaduto)
$dataFine  = ($piano['data_fine'] !== null && $piano['data_fine'] !== '') ? $piano['data_fine'] : null;
$giorniRim = null;
if ($dataFine !== null) {
    $fine  = new DateTime($dataFine);
    $oggi  = new DateTime('today');
    $diff  = $oggi->diff($fine);   // diff(oggi → fine): invert=1 se fine < oggi
    $giorniRim = $diff->invert ? -$diff->days : $diff->days;
}

$pageTitle = 'Piano Terapeutico — ' . $piano['titolo'];
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-journal-medical me-2"></i><?= h($piano['titolo']) ?></h1>
  <div class="d-flex gap-2 flex-wrap">
    <a href="edit.php?id=<?= $piano['id'] ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-pencil me-1"></i>Modifica
    </a>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Elenco
    </a>
  </div>
</div>

<div class="row g-4">

  <!-- ── Colonna sinistra: dati piano ─────────────────────── -->
  <div class="col-lg-7">

    <!-- Intestazione con badge stato -->
    <div class="win-card mb-4">
      <div class="win-card-header">
        <i class="bi bi-info-circle"></i> Dati Generali
        <span class="badge bg-<?= $statoCls ?> ms-auto"><?= ucfirst($piano['stato']) ?></span>
      </div>
      <div class="win-card-body">
        <dl class="row mb-0">
          <?php if ($piano['tipo']): ?>
          <dt class="col-sm-4 text-muted">Tipo</dt>
          <dd class="col-sm-8"><?= h($piano['tipo']) ?></dd>
          <?php endif; ?>
          <?php if ($piano['medico_redattore']): ?>
          <dt class="col-sm-4 text-muted">Medico Redattore</dt>
          <dd class="col-sm-8"><?= h($piano['medico_redattore']) ?></dd>
          <?php endif; ?>
          <?php if ($piano['struttura']): ?>
          <dt class="col-sm-4 text-muted">Struttura</dt>
          <dd class="col-sm-8"><?= h($piano['struttura']) ?></dd>
          <?php endif; ?>
          <dt class="col-sm-4 text-muted">Data Inizio</dt>
          <dd class="col-sm-8 fw-semibold"><?= dataITA($piano['data_inizio']) ?></dd>
          <dt class="col-sm-4 text-muted">Data Fine</dt>
          <dd class="col-sm-8">
            <?php if ($dataFine === null): ?>
            <span class="badge bg-info"><i class="bi bi-infinity me-1"></i>Durata Indefinita</span>
            <?php else: ?>
            <span class="fw-semibold"><?= dataITA($dataFine) ?></span>
            <?php if ($giorniRim !== null): ?>
            <span class="badge ms-2 bg-<?= $giorniRim < 0 ? 'danger' : ($giorniRim <= 30 ? 'warning' : 'success') ?>">
              <?php if ($giorniRim < 0): ?>
                Scaduto <?= abs($giorniRim) ?> gg fa
              <?php elseif ($giorniRim === 0): ?>
                Scade oggi
              <?php else: ?>
                <?= $giorniRim ?> gg rimasti
              <?php endif; ?>
            </span>
            <?php endif; ?>
            <?php endif; ?>
          </dd>
          <dt class="col-sm-4 text-muted">Registrato il</dt>
          <dd class="col-sm-8"><?= dataOraITA($piano['created_at']) ?></dd>
        </dl>
      </div>
    </div>

    <!-- Contenuto testuale -->
    <?php if ($piano['obiettivo']): ?>
    <div class="win-card mb-3">
      <div class="win-card-header"><i class="bi bi-bullseye"></i> Obiettivo Terapeutico</div>
      <div class="win-card-body">
        <p class="mb-0"><?= nl2br(h($piano['obiettivo'])) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($piano['descrizione']): ?>
    <div class="win-card mb-3">
      <div class="win-card-header"><i class="bi bi-file-text"></i> Descrizione / Indicazioni</div>
      <div class="win-card-body">
        <p class="mb-0" style="white-space:pre-line"><?= h($piano['descrizione']) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($piano['farmaci_previsti']): ?>
    <div class="win-card mb-3">
      <div class="win-card-header"><i class="bi bi-capsule"></i> Farmaci Previsti</div>
      <div class="win-card-body">
        <pre class="mb-0" style="font-size:.875rem;white-space:pre-wrap"><?= h($piano['farmaci_previsti']) ?></pre>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($piano['note']): ?>
    <div class="win-card mb-3">
      <div class="win-card-header"><i class="bi bi-sticky"></i> Note</div>
      <div class="win-card-body">
        <p class="mb-0"><?= nl2br(h($piano['note'])) ?></p>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /.col-lg-7 -->

  <!-- ── Colonna destra: documento allegato ─────────────── -->
  <div class="col-lg-5">
    <div class="win-card h-100">
      <div class="win-card-header">
        <i class="bi bi-paperclip"></i> Documento Allegato
      </div>
      <div class="win-card-body">
        <?php if ($piano['file_nome']): ?>

        <!-- Info file -->
        <div class="d-flex align-items-center gap-2 mb-3 p-2 bg-light rounded border">
          <i class="bi bi-<?= str_contains($piano['file_mime'] ?? '', 'pdf')
              ? 'file-earmark-pdf text-danger'
              : 'file-earmark-image text-info' ?> fs-4"></i>
          <div class="flex-grow-1 overflow-hidden">
            <div class="fw-semibold small text-truncate"><?= h($piano['file_nome']) ?></div>
            <small class="text-muted"><?= h($piano['file_mime']) ?></small>
          </div>
        </div>

        <!-- Pulsanti azione -->
        <div class="d-flex gap-2 mb-3">
          <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=piano_terapeutico&id=<?= $piano['id'] ?>"
             target="_blank" class="btn btn-outline-info btn-sm flex-grow-1">
            <i class="bi bi-eye me-1"></i>Apri in nuova scheda
          </a>
          <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=piano_terapeutico&id=<?= $piano['id'] ?>&mode=download"
             class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Scarica
          </a>
        </div>

        <!-- Anteprima inline -->
        <?php
        // Costruiamo l'anteprima solo se il file è in DB (già verificato sopra)
        // Per evitare di caricare il BLOB due volte, usiamo un iframe che chiama view.php
        $viewUrl = APP_URL . '/modules/documents/view.php?modulo=piano_terapeutico&id=' . $piano['id'];
        $isPdf   = str_contains($piano['file_mime'] ?? '', 'pdf');
        $isImg   = str_starts_with($piano['file_mime'] ?? '', 'image/');
        ?>
        <?php if ($isPdf): ?>
        <iframe src="<?= $viewUrl ?>&embed=1"
                class="doc-viewer-frame"
                style="height:60vh"
                title="Piano Terapeutico PDF"></iframe>
        <?php elseif ($isImg): ?>
        <div class="text-center mt-2">
          <img src="<?= $viewUrl ?>&embed=1"
               class="img-fluid rounded border"
               style="max-height:55vh;cursor:zoom-in"
               id="imgPreview"
               alt="<?= h($piano['file_nome']) ?>">
        </div>
        <script>
        document.getElementById('imgPreview').addEventListener('click', function(){
          window.open('<?= $viewUrl ?>', '_blank');
        });
        </script>
        <?php endif; ?>

        <?php else: ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-file-earmark-x" style="font-size:3rem;opacity:.3"></i>
          <p class="mt-2 small">Nessun documento allegato.</p>
          <a href="edit.php?id=<?= $piano['id'] ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-paperclip me-1"></i>Allega documento
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div><!-- /.col-lg-5 -->

</div><!-- /.row -->

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
