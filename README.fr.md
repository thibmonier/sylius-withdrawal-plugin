# Sylius Withdrawal Plugin

> English: [README.md](README.md) <!-- vitrine:lang -->

[![Build](https://github.com/thibmonier/sylius-withdrawal-plugin/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-withdrawal-plugin/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Le bouton européen « renoncer au contrat ici » pour Sylius 2.**

Depuis le **19 juin 2026**, toute boutique en ligne vendant à des consommateurs dans l'Union européenne doit proposer
une *fonction de rétractation* : un bouton clairement libellé permettant aux consommateurs de se rétracter en ligne
d'un contrat à distance, suivi d'une étape de confirmation et d'un accusé de réception sur un support durable
([article 11 bis de la directive 2011/83/UE][art11a], introduit par la [directive (UE) 2023/2673][dir]).

Ce plugin l'implémente pour Sylius 2.2+, gratuit et open source.

## Fonctionnalités

| Exigence (art. 11 bis) | Ce que fait le plugin |
|---|---|
| Fonction libellée *« renoncer au contrat ici »*, disponible en permanence, facilement accessible | Lien dans le pied de page de chaque page de la boutique et bouton sur la page de commande du client. Aucune connexion requise. |
| Le consommateur fournit ou confirme son nom, le contrat et son e-mail | Formulaire de déclaration, prérempli pour les clients connectés (considérant 37) |
| Étape distincte libellée uniquement *« confirmer la rétractation »* | Page de confirmation dédiée avec un seul bouton de confirmation ; rien n'est enregistré avant |
| Accusé de réception sur un support durable avec contenu, date et heure | E-mail envoyé immédiatement avec la déclaration complète et l'horodatage du serveur |
| Une déclaration envoyée dans le délai est réputée dans les temps | Les déclarations ne sont **jamais refusées** ; le respect du délai est calculé et affiché au marchand |

Également inclus :

- **Rétractation partielle** : les clients connectés choisissent les articles de la commande ; les invités les décrivent en texte libre.
- **Back-office** : grille *Ventes → Rétractations* avec filtres, page de détail, workflow *accepter* / *refuser*
  (Symfony Workflow), échéance et indicateur de respect du délai, et preuve de l'envoi de
  l'accusé de réception.
- **Rapprochement des commandes** sans énumération des commandes : la déclaration d'un invité n'est liée à la commande
  que si le numéro **et** l'e-mail du client correspondent, et la réponse est la même dans les deux cas.
- E-mail de **notification au marchand** à l'adresse de contact du canal.
- **Formulations officielles** en anglais, français, allemand, espagnol, italien, néerlandais et polonais, reprises de
  le Journal officiel et vérifiées par des tests.
- **Personnalisable** via Twig Hooks ; prise en charge de MySQL, MariaDB et PostgreSQL.

## Captures d'écran

| Étape de confirmation | Accusé de réception |
|---|---|
| ![Étape de confirmation](docs/images/shop-confirm.png) | ![E-mail d'accusé de réception](docs/images/email-acknowledgement.png) |

![Grille de l'administration](docs/images/admin-grid.png)

![Détail dans l'administration](docs/images/admin-show.png)

## Prérequis

| | Version |
|---|---|
| PHP | 8.2+ |
| Sylius | 2.2, 2.3 |
| Symfony | 6.4, 7.4, 8.0 |

## Installation

1. Installez le paquet :

    ```bash
    composer require tmonier/sylius-withdrawal-plugin
    ```

2. Enregistrez le bundle dans `config/bundles.php` (Symfony Flex le fait automatiquement dès qu'une recette existe) :

    ```php
    Tmonier\SyliusWithdrawalPlugin\TmonierSyliusWithdrawalPlugin::class => ['all' => true],
    ```

3. Importez la configuration dans `config/packages/tmonier_sylius_withdrawal.yaml` :

    ```yaml
    imports:
        - { resource: "@TmonierSyliusWithdrawalPlugin/config/config.yaml" }
    ```

4. Importez les routes dans `config/routes/tmonier_sylius_withdrawal.yaml` :

    ```yaml
    tmonier_sylius_withdrawal:
        resource: "@TmonierSyliusWithdrawalPlugin/config/routes.yaml"
    ```

5. Exécutez les migrations :

    ```bash
    bin/console doctrine:migrations:migrate
    ```

6. Vérifiez que chaque canal a un **e-mail de contact** si vous souhaitez recevoir les notifications du marchand.

## Configuration

```yaml
# config/packages/tmonier_sylius_withdrawal.yaml
tmonier_sylius_withdrawal:
    period_days: 14        # used to flag late statements; never used to refuse one
    notify_merchant: true  # copy of each statement to the channel contact e-mail
    rate_limit:            # symfony/rate-limiter, on the "confirm withdrawal" step only
        enabled: true
        per_ip: 10         # confirmed statements per IP (IPv6 grouped by /56)
        per_email: 3       # confirmed statements per e-mail (case and "+tag" ignored)
        interval: '1 hour'
        cache_pool: cache.rate_limiter
```

Chaque déclaration confirmée enregistre une demande et envoie des e-mails à l'adresse saisie dans le formulaire ;
l'étape de confirmation est donc soumise à une limitation de débit (fenêtre glissante, avec un verrou pour que des requêtes
parallèles ne puissent pas passer ensemble). Les limites sont volontairement élevées : la fonction de rétractation est
une obligation légale, et un consommateur qui les atteint conserve sa déclaration et peut la confirmer plus tard
(HTTP 429 avec un message flash). L'étape du formulaire elle-même n'est jamais limitée. Définissez `enabled: false`
si votre infrastructure limite déjà le débit de ces routes.

Le délai de rétractation court à partir de la livraison des biens (art. 9). Sylius ne suit pas
la livraison ; le plugin utilise donc la date de la dernière expédition, à défaut la date de finalisation du
tunnel de commande. L'échéance est donnée à titre informatif.

## Personnalisation

Chaque page est construite avec [Twig Hooks](https://stack.sylius.com/twig-hooks/getting-started) :

| Hook | Contenu |
|---|---|
| `sylius_shop.base.footer.content.menu.help_links` → `tmonier_withdrawal` | Lien du pied de page |
| `sylius_shop.account.order.show.content.main.header.buttons` → `tmonier_withdrawal` | Bouton sur la page de commande du compte |
| `tmonier_withdrawal.shop.withdrawal.form.content` | Page du formulaire de déclaration |
| `tmonier_withdrawal.shop.withdrawal.confirm.content` | Page de confirmation |
| `tmonier_withdrawal.shop.withdrawal.done.content` | Page d'accusé de réception |
| `tmonier_withdrawal.admin.withdrawal_request.show.content.sections#left/#right` | Page de détail de l'administration |

Les templates d'e-mail peuvent être surchargés dans `templates/bundles/TmonierSyliusWithdrawalPlugin/email/`.

> [!IMPORTANT]
> Si vous déplacez ou restylez le lien du pied de page, gardez-le **bien visible et accessible depuis chaque page**,
> et gardez le bouton de confirmation libellé **uniquement** avec la formulation officielle.

## Ce qu'il vous reste à faire

Le plugin fournit la fonction technique. En tant que marchand, vous restez responsable de :

- mentionner l'existence et l'emplacement de la fonction de rétractation dans vos informations précontractuelles
  et vos conditions (art. 6, paragraphe 1, point h, tel que modifié) ;
- traiter les remboursements (par exemple avec [`sylius/refund-plugin`](https://github.com/Sylius/RefundPlugin)) ;
- gérer les exceptions de l'art. 16 (biens personnalisés, biens périssables, etc.) lorsque vous examinez une déclaration ;
- vérifier les spécificités de la transposition nationale.

Ce plugin ne constitue pas un conseil juridique.

## Développement

```bash
docker compose up -d mysql mailhog   # or your own MySQL
composer install
(cd vendor/sylius/test-application && yarn install && yarn build)
vendor/bin/console doctrine:database:create
vendor/bin/console doctrine:migrations:migrate -n
vendor/bin/console sylius:fixtures:load -n

vendor/bin/phpunit --testsuite unit
APP_ENV=test vendor/bin/phpunit --testsuite non-unit   # needs a test database with fixtures
vendor/bin/phpstan analyse
vendor/bin/ecs check
```

<!-- vitrine:demo-b2b:start -->
## Démo B2B

Ce plugin est utilisé dans la [démo B2B Sylius](https://github.com/thibmonier/sylius-b2b-demo) : une boutique Sylius 2.3 avec des données d'exemple fictives, où les plugins B2B fonctionnent ensemble. Le code source, l'installation et les fixtures de la démo sont dans ce dépôt.

La démo est un exemple, pas une configuration de production.
<!-- vitrine:demo-b2b:end -->

## Licence

MIT. Voir [LICENSE](LICENSE).

[art11a]: https://eur-lex.europa.eu/eli/dir/2011/83/oj
[dir]: https://eur-lex.europa.eu/eli/dir/2023/2673/oj
