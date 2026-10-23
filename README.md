# La Réglette

Application Symfony 8.1, PHP 8.4 minimum, Doctrine ORM et EasyAdmin.
Activer les extensions PHP demandées par Composer, ainsi que `intl` pour les dates dans EasyAdmin et `pdo_mysql` pour MySQL.

## Installation locale

```bash
composer install
```

Configurer la connexion `DATABASE_URL` dans `.env.local` (voir les valeurs de développement de `.env.dev`). Pour Mailpit : `MAILER_DSN=smtp://127.0.0.1:1025`.

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load
symfony serve -d
```

**Le chargement des fixtures efface les données existantes de la base ciblée.** Utiliser une base de démonstration. Ne pas ajouter `--append` : les adresses e-mail des fixtures sont fixes et uniques.

## Comptes de démonstration

Tous les comptes sont vérifiés. Les trois comptes initiaux sont conservés.

| Rôle | Adresse e-mail | Mot de passe |
| --- | --- | --- |
| Administrateur | `admin@admin.fr` | `admin` |
| Ouvreur | `open@open.fr` | `test` |
| Ouvreuse | `lea.ouvreur@example.com` | `test` |
| Ouvreur | `hugo.ouvreur@example.com` | `test` |
| Grimpeur | `test@test.fr` | `test` |
| Grimpeuse | `camille.grimpeur@example.com` | `test` |
| Grimpeur | `nicolas.grimpeur@example.com` | `test` |
| Grimpeuse | `sarah.grimpeur@example.com` | `test` |
| 16 autres grimpeurs | `grimpeur04@example.com` à `grimpeur19@example.com` inclus | `test` |

Les mots de passe sont hachés en base. Ces identifiants servent uniquement à la démonstration.

## Jeu de données et requêtes

- 1 administrateur, 3 ouvreurs et 20 grimpeurs : 24 comptes.
- 4 secteurs : La Dalle, Le Dévers, Le Toit et Les Arêtes.
- 10 blocs par secteur : 40 blocs, dont 10 démontés et 30 encore en place.
- Cotations françaises de 3 à 8A+, stockées sous forme d’identifiants ordonnés de 1 à 18 ; ouvertures échelonnées entre J−59 et J−1 au chargement.
- 300 ascensions, réparties entre les 20 grimpeurs. Chaque couple grimpeur/bloc est unique. Les dates sont postérieures à l'ouverture, antérieures au démontage lorsqu'il existe, et jamais futures.
- Les nombres de réussites par bloc diminuent avec la difficulté, de 15 à 1 ; deux blocs de cotation 8A+ n’ont aucune réussite.

Scénarios disponibles pour les requêtes actuelles :

| Requête | Données permettant de la tester |
| --- | --- |
| Ascensions personnelles (`AscentRepository::getAscentByUser`) | Chaque grimpeur possède des ascensions, notamment `test@test.fr`. |
| Blocs encore en place non réussis par un grimpeur (`getBoulderUndoByUSer`) | Chaque grimpeur possède des blocs restant à réussir ; Le Dévers — Bloc 08 et Les Arêtes — Bloc 06 n’ont aucune réussite. |
| Blocs sans réussite depuis deux semaines (`getBoulderUnSuccessSinceTimelaps`) | Le Dévers — Bloc 08, ainsi que La Dalle — Bloc 06, dont toutes les réussites datent de plus de deux semaines. Passer `new DateTimeImmutable('-2 weeks')`. |
| Blocs d’un ouvreur avec statistiques (`findOpenedBy`) | La section « Mes blocs ouverts » de `/profil` affiche uniquement ses blocs, y compris ceux démontés, avec leurs compteurs et moyennes stockés. |

Les dates sont relatives au jour de chargement. Recharger les fixtures pour retrouver les mêmes scénarios temporels après plusieurs semaines.

## Cotations et droits des ouvreurs

Les pages de secteurs, les listes et les fiches de blocs sont accessibles sans compte, avec les filtres de secteur, couleur et cotation.
L’enregistrement et la suppression d’ascensions restent réservés aux comptes connectés disposant du rôle grimpeur (y compris les ouvreurs et administrateurs par héritage).
Le filtre personnel « Pas encore réussis » est proposé uniquement après connexion.

Les cotations restent des entiers en base. `App\Enum\Grade` centralise les libellés français :

| Identifiant | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Cotation | 3 | 4 | 5 | 5+ | 6A | 6A+ | 6B | 6B+ | 6C |

| Identifiant | 10 | 11 | 12 | 13 | 14 | 15 | 16 | 17 | 18 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Cotation | 6C+ | 7A | 7A+ | 7B | 7B+ | 7C | 7C+ | 8A | 8A+ |

Les formulaires proposent des libellés français et la liste HTML accepte une borne minimale et/ou maximale, inclusives, combinables avec le secteur et la couleur.
Une plage inversée est refusée. Le stockage existant ne change pas de type et les identifiants déjà présents ne sont pas modifiés.
Les fixtures mises à jour couvrent toute l’échelle ; leur rechargement efface la base ciblée et n’est pas nécessaire pour utiliser les nouveaux affichages.

`ROLE_OUVREUR` hérite de `ROLE_GRIMPEUR` dans `security.yaml`.
Le contrôleur vérifie avant toute modification ou tout démontage que l’ouvreur est bien l’auteur du bloc ; sinon il répond HTTP 403.
L’administrateur conserve ses droits sur tous les blocs, y compris ceux dont l’auteur a été supprimé.
Le profil d’un ouvreur charge ses blocs via une requête QueryBuilder filtrée sur `openedBy`, et lit les statistiques stockées par Messenger.

## API de l’écran de la salle

`GET /api/blocs` est public et renvoie du JSON, sans connexion.

Filtres facultatifs et combinables, avec les mêmes noms et valeurs que la liste HTML :

| Paramètre | Valeur |
| --- | --- |
| `sector` | Identifiant du secteur, entier positif |
| `color` | Couleur exacte, par exemple `rouge` |
| `gradeMin` | Identifiant de cotation minimale, de 1 à 18, inclusif |
| `gradeMax` | Identifiant de cotation maximale, de 1 à 18, inclusif |
| `grade` | Identifiant exact de cotation, de 1 à 18, conservé pour compatibilité |

Exemple : `GET /api/blocs?sector=1&color=rouge&gradeMin=4&gradeMax=7` sélectionne les blocs de cotation 5+ à 6B.
Les paramètres sont directement dans la query string, sans le préfixe de formulaire HTML `boulder_filter`.
Le filtre personnel `unfinished=1` ne peut pas être évalué sans grimpeur connecté : il renvoie une erreur JSON HTTP 400.
Les valeurs de filtres invalides renvoient également HTTP 400.

Exemple de réponse HTTP 200 :

```json
{
  "blocs": [
    {
      "id": 12,
      "name": "Le dernier mouvement",
      "color": "rouge",
      "grade": 3,
      "gradeLabel": "5",
      "openedAt": "2026-10-11T10:00:00+00:00",
      "sector": {"id": 1, "name": "La Dalle"},
      "successCount": 1,
      "averageRating": 4,
      "ascents": [
        {"id": 42, "completedAt": "2026-10-20T15:00:00+00:00", "rating": 4}
      ]
    }
  ]
}
```

Seuls les blocs déjà ouverts et pas encore démontés sont inclus. Un démontage prévu dans le futur ne retire pas le bloc.
Les blocs sont triés par ouverture décroissante, puis par identifiant décroissant ; les réussites sont triées de la plus récente à la plus ancienne.
Les dates suivent ISO 8601 avec leur décalage horaire.
Les réussites comprennent leur identifiant, leur date et leur note ; les données des comptes et les commentaires personnels ne sont pas exposés.
Le nombre de réussites (`successCount`) et la note moyenne (`averageRating`) sont lus sur le bloc.
Ils sont mis à jour par le worker Messenger, pas pendant la requête HTTP : ils peuvent donc temporairement différer du détail des ascensions.
Sans résultat, la réponse reste `{"blocs": []}`.

## Statistiques asynchrones avec Messenger

Le bloc stocke `successCount`, `averageRating` (null sans réussite) et `climberCount`.
La fiche HTML, l’API et l’écran de la salle lisent ces valeurs enregistrées.

Un listener Doctrine envoie `RecalculateBoulderStatistics` après chaque insertion ou suppression d’ascension.
Il couvre aussi EasyAdmin, la suppression des ascensions d’un compte supprimé et les modifications de note ou de bloc.
Le message contient uniquement l’identifiant du bloc.
Le message et l’ascension sont enregistrés dans la même transaction Doctrine : une annulation de la transaction annule aussi l’envoi.

Le transport `statistics` utilise Doctrine, dans la table `messenger_messages` avec `queue_name = statistics`.
Cette table est créée par la migration initiale. Le nouveau transport utilise `auto_setup=0` : appliquez les migrations avant de l’utiliser.
La file est séparée des e-mails. Le handler recalcule les statistiques à partir des ascensions actuellement en base.
Le traitement est idempotent et verrouille la ligne du bloc dans une transaction pour sérialiser les recalculs concurrents.
Les échecs suivent la stratégie de retry de Messenger et finissent dans le transport `failed` si les nouvelles tentatives échouent.

```bash
php bin/console doctrine:migrations:migrate --no-interaction
# Initialiser les statistiques des blocs déjà présents :
php bin/console app:boulders:refresh-statistics
# Démarrer le worker (laisser ce terminal ouvert) :
php bin/console messenger:consume statistics -vv
```

Pour démontrer le comportement :

1. Arrêtez le worker avec Ctrl+C.
2. Notez le nombre de réussites et la moyenne dans la fiche d’un bloc ou dans `/api/blocs`.
3. Créez une ascension sur ce bloc : elle est enregistrée, mais les statistiques restent inchangées.
4. Vérifiez la présence du message avec `php bin/console messenger:stats statistics`.
5. Lancez `php bin/console messenger:consume statistics -vv`, puis rechargez la fiche ou l’API : les statistiques sont actualisées.
6. Arrêtez le worker, supprimez l’ascension et répétez : le compteur diminue après traitement. Sans ascension restante, la moyenne redevient null.

Le test `AsyncBoulderStatisticsTest` reproduit ce scénario avec les requêtes HTTP et la véritable commande `messenger:consume`, sur le transport Doctrine en SQLite mémoire.
Il vérifie aussi les messages répétés, les transactions annulées et la suppression d’un compte.

Référence : [documentation Symfony Messenger](https://symfony.com/doc/current/messenger.html).

## Commande de démontage

`app:blocs:demontage` affiche les blocs en place dont l’ouverture remonte à strictement plus de N semaines, 6 par défaut.
Les dates d’ouverture et de démontage sont conservées ; aucun bloc ni aucune ascension n’est supprimé.

```bash
# Liste seule, sans écriture et sans confirmation :
php bin/console app:blocs:demontage --dry-run
php bin/console app:blocs:demontage --semaines=8 --dry-run

