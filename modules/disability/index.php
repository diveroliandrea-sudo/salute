<?php
/**
 * disability/index.php — Gestione Invalidità e Legge 104
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
requireLogin();
$membro = membroSelezionato();
if (!$membro) { setFlash('warning','Seleziona prima un membro.'); header('Location: '.APP_URL.'/index.php'); exit; }
$mid = $membro['id'];

if (isset($_GET['delete'])) {
    $did=(int)$_GET['delete'];
    $s=db()->prepare('SELECT id FROM invalidita WHERE id=? AND membro_id=?');
    $s->execute([$did,$mid]);
    if ($s->fetch()) { db()->prepare('DELETE FROM invalidita WHERE id=?')->execute([$did]); setFlash('success','Record eliminato.'); }
    header('Location: index.php'); exit;
}

$errore='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    verifyCsrf();
    $tipo = trim($_POST['tipo']??'');
    if (!$tipo) { $errore='Il tipo è obbligatorio.'; }
    else {
        $fileNome=$fileMime=$fileDati=null;
        if (!empty($_FILES['file_allegato']['name'])) {
            $file=$_FILES['file_allegato']; $mime=mime_content_type($file['tmp_name']);
            if (!in_array($mime,unserialize(ALLOWED_MIME))) { $errore='Tipo file non consentito.'; }
            elseif ($file['size']>MAX_UPLOAD_BYTE) { $errore='File troppo grande.'; }
            else { $fileNome=$file['name'];$fileMime=$mime;$fileDati=file_get_contents($file['tmp_name']); }
        }
        if (!$errore) {
            $editId=(int)($_POST['edit_id']??0);
            if ($editId) {
                db()->prepare(
                    'UPDATE invalidita SET tipo=?,percentuale=?,legge_104_grado=?,data_verbale=?,data_scadenza=?,
                     commissione=?,benefici_attivi=?,note=?,
                     file_nome=COALESCE(?,file_nome),file_mime=COALESCE(?,file_mime),file_dati=COALESCE(?,file_dati),
                     updated_at=NOW() WHERE id=? AND membro_id=?'
                )->execute([
                    $tipo, $_POST['percentuale']?:(null), $_POST['legge_104_grado']?:(null),
                    $_POST['data_verbale']?:(null), $_POST['data_scadenza']?:(null),
                    trim($_POST['commissione']??'')?: null, trim($_POST['benefici_attivi']??'')?: null,
                    trim($_POST['note']??'')?: null,
                    $fileNome,$fileMime,$fileDati,$editId,$mid,
                ]);
                setFlash('success','Record aggiornato.');
            } else {
                db()->prepare(
                    'INSERT INTO invalidita (membro_id,tipo,percentuale,legge_104_grado,data_verbale,data_scadenza,
                     commissione,benefici_attivi,note,file_nome,file_mime,file_dati)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([
                    $mid,$tipo,$_POST['percentuale']?:(null),$_POST['legge_104_grado']?:(null),
                    $_POST['data_verbale']?:(null),$_POST['data_scadenza']?:(null),
                    trim($_POST['commissione']??'')?: null, trim($_POST['benefici_attivi']??'')?: null,
                    trim($_POST['note']??'')?: null,$fileNome,$fileMime,$fileDati,
                ]);
                setFlash('success','Record aggiunto.');
            }
            header('Location: index.php'); exit;
        }
    }
}

$records = db()->prepare('SELECT * FROM invalidita WHERE membro_id=? ORDER BY data_verbale DESC');
$records->execute([$mid]);
$records = $records->fetchAll();

$pageTitle = 'Invalidità & Legge 104';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-titlebar">
  <h1><i class="bi bi-shield-check me-2"></i>Invalidità & Legge 104 — <?= h($membro['nome'].' '.$membro['cognome']) ?></h1>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalInvalidita">
    <i class="bi bi-plus-circle me-1"></i>Aggiungi
  </button>
</div>

<?php if ($errore): ?><div class="alert alert-danger"><?= h($errore) ?></div><?php endif; ?>

<div class="win-card"><div class="win-card-body p-0">
  <table class="table table-hover mb-0 dt-table">
    <thead>
      <tr><th>Tipo</th><th>%</th><th>Legge 104</th><th>Data Verbale</th><th>Scadenza</th><th>Commissione</th><th>File</th><th>Azioni</th></tr>
    </thead>
    <tbody>
      <?php foreach ($records as $r): ?>
      <tr>
        <td class="fw-semibold"><?= h($r['tipo']) ?></td>
        <td><?= $r['percentuale'] ? $r['percentuale'].'%' : '—' ?></td>
        <td><?= $r['legge_104_grado'] ? '<span class="badge bg-warning text-dark">Grado '.$r['legge_104_grado'].'</span>' : '—' ?></td>
        <td><?= dataITA($r['data_verbale']) ?></td>
        <td><?= $r['data_scadenza'] ? '<span class="'.( strtotime($r['data_scadenza'])<time()?'text-danger fw-bold':'').'">' . dataITA($r['data_scadenza']).'</span>' : '<span class="badge bg-info">Permanente</span>' ?></td>
        <td><?= h($r['commissione']??'—') ?></td>
        <td class="text-center">
          <?php if ($r['file_nome']): ?>
          <a href="<?= APP_URL ?>/modules/documents/view.php?modulo=invalidita&id=<?= $r['id'] ?>"
             target="_blank" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <a href="?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger btn-confirm-delete"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div></div>

<!-- Modal aggiungi -->
<div class="modal fade" id="modalInvalidita" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-shield-plus me-2"></i>Nuovo Record Invalidità</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="save">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Tipo *</label>
              <input type="text" name="tipo" class="form-control" required placeholder="es. Invalidità Civile, Legge 104">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Percentuale %</label>
              <input type="number" name="percentuale" class="form-control" min="0" max="100">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Legge 104 - Grado</label>
              <select name="legge_104_grado" class="form-select">
                <option value="">— Nessuno —</option>
                <option value="1">Grado 1</option>
                <option value="2">Grado 2</option>
                <option value="3">Grado 3</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Data Verbale</label>
              <input type="date" name="data_verbale" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Data Scadenza</label>
              <input type="date" name="data_scadenza" class="form-control">
              <div class="form-text">Lascia vuoto se permanente.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Commissione</label>
              <input type="text" name="commissione" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Benefici Attivi</label>
              <textarea name="benefici_attivi" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Note</label>
              <textarea name="note" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Allega Verbale/Documento</label>
              <input type="file" name="file_allegato" id="file_allegato" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
              <div class="form-text" id="file_nome_preview"></div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annulla</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Salva</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
