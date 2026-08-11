# EVA connector modules

## Operating model

An EVA module is an independent connector package installed at `modules/<module-id>/`. It is hierarchically above projects, but it is never owned by a project, document, or user. Zero, one, or many modules may be active, and several modules may observe the same event independently.

The Core knows only generic contracts. It stores each neutral event once in `module_events`, included directly in consolidated `database/schema.sql`, discovers valid manifests, and fans events out to active subscribers. Migration `database/migrations/20260803_010_module_events.sql` remains available for databases created before this consolidation. Each module owns its cursor, history, schema, migrations, and results in `modules/.runtime/data/<module-id>/module.sqlite`. If a legacy deployment has not applied that migration, event emission fails in isolation and the documentary answer continues, but no new module event is stored.

No specialized module table is added to the Core database. The modular release adds only the neutral `module_events` mailbox and does not alter an existing MySQL table.

## Manual installation

1. Audit and extract the package to `modules/<module-id>/`.
2. Confirm that the directory name exactly matches the `id` in `module.json`.
3. Open **Modules** as superadmin. An invalid manifest is shown as invalid and cannot be activated.
4. Activate the discovered package. Opening its dashboard incorporates pending events before presenting data.

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

The Runtime rejects unknown fields, invalid identifiers or versions, directory and manifest ID mismatches, absolute paths, `..`, missing entrypoints, and unsupported EVA contracts. The canonical schemas are:

- `modules/runtime/contracts/module-manifest.schema.json`;
- `modules/runtime/contracts/module-event.schema.json`;
- `modules/runtime/contracts/module-dashboard.schema.json`;
- `modules/runtime/contracts/module-action.schema.json`.

## PHP SDK and capabilities

The package entrypoint returns `Eva\ModuleRuntime\ModuleInterface`:

```php
interface ModuleInterface
{
    public function id(): string;
    public function install(ModuleContext $context): void;
    public function handle(ModuleEvent $event, ModuleContext $context): void;
}
```

`ModuleContext` exposes only:

- `manifest`: the validated immutable manifest;
- `storage`: a private PDO SQLite connection;
- `core`: a capability-limited Core read API; in interactive contexts, `actorScopes()` returns only projects and works authorized for the authenticated actor;
- `language`: a provider-neutral `LanguageModelInterface` for JSON generation;
- `query`: an actor-bound scoped documentary query interface, available only in interactive contexts when the package declares `core.query.scoped`.

Optional interfaces are:

- `DashboardModuleInterface`, which returns `contract`, `html`, and `css` through the generic dashboard endpoint;
- `ModuleAccessInterface`, which decides whether an actor can discover, open, and execute the module while preserving superadmin administrative access;
- `ModuleActionInterface`, which receives authenticated actions and returns `eva.module.action/1` with an updated dashboard.

AI keys, database credentials, bearer tokens, and raw Core PDO access are not provided to modules. Packages must be idempotent by `event_id` and cannot call one another.

## Events and deterministic processing

The first official event is `interaction.completed`. It contains actor identity, occurrence time, contextual project and document snapshots, the current question, the conversational input used by the Core, the validated answer, public evidence references, and limitations.

```json
{
  "event_id": "EVA-EVT-0123456789ABCDEF01234567",
  "event_type": "interaction.completed",
  "contract_version": 1,
  "occurred_at": "2026-08-03T15:30:00-03:00",
  "actor": {"user_id": 18, "role": "user"},
  "scope": {
    "projects": [{"id": 3, "name": "Project A"}],
    "documents": [{"id": 12, "public_id": "EVA-D000012", "title": "Document A"}]
  },
  "interaction": {
    "current_input": "Current question",
    "contextual_input": "Current question and conversational context used by the Core",
    "answer": "Final answer"
  },
  "evidences": [{"id": "EVA-E000120", "document": "Document A"}],
  "limitations": []
}
```

Sensitive keys such as `password`, `secret`, `token`, `api_key`, and `authorization` are rejected recursively. Payload size is limited to 1 MB.

After a successful documentary answer, the Core emits the event and immediately runs one generic dispatcher pass. The active module processes its event and advances its cursor in the same SQLite transaction. A module failure is isolated: it cannot modify the answer, roll back the Core query, or prevent another subscriber from consuming the event.

CLI consumption remains available for recovery or modules without dashboards:

```bash
php modules/runtime/bin/consume.php --limit=50
php modules/runtime/bin/consume.php --limit=50 --drain
```

Periodic scheduling is optional for modules with dashboards: opening or refreshing the dashboard executes a recovery pass before reading its data. The CLI remains available to process events in advance and for modules without a dashboard. For every existing mailbox item, the module confirms processing, the idempotent event record, and cursor advancement together in its private SQLite transaction.

