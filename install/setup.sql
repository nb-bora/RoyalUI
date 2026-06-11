-- PharmaRoyal - Installation complète
CREATE DATABASE IF NOT EXISTS pharma CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pharma;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

CREATE TABLE IF NOT EXISTS `categorie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fournisseur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `client` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('admin','gestionnaire','vendeur') DEFAULT 'vendeur',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `medicament` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(150) NOT NULL,
  `prix_achat` decimal(10,2) NOT NULL DEFAULT 0,
  `prix_vente` decimal(10,2) NOT NULL DEFAULT 0,
  `stock_actuel` int(11) DEFAULT 0,
  `stock_min` int(11) DEFAULT 5,
  `id_categorie` int(11) DEFAULT NULL,
  `code_barre` varchar(50) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_medicament_categorie` (`id_categorie`),
  CONSTRAINT `fk_medicament_categorie` FOREIGN KEY (`id_categorie`) REFERENCES `categorie` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_vente` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_client` int(11) DEFAULT NULL,
  `id_utilisateur` int(11) DEFAULT NULL,
  `statut` enum('validee','annulee') NOT NULL DEFAULT 'validee',
  `montant_total` decimal(12,2) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_vente_client` (`id_client`),
  KEY `fk_vente_user` (`id_utilisateur`),
  CONSTRAINT `fk_vente_client` FOREIGN KEY (`id_client`) REFERENCES `client` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vente_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ligne_vente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_vente` int(11) NOT NULL,
  `id_medicament` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_vente` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ligne_vente_vente` (`id_vente`),
  KEY `fk_ligne_vente_medicament` (`id_medicament`),
  CONSTRAINT `fk_ligne_vente_medicament` FOREIGN KEY (`id_medicament`) REFERENCES `medicament` (`id`),
  CONSTRAINT `fk_ligne_vente_vente` FOREIGN KEY (`id_vente`) REFERENCES `vente` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `achat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_achat` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_fournisseur` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_achat_fournisseur` (`id_fournisseur`),
  CONSTRAINT `fk_achat_fournisseur` FOREIGN KEY (`id_fournisseur`) REFERENCES `fournisseur` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ligne_achat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_achat` int(11) NOT NULL,
  `id_medicament` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  `prix_achat` decimal(10,2) NOT NULL,
  `date_peremption` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ligne_achat_achat` (`id_achat`),
  KEY `fk_ligne_achat_medicament` (`id_medicament`),
  CONSTRAINT `fk_ligne_achat_achat` FOREIGN KEY (`id_achat`) REFERENCES `achat` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ligne_achat_medicament` FOREIGN KEY (`id_medicament`) REFERENCES `medicament` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mouvement_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_medicament` int(11) NOT NULL,
  `id_reference` int(11) NOT NULL DEFAULT 0,
  `type_mouvement` varchar(20) NOT NULL,
  `quantite` int(11) NOT NULL,
  `date_mouvement` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_mouvement_stock_medicament` (`id_medicament`),
  CONSTRAINT `fk_mouvement_stock_medicament` FOREIGN KEY (`id_medicament`) REFERENCES `medicament` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `parametres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom_pharmacie` varchar(150) DEFAULT 'PharmaRoyal',
  `adresse` text DEFAULT NULL,
  `telephone` varchar(50) DEFAULT NULL,
  `devise` varchar(10) DEFAULT 'FCFA',
  `seuil_marge_min` decimal(5,2) DEFAULT 15.00,
  `seuil_peremption_jours` int(11) DEFAULT 30,
  `objectif_ca_jour` decimal(12,2) DEFAULT 0,
  `delai_fournisseur_jours` int(11) DEFAULT 3,
  `email_alerte` varchar(150) DEFAULT NULL,
  `nif` varchar(50) DEFAULT NULL,
  `rccm` varchar(50) DEFAULT NULL,
  `mention_legale_facture` text DEFAULT NULL,
  `taux_tva` decimal(5,2) DEFAULT 0,
  `prefixe_facture` varchar(10) DEFAULT 'FA',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_cible` varchar(50) DEFAULT NULL,
  `id_cible` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  CONSTRAINT `fk_lot_med` FOREIGN KEY (`id_medicament`) REFERENCES `medicament` (`id`),
  CONSTRAINT `fk_lot_la` FOREIGN KEY (`id_ligne_achat`) REFERENCES `ligne_achat` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ligne_vente_lot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_ligne_vente` int(11) NOT NULL,
  `id_lot` int(11) NOT NULL,
  `quantite` int(11) NOT NULL,
  PRIMARY KEY (`id`),
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
  CONSTRAINT `fk_sess_user` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventaire` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_utilisateur` int(11) NOT NULL,
  `date_inventaire` timestamp NOT NULL DEFAULT current_timestamp(),
  `statut` enum('brouillon','valide') NOT NULL DEFAULT 'brouillon',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
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
  CONSTRAINT `fk_lbc_bc` FOREIGN KEY (`id_bon_commande`) REFERENCES `bon_commande` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lbc_med` FOREIGN KEY (`id_medicament`) REFERENCES `medicament` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `regle_metier` (`cle`, `valeur`, `description`) VALUES
('seuil_marge_min', '15', 'Alerte si marge % inférieure'),
('seuil_peremption_urgent', '7', 'Péremption urgente (jours)'),
('seuil_rupture_heures', '24', 'Rupture imminente selon vélocité'),
('seuil_surstock_ratio', '3', 'Stock > ratio × stock_min = surstock'),
('stock_dormant_jours', '90', 'Jours sans vente = stock dormant');

COMMIT;
