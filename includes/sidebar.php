<?php
/**
 * sidebar.php
 * Menu laterale di navigazione, stile Windows-panel.
 * Evidenzia la voce attiva in base al percorso corrente.
 */
$current = $_SERVER['REQUEST_URI'] ?? '';

function navItem(string $url, string $icon, string $label, string $current): string {
    $active = (strpos($current, $url) !== false) ? 'active' : '';
    return '<li class="nav-item">
      <a class="nav-link sidebar-link ' . $active . '" href="' . APP_URL . $url . '">
        <i class="bi ' . $icon . ' me-2"></i>' . $label . '
      </a></li>';
}
?>
<nav class="app-sidebar d-flex flex-column py-3" id="sidebar">
  <div class="sidebar-header px-3 mb-2">
    <small class="text-uppercase text-muted fw-semibold">Navigazione</small>
  </div>
  <ul class="nav flex-column px-2">
    <?= navItem('/index.php',                          'bi-house-fill',        'Dashboard',             $current) ?>
    <?= navItem('/modules/members/',                   'bi-people-fill',       'Membri Famiglia',       $current) ?>
  </ul>

  <div class="sidebar-header px-3 mt-3 mb-2">
    <small class="text-uppercase text-muted fw-semibold">Agenda</small>
  </div>
  <ul class="nav flex-column px-2">
    <?= navItem('/modules/appointments/',              'bi-calendar-check',    'Appuntamenti',          $current) ?>
    <?= navItem('/modules/prescriptions/',             'bi-file-medical',      'Impegnative',           $current) ?>
  </ul>

  <div class="sidebar-header px-3 mt-3 mb-2">
    <small class="text-uppercase text-muted fw-semibold">Cartella Clinica</small>
  </div>
  <ul class="nav flex-column px-2">
    <?= navItem('/modules/medical_record/',            'bi-folder2-open',      'Cartella Clinica',      $current) ?>
    <?= navItem('/modules/visits/',                    'bi-stethoscope',       'Visite / Analisi',      $current) ?>
    <?= navItem('/modules/hospitalizations/',          'bi-hospital',          'Ricoveri & Dimissioni', $current) ?>
  </ul>

  <div class="sidebar-header px-3 mt-3 mb-2">
    <small class="text-uppercase text-muted fw-semibold">Terapie</small>
  </div>
  <ul class="nav flex-column px-2">
    <?= navItem('/modules/medications/',               'bi-capsule',           'Medicinali',            $current) ?>
    <?= navItem('/modules/therapy_plans/',             'bi-journal-medical',   'Piani Terapeutici',     $current) ?>
    <?= navItem('/modules/vitals/',                    'bi-activity',          'Parametri Vitali',      $current) ?>
  </ul>

  <div class="sidebar-header px-3 mt-3 mb-2">
    <small class="text-uppercase text-muted fw-semibold">Dieta & Peso</small>
  </div>
  <ul class="nav flex-column px-2">
    <?= navItem('/modules/diet/',                      'bi-speedometer2',      'Dieta & Peso',          $current) ?>
  </ul>

  <div class="sidebar-header px-3 mt-3 mb-2">
    <small class="text-uppercase text-muted fw-semibold">Altro</small>
  </div>
  <ul class="nav flex-column px-2">
    <?= navItem('/modules/disability/',                'bi-shield-check',      'Invalidità / 104',      $current) ?>
    <?= navItem('/modules/documents/',                 'bi-paperclip',         'Documenti',             $current) ?>
  </ul>

  <!-- Toggle sidebar button -->
  <button class="btn btn-sm btn-outline-secondary ms-3 mt-auto me-3 d-none d-lg-block" id="sidebarToggle">
    <i class="bi bi-layout-sidebar"></i>
  </button>
</nav>
