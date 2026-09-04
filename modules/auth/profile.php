<?php
/**
 * profile.php
 * Modifica dati profilo utente e cambio password.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$utente = db()->prepare('SELECT * FROM utenti WHERE id=?');
$utente->execute([$_SESSION['utente_id']]);
$utente = $utente->fetch();

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $nome    = trim($_POST['nome']    ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $pw_old  = $_POST['pw_old']  ?? '';
    $pw_new  = $_POST['pw_new']  ?? '';
    $pw_new2 = $_POST['pw_new2'] ?? '';

    if (!$nome || !$cognome) {
        $errore = 'Nome e Cognome sono obbligatori.';
    } else {
        db()->prepare('UPDATE utenti SET nome=?, cognome=?, updated_at=NOW() WHERE id=?')
           ->execute([$nome, $cognome, $_SESSION['utente_id']]);
        $_SESSION['utente_nome'] = $nome;

        // Cambio password opzionale
        if ($pw_new) {
            if (!password_verify($pw_old, $utente['password_hash'])) {
                $errore = 'La password attuale non è corretta.';
            } elseif (strlen($pw_new) < 8) {
                $errore = 'La nuova password deve avere almeno 8 caratteri.';
            } elseif ($pw_new !== $pw_new2) {
                $errore = 'Le nuove password non coincidono.';
            } else {
                db()->prepare('UPDATE utenti SET password_hash=? WHERE id=?')
                   ->execute([password_hash($pw_new, PASSWORD_DEFAULT), $_SESSION['utente_id']]);
            }
        }
        if (!$errore) {
            setFlash('success', 'Profilo aggiornato correttamente.');
            header('Location: profile.php');
            exit;
        }
    }
}

$pageTitle = 'Profilo';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-person-circle me-2"></i>Profilo Utente</h1>
</div>

<div class="row g-4">
  <div class="col-md-6">
    <div class="win-card">
      <div class="win-card-header"><i class="bi bi-person"></i> Dati Personali</div>
      <div class="win-card-body">
        <?php if ($errore): ?>
        <div class="alert alert-danger py-2"><?= h($errore) ?></div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nome *</label>
            <input type="text" name="nome" class="form-control" value="<?= h($utente['nome']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Cognome *</label>
            <input type="text" name="cognome" class="form-control" value="<?= h($utente['cognome']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">E-mail</label>
            <input type="email" class="form-control" value="<?= h($utente['email']) ?>" readonly disabled>
          </div>
          <hr>
          <h6 class="text-muted">Cambia Password <small>(lascia vuoto per non modificare)</small></h6>
          <div class="mb-2">
            <label class="form-label">Password Attuale</label>
            <input type="password" name="pw_old" class="form-control">
          </div>
          <div class="mb-2">
            <label class="form-label">Nuova Password</label>
            <input type="password" name="pw_new" class="form-control" minlength="8">
          </div>
          <div class="mb-3">
            <label class="form-label">Conferma Nuova Password</label>
            <input type="password" name="pw_new2" class="form-control">
          </div>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i> Salva Modifiche
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
