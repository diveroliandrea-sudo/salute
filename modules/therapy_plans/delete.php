<?php
/**
 * therapy_plans/delete.php
 * Eliminazione piano terapeutico (incluso il file BLOB via CASCADE non necessario — delete esplicita).
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT id, titolo FROM piani_terapeutici WHERE id = ? AND membro_id = ?');
$stmt->execute([$id, $membro['id']]);
$piano = $stmt->fetch();

if ($piano) {
    db()->prepare('DELETE FROM piani_terapeutici WHERE id = ?')->execute([$id]);
    setFlash('success', 'Piano «' . $piano['titolo'] . '» eliminato.');
} else {
    setFlash('danger', 'Piano terapeutico non trovato.');
}

header('Location: index.php');
exit;
