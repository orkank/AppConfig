# IDangerous AppConfig (Magento 2)

**App Config** is a Magento 2 module that stores **structured configuration** for mobile apps, PWAs, and **headless front‑ends** (e.g. Next.js): groups, typed key-value rows, and stable **REST** / **GraphQL** read APIs. An optional **headless integration** layer adds **shared-secret HMAC**, **admin delegation → short-lived JWT**, JSON sync, **storefront URL → key** routing via `url_rewrite`, and write APIs for sync and cleanup.

**Version:** see [`composer.json`](composer.json) and the **module changelog** — [`CHANGELOG.md`](./CHANGELOG.md) next to this README (`app/code/IDangerous/AppConfig/`). This is **not** the Magento installation / repository root `CHANGELOG.md`; release notes for **IDangerous_AppConfig** live only in that file.

### Why we built this (real-world scenario)

We run **Magento as the system of record** and **Next.js as the authoring and delivery surface**. This module exists so that **Next.js can create and update storefront “pages” end-to-end**: editors design in Next.js, **save** page JSON and URL mappings through the **headless APIs** (delegation + HMAC writes), and the same app **reads** that data to **render the page for real shoppers** on the matching Magento URL paths (`urlResolver` / `route` → `APPCONFIG_HEADLESS`).

That workflow matters because **editors do not wait on a deploy** to open a new path: they ship a page from Next.js, persist it to Magento, and customers hit the live URL—**fast iteration** for campaigns and landing content without treating Magento as the visual CMS.

---

## Table of contents

- [Features at a glance](#features-at-a-glance)
- [Requirements](#requirements)
- [Installation & upgrades](#installation--upgrades)
- [Admin UI](#admin-ui)
- [REST API (public catalog)](#rest-api-public-catalog)
- [GraphQL (`appConfig`)](#graphql-appconfig)
- [Headless integration (Next.js, etc.)](#headless-integration-nextjs-etc)
- [Programmatic use (PHP)](#programmatic-use-php)
- [Changelog](#changelog)
- [License & author](#license--author)

---

## Features at a glance

| Area | What it does |
|------|----------------|
| **Admin** | Groups, key-value CRUD, rich types (text, JSON with editors, products, categories, CMS pages, files), Import/Export. |
| **Public read API** | Anonymous `GET /V1/appconfig/config` and `GET /V1/appconfig/groups` — catalog data, versions, filters. |
| **Storefront GraphQL** | `appConfig` query — same data as REST with key/group/version filters. |
| **Headless** | HMAC-signed REST/GraphQL: read JSON by prefix, upsert JSON, **register/unregister** storefront paths, **list** all routes, **delete** headless keys; admin **Launch headless** delegation POST. |
| **Routing** | `APPCONFIG_HEADLESS` on `urlResolver` / `route` with `app_config_key` → `key_name`. |

Rows created via the headless API use `origin=headless` and are excluded from the anonymous `appConfig` snapshot where applicable; use the headless read endpoints instead.

---

## Requirements

- Magento Open Source or Adobe Commerce **2.4.x**
- PHP **7.4** or **8.1+** (see `composer.json` for the exact constraint)
- PHP extensions required by Magento + JSON

---

## Installation & upgrades

### Install

```bash
bin/magento module:enable IDangerous_AppConfig
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
```

Composer (if you publish or mirror the package):

```bash
composer require idangerous/appconfig:^2.0
bin/magento setup:upgrade
```

### After upgrading this module

```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Review the module [**CHANGELOG.md**](./CHANGELOG.md) (same directory as this README) for schema or behavior changes.

---

## Admin UI

Under **Stores → App Config**:

| Menu | Purpose |
|------|---------|
| **Configuration** | Opens **Stores → Configuration → App Config** (system settings, including “Allow headless writes”). |
| **Launch headless** | Opens the **delegation POST** handoff (no need to open Headless Integration first). Same flow as the button on **Headless Integration**. |
| **Headless Integration** | Shared secret, app URL, key prefix, group code, delegation TTL/path, **Launch headless** (opens new tab), save credentials. |
| **Groups** / **Key-Value Pairs** | Manage the key-value store. |
| **Import/Export** | Migrate data between environments. |

### Key-value types (short)

- **Text**, **JSON** (key-value / nested array / raw modes, media picker on values).
- **Products** / **Categories** (grids/trees; optional product limit per category; optional **custom attribute codes** appended to each product in API output).
- **CMS pages** (optional full HTML `content` in API).
- **File** uploads.

---

## REST API (public catalog)

Anonymous storefront REST (`/rest/{store_code}/V1/...`):

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/V1/appconfig/config` | Full or filtered config snapshot (`appVersion`, `groupCode` query params). |
| `GET` | `/V1/appconfig/groups` | List of configuration groups. |

Response shape: top-level `DEFAULTS` and `GROUPS` with nested keys; product payloads include prices, stock, main `image`, full `media_gallery`, and any configured custom attributes when present in the catalog.

---

## GraphQL (`appConfig`)

Storefront query:

```graphql
query ($version: String, $keys: [String], $groups: [String]) {
  appConfig(app_version: $version, keys: $keys, groups: $groups) {
    key
    group
    type
    version
    text
    file
    json
    products { id sku name image final_price regular_price currency is_in_stock qty }
    categories { id name }
    cms_pages { id permalink title update_time content }
  }
}
```

- Only the field matching `type` is populated; others are `null`.
- `json` is returned as a **string** — parse on the client.

---

## Headless integration (Next.js, etc.)

The headless layer is documented in **[HEADLESS.md](HEADLESS.md)** (signing, headers, flows). Summary:

| Concern | Notes |
|---------|--------|
| **Reads** | Shared HMAC only — no `X-AppConfig-Session`. |
| **Writes** | HMAC + session JWT from **delegation exchange**; respects **Allow headless writes** in system config. |
| **Launch** | **Stores → App Config → Launch headless** or **Headless Integration** — POSTs one-time `delegation_code` to your app (not in the query string). |

Notable REST paths (under `/V1/appconfig/headless/` on the storefront REST base):

- `session/exchange`, `json` (GET/POST), `url-routes` (GET), `register-url`, `unregister-url`, `delete-json-keys`

GraphQL mutations/queries mirror these (see `etc/schema.graphqls`). A **[Postman](postman.json)** collection is included for signed requests.

---

## Programmatic use (PHP)

Inject **`IDangerous\AppConfig\Api\AppConfigInterface`**:

```php
// Single value
$text = $this->appConfig->getValue('my_key', 'group_code', $appVersion);

// Full structure for a version
$config = $this->appConfig->getConfig($appVersion); // ['DEFAULTS' => ..., 'GROUPS' => ...]
```

---

## Changelog

**IDangerous_AppConfig** maintains its own history in **[`app/code/IDangerous/AppConfig/CHANGELOG.md`](./CHANGELOG.md)** (relative to the Magento root — open the copy that sits **next to this README** in the module folder). Do not use the unrelated root or vendor changelog files.

Recent example: **2.0.0** — headless list/delete/unregister APIs, admin **Launch headless** menu shortcut, documentation refresh.

---

## License

MIT License.

## Author

**Orkan K.**
