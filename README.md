# PharmaRoyal

**Système intelligent de gestion de pharmacie** — application web complète pour la vente au comptoir, la gestion des stocks (FEFO), les achats fournisseurs, le pilotage d'activité et l'aide à la décision.

PharmaRoyal combine un **backend PHP/MySQL** (API REST + moteur de décision), un **frontend** basé sur le thème **RoyalUI** (Bootstrap), un design system métier (`pharma.css`) et des modules JavaScript modulaires. Le système couvre les opérations quotidiennes **et** l'autonomie opérationnelle de niveau 2–3 : notifications persistantes, recommandations actionnables, briefing par rôle et tâches planifiées.

---

## Table des matières

1. [Aperçu fonctionnel](#aperçu-fonctionnel)
2. [Niveaux d'autonomie](#niveaux-dautonomie)
3. [Stack technique](#stack-technique)
4. [Architecture](#architecture)
5. [Prérequis](#prérequis)
6. [Installation et migration](#installation-et-migration)
7. [Configuration](#configuration)
8. [Tâches planifiées (cron)](#tâches-planifiées-cron)
9. [Base de données](#base-de-données)
10. [Authentification et rôles](#authentification-et-rôles)
11. [Pages de l'application](#pages-de-lapplication)
12. [API REST](#api-rest)
13. [Moteur de décision](#moteur-de-décision)
14. [Structure du projet](#structure-du-projet)
15. [Modules JavaScript](#modules-javascript)
16. [Design system](#design-system)
17. [Logique métier](#logique-métier)
18. [Comptes de démonstration](#comptes-de-démonstration)
19. [Parcours utilisateur](#parcours-utilisateur)
20. [Sécurité](#sécurité)
21. [Limitations et roadmap](#limitations-et-roadmap)
22. [Dépannage](#dépannage)
23. [Crédits et licence](#crédits-et-licence)

---

## Aperçu fonctionnel

### Opérations quotidiennes

| Domaine | Fonctionnalités |
|---------|-----------------|
| **Tableau de bord** | Briefing personnalisé par rôle, KPI, graphique CA 7 jours, top produits, recommandations intelligentes |
| **Caisse (POS)** | Recherche, favoris vendeur, panier, FEFO à la vente, ticket imprimable, session caisse, clôture journalière |
| **Médicaments** | CRUD catalogue, marge, code-barres, seuil minimum, filtres stock |
| **Catégories** | CRUD des familles de produits |
| **Stock** | KPI et filtres, barres de niveau, ajustements manuels, historique mouvements, **inventaire physique** |
| **Ventes** | Historique, vendeur, statut, détail, **annulation** avec remise en stock, impression ticket |
| **Achats** | Réception multi-lignes, péremption, création de **lots FEFO** |
| **Fournisseurs** | CRUD, statistiques réceptions et volume d'achats |
| **Clients** | CRUD, association optionnelle aux ventes |
| **Rapports** | CA période, top ventes, marges par catégorie, stock dormant, **export CSV** |
| **Utilisateurs** | Gestion des comptes et rôles (admin) |
| **Paramètres** | Seuils, objectif CA, délai fournisseur, règles métier (admin) |
| **Audit** | Journal des actions sensibles avec statistiques (admin) |

### Intelligence et autonomie

| Domaine | Fonctionnalités |
|---------|-----------------|
| **Notifications** | Persistance en base, priorité (critique / haute / info), lu/non lu, cloche navbar, polling 30 s |
| **Recommandations** | Réappro, commandes suggérées, promos péremption — statut nouvelle/vue/appliquée/ignorée |
| **DecisionEngine** | Rupture imminente (vélocité), surstock, marge incohérente, péremption urgente, stock dormant, objectif CA, escalade |
| **Bons de commande** | Création brouillon en 1 clic depuis une recommandation |
| **Briefing login** | Salutation, stats vendeur, favoris caisse, alertes gestionnaire, liens d'action |
| **Personnalisation** | Page d'accueil par rôle/préférences, activité utilisateur, traçage vendeur sur chaque vente |

---

## Niveaux d'autonomie

| Niveau | Description | État PharmaRoyal |
|--------|-------------|------------------|
| **0 — Enregistrement** | Ventes, stock, achats | ✅ Complet |
| **1 — Réaction** | Blocage stock, badges, alertes | ✅ Complet |
| **2 — Recommandation** | Suggestions + bouton appliquer | ✅ Complet |
| **3 — Semi-auto** | BC brouillon, cron, clôture caisse | ✅ Partiel |
| **4 — Auto contrôlé** | Commandes sans intervention, ML | ❌ Non prévu |

Le moteur de décision s'exécute à la **connexion**, à chaque **vente/achat**, et via **cron horaire**.

---

## Stack technique

| Couche | Technologies |
|--------|--------------|
| **Serveur** | Apache (XAMPP), PHP 8+ (`declare(strict_types=1)`) |
| **Base de données** | MySQL / MariaDB, charset `utf8mb4` |
| **API** | REST JSON, routage `api/index.php?r={ressource}/{id}/{action}` |
| **Services métier** | PHP classes dans `api/services/` |
| **Sessions** | PHP (`pharma_session`), `credentials: 'include'` |
| **Frontend** | HTML5, Bootstrap (RoyalUI), jQuery, DataTables, Chart.js, SweetAlert2 |
| **Icônes** | Themify Icons |
| **Devise** | FCFA (configurable via `parametres`) |

**Dépendances CDN** : SweetAlert2 v11, DataTables v1.13.6 (fr-FR), Chart.js (local).

---

## Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│  Navigateur                                                       │
│  index.html → home.html, caisse.html, stock.html, parametres.html…  │
│  PharmaAPI · PharmaAuth · PharmaLayout · PharmaNotifications       │
└────────────────────────────┬─────────────────────────────────────┘
                             │ fetch() JSON + cookie session
                             ▼
┌──────────────────────────────────────────────────────────────────┐
│  api/index.php → routes/*.php                                     │
│  helpers.php · config.php                                         │
└────────────────────────────┬─────────────────────────────────────┘
                             │
         ┌───────────────────┼───────────────────┐
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ DecisionEngine  │ │ NotificationSvc │ │ LotStockService │
│ ParametresSvc   │ │ ActiviteService │ │ (FEFO)          │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         └───────────────────┼───────────────────┘
                             ▼ PDO
┌──────────────────────────────────────────────────────────────────┐
│  MySQL — base `pharma` (22 tables)                                │
└──────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────┐
│  api/cron/run_decisions.php · run_escalations.php (planifiés)     │
└──────────────────────────────────────────────────────────────────┘
```

**Exemples de routes** :

| Méthode | URL |
|---------|-----|
| POST | `api/index.php?r=auth/login` |
| GET | `api/index.php?r=notifications` |
| GET | `api/index.php?r=briefing` |
| POST | `api/index.php?r=ventes/42/annuler` |
| GET | `api/index.php?r=tickets/42` |
| POST | `api/index.php?r=recommandations/executer` |

---

## Prérequis

- **XAMPP** (Apache + PHP + MySQL) ou équivalent
- **PHP** 8.0+ avec `pdo_mysql`, `json`, `session`
- **MySQL** 5.7+ / MariaDB 10.3+
- Navigateur moderne (Chrome, Firefox, Edge)

---

## Installation et migration

### Nouvelle installation

1. Copier le projet dans `C:\xampp\htdocs\RoyalUI\`
2. Démarrer **Apache** et **MySQL**
3. Ouvrir :

```
http://localhost/RoyalUI/install/seed.php
```

Le script exécute `install/setup.sql` (schéma complet v2), insère les données de démo, puis lance `migrate.php` (lots FEFO, préférences utilisateurs).

### Mise à jour d'une base existante

Si PharmaRoyal était déjà installé **avant la v2**, exécuter uniquement :

```
http://localhost/RoyalUI/install/migrate.php
```

ou en CLI :

```bash
C:\xampp\php\php.exe install\migrate.php
```

`migrate_v2.sql` ajoute colonnes, tables intelligence et rétroactive les lots depuis les achats existants.

### Accès application

```
http://localhost/RoyalUI/
```

→ `index.html` (connexion). Après login, redirection selon le rôle (`caisse.html` pour vendeur par défaut).

---

## Configuration

Fichier : **`api/config.php`**

| Constante | Défaut | Description |
|-----------|--------|-------------|
| `DB_HOST` | `127.0.0.1` | Hôte MySQL |
| `DB_NAME` | `pharma` | Base de données |
| `DB_USER` | `root` | Utilisateur MySQL |
| `DB_PASS` | `''` | Mot de passe (vide sous XAMPP) |
| `DB_CHARSET` | `utf8mb4` | Encodage |
| `SESSION_NAME` | `pharma_session` | Nom de session PHP |
| `CORS_ORIGIN` | `*` | En-tête CORS |

**Paramètres métier** (interface `parametres.html` ou table `parametres` + `regle_metier`) :

| Paramètre | Usage |
|-----------|-------|
| `seuil_marge_min` | Alerte prix incohérent |
| `seuil_peremption_jours` | Fenêtre alertes péremption |
| `objectif_ca_jour` | Notification objectif CA |
| `delai_fournisseur_jours` | Calcul rupture imminente |
| `email_alerte` | Email pour alertes (prêt SMTP) |
| `regle_metier.*` | Péremption urgente, surstock, stock dormant |

---

## Tâches planifiées (cron)

Sans cron, les alertes ne sont générées qu'à l'ouverture de l'app ou lors des ventes/achats.

### Windows (Planificateur de tâches)

```bat
schtasks /create /tn "PharmaRoyalDecisions" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\RoyalUI\api\cron\run_decisions.php" /sc hourly

schtasks /create /tn "PharmaRoyalEscalations" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\RoyalUI\api\cron\run_escalations.php" /sc daily
```

### Linux (crontab)

```cron
0 * * * * php /var/www/RoyalUI/api/cron/run_decisions.php
0 8 * * * php /var/www/RoyalUI/api/cron/run_escalations.php
```

| Script | Rôle |
|--------|------|
| `run_decisions.php` | Exécute `DecisionEngine::run()` |
| `run_escalations.php` | Escalade ruptures non résolues depuis 48 h |

---

## Base de données

### Schéma relationnel (simplifié)

```
categorie ──┐
            ├── medicament ──┬── lot_stock (FEFO) ← ligne_achat
fournisseur ├── achat ─────┤
            │   ligne_achat┘
            ├── bon_commande ← recommandation
            │
            ├── vente ── ligne_vente ── ligne_vente_lot → lot_stock
            │     ↑ id_utilisateur (vendeur)
            │
            ├── mouvement_stock
            ├── inventaire / ligne_inventaire
            ├── notification / recommandation
            └── session_caisse (clôture)

utilisateur ── utilisateur_preferences
            └── activite_utilisateur

parametres · regle_metier · audit_log
```

### Tables (22)

| Table | Rôle |
|-------|------|
| `categorie`, `fournisseur`, `client`, `utilisateur` | Référentiels |
| `medicament` | Catalogue + `stock_actuel` agrégé |
| `vente`, `ligne_vente` | Tickets (`id_utilisateur`, `statut`, `montant_total`) |
| `achat`, `ligne_achat` | Réceptions + `date_peremption` |
| `lot_stock`, `ligne_vente_lot` | **FEFO** — lots et consommation à la vente |
| `mouvement_stock` | Journal ENTREE / SORTIE |
| `notification` | Alertes persistantes (priorité, lu, lien action) |
| `recommandation` | Suggestions actionnables (JSON payload) |
| `regle_metier` | Seuils configurables par clé |
| `parametres` | Identité pharmacie + seuils globaux |
| `bon_commande`, `ligne_bon_commande` | Commandes fournisseur (brouillon → envoyé) |
| `inventaire`, `ligne_inventaire` | Inventaire physique et écarts |
| `session_caisse` | Ouverture / clôture, écart caisse |
| `utilisateur_preferences` | Page d'accueil, thème, notifications email |
| `activite_utilisateur` | Historique actions (briefing, analytics) |
| `audit_log` | Traçabilité actions sensibles |

### Colonnes clés `vente`

| Colonne | Description |
|---------|-------------|
| `id_client` | Client optionnel |
| `id_utilisateur` | Vendeur ayant encaissé |
| `statut` | `validee` ou `annulee` |
| `montant_total` | Total figé du ticket |

### Données de démo (`seed.php`)

| Élément | Contenu |
|---------|---------|
| Comptes | admin, gestionnaire, vendeur (`admin123`) |
| Catégories | 5 familles thérapeutiques |
| Fournisseurs | MediSupply, PharmaDistrib |
| Clients | 3 clients dont « passage » |
| Médicaments | 5 produits (rupture, stock bas, OK) |
| Achat démo | Ibuprofène, péremption J+20 |
| Règles métier | 5 règles par défaut |

---

## Authentification et rôles

### Flux de connexion

1. `POST auth/login` → session + `DecisionEngine::run()` + briefing
2. Réponse : `{ user, redirect, briefing }` — redirection selon `utilisateur_preferences` ou rôle
3. Requêtes suivantes : cookie session, `require_auth()` côté API

### Matrice d'accès

| Page / API | admin | gestionnaire | vendeur |
|------------|:-----:|:------------:|:-------:|
| Tableau de bord, briefing | ✅ | ✅ | ✅ |
| Caisse, clôture | ✅ | ✅ | ✅ |
| Ventes (lecture, ticket) | ✅ | ✅ | ✅ |
| Annulation vente | ✅ | ✅ | ❌ |
| Clients | ✅ | ✅ | ✅ |
| Médicaments, catégories, stock, inventaire | ✅ | ✅ | ❌ |
| Achats, fournisseurs | ✅ | ✅ | ❌ |
| Rapports, export CSV | ✅ | ✅ | ❌ |
| Recommandations, notifications | ✅ | ✅ | ✅ (lecture) |
| Paramètres, audit, utilisateurs | ✅ | ❌ | ❌ |

Le menu (`layout.js`) et l'API appliquent ces restrictions indépendamment.

---

## Pages de l'application

| Fichier | Titre | Script | Onglet par défaut |
|---------|-------|--------|-------------------|
| `index.html` | Connexion | inline | — |
| `home.html` | Tableau de bord | `dashboard.js` | — |
| `caisse.html` | Caisse POS | `caisse.js` | — |
| `medicaments.html` | Médicaments | `medicaments.js` | `liste` |
| `categories.html` | Catégories | `categories.js` | `liste` |
| `stock.html` | Stock | `stock.js` | `etat` |
| `ventes.html` | Ventes | `ventes.js` | — |
| `achats.html` | Achats | `achats.js` | `reception` |
| `fournisseurs.html` | Fournisseurs | `fournisseurs.js` | `liste` |
| `clients.html` | Clients | `clients.js` | `liste` |
| `rapports.html` | Rapports | `rapports.js` | — |
| `parametres.html` | Paramètres | `parametres.js` | — |
| `audit.html` | Audit | `audit.js` | — |
| `utilisateurs.html` | Utilisateurs | `utilisateurs.js` | `liste` |

### `home.html` — Tableau de bord par rôle

- **Briefing** : salutation, stats du jour, favoris (vendeur), liens d'action
- **KPI** : CA jour, ventes, alertes stock, péremptions 30 j
- **Graphique** CA 7 jours (masqué pour vendeur)
- **Top produits** 30 jours
- **Recommandations** (gestionnaire/admin) : appliquer → BC brouillon, ignorer

### `caisse.html` — Point de vente

- Produits triés par **favoris vendeur** (top ventes 30 j)
- Session caisse auto-ouverte, **clôture** avec saisie montant réel et calcul d'écart
- FEFO transparent à la validation
- **Ticket** HTML imprimable après vente
- Raccourcis : **F2** recherche · **F10** valider · **Échap** effacer recherche

### `stock.html` — Onglets

| Onglet | Contenu |
|--------|---------|
| `etat` | KPI cliquables, filtres (Tous / Disponibles / Stock OK / Bas / Rupture), timeline mouvements |
| `mouvement` | Ajustement ENTREE/SORTIE avec prévisualisation |
| `inventaire` | Saisie quantités réelles, application des écarts au stock |

### `ventes.html`

- Colonnes : ticket, date, **vendeur**, client, total, **statut**
- Actions : détail, **imprimer ticket**, **annuler** (admin/gestionnaire)

### `parametres.html` (admin)

Identité pharmacie, seuils, objectif CA, règles métier éditables.

### `audit.html` (admin)

Journal `audit_log` avec stats agrégées sur 7 jours.

---

## API REST

**Base** : `api/index.php?r=`  
**Succès** : `{ "success": true, ... }` · **Erreur** : `{ "success": false, "error": "..." }`

### Auth — `auth/{action}`

| Méthode | Route | Auth | Description |
|---------|-------|------|-------------|
| POST | `auth/login` | Non | Login → `user`, `redirect`, `briefing` |
| POST | `auth/logout` | Session | Déconnexion |
| GET | `auth/me` | Session | Utilisateur courant |

### Intelligence

| Méthode | Route | Rôles | Description |
|---------|-------|-------|-------------|
| GET | `briefing` | Tous | Briefing personnalisé |
| GET | `notifications` | Tous | Liste + `unread` |
| PUT | `notifications/{id}` | Tous | Marquer lu |
| POST | `notifications/lire-tout` | Tous | Tout marquer lu |
| GET | `recommandations?statut=nouvelle` | admin, gestionnaire | Liste recommandations |
| PUT | `recommandations/{id}` | admin, gestionnaire | `statut` + option `creer_bc` |
| POST | `recommandations/executer` | admin, gestionnaire | Lance DecisionEngine |
| GET | `alertes` | Tous | Alertes legacy (dashboard) |

### Paramètres et préférences

| Méthode | Route | Rôles | Description |
|---------|-------|-------|-------------|
| GET | `parametres` | Tous | Paramètres + règles métier |
| PUT | `parametres` | admin | Mise à jour |
| GET | `preferences` | Tous | Préférences utilisateur |
| PUT | `preferences` | Tous | Page d'accueil, thème, etc. |

### Ventes — `ventes` / `ventes/{id}` / `ventes/{id}/annuler`

| Méthode | Rôles | Description |
|---------|-------|-------------|
| GET | Tous | Historique ou détail (`vendeur_nom`, `lignes`, `total`) |
| GET | `ventes/mes-ventes` | Tous | Ventes du jour de l'utilisateur connecté |
| POST | Tous | Créer vente — FEFO, `id_utilisateur`, DecisionEngine |
| POST | `ventes/{id}/annuler` | admin, gestionnaire | Annulation + restock lots |

### Tickets — `tickets/{id}`

| Méthode | Description |
|---------|-------------|
| GET | Ticket HTML imprimable (`?format=json` pour JSON) |

### Caisse — `caisse/{action}`

| Méthode | Action | Description |
|---------|--------|-------------|
| GET | `session` | Session ouverte |
| POST | `ouvrir` | `{ fond_caisse }` |
| POST | `cloturer` | `{ ca_reel }` → `ca_theorique`, `ecart` |
| GET | `historique` | Clôtures passées (admin, gestionnaire) |

### Inventaire — `inventaire`

| Méthode | Description |
|---------|-------------|
| GET | `inventaire/produits` | Liste avec stock théorique |
| GET | `inventaire` | Historique inventaires |
| POST | `inventaire` | `{ lignes: [{ id_medicament, stock_theorique, stock_reel }] }` |

### Bons de commande — `bons-commande`

| Méthode | Description |
|---------|-------------|
| GET | Liste ou détail `{id}` |
| PUT | `bons-commande/{id}` | Changer `statut` (brouillon, envoye, recu, annule) |

### Audit — `audit`

| Méthode | Rôles | Description |
|---------|-------|-------------|
| GET | admin | Journal + `stats` 7 jours |

### Routes existantes (inchangées dans l'esprit)

| Ressource | Points clés |
|-----------|-------------|
| `dashboard` | KPI, ventes_7j, top_produits |
| `medicaments` | CRUD, `?q=`, `statut_stock`, `marge_pct` |
| `categories`, `clients`, `fournisseurs`, `utilisateurs` | CRUD standard |
| `achats` | POST crée **lot_stock** (FEFO) |
| `stock`, `stock/mouvements` | Niveaux et ajustements |
| `rapports?jours=30` | Stats + `&export=csv` |

---

## Moteur de décision

Fichier : **`api/services/DecisionEngine.php`**

### Déclencheurs

- Connexion (`auth/login`)
- Chaque vente / achat
- `POST recommandations/executer`
- Cron `run_decisions.php`

### Règles implémentées

| Type | Logique | Sortie |
|------|---------|--------|
| `STOCK_CRITIQUE` | Rupture ou vélocité → rupture avant délai fournisseur | Notification + recommandation COMMANDE_SUGGEREE |
| `REAPPRO` | Stock ≤ minimum | Recommandation quantité |
| `SURSTOCK` | Stock > ratio × minimum | Notification gestionnaire |
| `ANOMALIE_PRIX` | Marge < seuil ou PV < PA | Notification admin |
| `PEREMPTION_URGENTE` | Lot expire sous N jours | Notification + promo suggérée |
| `STOCK_DORMANT` | Pas de vente depuis N jours | Notification |
| `OBJECTIF_CA` | CA jour vs objectif (après 18 h) | Notification admin |
| `ESCALADE_RUPTURE` | Ruptures non résolues | Notification critique admin |

### Scoring réappro

```
vélocité = ventes_30j / 30
jours_restants = stock_actuel / vélocité
besoin = max(stock_min × 2 - stock_actuel, stock_min)
```

---

## Structure du projet

```
RoyalUI/
├── index.html · home.html · caisse.html · stock.html · ventes.html
├── medicaments.html · categories.html · achats.html · fournisseurs.html
├── clients.html · rapports.html · parametres.html · audit.html · utilisateurs.html
│
├── api/
│   ├── index.php              # Routeur (id + subAction)
│   ├── config.php · helpers.php
│   ├── services/
│   │   ├── DecisionEngine.php
│   │   ├── NotificationService.php
│   │   ├── LotStockService.php      # FEFO
│   │   ├── ParametresService.php
│   │   └── ActiviteService.php
│   ├── routes/                # 20 fichiers de routes
│   └── cron/
│       ├── run_decisions.php
│       └── run_escalations.php
│
├── install/
│   ├── setup.sql              # Schéma complet v2
│   ├── migrate_v2.sql         # Migration bases existantes
│   ├── migrate.php
│   └── seed.php
│
└── assets/
    ├── css/pharma.css · style.css
    └── js/app/
        ├── api.js · auth.js · layout.js · notifications.js
        ├── ui.js · swal.js · toast.js · tabs.js
        └── pages/             # Un script par page métier
```

---

## Modules JavaScript

### Chargement type (pages protégées)

```
vendor.bundle → template → DataTables? → SweetAlert2
→ api.js → swal.js → ui.js → toast.js → auth.js
→ notifications.js → layout.js → tabs.js? → pages/*.js
```

### Modules principaux

| Module | Rôle |
|--------|------|
| `PharmaAPI` | `get`, `post`, `put`, `del` — `formatMoney`, `formatDate` |
| `PharmaAuth` | Login (retourne `redirect`), logout, `requireAuth` |
| `PharmaLayout` | Menu par rôle, navbar utilisateur |
| `PharmaNotifications` | Cloche, dropdown, lu/non lu, polling 30 s |
| `PharmaSwal` | Confirmations vente, stock, suppression ; toasts |
| `ui.js` | `stockBadge`, `stockBarHtml`, `bindFilterChips`, `pharmaEmpty` |
| `tabs.js` | Onglets hash URL, `initDataTable` |

---

## Design system

Fichier : **`assets/css/pharma.css`**

| Composant | Classes |
|-----------|---------|
| KPI | `.pharma-kpi`, `.pharma-kpi--success/warning/danger`, `.pharma-kpi--clickable` |
| Notifications | `.pharma-notif-item`, `.pharma-notif--critique/haute/info` |
| Briefing | `.pharma-briefing` |
| POS | `.pharma-pos-*`, `.pharma-product-card`, `.pharma-cart-*` |
| Stock | `.pharma-stock-bar`, `.badge-stock-*` |
| Filtres | `.pharma-chip`, `.pharma-filter-chips` |
| Modales | `.pharma-swal-*` |
| CRUD | `.pharma-crud-page`, `.pharma-form-grid`, `.pharma-table-compact` |

---

## Logique métier

### Statuts de stock

| Condition | Statut |
|-----------|--------|
| `stock_actuel <= 0` | `rupture` |
| `stock_actuel <= stock_min` | `bas` |
| Sinon | `ok` |

### FEFO (First Expired, First Out)

1. **Réception achat** → `lot_stock` (qté, péremption, lien `ligne_achat`)
2. **Vente** → `LotStockService::consumeFefo()` — lots triés par `date_peremption ASC`
3. **Annulation** → `restoreFromVente()` — quantités rendues aux lots d'origine via `ligne_vente_lot`

### Mouvements `mouvement_stock`

| Origine | Type | `id_reference` |
|---------|------|----------------|
| Vente | SORTIE | ID vente |
| Achat | ENTREE | ID achat |
| Ajustement | ENTREE/SORTIE | 0 |
| Inventaire | ENTREE/SORTIE | ID inventaire |
| Annulation vente | ENTREE | ID vente |

### Transactions

Ventes et achats : `BEGIN … COMMIT` + `SELECT … FOR UPDATE` sur les lignes médicament.

---

## Comptes de démonstration

| Email | Mot de passe | Rôle | Redirection login |
|-------|--------------|------|-------------------|
| `admin@pharma.local` | `admin123` | Administrateur | `home.html` |
| `gestion@pharma.local` | `admin123` | Gestionnaire | `home.html` |
| `vendeur@pharma.local` | `admin123` | Vendeur | `caisse.html` |

> ⚠️ Changer les mots de passe et supprimer/protéger `install/` en production.

---

## Parcours utilisateur

### Vendeur

1. Login → **caisse** (briefing : ventes du jour, favoris)
2. Vente → ticket optionnel → notifications si rupture
3. **Clôture caisse** en fin de journée
4. **Ventes** → historique personnel

### Gestionnaire

1. Login → **dashboard** + recommandations
2. **Appliquer** une reco → bon de commande brouillon
3. **Achats** → réception (crée lots FEFO)
4. **Stock** → inventaire physique si écarts
5. **Rapports** + export CSV

### Administrateur

- Paramètres pharmacie et règles métier
- Audit des actions
- Gestion utilisateurs
- Annulation ventes si erreur

---

## Sécurité

| Mesure | Implémentation |
|--------|----------------|
| Mots de passe | bcrypt (`password_hash`) |
| Sessions | PHP native, cookie HttpOnly (config serveur) |
| Autorisation | `require_auth(['roles'])` par route |
| SQL | Requêtes préparées PDO |
| Audit | `audit_log` + page dédiée |
| CORS | `CORS_ORIGIN` dans `config.php` |

**Production** : HTTPS, mot de passe MySQL fort, restreindre CORS, protéger `install/`, désactiver `display_errors`.

---

## Limitations et roadmap

### Implémenté (v2)

- Notifications persistantes, FEFO, DecisionEngine, recommandations, paramètres UI
- Annulation vente, ticket imprimable, clôture caisse, inventaire physique
- Dashboard par rôle, briefing, favoris vendeur, export CSV, audit UI
- Bons de commande brouillon depuis recommandation

### Non implémenté / partiel

| Élément | État |
|---------|------|
| Envoi email/SMS réel | Champ `email_alerte` prêt, pas de SMTP |
| WebSocket temps réel | Polling 30 s à la place |
| PDF ticket natif | HTML imprimable via navigateur |
| ML / prévisions avancées | Vélocité 30 j seulement |
| Multi-dépôts | Un stock agrégé par médicament |
| Scanner code-barres hardware | Saisie / recherche manuelle |
| Ordonnances, interactions médicamenteuses | Hors périmètre réglementaire |
| PWA offline | Non prévu |
| Pagination API | Limites fixes (200 ventes, 300 mouvements, 50 notifications) |
| Filtres `medicaments.html` | « En stock » = statut `ok` (différent de stock.html « Disponibles ») |

---

## Dépannage

### « Non authentifié » / boucle login

- Utiliser `http://localhost/RoyalUI/` (pas `file://`)
- Cookies autorisés pour `localhost`
- Vérifier qu'Apache charge les sessions PHP

### Erreurs SQL après mise à jour

Exécuter la migration :

```
http://localhost/RoyalUI/install/migrate.php
```

Erreurs fréquentes : colonnes `vente.id_utilisateur`, tables `notification` / `lot_stock` manquantes.

### Vente échoue « Lots FEFO insuffisants »

- Lancer `migrate.php` pour créer les lots depuis les achats existants
- Ou réceptionner via **Achats** (crée automatiquement les lots)

### Notifications en double

Le moteur peut créer plusieurs alertes si exécuté souvent ; les recommandations sont dédupliquées sur 24 h. Configurer le cron à 1 h d'intervalle minimum.

### DataTables vide ou erreur CDN

Connexion internet requise pour CDN DataTables (fr-FR). Le tableau stock principal n'utilise **pas** DataTables volontairement.

### Logs PHP

```
C:\xampp\apache\logs\error.log
```

Test API : `http://localhost/RoyalUI/api/index.php?r=auth/me` (connecté).

---

## Crédits et licence

- **Interface** : [RoyalUI](https://www.bootstrapdash.com/) — thème admin Bootstrap
- **Projet métier** : PharmaRoyal
- **Icônes** : [Themify Icons](https://themify.me/themify-icons)
- **Modales** : [SweetAlert2](https://sweetalert2.github.io/)

Le thème RoyalUI est soumis à sa propre licence (BootstrapDash).  
Le code métier PharmaRoyal (API, services, JS, `pharma.css`) accompagne ce dépôt.

---

**PharmaRoyal v2** — *Gérez votre pharmacie. Anticipez. Décidez.*
