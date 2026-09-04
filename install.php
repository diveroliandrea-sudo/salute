<?php
/**
 * install.php
 * ============================================================
 * Wizard di installazione — Salute Famiglia
 *
 * PASSI ESEGUITI:
 *  1. Test connessione al server MySQL (senza selezionare il DB)
 *  2. Creazione database `salute` (se non esiste)
 *  3. Esecuzione di tutto lo schema SQL (tabelle, FK, charset)
 *  4. Creazione utente admin (email: admin@salute.local / pw: admin)
 *     con email_verificata = 1 e attivo = 1
 *  5. Scrittura del file lock  install.lock  per bloccare i
 *     successivi accessi alla pagina
 *
 * SICUREZZA: dopo l'installazione eliminare o rinominare install.php
 * ============================================================
 */

// ── Blocco se già installato ─────────────────────────────────
$lockFile = __DIR__ . '/install.lock';
if (file_exists($lockFile)) {
    http_response_code(403);
    die(renderMsg('danger',
        '<strong>Installazione già eseguita.</strong> ' .
        'Per sicurezza questa pagina è stata bloccata.<br>' .
        'Elimina il file <code>install.lock</code> solo se vuoi reinstallare.' .
        '<br><br><a href="http://localhost/salute/" class="btn btn-primary">Vai all\'applicazione</a>'
    ));
}

// ── Parametri di connessione (copiati da config.php) ─────────
$cfg = [
    'host'    => 'localhost',
    'port'    => '3306',
    'db'      => 'salute',
    'user'    => 'root',
    'pass'    => 'root',
    'charset' => 'utf8mb4',
];

// Credenziali admin di default
$adminNome     = 'Admin';
$adminCognome  = 'Sistema';
$adminEmail    = 'admin@salute.local';
$adminPassword = 'admin';

// ── Raccolta log di installazione ────────────────────────────
$log   = [];   // array di [tipo, messaggio]
$done  = false;

// ── Funzioni di supporto ──────────────────────────────────────
function logStep(string $tipo, string $msg): void {
    global $log;
    $log[] = ['tipo' => $tipo, 'msg' => $msg];
}

function renderMsg(string $tipo, string $msg): string {
    return '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Installazione — Salute Famiglia</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    </head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="card shadow p-4" style="max-width:600px;width:100%">
    <div class="alert alert-'.$tipo.' mb-0">'.$msg.'</div>
    </div></body></html>';
}

