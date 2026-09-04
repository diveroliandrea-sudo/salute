<?php
/**
 * appointments/index.php — Gestione Appuntamenti CRUD
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();
$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }
$mid = $membro['id'];

// Gestione eliminazione rapida
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $s = db()->prepare('SELECT id FROM appuntamenti WHERE id=? AND membro_id=?');
    $s->execute([$did, $mid]);
    if ($s->fetch()) { db()->prepare('DELETE FROM appuntamenti WHERE id=?')->execute([$did]); setFlash('success','Appuntamento eliminato.'); }
    header('Location: index.php'); exit;
}

$appuntamenti = db()->prepare(
    'SELECT * FROM appuntamenti WHERE membro_id=? ORDER BY data_ora DESC'
);
$appuntamenti->execute([$mid]);
$appuntamenti = $appuntamenti->fetchAll();

$pageTitle = 'Appuntamenti';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-calendar-check me-2"></i>Appuntamenti — <?= h($membro['nome'].' '.$membro['cognome']) ?></h1>
  <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Nuovo Appuntamento</a>
</div>
<div class="win-card">
  <div class="win-card-body p-0">
    <table class="table table-hover mb-0 dt-table">
      <thead>
        <tr><th>Data / Ora</th><th>Titolo</th><th>Tipo</th><th>Medico / Struttura</th><th>Stato</th><th>Note</th><th>Azioni</th></tr>
      </thead>
      <tbody>
      <?php foreach ($appuntamenti as $a): ?>
        <?php
          // Link Google Calendar per questo appuntamento
          $gcDetails  = implode("\n", array_filter([
              $a['tipo']     ? 'Tipo: '.$a['tipo']         : null,
              $a['medico']   ? 'Medico: '.$a['medico']     : null,
              $a['struttura']? 'Struttura: '.$a['struttura']: null,
              $a['note']     ? 'Note: '.$a['note']          : null,
          ]));
          $gcLocation = trim(implode(', ', array_filter([$a['luogo'] ?? null, $a['struttura'] ?? null]))) ?: null;
          $gcUrl = googleCalendarUrl(
              $a['titolo'],
              $a['data_ora'],   // formato Y-m-d H:i:s → con orario
              null,
              $gcDetails ?: null,
              $gcLocation
          );
        ?>
        <tr>
          <td class="text-nowrap"><?= dataOraITA($a['data_ora']) ?></td>
          <td class="fw-semibold"><?= h($a['titolo']) ?></td>
          <td><?= h($a['tipo'] ?? '—') ?></td>
          <td>
            <?php if ($a['medico']): ?><div class="small"><i class="bi bi-person-badge me-1 text-muted"></i><?= h($a['medico']) ?></div><?php endif; ?>
            <?php if ($a['struttura']): ?><div class="small"><i class="bi bi-building me-1 text-muted"></i><?= h($a['struttura']) ?></div><?php endif; ?>
          </td>
          <td>
            <?php
            $bg = ['programmato'=>'primary','completato'=>'success','annullato'=>'danger','rinviato'=>'warning'];
            $b  = $bg[$a['stato']] ?? 'secondary';
            ?>
            <span class="badge bg-<?= $b ?>"><?= h(ucfirst($a['stato'])) ?></span>
          </td>
          <td><small><?= h(mb_substr($a['note']??'',0,50)) ?></small></td>
          <td>
            <div class="d-flex gap-1">
              <a href="edit.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifica"><i class="bi bi-pencil"></i></a>
              <a href="<?= h($gcUrl) ?>" target="_blank" rel="noopener"
                 class="btn btn-sm btn-outline-success" title="Aggiungi a Google Calendar">
                <i class="bi bi-calendar-plus"></i>
              </a>
              <a href="?delete=<?= $a['id'] ?>" class="btn btn-sm btn-outline-danger btn-confirm-delete" title="Elimina"><i class="bi bi-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
