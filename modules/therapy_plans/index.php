<?php
/**
 * therapy_plans/index.php
 * Elenco piani terapeutici del membro selezionato.
 * Mostra stato, date, scadenza e badge colorati.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) {
    setFlash('warning', 'Seleziona prima un membro.');
    header('Location: ' . APP_URL . '/index.php');
    exit;
}
$mid = $membro['id'];

// ── Eliminazione rapida ──────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $s = db()->prepare('SELECT id FROM piani_terapeutici WHERE id = ? AND membro_id = ?');
    $s->execute([$did, $mid]);
    if ($s->fetch()) {
        db()->prepare('DELETE FROM piani_terapeutici WHERE id = ?')->execute([$did]);
        setFlash('success', 'Piano terapeutico eliminato.');
    }
    header('Location: index.php');
    exit;
}

// ── Elenco piani ─────────────────────────────────────────────
$piani = db()->prepare(
    'SELECT id, titolo, tipo, medico_redattore, struttura,
            data_inizio, data_fine, stato, file_nome, created_at
     FROM piani_terapeutici
     WHERE membro_id = ?
     ORDER BY data_inizio DESC'
);
$piani->execute([$mid]);
$piani = $piani->fetchAll();

// Helper: calcola quanti giorni mancano alla scadenza.
// Valore positivo = giorni rimanenti (futuro), negativo = scaduto da N giorni.
function giorniAllaScadenza(?string $dataFine): ?int {
    if (!$dataFine) return null;
    $fine  = new DateTime($dataFine);
    $oggi  = new DateTime('today');
    $diff  = $oggi->diff($fine);          // diff(today → fine)
    // $diff->invert = 1 se fine < oggi (scaduto), 0 se fine >= oggi (futuro)
    return $diff->invert ? -$diff->days : $diff->days;
}

$pageTitle = 'Piani Terapeutici';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-journal-medical me-2"></i>Piani Terapeutici
    — <?= h($membro['nome'] . ' ' . $membro['cognome']) ?></h1>
  <a href="add.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-circle me-1"></i>Nuovo Piano
  </a>
</div>

<?php if (empty($piani)): ?>
<div class="alert alert-info">
  <i class="bi bi-info-circle me-2"></i>
  Nessun piano terapeutico registrato.
  <a href="add.php">Aggiungine uno</a>.
</div>
<?php else: ?>

<!-- ── Riepilogo badge contatori ────────────────────────────── -->
<?php
$contatori = ['attivo' => 0, 'completato' => 0, 'sospeso' => 0, 'revocato' => 0];
foreach ($piani as $p) $contatori[$p['stato']] = ($contatori[$p['stato']] ?? 0) + 1;
$map = ['attivo' => ['success','Attivi'], 'completato' => ['secondary','Completati'],
        'sospeso' => ['warning','Sospesi'], 'revocato' => ['danger','Revocati']];
?>
<div class="row g-2 mb-3">
  <?php foreach ($map as $k => [$cls, $lbl]): ?>
  <div class="col-6 col-sm-3">
    <div class="win-card text-center py-2">
      <div class="fs-4 fw-bold text-<?= $cls ?>"><?= $contatori[$k] ?></div>
      <div class="small text-muted"><?= $lbl ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Tabella piani ─────────────────────────────────────────── -->
<div class="win-card">
  <div class="win-card-body p-0">
    <table class="table table-hover align-middle mb-0 dt-table" id="tblPiani">
      <thead>
        <tr>
          <th>Titolo / Tipo</th>
          <th>Medico</th>
          <th>Struttura</th>
          <th>Data Inizio</th>
          <th>Data Fine</th>
          <th>Scadenza</th>
          <th>Stato</th>
          <th>File</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($piani as $p):
          // data_fine può arrivare dal DB come null, stringa vuota o stringa data
          $dataFine = ($p['data_fine'] !== null && $p['data_fine'] !== '') ? $p['data_fine'] : null;
          $gg       = giorniAllaScadenza($dataFine);

          // Badge scadenza
          if ($dataFine === null) {
              $scadCls = 'info';    $scadLbl = 'Indefinito';
          } elseif ($gg < 0) {
              $scadCls = 'danger';  $scadLbl = 'Scaduto ' . abs($gg) . ' gg fa';
          } elseif ($gg === 0) {
              $scadCls = 'warning'; $scadLbl = 'Scade oggi';
          } elseif ($gg <= 30) {
              $scadCls = 'warning'; $scadLbl = $gg . ' gg rimasti';
          } else {
              $scadCls = 'success'; $scadLbl = $gg . ' gg rimasti';
          }
          $statoCls = ['attivo'=>'success','completato'=>'secondary',
                       'sospeso'=>'warning','revocato'=>'danger'][$p['stato']] ?? 'secondary';
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= h($p['titolo']) ?></div>
            <?php if ($p['tipo']): ?>
            <small class="text-muted"><?= h($p['tipo']) ?></small>
            <?php endif; ?>
          </td>
          <td><?= h($p['medico_redattore'] ?? '—') ?></td>
          <td><?= h($p['struttura'] ?? '—') ?></td>
          <td class="text-nowrap"><?= dataITA($p['data_inizio']) ?></td>
          <td class="text-nowrap">
            <?= $dataFine ? dataITA($dataFine) : '<span class="text-muted fst-italic">Indefinita</span>' ?>
          </td>
          <td>
            <span class="badge bg-<?= $scadCls ?>"><?= $scadLbl ?></span>
          </td>
          <td>
            <span class="badge bg-<?= $statoCls ?>"><?= ucfirst($p['stato']) ?></span>
          </td>
          <td class="text-center">
            <?php if ($p['file_nome']): ?>
            <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=piano_terapeutico&id=<?= $p['id'] ?>"
               target="_blank" class="btn btn-sm btn-outline-info"
               data-bs-toggle="tooltip" title="<?= h($p['file_nome']) ?>">
              <i class="bi bi-file-earmark-text"></i>
            </a>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="d-flex gap-1">
              <a href="view.php?id=<?= $p['id'] ?>"
                 class="btn btn-sm btn-outline-secondary" title="Dettaglio">
                <i class="bi bi-eye"></i></a>
              <a href="edit.php?id=<?= $p['id'] ?>"
                 class="btn btn-sm btn-outline-primary" title="Modifica">
                <i class="bi bi-pencil"></i></a>
              <a href="?delete=<?= $p['id'] ?>"
                 class="btn btn-sm btn-outline-danger btn-confirm-delete" title="Elimina">
                <i class="bi bi-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
