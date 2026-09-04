<?php
/**
 * medications/index.php
 * Elenco farmaci del membro selezionato + dashboard orari giornalieri.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }
$mid = $membro['id'];

// ── Elenco farmaci con orari ─────────────────────────────────
$farmaci = db()->prepare(
    'SELECT f.*,
            GROUP_CONCAT(fo.ora ORDER BY fo.ora SEPARATOR ", ") AS orari
     FROM farmaci f
     LEFT JOIN farmaci_orari fo ON fo.farmaco_id = f.id
     WHERE f.membro_id = ?
     GROUP BY f.id
     ORDER BY f.attivo DESC, f.nome_farmaco ASC'
);
// Nota: la query include già f.* → scontrino_file_nome e scontrino_file_mime sono disponibili
$farmaci->execute([$mid]);
$farmaci = $farmaci->fetchAll();

// ── Planning giornaliero (fascia oraria) ─────────────────────
$planning = db()->prepare(
    'SELECT fo.ora, fo.quantita, fo.note AS nota_orario,
            f.id AS farmaco_id, f.nome_farmaco, f.dosaggio, f.note_assunzione
     FROM farmaci f
     JOIN farmaci_orari fo ON fo.farmaco_id = f.id
     WHERE f.membro_id=? AND f.attivo=1
       AND (f.cronico=1 OR f.data_fine IS NULL OR f.data_fine >= CURDATE())
       AND (f.data_inizio IS NULL OR f.data_inizio <= CURDATE())
     ORDER BY fo.ora ASC, f.nome_farmaco ASC'
);
$planning->execute([$mid]);
$planningRows = $planning->fetchAll();

// Raggruppa per ora
$perOra = [];
foreach ($planningRows as $r) {
    $ora = substr($r['ora'], 0, 5);
    $perOra[$ora][] = $r;
}

$pageTitle = 'Medicinali';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-capsule me-2"></i>Medicinali — <?= h($membro['nome'].' '.$membro['cognome']) ?></h1>
  <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Aggiungi Farmaco</a>
</div>

<!-- ── Tabs ─────────────────────────────────────────────────── -->
<ul class="nav nav-tabs mb-3" id="medTabs">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabElenco">
    <i class="bi bi-list-ul me-1"></i>Elenco Farmaci</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabPlanning">
    <i class="bi bi-clock me-1"></i>Planning Giornaliero</a></li>
</ul>

<div class="tab-content">
  <!-- ── Tab Elenco ──────────────────────────────────────────── -->
  <div class="tab-pane fade show active" id="tabElenco">
    <div class="win-card">
      <div class="win-card-body p-0">
        <table class="table table-hover mb-0 dt-table" id="tblFarmaci">
          <thead>
            <tr>
              <th>Farmaco</th>
              <th>Principio Attivo</th>
              <th>Dosaggio</th>
              <th>Orari</th>
              <th>Inizio</th>
              <th>Fine / Cronica</th>
              <th>Stato</th>
              <th>Scontrino</th>
              <th>Azioni</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($farmaci as $f): ?>
            <tr>
              <td class="fw-semibold"><?= h($f['nome_farmaco']) ?></td>
              <td><?= h($f['principio_attivo'] ?? '—') ?></td>
              <td><?= h($f['dosaggio'] ?? '—') ?></td>
              <td>
                <?php if ($f['orari']): ?>
                <?php foreach (explode(', ', $f['orari']) as $o): ?>
                <span class="schedule-hour-badge me-1"><?= substr($o,0,5) ?></span>
                <?php endforeach; ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td><?= dataITA($f['data_inizio']) ?></td>
              <td>
                <?php if ($f['cronico']): ?>
                <span class="badge bg-info">Cronica</span>
                <?php else: ?>
                <?= dataITA($f['data_fine']) ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($f['attivo']): ?>
                <span class="badge bg-success">Attivo</span>
                <?php else: ?>
                <span class="badge bg-secondary">Sospeso</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($f['scontrino_file_nome'])): ?>
                <div class="d-flex gap-1">
                  <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=farmaco&id=<?= $f['id'] ?>"
                     target="_blank" class="btn btn-sm btn-outline-info" title="Visualizza scontrino">
                    <i class="bi bi-eye"></i>
                  </a>
                  <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=farmaco&id=<?= $f['id'] ?>&mode=download"
                     class="btn btn-sm btn-outline-secondary" title="Scarica scontrino">
                    <i class="bi bi-download"></i>
                  </a>
                </div>
                <?php else: ?>
                <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <a href="edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifica">
                    <i class="bi bi-pencil"></i></a>
                  <a href="delete.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-danger btn-confirm-delete" title="Elimina">
                    <i class="bi bi-trash"></i></a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── Tab Planning ────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tabPlanning">
    <div class="win-card">
      <div class="win-card-header"><i class="bi bi-clock-history"></i> Somministrazioni Oggi — <?= date('d/m/Y') ?></div>
      <div class="win-card-body">
        <?php if (empty($perOra)): ?>
        <p class="text-muted text-center py-3">Nessun farmaco attivo con orario impostato.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead>
              <tr>
                <th style="width:100px">Orario</th>
                <th>Farmaco</th>
                <th>Dosaggio</th>
                <th>Quantità</th>
                <th>Note Assunzione</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($perOra as $ora => $righe): ?>
              <?php foreach ($righe as $i => $r): ?>
              <tr>
                <?php if ($i === 0): ?>
                <td rowspan="<?= count($righe) ?>" class="text-center align-middle bg-light">
                  <span class="schedule-hour-badge"><?= h($ora) ?></span>
                </td>
                <?php endif; ?>
                <td class="fw-semibold"><?= h($r['nome_farmaco']) ?></td>
                <td><?= h($r['dosaggio'] ?? '—') ?></td>
                <td><?= h($r['quantita']) ?></td>
                <td><?= h($r['note_assunzione'] ?? $r['nota_orario'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
