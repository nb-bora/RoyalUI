USE pharma;

CREATE TABLE IF NOT EXISTS `facture` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_vente` int(11) NOT NULL,
  `numero_facture` varchar(30) NOT NULL,
  `date_facture` timestamp NOT NULL DEFAULT current_timestamp(),
  `montant_ht` decimal(12,2) NOT NULL DEFAULT 0,
  `montant_tva` decimal(12,2) NOT NULL DEFAULT 0,
  `montant_ttc` decimal(12,2) NOT NULL DEFAULT 0,
  `taux_tva` decimal(5,2) NOT NULL DEFAULT 0,
  `mode_paiement` varchar(30) NOT NULL DEFAULT 'ESPECES',
  `statut` enum('emise','annulee') NOT NULL DEFAULT 'emise',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_facture` (`numero_facture`),
  UNIQUE KEY `id_vente` (`id_vente`),
  CONSTRAINT `fk_facture_vente` FOREIGN KEY (`id_vente`) REFERENCES `vente` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `parametres` ADD COLUMN `nif` varchar(50) DEFAULT NULL;
ALTER TABLE `parametres` ADD COLUMN `rccm` varchar(50) DEFAULT NULL;
ALTER TABLE `parametres` ADD COLUMN `mention_legale_facture` text DEFAULT NULL;
ALTER TABLE `parametres` ADD COLUMN `taux_tva` decimal(5,2) DEFAULT 0;
ALTER TABLE `parametres` ADD COLUMN `prefixe_facture` varchar(10) DEFAULT 'FA';

CREATE TABLE IF NOT EXISTS `import_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int(11) NOT NULL,
  `type_import` varchar(50) NOT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `lignes_ok` int(11) DEFAULT 0,
  `lignes_erreur` int(11) DEFAULT 0,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_import_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Factures rétroactives pour ventes validées existantes
INSERT INTO `facture` (`id_vente`, `numero_facture`, `date_facture`, `montant_ht`, `montant_tva`, `montant_ttc`, `taux_tva`, `mode_paiement`, `statut`)
SELECT v.id,
  CONCAT('FA-', YEAR(v.date_vente), '-', LPAD(v.id, 5, '0')),
  v.date_vente,
  COALESCE(v.montant_total, 0),
  0,
  COALESCE(v.montant_total, 0),
  0,
  'ESPECES',
  'emise'
FROM vente v
WHERE v.statut = 'validee'
AND NOT EXISTS (SELECT 1 FROM facture f WHERE f.id_vente = v.id);
