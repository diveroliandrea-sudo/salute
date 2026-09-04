<?php
/**
 * therapy_plans/add.php
 * Creazione nuovo piano terapeutico con upload file.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) {
    setFlash('warning', 'Seleziona prima un membro.');
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $titolo    = trim($_POST['titolo']     ?? '');
    $dataInizio = trim($_POST['data_inizio'] ?? '');

    if (!$titolo) {
        $errore = 'Il titolo del piano è obbligatorio.';
    } elseif (!$dataInizio) {
        $errore = 'La data di inizio è obbligatoria.';
    } else {
        // ── Gestione file allegato ────────────────────────────
        $fileNome = $fileMime = $fileDati = null;

        if (!empty($_FILES['file_allegato']['name'])) {
            $file      = $_FILES['file_allegato'];
            $uploadErr = $file['error'];

            if ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE) {
                $errore = 'File troppo grande. Il limite del server è '
                    . ini_get('upload_max_filesize') . '. Massimo consentito dall\'app: ' . MAX_UPLOAD_MB . ' MB.';
            } elseif ($uploadErr === UPLOAD_ERR_PARTIAL) {
                $errore = 'Upload incompleto. Riprova.';
            } elseif ($uploadErr !== UPLOAD_ERR_OK) {
                $errore = 'Errore durante l\'upload (codice: ' . $uploadErr . '). Riprova.';
            } else {
                $mime         = mime_content_type($file['tmp_name']);
                $allowedMimes = unserialize(ALLOWED_MIME);

                if (!in_array($mime, $allowedMimes)) {
                    $errore = 'Tipo file non consentito (rilevato: ' . $mime . '). Usa PDF o immagine (JPG, PNG, GIF, WEBP).';
                } elseif ($file['size'] > MAX_UPLOAD_BYTE) {
                    $errore = 'File troppo grande (' . round($file['size'] / 1048576, 1) . ' MB). Massimo: ' . MAX_UPLOAD_MB . ' MB.';
                } else {
                    $fileNome = $file['name'];
                    $fileMime = $mime;
                    $fileDati = file_get_contents($file['tmp_name']);
                }
            }
        }

        if (!$errore) {
            // data_fine NULL se "durata indefinita" spuntata o campo vuoto
            $dataFine = (!empty($_POST['indefinito']) || trim($_POST['data_fine'] ?? '') === '')
                        ? null
                        : trim($_POST['data_fine']);

            db()->prepare(
                'INSERT INTO piani_terapeutici
                 (membro_id, titolo, tipo, medico_redattore, struttura,
                  data_inizio, data_fine, obiettivo, descrizione,
                  farmaci_previsti, note, stato,
                  file_nome, file_mime, file_dati)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $membro['id'],
                $titolo,
                trim($_POST['tipo']              ?? '') ?: null,
                trim($_POST['medico_redattore']  ?? '') ?: null,
                trim($_POST['struttura']         ?? '') ?: null,
                $dataInizio,
                $dataFine,
                trim($_POST['obiettivo']         ?? '') ?: null,
                trim($_POST['descrizione']       ?? '') ?: null,
                trim($_POST['farmaci_previsti']  ?? '') ?: null,
                trim($_POST['note']              ?? '') ?: null,
                $_POST['stato']                        ?? 'attivo',
                $fileNome, $fileMime, $fileDati,
            ]);

            setFlash('success', 'Piano terapeutico «' . $titolo . '» aggiunto correttamente.');
            header('Location: index.php');
            exit;
        }
    }
}

$pageTitle = 'Nuovo Piano Terapeutico';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-journal-plus me-2"></i>Nuovo Piano Terapeutico</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Indietro
  </a>
</div>

<div class="row justify-content-center">
<div class="col-lg-10">
<div class="win-card">
  <div class="win-card-header">
    <i class="bi bi-journal-medical"></i>
    Dati Piano — <?= h($membro['nome'] . ' ' . $membro['cognome']) ?>
  </div>
  <div class="win-card-body">

    <?php if ($errore): ?>
    <div class="alert alert-danger py-2">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($errore) ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <!-- ── Sezione 1: Identificazione ──────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-semibold mb-3">
        <i class="bi bi-info-circle me-1"></i>Identificazione
      </h6>
      <div class="row g-3 mb-4">
        <div class="col-md-7">
          <label class="form-label fw-semibold">Titolo Piano *</label>
          <input type="text" name="titolo" class="form-control" required
                 value="<?= h($_POST['titolo'] ?? '') ?>"
                 placeholder="es. Piano terapeutico BPCO, Terapia antipertensiva">
        </div>
        <div class="col-md-5">
          <label class="form-label fw-semibold">Tipo / Categoria</label>
          <select name="tipo" class="form-select">
            <option value="">— Seleziona —</option>
            <?php
            $tipi = [
                'Farmacologica', 'Riabilitativa', 'Oncologica', 'Psicologica',
                'Nutrizionale', 'Fisioterapica', 'Palliativa', 'Post-operatoria',
                'Preventiva', 'Cronica', 'Altra',
            ];
            foreach ($tipi as $t):
            ?>
            <option value="<?= $t ?>"
              <?= (($_POST['tipo'] ?? '') === $t) ? 'selected' : '' ?>>
              <?= $t ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Medico Redattore</label>
          <input type="text" name="medico_redattore" class="form-control"
                 value="<?= h($_POST['medico_redattore'] ?? '') ?>"
                 placeholder="Nome e cognome del medico">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Struttura / Reparto</label>
          <input type="text" name="struttura" class="form-control"
                 value="<?= h($_POST['struttura'] ?? '') ?>"
                 placeholder="es. Ospedale Civile — Cardiologia">
        </div>
      </div>

      <hr class="my-3">

      <!-- ── Sezione 2: Date e Stato ─────────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-semibold mb-3">
        <i class="bi bi-calendar-range me-1"></i>Durata e Stato
      </h6>
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data Inizio *</label>
          <input type="date" name="data_inizio" id="data_inizio" class="form-control"
                 required value="<?= h($_POST['data_inizio'] ?? date('Y-m-d')) ?>">
        </div>

        <div class="col-md-4" id="col_data_fine">
          <label class="form-label fw-semibold">Data Fine</label>
          <input type="date" name="data_fine" id="data_fine" class="form-control"
                 value="<?= h($_POST['data_fine'] ?? '') ?>">
          <div class="form-text">Lascia vuoto oppure spunta "Durata indefinita".</div>
        </div>

        <div class="col-md-4 d-flex align-items-center pt-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox"
                   name="indefinito" id="indefinito" value="1"
                   <?= !empty($_POST['indefinito']) ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="indefinito">
              <i class="bi bi-infinity me-1 text-info"></i>Durata Indefinita
            </label>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label fw-semibold">Stato</label>
          <select name="stato" class="form-select">
            <?php
            $stati = ['attivo' => 'Attivo', 'sospeso' => 'Sospeso',
                      'completato' => 'Completato', 'revocato' => 'Revocato'];
            foreach ($stati as $v => $l):
            ?>
            <option value="<?= $v ?>"
              <?= (($_POST['stato'] ?? 'attivo') === $v) ? 'selected' : '' ?>>
              <?= $l ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr class="my-3">

      <!-- ── Sezione 3: Contenuto Piano ─────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-semibold mb-3">
        <i class="bi bi-file-text me-1"></i>Contenuto del Piano
      </h6>
      <div class="row g-3 mb-4">
        <div class="col-md-12">
          <label class="form-label fw-semibold">Obiettivo Terapeutico</label>
          <textarea name="obiettivo" class="form-control" rows="2"
                    placeholder="es. Controllo pressione arteriosa, riduzione del dolore cronico"
                    ><?= h($_POST['obiettivo'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Descrizione / Indicazioni</label>
          <textarea name="descrizione" class="form-control" rows="4"
                    placeholder="Dettaglio del protocollo terapeutico, istruzioni per il paziente…"
                    ><?= h($_POST['descrizione'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Farmaci Previsti</label>
          <textarea name="farmaci_previsti" class="form-control" rows="4"
                    placeholder="Elenco sintetico dei farmaci inclusi nel piano (nome, dosaggio, frequenza)…"
                    ><?= h($_POST['farmaci_previsti'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Note Aggiuntive</label>
          <textarea name="note" class="form-control" rows="2"
                    ><?= h($_POST['note'] ?? '') ?></textarea>
        </div>
      </div>

      <hr class="my-3">

      <!-- ── Sezione 4: Upload file ──────────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-semibold mb-3">
        <i class="bi bi-paperclip me-1"></i>Documento Allegato
      </h6>
      <div class="row g-3 mb-4">
        <div class="col-md-8">
          <label class="form-label fw-semibold">
            Allega Piano Terapeutico firmato
            <small class="text-muted fw-normal">(PDF o immagine — max <?= MAX_UPLOAD_MB ?> MB)</small>
          </label>
          <input type="file" name="file_allegato" id="file_allegato"
                 class="form-control"
                 accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
          <div class="form-text" id="file_nome_preview"></div>
          <?php
          // Avviso se php.ini ha limiti inferiori all'app
          $phpMaxPost   = (int)ini_get('post_max_size');
          $phpMaxUpload = (int)ini_get('upload_max_filesize');
          $phpMin       = min($phpMaxPost, $phpMaxUpload);
          if ($phpMin < MAX_UPLOAD_MB):
          ?>
          <div class="alert alert-warning py-1 px-2 mt-1 mb-0" style="font-size:.8rem">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Attenzione:</strong> php.ini limita l'upload a <strong><?= $phpMin ?> MB</strong>
            (<code>upload_max_filesize=<?= ini_get('upload_max_filesize') ?></code>,
             <code>post_max_size=<?= ini_get('post_max_size') ?></code>).
            Per aumentarlo modifica <code>C:\xampp\php\php.ini</code> e riavvia Apache.
          </div>
          <?php endif; ?>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="alert alert-light border mb-0 py-2 px-3 w-100" style="font-size:.8rem">
            <i class="bi bi-shield-check text-success me-1"></i>
            Il file viene archiviato in modo sicuro nel database.
            Il tipo MIME viene verificato lato server.
          </div>
        </div>
      </div>

      <!-- ── Pulsanti ────────────────────────────────────────── -->
      <div class="d-flex gap-2 mt-2">
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-save me-1"></i>Salva Piano
        </button>
        <a href="index.php" class="btn btn-outline-secondary">Annulla</a>
      </div>
    </form>
  </div><!-- /.win-card-body -->
</div><!-- /.win-card -->
</div>
</div>

<script>
// Toggle data fine quando "Durata indefinita" è spuntata
document.getElementById('indefinito').addEventListener('change', function () {
    var df = document.getElementById('data_fine');
    df.disabled = this.checked;
    if (this.checked) df.value = '';
    document.getElementById('col_data_fine').style.opacity = this.checked ? '.4' : '1';
});
// Inizializza stato al caricamento
(function () {
    var cb = document.getElementById('indefinito');
    if (cb && cb.checked) {
        document.getElementById('data_fine').disabled = true;
        document.getElementById('col_data_fine').style.opacity = '.4';
    }
})();
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
