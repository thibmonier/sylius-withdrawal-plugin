# Audit qualité — sylius-withdrawal-plugin

> Généré le 2026-10-14 · Branche `chore/quality-tooling` · Outillage claude-craft (ECS, PHPStan, Rector, PHPUnit) exécuté via Docker `ghcr.io/sylius/sylius-php:8.4-alpine`. Revue qualitative par l'orchestrateur (lecture directe des fichiers sources).

## 1. Audit factuel (outils)

| Contrôle | Commande | Résultat |
|---|---|---|
| Style (ECS) | `vendor/bin/ecs check --no-progress-bar` | ✅ 0 erreur |
| Analyse statique (PHPStan **level max**) | `vendor/bin/phpstan analyse --no-progress` | ✅ 0 erreur |
| Tests unitaires | `vendor/bin/phpunit --testsuite=unit --no-coverage` | ✅ 30 tests, 76 assertions |
| Validation composer | `composer validate --strict` | ✅ valide |
| Modernisation (Rector, dry-run) | `vendor/bin/rector process --dry-run` | ⚠️ 9 fichiers avec suggestions |
| Vulnérabilités dépendances | `composer audit` | ⚠️ 2 packages abandonnés (pas de CVE) |

**Rector** vient d'être ajouté (`rector/rector ^2.0`, `rector.php`, cibles `make rector` / `make rector-fix`). Les 9 fichiers signalés relèvent de deux règles : `FlipTypeControlToUseExclusiveTypeRector` et `ReadOnlyClassRector` — suggestions de modernisation non bloquantes. Lancer `make rector-fix` pour les appliquer, puis relancer ECS/PHPStan/tests.

**Packages abandonnés** : `behat/transliterator` et `php-http/message-factory`, hérités transitivement de `sylius/sylius` — aucune action requise de la part du plugin.

**Tests non-unit (fonctionnels)** : non exécutés localement (DB + app Sylius requise) — couverts par le CI.

**Note `ecs.php`** : le fichier était déjà correctement aligné (import depuis `vendor/sylius-labs/coding-standard/ecs.php`, paths limités aux dossiers existants `src`, `tests/Functional`, `tests/Unit`, sans `tests/Integration` vide ni `tests/TestApplication`). Aucune correction nécessaire.

## 2. Audit qualitatif (revue par l'orchestrateur)

| Dimension | Score | Synthèse |
|---|---|---|
| PHP / Clean Archi / SOLID | **8/10** | Typage strict, `final` sur tous les services, SRP respecté, interfaces complètes. |
| Intégration Symfony / Sylius | **8/10** | Workflow Symfony, PSR Clock, AbstractResourceExtension, mapped superclasses, CSRF sur la confirmation. |
| Sécurité (OWASP 2025) | **7/10** | Ownership vérifié deux fois, DQL paramétré, `|e` systématique dans les emails. Entropie de référence et absence de rate-limiting à corriger. |

### Points forts

- `declare(strict_types=1)` + classes `final` sur l'ensemble des services de `src/` ; `WithdrawalRequest` et `WithdrawalRequestRepository` intentionnellement non-`final` (superclasses Doctrine extensibles).
- Flux deux étapes (form → confirm avec token CSRF → done) correctement sécurisé : la déclaration pendante ne vit qu'en session et est relue/validée à chaque étape.
- `WithdrawalStatement::fromArray()` (`src/Model/WithdrawalStatement.php:40-48`) : désérialisation défensive depuis la session — `orderItemIds` filtré à des entiers stricts, chaque champ string validé par type.
- `OrderMatcher.match()` (`src/Order/OrderMatcher.php:19-46`) : double critère ownership (ID client OU email canonique) + exclusion des paniers, puis contrôle reconduit dans `WithdrawalRequestSubmitter.submit()` — aucun risque de soumission sur une commande tierce.
- `RandomReferenceGenerator.generate()` utilise `random_bytes()` — génération cryptographiquement sûre.
- Templates email (`templates/email/_statement.html.twig`) : tous les champs consommateur échappés explicitement avec `|e` conformément à la recommandation du CLAUDE.md (autoescaping désactivé dans le layout Sylius).
- `WithdrawalRequestSubmitter.sendEmails()` : la déclaration est persistée avant l'envoi ; les erreurs d'envoi sont loguées sans être exposées ; l'acquittement manquant est visible en admin.

