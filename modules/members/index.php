<?php
/**
 * members/index.php
 * Elenco membri della famiglia.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membri = db()->prepare('SELECT * FROM membri_famiglia WHERE utente_id=? ORDER BY nome, cognome');
$membri->execute([$_SESSION['utente_id']]);
$membri = $membri->fetchAll();

$pageTitle = 'Membri Famiglia';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-people-fill me-2"></i>Membri della Famiglia</h1>
  <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Aggiungi</a>
</div>

<div class="row g-3">
<?php foreach ($membri as $m): ?>
<div class="col-sm-6 col-lg-4">
  <div class="win-card h-100">
    <div class="win-card-body">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="avatar-circle" style="width:50px;height:50px;font-size:1.2rem">
          <?= mb_strtoupper(mb_substr($m['nome'],0,1).mb_substr($m['cognome'],0,1)) ?>
        </div>
        <div>
          <div class="fw-bold"><?= h($m['nome'].' '.$m['cognome']) ?></div>
          <?php if ($m['relazione']): ?>
          <span class="badge bg-secondary"><?= h($m['relazione']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <ul class="list-unstyled small text-muted mb-3">
        <?php if ($m['data_nascita']): ?><li><i class="bi bi-calendar3 me-1"></i><?= dataITA($m['data_nascita']) ?></li><?php endif; ?>
        <?php if ($m['sesso']): ?><li><i class="bi bi-gender-ambiguous me-1"></i><?= h($m['sesso']) ?></li><?php endif; ?>
        <?php if ($m['codice_fiscale']): ?><li><i class="bi bi-card-text me-1"></i><?= h($m['codice_fiscale']) ?></li><?php endif; ?>
        <?php if ($m['medico_base']): ?><li><i class="bi bi-person-badge me-1"></i><?= h($m['medico_base']) ?></li><?php endif; ?>
      </ul>
      <div class="d-flex gap-2">
        <a href="<?= APP_URL ?>/index.php?set_membro=<?= $m['id'] ?>" class="btn btn-sm btn-primary flex-grow-1">
          <i class="bi bi-eye"></i> Seleziona
        </a>
        <a href="edit.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-pencil"></i>
        </a>
        <a href="delete.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger btn-confirm-delete">
          <i class="bi bi-trash"></i>
        </a>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if (empty($membri)): ?>
<div class="col-12">
  <div class="alert alert-info">Nessun membro registrato. <a href="add.php">Aggiungi il primo membro</a>.</div>
</div>
<?php endif; ?>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
