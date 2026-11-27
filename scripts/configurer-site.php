<?php

/**
 * @file
 * Configuration du nouveau site DAPSA, à lancer après la migration :
 *   vendor/bin/drush php:script scripts/configurer-site.php
 *
 * - active le thème DAPSA ;
 * - crée les listes (vues) : publications, actualités, agenda, partenaires, mot du directeur ;
 * - reconstruit le menu principal ;
 * - place les blocs système dans le thème.
 * Le script peut être relancé sans risque : il remplace ce qu'il a déjà créé.
 */

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\views\Entity\View;

// 1. Thème.
\Drupal::service('theme_installer')->install(['dapsa', 'claro']);
\Drupal::configFactory()->getEditable('system.theme')->set('default', 'dapsa')->set('admin', 'claro')->save();
\Drupal::configFactory()->getEditable('node.settings')->set('use_admin_theme', TRUE)->save();
\Drupal::configFactory()->getEditable('system.site')
  ->set('name', "DAPSA – Direction de l'Analyse, de la Prévision et des Statistiques Agricoles")
  ->set('slogan', "Pour un développement durable et harmonieux de l'agriculture au Sénégal")
  ->set('page.front', '/node')
  ->save();

// Page de recherche dans les contenus (/search/node).
if (!\Drupal\search\Entity\SearchPage::load('node_search')) {
  \Drupal\search\Entity\SearchPage::create([
    'id' => 'node_search', 'label' => 'Contenus', 'plugin' => 'node_search', 'path' => 'node', 'weight' => -10, 'status' => TRUE,
  ])->save();
  \Drupal::configFactory()->getEditable('search.settings')->set('default_page', 'node_search')->save();
}

// 2. Mode d'affichage « carrousel » pour les actualités.
if (!EntityViewMode::load('node.carrousel')) {
  EntityViewMode::create(['id' => 'node.carrousel', 'label' => 'Carrousel', 'targetEntityType' => 'node'])->save();
}
foreach (['actualite' => 'carrousel', 'publication' => 'teaser', 'agenda' => 'teaser', 'partenaires' => 'teaser', 'mot_du_directeur' => 'teaser'] as $bundle => $mode) {
  $id = "node.$bundle.$mode";
  $display = EntityViewDisplay::load($id) ?: EntityViewDisplay::create([
    'targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => $mode, 'status' => TRUE,
  ]);
  $display->setStatus(TRUE)->save();
}

// 3. Vues.
$filtre_publie = [
  'id' => 'status', 'table' => 'node_field_data', 'field' => 'status', 'plugin_id' => 'boolean',
  'value' => '1', 'entity_type' => 'node', 'entity_field' => 'status', 'group' => 1,
];
$filtre_type = fn(string $bundle) => [
  'id' => 'type', 'table' => 'node_field_data', 'field' => 'type', 'plugin_id' => 'bundle',
  'value' => [$bundle => $bundle], 'entity_type' => 'node', 'entity_field' => 'type', 'group' => 1,
];
$tri_date = ['id' => 'created', 'table' => 'node_field_data', 'field' => 'created', 'plugin_id' => 'date', 'order' => 'DESC', 'entity_type' => 'node', 'entity_field' => 'created'];
$ligne = fn(string $mode) => ['type' => 'entity:node', 'options' => ['view_mode' => $mode]];
$cache = ['type' => 'tag', 'options' => []];

$vue = function (string $id, string $label, array $defaut, array $affichages) use ($cache) {
  if ($existante = View::load($id)) {
    $existante->delete();
  }
  $display = [
    'default' => [
      'id' => 'default', 'display_title' => 'Par défaut', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => $defaut + [
        'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
        'cache' => $cache,
        'query' => ['type' => 'views_query', 'options' => []],
        'exposed_form' => ['type' => 'basic', 'options' => ['submit_button' => 'Filtrer', 'reset_button' => TRUE, 'reset_button_label' => 'Réinitialiser']],
        'style' => ['type' => 'default', 'options' => []],
      ],
    ],
  ];
  $pos = 1;
  foreach ($affichages as $did => $a) {
    $display[$did] = ['id' => $did, 'display_title' => $a['titre'], 'display_plugin' => $a['plugin'], 'position' => $pos++, 'display_options' => $a['options']];
  }
  View::create(['id' => $id, 'label' => $label, 'base_table' => 'node_field_data', 'base_field' => 'nid', 'module' => 'views', 'status' => TRUE, 'display' => $display])->save();
};

