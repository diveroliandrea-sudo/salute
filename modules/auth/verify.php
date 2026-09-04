<?php
/**
 * verify.php
 * Verifica il token inviato per e-mail e attiva l'account.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';

$token = trim($_GET['token'] ?? '');
$msg   = '';
$tipo  = 'danger';

if ($token) {
    $stmt = db()->prepare('SELECT id FROM utenti WHERE token_conferma = ? AND email_verificata = 0');
    $stmt->execute([$token]);
    $utente = $stmt->fetch();
    if ($utente) {
        db()->prepare('UPDATE utenti SET email_verificata=1, token_conferma=NULL WHERE id=?')
           ->execute([$utente['id']]);
        $msg  = 'E-mail verificata con successo! Puoi ora accedere.';
        $tipo = 'success';
    } else {
        $msg = 'Token non valido o già utilizzato.';
    }
} else {
    $msg = 'Token mancante.';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8"><title>Verifica E-mail</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="auth-wrapper">
<div class="auth-card text-center">
  <div class="auth-logo"><i class="bi bi-heart-pulse-fill text-danger" style="font-size:3rem"></i></div>
  <div class="alert alert-<?= $tipo ?>"><?= h($msg) ?></div>
  <a href="<?= APP_URL ?>/modules/auth/login.php" class="btn btn-primary">Vai al Login</a>
</div>
</body>
</html>
