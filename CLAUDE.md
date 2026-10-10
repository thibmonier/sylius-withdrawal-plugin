# CLAUDE.md

Sylius 2 plugin implementing the EU withdrawal function (Art. 11a CRD). Functional spec and
legal mapping: `SPEC.md`. Public docs: `README.md`.

## Commands

PHP needs more than 128 MB to compile the test application container: prefix console and
PHPStan calls with `php -d memory_limit=2G` when running locally.

```bash
docker compose up -d mysql mailhog        # MySQL on 3306, MailHog UI on 8025 (SMTP 1025)
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # functional tests, need the test DB + Sylius fixtures
vendor/bin/phpstan analyse                # level max, src + tests
vendor/bin/ecs check
vendor/bin/console lint:container
```

Local env overrides (gitignored): `tests/TestApplication/.env.local` (MAILER_DSN) and
`.env.test.local` (DATABASE_URL without password for the Docker MySQL).

## Architecture

- `src/Entity` — `WithdrawalRequest` / `WithdrawalRequestItem` resources (XML mapping in
  `config/doctrine`, mapped superclasses so applications can extend them).
- `src/Submitter/WithdrawalRequestSubmitter` — records a confirmed statement, matches the order,
  computes the deadline, sends acknowledgement + merchant e-mails. Never refuses a statement.
- `src/Order` — `OrderMatcher` (number + e-mail, or ownership for logged-in customers) and
  `DeadlineCalculator`.
- `src/Controller/Shop/WithdrawalController` — form → confirm (CSRF) → done; the pending
  statement lives in the session between steps.
- Admin uses the generic ResourceController with `config/routes/admin.yaml`,
  `config/grids/withdrawal_request.yaml` and hook prefix `tmonier_withdrawal.admin.withdrawal_request`.
- Workflow `tmonier_withdrawal_request` (`config/app/workflow.yaml`), pinned to Symfony Workflow.

## Rules

- Button wording is legally imposed: `LegalLabelsTest` guards it. Do not change the
  `withdraw_from_contract_here` / `confirm_withdrawal` translations without checking the
  Official Journal text.
- The Sylius e-mail layout disables autoescaping: escape every consumer-provided value with `|e`
  in `templates/email`.
- Hook context values may be missing when null: use `hookable_metadata.context.x|default(null)`.
- Migrations use the Schema API (MySQL + PostgreSQL), and contain plugin tables only.
