# laravel-expi

> Zero-config Artisan tool that introspects your Laravel app and emits a single **`laravel.json`** manifest — models, schema, relationships, validation and routes — the way OpenAPI describes an HTTP surface.

`laravel-expi` reflects over your **running** application, so it never parses source code or guesses. Everything comes from the live framework: the database schema, the model instances, the validator and the route table. The result is one machine-readable document you can feed into:

- **API / OpenAPI tooling** — a single source of truth for endpoints + validation contracts.
- **AI agents** — a tool/entity catalog so an agent knows your models, fields and relations.
- **Code generators** — scaffold TypeScript clients, resources or SDKs from one file.

## Requirements

- PHP **8.3+**
- Laravel **11**, **12** or **13**

> Schema introspection uses `Schema::getColumns()`, which is Laravel 11+.

## Installation

```bash
composer require boralp/laravel-expi --dev
```

That's it. The service provider is auto-discovered — **no config to publish, nothing to register**. The command is only wired up while Artisan is running, and the package registers nothing on the HTTP side.

## Usage

```bash
# Write docs/laravel.json (default)
php artisan expi:json

# Write somewhere specific
php artisan expi:json --output=public/schema/laravel.json

# Print to the terminal instead of writing a file
php artisan expi:json --stdout

# Only some sections (models | requests | routes)
php artisan expi:json --only=models,routes
php artisan expi:json --except=routes

# Point at non-default directories
php artisan expi:json \
    --models-path=app/Models \
    --requests-path=app/Http/Requests \
    --observers-path=app/Observers

# Compact output
php artisan expi:json --minify
```

### Options

| Option | Default | Description |
| --- | --- | --- |
| `-o, --output` | `docs/laravel.json` | Destination file. Relative paths resolve from the app base. |
| `--stdout` | – | Print to the terminal instead of writing a file. |
| `--only` | – | Comma-separated sections to include. |
| `--except` | – | Comma-separated sections to exclude. |
| `--models-path` | `app/Models` | Directory scanned for Eloquent models. |
| `--requests-path` | `app/Http/Requests` | Directory scanned for Form Requests. |
| `--observers-path` | `app/Observers` | Directory scanned for Observers. |
| `--minify` | – | Compact JSON instead of pretty-printed. |

## What's in the manifest

```jsonc
{
  "laravel": "13.0.0",
  "php": "8.4.0",
  "generatedAt": "2026-06-21T15:16:00+00:00",
  "app": { "name": "Acme" },

  "models": {
    "User": {
      "class": "App\\Models\\User",
      "table": "users",
      "primaryKey": "id",
      "routeKey": "id",
      "incrementing": true,
      "timestamps": true,
      "attributes": {
        "id":    { "type": "bigint",  "nullable": false, "default": null, "auto": true },
        "email": { "type": "varchar", "nullable": false, "default": null, "auto": false }
      },
      "fillable":   ["name", "email", "password"],
      "guarded":    ["*"],
      "hidden":     ["password"],
      "appends":    ["full_name"],
      "casts":      { "email_verified_at": "datetime" },
      "accessors":  { "full_name": { "style": "attribute", "get": true, "set": false } },
      "relationships": {
        "posts": { "type": "HasMany", "related": "App\\Models\\Post" }
      },
      "traits":     ["Illuminate\\Database\\Eloquent\\SoftDeletes"],
      "interfaces": ["Illuminate\\Contracts\\Auth\\Authenticatable"],
      "observers":  ["App\\Observers\\UserObserver"]
    }
  },

  "requests": {
    "App\\Http\\Requests\\StoreUserRequest": {
      "rules": {
        "name":  ["required", "string", "max:255"],
        "email": ["required", "email", "unique:users,email,NULL,id"]
      },
      "messages": { "email.unique": "That email is taken." }
    }
  },

  "routes": [
    {
      "method": ["GET"],
      "uri": "api/v1/users/{user}",
      "name": "users.show",
      "action": "App\\Http\\Controllers\\UserController@show",
      "middleware": ["api", "auth:sanctum", "throttle:60,1"],
      "prefix": "/api/v1",
      "domain": "admin.example.com",
      "wheres": { "user": "[0-9]+" },
      "request": "App\\Http\\Requests\\StoreUserRequest"
    }
  ]
}
```

`middleware` is the fully gathered list, so middleware applied to the enclosing
group is included alongside the route's own. `prefix`, `domain` and `wheres`
(parameter constraints from `->where()`/`whereNumber()` etc.) are emitted only
when present.

The `request` key on a route is a reference into the `requests` map, giving each
endpoint a real, per-route validation contract.

## How it works (and its limits)

Everything is read from the live application, which is precise but has a few honest edges:

- **Models** are instantiated and reflected. Relationships are detected by invoking public, no-argument methods that return an Eloquent `Relation`; name relation methods plainly (`owner()`, not `getOwner()`).
- **Accessors/mutators** are detected in both the legacy `getXAttribute`/`setXAttribute` and the modern `method(): Attribute` styles.
- **Observers** are found via the `#[ObservedBy]` attribute and by scanning the observers directory and matching the model type-hint. Observers wired up imperatively (`Model::observe(...)` in a provider, with no model type-hint) won't surface.
- **Form Requests** are instantiated directly (never resolved through the container, which would trigger validation), and their `rules()` are normalised into string tokens. A `rules()` body that branches on request state is read with a bare GET instance and skipped if it throws.
- **Routes** carry their gathered middleware (group middleware included), group `prefix`, `domain` and parameter `wheres`, and link to a Form Request by reflecting the controller action's type-hints.

## Security

`laravel-expi` is a development/build-time tool. It makes **no network calls**,
registers **no routes or middleware**, and the command is only available while
Artisan runs. The default output path is `docs/` (not a web-served directory) —
if you point `--output` at a public path, treat the manifest like any other
artifact you choose to expose. Install it as a `--dev` dependency.

## Testing

```bash
composer install
composer test      # PHPUnit suite
composer analyse   # Larastan / PHPStan (level 6)
composer check     # both
```

## License

[MIT](LICENSE).
