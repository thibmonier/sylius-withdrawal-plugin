# Functional specification — EU withdrawal function

Implements the "withdrawal function" required by **Article 11a of Directive 2011/83/EU**
(Consumer Rights Directive), introduced by **Directive (EU) 2023/2673** and applicable
since **19 June 2026**.

Legal source: https://eur-lex.europa.eu/eli/dir/2023/2673/oj

> This plugin is a technical tool. It does not constitute legal advice; merchants remain
> responsible for their own compliance (pre-contractual information, terms and conditions,
> refund handling, national specificities).

## 1. Legal requirements → plugin features

| Art. 11a | Requirement | Plugin |
|---|---|---|
| (1) | Function labelled "withdraw from contract here" (or unambiguous equivalent), continuously available during the withdrawal period, prominently displayed and easily accessible | Footer link on every shop page + button on the customer's order page. Label uses the official wording of each language. No login required. |
| (2)(a) | Consumer provides or confirms their name | `customerName` field, prefilled for logged-in customers |
| (2)(b) | Details identifying the contract | `orderNumber` field (+ selection of order items for logged-in customers, free text otherwise) |
| (2)(c) | Electronic means for the confirmation | `email` field, prefilled for logged-in customers |
| (3) | Separate confirmation step labelled **only** "confirm withdrawal" | Second step showing a summary with a single "confirm withdrawal" button |
| (4) | Acknowledgement of receipt on a durable medium, with content, date and time of submission, without undue delay | Email sent immediately after confirmation, containing the full statement and the server timestamp |
| (5) | Statement sent before the end of the period is timely | Submission timestamp stored server side; submissions are **never blocked**, the merchant evaluates timeliness |

Recital 37: a logged-in consumer must not have to identify themselves again → prefill.
Partial withdrawal is optional under EU law; German § 356a BGB mentions "the contract or a
part of it" → supported (item selection / free text).

## 2. Flows

### 2.1 Guest (decided: direct submission, no one-time link)

1. Footer link "withdraw from contract here" → `/{_locale}/withdrawal`.
2. Form: name, email, order number, concerned items (optional free text).
3. Summary page with a single **"confirm withdrawal"** button. Pending data is kept in the
   session between the two steps.
4. On confirmation:
   - the request is persisted with `submittedAt` (server time, UTC);
   - the plugin tries to match an order of the current channel by number **and** customer
     email (case-insensitive). The response is identical whether or not it matches, to
     avoid order enumeration;
   - the acknowledgement email is sent to the address given;
   - a notification email is sent to the channel contact email (if configured).
5. Thank-you page restating the timestamp and the content.

### 2.2 Logged-in customer

- Button "withdraw from contract here" on the account order page, for fulfilled/placed
  orders of the customer.
- Same two-step flow, with name/email/order prefilled and a checkbox list of order items
  (none checked = whole contract).

## 3. Back office

- Menu entry *Sales → Withdrawals*.
- Grid: number, date, customer name, email, order (link when matched), timeliness
  (within period / late / unknown), state. Filters: state, channel, date.
- Show page: full statement, items, timestamps, linked order.
- Workflow `tmonier_withdrawal_request` (Symfony Workflow, via Sylius state machine
  abstraction): `received → accepted`, `received → rejected`.
- Refunds are out of scope (use `sylius/refund-plugin` or manual refunds).

## 4. Timeliness (informational only)

`deadline = reference date + period` where the reference date is the last shipment's
`shippedAt`, falling back to the order's `checkoutCompletedAt`. Period defaults to
14 days (configurable). Computed when the request is matched with an order. Shown in admin,
never used to block a submission.

## 5. Configuration (bundle config)

```yaml
tmonier_sylius_withdrawal:
    period_days: 14          # withdrawal period used for the timeliness indicator
    notify_merchant: true    # send a copy to the channel contact email
```

## 6. Out of scope for v1

- Per-variant exclusions (Art. 16 exceptions), refunds, return labels, PDF receipt, API
  Platform endpoints. Candidates for v1.x.

## 7. Official labels

| Lang | Withdrawal function | Confirmation |
|---|---|---|
| en | withdraw from contract here | confirm withdrawal |
| fr | renoncer au contrat ici | confirmer la rétractation |
| de | Vertrag widerrufen | Widerruf bestätigen |
| es | desistir del contrato aquí | confirmar desistimiento |
| it | recedere dal contratto qui | conferma recesso |
| nl | hier de overeenkomst herroepen | herroeping bevestigen |
| pl | odstąp od umowy tutaj | potwierdź odstąpienie od umowy |
