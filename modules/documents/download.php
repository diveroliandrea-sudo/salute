<?php
/**
 * documents/download.php
 * Reindirizza a view.php con mode=download per forzare il download sicuro.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$modulo = $_GET['modulo'] ?? '';
$id     = (int)($_GET['id'] ?? 0);
$campo  = $_GET['campo']  ?? 'file';

header('Location: ' . APP_URL . '/modules/documents/view.php?modulo=' . urlencode($modulo) . '&id=' . $id . '&campo=' . urlencode($campo) . '&mode=download');
exit;