// Publications : page avec filtres (type, mot-clé) + bloc des 5 dernières.
$vue('publications', 'Publications', [
  'title' => 'Publications',
  'filters' => [
    'status' => $filtre_publie,
    'type' => $filtre_type('publication'),
    'field_type_value' => [
      'id' => 'field_type_value', 'table' => 'node__field_type', 'field' => 'field_type_value', 'plugin_id' => 'list_field',
      'operator' => 'or', 'value' => [], 'exposed' => TRUE, 'group' => 1,
      'expose' => ['operator_id' => 'field_type_value_op', 'label' => 'Type de document', 'identifier' => 'type', 'multiple' => FALSE, 'remember' => FALSE, 'reduce' => FALSE],
    ],
    'title' => [
      'id' => 'title', 'table' => 'node_field_data', 'field' => 'title', 'plugin_id' => 'string', 'operator' => 'allwords',
      'value' => '', 'exposed' => TRUE, 'group' => 1, 'entity_type' => 'node', 'entity_field' => 'title',
      'expose' => ['operator_id' => 'title_op', 'label' => 'Mot-clé', 'identifier' => 'motcle', 'remember' => FALSE, 'placeholder' => 'arachide, EAA, riz…'],
    ],
  ],
  'sorts' => ['created' => $tri_date],
  'row' => $ligne('teaser'),
  'pager' => ['type' => 'full', 'options' => ['items_per_page' => 15]],
  'empty' => ['area_text_custom' => ['id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom', 'plugin_id' => 'text_custom', 'empty' => TRUE, 'content' => 'Aucune publication ne correspond à votre recherche.']],
], [
  'page_1' => ['titre' => 'Page', 'plugin' => 'page', 'options' => ['path' => 'publications']],
  'block_1' => ['titre' => 'Dernières publications', 'plugin' => 'block', 'options' => [
    'defaults' => ['filters' => FALSE, 'filter_groups' => FALSE, 'pager' => FALSE],
    'filters' => ['status' => $filtre_publie, 'type' => $filtre_type('publication')],
    'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
    'pager' => ['type' => 'some', 'options' => ['items_per_page' => 5]],
  ]],
]);

// Actualités : page + carrousel de l'accueil.
$vue('actualites', 'Actualités', [
  'title' => 'Actualités',
  'filters' => ['status' => $filtre_publie, 'type' => $filtre_type('actualite')],
  'sorts' => ['created' => $tri_date],
  'row' => $ligne('teaser'),
  'pager' => ['type' => 'full', 'options' => ['items_per_page' => 10]],
], [
  'page_1' => ['titre' => 'Page', 'plugin' => 'page', 'options' => ['path' => 'actualites']],
  'block_carrousel' => ['titre' => 'Carrousel', 'plugin' => 'block', 'options' => [
    'defaults' => ['row' => FALSE, 'pager' => FALSE],
    'row' => $ligne('carrousel'),
    'pager' => ['type' => 'some', 'options' => ['items_per_page' => 6]],
  ]],
]);

// Agenda : page + 3 derniers événements.
$tri_evenement = ['id' => 'field_date_value', 'table' => 'node__field_date', 'field' => 'field_date_value', 'plugin_id' => 'datetime', 'order' => 'DESC', 'granularity' => 'second'];
$vue('agenda', 'Agenda', [
  'title' => 'Agenda',
  'filters' => ['status' => $filtre_publie, 'type' => $filtre_type('agenda')],
  'sorts' => ['field_date_value' => $tri_evenement],
  'row' => $ligne('teaser'),
  'pager' => ['type' => 'full', 'options' => ['items_per_page' => 10]],
], [
  'page_1' => ['titre' => 'Page', 'plugin' => 'page', 'options' => ['path' => 'agenda']],
  'block_1' => ['titre' => 'Prochains événements', 'plugin' => 'block', 'options' => [
    'defaults' => ['pager' => FALSE],
    'pager' => ['type' => 'some', 'options' => ['items_per_page' => 3]],
  ]],
]);

