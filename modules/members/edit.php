<?php
/**
 * members/edit.php
 * Modifica dati membro della famiglia.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM membri_famiglia WHERE id=? AND utente_id=?');
$stmt->execute([$id, $_SESSION['utente_id']]);
$m = $stmt->fetch();
if (!$m) { setFlash('danger','Membro non trovato.'); header('Location: index.php'); exit; }

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $nome    = trim($_POST['nome']    ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    if (!$nome || !$cognome) {
        $errore = 'Nome e Cognome sono obbligatori.';
    } else {
        db()->prepare(
            'UPDATE membri_famiglia SET nome=?, cognome=?, data_nascita=?, sesso=?, codice_fiscale=?,
             relazione=?, medico_base=?, note=?, updated_at=NOW() WHERE id=?'
        )->execute([
            $nome, $cognome,
            $_POST['data_nascita'] ?: null,
            $_POST['sesso']         ?: null,
            strtoupper(trim($_POST['codice_fiscale'] ?? '')) ?: null,
            trim($_POST['relazione']   ?? '') ?: null,
            trim($_POST['medico_base'] ?? '') ?: null,
            trim($_POST['note']        ?? '') ?: null,
            $id
        ]);
        setFlash('success', 'Membro aggiornato.');
        header('Location: index.php'); exit;
    }
}

$pageTitle = 'Modifica Membro';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-person-gear me-2"></i>Modifica Membro</h1>
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Indietro</a>
</div>
<div class="row justify-content-center">
<div class="col-md-8">
<div class="win-card">
  <div class="win-card-header"><i class="bi bi-person"></i> <?= h($m['nome'].' '.$m['cognome']) ?></div>
  <div class="win-card-body">
    <?php if ($errore): ?><div class="alert alert-danger py-2"><?= h($errore) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Nome *</label>
          <input type="text" name="nome" class="form-control" value="<?= h($m['nome']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Cognome *</label>
          <input type="text" name="cognome" class="form-control" value="<?= h($m['cognome']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Data di Nascita</label>
          <input type="date" name="data_nascita" class="form-control" value="<?= h($m['data_nascita'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Sesso</label>
          <select name="sesso" class="form-select">
            <option value="">— Seleziona —</option>
            <?php foreach (['M'=>'Maschio','F'=>'Femmina','Altro'=>'Altro'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= $m['sesso']==$v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Relazione</label>
          <input type="text" name="relazione" class="form-control" value="<?= h($m['relazione'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Codice Fiscale</label>
          <input type="text" name="codice_fiscale" class="form-control" maxlength="16" value="<?= h($m['codice_fiscale'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Medico di Base</label>
          <input type="text" name="medico_base" class="form-control" value="<?= h($m['medico_base'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Note</label>
          <textarea name="note" class="form-control" rows="3"><?= h($m['note'] ?? '') ?></textarea>
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
