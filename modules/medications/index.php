<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();

$membro = membroSelezionato();
if (!$membro) {
    setFlash('warning', 'Seleziona prima un membro.');
    header('Location: ' . APP_URL . '/index.php');
    exit;
}
$mid = $membro['id'];

// Crea tabella se non esiste
db()->exec("CREATE TABLE IF NOT EXISTS `farmaci_somministrazioni` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `farmaco_id` INT UNSIGNED NOT NULL,
  `data_ora`   DATETIME     NOT NULL,
  `quantita`   VARCHAR(50)  NOT NULL DEFAULT '1',
  `modalita`   VARCHAR(100) DEFAULT NULL,
  `zona`       VARCHAR(100) DEFAULT NULL,
  `note`       VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fs_farmaco` (`farmaco_id`),
  CONSTRAINT `fk_fs_farmaco` FOREIGN KEY (`farmaco_id`) REFERENCES `farmaci` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Aggiungi colonne modalita/zona se la tabella esisteva già senza di esse
foreach (['modalita VARCHAR(100) DEFAULT NULL', 'zona VARCHAR(100) DEFAULT NULL'] as $colDef) {
    $col = explode(' ', $colDef)[0];
    try { db()->exec("ALTER TABLE `farmaci_somministrazioni` ADD COLUMN `$col` $colDef"); }
    catch (PDOException $e) { /* colonna già presente */ }
}

// Farmaci
$stmtF = db()->prepare(
    'SELECT * FROM farmaci WHERE membro_id=? ORDER BY attivo DESC, nome_farmaco ASC'
);
$stmtF->execute([$mid]);
$farmaci = $stmtF->fetchAll();

// Somministrazioni per farmaco
$somPerFarmaco = [];
if ($farmaci) {
    $ids = implode(',', array_column($farmaci, 'id'));
    $rows = db()->query(
        "SELECT * FROM farmaci_somministrazioni
         WHERE farmaco_id IN ($ids)
         ORDER BY data_ora DESC"
    )->fetchAll();
    foreach ($rows as $r) {
        $somPerFarmaco[(int)$r['farmaco_id']][] = $r;
    }
}

$pageTitle = 'Medicinali';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- MODALE: Aggiungi Farmaco -->
<div class="modal fade" id="modalAddFarmaco" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--win-blue);color:#fff">
        <h5 class="modal-title"><i class="bi bi-capsule me-2"></i>Aggiungi Farmaco</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="alertAddFarmaco" class="alert alert-danger d-none py-2"></div>
        <form id="formAddFarmaco" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="add_farmaco">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Nome Farmaco *</label>
              <input type="text" name="nome_farmaco" class="form-control" required placeholder="es. Mounjaro 2.5">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Principio Attivo</label>
              <input type="text" name="principio_attivo" class="form-control" placeholder="es. Tirzepatide">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Dosaggio</label>
              <input type="text" name="dosaggio" class="form-control" placeholder="es. 2.5mg">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Forma</label>
              <select name="forma" class="form-select">
                <option value="">— Seleziona —</option>
                <?php foreach (['Compressa','Capsula','Sciroppo','Fiala','Gocce','Crema','Supposte','Spray','Cerotto','Penna','Altro'] as $fo): ?>
                <option value="<?= $fo ?>"><?= $fo ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Ogni quanto</label>
              <input type="text" name="note_assunzione" class="form-control" placeholder="es. 1 volta a settimana">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Note</label>
              <textarea name="note" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">
                <i class="bi bi-paperclip me-1"></i>Scontrino / Prescrizione
              </label>
              <input type="file" name="scontrino" id="m_scontrino" class="form-control"
                     accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
              <div class="form-text">Opzionale. PDF, JPG, PNG, GIF, WEBP — max <?= MAX_UPLOAD_MB ?> MB.</div>
              <div id="m_scontrino_preview" class="mt-1 small text-muted"></div>
            </div>
            <div class="col-md-4 d-flex align-items-center">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="cronico" id="m_cronico" value="1">
                <label class="form-check-label fw-semibold" for="m_cronico">Terapia Cronica</label>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-primary" id="btnSalvaFarmaco">
          <i class="bi bi-save me-1"></i>Salva Farmaco
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODALE: Registra Somministrazione -->
<div class="modal fade" id="modalSomm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-check2-circle me-2"></i>Registra Somministrazione</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="alertSomm" class="alert alert-danger d-none py-2"></div>
        <form id="formSomm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="action" value="add_somministrazione">
          <input type="hidden" name="farmaco_id" id="somFarmacoId">
          <p class="fw-semibold mb-3" id="somFarmacoNome"></p>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Data e Ora *</label>
              <input type="datetime-local" name="data_ora" id="somDataOra" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Quantità / Dose</label>
              <input type="text" name="quantita" class="form-control" value="1" placeholder="es. 1, 2 compresse, 1 penna">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Modalità</label>
              <select name="modalita" id="somModalita" class="form-select">
                <option value="">— Seleziona —</option>
                <option value="Orale">Orale</option>
                <option value="Iniezione sottocutanea">Iniezione sottocutanea</option>
                <option value="Iniezione intramuscolare">Iniezione intramuscolare</option>
                <option value="Iniezione endovenosa">Iniezione endovenosa</option>
                <option value="Penna autoiniettante">Penna autoiniettante</option>
                <option value="Topica / Crema">Topica / Crema</option>
                <option value="Spray nasale">Spray nasale</option>
                <option value="Sublinguale">Sublinguale</option>
                <option value="Cerotto">Cerotto</option>
                <option value="Altro">Altro</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Zona corporea</label>
              <input type="text" name="zona" id="somZona" class="form-control"
                     placeholder="es. addome, coscia sinistra">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Note</label>
              <input type="text" name="note" class="form-control" placeholder="Opzionale">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
        <button type="button" class="btn btn-success" id="btnSalvaSomm">
          <i class="bi bi-check2 me-1"></i>Salva
        </button>
      </div>
    </div>
  </div>
</div>

<!-- PAGINA -->
<div class="page-titlebar">
  <h1><i class="bi bi-capsule me-2"></i>Medicinali — <?= h($membro['nome'] . ' ' . $membro['cognome']) ?></h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddFarmaco">
    <i class="bi bi-plus-circle me-1"></i>Aggiungi Farmaco
  </button>
</div>

<?php if (empty($farmaci)): ?>
<div class="win-card">
  <div class="win-card-body text-center text-muted py-5">
    <i class="bi bi-capsule fs-1 opacity-25 d-block mb-3"></i>
    <p class="mb-3">Nessun farmaco registrato.</p>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddFarmaco">
      <i class="bi bi-plus-circle me-1"></i>Aggiungi il primo farmaco
    </button>
  </div>
</div>

<?php else: ?>

<div class="farm-tree" id="farmTree">
<?php foreach ($farmaci as $farm):
    $fid    = (int)$farm['id'];
    $somm   = $somPerFarmaco[$fid] ?? [];
    $nSomm  = count($somm);
    $nodeId = 'farm_' . $fid;
    $ultima = $nSomm ? date('d/m/Y', strtotime($somm[0]['data_ora'])) : null;
?>
<div class="farm-tree-node <?= $farm['attivo'] ? '' : 'farm-tree-node--inactive' ?>" id="node_<?= $fid ?>">

  <!-- Riga principale del nodo -->
  <div class="farm-tree-header d-flex align-items-center gap-2 flex-wrap">

    <!-- Parte sinistra: cliccabile per espandere -->
    <div class="d-flex align-items-center gap-2 flex-grow-1"
         data-bs-toggle="collapse"
         data-bs-target="#<?= $nodeId ?>"
         style="cursor:pointer;min-width:0">
      <i class="bi bi-chevron-right farm-chevron flex-shrink-0"></i>
      <i class="bi bi-capsule flex-shrink-0 <?= $farm['attivo'] ? 'text-primary' : 'text-secondary' ?>"></i>
      <span class="fw-semibold"><?= h($farm['nome_farmaco']) ?></span>
      <?php if ($farm['principio_attivo']): ?>
      <span class="text-muted small"><?= h($farm['principio_attivo']) ?></span>
      <?php endif; ?>
      <?php if ($farm['dosaggio']): ?>
      <span class="badge bg-light text-dark border"><?= h($farm['dosaggio']) ?></span>
      <?php endif; ?>
      <?php if ($farm['note_assunzione']): ?>
      <span class="badge bg-light text-dark border">
        <i class="bi bi-arrow-repeat me-1"></i><?= h($farm['note_assunzione']) ?>
      </span>
      <?php endif; ?>
      <?php if ($nSomm): ?>
      <span class="badge bg-warning text-dark">
        <i class="bi bi-clock me-1"></i>ultima: <?= $ultima ?>
      </span>
      <?php endif; ?>
    </div>

    <!-- Parte destra: azioni — NON propagano al collapse -->
    <div class="d-flex align-items-center gap-1 flex-shrink-0">
      <button type="button"
              class="btn btn-xs btn-toggle-attivo <?= $farm['attivo'] ? 'btn-success' : 'btn-secondary' ?>"
              data-id="<?= $fid ?>"
              data-attivo="<?= $farm['attivo'] ?>">
        <i class="bi <?= $farm['attivo'] ? 'bi-check-circle-fill' : 'bi-pause-circle-fill' ?> me-1"></i><?= $farm['attivo'] ? 'Attivo' : 'Sospeso' ?>
      </button>
      <button type="button"
              class="btn btn-xs btn-success"
              data-bs-toggle="modal"
              data-bs-target="#modalSomm"
              data-farmaco-id="<?= $fid ?>"
              data-farmaco-nome="<?= h($farm['nome_farmaco']) ?>">
        <i class="bi bi-plus-circle me-1"></i>Registra
      </button>
      <a href="edit.php?id=<?= $fid ?>" class="btn btn-xs btn-outline-secondary" title="Impostazioni">
        <i class="bi bi-gear"></i>
      </a>
      <a href="delete.php?id=<?= $fid ?>" class="btn btn-xs btn-outline-danger btn-confirm-delete" title="Elimina">
        <i class="bi bi-trash"></i>
      </a>
    </div>

  </div><!-- /farm-tree-header -->

  <!-- Contenuto espandibile: solo le date -->
  <div class="collapse" id="<?= $nodeId ?>">
    <div class="farm-tree-content" id="somm_list_<?= $fid ?>">

      <?php if (empty($somm)): ?>
      <p class="text-muted small mb-0 somm-empty-msg">
        <i class="bi bi-info-circle me-1"></i>Nessuna somministrazione registrata ancora.
        <button type="button"
                class="btn btn-link btn-sm p-0 ms-1"
                data-bs-toggle="modal"
                data-bs-target="#modalSomm"
                data-farmaco-id="<?= $fid ?>"
                data-farmaco-nome="<?= h($farm['nome_farmaco']) ?>">Registra ora</button>
      </p>
      <?php else: ?>
      <table class="table table-sm table-hover mb-0" id="somm_table_<?= $fid ?>">
        <thead class="table-light">
          <tr>
            <th>Data e Ora</th>
            <th style="width:80px" class="text-center">Quantità</th>
            <th>Modalità</th>
            <th>Zona</th>
            <th>Note</th>
            <th style="width:40px"></th>
          </tr>
        </thead>
        <tbody id="somm_tbody_<?= $fid ?>">
          <?php foreach ($somm as $s): ?>
          <tr id="somm_row_<?= $s['id'] ?>">
            <td><?= date('d/m/Y H:i', strtotime($s['data_ora'])) ?></td>
            <td class="text-center"><?= h($s['quantita']) ?></td>
            <td class="small"><?= h($s['modalita'] ?? '') ?></td>
            <td class="small text-muted"><?= h($s['zona'] ?? '') ?></td>
            <td class="text-muted small"><?= h($s['note'] ?? '') ?></td>
            <td>
              <button type="button"
                      class="btn btn-xs btn-outline-danger btn-del-somm"
                      data-id="<?= $s['id'] ?>"
                      data-farmaco="<?= $fid ?>">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

    </div>
  </div>

</div><!-- /node -->
<?php endforeach; ?>
</div><!-- /farmTree -->

<?php endif; ?>

<!-- ── Export ─────────────────────────────────────────────────── -->
<div class="win-card mt-4">
  <div class="win-card-header"><i class="bi bi-download me-2"></i>Export</div>
  <div class="win-card-body">
    <p class="text-muted small mb-3">Esporta l'elenco completo dei medicinali e delle somministrazioni registrate.</p>
    <div id="exportButtons"></div>
  </div>
</div>

<!-- Tabella dati per export — nascosta visivamente, usata solo da DataTables -->
<table id="tblExport" style="display:none">
  <thead>
    <tr>
      <th>Farmaco</th>
      <th>Principio Attivo</th>
      <th>Dosaggio</th>
      <th>Forma</th>
      <th>Ogni quanto</th>
      <th>Stato</th>
      <th data-sort="datetime-it">Data Somministrazione</th>
      <th>Quantità</th>
      <th>Modalità</th>
      <th>Zona</th>
      <th>Note</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($farmaci as $farm):
        $fid  = (int)$farm['id'];
        $somm = $somPerFarmaco[$fid] ?? [];
        $stato = $farm['attivo'] ? 'Attivo' : 'Sospeso';
        if (empty($somm)): ?>
    <tr>
      <td><?= h($farm['nome_farmaco']) ?></td>
      <td><?= h($farm['principio_attivo'] ?? '') ?></td>
      <td><?= h($farm['dosaggio'] ?? '') ?></td>
      <td><?= h($farm['forma'] ?? '') ?></td>
      <td><?= h($farm['note_assunzione'] ?? '') ?></td>
      <td><?= $stato ?></td>
      <td data-order="">—</td>
      <td>—</td><td>—</td><td>—</td><td>—</td>
    </tr>
        <?php else: foreach ($somm as $s):
            // data-order in formato ISO per ordinamento corretto
            $iso = date('Y-m-d H:i', strtotime($s['data_ora']));
            $ita = date('d/m/Y H:i', strtotime($s['data_ora']));
        ?>
    <tr>
      <td><?= h($farm['nome_farmaco']) ?></td>
      <td><?= h($farm['principio_attivo'] ?? '') ?></td>
      <td><?= h($farm['dosaggio'] ?? '') ?></td>
      <td><?= h($farm['forma'] ?? '') ?></td>
      <td><?= h($farm['note_assunzione'] ?? '') ?></td>
      <td><?= $stato ?></td>
      <td data-order="<?= $iso ?>"><?= $ita ?></td>
      <td><?= h($s['quantita']) ?></td>
      <td><?= h($s['modalita'] ?? '') ?></td>
      <td><?= h($s['zona'] ?? '') ?></td>
      <td><?= h($s['note'] ?? '') ?></td>
    </tr>
        <?php endforeach; endif; endforeach; ?>
  </tbody>
</table>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

<script>
$(function () {
  var API = '<?= APP_URL ?>/modules/medications/api.php';

  /* Chevron */
  $(document).on('show.bs.collapse', '.farm-tree-node .collapse', function () {
    $(this).closest('.farm-tree-node').find('.farm-chevron').addClass('rotated');
  }).on('hide.bs.collapse', '.farm-tree-node .collapse', function () {
    $(this).closest('.farm-tree-node').find('.farm-chevron').removeClass('rotated');
  });

  /* Blocca propagazione sui pulsanti azione */
  /* Nessun stopPropagation generico — ogni handler lo gestisce da solo */

  /* Export DataTables — solo bottoni, tabella nascosta */
  var dtExport = $('#tblExport').DataTable({
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/it-IT.json' },
    dom: 'B',
    buttons: [
      { extend: 'excelHtml5', text: '<i class="bi bi-file-earmark-excel me-1"></i>Excel',
        className: 'btn btn-sm btn-outline-success',
        title: 'Medicinali — <?= h($membro['nome'].' '.$membro['cognome']) ?>',
        exportOptions: { columns: ':all' } },
      { extend: 'pdfHtml5',   text: '<i class="bi bi-file-earmark-pdf me-1"></i>PDF',
        className: 'btn btn-sm btn-outline-danger',
        title: 'Medicinali — <?= h($membro['nome'].' '.$membro['cognome']) ?>',
        orientation: 'landscape', pageSize: 'A4',
        exportOptions: { columns: ':all' } },
      { extend: 'csvHtml5',   text: '<i class="bi bi-filetype-csv me-1"></i>CSV',
        className: 'btn btn-sm btn-outline-secondary',
        exportOptions: { columns: ':all' } },
      { extend: 'print',      text: '<i class="bi bi-printer me-1"></i>Stampa',
        className: 'btn btn-sm btn-outline-secondary',
        title: 'Medicinali — <?= h($membro['nome'].' '.$membro['cognome']) ?>',
        exportOptions: { columns: ':all' } }
    ],
    paging: false,
    searching: false,
    info: false,
    ordering: true,
    order: [[0,'asc'],[6,'desc']],
    columnDefs: [
      // Colonna 6 = Data Somministrazione: usa data-order (ISO) per l'ordinamento
      { targets: 6, orderDataType: 'dom-data-order' }
    ]
  });
  // Sposta i bottoni nel div dedicato
  dtExport.buttons().container().appendTo('#exportButtons');
  $('#btnSalvaFarmaco').on('click', function () {
    var $btn = $(this).prop('disabled', true)
      .html('<span class="spinner-border spinner-border-sm me-1"></span>Salvataggio…');
    $.ajax({
      url: API, method: 'POST',
      data: new FormData($('#formAddFarmaco')[0]),
      processData: false, contentType: false, dataType: 'json',
      success: function (res) {
        if (res.ok) { location.reload(); }
        else {
          $('#alertAddFarmaco').removeClass('d-none').text(res.msg);
          $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Salva Farmaco');
        }
      },
      error: function () {
        $('#alertAddFarmaco').removeClass('d-none').text('Errore di rete.');
        $btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i>Salva Farmaco');
      }
    });
  });

  $('#modalAddFarmaco').on('hidden.bs.modal', function () {
    $('#formAddFarmaco')[0].reset();
    $('#alertAddFarmaco').addClass('d-none').text('');
    $('#m_scontrino_preview').text('');
  });

  /* Preview file scontrino */
  $('#m_scontrino').on('change', function () {
    var file = this.files[0];
    if (!file) { $('#m_scontrino_preview').text(''); return; }
    var allowed = ['application/pdf','image/jpeg','image/png','image/gif','image/webp'];
    if (!allowed.includes(file.type)) {
      alert('Tipo file non consentito. Usa PDF o immagine.');
      this.value = ''; $('#m_scontrino_preview').text(''); return;
    }
    var maxMb = <?= MAX_UPLOAD_MB ?>;
    if (file.size > maxMb * 1024 * 1024) {
      alert('File troppo grande. Massimo ' + maxMb + ' MB.');
      this.value = ''; $('#m_scontrino_preview').text(''); return;
    }
    var icon = file.type === 'application/pdf' ? 'bi-file-earmark-pdf text-danger' : 'bi-file-earmark-image text-info';
    $('#m_scontrino_preview').html(
      '<i class="bi ' + icon + ' me-1"></i>' +
      $('<span>').text(file.name).html() +
      ' <span class="text-muted">(' + (file.size / 1024 / 1024).toFixed(2) + ' MB)</span>'
    );
  });

  /* Popola modale registra quando Bootstrap la apre */
  document.getElementById('modalSomm').addEventListener('show.bs.modal', function (e) {
    var btn  = e.relatedTarget;  // il pulsante che ha triggerato
    var id   = btn ? btn.getAttribute('data-farmaco-id')   : '';
    var nome = btn ? btn.getAttribute('data-farmaco-nome') : '';
    document.getElementById('somFarmacoId').value = id;
    document.getElementById('somFarmacoNome').innerHTML =
      '<i class="bi bi-capsule me-1 text-primary"></i><strong>' +
      nome.replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>') + '</strong>';
    var now = new Date();
    var p   = function (n) { return String(n).padStart(2, '0'); };
    document.getElementById('somDataOra').value =
      now.getFullYear() + '-' + p(now.getMonth()+1) + '-' + p(now.getDate()) +
      'T' + p(now.getHours()) + ':' + p(now.getMinutes());
    document.querySelector('#formSomm input[name=quantita]').value = '1';
    document.querySelector('#formSomm input[name=note]').value     = '';
    var sel = document.querySelector('#formSomm select[name=modalita]');
    if (sel) sel.value = '';
    var zona = document.querySelector('#formSomm input[name=zona]');
    if (zona) zona.value = '';
    document.getElementById('alertSomm').classList.add('d-none');
  });

  /* Salva somministrazione */
  $('#btnSalvaSomm').on('click', function () {
    var $btn = $(this).prop('disabled', true)
      .html('<span class="spinner-border spinner-border-sm me-1"></span>…');

    var data = {
      action:     'add_somministrazione',
      csrf_token: $('#formSomm input[name=csrf_token]').val(),
      farmaco_id: $('#somFarmacoId').val(),
      data_ora:   $('#somDataOra').val(),
      quantita:   $('#formSomm input[name=quantita]').val() || '1',
      modalita:   $('#formSomm select[name=modalita]').val() || '',
      zona:       $('#formSomm input[name=zona]').val() || '',
      note:       $('#formSomm input[name=note]').val() || ''
    };

      $btn.prop('disabled', false).html('<i class="bi bi-check2 me-1"></i>Salva');
      if (!data.farmaco_id || !data.data_ora) {
        $('#alertSomm').removeClass('d-none').text('Compila data e ora.');
        $btn.prop('disabled', false).html('<i class="bi bi-check2 me-1"></i>Salva');
        return;
      }

    $.post(API, data, function (res) {
      $btn.prop('disabled', false).html('<i class="bi bi-check2 me-1"></i>Salva');
      if (!res.ok) {
        $('#alertSomm').removeClass('d-none').text(res.msg);
        return;
      }

      bootstrap.Modal.getInstance(document.getElementById('modalSomm')).hide();

      var fid   = data.farmaco_id;
      var s     = res.somministrazione;
      var p     = function (n) { return String(n).padStart(2, '0'); };
      var dt    = new Date(s.data_ora.replace(' ', 'T'));
      var dtStr = p(dt.getDate()) + '/' + p(dt.getMonth()+1) + '/' + dt.getFullYear()
                + ' ' + p(dt.getHours()) + ':' + p(dt.getMinutes());

      var row = '<tr id="somm_row_' + s.id + '">'
        + '<td>' + dtStr + '</td>'
        + '<td class="text-center">' + $('<span>').text(s.quantita).html() + '</td>'
        + '<td class="small">'           + $('<span>').text(s.modalita || '').html() + '</td>'
        + '<td class="small text-muted">'+ $('<span>').text(s.zona     || '').html() + '</td>'
        + '<td class="text-muted small">'+ $('<span>').text(s.note     || '').html() + '</td>'
        + '<td><button type="button" class="btn btn-xs btn-outline-danger btn-del-somm"'
        + ' data-id="' + s.id + '" data-farmaco="' + fid + '">'
        + '<i class="bi bi-trash"></i></button></td></tr>';

      var $tbody = $('#somm_tbody_' + fid);
      if ($tbody.length) {
        $tbody.prepend(row);
      } else {
        var tbl = '<table class="table table-sm table-hover mb-0" id="somm_table_' + fid + '">'
          + '<thead class="table-light"><tr>'
          + '<th>Data e Ora</th>'
          + '<th style="width:80px" class="text-center">Quantità</th>'
          + '<th>Modalità</th><th>Zona</th><th>Note</th><th style="width:40px"></th>'
          + '</tr></thead>'
          + '<tbody id="somm_tbody_' + fid + '">' + row + '</tbody></table>';
        $('#somm_list_' + fid).html(tbl);
      }

      // Aggiorna badge ultima somministrazione nell'intestazione
      var $node  = $('#node_' + fid);
      var dataStr = p(dt.getDate()) + '/' + p(dt.getMonth()+1) + '/' + dt.getFullYear();
      var $badge = $node.find('.badge.bg-warning');
      if ($badge.length) {
        $badge.html('<i class="bi bi-clock me-1"></i>ultima: ' + dataStr);
      } else {
        $node.find('.d-flex.align-items-center.gap-2.flex-grow-1').append(
          '<span class="badge bg-warning text-dark">'
          + '<i class="bi bi-clock me-1"></i>ultima: ' + dataStr + '</span>'
        );
      }

    }, 'json').fail(function (xhr) {
      $btn.prop('disabled', false).html('<i class="bi bi-check2 me-1"></i>Salva');
      $('#alertSomm').removeClass('d-none')
        .text('Errore: ' + (xhr.responseText ? xhr.responseText.substring(0, 120) : 'risposta non valida'));
    });
  });

  /* Toggle attivo/sospeso */
  $(document).on('click', '.btn-toggle-attivo', function (e) {
    e.stopPropagation();
    var $btn   = $(this);
    var farmId = $btn.data('id');
    var csrf   = $('input[name=csrf_token]').first().val();

    $.post(API, { action: 'toggle_attivo', farmaco_id: farmId, csrf_token: csrf },
      function (res) {
        if (!res.ok) { alert(res.msg); return; }
        var attivo = res.attivo;
        $btn.data('attivo', attivo);

        if (attivo) {
          $btn.removeClass('btn-secondary').addClass('btn-success')
              .attr('title', 'Clicca per sospendere')
              .html('<i class="bi bi-check-circle-fill me-1"></i>Attivo');
          $('#node_' + farmId).removeClass('farm-tree-node--inactive');
        } else {
          $btn.removeClass('btn-success').addClass('btn-secondary')
              .attr('title', 'Clicca per riattivare')
              .html('<i class="bi bi-pause-circle-fill me-1"></i>Sospeso');
          $('#node_' + farmId).addClass('farm-tree-node--inactive');
        }
      }, 'json'
    );
  });

  /* Elimina somministrazione */
  $(document).on('click', '.btn-del-somm', function () {
    if (!confirm('Eliminare questa somministrazione?')) return;
    var somId  = $(this).data('id');
    var farmId = $(this).data('farmaco');
    var csrf   = $('input[name=csrf_token]').first().val();
    $.post(API, { action: 'del_somministrazione', som_id: somId, csrf_token: csrf },
      function (res) {
        if (res.ok) {
          $('#somm_row_' + somId).fadeOut(200, function () { $(this).remove(); });
        } else { alert(res.msg); }
      }, 'json'
    );
  });

});
</script>
