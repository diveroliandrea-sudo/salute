<?php
/**
 * vitals/index.php — Parametri Vitali: storico e inserimento
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();
$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }
$mid = $membro['id'];

// Elimina
if (isset($_GET['delete'])) {
    $did=(int)$_GET['delete'];
    $s=db()->prepare('SELECT id FROM parametri_vitali WHERE id=? AND membro_id=?');
    $s->execute([$did,$mid]);
    if ($s->fetch()) { db()->prepare('DELETE FROM parametri_vitali WHERE id=?')->execute([$did]); setFlash('success','Rilevazione eliminata.'); }
    header('Location: index.php'); exit;
}

// Salva nuova rilevazione
$errore='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $dataOra=trim($_POST['data_ora']??'');
    if (!$dataOra) { $errore='Data e ora sono obbligatorie.'; }
    else {
        $peso    = $_POST['peso_kg']    ? (float)$_POST['peso_kg']    : null;
        $altezza = $_POST['altezza_cm'] ? (float)$_POST['altezza_cm'] : null;
        $bmi     = ($peso && $altezza)  ? calcolaBMI($peso, $altezza) : null;

        db()->prepare(
            'INSERT INTO parametri_vitali
             (membro_id,data_ora,peso_kg,altezza_cm,bmi,pressione_sistolica,pressione_diastolica,
              frequenza_cardiaca,temperatura,saturazione_o2,glicemia,note)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $mid, $dataOra, $peso, $altezza, $bmi,
            $_POST['pressione_sistolica']  ?:(null),
            $_POST['pressione_diastolica'] ?:(null),
            $_POST['frequenza_cardiaca']   ?:(null),
            $_POST['temperatura']          ?:(null),
            $_POST['saturazione_o2']       ?:(null),
            $_POST['glicemia']             ?:(null),
            trim($_POST['note']??'')?: null,
        ]);
        setFlash('success','Parametri salvati.');
        header('Location: index.php'); exit;
    }
}

$storico = db()->prepare('SELECT * FROM parametri_vitali WHERE membro_id=? ORDER BY data_ora DESC');
$storico->execute([$mid]);
$storico = $storico->fetchAll();

$pageTitle = 'Parametri Vitali';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-activity me-2"></i>Parametri Vitali — <?= h($membro['nome'].' '.$membro['cognome']) ?></h1>
</div>

<div class="row g-4">
  <!-- ── Form inserimento ──────────────────────────────────── -->
  <div class="col-lg-4">
    <div class="win-card h-100">
      <div class="win-card-header"><i class="bi bi-plus-circle"></i> Nuova Rilevazione</div>
      <div class="win-card-body">
        <?php if ($errore): ?><div class="alert alert-danger py-2"><?= h($errore) ?></div><?php endif; ?>
        <form method="POST" id="formVitali">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <div class="mb-2">
            <label class="form-label fw-semibold">Data e Ora *</label>
            <input type="datetime-local" name="data_ora" class="form-control"
                   value="<?= date('Y-m-d\TH:i') ?>" required>
          </div>
          <hr class="my-2">
          <h6 class="small text-muted fw-semibold text-uppercase">Peso & BMI</h6>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Peso (kg)</label>
              <input type="number" name="peso_kg" id="peso_kg" class="form-control form-control-sm" step="0.1" min="1" max="500">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Altezza (cm)</label>
              <input type="number" name="altezza_cm" id="altezza_cm" class="form-control form-control-sm" step="0.5" min="30" max="250">
            </div>
          </div>
          <div class="mb-2">
            <div class="d-flex align-items-center gap-2">
              <span class="small text-muted">BMI:</span>
              <strong id="bmi_calc">—</strong>
              <input type="hidden" name="bmi" id="bmi_hidden">
              <span class="badge" id="bmi_label"></span>
            </div>
          </div>
          <hr class="my-2">
          <h6 class="small text-muted fw-semibold text-uppercase">Pressione</h6>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Sistolica</label>
              <input type="number" name="pressione_sistolica" class="form-control form-control-sm" min="50" max="300" placeholder="mmHg">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Diastolica</label>
              <input type="number" name="pressione_diastolica" class="form-control form-control-sm" min="30" max="200" placeholder="mmHg">
            </div>
          </div>
          <hr class="my-2">
          <h6 class="small text-muted fw-semibold text-uppercase">Altri Parametri</h6>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Freq. Cardiaca</label>
              <input type="number" name="frequenza_cardiaca" class="form-control form-control-sm" placeholder="bpm">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Temperatura °C</label>
              <input type="number" name="temperatura" class="form-control form-control-sm" step="0.1" placeholder="°C">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">SpO₂ %</label>
              <input type="number" name="saturazione_o2" class="form-control form-control-sm" min="50" max="100" placeholder="%">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Glicemia mg/dL</label>
              <input type="number" name="glicemia" class="form-control form-control-sm" placeholder="mg/dL">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Note</label>
            <textarea name="note" class="form-control form-control-sm" rows="2"></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-save me-1"></i>Salva Parametri
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Storico ───────────────────────────────────────────── -->
  <div class="col-lg-8">
    <div class="win-card">
      <div class="win-card-header"><i class="bi bi-clock-history"></i> Storico Rilevazioni</div>
      <div class="win-card-body p-0">
        <table class="table table-hover mb-0 dt-table" id="tblVitali">
          <thead>
            <tr>
              <th>Data / Ora</th>
              <th>Peso</th>
              <th>BMI</th>
              <th>Pressione</th>
              <th>FC</th>
              <th>Temp.</th>
              <th>SpO₂</th>
              <th>Glicemia</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($storico as $r): ?>
            <tr>
              <td class="text-nowrap"><?= dataOraITA($r['data_ora']) ?></td>
              <td><?= $r['peso_kg'] ? $r['peso_kg'].' kg' : '—' ?></td>
              <td>
                <?php if ($r['bmi']): $bc=classificaBMI((float)$r['bmi']); ?>
                <span class="badge bg-<?= $bc['class'] ?>" title="<?= $bc['label'] ?>"><?= $r['bmi'] ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td>
                <?php if ($r['pressione_sistolica']): $pr=classificaPressione((int)$r['pressione_sistolica'],(int)$r['pressione_diastolica']); ?>
                <?= $r['pressione_sistolica'] ?>/<?= $r['pressione_diastolica'] ?>
                <span class="badge bg-<?= $pr['class'] ?> ms-1" style="font-size:.65rem"><?= $pr['label'] ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td><?= $r['frequenza_cardiaca'] ? $r['frequenza_cardiaca'].' bpm' : '—' ?></td>
              <td><?= $r['temperatura'] ? $r['temperatura'].' °C' : '—' ?></td>
              <td><?= $r['saturazione_o2'] ? $r['saturazione_o2'].' %' : '—' ?></td>
              <td><?= $r['glicemia'] ? $r['glicemia'].' mg/dL' : '—' ?></td>
              <td>
                <a href="?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger btn-confirm-delete">
                  <i class="bi bi-trash"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
