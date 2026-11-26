#!/usr/bin/env bash
# Migration complète de l'ancien site DAPSA (Drupal 7) vers Drupal 11.
# Prérequis :
#   - la base Drupal 7 importée dans MySQL/MariaDB (connexion "migrate" de settings.php) ;
#   - D7_FILES_ROOT : dossier racine de l'ancien site (celui qui contient sites/default/files).
# Usage : D7_FILES_ROOT=/chemin/public_html scripts/migrer-depuis-d7.sh
set -euo pipefail
cd "$(dirname "$0")/.."
DRUSH=vendor/bin/drush
if [ -z "${D7_FILES_ROOT:-}" ]; then
  echo "Indiquer D7_FILES_ROOT (racine de l'ancien site Drupal 7)" >&2
  exit 1
fi

# Modules nécessaires avant la migration (champs commentaires, dates de début/fin, blocs).
$DRUSH en -y comment datetime_range block_content migrate migrate_drupal migrate_plus migrate_tools migrate_upgrade \
  pathauto token metatag admin_toolbar redirect simple_sitemap better_exposed_filters language locale search twig_tweak

# Génère les migrations à partir de la base Drupal 7.
$DRUSH migrate:upgrade --legacy-db-key=migrate --legacy-root="$D7_FILES_ROOT" --configure-only

# Structure (types de contenu, vocabulaires, champs), puis fichiers, comptes, contenus, adresses, menus.
STEPS=(
  upgrade_d7_filter_format upgrade_d7_node_type upgrade_d7_taxonomy_vocabulary
  upgrade_d7_field upgrade_d7_field_instance upgrade_d7_view_modes
  upgrade_d7_field_instance_widget_settings upgrade_d7_field_formatter_settings
  upgrade_d7_file upgrade_d7_user_role upgrade_d7_user
  upgrade_d7_taxonomy_term_tags upgrade_d7_taxonomy_term_newsletter
  upgrade_d7_node_complete_actualite upgrade_d7_node_complete_agenda upgrade_d7_node_complete_cartographie
  upgrade_d7_node_complete_features upgrade_d7_node_complete_mot_du_directeur upgrade_d7_node_complete_page
  upgrade_d7_node_complete_partenaires upgrade_d7_node_complete_publication
  upgrade_d7_url_alias upgrade_d7_menu upgrade_d7_menu_links
  upgrade_block_content_type upgrade_block_content_body_field_storage upgrade_block_content_body_field upgrade_d7_custom_block
)
for m in "${STEPS[@]}"; do
  echo "==> $m"
  $DRUSH migrate:import "$m" --force --continue-on-failure || true
done

# Sécurité : les anciens comptes sont conservés comme auteurs des contenus,
# mais bloqués et sans mot de passe utilisable. L'administrateur (uid 1) n'est pas concerné.
$DRUSH php:eval '
$storage = \Drupal::entityTypeManager()->getStorage("user");
foreach ($storage->loadMultiple() as $u) {
  if ($u->id() > 1) { $u->block(); $u->setPassword(\Drupal::service("password_generator")->generate(32)); $u->save(); }
}'
$DRUSH cr
echo "Migration terminée."
