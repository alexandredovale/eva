# EVA connector modules

## Operating model

An EVA module is an independent connector package installed at `modules/<module-id>/`. It is hierarchically above projects, but it is never owned by a project, document, or user. Zero, one, or many modules may be active, and several modules may observe the same event independently.

The Core knows only generic contracts. It stores each neutral event once in `module_events`, included directly in consolidated `database/schema.sql`, discovers valid manifests, and fans events out to active subscribers. Migration `database/migrations/20260803_010_module_events.sql` remains available for databases created before this consolidation. Each module owns its cursor, history, schema, migrations, and results in `modules/.runtime/data/<module-id>/module.sqlite`. If a legacy deployment has not applied that migration, event emission fails in isolation and the documentary answer continues, but no new module event is stored.

No specialized module table is added to the Core database. The modular release adds only the neutral `module_events` mailbox and does not alter an existing MySQL table.

## Manual installation

1. Audit and extract the package to `modules/<module-id>/`.
2. Confirm that the directory name exactly matches the `id` in `module.json`.
3. Open **Modules** as superadmin.
4. Activate the discovered package.

Only the superadmin manages this lifecycle. Deactivation preserves package files and runtime data. Definitive deletion requires typed confirmation and removes both the package directory and `modules/.runtime/data/<module-id>/`, including all module history.

To update a connector, deactivate it, back up its SQLite database, replace only `modules/<module-id>/`, and reactivate it. Runtime data must never be copied into the package.

## Manifest contract v1

```json
{
  "id": "com.vendor.connector",
  "name": "Connector name",
  "vendor": "Vendor",
  "version": "1.0.0",
  "eva_contract": "1",
  "entrypoint": "bootstrap.php",
  "subscribed_events": ["interaction.completed"],
  "capabilities": [],
  "dashboard": {
    "enabled": true,
    "entrypoint": "dashboard.php",
    "order": 100
  },
  "storage": {
    "driver": "sqlite",
    "schema_version": 1
  }
}
```

The Runtime rejects unknown fields, invalid identifiers or versions, directory and manifest ID mismatches, absolute paths, `..`, missing entrypoints, and unsupported EVA contracts. Canonical schemas are stored in `modules/runtime/contracts/`.

## PHP SDK and capabilities

The package entrypoint returns `ModuleInterface`:

```php
interface ModuleInterface
{
    public function id(): string;
    public function install(ModuleContext $context): void;
    public function handle(ModuleEvent $event, ModuleContext $context): void;
}
```

`ModuleContext` exposes only:

- the validated immutable manifest;
- a private PDO SQLite connection;
- a capability-limited Core read API;
- a provider-neutral JSON language interface;
- an actor-bound scoped documentary query interface when the package declares `core.query.scoped`.

AI keys, database credentials, bearer tokens, and raw Core PDO access are not provided to modules. Packages must be idempotent by `event_id` and cannot call one another.

## Events and deterministic processing

The first official event is `interaction.completed`. It contains actor identity, occurrence time, contextual project and document snapshots, the current question, the conversational input used by the Core, the validated answer, public evidence references, and limitations.

Sensitive keys such as `password`, `secret`, `token`, `api_key`, and `authorization` are rejected recursively. Payload size is limited to 1 MB.

After a successful documentary answer, the Core emits the event and immediately runs one generic dispatcher pass. The active module processes its event and advances its cursor in the same SQLite transaction. A module failure is isolated: it cannot modify the answer, roll back the Core query, or prevent another subscriber from consuming the event.

CLI consumption remains available for recovery or modules without dashboards:

```bash
php modules/runtime/bin/consume.php --limit=50
php modules/runtime/bin/consume.php --limit=50 --drain
```

## Generic dashboards and white-label isolation

Dashboard modules implement `DashboardModuleInterface` and return the contract `eva.module.dashboard/1` with HTML and CSS. The Core frontend discovers active descriptors through `GET /api/modules`, uses the canonical manifest `name` for navigation, and renders all module output in one generic host.

The package owns its styles and markup. CSP authorizes returned CSS with a request nonce; `unsafe-inline` remains disabled. Generic declarative attributes provide refresh, remote filters, local content filters, entries, and accordion toggles without module-specific JavaScript in the Core.

Removing or deactivating a package removes its descriptor and menu entry automatically. The Core contains no module ID, domain label, renderer, or stylesheet.

## Interactive actions and module access

Interactive packages may additionally implement `ModuleAccessInterface` and `ModuleActionInterface`. Access is evaluated before discovery, dashboard rendering, and action execution. Legacy modules remain available by default, while the superadmin retains administrative access.

Declarative forms use `data-module-action-form` and `data-module-action`. The neutral host serializes their fields and sends an authenticated request to:

```text
POST /api/modules/<module-id>/actions/<action-id>
```

The request contains a bounded `input` object and a `request_id` that packages can use for SQLite idempotency. A valid `eva.module.action/1` response returns a fresh `eva.module.dashboard/1` payload and may include a neutral notice. Packages still cannot provide executable JavaScript or expose direct HTTP files.

The optional `core.query.scoped` capability lets an authorized action reuse Core retrieval and answer validation. The Runtime binds the authenticated actor, Core scope authorization runs again, and supplementary module instructions remain subordinate to documentary evidence, citations, limitations, and the base answer contract.

Module-specific profiles, preferences, authorization records, and action history remain in the package SQLite database. The connector adds no MySQL table and contains no domain-specific identifier, label, renderer, or action name.

## Backup and retention

Create a verified module backup before replacing or deleting a package:

```bash
php modules/runtime/bin/backup.php --module=com.vendor.connector
```

The neutral mailbox can be pruned only through explicit confirmation:

```bash
php modules/runtime/bin/prune.php --days=90 --confirm
```

The retention period must exceed the longest acceptable module outage. Definitive history belongs to each module SQLite database, not to the Core mailbox.

## Education reference connector

`com.eva.education` demonstrates the full contract. It observes completed documentary interactions, processes them immediately, and produces descriptive pedagogical observations without scores, weights, percentages, confidence, mastery levels, or rankings.

Its active governance uses only three dimensions:

- conceptual articulation;
- evidence use;
- contextual connection.

The connector extracts linguistic units and concepts from the inseparable question-and-answer object, validates exact source spans and evidence IDs, localizes human-facing output to the question language, and renders a searchable accordion timeline. Its schema version 2 removes the retired redundant “Question Refinement” observation from existing histories while preserving interactions and every other analysis.

## Distribution and marketplace readiness

Institutions, contracted developers, and communities may distribute connectors independently or offer them through a future marketplace. A distributable package may contain source code, manifest, assets, internal migrations, and package documentation. It must never contain:

- `.env`, API keys, tokens, or credentials;
- `module.sqlite`, WAL files, logs, or institutional history;
- real user, project, document, or event snapshots;
- a dependency on one institution's project IDs;
- manual patches to Core HTML, CSS, JavaScript, or domain code.

This boundary is what allows EVA to install future connectors that the Core does not know in advance.

## HTTP protection

The `.htaccess` files in `modules/` and `modules/.runtime/` deny direct web access, directory listing, and PHP execution in the data area. Manifests, internal PHP, activation state, SQLite databases, WAL files, and backups must return HTTP 403 under Apache.
