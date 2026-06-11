-- PharmaRoyal v2 — Migration autonomie intelligente
-- Exécuter : php install/migrate.php  OU  importer dans phpMyAdmin

USE pharma;

-- Vente : vendeur, statut, montant
ALTER TABLE `vente`
  ADD COLUMN IF NOT EXISTS `id_utilisateur` int(11) DEFAULT NULL AFTER `id_client`,
  ADD COLUMN IF NOT EXISTS `statut` enum('validee','annulee') NOT NULL DEFAULT 'validee' AFTER `date_vente`,
  ADD COLUMN IF NOT EXISTS `montant_total` decimal(12,2) NOT NULL DEFAULT 0 AFTER `statut`;

-- Paramètres étendus
ALTER TABLE `parametres`
  ADD COLUMN IF NOT EXISTS `seuil_marge_min` decimal(5,2) DEFAULT 15.00,
  ADD COLUMN IF NOT EXISTS `seuil_peremption_jours` int(11) DEFAULT 30,
  ADD COLUMN IF NOT EXISTS `objectif_ca_jour` decimal(12,2) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `delai_fournisseur_jours` int(11) DEFAULT 3,
  ADD COLUMN IF NOT EXISTS `email_alerte` varchar(150) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int(11) DEFAULT NULL,
  `role_cible` varchar(20) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `priorite` enum('critique','haute','info') NOT NULL DEFAULT 'info',
  `titre` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `lien_action` varchar(255) DEFAULT NULL,
  `id_reference` int(11) DEFAULT NULL,
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expire_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_notif_user` (`id_utilisateur`),
  KEY `idx_notif_lu` (`lu`,`created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recommandation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `id_medicament` int(11) DEFAULT NULL,
  `id_fournisseur` int(11) DEFAULT NULL,
  `priorite` int(11) NOT NULL DEFAULT 50,
  `score` decimal(10,2) NOT NULL DEFAULT 0,
  `titre` varchar(200) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `statut` enum('nouvelle','vue','appliquee','ignoree') NOT NULL DEFAULT 'nouvelle',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_reco_med` (`id_medicament`),
  KEY `fk_reco_four` (`id_fournisseur`),
  CONSTRAINT `fk_reco_med` FOREIGN KEY (`id_medicament`) REFERENCES `medicament` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reco_four` FOREIGN KEY (`id_fournisseur`) REFERENCES `fournisseur` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `regle_metier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cle` varchar(100) NOT NULL,
  `valeur` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lot_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_medicament` int(11) NOT NULL,
  `id_ligne_achat` int(11) DEFAULT NULL,
  `quantite_initiale` int(11) NOT NULL,
  `quantite_restante` int(11) NOT NULL,
  `date_peremption` date NOT NULL,
  `prix_achat` decimal(10,2) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_lot_med` (`id_medicament`),
  KEY `fk_lot_la` (`id_ligne_achat`),
  KEY `idx_lot_fefo` (`id_medicament`,`date_peremption`,`quantite_restante`),
  CONSTRAINT `fk_lot_med` FOREIGN KEY (`id_medicament`) REFERENCES `medicament` (`id`),
  CONSTRAINT `fk_lot_la` FOREIGN KEY (`id_ligne_achat`) REFERENCES `ligne_achat` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ligne_vente_lot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ligne_vente` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lvl_lv` (`id_ligne_vente`),
  KEY `fk_lvl_lot` (`id_lot`),
  CONSTRAINT `fk_lvl_lv` FOREIGN KEY (`id_ligne_vente`) REFERENCES `ligne_vente` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lvl_lot` FOREIGN KEY (`id_lot`) REFERENCES `lot_stock` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `utilisateur_preferences` (
  `id_utilisateur` int(11) NOT NULL,
  `page_accueil` varchar(100) DEFAULT 'home.html',
  `theme` varchar(20) DEFAULT 'light',
  `notifications_email` tinyint(1) DEFAULT 0,
  `seuils_personnels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`seuils_personnels`)),
  PRIMARY KEY (`id_utilisateur`),
  CONSTRAINT `fk_pref_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activite_utilisateur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int(11) NOT NULL,
  `action` varchar(100) DEFAULT NULL,
  `page` varchar(100) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_act_user` (`id_utilisateur`),
  KEY `idx_act_date` (`created_at`),
  CONSTRAINT `fk_act_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `session_caisse` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int(11) NOT NULL,
  `ouverture` timestamp NOT NULL DEFAULT current_timestamp(),
  `cloture` timestamp NULL DEFAULT NULL,
  `fond_caisse` decimal(12,2) NOT NULL DEFAULT 0,
  `ca_theorique` decimal(12,2) NOT NULL DEFAULT 0,
  `ca_reel` decimal(12,2) DEFAULT NULL,
  `ecart` decimal(12,2) DEFAULT NULL,
  `statut` enum('ouverte','cloturee') NOT NULL DEFAULT 'ouverte',
  PRIMARY KEY (`id`),
  KEY `fk_sess_user` (`id_utilisateur`),
  CONSTRAINT `fk_sess_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventaire` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int(11) NOT NULL,
  `date_inventaire` timestamp NOT NULL DEFAULT current_timestamp(),
  `statut` enum('brouillon','valide') NOT NULL DEFAULT 'brouillon',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_inv_user` (`id_utilisateur`),
  CONSTRAINT `fk_inv_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ligne_inventaire` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_inventaire` int(11) NOT NULL,
  `id_medicament` int(11) NOT NULL,
  `stock_theorique` int(11) NOT NULL,
  `stock_reel` int(11) NOT NULL,
  `ecart` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_li_inv` (`id_inventaire`),
  KEY `fk_li_med` (`id_medicament`),
  CONSTRAINT `fk_li_inv` FOREIGN KEY (`id_inventaire`) REFERENCES `inventaire` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_li_med` FOREIGN KEY (`id_medicament`) REFERENCES `medicament` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bon_commande` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_fournisseur` int(11) NOT NULL,
  `id_utilisateur` int(11) DEFAULT NULL,
  `statut` enum('brouillon','envoye','recu','annule') NOT NULL DEFAULT 'brouillon',
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bc_four` (`id_fournisseur`),
  KEY `fk_bc_user` (`id_utilisateur`),
  CONSTRAINT `fk_bc_four` FOREIGN KEY (`id_fournisseur`) REFERENCES `fournisseur` (`id`),
  CONSTRAINT `fk_bc_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ligne_bon_commande` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_bon_commande` int(11) NOT NULL,
  `id_medicament` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_estime` decimal(10,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_lbc_bc` (`id_bon_commande`),
  KEY `fk_lbc_med` (`id_medicament`),
  CONSTRAINT `fk_lbc_bc` FOREIGN KEY (`id_bon_commande`) REFERENCES `bon_commande` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lbc_med` FOREIGN KEY (`id_medicament`) REFERENCES `medicament` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Règles métier par défaut
INSERT IGNORE INTO `regle_metier` (`cle`, `valeur`, `description`) VALUES
('seuil_marge_min', '15', 'Alerte si marge % inférieure'),
('seuil_peremption_urgent', '7', 'Péremption urgente (jours)'),
('seuil_rupture_heures', '24', 'Rupture imminente selon vélocité'),
('seuil_surstock_ratio', '3', 'Stock > ratio × stock_min = surstock'),
('stock_dormant_jours', '90', 'Jours sans vente = stock dormant');

-- Lots depuis achats existants (si pas déjà créés)
INSERT INTO `lot_stock` (`id_medicament`, `id_ligne_achat`, `quantite_initiale`, `quantite_restante`, `date_peremption`, `prix_achat`)
SELECT la.id_medicament, la.id, la.quantite, la.quantite, la.date_peremption, la.prix_achat
FROM ligne_achat la
WHERE NOT EXISTS (SELECT 1 FROM lot_stock ls WHERE ls.id_ligne_achat = la.id);

-- Lots synthétiques pour médicaments sans achat (stock actuel)
INSERT INTO `lot_stock` (`id_medicament`, `id_ligne_achat`, `quantite_initiale`, `quantite_restante`, `date_peremption`, `prix_achat`)
SELECT m.id, NULL, m.stock_actuel, m.stock_actuel, DATE_ADD(CURDATE(), INTERVAL 1 YEAR), m.prix_achat
FROM medicament m
WHERE m.actif = 1 AND m.stock_actuel > 0
AND NOT EXISTS (SELECT 1 FROM lot_stock ls WHERE ls.id_medicament = m.id AND ls.quantite_restante > 0);

-- Préférences utilisateurs existants
INSERT IGNORE INTO `utilisateur_preferences` (`id_utilisateur`, `page_accueil`)
SELECT id, CASE role WHEN 'vendeur' THEN 'caisse.html' WHEN 'gestionnaire' THEN 'home.html' ELSE 'home.html' END
FROM utilisateur;
