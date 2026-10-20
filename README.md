# Roles Audit (local_rolesaudit)

> 🇫🇷 Version française ci-dessous · 🇬🇧 English version below

---

## 🇫🇷 Français

### Présentation

**Audit des rôles** (`local_rolesaudit`) est un plugin Moodle de type *local* qui
permet de **comparer, modifier, exporter et importer** les droits associés aux
capacités des rôles, au **contexte système** (c.-à-d. les *définitions* de rôles).

Il remplace avantageusement l'outil du cœur de Moodle
*Administration du site → Utilisateurs → Permissions → Vérification des capacités*
(`/admin/tool/capability/index.php`), jugé peu lisible : ici, l'affichage est un
tableau clair et coloré, l'édition se fait en un clic, et les valeurs sont
transférables d'une instance à l'autre.

### Fonctionnalités

- **Comparer** : tableau *rôles (colonnes) × capacités (lignes)* avec un code
  couleur par valeur (Non défini / Autoriser / Empêcher / Interdire).
- **Repérer les modifications** : les capacités dont la valeur diffère de leur
  **configuration initiale** (défaut de l'archétype du rôle) sont signalées par
  une **étoile `*`**.
- **Filtrage dynamique** : toutes les données sont chargées à l'ouverture, puis
  filtrées **côté client, sans rechargement**, via un **bloc de filtres
  rétractable** : lignes de tags cliquables (rôles, types de plugin, plugins,
  capacités, valeurs) en **cascade descendante**, une recherche de capacité et
  une case « seulement les modifiées ». Par défaut, seuls les 5 rôles standard
  (shortnames `manager`, `coursecreator`, `editingteacher`, `teacher`,
  `student` — liste modifiable dans les **Réglages** du plugin), le type
  `moodle` et la case « modifiées » sont actifs. Les bandes défilent au
  **clic-glisser** (curseur main) et chaque ligne dispose de deux boutons ronds
  pour **tout sélectionner / tout désélectionner** ce qu'elle affiche. Le
  libellé de chaque ligne est suivi d'un **compteur `(sélectionnés/total)`** :
  il porte sur toute la ligne, y compris les tags hors champ, et une recherche
  dans la ligne ne le change pas. Les tags
  plugins sont libellés `type/plugin` et les tags capacités `plugin:action`, tous
  triés alphabétiquement. La ligne « Rôles » place **d'abord les rôles sélectionnés
  par défaut dans les Réglages**, puis tous les autres, chaque groupe trié
  alphabétiquement ; ce regroupement dépend du réglage et non de la sélection en
  cours, les tags ne se déplacent donc pas quand on les coche ou décoche.
- **Rechercher dans une ligne** : un bouton loupe déploie, sur chaque ligne sauf
  « Valeurs », une zone de recherche avec **autocomplétion** (navigation aux
  flèches, `Entrée` pour cibler une entrée) qui **restreint les tags affichés** par
  cette ligne — et donc aussi la portée de ses deux boutons de (dé)sélection.
  Refermer la zone (loupe ou `Échap`) efface la recherche.
- **Réutiliser un jeu de filtres** : deux boutons **enregistrent** et
  **rechargent** l'ensemble des filtres courants (stockage local du navigateur).
- **Exporter le tableau en CSV** : un bouton télécharge le tableau tel qu'il est
  filtré, en CSV délimité par `;` (nom de la capacité, description, puis le droit
  et l'indicateur « modifié » pour chaque rôle affiché).
- **Confort de lecture** : en-tête et première colonne **figés** pour naviguer
  dans un grand tableau sans perdre le contexte, largeur de colonnes fixe, et
  **couleurs reprises du thème** (variables Bootstrap, sinon valeurs par défaut).
- **Modifier à la volée** : un clic sur une cellule ouvre un menu des 4 valeurs ;
  la modification est enregistrée immédiatement en AJAX, sans rechargement.
- **Exporter** : génère un fichier JSON (toutes les définitions, ou uniquement
  les capacités modifiées) applicable sur une autre instance Moodle.
- **Importer** : téléverse un tel fichier, affiche un **aperçu des changements**
  (actuel → nouveau) avant application, puis applique après confirmation.

### Prérequis

- **Moodle 4.5** minimum (`requires = 2024100700`), testé jusqu'à Moodle 5.2
  (`supported = [405, 502]`).
- L'outil est **réservé aux administrateurs du site** : ses pages ne sont
  enregistrées que pour `moodle/site:config`. La capacité
  `local/rolesaudit:manage` n'est accordée à aucun archétype ; depuis la 1.3.10,
  la mise à jour la retire aux rôles d'archétype *Gestionnaire* qui l'avaient
  reçue à l'installation.

### Installation

#### Via une archive ZIP

1. Connectez-vous en administrateur, puis allez dans
   *Administration du site → Plugins → Installer des plugins*.
2. Téléversez l'archive ZIP du plugin.
3. Vérifiez le rapport de validation et terminez l'installation.

#### Manuellement

Copiez le contenu de ce dossier dans :

    {votre/moodle/dirroot}/local/rolesaudit

Puis connectez-vous en administrateur et allez dans
*Administration du site → Notifications*, ou lancez :

    $ php admin/cli/upgrade.php

> **Développeurs** : le module JavaScript est fourni pré-compilé dans
> `amd/build/`. Après toute modification de `amd/src/compare.js`, régénérez-le
> avec `npx grunt amd --files=local/rolesaudit/amd/src/compare.js` depuis la
> racine de Moodle (`--files=public/local/rolesaudit/amd/src/compare.js` sur
> Moodle 5.1+). Évitez `--root=…` : si le chemin résolu sort de l'arbre Moodle,
> grunt reconstruit tout le cœur. Les sources doivent se trouver physiquement
> dans l'arbre Moodle (pas de jonction ni de lien symbolique). Ne modifiez
> **jamais** `amd/build/compare.min.js` à la main.
>
> La compilation produit **deux** fichiers, et les deux doivent être versionnés :
> `amd/build/compare.min.js` et `amd/build/compare.min.js.map`. Sans la source
> map, Moodle sert la source ES6 telle quelle lorsque le cache JavaScript est
> désactivé (`$CFG->cachejs = false`, mode développeur), ce qui provoque
> `Uncaught SyntaxError: Cannot use import statement outside a module` puis
> `No define call for local_rolesaudit/compare` et casse les pages
> *Comparer et modifier* et *Exporter*.

### Utilisation

L'outil est accessible dans
*Administration du site → Utilisateurs → Permissions → Audit des rôles*,
sous forme de trois onglets :

1. **Comparer et modifier** — à l'ouverture, le tableau n'affiche que les
   capacités `moodle` **modifiées** pour les 5 rôles standard (la case
   « seulement les modifiées » est cochée par défaut). Affinez avec les tags
   (types, plugins, capacités, valeurs) ou la recherche, puis cliquez sur une
   cellule pour changer sa valeur (enregistrement AJAX immédiat). Le bloc de
   filtres peut être replié pour gagner de la place.
2. **Exporter** — choisissez les rôles et le contenu (toutes les définitions ou
   seulement les capacités modifiées), puis téléchargez le fichier JSON.
3. **Importer** — téléversez un fichier, vérifiez l'aperçu des changements, puis
   confirmez pour appliquer.

La même catégorie contient une page **Réglages** avec une seule option,
*Rôles sélectionnés par défaut* : la liste (multi-sélection des rôles du site,
mémorisée par shortname) des rôles cochés à l'ouverture des onglets *Comparer et
modifier* et *Exporter*. Ne rien sélectionner ouvre le tableau vide.

### Format du fichier d'export / import

Fichier **JSON** structuré ainsi :

```json
{
  "meta": {
    "format": "local_rolesaudit",
    "formatversion": 2,
    "sourcesite": "https://moodle.exemple.org",
    "sitename": "Moodle Exemple",
    "moodleversion": "2024100700.00",
    "moodlerelease": "4.5",
    "pluginversion": 2026072100,
    "timestamp": 1753080000,
    "exportedby": "Prénom Nom",
    "onlymodified": false,
    "context": "system"
  },
  "plugins": {
    "core": { "version": 2024100700, "name": "core" },
    "mod_quiz": { "version": 2024100700, "name": "Test" }
  },
  "roles": {
    "editingteacher": {
      "archetype": "editingteacher",
      "name": "Enseignant",
      "capabilities": {
        "moodle/course:manageactivities": 1,
        "mod/quiz:grade": -1
      }
    }
  }
}
```

Les valeurs de permission sont les constantes du cœur de Moodle :
`0` = Non défini, `1` = Autoriser, `-1` = Empêcher, `-1000` = Interdire.

À l'import, les **rôles sont appariés par leur nom abrégé** (`shortname`) et les
**capacités par leur nom**. Les rôles ou capacités inconnus sur le site cible
sont **ignorés et listés** (aucun rôle n'est créé automatiquement).

Le bloc `plugins` (format v2) recense le composant propriétaire et sa version
pour chaque capacité exportée. À l'import, il permet de classer les capacités
ignorées selon la cause : **plugin non installé**, **plugin installé mais dans
une version antérieure** (capacité pas encore déployée) ou **capacité inconnue**
(renommée/supprimée). Les fichiers v1 (sans bloc `plugins`) restent importables.

### Points importants

- **Contexte système uniquement** : l'outil compare/modifie les *définitions* de
  rôles, pas les surcharges par cours/catégorie (c'est ce qui est portable entre
  instances).
- **Garde anti-verrouillage** : une modification qui vous retirerait l'accès à la
  gestion des rôles (sur un rôle que vous détenez) est bloquée. Les
  administrateurs du site ne sont jamais concernés (ils contournent les
  capacités).
- **Garde anti-escalade** : on ne peut accorder (« Autoriser », ou sortir de
  « Interdire ») qu'une capacité que l'on détient soi-même au contexte système.
  Les capacités critiques (configuration du site, connexion en tant que, gestion
  des comptes, des rôles et des web services) ne peuvent être accordées que par
  un administrateur du site. La règle vaut pour l'édition en ligne comme pour
  l'import, où les lignes refusées sont signalées dès l'aperçu.
- **Import** : exige, en plus de `local/rolesaudit:manage`, la capacité cœur
  `moodle/role:manage`.
- **Traçabilité** : chaque écriture est consignée dans les **journaux du site**
  (*Administration du site → Rapports → Journaux*), par des événements propres au
  plugin — modification d'un droit en ligne, export et import — en plus des
  événements `capability_assigned` / `capability_unassigned` du cœur.
- **Interdire (`Prohibit`)** est une valeur terminale *à l'évaluation des
  permissions* : elle ne peut pas être surchargée dans un contexte inférieur
  (catégorie, cours). La *définition* du rôle, elle, reste modifiable ici : vous
  pouvez toujours faire repasser une capacité de « Interdire » à « Autoriser »,
  « Empêcher » ou « Non défini ».
- Le plugin ne stocke **aucune donnée personnelle** ; il agit sur la table
  cœur `role_capabilities` via les API officielles `assign_capability()` /
  `unassign_capability()` — celles qu'utilise la page « Définir un rôle » du cœur.

### Licence

2026 Septeo learn-ymag@septeo.com

Ce programme est un logiciel libre : vous pouvez le redistribuer et/ou le
modifier selon les termes de la GNU General Public License telle que publiée par
la Free Software Foundation, en version 3 ou (à votre choix) toute version
ultérieure. Voir [LICENSE.md](LICENSE.md).

---

## 🇬🇧 English

### Overview

**Roles Audit** (`local_rolesaudit`) is a Moodle *local* plugin that lets you
**compare, edit, export and import** the permission values of role capabilities
at the **system context** (i.e. the role *definitions*).

It is a friendlier replacement for Moodle's core
*Site administration → Users → Permissions → Capability overview*
(`/admin/tool/capability/index.php`): a clear, colour-coded table, one-click
inline editing, and values that can be transferred between sites.

### Features

- **Compare**: a *roles (columns) × capabilities (rows)* table, colour-coded per
  value (Not set / Allow / Prevent / Prohibit).
- **Spot changes**: capabilities whose value differs from their **initial
  configuration** (the role archetype default) are flagged with an
  **asterisk `*`**.
- **Dynamic filtering**: all data is loaded up front, then filtered
  **client-side, with no reload**, through a **collapsible filter box**:
  clickable tag lines (roles, plugin types, plugins, capabilities, values) with
  a **downward cascade**, a capability search box and an "only changed"
  checkbox. By default only the 5 standard roles (shortnames `manager`,
  `coursecreator`, `editingteacher`, `teacher`, `student` — the list is
  configurable in the plugin **Settings**), the `moodle` type and the "changed"
  checkbox are active. Tag strips scroll by **click-and-drag** (grab cursor) and
  each line has two round buttons to **select all / deselect all** of what it
  currently shows. Every line label is followed by a
  **`(selected/total)` counter**: it covers the whole line, off-screen tags
  included, and a search within the line does not change it. Plugin tags are
  labelled `type/plugin` and capability tags
  `plugin:action`, all sorted alphabetically. The "Roles" line lists the **roles
  pre-selected in the Settings first**, then all the others, each group sorted
  alphabetically; the grouping follows the setting rather than the current
  selection, so tags do not move around as you tick and untick them.
- **Search within a line**: a magnifier button expands a search box, with
  **autocomplete** (arrow keys, `Enter` to target one entry), on every line but
  "Values", **narrowing the tags that line shows** — and therefore the reach of its
  two selection buttons. Closing the box (magnifier or `Esc`) clears the search.
- **Reuse a filter set**: two buttons **save** and **reload** the whole current
  filter selection (kept in the browser's local storage).
- **Export the table to CSV**: one button downloads the table as currently
  filtered, as a `;`-delimited CSV (capability name, description, then the
  permission and a "changed" flag for every displayed role).
- **Comfortable reading**: frozen header row and first column to navigate a
  large table without losing context, fixed column width, and **colours taken
  from the theme** (Bootstrap variables, else defaults).
- **Inline edit**: click a cell to open a 4-value menu; the change is saved
  immediately over AJAX, no page reload.
- **Export**: produces a JSON file (all definitions, or only changed
  capabilities) that can be applied on another Moodle site.
- **Import**: upload such a file, review a **preview of the changes**
  (current → new) before anything is applied, then apply on confirmation.

### Requirements

- **Moodle 4.5** minimum (`requires = 2024100700`), tested up to Moodle 5.2
  (`supported = [405, 502]`).
- The tool is **reserved to site administrators**: its pages are only
  registered for `moodle/site:config`. The `local/rolesaudit:manage` capability
  is granted to no archetype; since 1.3.10 the upgrade withdraws it from the
  *Manager*-archetype roles that received it at install time.

### Installation

#### From a ZIP file

1. As an admin, go to *Site administration → Plugins → Install plugins*.
2. Upload the plugin ZIP file.
3. Review the validation report and finish the installation.

#### Manually

Copy the contents of this directory into:

    {your/moodle/dirroot}/local/rolesaudit

Then log in as an admin and go to *Site administration → Notifications*, or run:

    $ php admin/cli/upgrade.php

> **Developers**: the JavaScript module ships pre-built in `amd/build/`. After
> editing `amd/src/compare.js`, rebuild it with
> `npx grunt amd --files=local/rolesaudit/amd/src/compare.js` from the Moodle
> root (`--files=public/local/rolesaudit/amd/src/compare.js` on Moodle 5.1+).
> Avoid `--root=…`: if the resolved path leaves the Moodle tree, grunt rebuilds
> the whole core. The sources must physically sit in the Moodle tree (no
> junction or symlink). Never hand-edit `amd/build/compare.min.js`.
>
> The build produces **two** files and both must be committed:
> `amd/build/compare.min.js` and `amd/build/compare.min.js.map`. Without the
> source map, Moodle serves the raw ES6 source whenever the JavaScript cache is
> disabled (`$CFG->cachejs = false`, developer mode), which throws
> `Uncaught SyntaxError: Cannot use import statement outside a module` followed by
> `No define call for local_rolesaudit/compare` and breaks the *Compare and edit*
> and *Export* pages.

### Usage

The tool lives under
*Site administration → Users → Permissions → Roles Audit*, as three tabs:

1. **Compare & edit** — on load the table only shows the **changed** `moodle`
   capabilities for the 5 standard roles (the "only changed" checkbox is ticked
   by default). Refine with the tags (types, plugins, capabilities, values) or
   the search box, then click a cell to change its value (saved instantly over
   AJAX). The filter box can be collapsed to save space.
2. **Export** — choose the roles and the content (all definitions, or only
   changed capabilities), then download the JSON file.
3. **Import** — upload a file, review the change preview, then confirm to apply.

The same category holds a **Settings** page with a single option, *Default
selected roles*: the list (a multi-select of the site roles, stored by
shortname) of roles ticked when the *Compare & edit* and *Export* tabs load.
Selecting none opens the table empty.

### Export / import file format

A **JSON** file structured as follows:

```json
{
  "meta": {
    "format": "local_rolesaudit",
    "formatversion": 2,
    "sourcesite": "https://moodle.example.org",
    "sitename": "Example Moodle",
    "moodleversion": "2024100700.00",
    "moodlerelease": "4.5",
    "pluginversion": 2026072100,
    "timestamp": 1753080000,
    "exportedby": "First Last",
    "onlymodified": false,
    "context": "system"
  },
  "plugins": {
    "core": { "version": 2024100700, "name": "core" },
    "mod_quiz": { "version": 2024100700, "name": "Quiz" }
  },
  "roles": {
    "editingteacher": {
      "archetype": "editingteacher",
      "name": "Teacher",
      "capabilities": {
        "moodle/course:manageactivities": 1,
        "mod/quiz:grade": -1
      }
    }
  }
}
```

Permission values are the Moodle core constants:
`0` = Not set, `1` = Allow, `-1` = Prevent, `-1000` = Prohibit.

On import, **roles are matched by their `shortname`** and **capabilities by their
name**. Roles or capabilities unknown on the target site are **skipped and
reported** (roles are never created automatically).

The `plugins` block (format v2) records the owning component and its version for
each exported capability. On import it lets the tool classify skipped
capabilities by cause: **plugin not installed**, **plugin installed but at an
older version** (capability not yet deployed) or **unknown capability**
(renamed/removed). v1 files (without the `plugins` block) remain importable.

### Important notes

- **System context only**: the tool compares/edits role *definitions*, not
  per-course/category overrides (definitions are the portable part).
- **Anti-lockout guard**: a change that would remove your own access to role
  management (on a role you hold) is blocked. Site administrators are never
  affected (they bypass capability checks).
- **Traceability**: every write is recorded in the **site logs**
  (*Site administration → Reports → Logs*) through the plugin's own events —
  inline permission change, export and import — on top of core's
  `capability_assigned` / `capability_unassigned` events.
- **Prohibit** is a terminal value *when permissions are evaluated*: it cannot be
  overridden in a lower context (category, course). The role *definition* itself
  stays editable here: you can always switch a capability back from Prohibit to
  Allow, Prevent or Not set.
- The plugin stores **no personal data**; it operates on the core
  `role_capabilities` table through the official `assign_capability()` /
  `unassign_capability()` APIs — the ones core's own *Define role* page uses.

### License

2026 Septeo learn-ymag@septeo.com

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version. See [LICENSE.md](LICENSE.md).
