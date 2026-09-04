<?php
/**
 * forgot.php
 * Richiesta reset password — invia e-mail con link sicuro.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$errore  = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errore = 'Indirizzo e-mail non valido.';
    } else {
        $stmt = db()->prepare('SELECT id, nome FROM utenti WHERE email = ? AND attivo = 1');
        $stmt->execute([$email]);
        $utente = $stmt->fetch();

        if ($utente) {
            $token  = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 ora
            db()->prepare('UPDATE utenti SET token_reset=?, token_reset_exp=? WHERE id=?')
               ->execute([$token, $expiry, $utente['id']]);

            $link    = APP_URL . '/modules/auth/reset.php?token=' . $token;
            $subject = 'Reset password — ' . APP_NAME;
            $body    = "Ciao {$utente['nome']},\n\nClicca il link per reimpostare la password (valido 1 ora):\n$link\n\nSe non hai richiesto il reset ignora questa e-mail.";
            @mail($email, $subject, $body, 'From: ' . MAIL_FROM . "\r\n");
        }
        // Messaggio generico per sicurezza (no user enumeration)
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8"><title>Password Dimenticata — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="auth-wrapper">
<div class="auth-card">
  <div class="auth-logo">
    <i class="bi bi-key-fill" style="font-size:3rem;color:#0078d4"></i>
    <h5 class="mt-2">Recupero Password</h5>
  </div>
  <?php if ($success): ?>
    <div class="alert alert-success">Se l'e-mail esiste riceverai il link per il reset entro qualche minuto.</div>
    <div class="text-center"><a href="login.php" class="btn btn-primary">Torna al Login</a></div>
  <?php else: ?>
    <?php if ($errore): ?>
    <div class="alert alert-danger py-2"><?= h($errore) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="mb-3">
        <label class="form-label fw-semibold">La tua E-mail</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="d-grid mb-3">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-send me-1"></i> Invia link di reset
        </button>
      </div>
      <div class="text-center small"><a href="login.php">Torna al Login</a></div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
