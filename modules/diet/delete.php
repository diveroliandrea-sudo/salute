<?php
/**
 * diet/delete.php
 * Elimina una misurazione peso/BMI.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) { header('Location: '.APP_URL.'/index.php'); exit; }

$id   = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT id, data, peso_kg FROM peso_diario WHERE id = ? AND membro_id = ?');
$stmt->execute([$id, $membro['id']]);
$riga = $stmt->fetch();

if ($riga) {
    db()->prepare('DELETE FROM peso_diario WHERE id = ?')->execute([$id]);
    setFlash('success', 'Misurazione del ' . dataITA($riga['data']) . ' (' . $riga['peso_kg'] . ' kg) eliminata.');
} else {
    setFlash('danger', 'Misurazione non trovata.');
}

header('Location: index.php'); exit;
