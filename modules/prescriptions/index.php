<?php
/**
 * prescriptions/index.php — Gestione Impegnative (Ricette) CRUD
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();
$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }
$mid = $membro['id'];

if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $s = db()->prepare('SELECT id FROM impegnative WHERE id=? AND membro_id=?');
    $s->execute([$did, $mid]);
    if ($s->fetch()) { db()->prepare('DELETE FROM impegnative WHERE id=?')->execute([$did]); setFlash('success','Impegnativa eliminata.'); }
    header('Location: index.php'); exit;
}

$items = db()->prepare('SELECT * FROM impegnative WHERE membro_id=? ORDER BY data_prescrizione DESC');
$items->execute([$mid]);
$items = $items->fetchAll();

$pageTitle = 'Impegnative';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-file-medical me-2"></i>Impegnative — <?= h($membro['nome'].' '.$membro['cognome']) ?></h1>
  <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Nuova Impegnativa</a>
</div>
<div class="win-card"><div class="win-card-body p-0">
  <table class="table table-hover mb-0 dt-table">
    <thead>
      <tr><th>Data</th><th>Codice NRE</th><th>Prestazione</th><th>Medico</th><th>Priorità</th><th>Scadenza</th><th>Stato</th><th>File</th><th>Azioni</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i): ?>
      <tr>
        <td><?= dataITA($i['data_prescrizione']) ?></td>
        <td><code><?= h($i['codice_nre'] ?? '—') ?></code></td>
        <td><?= h($i['prestazione'] ?? '—') ?></td>
        <td><?= h($i['medico_prescrivente'] ?? '—') ?></td>
        <td>
          <?php $pr=['U'=>'danger','B'=>'warning','P'=>'info','D'=>'secondary']; ?>
          <span class="badge bg-<?= $pr[$i['tipo_priorita']] ?? 'secondary' ?>"><?= h($i['tipo_priorita']) ?></span>
        </td>
        <td><?= dataITA($i['data_scadenza']) ?></td>
        <td>
          <?php $sc=['da_utilizzare'=>'primary','utilizzata'=>'success','scaduta'=>'danger']; ?>
          <span class="badge bg-<?= $sc[$i['stato']] ?? 'secondary' ?>"><?= h(str_replace('_',' ',ucfirst($i['stato']))) ?></span>
        </td>
        <td class="text-center">
          <?php if ($i['file_nome']): ?>
          <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=impegnativa&id=<?= $i['id'] ?>"
             target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <div class="d-flex gap-1">
            <a href="edit.php?id=<?= $i['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
            <a href="?delete=<?= $i['id'] ?>" class="btn btn-sm btn-outline-danger btn-confirm-delete"><i class="bi bi-trash"></i></a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
