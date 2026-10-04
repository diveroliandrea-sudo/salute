-- ============================================================
-- Migration: farmaci_somministrazioni
-- Registra ogni somministrazione effettiva di un farmaco
-- ============================================================

CREATE TABLE IF NOT EXISTS `farmaci_somministrazioni` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `farmaco_id`  INT UNSIGNED NOT NULL,
  `data_ora`    DATETIME     NOT NULL COMMENT 'Data e ora della somministrazione',
  `quantita`    VARCHAR(50)  NOT NULL DEFAULT '1' COMMENT 'es. 1, 1/2, 2 compresse',
  `note`        VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fs_farmaco` (`farmaco_id`),
  KEY `idx_fs_data_ora` (`data_ora`),
  CONSTRAINT `fk_fs_farmaco`
    FOREIGN KEY (`farmaco_id`)
    REFERENCES `farmaci` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
