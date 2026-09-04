<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();
$membro = membroSelezionato();
if (!$membro) { header('Location: '.APP_URL.'/index.php'); exit; }
$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT id FROM ricoveri WHERE id=? AND membro_id=?');
$stmt->execute([$id, $membro['id']]);
if ($stmt->fetch()) {
    db()->prepare('DELETE FROM ricoveri WHERE id=?')->execute([$id]);
    setFlash('success', 'Ricovero eliminato.');
} else { setFlash('danger', 'Ricovero non trovato.'); }
header('Location: index.php'); exit;