// ── Esegui installazione al submit ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_install'])) {

    // Sovrascrittura parametri dal form (se l'utente li ha modificati)
    $cfg['host'] = trim($_POST['db_host'] ?? $cfg['host']);
    $cfg['port'] = trim($_POST['db_port'] ?? $cfg['port']);
    $cfg['user'] = trim($_POST['db_user'] ?? $cfg['user']);
    $cfg['pass'] = $_POST['db_pass']      ?? $cfg['pass'];
    $cfg['db']   = trim($_POST['db_name'] ?? $cfg['db']);

    $adminEmail    = trim($_POST['admin_email']    ?? $adminEmail);
    $adminPassword = $_POST['admin_password']       ?? $adminPassword;
    $adminNome     = trim($_POST['admin_nome']      ?? $adminNome);
    $adminCognome  = trim($_POST['admin_cognome']   ?? $adminCognome);

    // ── STEP 1: connessione senza selezionare il DB ───────────
    try {
        $dsnBase = "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}";
        $pdoBase = new PDO($dsnBase, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        logStep('success', '✔ Connessione al server MySQL riuscita ('.$cfg['host'].':'.$cfg['port'].')');
    } catch (PDOException $e) {
        logStep('danger', '✘ Impossibile connettersi a MySQL: ' . htmlspecialchars($e->getMessage()));
        goto renderPage;
    }

    // ── STEP 2: crea database ─────────────────────────────────
    try {
        $pdoBase->exec(
            "CREATE DATABASE IF NOT EXISTS `{$cfg['db']}`
             CHARACTER SET {$cfg['charset']} COLLATE {$cfg['charset']}_unicode_ci"
        );
        logStep('success', '✔ Database <code>'.$cfg['db'].'</code> creato / già esistente');
    } catch (PDOException $e) {
        logStep('danger', '✘ Errore creazione DB: ' . htmlspecialchars($e->getMessage()));
        goto renderPage;
    }

    // ── STEP 3: connessione con il DB e creazione schema ──────
    try {
        $dsnFull = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset={$cfg['charset']}";
        $pdo = new PDO($dsnFull, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        logStep('success', '✔ Connessione al database <code>'.$cfg['db'].'</code> riuscita');
    } catch (PDOException $e) {
        logStep('danger', '✘ Connessione DB fallita: ' . htmlspecialchars($e->getMessage()));
        goto renderPage;
    }

    // Esegui lo schema SQL istruzione per istruzione
    $sqlFile = __DIR__ . '/database/salute.sql';
    if (!file_exists($sqlFile)) {
        logStep('danger', '✘ File SQL non trovato: <code>database/salute.sql</code>');
        goto renderPage;
    }

    $sqlRaw = file_get_contents($sqlFile);

    // Rimuove le righe CREATE DATABASE / USE (già gestite sopra)
    $sqlRaw = preg_replace('/^CREATE DATABASE.*?;/im',  '', $sqlRaw);
    $sqlRaw = preg_replace('/^USE\s+`?salute`?\s*;/im', '', $sqlRaw);

    // Divide in singole istruzioni (split su ";" a fine riga significativa)
    $statements = array_filter(
        array_map('trim', explode(';', $sqlRaw)),
        fn($s) => strlen($s) > 5
    );

    $tableCount = 0;
    $errors     = [];
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt . ';');
            if (stripos($stmt, 'CREATE TABLE') !== false) {
                // Estrae nome tabella per il log
                preg_match('/CREATE TABLE IF NOT EXISTS `?(\w+)`?/i', $stmt, $m);
                $tname = $m[1] ?? '?';
                logStep('success', '✔ Tabella <code>'.$tname.'</code> creata / già esistente');
                $tableCount++;
            }
        } catch (PDOException $e) {
            $short = mb_substr($stmt, 0, 80);
            $errors[] = htmlspecialchars($e->getMessage()) . ' — <code>' . htmlspecialchars($short) . '…</code>';
        }
    }

    if ($errors) {
        foreach ($errors as $err) {
            logStep('warning', '⚠ ' . $err);
        }
    } else {
        logStep('success', "✔ Schema SQL eseguito correttamente ($tableCount tabelle)");
    }

    // ── STEP 4: creazione utente admin ────────────────────────
    try {
        // Controlla se esiste già
        $check = $pdo->prepare('SELECT id FROM utenti WHERE email = ?');
        $check->execute([$adminEmail]);
        $existing = $check->fetch();

        if ($existing) {
            // Aggiorna la password e lo stato dell'utente esistente
            $pdo->prepare(
                'UPDATE utenti SET password_hash=?, email_verificata=1, attivo=1, updated_at=NOW() WHERE email=?'
            )->execute([password_hash($adminPassword, PASSWORD_DEFAULT), $adminEmail]);
            logStep('info', '✔ Utente admin già esistente — password e stato aggiornati: <code>' . htmlspecialchars($adminEmail) . '</code>');
        } else {
            $pdo->prepare(
                'INSERT INTO utenti (nome, cognome, email, password_hash, email_verificata, attivo)
                 VALUES (?, ?, ?, ?, 1, 1)'
            )->execute([
                $adminNome,
                $adminCognome,
                $adminEmail,
                password_hash($adminPassword, PASSWORD_DEFAULT),
            ]);
            logStep('success',
                '✔ Utente admin creato: email=<code>' . htmlspecialchars($adminEmail) .
                '</code> password=<code>' . htmlspecialchars($adminPassword) . '</code>'
            );
        }
    } catch (PDOException $e) {
        logStep('danger', '✘ Errore creazione utente admin: ' . htmlspecialchars($e->getMessage()));
        goto renderPage;
    }

    // ── STEP 5: scrittura lock file ───────────────────────────
    $hasFatal = !empty(array_filter($log, fn($l) => $l['tipo'] === 'danger'));
    if (!$hasFatal) {
        file_put_contents($lockFile,
            "Installazione completata il " . date('Y-m-d H:i:s') . "\n" .
            "Admin: $adminEmail\n"
        );
        logStep('success', '✔ File <code>install.lock</code> creato — pagina di installazione bloccata');
        $done = true;
    } else {
        logStep('warning', '⚠ Installazione completata con errori — il file install.lock NON è stato creato');
    }
}

