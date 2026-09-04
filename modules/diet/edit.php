<?php
/**
 * diet/edit.php
 * Modifica una misurazione peso/BMI esistente.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }

$id   = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM peso_diario WHERE id = ? AND membro_id = ?');
$stmt->execute([$id, $membro['id']]);
$riga = $stmt->fetch();
if (!$riga) { setFlash('danger','Misurazione non trovata.'); header('Location: index.php'); exit; }

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data    = trim($_POST['data']       ?? '');
    $ora     = trim($_POST['ora']        ?? '');
    $peso    = trim($_POST['peso_kg']    ?? '');
    $altezza = trim($_POST['altezza_cm'] ?? '');
    $note    = trim($_POST['note']       ?? '');

    if (!$data || !$ora || !$peso) {
        $errore = 'Data, ora e peso sono obbligatori.';
    } elseif (!is_numeric($peso) || (float)$peso <= 0) {
        $errore = 'Il peso deve essere un numero positivo.';
    } elseif ($altezza !== '' && (!is_numeric($altezza) || (float)$altezza <= 0)) {
        $errore = 'L\'altezza deve essere un numero positivo.';
    } else {
        $pesoF    = (float)$peso;
        $altezzaF = ($altezza !== '') ? (float)$altezza : null;
        $bmi      = ($altezzaF && $altezzaF > 0) ? calcolaBMI($pesoF, $altezzaF) : null;

        if (!$bmi) {
            $errore = 'Inserisci l\'altezza per calcolare il BMI.';
        } else {
            db()->prepare(
                'UPDATE peso_diario
                    SET data = ?, ora = ?, peso_kg = ?, altezza_cm = ?, bmi = ?, note = ?, updated_at = NOW()
                  WHERE id = ?'
            )->execute([$data, $ora, $pesoF, $altezzaF, $bmi, $note ?: null, $id]);

            setFlash('success', 'Misurazione aggiornata.');
            header('Location: index.php'); exit;
        }
    }
}

// Valori da mostrare nel form (POST ha priorità su DB)
$val = [
    'data'       => $_POST['data']       ?? $riga['data'],
    'ora'        => $_POST['ora']        ?? substr($riga['ora'], 0, 5),
    'peso_kg'    => $_POST['peso_kg']    ?? $riga['peso_kg'],
    'altezza_cm' => $_POST['altezza_cm'] ?? $riga['altezza_cm'],
    'note'       => $_POST['note']       ?? $riga['note'],
];

$pageTitle = 'Modifica Misurazione';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-speedometer2 me-2"></i>Modifica Misurazione</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Indietro</a>
</div>

<div class="row justify-content-center">
<div class="col-lg-7">
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-pencil"></i> Modifica — <?= h($membro['nome'].' '.$membro['cognome']) ?> — <?= dataITA($riga['data']) ?></div>
  <div class="win-card-body">
    <?php if ($errore): ?>
    <div class="alert alert-danger py-2"><?= h($errore) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div class="row g-3">
        <!-- Data -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Data <span class="text-danger">*</span></label>
          <input type="date" name="data" class="form-control" required
                 value="<?= h($val['data']) ?>">
        </div>

        <!-- Ora -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Ora <span class="text-danger">*</span></label>
          <input type="time" name="ora" class="form-control" required
                 value="<?= h($val['ora']) ?>">
        </div>

        <!-- Peso -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Peso (kg) <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="number" name="peso_kg" id="peso_kg" class="form-control" required
                   step="0.1" min="1" max="500"
                   value="<?= h($val['peso_kg']) ?>">
            <span class="input-group-text">kg</span>
          </div>
        </div>

        <!-- Altezza -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Altezza (cm)</label>
          <div class="input-group">
            <input type="number" name="altezza_cm" id="altezza_cm" class="form-control"
                   step="0.5" min="50" max="250"
                   value="<?= h($val['altezza_cm']) ?>">
            <span class="input-group-text">cm</span>
          </div>
        </div>

        <!-- BMI preview -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">BMI (calcolato automaticamente)</label>
          <div class="input-group">
            <input type="text" id="bmi_preview" class="form-control" readonly
                   value="<?= number_format($riga['bmi'], 1) ?>">
            <?php $c = classificaBMI((float)$riga['bmi']); ?>
            <span class="input-group-text fw-semibold text-<?= $c['class'] ?>" id="bmi_label">
              <?= $c['label'] ?>
            </span>
          </div>
        </div>

        <!-- Note -->
        <div class="col-12">
          <label class="form-label fw-semibold">Note</label>
          <textarea name="note" class="form-control" rows="2"><?= h($val['note'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salva Modifiche</button>
        <a href="index.php" class="btn btn-outline-secondary">Annulla</a>
      </div>
    </form>
  </div>
</div>
</div>
</div>

<script>
(function () {
  const pesoEl    = document.getElementById('peso_kg');
  const altezzaEl = document.getElementById('altezza_cm');
  const bmiEl     = document.getElementById('bmi_preview');
  const labelEl   = document.getElementById('bmi_label');

  const classifiche = [
    { max: 18.5, label: 'Sottopeso',  cls: 'text-info' },
    { max: 25.0, label: 'Normopeso',  cls: 'text-success' },
    { max: 30.0, label: 'Sovrappeso', cls: 'text-warning' },
    { max: Infinity, label: 'Obesità', cls: 'text-danger' },
  ];

  function calcolaBMI() {
    const p = parseFloat(pesoEl.value);
    const a = parseFloat(altezzaEl.value) / 100;
    if (p > 0 && a > 0) {
      const bmi = (p / (a * a)).toFixed(1);
      bmiEl.value = bmi;
      const c = classifiche.find(x => parseFloat(bmi) < x.max);
      labelEl.textContent = c.label;
      labelEl.className   = 'input-group-text fw-semibold ' + c.cls;
    } else {
      bmiEl.value         = '';
      labelEl.textContent = '—';
      labelEl.className   = 'input-group-text';
    }
  }

  pesoEl.addEventListener('input', calcolaBMI);
  altezzaEl.addEventListener('input', calcolaBMI);
})();
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