// Partenaires.
$vue('partenaires', 'Partenaires', [
  'filters' => ['status' => $filtre_publie, 'type' => $filtre_type('partenaires')],
  'sorts' => ['title' => ['id' => 'title', 'table' => 'node_field_data', 'field' => 'title', 'plugin_id' => 'standard', 'order' => 'ASC', 'entity_type' => 'node', 'entity_field' => 'title']],
  'row' => $ligne('teaser'),
  'pager' => ['type' => 'none', 'options' => []],
], ['block_1' => ['titre' => 'Bloc', 'plugin' => 'block', 'options' => []]]);

// Mot du directeur.
$vue('mot_du_directeur', 'Mot du directeur', [
  'filters' => ['status' => $filtre_publie, 'type' => $filtre_type('mot_du_directeur')],
  'sorts' => ['created' => $tri_date],
  'row' => $ligne('teaser'),
  'pager' => ['type' => 'some', 'options' => ['items_per_page' => 1]],
], ['block_1' => ['titre' => 'Bloc', 'plugin' => 'block', 'options' => []]]);

// 4. Menu principal.
$liens = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'main']);
foreach ($liens as $lien) {
  $lien->delete();
}
$poids = 0;
foreach ([
  ['Accueil', 'internal:/'],
  ['La DAPSA', 'entity:node/25'],
  ['Statistiques agricoles', 'entity:node/6'],
  ['Politique agricole', 'entity:node/5'],
  ['Projets et programmes', 'entity:node/98'],
  ['Publications', 'internal:/publications'],
  ['Actualités', 'internal:/actualites'],
  ['Agenda', 'internal:/agenda'],
] as [$titre, $uri]) {
  MenuLinkContent::create(['title' => $titre, 'link' => ['uri' => $uri], 'menu_name' => 'main', 'weight' => $poids++, 'expanded' => FALSE])->save();
}

// Le lien « Accueil » fourni par le profil d'installation fait doublon : on le désactive.
\Drupal::service('menu_link.static.overrides')->saveOverride('standard.front_page', ['enabled' => FALSE]);

// 5. Blocs du thème (messages, onglets, contenu principal).
// Les blocs copiés automatiquement depuis l'ancien thème par défaut sont retirés.
foreach (\Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['theme' => 'dapsa']) as $bloc) {
  if (!in_array($bloc->id(), ['dapsa_messages', 'dapsa_onglets', 'dapsa_contenu'], TRUE)) {
    $bloc->delete();
  }
}
foreach ([
  'dapsa_messages' => ['plugin' => 'system_messages_block', 'region' => 'highlighted', 'weight' => -10],
  'dapsa_onglets' => ['plugin' => 'local_tasks_block', 'region' => 'highlighted', 'weight' => -5],
  'dapsa_contenu' => ['plugin' => 'system_main_block', 'region' => 'content', 'weight' => 0],
] as $id => $b) {
  if ($bloc = Block::load($id)) {
    $bloc->delete();
  }
  Block::create(['id' => $id, 'theme' => 'dapsa', 'plugin' => $b['plugin'], 'region' => $b['region'], 'weight' => $b['weight'], 'settings' => ['label_display' => '0']])->save();
}

drupal_flush_all_caches();
echo "Configuration terminée.\n";

// 6. Droits des visiteurs : consulter et rechercher les contenus.
foreach (['anonymous', 'authenticated'] as $role) {
  user_role_grant_permissions($role, ['access content', 'search content']);
}
drupal_flush_all_caches();

