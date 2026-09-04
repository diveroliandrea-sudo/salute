<?php
/**
 * medical_record/index.php
 * Cartella Clinica — vista storica completa del paziente.
 * Raggruppa visite, analisi, ricoveri per anno/tipo.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }
$mid = $membro['id'];

// ── Visite e analisi ─────────────────────────────────────────
$visite = db()->prepare(
    'SELECT id, tipo, specialita, data_visita, medico, struttura, diagnosi, esito, file_nome
     FROM visite_specialistiche WHERE membro_id=? ORDER BY data_visita DESC'
);
$visite->execute([$mid]);
$visite = $visite->fetchAll();

// ── Ricoveri ─────────────────────────────────────────────────
$ricoveri = db()->prepare(
    'SELECT id, struttura, reparto, data_ingresso, data_dimissione,
            diagnosi_ingresso, diagnosi_dimissione
     FROM ricoveri WHERE membro_id=? ORDER BY data_ingresso DESC'
);
$ricoveri->execute([$mid]);
$ricoveri = $ricoveri->fetchAll();

// ── Impegnative ──────────────────────────────────────────────
$impegnative = db()->prepare(
    'SELECT id, prestazione, data_prescrizione, medico_prescrivente, tipo_priorita, stato
     FROM impegnative WHERE membro_id=? ORDER BY data_prescrizione DESC'
);
$impegnative->execute([$mid]);
$impegnative = $impegnative->fetchAll();

// ── Farmaci storici ───────────────────────────────────────────
$farmaci = db()->prepare(
    'SELECT id, nome_farmaco, principio_attivo, dosaggio, data_inizio, data_fine, cronico, attivo
     FROM farmaci WHERE membro_id=? ORDER BY data_inizio DESC'
);
$farmaci->execute([$mid]);
$farmaci = $farmaci->fetchAll();

// ── Invalidità ────────────────────────────────────────────────
$invalidita = db()->prepare(
    'SELECT id, tipo, percentuale, legge_104_grado, data_verbale, data_scadenza
     FROM invalidita WHERE membro_id=? ORDER BY data_verbale DESC'
);
$invalidita->execute([$mid]);
$invalidita = $invalidita->fetchAll();

// ── Piani Terapeutici ─────────────────────────────────────────
$pianiTer = db()->prepare(
    'SELECT id, titolo, tipo, medico_redattore, data_inizio, data_fine, stato, file_nome
     FROM piani_terapeutici WHERE membro_id=? ORDER BY data_inizio DESC'
);
$pianiTer->execute([$mid]);
$pianiTer = $pianiTer->fetchAll();

$pageTitle = 'Cartella Clinica';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-folder2-open me-2"></i>Cartella Clinica — <?= h($membro['nome'].' '.$membro['cognome']) ?></h1>
  <small class="text-muted">Vista storica completa</small>
</div>

<!-- Dati anagrafici membro -->
<div class="win-card mb-4">
  <div class="win-card-header"><i class="bi bi-person-lines-fill"></i> Dati Paziente</div>
  <div class="win-card-body">
    <div class="row g-3">
      <div class="col-auto">
        <small class="text-muted d-block">Nome</small>
        <strong><?= h($membro['nome'].' '.$membro['cognome']) ?></strong>
      </div>
      <?php if ($membro['data_nascita']): ?>
      <div class="col-auto">
        <small class="text-muted d-block">Data di nascita</small>
        <strong><?= dataITA($membro['data_nascita']) ?></strong>
      </div>
      <?php endif; ?>
      <?php if ($membro['sesso']): ?>
      <div class="col-auto">
        <small class="text-muted d-block">Sesso</small>
        <strong><?= h($membro['sesso']) ?></strong>
      </div>
      <?php endif; ?>
      <?php if ($membro['codice_fiscale']): ?>
      <div class="col-auto">
        <small class="text-muted d-block">Cod. Fiscale</small>
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

<!-- Tabs sezioni -->
<ul class="nav nav-tabs mb-3" id="ccTabs">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabVisite">
    <i class="bi bi-stethoscope me-1"></i>Visite & Analisi <span class="badge bg-secondary"><?= count($visite) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabRicoveri">
    <i class="bi bi-hospital me-1"></i>Ricoveri <span class="badge bg-secondary"><?= count($ricoveri) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabFarmaci">
    <i class="bi bi-capsule me-1"></i>Terapie <span class="badge bg-secondary"><?= count($farmaci) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabImpegnative">
    <i class="bi bi-file-medical me-1"></i>Impegnative <span class="badge bg-secondary"><?= count($impegnative) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabPiani">
    <i class="bi bi-journal-medical me-1"></i>Piani Terapeutici <span class="badge bg-secondary"><?= count($pianiTer) ?></span></a></li>
  <?php if ($invalidita): ?>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabInvalidita">
    <i class="bi bi-shield-check me-1"></i>Invalidità</a></li>
  <?php endif; ?>
</ul>

<div class="tab-content">
  <!-- Visite -->
  <div class="tab-pane fade show active" id="tabVisite">
    <div class="win-card"><div class="win-card-body p-0">
      <table class="table table-hover mb-0 dt-table">
        <thead><tr><th>Data</th><th>Tipo</th><th>Specialità</th><th>Medico</th><th>Struttura</th><th>Diagnosi</th><th>Referto</th></tr></thead>
        <tbody>
          <?php foreach ($visite as $v): ?>
          <tr>
            <td><?= dataITA($v['data_visita']) ?></td>
            <td><span class="badge bg-<?= $v['tipo']==='specialistica'?'primary':($v['tipo']==='analisi'?'info':'warning') ?>">
              <?= h(ucfirst($v['tipo'])) ?></span></td>
            <td><?= h($v['specialita'] ?? '—') ?></td>
            <td><?= h($v['medico'] ?? '—') ?></td>
            <td><?= h($v['struttura'] ?? '—') ?></td>
            <td><small><?= h(mb_substr($v['diagnosi'] ?? '—', 0, 60)) ?></small></td>
            <td>
              <?php if ($v['file_nome']): ?>
              <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=visita&id=<?= $v['id'] ?>"
                 target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>

  <!-- Ricoveri -->
  <div class="tab-pane fade" id="tabRicoveri">
    <div class="win-card"><div class="win-card-body p-0">
      <table class="table table-hover mb-0 dt-table">
        <thead><tr><th>Struttura</th><th>Reparto</th><th>Ingresso</th><th>Dimissione</th><th>Diagnosi Ingresso</th><th>Diagnosi Dimissione</th><th>Azioni</th></tr></thead>
        <tbody>
          <?php foreach ($ricoveri as $r): ?>
          <tr>
            <td class="fw-semibold"><?= h($r['struttura']) ?></td>
            <td><?= h($r['reparto'] ?? '—') ?></td>
            <td><?= dataITA($r['data_ingresso']) ?></td>
            <td><?= $r['data_dimissione'] ? dataITA($r['data_dimissione']) : '<span class="badge bg-warning">In corso</span>' ?></td>
            <td><small><?= h(mb_substr($r['diagnosi_ingresso']??'—',0,50)) ?></small></td>
            <td><small><?= h(mb_substr($r['diagnosi_dimissione']??'—',0,50)) ?></small></td>
            <td><a href="<?= APP_URL ?>/modules/hospitalizations/view.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>

  <!-- Farmaci -->
  <div class="tab-pane fade" id="tabFarmaci">
    <div class="win-card"><div class="win-card-body p-0">
      <table class="table table-hover mb-0 dt-table">
        <thead><tr><th>Farmaco</th><th>Principio Attivo</th><th>Dosaggio</th><th>Inizio</th><th>Fine</th><th>Stato</th></tr></thead>
        <tbody>
          <?php foreach ($farmaci as $f): ?>
          <tr>
            <td class="fw-semibold"><?= h($f['nome_farmaco']) ?></td>
            <td><?= h($f['principio_attivo'] ?? '—') ?></td>
            <td><?= h($f['dosaggio'] ?? '—') ?></td>
            <td><?= dataITA($f['data_inizio']) ?></td>
            <td><?= $f['cronico'] ? '<span class="badge bg-info">Cronica</span>' : dataITA($f['data_fine']) ?></td>
            <td><?= $f['attivo'] ? '<span class="badge bg-success">Attivo</span>' : '<span class="badge bg-secondary">Sospeso</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>

  <!-- Impegnative -->
  <div class="tab-pane fade" id="tabImpegnative">
    <div class="win-card"><div class="win-card-body p-0">
      <table class="table table-hover mb-0 dt-table">
        <thead><tr><th>Data</th><th>Prestazione</th><th>Medico</th><th>Priorità</th><th>Stato</th></tr></thead>
        <tbody>
          <?php foreach ($impegnative as $i): ?>
          <tr>
            <td><?= dataITA($i['data_prescrizione']) ?></td>
            <td><?= h($i['prestazione'] ?? '—') ?></td>
            <td><?= h($i['medico_prescrivente'] ?? '—') ?></td>
            <td><span class="badge bg-primary"><?= h($i['tipo_priorita']) ?></span></td>
            <td><span class="badge badge-<?= $i['stato'] ?> bg-<?= $i['stato']==='utilizzata'?'success':($i['stato']==='scaduta'?'danger':'primary') ?>">
              <?= h(ucfirst(str_replace('_',' ',$i['stato']))) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>

  <!-- Piani Terapeutici -->
  <div class="tab-pane fade" id="tabPiani">
    <div class="win-card"><div class="win-card-body p-0">
      <table class="table table-hover mb-0 dt-table">
        <thead>
          <tr>
            <th>Titolo</th><th>Tipo</th><th>Medico</th>
            <th>Inizio</th><th>Fine</th><th>Stato</th><th>File</th><th>Azioni</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pianiTer as $pt):
            $sCls = ['attivo'=>'success','completato'=>'secondary',
                     'sospeso'=>'warning','revocato'=>'danger'][$pt['stato']] ?? 'secondary';
          ?>
          <tr>
            <td class="fw-semibold"><?= h($pt['titolo']) ?></td>
            <td><?= h($pt['tipo'] ?? '—') ?></td>
            <td><?= h($pt['medico_redattore'] ?? '—') ?></td>
            <td><?= dataITA($pt['data_inizio']) ?></td>
            <td>
              <?= $pt['data_fine']
                  ? dataITA($pt['data_fine'])
                  : '<span class="badge bg-info"><i class="bi bi-infinity me-1"></i>Indefinita</span>' ?>
            </td>
            <td><span class="badge bg-<?= $sCls ?>"><?= ucfirst($pt['stato']) ?></span></td>
            <td class="text-center">
              <?php if ($pt['file_nome']): ?>
              <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=piano_terapeutico&id=<?= $pt['id'] ?>"
                 target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <a href="<?= APP_URL ?>/modules/therapy_plans/view.php?id=<?= $pt['id'] ?>"
                 class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($pianiTer)): ?>
          <tr><td colspan="8" class="text-center text-muted py-3">Nessun piano terapeutico registrato.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div></div>
  </div>

  <!-- Invalidità -->
  <?php if ($invalidita): ?>
  <div class="tab-pane fade" id="tabInvalidita">
    <div class="win-card"><div class="win-card-body">
      <?php foreach ($invalidita as $inv): ?>
      <div class="vital-card mb-2">
        <div class="d-flex justify-content-between">
          <strong><?= h($inv['tipo']) ?></strong>
          <?php if ($inv['percentuale']): ?>
          <span class="badge bg-primary"><?= $inv['percentuale'] ?>%</span>
          <?php endif; ?>
          <?php if ($inv['legge_104_grado']): ?>
          <span class="badge bg-warning text-dark">Legge 104 - Grado <?= $inv['legge_104_grado'] ?></span>
          <?php endif; ?>
        </div>
        <small class="text-muted">Verbale: <?= dataITA($inv['data_verbale']) ?> — Scadenza: <?= dataITA($inv['data_scadenza']) ?></small>
      </div>
      <?php endforeach; ?>
    </div></div>
  </div>
  <?php endif; ?>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
