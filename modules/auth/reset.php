<?php
/**
 * reset.php
 * Reimpostazione password tramite token.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$token  = trim($_GET['token'] ?? '');
$errore = '';
$done   = false;

// Verifica token
$stmt = db()->prepare('SELECT id, nome FROM utenti WHERE token_reset = ? AND token_reset_exp > NOW()');
$stmt->execute([$token]);
$utente = $stmt->fetch();

if (!$utente) {
    $errore = 'Link non valido o scaduto. Richiedi un nuovo reset.';
}

if (!$errore && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $p1 = $_POST['password']  ?? '';
    $p2 = $_POST['password2'] ?? '';

    if (strlen($p1) < 8) {
        $errore = 'La password deve essere di almeno 8 caratteri.';
    } elseif ($p1 !== $p2) {
        $errore = 'Le password non coincidono.';
    } else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        db()->prepare('UPDATE utenti SET password_hash=?, token_reset=NULL, token_reset_exp=NULL WHERE id=?')
           ->execute([$hash, $utente['id']]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8"><title>Reset Password — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="auth-wrapper">
<div class="auth-card">
  <h5 class="text-center mb-3">Nuova Password</h5>
  <?php if ($done): ?>
    <div class="alert alert-success">Password aggiornata con successo!</div>
    <div class="text-center"><a href="login.php" class="btn btn-primary">Accedi ora</a></div>
  <?php elseif ($errore): ?>
    <div class="alert alert-danger"><?= h($errore) ?></div>
    <div class="text-center"><a href="forgot.php" class="btn btn-secondary">Richiedi nuovo link</a></div>
  <?php else: ?>
    <form method="POST" action="?token=<?= h($token) ?>">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="mb-3">
        <label class="form-label fw-semibold">Nuova Password</label>
        <input type="password" name="password" class="form-control" required minlength="8">
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Conferma Password</label>
        <input type="password" name="password2" class="form-control" required>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-primary">Salva nuova password</button>
      </div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
