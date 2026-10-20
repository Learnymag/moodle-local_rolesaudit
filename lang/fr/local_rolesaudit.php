<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here (FR).
 *
 * @package     local_rolesaudit
 * @category    string
 * @copyright   2026 Septeo learn-ymag@septeo.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Audit des rôles';

// Capacité.
$string['rolesaudit:manage'] = 'Comparer, modifier, exporter et importer les définitions de capacités des rôles';

// Réglages du plugin.
$string['settings_heading'] = 'Réglages';
$string['setting_defaultroles'] = 'Rôles sélectionnés par défaut';
$string['setting_defaultroles_desc'] = 'Rôles cochés au chargement des onglets « Comparer et modifier » et « Exporter ». Si aucun rôle n\'est sélectionné, le tableau s\'ouvre vide.';

// Navigation / onglets.
$string['nav_compare'] = 'Comparer et modifier';
$string['nav_export'] = 'Exporter';
$string['nav_import'] = 'Importer';
$string['backtocompare'] = 'Retour à Comparer et modifier';

// Lignes de filtres dynamiques.
$string['filters_heading'] = 'Filtres';
$string['filters_hint'] = 'Chaque ligne est une liste d\'étiquettes que l\'on active ou désactive d\'un clic. Faites défiler une ligne avec les flèches situées à ses extrémités, la molette de la souris, ou en la faisant glisser.';
$string['filter_roles'] = 'Rôles';
$string['filter_types'] = 'Types de plugin';
$string['filter_plugins'] = 'Plugins';
$string['filter_capabilities'] = 'Capacités';
$string['filter_values'] = 'Valeurs';
$string['filter_onlymodified'] = 'Seulement les capacités modifiées par rapport à leurs configurations initiales';
$string['filter_count'] = 'Éléments sélectionnés sur cette ligne, sur tout ce qu\'elle propose. Une recherche dans la ligne ne le change pas.';
$string['filter_search'] = 'Rechercher une capacité…';
$string['filter_searchroles'] = 'Rechercher un rôle…';
$string['filter_searchtypes'] = 'Rechercher un type de plugin…';
$string['filter_searchplugins'] = 'Rechercher un plugin…';
$string['filter_searchtoggle'] = 'Afficher ou masquer la recherche de cette ligne';
$string['filter_scrollleft'] = 'Faire défiler cette ligne vers la gauche';
$string['filter_scrollright'] = 'Faire défiler cette ligne vers la droite';
$string['filter_scrollhint'] = 'Faites glisser, utilisez la molette ou les flèches aux deux extrémités pour faire défiler cette ligne';
$string['search_suggestions'] = 'Suggestions de capacités';
$string['field_role'] = 'Rôle';

// Barre d'outils de la page de comparaison.
$string['toolbar_savefilters'] = 'Enregistrer le jeu de filtres actuel';
$string['toolbar_loadfilters'] = 'Charger le jeu de filtres enregistré';
$string['toolbar_exportcsv'] = 'Exporter le tableau en CSV';
$string['filters_saved'] = 'Jeu de filtres enregistré dans ce navigateur.';
$string['filters_loaded'] = 'Jeu de filtres chargé.';
$string['filters_nosaved'] = 'Aucun jeu de filtres n\'a été enregistré dans ce navigateur.';
$string['csv_capability'] = 'Capacité';
$string['csv_description'] = 'Description';
$string['csv_modified'] = '{$a} modifié';

// Page de comparaison.
$string['compare_intro'] = 'Comparez côte à côte les définitions de capacités de plusieurs rôles et modifiez un droit directement dans le tableau. Les filtres ci-dessous réduisent l\'affichage aux rôles et aux capacités qui vous intéressent.';
$string['th_capability'] = 'Capacité';
$string['clicktoedit'] = 'Cliquer pour modifier ce droit';
$string['nodifferences'] = 'Aucune capacité ne correspond aux filtres actuels.';

// Légende.
$string['legend_modified'] = 'Valeur différente de la configuration initiale du rôle';
$string['legend_clicktoedit'] = 'Cliquez sur une cellule pour en modifier le droit';

// Export.
$string['export_intro'] = 'Exporte les définitions de capacités des rôles sélectionnés vers un fichier JSON importable sur une autre instance Moodle. Les rôles sont appariés par leur nom abrégé et les capacités par leur nom.';
$string['export_onlymodified'] = 'N\'exporter que les capacités modifiées par rapport au défaut';
$string['export_download'] = 'Télécharger le fichier d\'export';