// 7. Nettoyage des contenus migrés :
// - les anciens boutons JavaScript (onclick) deviennent de vrais liens ;
// - les liens absolus vers l'ancien domaine deviennent des liens relatifs.
$stockage = \Drupal::entityTypeManager()->getStorage('node');
$ids = \Drupal::entityQuery('node')->accessCheck(FALSE)->exists('body')->execute();
foreach ($stockage->loadMultiple($ids) as $noeud) {
  $avant = $noeud->get('body')->value;
  $apres = preg_replace_callback(
    '#<button[^>]*onclick="window\.location\.href=\'([^\']+)\';?"[^>]*>(.*?)</button>#s',
    fn($m) => '<a class="bouton-tuile" href="' . $m[1] . '">' . trim(strip_tags($m[2], '<br>')) . '</a>',
    $avant
  );
  $apres = preg_replace('#https?://(www\.)?dapsa\.gouv\.sn/#', '/', $apres);
  if ($apres !== $avant) {
    $noeud->get('body')->value = $apres;
    $noeud->setNewRevision(FALSE);
    $noeud->save();
  }
}
drupal_flush_all_caches();

// 8. Listes thématiques de l'ancien site, recréées à leurs anciennes adresses
//    (les liens « ici » des pages Statistiques, Politique agricole, etc. y mènent).
$filtre_etiquette = fn(int $tid) => [
  'id' => 'field_tags_target_id', 'table' => 'node__field_tags', 'field' => 'field_tags_target_id',
  'plugin_id' => 'taxonomy_index_tid', 'vid' => 'tags', 'type' => 'select', 'limit' => TRUE,
  'operator' => 'or', 'value' => [$tid => $tid], 'group' => 1,
];
$pages_listes = [];
$poids_liste = 0;
foreach ([
  'rapports_eaa' => ['rapports-finaux-des-principaux-resultats-enquete-agricole-annuelle', "Rapports finaux des principaux résultats de l'Enquête Agricole Annuelle", 5],
  'etudes_thematiques' => ['rapport-d-etudes-thematiques-approfondis-sur-le-secteur-agricole', "Rapports d'études thématiques approfondies sur le secteur agricole", 7],
  'autres_rapports' => ['autres-rapports', 'Autres rapports', 8],
  'revues' => ['revues-conjointes-et-biennales', 'Revues conjointes et biennales', 9],
  'notes_fiches' => ['notes-politiques-et-fiches-d-informations', "Notes politiques et fiches d'information", 11],
] as $did => [$chemin, $titre, $tid]) {
  $pages_listes[$did] = ['titre' => $titre, 'plugin' => 'page', 'options' => [
    'path' => $chemin, 'title' => $titre,
    'defaults' => ['filters' => FALSE, 'filter_groups' => FALSE, 'title' => FALSE],
    'filters' => ['status' => $filtre_publie, 'field_tags_target_id' => $filtre_etiquette($tid)],
    'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
  ]];
}
$pages_listes['partenariats'] = ['titre' => 'Projets et partenariats', 'plugin' => 'page', 'options' => [
  'path' => 'projets-et-partenairiats', 'title' => 'Projets et partenariats',
  'defaults' => ['filters' => FALSE, 'filter_groups' => FALSE, 'title' => FALSE, 'pager' => FALSE],
  'filters' => ['status' => $filtre_publie, 'type' => $filtre_type('partenaires')],
  'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
  'pager' => ['type' => 'none', 'options' => []],
]];
$vue('listes_thematiques', 'Listes thématiques', [
  'title' => 'Publications',
  'filters' => ['status' => $filtre_publie],
  'sorts' => ['created' => $tri_date],
  'row' => $ligne('teaser'),
  'pager' => ['type' => 'full', 'options' => ['items_per_page' => 20]],
  'empty' => ['area_text_custom' => ['id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom', 'plugin_id' => 'text_custom', 'empty' => TRUE, 'content' => 'Aucun document pour le moment.']],
], $pages_listes);
drupal_flush_all_caches();
