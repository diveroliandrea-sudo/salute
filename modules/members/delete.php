<?php
/**
 * members/delete.php
 * Eliminazione membro (con tutti i dati collegati via FK CASCADE).
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT id FROM membri_famiglia WHERE id=? AND utente_id=?');
$stmt->execute([$id, $_SESSION['utente_id']]);
if ($stmt->fetch()) {
    db()->prepare('DELETE FROM membri_famiglia WHERE id=?')->execute([$id]);
    if (($_SESSION['membro_id'] ?? 0) == $id) unset($_SESSION['membro_id']);
    setFlash('success', 'Membro eliminato.');
} else {
    setFlash('danger', 'Membro non trovato.');
}
header('Location: index.php'); exit;
