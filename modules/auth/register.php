<?php
/**
 * register.php
 * Registrazione nuovo utente con invio e-mail di conferma.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

if (!empty($_SESSION['utente_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$errore  = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $nome     = trim($_POST['nome']     ?? '');
    $cognome  = trim($_POST['cognome']  ?? '');
    $email    = strtolower(trim($_POST['email']    ?? ''));
    $password = $_POST['password'] ?? '';
    $password2= $_POST['password2'] ?? '';

    // Validazione
    if (!$nome || !$cognome || !$email || !$password) {
        $errore = 'Compila tutti i campi obbligatori.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errore = 'Indirizzo e-mail non valido.';
    } elseif (strlen($password) < 8) {
        $errore = 'La password deve essere di almeno 8 caratteri.';
    } elseif ($password !== $password2) {
        $errore = 'Le password non coincidono.';
    } else {
        // Verifica unicità e-mail
        $check = db()->prepare('SELECT id FROM utenti WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errore = 'L\'indirizzo e-mail è già registrato.';
        } else {
            $token = bin2hex(random_bytes(32));
            $hash  = password_hash($password, PASSWORD_DEFAULT);

            $ins = db()->prepare(
                'INSERT INTO utenti (nome, cognome, email, password_hash, token_conferma, email_verificata)
                 VALUES (?, ?, ?, ?, ?, 0)'
            );
            $ins->execute([$nome, $cognome, $email, $hash, $token]);

            // ── Invio e-mail di conferma ──────────────────────────
            // In ambiente locale XAMPP sostituire con PHPMailer o SMTP reale.
            $link    = APP_URL . '/modules/auth/verify.php?token=' . $token;
            $subject = 'Conferma registrazione — ' . APP_NAME;
            $body    = "Ciao $nome,\n\nClicca il link per attivare il tuo account:\n$link\n\nSe non hai effettuato la registrazione ignora questa e-mail.";
            $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>' . "\r\n";
            @mail($email, $subject, $body, $headers);

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrazione — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="auth-wrapper">
<div class="auth-card" style="max-width:480px">
  <div class="auth-logo">
    <i class="bi bi-heart-pulse-fill text-danger"></i>
    <h4 class="mt-2 text-win-blue fw-bold"><?= APP_NAME ?></h4>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success">
      <i class="bi bi-check-circle-fill me-2"></i>
      Registrazione completata! Controlla la tua e-mail per attivare l'account.<br>
      <small class="text-muted">(In locale: vai direttamente a
        <a href="<?= APP_URL ?>/modules/auth/login.php">Login</a>
        dopo aver verificato manualmente nel DB <code>email_verificata=1</code>)</small>
    </div>
    <div class="text-center mt-3">
      <a href="<?= APP_URL ?>/modules/auth/login.php" class="btn btn-primary">Vai al Login</a>
    </div>
  <?php else: ?>

    <?php if ($errore): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= h($errore) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div class="row g-2 mb-2">
        <div class="col">
          <label class="form-label fw-semibold">Nome *</label>
          <input type="text" name="nome" class="form-control" value="<?= h($_POST['nome'] ?? '') ?>" required>
        </div>
        <div class="col">
          <label class="form-label fw-semibold">Cognome *</label>
          <input type="text" name="cognome" class="form-control" value="<?= h($_POST['cognome'] ?? '') ?>" required>
        </div>
      </div>

      <div class="mb-2">
        <label class="form-label fw-semibold">E-mail *</label>
        <input type="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>" required>
      </div>

      <div class="mb-2">
        <label class="form-label fw-semibold">Password * <small class="text-muted">(min. 8 caratteri)</small></label>
        <input type="password" name="password" class="form-control" required minlength="8">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Conferma Password *</label>
        <input type="password" name="password2" class="form-control" required>
      </div>

      <div class="d-grid mb-3">
        <button type="submit" class="btn btn-primary fw-semibold">
          <i class="bi bi-person-plus me-1"></i> Registrati
        </button>
      </div>
      <div class="text-center small">
        Hai già un account? <a href="<?= APP_URL ?>/modules/auth/login.php">Accedi</a>
      </div>
    </form>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
