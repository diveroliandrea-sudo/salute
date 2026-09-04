<?php
/**
 * documents/index.php — Archivio documenti del membro selezionato
 * Mostra tutti i file allegati da ogni modulo in un'unica vista.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();
$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }
$mid = $membro['id'];

// Raccoglie tutti i documenti da ogni modulo
$docs = [];

// Visite
$s = db()->prepare('SELECT id, data_visita AS data, CONCAT(tipo," — ",COALESCE(specialita,"")) AS titolo,
    file_nome, file_mime, "visita" AS modulo FROM visite_specialistiche WHERE membro_id=? AND file_nome IS NOT NULL ORDER BY data_visita DESC');
$s->execute([$mid]); foreach ($s->fetchAll() as $r) $docs[] = $r;

// Impegnative
$s = db()->prepare('SELECT id, data_prescrizione AS data, CONCAT("Impegnativa — ",COALESCE(prestazione,"")) AS titolo,
    file_nome, file_mime, "impegnativa" AS modulo FROM impegnative WHERE membro_id=? AND file_nome IS NOT NULL ORDER BY data_prescrizione DESC');
$s->execute([$mid]); foreach ($s->fetchAll() as $r) $docs[] = $r;

// Ricoveri (lettera dimissione)
$s = db()->prepare('SELECT id, data_ingresso AS data, CONCAT("Lettera dim. — ",struttura) AS titolo,
    lettera_file_nome AS file_nome, lettera_file_mime AS file_mime, "ricovero" AS modulo
    FROM ricoveri WHERE membro_id=? AND lettera_file_nome IS NOT NULL ORDER BY data_ingresso DESC');
$s->execute([$mid]); foreach ($s->fetchAll() as $r) $docs[] = $r;

// Invalidità
$s = db()->prepare('SELECT id, data_verbale AS data, tipo AS titolo,
    file_nome, file_mime, "invalidita" AS modulo FROM invalidita WHERE membro_id=? AND file_nome IS NOT NULL ORDER BY data_verbale DESC');
$s->execute([$mid]); foreach ($s->fetchAll() as $r) $docs[] = $r;

// Piani Terapeutici
$s = db()->prepare('SELECT id, data_inizio AS data,
    CONCAT("Piano — ", titolo) AS titolo,
    file_nome, file_mime, "piano_terapeutico" AS modulo
    FROM piani_terapeutici WHERE membro_id=? AND file_nome IS NOT NULL ORDER BY data_inizio DESC');
$s->execute([$mid]); foreach ($s->fetchAll() as $r) $docs[] = $r;

// Scontrini farmaci
$s = db()->prepare('SELECT id, data_inizio AS data,
    CONCAT("Scontrino — ", nome_farmaco) AS titolo,
    scontrino_file_nome AS file_nome, scontrino_file_mime AS file_mime, "farmaco" AS modulo
    FROM farmaci WHERE membro_id=? AND scontrino_file_nome IS NOT NULL ORDER BY data_inizio DESC');
$s->execute([$mid]); foreach ($s->fetchAll() as $r) $docs[] = $r;

// Ordina per data desc
usort($docs, fn($a,$b) => strcmp($b['data']??'', $a['data']??''));

$pageTitle = 'Documenti';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-paperclip me-2"></i>Archivio Documenti — <?= h($membro['nome'].' '.$membro['cognome']) ?></h1>
</div>
<div class="win-card"><div class="win-card-body p-0">
  <table class="table table-hover mb-0 dt-table">
    <thead>
      <tr><th>Data</th><th>Documento</th><th>Modulo</th><th>Tipo File</th><th>Azioni</th></tr>
    </thead>
    <tbody>
      <?php foreach ($docs as $d): ?>
      <tr>
        <td><?= dataITA($d['data']) ?></td>
        <td><?= h($d['titolo']) ?></td>
        <td>
          <?php $mc=['visita'=>'primary','impegnativa'=>'info','ricovero'=>'warning',
                    'invalidita'=>'secondary','piano_terapeutico'=>'success','farmaco'=>'danger']; ?>
          <span class="badge bg-<?= $mc[$d['modulo']]??'dark' ?>"><?= h(match($d['modulo']) {
            'visita'            => 'Visita',
            'impegnativa'       => 'Impegnativa',
            'ricovero'          => 'Ricovero',
            'invalidita'        => 'Invalidità',
            'piano_terapeutico' => 'Piano Terap.',
            'farmaco'           => 'Scontrino',
            default             => ucfirst($d['modulo']),
          }) ?></span>
        </td>
        <td>
          <?php if (str_contains($d['file_mime']??'','pdf')): ?>
          <i class="bi bi-file-earmark-pdf text-danger"></i> PDF
          <?php else: ?>
          <i class="bi bi-file-earmark-image text-info"></i> Immagine
          <?php endif; ?>
          <small class="text-muted ms-1"><?= h($d['file_nome']) ?></small>
        </td>
        <td>
          <div class="d-flex gap-1">
            <a href="view.php?modulo=<?= h($d['modulo']) ?>&id=<?= $d['id'] ?>"
               target="_blank" class="btn btn-sm btn-outline-info">
              <i class="bi bi-eye me-1"></i>Visualizza
            </a>
            <a href="download.php?modulo=<?= h($d['modulo']) ?>&id=<?= $d['id'] ?>"
               class="btn btn-sm btn-outline-secondary">
              <i class="bi bi-download me-1"></i>Scarica
            </a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($docs)): ?>
      <tr><td colspan="5" class="text-center text-muted py-4">Nessun documento allegato.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div></div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
