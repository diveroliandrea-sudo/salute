<?php
/**
 * index.php
 * Dashboard principale — selezione membro e riepilogo dati.
 */
require_once __DIR__ . '/includes/config.php';
requireLogin();

// ── Gestione selezione membro dalla navbar ──────────────────
if (isset($_GET['set_membro'])) {
    $idM = (int)$_GET['set_membro'];
    // Verifica che il membro appartenga all'utente loggato
    $check = db()->prepare('SELECT id FROM membri_famiglia WHERE id=? AND utente_id=?');
    $check->execute([$idM, $_SESSION['utente_id']]);
    if ($check->fetch()) {
        $_SESSION['membro_id'] = $idM;
    }
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$membro = membroSelezionato();

// ── Statistiche rapide per il membro selezionato ────────────
$stats = [];
if ($membro) {
    $mid = $membro['id'];
    $q = [
        'appuntamenti_futuri' => 'SELECT COUNT(*) FROM appuntamenti WHERE membro_id=? AND data_ora >= NOW() AND stato="programmato"',
        'farmaci_attivi'      => 'SELECT COUNT(*) FROM farmaci WHERE membro_id=? AND attivo=1',
        'ricoveri'            => 'SELECT COUNT(*) FROM ricoveri WHERE membro_id=?',
        'visite'              => 'SELECT COUNT(*) FROM visite_specialistiche WHERE membro_id=?',
    ];
    foreach ($q as $k => $sql) {
        $s = db()->prepare($sql);
        $s->execute([$mid]);
        $stats[$k] = (int)$s->fetchColumn();
    }

    // Ultimi 5 appuntamenti futuri
    $prossimi = db()->prepare(
        'SELECT * FROM appuntamenti WHERE membro_id=? AND data_ora >= NOW() AND stato="programmato"
         ORDER BY data_ora ASC LIMIT 5'
    );
    $prossimi->execute([$mid]);
    $prossimi = $prossimi->fetchAll();

    // Farmaci di oggi (orari di oggi)
    $farmaciOggi = db()->prepare(
        'SELECT f.nome_farmaco, f.dosaggio, fo.ora, fo.quantita
         FROM farmaci f
         JOIN farmaci_orari fo ON fo.farmaco_id = f.id
         WHERE f.membro_id=? AND f.attivo=1
           AND (f.cronico=1 OR (f.data_fine IS NULL OR f.data_fine >= CURDATE()))
           AND (f.data_inizio IS NULL OR f.data_inizio <= CURDATE())
         ORDER BY fo.ora ASC'
    );
    $farmaciOggi->execute([$mid]);
    $farmaciOggi = $farmaciOggi->fetchAll();

    // Ultimo parametro vitale
    $ultimoVitale = db()->prepare(
        'SELECT * FROM parametri_vitali WHERE membro_id=? ORDER BY data_ora DESC LIMIT 1'
    );
    $ultimoVitale->execute([$mid]);
    $ultimoVitale = $ultimoVitale->fetch();
}

// ── Tutti i membri dell'utente ──────────────────────────────
$tutti = db()->prepare('SELECT * FROM membri_famiglia WHERE utente_id=? ORDER BY nome');
$tutti->execute([$_SESSION['utente_id']]);
$tutti = $tutti->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-titlebar">
  <h1><i class="bi bi-house-fill me-2"></i>Dashboard</h1>
  <a href="<?= APP_URL ?>/modules/members/add.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i> Aggiungi Membro
  </a>
</div>

<!-- ── Selezione membro ────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <?php foreach ($tutti as $m): ?>
  <div class="col-6 col-md-3 col-lg-2">
    <a href="<?= APP_URL ?>/index.php?set_membro=<?= $m['id'] ?>" class="text-decoration-none">
      <div class="member-card <?= ($membro && $membro['id'] == $m['id']) ? 'selected' : '' ?>">
        <div class="avatar-circle">
          <?= mb_strtoupper(mb_substr($m['nome'], 0, 1) . mb_substr($m['cognome'], 0, 1)) ?>
        </div>
        <div class="fw-semibold small"><?= h($m['nome']) ?></div>
        <div class="text-muted" style="font-size:.75rem"><?= h($m['cognome']) ?></div>
        <?php if ($m['relazione']): ?>
        <span class="badge bg-secondary mt-1" style="font-size:.7rem"><?= h($m['relazione']) ?></span>
        <?php endif; ?>
      </div>
    </a>
  </div>
  <?php endforeach; ?>

  <?php if (empty($tutti)): ?>
  <div class="col-12">
    <div class="alert alert-info">
      <i class="bi bi-info-circle me-2"></i>
      Nessun membro registrato. <a href="<?= APP_URL ?>/modules/members/add.php">Aggiungi il primo membro della famiglia</a>.
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($membro): ?>
<!-- ── Intestazione membro selezionato ────────────────────── -->
<div class="win-card mb-4">
  <div class="win-card-header">
    <i class="bi bi-person-fill"></i>
    <?= h($membro['nome'] . ' ' . $membro['cognome']) ?>
    <?php if ($membro['relazione']): ?>
    <span class="badge bg-light text-dark ms-2"><?= h($membro['relazione']) ?></span>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/modules/members/edit.php?id=<?= $membro['id'] ?>"
       class="btn btn-sm btn-light ms-auto">
      <i class="bi bi-pencil"></i> Modifica
    </a>
  </div>
  <div class="win-card-body">
    <div class="row g-3">
      <?php if ($membro['data_nascita']): ?>
      <div class="col-auto">
        <small class="text-muted d-block">Data di nascita</small>
        <strong><?= dataITA($membro['data_nascita']) ?></strong>
      </div>
      <?php endif; ?>
      <?php if ($membro['codice_fiscale']): ?>
      <div class="col-auto">
        <small class="text-muted d-block">Codice Fiscale</small>
        <strong><?= h($membro['codice_fiscale']) ?></strong>
      </div>
      <?php endif; ?>
      <?php if ($membro['medico_base']): ?>
      <div class="col-auto">
        <small class="text-muted d-block">Medico di base</small>
        <strong><?= h($membro['medico_base']) ?></strong>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Statistiche rapide ──────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <a href="<?= APP_URL ?>/modules/appointments/" class="text-decoration-none">
      <div class="win-card h-100 text-center p-3">
        <i class="bi bi-calendar-check display-6 text-primary"></i>
        <div class="fs-3 fw-bold text-primary"><?= $stats['appuntamenti_futuri'] ?></div>
        <div class="small text-muted">Appuntamenti futuri</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="<?= APP_URL ?>/modules/medications/" class="text-decoration-none">
      <div class="win-card h-100 text-center p-3">
        <i class="bi bi-capsule display-6 text-success"></i>
        <div class="fs-3 fw-bold text-success"><?= $stats['farmaci_attivi'] ?></div>
        <div class="small text-muted">Farmaci attivi</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="<?= APP_URL ?>/modules/hospitalizations/" class="text-decoration-none">
      <div class="win-card h-100 text-center p-3">
        <i class="bi bi-hospital display-6 text-warning"></i>
        <div class="fs-3 fw-bold text-warning"><?= $stats['ricoveri'] ?></div>
        <div class="small text-muted">Ricoveri</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="<?= APP_URL ?>/modules/visits/" class="text-decoration-none">
      <div class="win-card h-100 text-center p-3">
        <i class="bi bi-stethoscope display-6 text-info"></i>
        <div class="fs-3 fw-bold text-info"><?= $stats['visite'] ?></div>
        <div class="small text-muted">Visite / Analisi</div>
      </div>
    </a>
  </div>
</div>

<div class="row g-4">
  <!-- ── Prossimi appuntamenti ──────────────────────────────── -->
  <div class="col-md-6">
    <div class="win-card h-100">
      <div class="win-card-header"><i class="bi bi-calendar-event"></i> Prossimi Appuntamenti</div>
      <div class="win-card-body p-0">
        <?php if ($prossimi): ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($prossimi as $ap): ?>
          <li class="list-group-item d-flex justify-content-between align-items-start py-2">
            <div>
              <div class="fw-semibold small"><?= h($ap['titolo']) ?></div>
              <div class="text-muted" style="font-size:.78rem">
                <i class="bi bi-clock me-1"></i><?= dataOraITA($ap['data_ora']) ?>
                <?php if ($ap['luogo']): ?> &mdash; <?= h($ap['luogo']) ?><?php endif; ?>
              </div>
            </div>
            <span class="badge bg-primary rounded-pill"><?= h($ap['tipo'] ?? 'Visita') ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-muted text-center py-3 small">Nessun appuntamento programmato.</p>
        <?php endif; ?>
        <div class="p-2 border-top">
          <a href="<?= APP_URL ?>/modules/appointments/" class="btn btn-sm btn-outline-primary w-100">
            Vedi tutti gli appuntamenti
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Farmaci di oggi ────────────────────────────────────── -->
  <div class="col-md-6">
    <div class="win-card h-100">
      <div class="win-card-header"><i class="bi bi-capsule"></i> Farmaci di Oggi</div>
      <div class="win-card-body p-0">
        <?php if ($farmaciOggi): ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($farmaciOggi as $f): ?>
          <li class="list-group-item d-flex align-items-center gap-2 py-2">
            <span class="schedule-hour-badge"><?= substr($f['ora'], 0, 5) ?></span>
            <div class="small">
              <span class="fw-semibold"><?= h($f['nome_farmaco']) ?></span>
              <?php if ($f['dosaggio']): ?>
              <span class="text-muted"> — <?= h($f['dosaggio']) ?></span>
              <?php endif; ?>
              <span class="text-muted ms-1">(<?= h($f['quantita']) ?>)</span>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-muted text-center py-3 small">Nessun farmaco in terapia.</p>
        <?php endif; ?>
        <div class="p-2 border-top">
          <a href="<?= APP_URL ?>/modules/medications/" class="btn btn-sm btn-outline-success w-100">
            Gestisci medicinali
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Ultimi parametri vitali ───────────────────────────── -->
  <?php if ($ultimoVitale): ?>
  <div class="col-12">
    <div class="win-card">
      <div class="win-card-header"><i class="bi bi-activity"></i> Ultimi Parametri Vitali — <?= dataOraITA($ultimoVitale['data_ora']) ?></div>
      <div class="win-card-body">
        <div class="row g-3">
          <?php if ($ultimoVitale['peso_kg'] && $ultimoVitale['bmi']): ?>
          <div class="col-auto">
            <small class="text-muted d-block">Peso / BMI</small>
            <strong><?= $ultimoVitale['peso_kg'] ?> kg</strong>
            <?php $bc = classificaBMI((float)$ultimoVitale['bmi']); ?>
            <span class="badge bg-<?= $bc['class'] ?> ms-1"><?= $ultimoVitale['bmi'] ?> — <?= $bc['label'] ?></span>
          </div>
          <?php endif; ?>
          <?php if ($ultimoVitale['pressione_sistolica']): ?>
          <div class="col-auto">
            <small class="text-muted d-block">Pressione</small>
            <?php $pr = classificaPressione((int)$ultimoVitale['pressione_sistolica'], (int)$ultimoVitale['pressione_diastolica']); ?>
            <strong><?= $ultimoVitale['pressione_sistolica'] ?>/<?= $ultimoVitale['pressione_diastolica'] ?> mmHg</strong>
            <span class="badge bg-<?= $pr['class'] ?> ms-1"><?= $pr['label'] ?></span>
          </div>
          <?php endif; ?>
          <?php if ($ultimoVitale['temperatura']): ?>
          <div class="col-auto">
            <small class="text-muted d-block">Temperatura</small>
            <strong><?= $ultimoVitale['temperatura'] ?> °C</strong>
          </div>
          <?php endif; ?>
          <?php if ($ultimoVitale['saturazione_o2']): ?>
          <div class="col-auto">
            <small class="text-muted d-block">SpO₂</small>
            <strong><?= $ultimoVitale['saturazione_o2'] ?> %</strong>
          </div>
          <?php endif; ?>
        </div>
        <div class="mt-2">
          <a href="<?= APP_URL ?>/modules/vitals/" class="btn btn-sm btn-outline-secondary">
            Storico parametri vitali
          </a>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div><!-- /.row -->

<?php else: ?>
<div class="alert alert-warning">
  <i class="bi bi-arrow-up-circle me-2"></i>
  Seleziona un membro dalla lista sopra per visualizzare i dati.
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