renderPage:
// ── Render pagina ─────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Installazione — Salute Famiglia</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --win-blue: #0078d4; }
    body   { background: #f0f2f5; font-family: 'Segoe UI', system-ui, sans-serif; }
    .install-card { max-width: 780px; margin: 2.5rem auto; }
    .install-header {
      background: var(--win-blue);
      color: #fff;
      border-radius: 8px 8px 0 0;
      padding: 1.4rem 1.8rem;
    }
    .install-body  { background: #fff; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 8px 8px; padding: 2rem; }
    .step-badge    { width: 28px; height: 28px; border-radius: 50%; background: var(--win-blue); color:#fff;
                     display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:.8rem; flex-shrink:0; }
    .log-box       { background: #1e1e1e; color: #d4d4d4; border-radius: 6px; padding: 1rem; font-size: .82rem; max-height: 340px; overflow-y: auto; }
    .log-box .ok   { color: #4ec9b0; }
    .log-box .err  { color: #f44747; }
    .log-box .warn { color: #dcdcaa; }
    .log-box .info { color: #9cdcfe; }
    .separator     { border-top: 1px solid #e9ecef; margin: 1.5rem 0; }
  </style>
</head>
<body>

<div class="install-card">

  <!-- Header -->
  <div class="install-header d-flex align-items-center gap-3">
    <i class="bi bi-heart-pulse-fill" style="font-size:2rem;color:#ff6b6b"></i>
    <div>
      <h4 class="mb-0 fw-bold">Salute Famiglia</h4>
      <small class="opacity-75">Procedura guidata di installazione</small>
    </div>
  </div>

  <div class="install-body">

    <?php if ($done): ?>
    <!-- ── INSTALLAZIONE COMPLETATA ─────────────────────────── -->
    <div class="text-center mb-4">
      <i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i>
      <h4 class="mt-3 text-success fw-bold">Installazione completata!</h4>
      <p class="text-muted">Il database è stato creato e l'utente admin è pronto.</p>
    </div>

    <div class="alert alert-success">
      <h6 class="fw-bold"><i class="bi bi-person-check me-2"></i>Credenziali di accesso</h6>
      <table class="table table-sm table-borderless mb-0">
        <tr><td class="text-muted">URL applicazione</td><td><a href="http://localhost/salute/" target="_blank">http://localhost/salute/</a></td></tr>
        <tr><td class="text-muted">E-mail</td>    <td><code><?= htmlspecialchars($adminEmail) ?></code></td></tr>
        <tr><td class="text-muted">Password</td>  <td><code><?= htmlspecialchars($adminPassword) ?></code></td></tr>
      </table>
    </div>

    <div class="alert alert-warning">
      <i class="bi bi-shield-exclamation me-2"></i>
      <strong>Importante:</strong> dopo il primo accesso cambia la password admin dal tuo profilo.
      Il file <code>install.lock</code> è stato creato — questa pagina non è più accessibile.
    </div>

    <!-- Log finale -->
    <h6 class="fw-semibold mb-2"><i class="bi bi-terminal me-1"></i>Log installazione</h6>
    <div class="log-box">
      <?php foreach ($log as $l): ?>
      <div class="<?= $l['tipo']==='success'?'ok':($l['tipo']==='danger'?'err':($l['tipo']==='warning'?'warn':'info')) ?>">
        › <?= $l['msg'] ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-4">
      <a href="http://localhost/salute/" class="btn btn-primary btn-lg px-5">
        <i class="bi bi-box-arrow-in-right me-2"></i>Accedi all'applicazione
      </a>
    </div>

    <?php elseif (!empty($log)): ?>
    <!-- ── INSTALLAZIONE CON ERRORI ─────────────────────────── -->
    <div class="alert alert-danger">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <strong>Errore durante l'installazione.</strong> Controlla il log qui sotto e riprova.
    </div>

    <h6 class="fw-semibold mb-2"><i class="bi bi-terminal me-1"></i>Log installazione</h6>
    <div class="log-box">
      <?php foreach ($log as $l): ?>
      <div class="<?= $l['tipo']==='success'?'ok':($l['tipo']==='danger'?'err':($l['tipo']==='warning'?'warn':'info')) ?>">
        › <?= $l['msg'] ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-4 text-center">
      <button onclick="history.back()" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Torna al form
      </button>
    </div>

    <?php else: ?>
    <!-- ── FORM INIZIALE ─────────────────────────────────────── -->

    <p class="text-muted mb-4">
      Questa procedura creerà il database <strong>salute</strong>, eseguirà lo schema SQL
      e creerà l'utente amministratore. Verifica i parametri prima di procedere.
    </p>

    <form method="POST" id="installForm">
      <input type="hidden" name="run_install" value="1">

      <!-- Connessione DB -->
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="step-badge">1</span>
        <h6 class="mb-0 fw-semibold">Parametri Database</h6>
      </div>
      <div class="row g-3 ms-4 mb-4">
        <div class="col-md-5">
          <label class="form-label fw-semibold">Host MySQL</label>
          <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($cfg['host']) ?>" required>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">Porta</label>
          <input type="number" name="db_port" class="form-control" value="<?= htmlspecialchars($cfg['port']) ?>" required>
        </div>
        <div class="col-md-5">
          <label class="form-label fw-semibold">Nome Database</label>
          <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($cfg['db']) ?>" required>
        </div>
        <div class="col-md-5">
          <label class="form-label fw-semibold">Utente MySQL</label>
          <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($cfg['user']) ?>" required>
        </div>
        <div class="col-md-7">
          <label class="form-label fw-semibold">Password MySQL</label>
          <div class="input-group">
            <input type="password" name="db_pass" id="db_pass" class="form-control" value="<?= htmlspecialchars($cfg['pass']) ?>">
            <button class="btn btn-outline-secondary" type="button" id="toggleDbPass">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
      </div>

      <div class="separator"></div>

      <!-- Utente Admin -->
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="step-badge">2</span>
        <h6 class="mb-0 fw-semibold">Utente Amministratore</h6>
      </div>
      <div class="row g-3 ms-4 mb-4">
        <div class="col-md-4">
          <label class="form-label fw-semibold">Nome</label>
          <input type="text" name="admin_nome" class="form-control" value="<?= htmlspecialchars($adminNome) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Cognome</label>
          <input type="text" name="admin_cognome" class="form-control" value="<?= htmlspecialchars($adminCognome) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">E-mail Admin</label>
          <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($adminEmail) ?>" required>
        </div>
        <div class="col-md-5">
          <label class="form-label fw-semibold">Password Admin</label>
          <div class="input-group">
            <input type="password" name="admin_password" id="admin_pass" class="form-control"
                   value="<?= htmlspecialchars($adminPassword) ?>" required minlength="4">
            <button class="btn btn-outline-secondary" type="button" id="toggleAdminPass">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="form-text text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Cambia questa password dopo il primo accesso!</div>
        </div>
      </div>

      <div class="separator"></div>

      <!-- Riepilogo azioni -->
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="step-badge">3</span>
        <h6 class="mb-0 fw-semibold">Azioni che verranno eseguite</h6>
      </div>
      <ul class="list-group list-group-flush ms-4 mb-4">
        <li class="list-group-item py-2 px-0 border-0">
          <i class="bi bi-check2 text-success me-2"></i>Connessione al server MySQL
        </li>
        <li class="list-group-item py-2 px-0 border-0">
          <i class="bi bi-check2 text-success me-2"></i>Creazione database <code>salute</code> (CHARACTER SET utf8mb4)
        </li>
        <li class="list-group-item py-2 px-0 border-0">
          <i class="bi bi-check2 text-success me-2"></i>Esecuzione schema <code>database/salute.sql</code> (11 tabelle)
        </li>
        <li class="list-group-item py-2 px-0 border-0">
          <i class="bi bi-check2 text-success me-2"></i>Creazione utente admin con <code>email_verificata = 1</code>
        </li>
        <li class="list-group-item py-2 px-0 border-0">
          <i class="bi bi-check2 text-success me-2"></i>Scrittura file <code>install.lock</code> (blocco reinstallazione)
        </li>
      </ul>

      <div class="d-grid">
        <button type="submit" class="btn btn-primary btn-lg" id="btnInstall">
          <i class="bi bi-play-circle-fill me-2"></i>Avvia Installazione
        </button>
      </div>
    </form>

    <?php endif; ?>

  </div><!-- /.install-body -->
</div><!-- /.install-card -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle visibilità password
function togglePw(btnId, inputId) {
  document.getElementById(btnId).addEventListener('click', function() {
    var inp = document.getElementById(inputId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    this.querySelector('i').classList.toggle('bi-eye');
    this.querySelector('i').classList.toggle('bi-eye-slash');
  });
}
togglePw('toggleDbPass',    'db_pass');
togglePw('toggleAdminPass', 'admin_pass');

// Mostra spinner durante installazione
var form = document.getElementById('installForm');
if (form) {
  form.addEventListener('submit', function() {
    var btn = document.getElementById('btnInstall');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Installazione in corso…';
  });
}
</script>
</body>
</html>
