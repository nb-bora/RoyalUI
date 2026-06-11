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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_vente_client` (`id_client`),
  CONSTRAINT `fk_vente_client` FOREIGN KEY (`id_client`) REFERENCES `client` (`id`) ON DELETE SET NULL
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
  PRIMARY KEY (`id`)
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

COMMIT;
