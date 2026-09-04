# Documentazione — Salute Famiglia
**Versione:** 1.0.0  
**Ultimo aggiornamento:** 2026-08-25  
**Stack:** PHP 8+, MySQL 8, Bootstrap 5.3, DataTables 1.13, jQuery 3.7

---

## Indice

1. [Panoramica del Progetto](#1-panoramica-del-progetto)
2. [Struttura Cartelle](#2-struttura-cartelle)
3. [Installazione e Configurazione](#3-installazione-e-configurazione)
4. [Schema Database](#4-schema-database)
5. [Moduli Applicativi](#5-moduli-applicativi)
   - 5.1 [Autenticazione](#51-autenticazione)
   - 5.2 [Membri della Famiglia](#52-membri-della-famiglia)
   - 5.3 [Dashboard](#53-dashboard)
   - 5.4 [Appuntamenti](#54-appuntamenti)
   - 5.5 [Impegnative (Ricette)](#55-impegnative-ricette)
   - 5.6 [Visite & Analisi](#56-visite--analisi)
   - 5.7 [Ricoveri & Lettere di Dimissione](#57-ricoveri--lettere-di-dimissione)
   - 5.8 [Cartella Clinica](#58-cartella-clinica)
   - 5.9 [Medicinali (CRUD + Orari)](#59-medicinali-crud--orari)
   - 5.10 [Parametri Vitali](#510-parametri-vitali)
   - 5.11 [Invalidità & Legge 104](#511-invalidità--legge-104)
   - 5.12 [Archivio Documenti & Viewer](#512-archivio-documenti--viewer)
6. [Sicurezza](#6-sicurezza)
7. [Helper Functions](#7-helper-functions)
8. [Note di Deploy](#8-note-di-deploy)
9. [Changelog](#9-changelog)

---

## 1. Panoramica del Progetto

**Salute Famiglia** è una web application per la gestione centralizzata della salute di tutti i membri di un nucleo familiare. Permette di:

- Archiviare e consultare referti medici, ricoveri, lettere di dimissione
- Gestire terapie farmacologiche con orari di somministrazione
- Tracciare parametri vitali (peso, pressione, temperatura, BMI, SpO₂, glicemia)
- Mantenere un'agenda appuntamenti con stato e promemoria
- Gestire impegnative/ricette mediche
- Archiviare documenti con viewer integrato (PDF e immagini)
- Tenere traccia di invalidità civile e benefici Legge 104

---

## 2. Struttura Cartelle

```
salute/
├── index.php                    ← Dashboard principale
├── database/
│   └── salute.sql               ← Script SQL completo
├── includes/
│   ├── config.php               ← Connessione DB, helper functions, sessione
│   ├── header.php               ← HTML head + navbar + sidebar opener
│   ├── sidebar.php              ← Menu laterale di navigazione
│   └── footer.php               ← Script JS + chiusura HTML
├── assets/
│   ├── css/app.css              ← Stile Windows-style
│   ├── js/app.js                ← DataTables, BMI live, orari farmaci, CSRF
│   └── img/                     ← Immagini statiche
├── modules/
│   ├── auth/
│   │   ├── login.php
│   │   ├── register.php
│   │   ├── verify.php           ← Conferma e-mail
│   │   ├── forgot.php           ← Richiesta reset password
│   │   ├── reset.php            ← Reset password con token
│   │   ├── logout.php
│   │   └── profile.php
│   ├── members/
│   │   ├── index.php
│   │   ├── add.php
│   │   ├── edit.php
│   │   └── delete.php
│   ├── appointments/
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   ├── prescriptions/
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   ├── visits/
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   ├── hospitalizations/
│   │   ├── index.php
│   │   ├── add.php
│   │   ├── edit.php
│   │   ├── view.php
│   │   └── delete.php
│   ├── medications/
│   │   ├── index.php            ← Lista + Planning giornaliero
│   │   ├── add.php              ← CRUD + orari somministrazione
│   │   ├── edit.php
│   │   └── delete.php
│   ├── vitals/
│   │   └── index.php            ← Form inserimento + storico + BMI/pressione
│   ├── medical_record/
│   │   └── index.php            ← Vista storica completa (cartella clinica)
│   ├── disability/
│   │   └── index.php            ← Invalidità & Legge 104
│   └── documents/
│       ├── index.php            ← Archivio documenti aggregato
│       ├── view.php             ← Viewer PDF/immagine inline
│       └── download.php         ← Download sicuro
├── uploads/                     ← Directory di upload (opzionale, non usata con BLOB)
└── docs/
    └── doc_salute.md            ← Questa documentazione
```

---

## 3. Installazione e Configurazione

### Requisiti
- PHP >= 8.0 (estensioni: PDO, PDO_MySQL, fileinfo, mbstring)
- MySQL >= 8.0
- XAMPP (Windows) o equivalente
- Browser moderno

### Installazione automatica (consigliata)

1. **Copia il progetto** in `c:\xampp\htdocs\salute\`
2. **Avvia XAMPP** (Apache + MySQL)
3. **Apri il browser** su `http://localhost/salute/install.php`
4. Il wizard esegue automaticamente:
   - Connessione al server MySQL
   - Creazione database `salute`
   - Esecuzione di tutto lo schema `database/salute.sql` (11 tabelle)
   - Creazione utente admin (`admin@salute.local` / `admin`) con `email_verificata=1`
   - Scrittura del file `install.lock` per bloccare la pagina
5. Al termine clicca **"Accedi all'applicazione"** e cambia subito la password

> **Sicurezza:** dopo l'installazione il file `install.lock` impedisce l'accesso a `install.php`.  
> Puoi anche eliminare `install.php` dopo l'uso.

### Installazione manuale (alternativa)

1. **Crea il database** tramite phpMyAdmin o CLI:
   ```sql
   SOURCE c:/xampp/htdocs/salute/database/salute.sql;
   ```

2. **Verifica i parametri** in `includes/config.php`:
   ```php
   define('DB_HOST',    'localhost');
   define('DB_NAME',    'salute');
   define('DB_USER',    'root');
   define('DB_PASS',    'root');
   define('DB_PORT',    '3306');
   define('APP_URL',    'http://localhost/salute');
   ```

3. **Crea l'utente admin manualmente:**
   ```sql
   INSERT INTO utenti (nome, cognome, email, password_hash, email_verificata, attivo)
   VALUES ('Admin', 'Sistema', 'admin@salute.local',
           '$2y$...', 1, 1);
   ```
   > Genera l'hash con `password_hash('admin', PASSWORD_DEFAULT)` in PHP.

4. **Registra altri utenti** su `/modules/auth/register.php`

---

## 4. Schema Database

### Tabelle principali

| Tabella | Descrizione | Chiavi Esterne |
|---------|-------------|----------------|
| `utenti` | Autenticazione utenti | — |
| `membri_famiglia` | Anagrafica membri | `utente_id → utenti.id` |
| `appuntamenti` | Agenda visite/controlli | `membro_id → membri_famiglia.id` |
| `impegnative` | Ricette mediche NRE | `membro_id → membri_famiglia.id` |
| `visite_specialistiche` | Visite, analisi, strumentali | `membro_id → membri_famiglia.id` |
| `ricoveri` | Ricoveri ospedalieri + lettera dimissione | `membro_id → membri_famiglia.id` |
| `farmaci` | Anagrafica medicinali | `membro_id → membri_famiglia.id` |
| `farmaci_orari` | Piani di somministrazione | `farmaco_id → farmaci.id` |
| `parametri_vitali` | Storico parametri fisici | `membro_id → membri_famiglia.id` |
| `invalidita` | Invalidità civile e Legge 104 | `membro_id → membri_famiglia.id` |
| `piani_terapeutici` | Piani terapeutici con file allegato | `membro_id → membri_famiglia.id` |

### Gestione file
I file (PDF, immagini) sono memorizzati come `LONGBLOB` direttamente nel database MySQL. Il tipo MIME viene verificato in doppio:
1. Lato client (JavaScript, validazione estensione e dimensione)
2. Lato server (PHP `mime_content_type()` sul contenuto reale del file)

---

## 5. Moduli Applicativi

### 5.1 Autenticazione

| File | Funzione |
|------|----------|
| `login.php` | Form di login con toggle password |
| `register.php` | Registrazione + invio e-mail conferma |
| `verify.php` | Verifica token e-mail |
| `forgot.php` | Richiesta link reset password |
| `reset.php` | Impostazione nuova password (token 1h) |
| `logout.php` | Distruzione sessione sicura |
| `profile.php` | Modifica nome/cognome e cambio password |

**Sicurezza:**
- Password hashata con `password_hash()` (bcrypt)
- Protezione CSRF su tutti i form POST
- Rigenerazione session ID al login (`session_regenerate_id(true)`)
- Token reset con scadenza di 1 ora

---

### 5.2 Membri della Famiglia

Ogni utente può gestire più membri del nucleo familiare. I dati di ogni modulo sono filtrati in base al membro selezionato (salvato in `$_SESSION['membro_id']`).

**Campi:** nome, cognome, data nascita, sesso, codice fiscale, relazione (es. Figlio), medico di base, note.

**Selezione membro:** dalla navbar dropdown o dalla dashboard con le card avatar.

---

### 5.3 Dashboard

La dashboard (`index.php`) mostra per il membro selezionato:
- Card contatori: appuntamenti futuri, farmaci attivi, ricoveri, visite
- Lista prossimi 5 appuntamenti programmati
- Planning farmaci di oggi con orari
- Ultimo set di parametri vitali con classificazione BMI e pressione

---

### 5.4 Appuntamenti

**CRUD completo** per agenda visite/controlli.

| Campo | Tipo | Note |
|-------|------|------|
| titolo | VARCHAR | Obbligatorio |
| tipo | SELECT | Visita, Analisi, Controllo, Vaccinazione, Intervento, Altro |
| data_ora | DATETIME | Obbligatorio |
| stato | ENUM | programmato, completato, annullato, rinviato |
| luogo, medico, struttura | VARCHAR | Facoltativi |

**DataTables:** ricerca, ordinamento, export CSV/Excel/PDF.

---

### 5.5 Impegnative (Ricette)

Gestione ricette mediche del SSN.

| Campo | Tipo | Note |
|-------|------|------|
| codice_nre | VARCHAR | Codice Ricetta Elettronica |
| tipo_priorita | ENUM | U=Urgente, B=Breve, P=Programmabile, D=Differibile |
| stato | ENUM | da_utilizzare, utilizzata, scaduta |
| file_allegato | LONGBLOB | Scansione ricetta (PDF/immagine) |

---

### 5.6 Visite & Analisi

Registrazione unificata di 3 tipi:
- **specialistica** — visite con specialisti
- **analisi** — esami del sangue/urine/ecc.
- **strumentale** — ECG, radiografie, TAC, ecc.

Ogni record può avere un file allegato (referto) visualizzabile inline.  
Filtro rapido per tipo nella barra superiore.

---

### 5.7 Ricoveri & Lettere di Dimissione

Modulo dedicato ai ricoveri ospedalieri.

**Dati ricovero:**
- Struttura ospedaliera, reparto
- Data ingresso / data dimissione (se vuota → "In corso")
- Diagnosi d'ingresso, motivo del ricovero
- Medico responsabile

**Lettera di dimissione (integrata nel ricovero):**
- Diagnosi alla dimissione
- Terapia consigliata al rientro
- File allegato (PDF/immagine) con viewer integrato

**Vista dettaglio** (`view.php`): mostra dati ricovero + lettera in due colonne affiancate con pulsanti Visualizza/Scarica.

---

### 5.8 Cartella Clinica

Vista storica aggregata (`modules/medical_record/index.php`) che raccoglie in un'unica schermata con tabs:

1. **Visite & Analisi** — tutte le visite del paziente con DataTables
2. **Ricoveri** — tutti i ricoveri con link al dettaglio
3. **Terapie** — tutti i farmaci storici (attivi e sospesi)
4. **Impegnative** — tutte le prescrizioni ricevute
5. **Invalidità** — certificati e verbali (se presenti)

---

### 5.9 Medicinali CRUD + Orari

Pagina dedicata (`modules/medications/`) con due viste:

#### Tab "Elenco Farmaci"
Tabella con tutti i farmaci + badge orari. Azioni: modifica, elimina.

**Campi farmaco:**
- Nome farmaco, principio attivo, dosaggio, forma farmaceutica
- Note di assunzione (es. "a stomaco pieno")
- Data inizio / data fine
- Flag **Terapia Cronica / Indefinita** (disabilita data fine)
- Stato attivo/sospeso

#### Tab "Planning Giornaliero"
Tabella raggruppata per ora di somministrazione. Mostra solo farmaci attivi con terapia valida alla data odierna.

#### Piani di Somministrazione (farmaci_orari)
Ogni farmaco può avere N orari. Form dinamico (aggiunta/rimozione righe via JavaScript):
- Ora (TIME)
- Quantità (es. "1", "1/2", "2 compresse")
- Note orario

---

### 5.10 Parametri Vitali

Form di inserimento + storico in tabella nella stessa pagina.

**Parametri tracciati:**
| Parametro | Unità | Classificazione automatica |
|-----------|-------|---------------------------|
| Peso + Altezza | kg / cm | BMI con label (Sottopeso/Normopeso/Sovrappeso/Obesità) |
| Pressione Sistolica/Diastolica | mmHg | 6 classi (Ottimale → Crisi Ipertensiva) |
| Frequenza cardiaca | bpm | — |
| Temperatura | °C | — |
| Saturazione O₂ | % | — |
| Glicemia | mg/dL | — |

**BMI live:** calcolato in JavaScript mentre l'utente inserisce peso e altezza; il valore viene salvato nel campo `bmi` del database.

---

### 5.11 Invalidità & Legge 104

Gestione certificati di invalidità civile e benefici Legge 104.

**Campi:** tipo, percentuale (0-100%), grado Legge 104 (1/2/3), data verbale, data scadenza (vuota = permanente), commissione, benefici attivi, note, file allegato (verbale).

La scadenza evidenziata in rosso se già passata.

---

### 5.13 Piani Terapeutici

Modulo dedicato (`modules/therapy_plans/`) per gestire i piani terapeutici firmati dal medico.

**Campi:**

| Campo | Tipo | Note |
|-------|------|------|
| titolo | VARCHAR | Obbligatorio |
| tipo | SELECT | Farmacologica, Riabilitativa, Oncologica, Post-operatoria, ecc. |
| medico_redattore | VARCHAR | Medico che ha redatto il piano |
| struttura | VARCHAR | Ospedale / ambulatorio / reparto |
| data_inizio | DATE | Obbligatoria |
| data_fine | DATE | Nullable — durata indefinita se vuoto + checkbox ∞ |
| obiettivo | TEXT | Obiettivo terapeutico del piano |
| descrizione | TEXT | Dettaglio protocollo / indicazioni cliniche |
| farmaci_previsti | TEXT | Elenco sintetico dei farmaci inclusi |
| stato | ENUM | attivo, sospeso, completato, revocato |
| file allegato | LONGBLOB | PDF/immagine del piano firmato |

**Funzionalità chiave:**
- **Countdown scadenza** nella lista: badge verde (>30 gg), arancione (≤30 gg), rosso (scaduto), blu (indefinito)
- **Checkbox "Durata Indefinita"** disabilita data fine e mostra icona ∞ nel dettaglio
- **Viewer inline** nella pagina dettaglio: PDF via `<iframe embed>`, immagini con click-to-zoom
- **Sostituzione / rimozione file** in modifica senza perdere i dati testuali
- **Integrazione Cartella Clinica**: nuovo tab dedicato con stato e link al dettaglio
- **Integrazione Archivio Documenti**: i file dei piani appaiono nell'archivio globale

---

### 5.12 Archivio Documenti & Viewer

**`documents/index.php`** — Vista aggregata di tutti i file allegati da ogni modulo (visite, impegnative, ricoveri, invalidità, piani terapeutici), ordinati per data.

**`documents/view.php`** — Viewer integrato:
- **PDF:** visualizzato tramite `<iframe>` con sorgente base64 inline
- **Immagini:** visualizzate con `<img>` centrata, click per zoom full-size
- **Download:** header `Content-Disposition: attachment`
- **Embed mode** (`?embed=1`): risponde con il file raw (Content-Type + inline) per iframe interni
- Sicurezza: verifica MIME reale via `finfo` sul contenuto binario, non sull'estensione

---

## 6. Sicurezza

| Misura | Implementazione |
|--------|----------------|
| Password hashing | `password_hash()` bcrypt |
| CSRF protection | Token in sessione, `hash_equals()` su POST |
| Session fixation | `session_regenerate_id(true)` al login |
| XSS prevention | `htmlspecialchars()` via helper `h()` |
| SQL injection | PDO prepared statements ovunque |
| File upload | Verifica MIME reale con `finfo`, whitelist tipo, limite 20 MB |
| Ownership check | Ogni query verifica `membro_id` + `utente_id` |
| Cookie sicuri | `httponly=1`, `use_strict_mode=1` |

---

## 7. Helper Functions (`includes/config.php`)

| Funzione | Descrizione |
|----------|-------------|
| `db()` | Singleton PDO |
| `requireLogin()` | Redirect al login se non autenticato |
| `membroSelezionato()` | Recupera membro da sessione |
| `h(string)` | htmlspecialchars sicuro |
| `setFlash()` / `getFlash()` | Messaggi flash in sessione |
| `csrfToken()` / `verifyCsrf()` | Generazione e verifica token CSRF |
| `calcolaBMI(peso, altezza)` | Ritorna float BMI |
| `classificaBMI(bmi)` | Ritorna array `[label, class Bootstrap]` |
| `classificaPressione(sis, dia)` | Ritorna array `[label, class Bootstrap]` |
| `getMimeFromContent(data)` | Verifica MIME da contenuto binario |
| `dataITA(date)` | Formatta `Y-m-d` → `d/m/Y` |
| `dataOraITA(datetime)` | Formatta `Y-m-d H:i:s` → `d/m/Y H:i` |

---

## 8. Note di Deploy

### Produzione
1. Cambiare `APP_URL` con dominio reale
2. Settare `DB_PASS` sicura
3. Abilitare HTTPS e impostare `session.cookie_secure = 1`
4. Configurare `php.ini`:
   ```
   upload_max_filesize = 20M
   post_max_size = 22M
   max_execution_time = 60
   ```
5. Sostituire `@mail()` con PHPMailer + SMTP per e-mail affidabili
6. Considerare la migrazione dei file BLOB su filesystem con percorso salvato in DB per performance con grandi volumi

### Backup database
```bash
mysqldump -u root -p salute > salute_backup_$(date +%Y%m%d).sql
```

---

## 9. Changelog

### v1.0.2 — 2026-08-25
- **Aggiunto** modulo **Piani Terapeutici** (`modules/therapy_plans/`): CRUD completo,
  upload file firmato (PDF/immagine), toggle durata indefinita, badge stato con countdown scadenza,
  viewer inline con iframe PDF / click-to-zoom immagine nella pagina dettaglio
- **Aggiornata** sidebar: nuova voce "Piani Terapeutici" nella sezione Terapie
- **Aggiornata** Cartella Clinica: nuovo tab "Piani Terapeutici"
- **Aggiornato** archivio documenti: include file dei piani terapeutici
- **Aggiornato** document viewer (`view.php`): aggiunto modulo `piano_terapeutico`, modalità `embed=1`
- **Aggiornato** `database/salute.sql`: nuova tabella `piani_terapeutici`

### v1.0.1 — 2026-08-25
- **Aggiunto** `install.php`: wizard di installazione con form parametri DB + admin,
  esecuzione schema SQL, creazione utente admin, file lock di sicurezza

### v1.0.0 — 2026-08-25
- **Iniziale:** struttura completa del progetto
- Schema DB: 11 tabelle con FK cascade e utf8mb4
- Modulo Autenticazione: login, registrazione, verifica e-mail, reset password
- Modulo Membri Famiglia: CRUD completo
- Dashboard: statistiche, farmaci giorno, prossimi appuntamenti, ultimi vitali
- Modulo Appuntamenti: CRUD + DataTables
- Modulo Impegnative: CRUD + upload file
- Modulo Visite & Analisi: CRUD + filtro tipo + upload referto
- **Modulo Ricoveri & Lettere di Dimissione:** CRUD completo con lettera integrata
- **Cartella Clinica:** vista storica aggregata multi-tab
- **Medicinali CRUD + Orari:** piani di somministrazione dinamici + planning giornaliero
- Parametri Vitali: BMI live, classificazione pressione, storico
- Invalidità & Legge 104
- Document Viewer: PDF e immagini inline, download sicuro
- CSS stile Windows (Bootstrap 5, palette blue/grey/white)
- DataTables con export CSV, Excel, PDF
