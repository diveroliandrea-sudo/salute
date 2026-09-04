<?php
/**
 * login.php
 * Pagina di autenticazione utente.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

// Già loggato → redirect dashboard
if (!empty($_SESSION['utente_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $errore = 'Inserisci e-mail e password.';
    } else {
        $stmt = db()->prepare('SELECT id, nome, cognome, password_hash, email_verificata, attivo FROM utenti WHERE email = ?');
        $stmt->execute([$email]);
        $utente = $stmt->fetch();

        if (!$utente || !password_verify($password, $utente['password_hash'])) {
            $errore = 'Credenziali non valide.';
        } elseif (!$utente['email_verificata']) {
            $errore = 'Devi prima verificare il tuo indirizzo e-mail.';
        } elseif (!$utente['attivo']) {
            $errore = 'Account disabilitato. Contatta l\'amministratore.';
        } else {
            // Login riuscito: rigenerazione sessione (prevenzione session fixation)
            session_regenerate_id(true);
            $_SESSION['utente_id']    = $utente['id'];
            $_SESSION['utente_nome']  = $utente['nome'];
            $_SESSION['utente_email'] = $email;
            setFlash('success', 'Benvenuto, ' . $utente['nome'] . '!');
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="auth-wrapper">
<div class="auth-card">
  <div class="auth-logo">
    <i class="bi bi-heart-pulse-fill text-danger"></i>
    <h4 class="mt-2 text-win-blue fw-bold"><?= APP_NAME ?></h4>
    <p class="text-muted small">Gestione Salute Familiare</p>
  </div>

  <?php if ($errore): ?>
  <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($errore) ?></div>
  <?php endif; ?>

  <form method="POST" action="" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

    <div class="mb-3">
      <label class="form-label fw-semibold">E-mail</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
        <input type="email" name="email" class="form-control"
               value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold">Password</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input type="password" name="password" class="form-control" required>
        <button class="btn btn-outline-secondary" type="button" id="togglePass">
          <i class="bi bi-eye"></i>
        </button>
      </div>
    </div>

    <div class="d-grid mb-3">
      <button type="submit" class="btn btn-primary fw-semibold">
        <i class="bi bi-box-arrow-in-right me-1"></i> Accedi
      </button>
    </div>

    <div class="text-center small">
      <a href="<?= APP_URL ?>/modules/auth/register.php">Registrati</a>
      &nbsp;|&nbsp;
      <a href="<?= APP_URL ?>/modules/auth/forgot.php">Password dimenticata?</a>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePass').addEventListener('click', function(){
  var p = document.querySelector('input[name=password]');
  p.type = p.type === 'password' ? 'text' : 'password';
  this.querySelector('i').classList.toggle('bi-eye');
  this.querySelector('i').classList.toggle('bi-eye-slash');
});
</script>
</body>
</html>
