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
