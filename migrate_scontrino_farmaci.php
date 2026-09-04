<?php
/**
 * migrate_scontrino_farmaci.php
 * Aggiunge le colonne scontrino_file_nome, scontrino_file_mime, scontrino_file_dati
 * alla tabella farmaci (se non esistono già).
 * Da eseguire una volta sola: http://localhost/salute/migrate_scontrino_farmaci.php
 */
require_once __DIR__ . '/includes/config.php';

$lockFile = __DIR__ . '/migrate_scontrino_farmaci.lock';
if (file_exists($lockFile)) {
    die('<p style="font-family:sans-serif;color:green">✔ Migrazione già eseguita (lock file presente).</p>');
}

$errors = [];

$colonne = [
    'scontrino_file_nome' => "ADD COLUMN `scontrino_file_nome` VARCHAR(255) DEFAULT NULL AFTER `note`",
    'scontrino_file_mime' => "ADD COLUMN `scontrino_file_mime` VARCHAR(100)  DEFAULT NULL AFTER `scontrino_file_nome`",
    'scontrino_file_dati' => "ADD COLUMN `scontrino_file_dati` LONGBLOB      DEFAULT NULL AFTER `scontrino_file_mime`",
];

foreach ($colonne as $col => $ddl) {
    // Controlla se la colonna esiste già
    $chk = db()->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'farmaci' AND COLUMN_NAME = ?"
    );
    $chk->execute([DB_NAME, $col]);
    if ((int)$chk->fetchColumn() === 0) {
        try {
            db()->exec("ALTER TABLE `farmaci` $ddl");
        } catch (PDOException $e) {
            $errors[] = "Colonna $col: " . $e->getMessage();
        }
    }
}

if ($errors) {
    echo '<p style="font-family:sans-serif;color:red">✘ Errori:<br>' . implode('<br>', array_map('htmlspecialchars', $errors)) . '</p>';
} else {
    file_put_contents($lockFile, date('Y-m-d H:i:s'));
    echo '<p style="font-family:sans-serif;color:green;font-size:1.1rem">
            ✔ Migrazione completata. Colonne <strong>scontrino_file_nome / mime / dati</strong>
            aggiunte alla tabella <strong>farmaci</strong>.<br><br>
            <a href="' . APP_URL . '/modules/medications/">Vai ai Medicinali</a>
          </p>';
}
