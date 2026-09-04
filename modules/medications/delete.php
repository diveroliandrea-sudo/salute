<?php
/**
 * medications/delete.php
 * Eliminazione farmaco (elimina anche gli orari via FK CASCADE).
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();
$membro = membroSelezionato();
if (!$membro) { header('Location: '.APP_URL.'/index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT id, nome_farmaco FROM farmaci WHERE id=? AND membro_id=?');
$stmt->execute([$id, $membro['id']]);
$f = $stmt->fetch();
if ($f) {
    db()->prepare('DELETE FROM farmaci WHERE id=?')->execute([$id]);
    setFlash('success', 'Farmaco «'.$f['nome_farmaco'].'» eliminato.');
} else {
    setFlash('danger', 'Farmaco non trovato.');
}
header('Location: index.php'); exit;
