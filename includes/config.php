<?php
/**
 * config.php
 * Configurazione globale dell'applicazione e connessione al database.
 * Utilizza PDO con gestione degli errori tramite eccezioni.
 */

// ── Impostazioni applicazione ────────────────────────────────
define('APP_NAME',    'Salute Famiglia');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'https://www.memoriadeportati.it/salute');
//define('APP_URL',     'http://localhost/salute');

// ── Parametri database ───────────────────────────────────────
/*define('DB_HOST',    'localhost');
define('DB_NAME',    'salute');
define('DB_USER',    'root');
define('DB_PASS',    'root');
define('DB_PORT',    '3306');
define('DB_CHARSET', 'utf8mb4');*/

define('DB_HOST',    '172.245.156.21');
define('DB_PORT',    '3306');
define('DB_NAME',    'salute');
define('DB_USER',    'root');
define('DB_PASS',    'Deportati.1');
define('DB_CHARSET', 'utf8mb4');


// ── Impostazioni e-mail (usate per conferma registrazione / reset) ──
define('MAIL_FROM',      'noreply@salute.local');
define('MAIL_FROM_NAME', 'Salute Famiglia');

// ── Limiti upload ────────────────────────────────────────────
define('MAX_UPLOAD_MB',   20);                           // Megabyte
define('MAX_UPLOAD_BYTE', MAX_UPLOAD_MB * 1024 * 1024); // Bytes

// MIME type consentiti per gli allegati medici
define('ALLOWED_MIME', serialize([
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
]));

// ── Percorsi ─────────────────────────────────────────────────
define('ROOT_PATH',    dirname(__DIR__));
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// ── Fuso orario ──────────────────────────────────────────────
date_default_timezone_set('Europe/Rome');

// ── Sessione sicura ──────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// ── Connessione PDO (singleton) ──────────────────────────────
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // In produzione non mostrare dettagli dell'eccezione
            die('<div style="font-family:sans-serif;color:#c0392b;padding:2rem">
                <h2>Errore di connessione al database</h2>
                <p>Impossibile connettersi al database. Controlla i parametri in config.php.</p>
                <small>' . htmlspecialchars($e->getMessage()) . '</small>
                </div>');
        }
    }
    return $pdo;
}

// ── Helper: protezione pagine riservate ──────────────────────
function requireLogin(): void
{
    if (empty($_SESSION['utente_id'])) {
        header('Location: ' . APP_URL . '/modules/auth/login.php');
        exit;
    }
}

// ── Helper: membro selezionato ───────────────────────────────
function membroSelezionato(): ?array
{
    if (empty($_SESSION['membro_id'])) return null;
    $stmt = db()->prepare('SELECT * FROM membri_famiglia WHERE id = ?');
    $stmt->execute([$_SESSION['membro_id']]);
    return $stmt->fetch() ?: null;
}

// ── Helper: escape HTML ───────────────────────────────────────
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Helper: flash messages ────────────────────────────────────
function setFlash(string $tipo, string $msg): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'msg' => $msg];
}

function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ── Helper: token CSRF ────────────────────────────────────────
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token CSRF non valido.');
    }
}

// ── Helper: calcolo BMI ───────────────────────────────────────
function calcolaBMI(float $peso, float $altezzaCm): float
{
    $altezzaM = $altezzaCm / 100;
    return round($peso / ($altezzaM * $altezzaM), 2);
}

function classificaBMI(float $bmi): array
{
    if ($bmi < 18.5) return ['label' => 'Sottopeso',  'class' => 'info'];
    if ($bmi < 25.0) return ['label' => 'Normopeso',  'class' => 'success'];
    if ($bmi < 30.0) return ['label' => 'Sovrappeso', 'class' => 'warning'];
    return                  ['label' => 'Obesità',    'class' => 'danger'];
}

// ── Helper: classificazione pressione arteriosa ───────────────
function classificaPressione(int $sis, int $dia): array
{
    if ($sis < 120 && $dia < 80)  return ['label' => 'Ottimale',   'class' => 'success'];
    if ($sis < 130 && $dia < 85)  return ['label' => 'Normale',    'class' => 'success'];
    if ($sis < 140 && $dia < 90)  return ['label' => 'Normale-Alta','class' => 'warning'];
    if ($sis < 160 || $dia < 100) return ['label' => 'Ipertensione I', 'class' => 'warning'];
    if ($sis < 180 || $dia < 110) return ['label' => 'Ipertensione II','class' => 'danger'];
    return                               ['label' => 'Crisi Ipertensiva','class' => 'danger'];
}

// ── Helper: MIME type sicuro dal contenuto del file ───────────
function getMimeFromContent(string $dati): string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    return $finfo->buffer($dati);
}

// ── Helper: Google Calendar URL (Opzione A — nessuna API) ────
/**
 * Genera un link "Aggiungi a Google Calendar" senza API key.
 *
 * @param string      $title     Titolo evento
 * @param string      $start     Data/ora inizio in formato 'Y-m-d H:i:s' o 'Y-m-d'
 * @param string|null $end       Data/ora fine (opzionale; se null = stesso giorno +1h)
 * @param string|null $details   Descrizione / note
 * @param string|null $location  Luogo / struttura
 * @return string URL completo
 */
function googleCalendarUrl(
    string $title,
    string $start,
    ?string $end      = null,
    ?string $details  = null,
    ?string $location = null
): string {
    // Google vuole il formato YYYYMMDDTHHmmssZ (UTC) o YYYYMMDD per eventi tutto-il-giorno
    $allDay = (strlen($start) === 10); // solo data, nessun orario

    if ($allDay) {
        $startFmt = date('Ymd', strtotime($start));
        // Evento tutto-il-giorno: la data fine è il giorno successivo (esclusivo)
        $endFmt   = $end
            ? date('Ymd', strtotime($end) + 86400)
            : date('Ymd', strtotime($start) + 86400);
    } else {
        $tz       = new DateTimeZone('Europe/Rome');
        $dtStart  = new DateTime($start, $tz);
        $startFmt = $dtStart->format('Ymd\THis');

        if ($end) {
            $dtEnd  = new DateTime($end, $tz);
        } else {
            $dtEnd  = clone $dtStart;
            $dtEnd->modify('+1 hour');
        }
        $endFmt = $dtEnd->format('Ymd\THis');
    }

    $params = [
        'action' => 'TEMPLATE',
        'text'   => $title,
        'dates'  => $startFmt . '/' . $endFmt,
    ];
    if ($details)  $params['details']  = $details;
    if ($location) $params['location'] = $location;

    return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
}

// ── Helper: formato data italiana ────────────────────────────
function dataITA(?string $data): string
{
    if (!$data) return '—';
    $d = DateTime::createFromFormat('Y-m-d', $data)
      ?? DateTime::createFromFormat('Y-m-d H:i:s', $data);
    return $d ? $d->format('d/m/Y') : $data;
}

function dataOraITA(?string $data): string
{
    if (!$data) return '—';
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $data)
      ?? DateTime::createFromFormat('Y-m-d H:i', $data);
    return $d ? $d->format('d/m/Y H:i') : $data;
}
