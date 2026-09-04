<?php
/**
 * members/add.php
 * Aggiunta nuovo membro della famiglia.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $nome    = trim($_POST['nome']    ?? '');
    $cognome = trim($_POST['cognome'] ?? '');

    if (!$nome || !$cognome) {
        $errore = 'Nome e Cognome sono obbligatori.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO membri_famiglia (utente_id, nome, cognome, data_nascita, sesso, codice_fiscale, relazione, medico_base, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $_SESSION['utente_id'],
            $nome,
            $cognome,
            $_POST['data_nascita'] ?: null,
            $_POST['sesso']         ?: null,
            strtoupper(trim($_POST['codice_fiscale'] ?? '')) ?: null,
            trim($_POST['relazione']   ?? '') ?: null,
            trim($_POST['medico_base'] ?? '') ?: null,
            trim($_POST['note']        ?? '') ?: null,
        ]);
        $newId = db()->lastInsertId();
        $_SESSION['membro_id'] = $newId;
        setFlash('success', 'Membro aggiunto correttamente.');
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

$pageTitle = 'Aggiungi Membro';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-person-plus me-2"></i>Aggiungi Membro</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Indietro</a>
</div>

<div class="row justify-content-center">
<div class="col-md-8">
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-person"></i> Dati Membro</div>
  <div class="win-card-body">
    <?php if ($errore): ?>
    <div class="alert alert-danger py-2"><?= h($errore) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Nome *</label>
          <input type="text" name="nome" class="form-control" value="<?= h($_POST['nome'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Cognome *</label>
          <input type="text" name="cognome" class="form-control" value="<?= h($_POST['cognome'] ?? '') ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data di Nascita</label>
          <input type="date" name="data_nascita" class="form-control" value="<?= h($_POST['data_nascita'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Sesso</label>
          <select name="sesso" class="form-select">
            <option value="">— Seleziona —</option>
            <option value="M" <?= (($_POST['sesso'] ?? '') === 'M') ? 'selected' : '' ?>>Maschio</option>
            <option value="F" <?= (($_POST['sesso'] ?? '') === 'F') ? 'selected' : '' ?>>Femmina</option>
            <option value="Altro" <?= (($_POST['sesso'] ?? '') === 'Altro') ? 'selected' : '' ?>>Altro</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Relazione</label>
          <input type="text" name="relazione" class="form-control" placeholder="es. Figlio, Coniuge"
                 value="<?= h($_POST['relazione'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Codice Fiscale</label>
          <input type="text" name="codice_fiscale" class="form-control" maxlength="16"
                 value="<?= h($_POST['codice_fiscale'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Medico di Base</label>
          <input type="text" name="medico_base" class="form-control"
                 value="<?= h($_POST['medico_base'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Note</label>
          <textarea name="note" class="form-control" rows="3"><?= h($_POST['note'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salva</button>
        <a href="index.php" class="btn btn-outline-secondary">Annulla</a>
      </div>
    </form>
  </div>
</div>
</div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