// Import.
$string['import_intro'] = 'Téléversez un fichier JSON produit précédemment par l\'onglet Exporter. Un aperçu des changements est présenté avant toute application.';
$string['import_file'] = 'Fichier à importer (.json)';
$string['import_upload'] = 'Téléverser et prévisualiser';
$string['import_preview'] = 'Aperçu de l\'import';
$string['import_source'] = 'Fichier exporté depuis {$a->site} (Moodle {$a->release}) le {$a->date}.';
$string['import_summary'] = '{$a->changes} changement(s) à appliquer, {$a->unchanged} déjà à jour, {$a->skippedroles} rôle(s) inconnu(s) ignoré(s), {$a->skippedcaps} capacité(s) inconnue(s) ignorée(s).';
$string['import_current'] = 'Valeur actuelle';
$string['import_incoming'] = 'Nouvelle valeur';
$string['import_apply'] = 'Appliquer les changements sélectionnés';
$string['import_th_apply'] = 'Appliquer ?';
$string['import_selectrow'] = 'Appliquer {$a->capability} pour le rôle {$a->role}';
$string['import_selectedcount'] = '{$a->selected} sur {$a->total} changement(s) sélectionné(s)';
$string['import_noselection'] = 'Aucun changement n\'a été sélectionné, rien n\'a été appliqué.';
$string['import_nochanges'] = 'Le fichier ne contient aucun changement à appliquer.';
$string['import_skippedroles'] = 'Rôles ignorés (introuvables sur ce site)';
$string['import_skippedcaps_notinstalled'] = 'Capacités ignorées — plugin propriétaire non installé sur ce site';
$string['import_skippedcaps_older'] = 'Capacités ignorées — plugin installé mais dans une version antérieure (capacité pas encore déployée ici)';
$string['import_skippedcaps_unknown'] = 'Capacités ignorées — inconnues sur ce site (renommées, supprimées ou faute de frappe)';
$string['import_skippedcaps_plugin'] = '{$a->name} ({$a->component}) — non installé';
$string['import_skippedcaps_pluginolder'] = '{$a->name} ({$a->component}) — version installée {$a->localversion}, le site source avait {$a->fileversion}';
$string['import_startover'] = 'Recommencer avec un autre fichier';
$string['import_result'] = 'Résultat de l\'import';
$string['import_applied'] = '{$a} changement(s) appliqué(s) avec succès.';
$string['import_skippedlockout'] = '{$a} changement(s) ignoré(s) car ils auraient supprimé votre propre accès à la gestion des rôles.';
$string['import_skippedescalation'] = '{$a} changement(s) ignoré(s) car ils auraient accordé une capacité que vous n\'êtes pas habilité à accorder.';
$string['import_blocked_critical'] = 'Refusé : seul un administrateur du site peut accorder cette capacité.';
$string['import_blocked_notheld'] = 'Refusé : vous ne pouvez pas accorder une capacité que vous ne détenez pas vous-même.';
$string['import_failed'] = '{$a} changement(s) n\'ont pas pu être écrits en base et sont restés inchangés.';

// Erreurs.
$string['error_lockout'] = 'Ce changement a été bloqué car il supprimerait votre propre accès à la gestion des rôles.';
$string['error_escalation_critical'] = 'Ce changement a été bloqué car seul un administrateur du site peut accorder cette capacité.';
$string['error_escalation_notheld'] = 'Ce changement a été bloqué car vous ne pouvez pas accorder une capacité que vous ne détenez pas vous-même.';
$string['error_badfile'] = 'Le fichier téléversé n\'a pas pu être lu comme un export Audit des rôles valide.';
$string['error_badformat'] = 'Le fichier téléversé n\'est pas un fichier d\'export Audit des rôles.';
$string['error_badversion'] = 'Le fichier téléversé provient d\'une version plus récente du plugin et ne peut pas être importé.';
$string['error_sessionexpired'] = 'La session d\'import a expiré, ou un autre fichier a été téléversé depuis. Merci de téléverser à nouveau le fichier.';
$string['error_toolarge'] = 'Le fichier téléversé est trop volumineux pour être importé (maximum {$a}).';
$string['error_toomanyentries'] = 'Le fichier téléversé contient trop d\'entrées rôle/capacité pour être importé (maximum {$a}).';

// Événements.
$string['event_permission_changed'] = 'Droit de rôle modifié';
$string['event_roles_exported'] = 'Définitions de rôles exportées';
$string['event_roles_imported'] = 'Définitions de rôles importées';

// Confidentialité.
$string['privacy:metadata'] = 'Le plugin Audit des rôles ne stocke aucune donnée personnelle. Il ne fait que lire et modifier les définitions de rôles (les capacités de rôles du cœur de Moodle), qui ne sont pas des données personnelles.';
