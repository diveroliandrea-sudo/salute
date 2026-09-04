<?php
/**
 * documents/view.php
 * Visualizzatore integrato per PDF e immagini archiviati nel database.
 * Parametri GET: modulo=[visita|impegnativa|ricovero|invalidita|piano_terapeutico], id=INT, campo=[file|lettera]
 * Aggiunto: embed=1 per anteprima iframe senza layout completo (usato da therapy_plans/view.php)
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
// Per la visualizzazione documenti, il membro in sessione è necessario per il controllo
// di ownership. Se non è selezionato, redirect al login (già coperto da requireLogin).
$mid = $membro ? (int)$membro['id'] : 0;

$modulo = $_GET['modulo'] ?? '';
$id     = (int)($_GET['id'] ?? 0);
$campo  = $_GET['campo'] ?? 'file'; // 'file' o 'lettera'
$mode   = $_GET['mode'] ?? 'view';  // 'view' o 'download'
$embed  = !empty($_GET['embed']);   // true = risposta raw (src data-URI per iframe/img)

// Mappa modulo → tabella e colonne
$mappa = [
    'visita'             => ['table' => 'visite_specialistiche', 'nome' => 'file_nome',           'mime' => 'file_mime',            'dati' => 'file_dati'],
    'impegnativa'        => ['table' => 'impegnative',           'nome' => 'file_nome',           'mime' => 'file_mime',            'dati' => 'file_dati'],
    'ricovero'           => ['table' => 'ricoveri',              'nome' => 'lettera_file_nome',   'mime' => 'lettera_file_mime',    'dati' => 'lettera_file_dati'],
    'invalidita'         => ['table' => 'invalidita',            'nome' => 'file_nome',           'mime' => 'file_mime',            'dati' => 'file_dati'],
    'piano_terapeutico'  => ['table' => 'piani_terapeutici',     'nome' => 'file_nome',           'mime' => 'file_mime',            'dati' => 'file_dati'],
    'farmaco'            => ['table' => 'farmaci',               'nome' => 'scontrino_file_nome', 'mime' => 'scontrino_file_mime',  'dati' => 'scontrino_file_dati'],
];

if (!isset($mappa[$modulo]) || !$id) {
    http_response_code(404);
    die('Modulo o ID non valido.');
}

$m     = $mappa[$modulo];
$table = $m['table'];
$cNome = $m['nome'];
$cMime = $m['mime'];
$cDati = $m['dati'];

// Recupera il record verificando la proprietà:
// - per farmaci: verifica che membro_id appartenga all'utente loggato (ownership via JOIN)
//   così funziona anche se l'utente non ha pre-selezionato quel membro in sessione
// - per tutti gli altri: usa membro_id = membro in sessione (comportamento originale)
if ($modulo === 'farmaco') {
    $stmt = db()->prepare(
        "SELECT f.$cNome AS nome, f.$cMime AS mime, f.$cDati AS dati
         FROM `farmaci` f
         JOIN `membri_famiglia` m ON m.id = f.membro_id
         WHERE f.id = ? AND m.utente_id = ?"
    );
    $stmt->execute([$id, $_SESSION['utente_id']]);
} else {
    if (!$mid) { http_response_code(403); die('Seleziona prima un membro.'); }
    $stmt = db()->prepare("SELECT $cNome AS nome, $cMime AS mime, $cDati AS dati FROM `$table` WHERE id=? AND membro_id=?");
    $stmt->execute([$id, $mid]);
}
$row = $stmt->fetch();

if (!$row || !$row['dati']) {
    http_response_code(404);
    die('File non trovato.');
}

// Verifica MIME reale dal contenuto binario (sicurezza aggiuntiva)
$realMime = getMimeFromContent($row['dati']);
$allowed  = unserialize(ALLOWED_MIME);
if (!in_array($realMime, $allowed)) {
    http_response_code(403);
    die('Tipo di file non consentito.');
}

if ($mode === 'download') {
    // ── Download diretto ─────────────────────────────────────
    header('Content-Type: ' . $realMime);
    header('Content-Disposition: attachment; filename="' . addslashes($row['nome']) . '"');
    header('Content-Length: ' . strlen($row['dati']));
    header('Cache-Control: private, no-cache');
    echo $row['dati'];
    exit;
}

// ── Modalità embed: risponde direttamente con il contenuto ────
// Usata da therapy_plans/view.php tramite <iframe src="...&embed=1">
if ($embed) {
    header('Content-Type: ' . $realMime);
    header('Content-Disposition: inline; filename="' . addslashes($row['nome']) . '"');
    header('Content-Length: ' . strlen($row['dati']));
    header('Cache-Control: private, no-store');
    echo $row['dati'];
    exit;
}

// ── Viewer integrato ─────────────────────────────────────────
$isPdf   = ($realMime === 'application/pdf');
$isImage = in_array($realMime, ['image/jpeg','image/png','image/gif','image/webp']);

// Codifica in base64 per l'embed inline (evita un secondo request)
$b64 = base64_encode($row['dati']);
$src = 'data:' . $realMime . ';base64,' . $b64;

$pageTitle = 'Visualizza Documento';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-file-earmark me-2"></i><?= h($row['nome']) ?></h1>
  <div class="d-flex gap-2">
    <a href="?modulo=<?= h($modulo) ?>&id=<?= $id ?>&mode=download"
       class="btn btn-primary btn-sm">
      <i class="bi bi-download me-1"></i>Scarica
    </a>
    <?php
    // Determina la pagina di ritorno in base al modulo
    $backUrl = match($modulo) {
        'farmaco'           => APP_URL . '/modules/medications/',
        'visita'            => APP_URL . '/modules/visits/',
        'impegnativa'       => APP_URL . '/modules/prescriptions/',
        'ricovero'          => APP_URL . '/modules/hospitalizations/',
        'invalidita'        => APP_URL . '/modules/disability/',
        'piano_terapeutico' => APP_URL . '/modules/therapy_plans/',
        default             => APP_URL . '/modules/documents/',
    };
    ?>
    <a href="<?= $backUrl ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Indietro
    </a>
  </div>
</div>

<div class="win-card">
  <div class="win-card-body p-2">
    <?php if ($isPdf): ?>
    <!-- Viewer PDF in-page -->
    <iframe src="<?= $src ?>" class="doc-viewer-frame" title="Visualizzatore PDF"></iframe>
    <?php elseif ($isImage): ?>
    <!-- Viewer immagine centrata con zoom -->
    <div class="text-center">
      <img src="<?= $src ?>" class="img-fluid rounded"
           style="max-height:80vh;cursor:zoom-in" id="docImg"
           alt="<?= h($row['nome']) ?>">
    </div>
    <script>
    // Click to toggle full-size zoom
    document.getElementById('docImg').addEventListener('click', function(){
      this.style.maxHeight = this.style.maxHeight === 'none' ? '80vh' : 'none';
      this.style.cursor    = this.style.cursor === 'zoom-out' ? 'zoom-in' : 'zoom-out';
    });
    </script>
    <?php else: ?>
    <div class="alert alert-warning">
      Anteprima non disponibile per questo tipo di file. Usa il pulsante Scarica.
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