# Affiche le tableau puis demande confirmation (Non par défaut) :
php bin/console app:blocs:demontage
php bin/console app:blocs:demontage --semaines=8
```

`--semaines` doit être un entier strictement positif.
Sans confirmation, aucun démontage n’est effectué : une réponse négative annule l’action ; `--no-interaction` refuse de démonter (sauf `--dry-run`).
La confirmation valide uniquement les blocs qui viennent d’être présentés dans le tableau.

## Rapport hebdomadaire avec Scheduler

Le rapport du lundi utilise la **requête 3**, distincte du critère d’ancienneté de la commande de démontage :
**les blocs en place sans aucune réussite depuis au moins deux semaines**.
Il sélectionne les blocs ouverts depuis au moins deux semaines sans ascension pendant les 14 derniers jours, y compris ceux qui n’ont jamais été réussis.
Une ascension exactement à la borne des deux semaines exclut le bloc du rapport.
Les blocs récemment ouverts, pas encore ouverts ou déjà démontés sont exclus.

Scheduler déclenche `GenerateWeeklyBoulderReport` **chaque lundi à 08:00, fuseau Europe/Paris**, en tenant compte du changement d’heure.
Son handler écrit la liste détaillée dans les logs et envoie le rapport HTML rouge et noir, avec une alternative texte.
Il n’effectue **aucun démontage automatique**.
Le planning utilise le cache Symfony pour conserver son état et rattraper uniquement le dernier passage manqué après une interruption.

```bash
# Vérifier le planning :
php bin/console debug:scheduler

