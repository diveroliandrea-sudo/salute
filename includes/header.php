<?php
/**
 * header.php
 * Intestazione HTML comune a tutte le pagine protette.
 * Richiede che $pageTitle sia definita nel file chiamante.
 */
if (!defined('APP_NAME')) require_once dirname(__DIR__) . '/includes/config.php';
$flash  = getFlash();
$membro = membroSelezionato();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle) ?> — <?= APP_NAME ?></title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="app-body">

<!-- ── TOP NAVBAR ───────────────────────────────────────────── -->
<nav class="navbar navbar-expand-lg navbar-dark app-navbar px-3 py-0">
  <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= APP_URL ?>/index.php">
    <i class="bi bi-heart-pulse-fill text-danger"></i>
    <?= APP_NAME ?>
  </a>

  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="navMain">
    <ul class="navbar-nav me-auto">
      <?php if (!empty($_SESSION['utente_id'])): ?>
      <!-- Selettore membro -->
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" data-bs-toggle="dropdown">
          <i class="bi bi-people-fill"></i>
          <?= $membro ? h($membro['nome'] . ' ' . $membro['cognome']) : 'Seleziona membro' ?>
        </a>
        <ul class="dropdown-menu">
          <?php
            $membri = db()->prepare('SELECT id,nome,cognome FROM membri_famiglia WHERE utente_id=? ORDER BY nome');
            $membri->execute([$_SESSION['utente_id']]);
            foreach ($membri->fetchAll() as $m):
          ?>
          <li>
            <a class="dropdown-item <?= ($membro && $membro['id']==$m['id']) ? 'active' : '' ?>"
               href="<?= APP_URL ?>/index.php?set_membro=<?= $m['id'] ?>">
              <?= h($m['nome'] . ' ' . $m['cognome']) ?>
            </a>
          </li>
          <?php endforeach; ?>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item text-primary" href="<?= APP_URL ?>/modules/members/add.php">
              <i class="bi bi-plus-circle"></i> Aggiungi membro
            </a>
          </li>
        </ul>
      </li>
      <?php endif; ?>
    </ul>

    <?php if (!empty($_SESSION['utente_id'])): ?>
    <ul class="navbar-nav ms-auto align-items-center">
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle"></i>
          <?= h($_SESSION['utente_nome'] ?? 'Utente') ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/auth/profile.php"><i class="bi bi-person"></i> Profilo</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/modules/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Esci</a></li>
        </ul>
      </li>
    </ul>
    <?php endif; ?>
  </div>
</nav>

<!-- ── WRAPPER ──────────────────────────────────────────────── -->
<div class="d-flex">
<?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>
<main class="app-main flex-grow-1 p-4">

<!-- Flash message -->
<?php if ($flash): ?>
<div class="alert alert-<?= h($flash['tipo']) ?> alert-dismissible fade show" role="alert">
  <?= h($flash['msg']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
