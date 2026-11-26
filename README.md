# Site web de la DAPSA — Drupal 11

Nouveau site de la **Direction de l'Analyse, de la Prévision et des Statistiques Agricoles** (dapsa.gouv.sn), qui remplace l'ancien site Drupal 7 (plus maintenu depuis janvier 2025).

- **Drupal 11.4**, PHP 8.3 ou plus, MariaDB 10.6+ / MySQL 8.
- **Thème `dapsa`** (`web/themes/custom/dapsa`) : reprend la charte de l'ancien site (vert #487c39, bannière, logo République du Sénégal + DAPSA). Il s'adapte aux téléphones.
- **Contenu repris automatiquement** de l'ancien site : 114 contenus (60 publications, 14 actualités, 18 pages, agenda, partenaires…), 99 fichiers (dont 71 PDF rattachés à leurs publications) et 177 adresses de pages. Les anciennes adresses `/content/...` restent valables.

## Contenu du dépôt

| Dossier / fichier | Rôle |
|---|---|
| `composer.json`, `composer.lock` | Liste exacte des versions de Drupal et des modules |
| `web/themes/custom/dapsa/` | Thème du site (gabarits, CSS, images) |
| `scripts/migrer-depuis-d7.sh` | Reprend tout le contenu de l'ancien site Drupal 7 |
| `scripts/configurer-site.php` | Thème, listes (publications, actualités, agenda, partenaires), menu, recherche, nettoyage des contenus |
| `config/sync/` | Export complet de la configuration du site |

Ne sont **jamais** dans Git : `settings.php` (mots de passe), les fichiers envoyés (`web/sites/default/files`), les bases de données et les sauvegardes.

## Installer le site sur un serveur

1. **Récupérer le code et les dépendances**

   ```bash
   git clone https://github.com/Mamadou1997/dapsa-site.git
   cd dapsa-site
   composer install --no-dev
   ```

2. **Créer deux bases de données** : une vide pour le nouveau site (ex. `dapsa_d11`), une pour l'ancien site (ex. `dapsa_d7`). Importer dans la seconde l'export de l'ancienne base (`localhost.sql`).

3. **Installer Drupal**

   ```bash
   vendor/bin/drush site:install standard --locale=fr --db-url=mysql://UTILISATEUR:MOTDEPASSE@localhost/dapsa_d11 --site-name="DAPSA" --account-name=admin
   ```

4. **Indiquer l'ancienne base** : ajouter à la fin de `web/sites/default/settings.php`

   ```php
   $databases['migrate']['default'] = [
     'driver' => 'mysql',
     'database' => 'dapsa_d7',
     'username' => 'UTILISATEUR',
     'password' => 'MOTDEPASSE',
     'host' => 'localhost',
     'prefix' => 'dapsa_',
   ];
   $settings['config_sync_directory'] = '../config/sync';
   ```

5. **Migrer le contenu**, en indiquant le dossier de l'ancien site (celui qui contient `sites/default/files`)

   ```bash
   D7_FILES_ROOT=/chemin/vers/ancien/public_html scripts/migrer-depuis-d7.sh
   ```

6. **Configurer le site** (thème, listes, menu, recherche)

   ```bash
   vendor/bin/drush php:script scripts/configurer-site.php
   vendor/bin/drush search:index
   ```

7. **Définir le mot de passe administrateur.** Le compte administrateur reprend le nom de l'ancien site (`Dapsamaster`).

   ```bash
   vendor/bin/drush user:password Dapsamaster "un-mot-de-passe-solide"
   ```

8. **Mise en production** : pointer le domaine vers le dossier `web/`, activer HTTPS, et ajouter dans `settings.php` :

   ```php
   $settings['trusted_host_patterns'] = ['^dapsa\.gouv\.sn$', '^www\.dapsa\.gouv\.sn$'];
   ```

   Puis planifier la tâche `vendor/bin/drush cron` toutes les heures.

## Sécurité

- Les **52 anciens comptes** sont conservés comme auteurs des contenus, mais **bloqués** et sans mot de passe utilisable. Il faut recréer ou débloquer les comptes des personnes qui publient (*Administration › Personnes*).
- Les erreurs PHP ne doivent pas s'afficher en production : *Configuration › Développement › Journalisation et erreurs › « Aucun »*.
- Mettre à jour régulièrement : `composer update "drupal/*" --with-all-dependencies`, puis `vendor/bin/drush updatedb`.

## Points restant à traiter

- **Agenda** : les 3 événements publiés ont un texte de remplacement en latin (« Quis enim aut eum diligat… ») hérité de l'ancien site. Il faut le remplacer par les vraies descriptions.
- **Projets et programmes** : la page de l'ancien site ne contenait que « En cours… ». Le menu pointe vers *Projets et partenariats*.
- **Calendrier de diffusion** : il date de 2022, à mettre à jour.
- **Liens institutionnels** du pied de page : remplacer ADIE par Sénégal Numérique et mettre à jour le nom du Conseil Économique, Social et Environnemental (*Structure › Menus*).
- Contenus de types « article », « document », « médiathèque », « newsletter » et « formulaire » : ces types existent mais étaient vides dans l'ancien site.
