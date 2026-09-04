<?php
/**
 * diet/add.php
 * Inserimento nuova misurazione peso/BMI.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }

// Altezza di default dal membro (campo altezza_cm se esiste)
$altezzaDefault = $membro['altezza_cm'] ?? '';

$errore = '';
$bmiCalcolato = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data      = trim($_POST['data']       ?? '');
    $ora       = trim($_POST['ora']        ?? '');
    $peso      = trim($_POST['peso_kg']    ?? '');
    $altezza   = trim($_POST['altezza_cm'] ?? '');
    $note      = trim($_POST['note']       ?? '');

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
            $ins = db()->prepare(
                'INSERT INTO peso_diario (membro_id, data, ora, peso_kg, altezza_cm, bmi, note)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $membro['id'],
                $data,
                $ora,
                $pesoF,
                $altezzaF,
                $bmi,
                $note ?: null,
            ]);

            setFlash('success', 'Misurazione del ' . dataITA($data) . ' aggiunta correttamente.');
            header('Location: index.php'); exit;
        }
    }

    // Calcolo live per mostrare anteprima anche in caso di errore
    if (!$errore && is_numeric($peso) && is_numeric($altezza) && (float)$altezza > 0) {
        $bmiCalcolato = calcolaBMI((float)$peso, (float)$altezza);
    }
}

$pageTitle = 'Aggiungi Misurazione';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-speedometer2 me-2"></i>Aggiungi Misurazione</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Indietro</a>
</div>

<div class="row justify-content-center">
<div class="col-lg-7">
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-plus-circle"></i> Nuova Misurazione — <?= h($membro['nome'].' '.$membro['cognome']) ?></div>
  <div class="win-card-body">
    <?php if ($errore): ?>
    <div class="alert alert-danger py-2"><?= h($errore) ?></div>
    <?php endif; ?>

    <form method="POST" id="formPeso" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div class="row g-3">
        <!-- Data -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Data <span class="text-danger">*</span></label>
          <input type="date" name="data" class="form-control" required
                 value="<?= h($_POST['data'] ?? date('Y-m-d')) ?>">
        </div>

        <!-- Ora -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Ora <span class="text-danger">*</span></label>
          <input type="time" name="ora" class="form-control" required
                 value="<?= h($_POST['ora'] ?? date('H:i')) ?>">
        </div>

        <!-- Peso -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Peso (kg) <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="number" name="peso_kg" id="peso_kg" class="form-control" required
                   step="0.1" min="1" max="500"
                   value="<?= h($_POST['peso_kg'] ?? '') ?>"
                   placeholder="es. 72.5">
            <span class="input-group-text">kg</span>
          </div>
        </div>

        <!-- Altezza -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">Altezza (cm)</label>
          <div class="input-group">
            <input type="number" name="altezza_cm" id="altezza_cm" class="form-control"
                   step="0.5" min="50" max="250"
                   value="<?= h($_POST['altezza_cm'] ?? $altezzaDefault) ?>"
                   placeholder="es. 170">
            <span class="input-group-text">cm</span>
          </div>
          <div class="form-text">Necessaria per il calcolo del BMI.</div>
        </div>

        <!-- BMI (calcolato live) -->
        <div class="col-md-6">
          <label class="form-label fw-semibold">BMI (calcolato automaticamente)</label>
          <div class="input-group">
            <input type="text" id="bmi_preview" class="form-control" readonly placeholder="—">
            <span class="input-group-text" id="bmi_label">—</span>
          </div>
        </div>

        <!-- Note -->
        <div class="col-12">
          <label class="form-label fw-semibold">Note</label>
          <textarea name="note" class="form-control" rows="2"
                    placeholder="es. dopo colazione, dopo allenamento…"><?= h($_POST['note'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salva Misurazione</button>
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
      bmiEl.value        = '';
      labelEl.textContent = '—';
      labelEl.className   = 'input-group-text';
    }
  }

  pesoEl.addEventListener('input', calcolaBMI);
  altezzaEl.addEventListener('input', calcolaBMI);
  calcolaBMI(); // calcola subito se i valori sono già presenti
})();
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
