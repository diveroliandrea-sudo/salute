/**
 * app.js — Salute Famiglia
 * Logica JavaScript condivisa: DataTables, sidebar, BMI, utilità.
 */

'use strict';

/* ── Configurazione DataTables condivisa ─────────────────────── */
var dtDefaults = {
  language: {
    url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/it-IT.json'
  },
  dom: "<'row mb-2'<'col-sm-6'B><'col-sm-6'f>>" +
       "<'row'<'col-sm-12'tr>>" +
       "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
  buttons: [
    { extend: 'csvHtml5',   text: '<i class="bi bi-filetype-csv"></i> CSV',
      className: 'btn btn-sm btn-outline-secondary' },
    { extend: 'excelHtml5', text: '<i class="bi bi-file-earmark-excel"></i> Excel',
      className: 'btn btn-sm btn-outline-success' },
    { extend: 'pdfHtml5',   text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
      className: 'btn btn-sm btn-outline-danger',
      orientation: 'landscape', pageSize: 'A4' },
    { extend: 'print',      text: '<i class="bi bi-printer"></i> Stampa',
      className: 'btn btn-sm btn-outline-secondary' }
  ],
  pageLength: 25,
  order: []
};

$(function () {

  /* ── DataTables init ─────────────────────────────────────────
   *
   * PROBLEMA: DataTables inizializzata su una tabella dentro un tab
   * Bootstrap (display:none) non riesce a misurare le colonne e genera
   * il warning tn/18 "Incorrect column count".
   *
   * SOLUZIONE:
   *  - Tabelle FUORI da un tab (.tab-pane): init immediata
   *  - Tabelle DENTRO un tab (.tab-pane): init lazy, solo quando
   *    la tab diventa visibile (evento Bootstrap "shown.bs.tab")
   * ──────────────────────────────────────────────────────────── */

  // Mappa tab-id → istanza DataTable (per columns.adjust al resize)
  var dtInstances = {};

  function initDt($table) {
    var id = $table.attr('id') || ('dt_' + Math.random().toString(36).slice(2));
    $table.attr('id', id);
    if ($.fn.DataTable.isDataTable($table)) return; // già inizializzata
    var instance = $table.DataTable(dtDefaults);
    dtInstances[id] = instance;
  }

  // Tabelle visibili subito (fuori da tab, o nel primo tab attivo)
  $('.dt-table').each(function () {
    var $t = $(this);
    // Se è dentro un tab-pane NON attivo, salta (inizializzazione lazy)
    var $pane = $t.closest('.tab-pane');
    if ($pane.length && !$pane.hasClass('active')) return;
    initDt($t);
  });

  // Init lazy: quando un tab viene mostrato, inizializza le tabelle al suo interno
  $(document).on('shown.bs.tab', 'a[data-bs-toggle="tab"], button[data-bs-toggle="tab"]', function () {
    var target = $(this).attr('href') || $(this).attr('data-bs-target');
    if (!target) return;
    $(target).find('.dt-table').each(function () {
      var $t = $(this);
      if ($.fn.DataTable.isDataTable($t)) {
        // Già inizializzata: aggiusta le colonne ora che è visibile
        var id = $t.attr('id');
        if (dtInstances[id]) dtInstances[id].columns.adjust().draw(false);
      } else {
        initDt($t);
      }
    });
  });

  /* ── Sidebar toggle ─────────────────────────────────────────── */
  $('#sidebarToggle').on('click', function () {
    $('#sidebar').toggleClass('collapsed');
    // Ricalcola colonne di tutte le DT visibili dopo toggle sidebar
    setTimeout(function () {
      $.each(dtInstances, function (id, dt) { dt.columns.adjust(); });
    }, 300);
  });

  /* ── Confirm delete ─────────────────────────────────────────── */
  $(document).on('click', '.btn-confirm-delete', function (e) {
    if (!confirm('Confermi l\'eliminazione? L\'operazione non è reversibile.')) {
      e.preventDefault();
    }
  });

  /* ── BMI live calculator ─────────────────────────────────────── */
  function calcolaBMILive() {
    var peso    = parseFloat($('#peso_kg').val());
    var altezza = parseFloat($('#altezza_cm').val());
    if (peso > 0 && altezza > 0) {
      var h = altezza / 100;
      var bmi = (peso / (h * h)).toFixed(2);
      $('#bmi_calc').text(bmi);
      var cls = 'secondary', lbl = '';
      if (bmi < 18.5)       { cls = 'info';    lbl = 'Sottopeso'; }
      else if (bmi < 25)    { cls = 'success';  lbl = 'Normopeso'; }
      else if (bmi < 30)    { cls = 'warning';  lbl = 'Sovrappeso'; }
      else                  { cls = 'danger';   lbl = 'Obesità'; }
      $('#bmi_label').text(lbl)
        .removeClass('bg-info bg-success bg-warning bg-danger bg-secondary')
        .addClass('bg-' + cls);
      $('#bmi_hidden').val(bmi);
    } else {
      $('#bmi_calc').text('—');
      $('#bmi_label').text('');
    }
  }
  $('#peso_kg, #altezza_cm').on('input', calcolaBMILive);

  /* ── Terapia cronica toggle ──────────────────────────────────── */
  $('#cronico').on('change', function () {
    $('#data_fine').prop('disabled', this.checked).val('');
    $('#data_fine_row').toggleClass('opacity-50', this.checked);
  });
  if ($('#cronico').is(':checked')) {
    $('#data_fine').prop('disabled', true);
    $('#data_fine_row').addClass('opacity-50');
  }

  /* ── Aggiungi riga orario farmaco ────────────────────────────── */
  var orarioCount = parseInt($('#orario_count').val()) || 0;

  $('#btn_add_orario').on('click', function () {
    orarioCount++;
    var html = '<div class="row g-2 align-items-center orario-row mb-2" id="orario_row_' + orarioCount + '">'
      + '<div class="col-md-3"><input type="time" name="orari[' + orarioCount + '][ora]"'
      +   ' class="form-control form-control-sm" required></div>'
      + '<div class="col-md-3"><input type="text" name="orari[' + orarioCount + '][quantita]"'
      +   ' class="form-control form-control-sm" placeholder="Quantità es. 1" value="1"></div>'
      + '<div class="col-md-4"><input type="text" name="orari[' + orarioCount + '][note]"'
      +   ' class="form-control form-control-sm" placeholder="Note orario"></div>'
      + '<div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-orario"'
      +   ' data-row="' + orarioCount + '"><i class="bi bi-trash"></i></button></div>'
      + '</div>';
    $('#orari_container').append(html);
    $('#orario_count').val(orarioCount);
  });

  $(document).on('click', '.btn-remove-orario', function () {
    $('#orario_row_' + $(this).data('row')).remove();
  });

  /* ── Preview file allegato ───────────────────────────────────── */
  $('#file_allegato').on('change', function () {
    var file = this.files[0];
    if (!file) return;
    var allowed = ['application/pdf','image/jpeg','image/png','image/gif','image/webp'];
    if (!allowed.includes(file.type)) {
      alert('Tipo file non consentito. Usa PDF o immagine (JPG, PNG, GIF, WEBP).');
      this.value = '';
      return;
    }
    var maxMb = 20;
    if (file.size > maxMb * 1024 * 1024) {
      alert('File troppo grande. Massimo ' + maxMb + ' MB.');
      this.value = '';
      return;
    }
    $('#file_nome_preview').text(file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)');
  });

  /* ── Tooltip Bootstrap ───────────────────────────────────────── */
  $('[data-bs-toggle="tooltip"]').each(function () {
    new bootstrap.Tooltip(this);
  });

});