## 3. Recommandations priorisées

### 🟠 Majeur

1. **Entropie de référence insuffisante** (`src/Generator/RandomReferenceGenerator.php:11`, OWASP A02:2025).  
   `W` + 5 octets hex = 40 bits d'entropie. La référence est exposée dans l'URL de la page `done` et utilisée pour retrouver la demande (`findOneByReference`). Un attaquant ayant connaissance du format pourrait énumérer des références et accéder à des pages de confirmation affichant nom, e-mail et numéro de commande de tiers.  
   → Passer à 10 octets (`random_bytes(10)`) = 80 bits, ou utiliser un UUID v4.

2. **Absence de rate limiting sur le formulaire de rétractation** (`src/Controller/Shop/WithdrawalController.php`, OWASP A04:2025).  
   Le formulaire est accessible sans authentification et sans limite de débit. Un acteur malveillant peut soumettre un volume arbitraire de fausses déclarations (spam admin, saturation de la boîte marchande) ou tenter d'énumérer des numéros de commande valides via la réponse du formulaire.  
   → Ajouter un rate-limiter Symfony (`RateLimiterFactory`) sur `formAction()` basé sur l'IP, ou un CAPTCHA côté front.

3. **Déclaration toujours acceptée même si l'ordre est expiré** (`src/Submitter/WithdrawalRequestSubmitter.php:69-74`, OWASP A01:2025).  
   `submit()` ne vérifie pas `$withdrawalRequest->isSubmittedInTime()` : une déclaration hors délai légal est enregistrée sans avertissement. Le champ `deadlineAt` est calculé mais jamais utilisé comme garde.  
   → Documenter explicitement le choix (le plugin enregistre sans jamais refuser — conforme à la CRD qui n'interdit pas la réception) et ajouter un flag `isLate` en admin pour alerter le marchand.

### 🟡 Mineur

- **Paramètre `order` non vérifié dans `confirmAction`** (`src/Controller/Shop/WithdrawalController.php:84`). Le `?order=` en query string est utilisé uniquement pour le rendu (labels articles) mais pourrait induire en erreur si différent du `orderNumber` stocké en session. → Ignorer ce paramètre si la commande ne correspond pas au statement de session, ou le supprimer de la query string.
- **`WithdrawalRequestSubmitter` : 9 paramètres constructeur** (`src/Submitter/WithdrawalRequestSubmitter.php:29-41`). Envisager d'extraire un `WithdrawalEmailDispatcher` regroupant `emailManager + logger + notifyMerchant`.
- **`WithdrawalRequest` non-`final` sans annotation** (`src/Entity/WithdrawalRequest.php:13`). Le choix de superclasse est intentionnel mais non documenté. → Ajouter un commentaire de classe indiquant que l'extension via la config `resources` est le mécanisme prévu.
- Appliquer les 9 suggestions Rector (`make rector-fix`) — principalement `ReadOnlyClassRector` sur les value objects et `FlipTypeControlToUseExclusiveTypeRector`.

### ℹ️ Pour information (choix de conception assumés)

- Le plugin n'émet **jamais de refus** sur le délai légal : la CRD impose d'enregistrer la déclaration reçue ; c'est le marchand qui statue sur la recevabilité.
- Le `orderNumber` fourni par le consommateur n'est pas une clé d'accès : si la commande ne correspond pas au client, elle n'est simplement pas liée à la demande (la demande est créée sans `order`).
- Le formulaire est accessible aux **invités** (email guest checkout) : le contrôle d'ownership via email canonique est le seul facteur, ce qui est légalement correct.

## 4. Verdict

Plugin **propre et fonctionnellement solide** : outillage au vert (ECS, PHPStan level max, 30 tests unitaires), typage strict généralisé, flux de rétractation bien structuré (deux étapes, CSRF, session défensive), ownership vérifié en double. Les axes d'amélioration prioritaires sont l'**entropie de la référence** (risque énumération/information disclosure) et l'**absence de rate-limiting** sur le formulaire public — à traiter avant exposition en production. Le reste (Rector, paramètre `?order=` au confirm, constructeur lourd) relève de l'hygiène et peut être traité en parallèle.