## Generic dashboards and white-label isolation

Dashboard modules implement `DashboardModuleInterface` and return the contract `eva.module.dashboard/1` with HTML and CSS. When a module declares `dashboard.enabled`, the Core frontend discovers its active descriptor through `GET /api/modules`, uses the canonical manifest `name` for navigation, and renders all module output in one generic host.

The package owns its styles and markup and may keep its stylesheet under `assets/`. CSP authorizes returned CSS with a request nonce; `unsafe-inline` remains disabled. Generic declarative attributes provide refresh, remote filters, local content filters, entries, and accordion toggles without module-specific JavaScript in the Core.

The navigation label is always exactly `module.name`; there is no `dashboard.label` or Core-owned alias. A manifest with `"name": "Education"` therefore produces the **Education** menu automatically. Navigation does not display numeric indexes before module names. Its position follows the fixed Core layout and, among modules, `dashboard.order`.

The recognized declarative attributes are:

- `data-module-filter` for a remote filter sent back to the dashboard;
- `data-module-refresh` for interface refresh;
- `data-module-content-filter` and `data-module-entry` for local filtering;
- `data-module-accordion-toggle` for exclusive item expansion;
- `data-module-action-form` and `data-module-action` for authenticated submission to a module-owned action.

Removing or deactivating a package removes its descriptor and menu entry automatically. The Core contains no module ID, domain label, renderer, or stylesheet.

## Interactive actions and module access

Interactive packages may additionally implement `ModuleAccessInterface` and `ModuleActionInterface`. Access is evaluated before discovery, dashboard rendering, and action execution. Legacy modules remain available by default, while the superadmin retains administrative access.

Declarative forms use `data-module-action-form` and `data-module-action`. The neutral host serializes their fields and sends an authenticated request to:

```text
POST /api/modules/<module-id>/actions/<action-id>
```

The request contains a bounded `input` object and a `request_id` that packages can use for SQLite idempotency. A valid `eva.module.action/1` response returns a fresh `eva.module.dashboard/1` payload and may include a neutral notice. Packages still cannot provide executable JavaScript or expose direct HTTP files.

Sensitive fields are rejected recursively. The input payload is limited to 128 KiB, and returned HTML and CSS have their own bounded limits.

The optional `core.query.scoped` capability lets an authorized action call `ModuleContext::scopedQuery()` to reuse Core retrieval and answer validation. The Runtime binds the authenticated actor, `ScopeAccessService` runs Core scope authorization again, and supplementary module instructions remain subordinate to documentary evidence, citations, limitations, and the base answer contract.

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

Deactivation never deletes data. Definitive deletion requires typing the exact module ID in the system modal and removes:

- `modules/<module-id>/`, including the executable package;
- `modules/.runtime/data/<module-id>/`, including `module.sqlite`, WAL files, local backups, and history;
- the activation record in `modules/.runtime/state.json`.

Deletion is irreversible and does not create a backup automatically. Run `backup.php` first whenever institutional archiving is required.

## EXPLORER reference connector

`com.eva.explorer` is the public reference cartridge for EVA Module Contract v1. It does not subscribe to Core events: its flow is interactive and starts from explicit dashboard actions. Its manifest declares its own dashboard, schema 2 SQLite storage, and only the generic capabilities required for authorized reads, actor-scoped documentary queries, and language generation.

The module has three internal profiles:

- **Teacher** creates Learning Themes and defines the path guidance;
- **Student** progresses through Quiz, Nodes, and Exam;
- **Institutional staff** follows themes, assignments, and learning progress.

Activities reuse Core RAG through `core.query.scoped`. The Runtime binds the authenticated actor, while projects and documents remain subject to Core authorization. Used evidence preserves its complete excerpt and reference. The module never broadens scope or changes κq, CIE, lineage, κe, or any Core calculation.

Quiz and Nodes provide qualitative **correction** or **deepening** support. Exam uses its own answer contract. The path produces no grades, weights, percentages, confidence, mastery levels, or rankings.

Profiles, Learning Themes, assignments, idempotent actions, results, and progress belong exclusively to the EXPLORER private SQLite database. Installation creates no domain table or structure in the Core database. HTML and CSS also remain inside the package; the frontend discovers only the generic contract and the manifest name `EXPLORER`.

The public reference test validates the manifest, capabilities, database isolation, authorization for all three profiles, actions, scoped queries, answer contracts, evidence preservation, and interface:

```bash
php tests/ExplorerModuleTest.php
```

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
