# La Réglette

Symfony 8.1 · PHP 8.4 minimum · MySQL (`pdo_mysql` et `intl` activés).

## Lancer en local

Configurer `DATABASE_URL` dans `.env.local`, puis :

```powershell
composer install
php bin/console doctrine:database:create --if-not-exists --env=dev
php bin/console doctrine:migrations:migrate --no-interaction --env=dev
php bin/console doctrine:fixtures:load --env=dev
symfony serve -d
symfony open:local
```

Les fixtures effacent les données existantes. Comptes de démonstration :

| Compte | Mot de passe |
| --- | --- |
| `admin@admin.fr` | `admin` |
| `open@open.fr` | `test` |
| `test@test.fr` | `test` |

## Préparer la production

Configurer `APP_ENV=prod`, `APP_DEBUG=0`, `APP_SECRET`, `DATABASE_URL`, `MAILER_DSN` et `MESSENGER_TRANSPORT_DSN` dans l'environnement du serveur ou `.env.local`.
Pour Messenger avec la base : `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0`.

```powershell
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console importmap:install --env=prod
php bin/console assets:install public --env=prod
php bin/console asset-map:compile --env=prod
php bin/console cache:clear --env=prod --no-debug
```

Configurer Apache/Nginx avec PHP et la racine web sur `public/`. CSS et JavaScript sont compilés par AssetMapper.

Laisser ces workers actifs avec le gestionnaire de processus du serveur :

```powershell
php bin/console messenger:consume async statistics --env=prod --no-debug -vv
php bin/console messenger:consume scheduler_default --env=prod --no-debug -vv
```

Après chaque déploiement, demander le redémarrage des workers :

```powershell
php bin/console messenger:stop-workers --env=prod
```

## Tester les mails

Démarrer Mailpit (par exemple depuis Laragon), SMTP sur `1025`, interface sur http://127.0.0.1:8025.

```powershell
$env:MAILER_DSN = 'smtp://127.0.0.1:1025'
$env:BOULDER_REPORT_TO = 'admin@admin.fr'
php bin/console app:blocs:rapport --env=dev
```

Le rapport apparaît directement dans Mailpit. Une inscription envoie aussi un mail de confirmation.

En production, générer le rapport puis traiter la file d'e-mails :

```powershell
php bin/console app:blocs:rapport --env=prod
php bin/console messenger:consume async --env=prod --time-limit=10 -vv
```

## Vérifier les autres fonctionnalités

```powershell
# Recalculer les statistiques et traiter les messages
php bin/console app:boulders:refresh-statistics --env=dev
php bin/console messenger:consume statistics --env=dev --time-limit=10 -vv
php bin/console messenger:stats --env=dev

# Vérifier le planning du rapport : lundi à 08:00, Europe/Paris
php bin/console debug:scheduler --env=dev
php bin/console messenger:consume scheduler_default --env=dev -vv

# Prévisualiser le démontage, puis le lancer avec confirmation
php bin/console app:blocs:demontage --semaines=6 --dry-run --env=dev
php bin/console app:blocs:demontage --semaines=6 --env=dev

# Lancer les tests (dépendances de développement requises)
php bin/phpunit
```

Les workers restent au premier plan : utiliser un terminal séparé et `Ctrl+C` pour les arrêter.
Pour vérifier les statistiques, enregistrer une ascension puis recharger la fiche du bloc après traitement de la file `statistics`. L'API publique est accessible sur `/api/blocs`.
