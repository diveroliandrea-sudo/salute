-- ============================================================
-- DATABASE: salute
-- Gestione Salute Familiare
-- Charset: utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `salute`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `salute`;

-- ------------------------------------------------------------
-- TABELLA: utenti (autenticazione)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `utenti` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`              VARCHAR(100)  NOT NULL,
  `cognome`           VARCHAR(100)  NOT NULL,
  `email`             VARCHAR(255)  NOT NULL UNIQUE,
  `password_hash`     VARCHAR(255)  NOT NULL,
  `token_conferma`    VARCHAR(64)   DEFAULT NULL COMMENT 'Token per conferma e-mail',
  `email_verificata`  TINYINT(1)    NOT NULL DEFAULT 0,
  `token_reset`       VARCHAR(64)   DEFAULT NULL COMMENT 'Token per recupero password',
  `token_reset_exp`   DATETIME      DEFAULT NULL,
  `attivo`            TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLA: membri_famiglia
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `membri_famiglia` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `utente_id`     INT UNSIGNED NOT NULL,
  `nome`          VARCHAR(100) NOT NULL,
  `cognome`       VARCHAR(100) NOT NULL,
  `data_nascita`  DATE         DEFAULT NULL,
  `sesso`         ENUM('M','F','Altro') DEFAULT NULL,
  `codice_fiscale` VARCHAR(16) DEFAULT NULL,
  `relazione`     VARCHAR(100) DEFAULT NULL COMMENT 'es. Figlio, Coniuge, Genitore',
  `medico_base`   VARCHAR(200) DEFAULT NULL,
  `note`          TEXT         DEFAULT NULL,
  `avatar`        VARCHAR(255) DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_mf_utente` (`utente_id`),
  CONSTRAINT `fk_mf_utente` FOREIGN KEY (`utente_id`) REFERENCES `utenti` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLA: invalidita (Legge 104)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invalidita` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `membro_id`         INT UNSIGNED NOT NULL,
  `tipo`              VARCHAR(100) NOT NULL COMMENT 'es. Invalidità Civile, Legge 104',
  `percentuale`       TINYINT UNSIGNED DEFAULT NULL COMMENT 'Percentuale invalidità 0-100',
  `legge_104_grado`   ENUM('1','2','3') DEFAULT NULL,
  `data_verbale`      DATE         DEFAULT NULL,
  `data_scadenza`     DATE         DEFAULT NULL,
  `commissione`       VARCHAR(200) DEFAULT NULL,
  `benefici_attivi`   TEXT         DEFAULT NULL,
  `note`              TEXT         DEFAULT NULL,
  `file_nome`         VARCHAR(255) DEFAULT NULL,
  `file_mime`         VARCHAR(100) DEFAULT NULL,
  `file_dati`         LONGBLOB     DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_inv_membro` (`membro_id`),
  CONSTRAINT `fk_inv_membro` FOREIGN KEY (`membro_id`) REFERENCES `membri_famiglia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLA: appuntamenti
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appuntamenti` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `membro_id`     INT UNSIGNED NOT NULL,
  `titolo`        VARCHAR(255) NOT NULL,
  `tipo`          VARCHAR(100) DEFAULT NULL COMMENT 'es. Visita, Analisi, Controllo',
  `data_ora`      DATETIME     NOT NULL,
  `luogo`         VARCHAR(255) DEFAULT NULL,
  `medico`        VARCHAR(200) DEFAULT NULL,
  `struttura`     VARCHAR(255) DEFAULT NULL,
  `stato`         ENUM('programmato','completato','annullato','rinviato') NOT NULL DEFAULT 'programmato',
  `note`          TEXT         DEFAULT NULL,
  `promemoria`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_app_membro` (`membro_id`),
  CONSTRAINT `fk_app_membro` FOREIGN KEY (`membro_id`) REFERENCES `membri_famiglia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLA: impegnative (ricette mediche)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `impegnative` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `membro_id`         INT UNSIGNED NOT NULL,
  `codice_nre`        VARCHAR(50)  DEFAULT NULL,
  `tipo_priorita`     ENUM('U','B','P','D') NOT NULL DEFAULT 'D'
                      COMMENT 'U=Urgente, B=Breve, P=Programmabile, D=Differibile',
  `medico_prescrivente` VARCHAR(200) DEFAULT NULL,
  `prestazione`       VARCHAR(255) DEFAULT NULL,
  `data_prescrizione` DATE         DEFAULT NULL,
  `data_scadenza`     DATE         DEFAULT NULL,
  `stato`             ENUM('da_utilizzare','utilizzata','scaduta') NOT NULL DEFAULT 'da_utilizzare',
  `note`              TEXT         DEFAULT NULL,
  `file_nome`         VARCHAR(255) DEFAULT NULL,
  `file_mime`         VARCHAR(100) DEFAULT NULL,
  `file_dati`         LONGBLOB     DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_imp_membro` (`membro_id`),
  CONSTRAINT `fk_imp_membro` FOREIGN KEY (`membro_id`) REFERENCES `membri_famiglia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLA: visite_specialistiche
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `visite_specialistiche` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `membro_id`     INT UNSIGNED NOT NULL,
  `tipo`          ENUM('specialistica','analisi','strumentale') NOT NULL DEFAULT 'specialistica',
  `specialita`    VARCHAR(200) DEFAULT NULL,
  `data_visita`   DATE         NOT NULL,
  `medico`        VARCHAR(200) DEFAULT NULL,
  `struttura`     VARCHAR(255) DEFAULT NULL,
  `diagnosi`      TEXT         DEFAULT NULL,
  `esito`         TEXT         DEFAULT NULL,
  `terapia_consigliata` TEXT   DEFAULT NULL,
  `note`          TEXT         DEFAULT NULL,
  `file_nome`     VARCHAR(255) DEFAULT NULL,
  `file_mime`     VARCHAR(100) DEFAULT NULL,
  `file_dati`     LONGBLOB     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_vis_membro` (`membro_id`),
  CONSTRAINT `fk_vis_membro` FOREIGN KEY (`membro_id`) REFERENCES `membri_famiglia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLA: ricoveri
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ricoveri` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `membro_id`           INT UNSIGNED NOT NULL,
  `struttura`           VARCHAR(255) NOT NULL COMMENT 'Ospedale / Clinica',
  `reparto`             VARCHAR(200) DEFAULT NULL,
  `data_ingresso`       DATE         NOT NULL,
  `data_dimissione`     DATE         DEFAULT NULL,
  `diagnosi_ingresso`   TEXT         DEFAULT NULL,
  `motivo_ricovero`     TEXT         DEFAULT NULL,
  `diagnosi_dimissione` TEXT         DEFAULT NULL,
  `terapia_dimissione`  TEXT         DEFAULT NULL COMMENT 'Terapia consigliata al rientro',
  `medico_responsabile` VARCHAR(200) DEFAULT NULL,
  `note`                TEXT         DEFAULT NULL,
  -- Lettera di dimissione allegata
  `lettera_file_nome`   VARCHAR(255) DEFAULT NULL,
  `lettera_file_mime`   VARCHAR(100) DEFAULT NULL,
  `lettera_file_dati`   LONGBLOB     DEFAULT NULL,
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_ric_membro` (`membro_id`),
  CONSTRAINT `fk_ric_membro` FOREIGN KEY (`membro_id`) REFERENCES `membri_famiglia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLA: farmaci (anagrafica medicinali)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `farmaci` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `membro_id`       INT UNSIGNED NOT NULL,
  `nome_farmaco`    VARCHAR(255) NOT NULL,
  `principio_attivo` VARCHAR(255) DEFAULT NULL,
  `dosaggio`        VARCHAR(100) DEFAULT NULL COMMENT 'es. 500mg, 1 compressa',
  `forma`           VARCHAR(100) DEFAULT NULL COMMENT 'es. compressa, sciroppo, fiala',
  `note_assunzione` TEXT         DEFAULT NULL COMMENT 'es. a stomaco pieno, con acqua',
  `data_inizio`     DATE         DEFAULT NULL,
  `data_fine`       DATE         DEFAULT NULL,
  `cronico`         TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=Terapia cronica/indefinita',
  `attivo`          TINYINT(1)   NOT NULL DEFAULT 1,
  `note`            TEXT         DEFAULT NULL,
  `scontrino_file_nome` VARCHAR(255) DEFAULT NULL COMMENT 'Nome file scontrino/ricevuta',
  `scontrino_file_mime` VARCHAR(100) DEFAULT NULL,
  `scontrino_file_dati` LONGBLOB     DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_farm_membro` (`membro_id`),
  CONSTRAINT `fk_farm_membro` FOREIGN KEY (`membro_id`) REFERENCES `membri_famiglia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLA: farmaci_orari (piani di somministrazione)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `farmaci_orari` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `farmaco_id`  INT UNSIGNED NOT NULL,
  `ora`         TIME         NOT NULL COMMENT 'Orario di somministrazione es. 08:00',
  `quantita`    VARCHAR(50)  DEFAULT '1' COMMENT 'es. 1, 1/2, 2 compresse',
  `giorni`      VARCHAR(100) DEFAULT 'tutti' COMMENT 'es. tutti, lun-mer-ven, 1-3-5',
  `note`        VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_fo_farmaco` (`farmaco_id`),
  CONSTRAINT `fk_fo_farmaco` FOREIGN KEY (`farmaco_id`) REFERENCES `farmaci` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLA: parametri_vitali
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `parametri_vitali` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `membro_id`         INT UNSIGNED NOT NULL,
  `data_ora`          DATETIME     NOT NULL,
  `peso_kg`           DECIMAL(5,2) DEFAULT NULL,
  `altezza_cm`        DECIMAL(5,1) DEFAULT NULL,
  `bmi`               DECIMAL(4,2) DEFAULT NULL COMMENT 'Calcolato automaticamente',
  `pressione_sistolica`  SMALLINT UNSIGNED DEFAULT NULL,
  `pressione_diastolica` SMALLINT UNSIGNED DEFAULT NULL,
  `frequenza_cardiaca`   SMALLINT UNSIGNED DEFAULT NULL COMMENT 'bpm',
  `temperatura`       DECIMAL(4,1) DEFAULT NULL COMMENT 'Gradi Celsius',
  `saturazione_o2`    TINYINT UNSIGNED DEFAULT NULL COMMENT 'SpO2 %',
  `glicemia`          SMALLINT UNSIGNED DEFAULT NULL COMMENT 'mg/dL',
  `note`              TEXT         DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pv_membro` (`membro_id`),
  CONSTRAINT `fk_pv_membro` FOREIGN KEY (`membro_id`) REFERENCES `membri_famiglia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLA: documenti (archiviazione generica documenti)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documenti` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `membro_id`   INT UNSIGNED NOT NULL,
  `modulo`      VARCHAR(50)  NOT NULL COMMENT 'es. visita, analisi, ricovero, altro',
  `riferimento_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID del record padre',
  `titolo`      VARCHAR(255) NOT NULL,
  `descrizione` TEXT         DEFAULT NULL,
  `file_nome`   VARCHAR(255) NOT NULL,
  `file_mime`   VARCHAR(100) NOT NULL,
  `file_size`   INT UNSIGNED DEFAULT NULL COMMENT 'Dimensione in byte',
  `file_dati`   LONGBLOB     NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_doc_membro` (`membro_id`),
  CONSTRAINT `fk_doc_membro` FOREIGN KEY (`membro_id`) REFERENCES `membri_famiglia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABELLA: piani_terapeutici
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `piani_terapeutici` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `membro_id`         INT UNSIGNED  NOT NULL,
  `titolo`            VARCHAR(255)  NOT NULL COMMENT 'es. Terapia post-operatoria, Piano BPCO',
  `tipo`              VARCHAR(100)  DEFAULT NULL COMMENT 'es. Farmacologica, Riabilitativa, Oncologica',
  `medico_redattore`  VARCHAR(200)  DEFAULT NULL,
  `struttura`         VARCHAR(255)  DEFAULT NULL,
  `data_inizio`       DATE          NOT NULL,
  `data_fine`         DATE          DEFAULT NULL COMMENT 'NULL = durata indefinita',
  `obiettivo`         TEXT          DEFAULT NULL,
  `descrizione`       TEXT          DEFAULT NULL COMMENT 'Dettaglio del piano / indicazioni',
  `farmaci_previsti`  TEXT          DEFAULT NULL COMMENT 'Elenco sintetico farmaci del piano',
  `note`              TEXT          DEFAULT NULL,
  `stato`             ENUM('attivo','completato','sospeso','revocato') NOT NULL DEFAULT 'attivo',
  -- File allegato (es. PDF del piano terapeutico firmato)
  `file_nome`         VARCHAR(255)  DEFAULT NULL,
  `file_mime`         VARCHAR(100)  DEFAULT NULL,
  `file_dati`         LONGBLOB      DEFAULT NULL,
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pt_membro` (`membro_id`),
  CONSTRAINT `fk_pt_membro` FOREIGN KEY (`membro_id`)
    REFERENCES `membri_famiglia` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- ------------------------------------------------------------
-- TABELLA: peso_diario (Dieta & Peso)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `peso_diario` (
  `id`          INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `membro_id`   INT UNSIGNED      NOT NULL,
  `data`        DATE              NOT NULL,
  `ora`         TIME              NOT NULL,
  `peso_kg`     DECIMAL(5,2)      NOT NULL,
  `altezza_cm`  DECIMAL(5,1)          NULL DEFAULT NULL,
  `bmi`         DECIMAL(5,2)      NOT NULL,
  `note`        TEXT                  NULL DEFAULT NULL,
  `created_at`  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME              NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_peso_membro` (`membro_id`),
  KEY `idx_peso_data`   (`membro_id`, `data` DESC),
  CONSTRAINT `fk_peso_membro`
    FOREIGN KEY (`membro_id`) REFERENCES `membri_famiglia` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
