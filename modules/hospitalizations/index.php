<?php
/**
 * hospitalizations/index.php
 * Elenco ricoveri e lettere di dimissione.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }

$ricoveri = db()->prepare(
    'SELECT id, struttura, reparto, data_ingresso, data_dimissione,
            diagnosi_ingresso, diagnosi_dimissione, medico_responsabile,
            lettera_file_nome, created_at
     FROM ricoveri WHERE membro_id=? ORDER BY data_ingresso DESC'
);
$ricoveri->execute([$membro['id']]);
$ricoveri = $ricoveri->fetchAll();

$pageTitle = 'Ricoveri & Dimissioni';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-hospital me-2"></i>Ricoveri & Lettere di Dimissione — <?= h($membro['nome'].' '.$membro['cognome']) ?></h1>
  <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Nuovo Ricovero</a>
</div>

<div class="win-card">
  <div class="win-card-body p-0">
    <table class="table table-hover mb-0 dt-table" id="tblRicoveri">
      <thead>
        <tr>
          <th>Struttura / Reparto</th>
          <th>Data Ingresso</th>
          <th>Data Dimissione</th>
          <th>Diagnosi Ingresso</th>
          <th>Diagnosi Dimissione</th>
          <th>Medico</th>
          <th>Lettera</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ricoveri as $r): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= h($r['struttura']) ?></div>
            <?php if ($r['reparto']): ?><small class="text-muted"><?= h($r['reparto']) ?></small><?php endif; ?>
          </td>
          <td><?= dataITA($r['data_ingresso']) ?></td>
          <td><?= $r['data_dimissione'] ? dataITA($r['data_dimissione']) : '<span class="badge bg-warning">In corso</span>' ?></td>
          <td><small><?= h(mb_substr($r['diagnosi_ingresso'] ?? '—', 0, 60)) ?><?= strlen($r['diagnosi_ingresso']??'')>60 ? '…' : '' ?></small></td>
          <td><small><?= h(mb_substr($r['diagnosi_dimissione'] ?? '—', 0, 60)) ?><?= strlen($r['diagnosi_dimissione']??'')>60 ? '…' : '' ?></small></td>
          <td><?= h($r['medico_responsabile'] ?? '—') ?></td>
          <td class="text-center">
            <?php if ($r['lettera_file_nome']): ?>
            <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=ricovero&id=<?= $r['id'] ?>&campo=lettera"
               class="btn btn-sm btn-outline-info" target="_blank" title="Visualizza lettera">
              <i class="bi bi-file-earmark-text"></i>
            </a>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="view.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Dettaglio">
                <i class="bi bi-eye"></i></a>
              <a href="edit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifica">
                <i class="bi bi-pencil"></i></a>
              <a href="delete.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger btn-confirm-delete" title="Elimina">
                <i class="bi bi-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
