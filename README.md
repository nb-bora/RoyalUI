# PharmaRoyal

**Système intelligent de gestion de pharmacie** — application web complète pour la gestion des stocks, des ventes au comptoir, des achats fournisseurs, des clients et du pilotage d'activité.

Le projet combine un **backend PHP/MySQL** (API REST) et un **frontend** basé sur le thème admin **RoyalUI** (Bootstrap), enrichi d'un design system métier (`pharma.css`) et de modules JavaScript modulaires.

---

## Table des matières

1. [Aperçu fonctionnel](#aperçu-fonctionnel)
2. [Stack technique](#stack-technique)
3. [Architecture](#architecture)
4. [Prérequis](#prérequis)
5. [Installation](#installation)
6. [Configuration](#configuration)
7. [Base de données](#base-de-données)
8. [Authentification et rôles](#authentification-et-rôles)
9. [Pages de l'application](#pages-de-lapplication)
10. [API REST](#api-rest)
11. [Structure du projet](#structure-du-projet)
12. [Modules JavaScript](#modules-javascript)
13. [Design system](#design-system)
14. [Logique métier](#logique-métier)
15. [Comptes de démonstration](#comptes-de-démonstration)
16. [Parcours utilisateur](#parcours-utilisateur)
17. [Sécurité](#sécurité)
18. [Limitations connues](#limitations-connues)
19. [Dépannage](#dépannage)

---

## Aperçu fonctionnel

| Domaine | Fonctionnalités |
|---------|-----------------|
| **Tableau de bord** | CA du jour, ventes du jour, alertes stock/péremption, graphique CA 7 jours, top produits 30 jours, centre d'alertes intelligent |
| **Caisse (POS)** | Recherche produits, panier, client optionnel, validation vente avec contrôle stock, raccourcis clavier |
| **Médicaments** | CRUD catalogue, prix achat/vente, marge, code-barres, seuil minimum, filtres stock |
| **Catégories** | CRUD des familles de produits |
| **Stock** | Vue d'ensemble avec KPI et filtres, barres de niveau, ajustements manuels (entrée/sortie), historique mouvements |
| **Ventes** | Historique des tickets, détail par vente |
| **Achats** | Réception fournisseur multi-lignes, date de péremption, mise à jour automatique du stock |
| **Fournisseurs** | CRUD, statistiques réceptions et volume d'achats |
| **Clients** | CRUD, association optionnelle aux ventes |
| **Utilisateurs** | Gestion des comptes et rôles (admin uniquement) |
| **Rapports** | CA période, top ventes, marges par catégorie, stock dormant (90 jours sans vente) |

---

## Stack technique

| Couche | Technologies |
|--------|--------------|
| **Serveur** | Apache (XAMPP), PHP 8+ (`declare(strict_types=1)`) |
| **Base de données** | MySQL / MariaDB, charset `utf8mb4` |
| **API** | REST JSON, routage via `api/index.php?r=...` |
| **Sessions** | PHP sessions (`pharma_session`), cookies `credentials: 'include'` |
| **Frontend** | HTML5, Bootstrap (RoyalUI), jQuery, DataTables, Chart.js, SweetAlert2 |
| **Icônes** | Themify Icons |
| **Devise** | FCFA (configurable en base via table `parametres`) |

**Dépendances CDN (frontend)** :
- [SweetAlert2](https://sweetalert2.github.io/) v11
- [DataTables](https://datatables.net/) v1.13.6 (fr-FR)
- Chart.js (fichier local `assets/vendors/chart.js/Chart.min.js`)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│  Navigateur (pages HTML + JS modulaire)                     │
│  index.html → home.html, caisse.html, stock.html, ...       │
└──────────────────────────┬──────────────────────────────────┘
                           │ fetch() JSON + cookies session
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  api/index.php  →  routes/*.php  →  helpers.php / config    │
└──────────────────────────┬──────────────────────────────────┘
                           │ PDO
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  MySQL — base `pharma`                                      │
└─────────────────────────────────────────────────────────────┘
```

**Principe de routage API** : toutes les requêtes passent par `api/index.php?r={ressource}/{id|action}`.

Exemples :
- `GET  api/index.php?r=medicaments`
- `POST api/index.php?r=auth/login`
- `GET  api/index.php?r=stock/mouvements`
- `GET  api/index.php?r=ventes/42`

---

## Prérequis

- **XAMPP** (ou équivalent : Apache + PHP + MySQL)
- **PHP** 8.0 ou supérieur avec extensions : `pdo_mysql`, `json`, `session`
- **MySQL** 5.7+ ou MariaDB 10.3+
- Navigateur moderne (Chrome, Firefox, Edge)

---

## Installation

### 1. Placer le projet

Copier le dossier dans le répertoire web Apache :

```
C:\xampp\htdocs\RoyalUI\
```

### 2. Démarrer les services

Démarrer **Apache** et **MySQL** depuis le panneau XAMPP.

### 3. Initialiser la base de données

**Option A — Navigateur (recommandé)**

Ouvrir :

```
http://localhost/RoyalUI/install/seed.php
```

**Option B — Ligne de commande**

```bash
php install/seed.php
```

Le script :
1. Exécute `install/setup.sql` (création base + tables)
2. Insère les utilisateurs, catégories, fournisseurs, clients, médicaments et données de démo
3. Affiche les identifiants de connexion

> Si la base contient déjà des utilisateurs, le script affiche « Base déjà initialisée » sans écraser les données.

### 4. Accéder à l'application

```
http://localhost/RoyalUI/
```

(redirige vers `index.html` — page de connexion)

---

## Configuration

Fichier : `api/config.php`

| Constante | Valeur par défaut | Description |
|-----------|-------------------|-------------|
| `DB_HOST` | `127.0.0.1` | Hôte MySQL |
| `DB_NAME` | `pharma` | Nom de la base |
| `DB_USER` | `root` | Utilisateur MySQL |
| `DB_PASS` | `''` | Mot de passe MySQL (vide sous XAMPP) |
| `DB_CHARSET` | `utf8mb4` | Encodage |
| `SESSION_NAME` | `pharma_session` | Nom de session PHP |
| `CORS_ORIGIN` | `*` | En-tête CORS (utile si frontend séparé) |

Modifier ces valeurs selon votre environnement de production.

---

## Base de données

### Schéma relationnel

```
categorie ──┐
            ├── medicament ──┬── ligne_vente ── vente ── client
fournisseur ├── achat ─────┤
            │   ligne_achat┘
            └── mouvement_stock (traçabilité ENTREE / SORTIE)

utilisateur (authentification)
parametres  (nom pharmacie, devise — pas encore d'UI)
audit_log   (journal d'actions — écriture silencieuse)
```

### Tables détaillées

#### `categorie`
| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AI | Identifiant |
| `nom` | VARCHAR(100) UNIQUE | Nom de la catégorie |
| `created_at` | TIMESTAMP | Date de création |

#### `fournisseur`
| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AI | Identifiant |
| `nom` | VARCHAR(150) | Raison sociale |
| `telephone` | VARCHAR(50) NULL | Téléphone |
| `email` | VARCHAR(150) NULL | Email |
| `created_at` | TIMESTAMP | Date de création |

#### `client`
| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AI | Identifiant |
| `nom` | VARCHAR(100) | Nom du client |
| `telephone` | VARCHAR(50) NULL | Téléphone |
| `created_at` | TIMESTAMP | Date de création |

#### `utilisateur`
| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AI | Identifiant |
| `nom` | VARCHAR(100) | Nom affiché |
| `email` | VARCHAR(150) UNIQUE | Identifiant de connexion |
| `mot_de_passe` | VARCHAR(255) | Hash bcrypt (`password_hash`) |
| `role` | ENUM | `admin`, `gestionnaire`, `vendeur` |
| `created_at` | TIMESTAMP | Date de création |

#### `medicament`
| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK AI | Identifiant |
| `nom` | VARCHAR(150) | Désignation |
| `prix_achat` | DECIMAL(10,2) | Prix d'achat unitaire |
| `prix_vente` | DECIMAL(10,2) | Prix de vente unitaire |
| `stock_actuel` | INT | Quantité en stock |
| `stock_min` | INT | Seuil d'alerte (défaut : 5) |
| `id_categorie` | INT FK NULL | Catégorie |
| `code_barre` | VARCHAR(50) NULL | Code-barres |
| `actif` | TINYINT(1) | 1 = visible, 0 = suppression logique |
| `created_at` | TIMESTAMP | Date de création |

#### `vente` / `ligne_vente`
- **vente** : en-tête ticket (`id_client` optionnel, `date_vente`)
- **ligne_vente** : `id_vente`, `id_medicament`, `quantite`, `prix_vente` (figé au moment de la vente)

#### `achat` / `ligne_achat`
- **achat** : en-tête réception (`id_fournisseur`, `date_achat`)
- **ligne_achat** : `quantite`, `prix_achat`, **`date_peremption`** (obligatoire — sert aux alertes)

#### `mouvement_stock`
Journal de tous les mouvements :
| Colonne | Description |
|---------|-------------|
| `type_mouvement` | `ENTREE` ou `SORTIE` |
| `id_reference` | ID vente ou achat (0 pour ajustement manuel) |
| `quantite` | Quantité du mouvement |

#### `parametres`
`nom_pharmacie`, `adresse`, `telephone`, `devise` — table prévue, **sans interface graphique** pour l'instant.

#### `audit_log`
`id_utilisateur`, `action`, `table_cible`, `id_cible` — journalisation des actions sensibles.

### Données de démo (seed)

| Élément | Contenu |
|---------|---------|
| Catégories | Antalgique, Antibiotique, Anti-inflammatoire, Vitamines, Dermatologie |
| Fournisseurs | MediSupply SARL, PharmaDistrib |
| Clients | Jean Dupont, Marie Court, Client passage |
| Médicaments | 5 produits avec stocks variés (dont rupture et stock bas) |
| Achat démo | Ibuprofène avec péremption à J+20 (alerte péremption) |

---

## Authentification et rôles

### Mécanisme

1. `POST auth/login` — vérifie email + mot de passe, crée la session PHP
2. Chaque requête API protégée appelle `require_auth()` dans `api/helpers.php`
3. Le frontend envoie `credentials: 'include'` pour transmettre le cookie de session
4. `PharmaAuth.requireAuth()` redirige vers `index.html` si non connecté

### Matrice des rôles

| Page / API | admin | gestionnaire | vendeur |
|------------|:-----:|:------------:|:-------:|
| Tableau de bord | ✅ | ✅ | ✅ |
| Caisse | ✅ | ✅ | ✅ |
| Ventes (lecture) | ✅ | ✅ | ✅ |
| Clients | ✅ | ✅ | ✅ |
| Médicaments | ✅ | ✅ | ❌ |
| Catégories | ✅ | ✅ | ❌ |
| Stock | ✅ | ✅ | ❌ |
| Achats | ✅ | ✅ | ❌ |
| Fournisseurs | ✅ | ✅ | ❌ |
| Rapports | ✅ | ✅ | ❌ |
| Utilisateurs | ✅ | ❌ | ❌ |

Le menu latéral (`layout.js`) filtre automatiquement les entrées selon le rôle connecté.

---

## Pages de l'application

| Fichier | Titre | Script page | Onglets (`data-default-tab`) |
|---------|-------|-------------|------------------------------|
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
| `utilisateurs.html` | Utilisateurs | `utilisateurs.js` | `liste` |
| `rapports.html` | Rapports | `rapports.js` | — |

### Détail par page

#### `index.html` — Connexion
- Formulaire email / mot de passe
- Affichage/masquage du mot de passe
- Redirection vers `home.html` après succès
- Identifiants pré-remplis en démo

#### `home.html` — Tableau de bord
- **4 KPI** : CA jour, ventes jour, alertes stock (bas + ruptures), péremptions 30 j
- **Graphique** ligne : CA sur 7 jours (Chart.js)
- **Top 5 produits** vendus sur 30 jours
- **Centre d'alertes** (`#alertes`, ancrage `home.html#alertes`) :
  - Stock bas / rupture → lien médicaments
  - Péremption proche → lien stock
  - Réapprovisionnement suggéré → lien achats
- Badge cloche navbar alimenté par `GET alertes`

#### `caisse.html` — Point de vente
- Interface POS dédiée (`pharma-pos-body`)
- Recherche temps réel sur nom / code-barres
- Liste produits **une ligne par produit** avec statut stock
- Panier sticky : quantités, total, client optionnel
- Validation → `POST ventes` avec contrôle stock transactionnel
- Raccourcis : **F2** focus recherche, **Échap** vider panier

#### `medicaments.html`
- Formulaire CRUD (admin/gestionnaire)
- Liste avec filtres : Tous, En stock, Stock bas, Rupture
- Colonnes : produit, catégorie, prix, barre de stock, marge %, actions
- Suppression logique (`actif = 0`)

#### `categories.html`
- CRUD simple nom de catégorie

#### `stock.html`
- **Onglet Vue d'ensemble** :
  - KPI cliquables (total, stock OK, stock bas, ruptures)
  - Filtres : Tous, Disponibles (>0), Stock OK, Stock bas, Rupture
  - Compteur `X / Y · filtre`
  - Tableau sans DataTables (filtrage client fiable)
  - Clic ligne → pré-remplit l'onglet ajustement
  - Timeline des 12 derniers mouvements
- **Onglet Ajustement manuel** :
  - Sélection médicament, type ENTREE/SORTIE, quantité
  - Prévisualisation stock avant/après avec jauge et conseils
  - Confirmation SweetAlert avant enregistrement

#### `ventes.html`
- Historique des ventes (date, client, lignes, total)
- Détail d'une vente au clic

#### `achats.html`
- **Réception** : fournisseur + lignes (médicament, qté, prix, date péremption)
- Incrémente stock, met à jour `prix_achat`, crée mouvement ENTREE
- **Historique** des réceptions

#### `fournisseurs.html`
- KPI : total fournisseurs, nb achats, montant total
- Liste avec nb réceptions et volume par fournisseur

#### `clients.html`
- CRUD clients (nom, téléphone)

#### `utilisateurs.html` (admin)
- CRUD utilisateurs avec rôle et mot de passe
- Impossible de supprimer son propre compte

#### `rapports.html`
- Sélecteur période (7 / 30 / 90 jours)
- CA période, top 10 ventes, marges par catégorie, stock dormant

---

## API REST

**URL de base** : `api/index.php?r=`

**Format réponse succès** : `{ "success": true, ... }`  
**Format erreur** : `{ "success": false, "error": "message" }`  
**Corps JSON** : `Content-Type: application/json`

### Auth — `r=auth/{action}`

| Méthode | Route | Auth | Description |
|---------|-------|------|-------------|
| POST | `auth/login` | Non | `{ email, password }` → `{ user }` |
| POST | `auth/logout` | Session | Détruit la session |
| GET | `auth/me` | Session | Utilisateur courant |

### Dashboard — `r=dashboard`

| Méthode | Auth | Retour |
|---------|------|--------|
| GET | Tous | `kpis`, `ventes_7j`, `top_produits` |

**KPIs** : `ca_jour`, `ventes_jour`, `stock_bas`, `ruptures`, `peremption_30j`

### Alertes — `r=alertes`

| Méthode | Auth | Retour |
|---------|------|--------|
| GET | Tous | `stock_bas`, `peremption`, `reappro`, `total` |

- **stock_bas** : produits où `stock_actuel <= stock_min`
- **peremption** : lots avec péremption dans les 90 prochains jours
- **reappro** : produits en alerte avec ventes sur 30 j et quantité suggérée

### Médicaments — `r=medicaments` / `r=medicaments/{id}`

| Méthode | Rôles | Description |
|---------|-------|-------------|
| GET | Tous | Liste active ; `?q=` recherche nom/code-barres ; champs calculés `statut_stock`, `marge_pct` |
| POST | admin, gestionnaire | Créer |
| PUT | admin, gestionnaire | Modifier |
| DELETE | admin, gestionnaire | Désactiver (`actif=0`) |

### Catégories — `r=categories` / `r=categories/{id}`

| Méthode | Rôles | Description |
|---------|-------|-------------|
| GET | admin, gestionnaire | Liste |
| POST | admin, gestionnaire | Créer |
| PUT | admin, gestionnaire | Modifier |
| DELETE | admin, gestionnaire | Supprimer |

### Clients — `r=clients` / `r=clients/{id}`

| Méthode | Rôles | Description |
|---------|-------|-------------|
| GET | Tous | Liste |
| POST | Tous | Créer |
| PUT | Tous | Modifier |
| DELETE | Tous | Supprimer |

### Fournisseurs — `r=fournisseurs` / `r=fournisseurs/{id}`

| Méthode | Rôles | Description |
|---------|-------|-------------|
| GET | admin, gestionnaire | Liste + `stats` (total, nb_achats, montant_total) + `nb_achats`/`montant_achats` par fournisseur |
| POST | admin, gestionnaire | Créer |
| PUT | admin, gestionnaire | Modifier |
| DELETE | admin, gestionnaire | Supprimer |

### Utilisateurs — `r=utilisateurs` / `r=utilisateurs/{id}`

| Méthode | Rôles | Description |
|---------|-------|-------------|
| GET | admin | Liste (sans mot de passe) |
| POST | admin | Créer `{ nom, email, password, role }` |
| PUT | admin | Modifier (champs partiels, hash si password) |
| DELETE | admin | Supprimer (sauf soi-même) |

### Ventes — `r=ventes` / `r=ventes/{id}`

| Méthode | Rôles | Description |
|---------|-------|-------------|
| GET | Tous | Historique (200 derniers) ou détail avec `lignes` et `total` |
| POST | Tous | Créer vente `{ id_client?, lignes: [{ id_medicament, quantite, prix_vente? }] }` |

**Transaction POST vente** :
1. Vérification stock (SELECT … FOR UPDATE)
2. Insertion vente + lignes
3. Décrément stock
4. Mouvement SORTIE lié à l'ID vente

### Achats — `r=achats` / `r=achats/{id}`

| Méthode | Rôles | Description |
|---------|-------|-------------|
| GET | admin, gestionnaire | Historique ou détail |
| POST | admin, gestionnaire | Réception `{ id_fournisseur, lignes: [{ id_medicament, quantite, prix_achat, date_peremption }] }` |

**Transaction POST achat** :
1. Insertion achat + lignes
2. Incrément stock + mise à jour `prix_achat`
3. Mouvement ENTREE lié à l'ID achat

### Stock — `r=stock` / `r=stock/mouvements`

| Méthode | Rôles | Description |
|---------|-------|-------------|
| GET | admin, gestionnaire | Niveaux stock tous produits actifs |
| GET | `stock/mouvements` | 300 derniers mouvements |
| POST | admin, gestionnaire | Ajustement `{ id_medicament, type_mouvement, quantite, motif? }` |

Types : `ENTREE`, `SORTIE` — refus si sortie > stock disponible.

### Rapports — `r=rapports?jours=30`

| Méthode | Rôles | Retour |
|---------|-------|--------|
| GET | admin, gestionnaire | `ca_periode`, `top_ventes`, `marges_categories`, `stock_mort` |

---

## Structure du projet

```
RoyalUI/
├── index.html                 # Page de connexion
├── home.html                  # Tableau de bord
├── caisse.html                # Point de vente
├── medicaments.html
├── categories.html
├── stock.html
├── ventes.html
├── achats.html
├── fournisseurs.html
├── clients.html
├── utilisateurs.html
├── rapports.html
│
├── api/
│   ├── index.php              # Routeur API
│   ├── config.php             # Configuration BDD / session
│   ├── helpers.php            # Auth, JSON, CORS, audit, stock_badge
│   └── routes/
│       ├── auth.php
│       ├── dashboard.php
│       ├── alertes.php
│       ├── medicaments.php
│       ├── categories.php
│       ├── clients.php
│       ├── fournisseurs.php
│       ├── utilisateurs.php
│       ├── ventes.php
│       ├── achats.php
│       ├── stock.php
│       └── rapports.php
│
├── install/
│   ├── setup.sql              # Schéma complet
│   └── seed.php               # Installation + données démo
│
└── assets/
    ├── css/
    │   ├── style.css          # Thème RoyalUI
    │   └── pharma.css         # Design system PharmaRoyal
    ├── js/
    │   ├── app/
    │   │   ├── api.js         # Client HTTP + formatMoney/formatDate
    │   │   ├── auth.js        # Session frontend
    │   │   ├── layout.js      # Menu, navbar, badge alertes
    │   │   ├── tabs.js        # Onglets + DataTables
    │   │   ├── ui.js          # Badges, barres stock, filtres
    │   │   ├── swal.js        # Dialogues SweetAlert2
    │   │   ├── toast.js       # Notifications
    │   │   └── pages/         # Logique par page
    │   │       ├── dashboard.js
    │   │       ├── caisse.js
    │   │       ├── medicaments.js
    │   │       ├── categories.js
    │   │       ├── stock.js
    │   │       ├── ventes.js
    │   │       ├── achats.js
    │   │       ├── fournisseurs.js
    │   │       ├── clients.js
    │   │       ├── utilisateurs.js
    │   │       └── rapports.js
    │   ├── template.js        # Scripts thème RoyalUI
    │   └── ...
    ├── vendors/               # Bootstrap, Chart.js, Themify
    └── images/                # Logos, assets UI
```

---

## Modules JavaScript

Chaque page protégée charge dans l'ordre :

1. `vendor.bundle.base.js` (jQuery, Bootstrap)
2. Scripts thème (`off-canvas.js`, `template.js`, …)
3. DataTables (si table `.data-table`)
4. SweetAlert2
5. `api.js` → `swal.js` → `ui.js` → `toast.js` → `auth.js` → `layout.js`
6. `tabs.js` (si onglets)
7. `pages/{page}.js`

### `PharmaAPI` (`api.js`)
```javascript
PharmaAPI.get('medicaments')
PharmaAPI.post('ventes', { lignes: [...] })
PharmaAPI.put('medicaments/3', { nom: '...' })
PharmaAPI.del('clients/2')
```

### `PharmaAuth` (`auth.js`)
- `login`, `logout`, `me`, `requireAuth(roles?)`
- Redirection automatique si session expirée

### `PharmaLayout` (`layout.js`)
- `init()` : auth + sidebar + navbar + badge alertes
- Menu filtré par rôle (tableau `menu[]`)

### `PharmaSwal` (`swal.js`)
- `confirmDelete`, `confirmSave`, `confirmLogout`, `confirmSale`, `confirmStockMovement`
- `toast`, `error`

### Utilitaires `ui.js`
- `stockBadge(statut)` — badges HTML ok / bas / rupture
- `stockBarHtml()` — barre de progression stock
- `margeBadge(pct)` — badge marge coloré
- `bindFilterChips()` — gestion puces de filtre
- `pharmaActions()` — boutons modifier/supprimer
- `setFormMode()` — bascule formulaire création/édition

### `tabs.js`
- `initPageTabs()` — navigation par hash URL (`#liste`, `#etat`, …)
- `initDataTable(selector)` — DataTables français sur `.data-table`

---

## Design system

Fichier principal : **`assets/css/pharma.css`**

| Composant | Classes | Usage |
|-----------|---------|-------|
| KPI | `.pharma-kpi`, `.pharma-kpi--success/warning/danger` | Cartes statistiques |
| KPI cliquable | `.pharma-kpi--clickable`, `.pharma-kpi--active` | Filtres stock |
| Page header | `.pharma-page-header`, `.pharma-breadcrumb` | En-têtes de page |
| Boutons | `.btn-pharma`, `.btn-pharma-primary/outline` | Actions |
| Cartes | `.pharma-card`, `.pharma-card-header` | Conteneurs contenu |
| Filtres | `.pharma-chip`, `.pharma-filter-chips` | Puces de filtrage |
| Stock | `.pharma-stock-bar`, `.badge-stock-*` | Visualisation niveaux |
| POS | `.pharma-pos-*` | Interface caisse |
| CRUD | `.pharma-crud-page`, `.pharma-form-grid` | Pages formulaire/liste |
| Alertes | `.pharma-alert-item` | Dashboard alertes |
| SweetAlert | `.pharma-swal-*` | Modales personnalisées |
| Vide | `.pharma-empty` | États sans données |

Le thème de base **RoyalUI** (`assets/css/style.css`) fournit la sidebar, navbar et grille Bootstrap.

---

## Logique métier

### Statuts de stock

Fonction `stock_badge($stock, $min)` dans `api/helpers.php` — recalculée aussi côté client dans `stock.js` :

| Condition | Statut | Badge |
|-----------|--------|-------|
| `stock_actuel <= 0` | `rupture` | Rupture |
| `stock_actuel <= stock_min` | `bas` | Stock bas |
| Sinon | `ok` | En stock / Stock OK |

**Filtres page stock** :
- **Disponibles** : `stock_actuel > 0` (OK + bas)
- **Stock OK** : au-dessus du seuil minimum
- **Stock bas** : `> 0` et `<= stock_min`
- **Rupture** : `<= 0`

### Mouvements de stock

Tout changement de quantité génère une ligne `mouvement_stock` :

| Origine | Type | `id_reference` |
|---------|------|----------------|
| Vente caisse | SORTIE | ID vente |
| Réception achat | ENTREE | ID achat |
| Ajustement manuel | ENTREE / SORTIE | 0 |

### Marge

```
marge_pct = ((prix_vente - prix_achat) / prix_achat) × 100
```

Affichée sur la liste médicaments ; badge rouge si marge < 15 %.

### Intelligence alertes (niveau 1)

- **Réapprovisionnement** : produits en stock bas ayant eu des ventes sur 30 jours
- Quantité suggérée : `max(stock_min × 2 - stock_actuel, stock_min)`

### Transactions

Ventes et achats utilisent `BEGIN … COMMIT` avec `SELECT … FOR UPDATE` sur les lignes médicament pour éviter les ventes en surstock concurrentes.

---

## Comptes de démonstration

| Email | Mot de passe | Rôle |
|-------|--------------|------|
| `admin@pharma.local` | `admin123` | Administrateur |
| `gestion@pharma.local` | `admin123` | Gestionnaire |
| `vendeur@pharma.local` | `admin123` | Vendeur |

> ⚠️ Changer ces mots de passe avant toute mise en production.

---

## Parcours utilisateur

### Vendeur — journée type
1. Connexion → **Tableau de bord** (vue CA)
2. **Caisse** → recherche produit → ajout panier → validation
3. **Ventes** → consulter l'historique
4. **Clients** → créer un client si besoin

### Gestionnaire — réception marchandise
1. **Fournisseurs** → vérifier le fournisseur
2. **Achats** → onglet Réception → lignes + dates péremption
3. **Stock** → contrôler les niveaux après réception
4. **Rapports** → analyser CA et marges

### Administrateur
- Tout ce qui précède +
- **Médicaments / Catégories** → maintenir le catalogue
- **Utilisateurs** → créer comptes vendeurs/gestionnaires

---

## Sécurité

| Mesure | Implémentation |
|--------|----------------|
| Mots de passe | `password_hash` / `password_verify` (bcrypt) |
| Sessions | PHP native, nom personnalisé |
| Autorisation | `require_auth(['roles'])` par route |
| SQL | Requêtes préparées PDO |
| CORS | Configurable (`CORS_ORIGIN`) |
| Audit | Table `audit_log` (connexion, CRUD, ventes, achats) |

**Recommandations production** :
- HTTPS obligatoire
- Mot de passe MySQL fort
- Restreindre `CORS_ORIGIN`
- Supprimer ou protéger `install/seed.php`
- Désactiver l'affichage des erreurs PHP

---

## Limitations connues

| Élément | État |
|---------|------|
| Paramètres pharmacie (`parametres`) | Table en base, **pas d'écran Paramètres** |
| Notifications persistantes | Non implémentées |
| Multi-dépôts / lots FIFO | Un seul `stock_actuel` par médicament |
| Impression ticket / facture PDF | Non implémentée |
| Code-barres scanner hardware | Recherche manuelle uniquement |
| API pagination | Limite fixe (200 ventes, 300 mouvements) |
| Filtres `medicaments.html` | « En stock » = statut `ok` API (pas « disponibles > 0 » comme stock.html) |

---

## Dépannage

### « Non authentifié » / redirection login
- Vérifier qu'Apache et PHP sessions fonctionnent
- Cookies autorisés pour `localhost`
- Ne pas ouvrir les pages en `file://` — utiliser `http://localhost/RoyalUI/`

### Erreur base de données
- MySQL démarré dans XAMPP
- Exécuter `install/seed.php`
- Vérifier `api/config.php` (user/password)

### Page blanche API
- Consulter `C:\xampp\apache\logs\error.log`
- Tester : `http://localhost/RoyalUI/api/index.php?r=auth/me`

### DataTables ne s'affiche pas
- Vérifier la connexion internet (CDN DataTables + traduction fr-FR)
- La table doit avoir la classe `data-table` (sauf `stock-table` volontairement exclu)

### Filtres stock incorrects
- Recharger la page après mise à jour
- Les filtres utilisent le calcul client dans `stock.js` (indépendant de DataTables)

---

## Crédits

- **Interface** : [RoyalUI](https://www.bootstrapdash.com/) — thème admin Bootstrap
- **Projet métier** : PharmaRoyal — gestion de pharmacie
- **Icônes** : [Themify Icons](https://themify.me/themify-icons)
- **Modales** : [SweetAlert2](https://sweetalert2.github.io/)

---

## Licence

Le thème RoyalUI est soumis à sa propre licence d'utilisation (voir fournisseur BootstrapDash).  
Le code métier PharmaRoyal (API PHP, modules JS, design `pharma.css`) est fourni dans le cadre de ce projet de gestion de pharmacie.

---

**PharmaRoyal** — *Gérez votre pharmacie avec intelligence.*
