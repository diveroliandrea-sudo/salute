<?php
/**
 * diet/index.php
 * Elenco misurazioni peso/BMI del membro selezionato.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }
$mid = $membro['id'];

// ── Recupera altezza dal membro (se disponibile) ─────────────
$altezzaDefault = $membro['altezza_cm'] ?? null;

// ── Elenco misurazioni ───────────────────────────────────────
$stmt = db()->prepare(
    'SELECT * FROM peso_diario WHERE membro_id = ? ORDER BY data DESC, ora DESC'
);
$stmt->execute([$mid]);
$misurazioni = $stmt->fetchAll();

// ── Statistiche ──────────────────────────────────────────────
$stats = db()->prepare(
    'SELECT COUNT(*) AS totale,
            MIN(peso_kg) AS peso_min,
            MAX(peso_kg) AS peso_max,
            AVG(peso_kg) AS peso_medio,
            AVG(bmi)     AS bmi_medio
     FROM peso_diario WHERE membro_id = ?'
);
$stats->execute([$mid]);
$stats = $stats->fetch();

// Ultima misurazione
$ultima = $misurazioni[0] ?? null;

$pageTitle = 'Dieta & Peso';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-speedometer2 me-2"></i>Dieta & Peso — <?= h($membro['nome'].' '.$membro['cognome']) ?></h1>
  <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Aggiungi Misurazione</a>
</div>

<!-- ── Schede riepilogative ─────────────────────────────────── -->
<?php if ($stats['totale'] > 0): ?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="win-card h-100">
      <div class="win-card-body text-center">
        <div class="fs-1 fw-bold text-primary"><?= number_format($ultima['peso_kg'], 1) ?> kg</div>
        <div class="text-muted small">Ultimo peso rilevato</div>
        <div class="text-muted small"><?= dataITA($ultima['data']) ?> <?= substr($ultima['ora'],0,5) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <?php
      $bmiUltimo = $ultima['bmi'];
      $classeBMI = classificaBMI((float)$bmiUltimo);
    ?>
    <div class="win-card h-100">
      <div class="win-card-body text-center">
        <div class="fs-1 fw-bold text-<?= $classeBMI['class'] ?>"><?= number_format($bmiUltimo, 1) ?></div>
        <div class="text-muted small">BMI attuale</div>
        <span class="badge bg-<?= $classeBMI['class'] ?>"><?= $classeBMI['label'] ?></span>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="win-card h-100">
      <div class="win-card-body text-center">
        <div class="fw-bold text-success"><?= number_format($stats['peso_min'], 1) ?> kg</div>
        <div class="text-muted small">Peso minimo</div>
        <div class="fw-bold text-danger mt-2"><?= number_format($stats['peso_max'], 1) ?> kg</div>
        <div class="text-muted small">Peso massimo</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="win-card h-100">
      <div class="win-card-body text-center">
        <div class="fw-bold"><?= number_format($stats['peso_medio'], 1) ?> kg</div>
        <div class="text-muted small">Peso medio</div>
        <div class="fw-bold mt-2"><?= number_format($stats['bmi_medio'], 1) ?></div>
        <div class="text-muted small">BMI medio</div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Tabella misurazioni ──────────────────────────────────── -->
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-table me-1"></i> Storico Misurazioni</div>
  <div class="win-card-body p-0">
    <?php if (empty($misurazioni)): ?>
    <p class="text-muted text-center py-4">Nessuna misurazione registrata. <a href="add.php">Aggiungi la prima</a>.</p>
    <?php else: ?>
    <table class="table table-hover mb-0 dt-table" id="tblPeso">
      <thead>
        <tr>
          <th>Data</th>
          <th>Ora</th>
          <th>Peso (kg)</th>
          <th>Altezza (cm)</th>
          <th>BMI</th>
          <th>Classificazione</th>
          <th>Note</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($misurazioni as $m): ?>
        <?php $classe = classificaBMI((float)$m['bmi']); ?>
        <tr>
          <td><?= dataITA($m['data']) ?></td>
          <td><?= h(substr($m['ora'], 0, 5)) ?></td>
          <td class="fw-semibold"><?= number_format($m['peso_kg'], 1) ?></td>
          <td><?= $m['altezza_cm'] ? number_format($m['altezza_cm'], 0) : '—' ?></td>
          <td class="fw-semibold text-<?= $classe['class'] ?>"><?= number_format($m['bmi'], 1) ?></td>
          <td><span class="badge bg-<?= $classe['class'] ?>"><?= $classe['label'] ?></span></td>
          <td><?= $m['note'] ? h(mb_strimwidth($m['note'], 0, 40, '…')) : '—' ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="edit.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary" title="Modifica">
                <i class="bi bi-pencil"></i></a>
              <a href="delete.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger btn-confirm-delete" title="Elimina">
                <i class="bi bi-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