# Laisser un seul worker de planning actif :
php bin/console messenger:consume scheduler_default -vv

# Générer immédiatement le même rapport pour le tester, sans attendre lundi :
php bin/console app:blocs:rapport
```

Destinataire et expéditeur sont configurables dans `.env.local` :

```dotenv
BOULDER_REPORT_TO=admin@admin.fr
BOULDER_REPORT_FROM=contact@lareglette.fr
MAILER_DSN=smtp://127.0.0.1:1025
```

En développement, le mailer livre directement à Mailpit. Consulter son interface sur `http://127.0.0.1:8025` si ce port est celui exposé localement.
En production, les e-mails passent par le transport Messenger `async` : il faut aussi lancer `php bin/console messenger:consume async -vv`.
La file de statistiques reste indépendante (`statistics`).
Un rapport vide est également envoyé afin de confirmer l’exécution du planning.

### Envoyer les e-mails immédiatement, sans attendre lundi

Depuis la racine du projet, cette commande génère et envoie immédiatement le même rapport que le Scheduler :

```bash
php bin/console app:blocs:rapport --env=dev
```

En environnement `dev`, aucun worker n’est nécessaire pour envoyer l’e-mail à Mailpit. Le rapport conserve sa présentation HTML rouge et noire et n’effectue aucun démontage.
La commande peut être relancée à chaque démonstration ; chaque exécution produit un nouvel e-mail.

