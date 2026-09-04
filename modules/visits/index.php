<?php
/**
 * visits/index.php — Visite Specialistiche, Analisi e Analisi Strumentali CRUD
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();
$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }
$mid = $membro['id'];

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $s = db()->prepare('SELECT id FROM visite_specialistiche WHERE id=? AND membro_id=?');
    $s->execute([$did,$mid]);
    if ($s->fetch()) { db()->prepare('DELETE FROM visite_specialistiche WHERE id=?')->execute([$did]); setFlash('success','Visita eliminata.'); }
    header('Location: index.php'); exit;
}

// Filtro tipo
$tipoFiltro = $_GET['tipo'] ?? '';
if ($tipoFiltro && in_array($tipoFiltro,['specialistica','analisi','strumentale'])) {
    $visite = db()->prepare('SELECT * FROM visite_specialistiche WHERE membro_id=? AND tipo=? ORDER BY data_visita DESC');
    $visite->execute([$mid, $tipoFiltro]);
} else {
    $visite = db()->prepare('SELECT * FROM visite_specialistiche WHERE membro_id=? ORDER BY data_visita DESC');
    $visite->execute([$mid]);
}
$visite = $visite->fetchAll();

$pageTitle = 'Visite & Analisi';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-stethoscope me-2"></i>Visite & Analisi — <?= h($membro['nome'].' '.$membro['cognome']) ?></h1>
  <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Nuova Registrazione</a>
</div>

<!-- Filtro tipo -->
<div class="mb-3">
  <div class="btn-group btn-group-sm">
    <a href="index.php" class="btn btn-<?= !$tipoFiltro?'primary':'outline-primary' ?>">Tutti</a>
    <a href="?tipo=specialistica" class="btn btn-<?= $tipoFiltro==='specialistica'?'primary':'outline-primary' ?>">Specialistiche</a>
    <a href="?tipo=analisi" class="btn btn-<?= $tipoFiltro==='analisi'?'info':'outline-info' ?>">Analisi</a>
    <a href="?tipo=strumentale" class="btn btn-<?= $tipoFiltro==='strumentale'?'warning':'outline-warning' ?>">Strumentali</a>
  </div>
</div>

<div class="win-card"><div class="win-card-body p-0">
  <table class="table table-hover mb-0 dt-table">
    <thead>
      <tr><th>Data</th><th>Tipo</th><th>Specialità</th><th>Medico</th><th>Struttura</th><th>Diagnosi / Esito</th><th>File</th><th>Azioni</th></tr>
    </thead>
    <tbody>
      <?php foreach ($visite as $v): ?>
      <?php
        // Costruisce il link Google Calendar per questa visita
        $gcTitle    = trim(ucfirst($v['tipo']) . ($v['specialita'] ? ' — '.$v['specialita'] : ''));
        $gcDetails  = implode("\n", array_filter([
            $v['medico']   ? 'Medico: '.$v['medico']       : null,
            $v['diagnosi'] ? 'Diagnosi: '.$v['diagnosi']    : null,
            $v['esito']    ? 'Esito: '.$v['esito']          : null,
            $v['note']     ? 'Note: '.$v['note']            : null,
        ]));
        $gcLocation = $v['struttura'] ?? null;
        $gcUrl      = googleCalendarUrl($gcTitle, $v['data_visita'], null, $gcDetails ?: null, $gcLocation);
      ?>
      <tr>
        <td class="text-nowrap"><?= dataITA($v['data_visita']) ?></td>
        <td>
          <?php $tc=['specialistica'=>'primary','analisi'=>'info','strumentale'=>'warning']; ?>
          <span class="badge bg-<?= $tc[$v['tipo']]??'secondary' ?>"><?= h(ucfirst($v['tipo'])) ?></span>
        </td>
        <td><?= h($v['specialita']??'—') ?></td>
        <td><?= h($v['medico']??'—') ?></td>
        <td><?= h($v['struttura']??'—') ?></td>
        <td><small><?= h(mb_substr($v['diagnosi']??$v['esito']??'—',0,60)) ?></small></td>
        <td class="text-center">
          <?php if ($v['file_nome']): ?>
          <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=visita&id=<?= $v['id'] ?>"
             target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <div class="d-flex gap-1">
            <a href="edit.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifica"><i class="bi bi-pencil"></i></a>
            <a href="<?= h($gcUrl) ?>" target="_blank" rel="noopener"
               class="btn btn-sm btn-outline-success" title="Aggiungi a Google Calendar">
              <i class="bi bi-calendar-plus"></i>
            </a>
            <a href="?delete=<?= $v['id'] ?>" class="btn btn-sm btn-outline-danger btn-confirm-delete" title="Elimina"><i class="bi bi-trash"></i></a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
