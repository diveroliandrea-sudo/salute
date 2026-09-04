<?php
/**
 * medications/add.php
 * Creazione nuovo farmaco con piani di somministrazione.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $nome = trim($_POST['nome_farmaco'] ?? '');
    if (!$nome) {
        $errore = 'Il nome del farmaco è obbligatorio.';
    } else {
        // ── Gestione upload scontrino ─────────────────────────
        $scontrinoNome = null;
        $scontrinoMime = null;
        $scontrinoDati = null;

        if (!empty($_FILES['scontrino']['tmp_name'])) {
            $file = $_FILES['scontrino'];
            if ($file['size'] > MAX_UPLOAD_BYTE) {
                $errore = 'Il file scontrino supera il limite di ' . MAX_UPLOAD_MB . ' MB.';
            } else {
                $dati = file_get_contents($file['tmp_name']);
                $mime = getMimeFromContent($dati);
                $allowed = unserialize(ALLOWED_MIME);
                if (!in_array($mime, $allowed)) {
                    $errore = 'Tipo di file non consentito. Usa PDF o immagine.';
                } else {
                    $scontrinoNome = basename($file['name']);
                    $scontrinoMime = $mime;
                    $scontrinoDati = $dati;
                }
            }
        }

        if (!$errore) {
        $cronico   = isset($_POST['cronico']) ? 1 : 0;
        $dataFine  = (!$cronico && !empty($_POST['data_fine'])) ? $_POST['data_fine'] : null;

        $ins = db()->prepare(
            'INSERT INTO farmaci (membro_id, nome_farmaco, principio_attivo, dosaggio, forma,
             note_assunzione, data_inizio, data_fine, cronico, attivo, note,
             scontrino_file_nome, scontrino_file_mime, scontrino_file_dati)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $membro['id'],
            $nome,
            trim($_POST['principio_attivo'] ?? '') ?: null,
            trim($_POST['dosaggio']         ?? '') ?: null,
            trim($_POST['forma']            ?? '') ?: null,
            trim($_POST['note_assunzione']  ?? '') ?: null,
            $_POST['data_inizio']           ?: null,
            $dataFine,
            $cronico,
            1,
            trim($_POST['note'] ?? '') ?: null,
            $scontrinoNome,
            $scontrinoMime,
            $scontrinoDati,
        ]);
        $farmId = (int)db()->lastInsertId();

        // ── Salva orari di somministrazione ──────────────────
        $orari = $_POST['orari'] ?? [];
        $insOra = db()->prepare(
            'INSERT INTO farmaci_orari (farmaco_id, ora, quantita, note) VALUES (?, ?, ?, ?)'
        );
        foreach ($orari as $o) {
            $ora = trim($o['ora'] ?? '');
            if ($ora) {
                $insOra->execute([
                    $farmId,
                    $ora,
                    trim($o['quantita'] ?? '1') ?: '1',
                    trim($o['note']     ?? '') ?: null,
                ]);
            }
        }

        setFlash('success', 'Farmaco «' . $nome . '» aggiunto correttamente.');
        header('Location: index.php'); exit;
        } // end if (!$errore)
    }
}

$pageTitle = 'Aggiungi Farmaco';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-capsule me-2"></i>Aggiungi Farmaco</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Indietro</a>
</div>

<div class="row justify-content-center">
<div class="col-lg-9">
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-capsule"></i> Nuovo Farmaco — <?= h($membro['nome'].' '.$membro['cognome']) ?></div>
  <div class="win-card-body">
    <?php if ($errore): ?><div class="alert alert-danger py-2"><?= h($errore) ?></div><?php endif; ?>
    <form method="POST" id="formFarmaco" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" id="orario_count" name="orario_count" value="0">

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Nome Farmaco *</label>
          <input type="text" name="nome_farmaco" class="form-control" required
                 value="<?= h($_POST['nome_farmaco'] ?? '') ?>" placeholder="es. Tachipirina">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Principio Attivo</label>
          <input type="text" name="principio_attivo" class="form-control"
                 value="<?= h($_POST['principio_attivo'] ?? '') ?>" placeholder="es. Paracetamolo">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Dosaggio</label>
          <input type="text" name="dosaggio" class="form-control"
                 value="<?= h($_POST['dosaggio'] ?? '') ?>" placeholder="es. 500mg, 1 compressa">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Forma</label>
          <select name="forma" class="form-select">
            <option value="">— Seleziona —</option>
            <?php foreach (['Compressa','Capsula','Sciroppo','Fiala','Gocce','Crema','Supposte','Spray','Cerotto','Altro'] as $f): ?>
            <option value="<?= $f ?>" <?= (($_POST['forma'] ?? '') === $f) ? 'selected' : '' ?>><?= $f ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Note di Assunzione</label>
          <input type="text" name="note_assunzione" class="form-control"
                 value="<?= h($_POST['note_assunzione'] ?? '') ?>" placeholder="es. a stomaco pieno">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data Inizio</label>
          <input type="date" name="data_inizio" class="form-control"
                 value="<?= h($_POST['data_inizio'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-md-4" id="data_fine_row">
          <label class="form-label fw-semibold">Data Fine</label>
          <input type="date" name="data_fine" id="data_fine" class="form-control"
                 value="<?= h($_POST['data_fine'] ?? '') ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="cronico" id="cronico" value="1"
                   <?= !empty($_POST['cronico']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="cronico">
              Terapia Cronica / Indefinita
            </label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Note aggiuntive</label>
          <textarea name="note" class="form-control" rows="2"><?= h($_POST['note'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold"><i class="bi bi-receipt me-1"></i>Scontrino / Ricevuta</label>
          <input type="file" name="scontrino" class="form-control"
                 accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
          <div class="form-text">Opzionale. Formati accettati: PDF, JPG, PNG, GIF, WEBP (max <?= MAX_UPLOAD_MB ?> MB).</div>
        </div>
      </div>

      <!-- ── Orari di somministrazione ──────────────────────── -->
      <hr>
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="mb-0"><i class="bi bi-clock me-2"></i>Orari di Somministrazione</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn_add_orario">
          <i class="bi bi-plus-circle me-1"></i>Aggiungi Orario
        </button>
      </div>
      <div class="mb-2">
        <div class="row g-2 mb-1">
          <div class="col-md-3"><small class="text-muted fw-semibold">Ora</small></div>
          <div class="col-md-3"><small class="text-muted fw-semibold">Quantità</small></div>
          <div class="col-md-4"><small class="text-muted fw-semibold">Note Orario</small></div>
          <div class="col-md-2"></div>
        </div>
        <div id="orari_container">
          <!-- righe aggiunte dinamicamente da app.js -->
        </div>
        <p class="text-muted small" id="no_orari_msg">
          Nessun orario impostato. Clicca «Aggiungi Orario» per aggiungerne uno.
        </p>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salva Farmaco</button>
        <a href="index.php" class="btn btn-outline-secondary">Annulla</a>
      </div>
    </form>
  </div>
</div>
</div>
</div>

<script>
// Mostra/nascondi messaggio "nessun orario"
$(document).ready(function(){
  function aggiornaMsg() {
    var n = $('.orario-row').length;
    $('#no_orari_msg').toggle(n === 0);
  }
  $('#btn_add_orario').on('click', function(){ setTimeout(aggiornaMsg, 50); });
  $(document).on('click','.btn-remove-orario', function(){ setTimeout(aggiornaMsg, 50); });
  aggiornaMsg();
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