Pour choisir ponctuellement le destinataire et forcer l’envoi vers Mailpit, dans PowerShell :

```powershell
$env:MAILER_DSN = 'smtp://127.0.0.1:1025'
$env:BOULDER_REPORT_TO = 'admin@admin.fr'
php bin/console app:blocs:rapport --env=dev
```

Ouvrir ensuite [Mailpit](http://127.0.0.1:8025) pour consulter le message, si Mailpit expose son interface sur ce port. Mailpit doit être démarré et écouter sur le port SMTP 1025.
Le destinataire par défaut est `BOULDER_REPORT_TO` ; il peut aussi être défini durablement dans `.env.local`.

En production, le rapport est généré immédiatement mais son e-mail est mis en file. Pour envoyer les e-mails en attente sans attendre le Scheduler :

```bash
php bin/console app:blocs:rapport --env=prod
php bin/console messenger:consume async --env=prod --time-limit=10 -vv
```

Le worker traite les messages disponibles dans `async`, puis s’arrête après environ dix secondes. Cela inclut les autres e-mails éventuellement en attente dans cette file.
Pour les e-mails de confirmation de compte, soumettre le formulaire d’inscription ou modifier l’adresse e-mail du profil : ils sont également envoyés directement à Mailpit en `dev`.

Références : [Symfony Console](https://symfony.com/doc/current/console.html), [Symfony Scheduler](https://symfony.com/doc/current/scheduler.html).

## Tests

```bash
php bin/phpunit
```

Pour exécuter aussi les tests de base de données, utiliser SQLite en mémoire avec l'extension `pdo_sqlite`. Exemple PowerShell :

```powershell
$env:DATABASE_URL = 'sqlite:///:memory:'
$env:MAILER_DSN = 'null://null'
php -d extension=pdo_sqlite -d memory_limit=512M bin/phpunit
```
