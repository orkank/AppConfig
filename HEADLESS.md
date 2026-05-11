### Why we built this (real-world scenario)

We run **Magento as the system of record** and **Next.js as the authoring and delivery surface**. This module exists so that **Next.js can create and update storefront “pages” end-to-end**: editors design in Next.js, **save** page JSON and URL mappings through the **headless APIs** (delegation + HMAC writes), and the same app **reads** that data to **render the page for real shoppers** on the matching Magento URL paths (`urlResolver` / `route` → `APPCONFIG_HEADLESS`).

That workflow matters because **editors do not wait on a deploy** to open a new path: they ship a page from Next.js, persist it to Magento, and customers hit the live URL—**fast iteration** for campaigns and landing content without treating Magento as the visual CMS.

# App Config — Headless integration

Public App Config values still use anonymous **`appConfig {}`** GraphQL and catalog REST. **`origin=headless`** rows are hidden there; this document covers the **headless API** that syncs those rows and maps storefront paths.

Design goals:

1. **Next.js reads** headless JSON **on its own** using only the **shared HMAC secret** (no shopper session and no JWT for reads).
2. **Writes** (JSON upsert, **delete JSON keys**, **`register-url`**, **`unregister-url`**) require an **admin** to establish trust first: Admin **Launch** produces a single-use **`delegationCode`**; Next exchanges it for a short-lived **session JWT**, then sends **JWT + HMAC** for write calls.

Customers do not receive write tokens or a separate OAuth path; there is **no** **`accessToken`** exchange.

For module setup see [README.md](README.md).

## What lives where

| Item | Role |
|------|------|
| **Shared HMAC secret** | Set in **Stores → App Config → Headless Integration**; mirrored in Next (env). Used to compute **`X-AppConfig-Signature`** on every headless REST/GraphQL request. |
| **`delegationCode`** | Minted when an ACL admin uses **Stores → App Config → Launch headless** (or **Headless Integration → Launch headless**). Post it to **`session/exchange`** once; consumed on success. |
| **`session_token`** (`X-AppConfig-Session`) | Returned by **exchange**; identifies the delegating admin. **Required only for writes** (REST POST JSON upsert, **delete-json-keys**, **register-url**, **unregister-url**, GraphQL save/register/delete mutations). **Not used for reads.** |
| **`key_name` / `appconfig_key`** | App Config row identifier (data), not authentication. **`register-url`** must match **`key_name`**. |

> **Breaking (older deployments):** `session/exchange` accepts **`delegationCode` only**. Session JWTs whose payload had non-admin **`utt`** (legacy) **no longer** validate — re-exchange via Launch after upgrade. **`GET …/json`**, **`GET …/url-routes`**, and GraphQL **`appConfigHeadlessJson` / `appConfigHeadlessUrlRoutes`** require **HMAC only** — no **`X-AppConfig-Session`**.

---

## Request signing (all headless REST/GraphQL)

Headers on every call (when configured):

- **`X-AppConfig-Timestamp`** — UNIX seconds
- **`X-AppConfig-Signature`** — `HMAC-SHA256(secret, METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + SHA256(raw_body))`

**`PATH`** is the storefront request **`pathInfo`** (`/graphql` vs `/rest/.../V1/…` differs — sign accordingly).

Then:

| Operation | Also send **`X-AppConfig-Session`** |
|-----------|--------------------------------------|
| **Exchange** delegation → JWT | No |
| **Read** REST `GET …/headless/json`, `GET …/headless/url-routes`, GraphQL **`appConfigHeadlessJson`**, **`appConfigHeadlessUrlRoutes`** | **No** |
| **Write** REST POST JSON, **delete-json-keys**, **register-url**, **unregister-url**, GraphQL save/register/delete | **Yes** (JWT from exchange) |

Empty body (typical GET) ⇒ hash `SHA256("")`.

---

## REST

Base: `{{base_url}}/rest/{{store_view_code}}/V1/…`

**Admin → Next:** **`Launch`** posts **`delegation_code`** (`application/x-www-form-urlencoded`) so the code avoids query-string logs.

| Method | Path | Auth |
|--------|------|------|
| POST | `/appconfig/headless/session/exchange` | Body `{"delegationCode":"..."}` · HMAC · **no** session header |
| GET | `/appconfig/headless/json` | HMAC · **no** session |
| GET | `/appconfig/headless/url-routes` | HMAC · **no** session · optional **`?store_id=`** (store view id; omit for all stores) |
| POST | `/appconfig/headless/json` | HMAC · session JWT · “Allow Headless Writes” |
| POST | `/appconfig/headless/delete-json-keys` | Same (body `{"keys":["prefix.a","prefix.b"]}`) · **only** rows with **`origin=headless`** in the configured group |
| POST | `/appconfig/headless/register-url` | Same as POST JSON |
| POST | `/appconfig/headless/unregister-url` | Same (body `{"route_id":<id>}` or **`store_ids`** to drop selected stores only) |

Exchange response includes **`session_token`**, **`user_id`** (delegating admin), **`user_type`** (admin constant).

### Example bodies

Exchange:

```json
{ "delegationCode": "<one-time>" }
```

Register URL:

```json
{
  "request_path": "promo/summer-2026",
  "appconfig_key": "next_promo_page",
  "store_ids": [1, 2]
}
```

Delete JSON keys (removes **`origin=headless`** rows; key must match configured prefix):

```json
{ "keys": ["next_prefix.page_a", "next_prefix.page_b"] }
```

Unregister URL (remove **`url_rewrite`** / **`headless_route`**):

```json
{ "route_id": 12 }
```

Only certain stores (keep other store rewrites for the same logical route):

```json
{ "route_id": 12, "store_ids": [1] }
```

Omit **`store_ids`** to remove the route everywhere and delete the **`headless_route`** row when no rewrites remain.

---

## GraphQL (storefront)

**`APPCONFIG_HEADLESS`** is on **`UrlRewriteEntityTypeEnum`** for **`urlResolver` / `route`**.

Headless sync:

| Operation | Field | Session header |
|-----------|-------|----------------|
| Exchange | **`mutation`** `appConfigHeadlessExchangeSession(delegationCode: String!)` | No |
| Read JSON | **`query`** `appConfigHeadlessJson(keys: [...]) { items { key json } }` | **No** |
| List URL routes | **`query`** `appConfigHeadlessUrlRoutes(store_id: Int) { items { route_id request_path appconfig_key store_id } }` | **No** |
| Upsert JSON | **`mutation`** `appConfigHeadlessSaveJson(items: [...]) { saved }` | Yes |
| Register path | **`mutation`** `appConfigHeadlessRegisterUrl(...)` | Yes |
| Unregister path | **`mutation`** `appConfigHeadlessUnregisterUrl(route_id, store_ids)` | Yes |
| Delete JSON keys | **`mutation`** `appConfigHeadlessDeleteJsonKeys(keys: [...]) { deleted }` | Yes |

Example exchange:

```graphql
mutation DelegationExchange($delegationCode: String!) {
  appConfigHeadlessExchangeSession(delegationCode: $delegationCode) {
    session_token
    expires_at
    expires_in
    user_id
    user_type
  }
}
```

Example list of registered storefront paths (same data as **`GET …/headless/url-routes`**):

```graphql
query HeadlessUrlRoutes($storeId: Int) {
  appConfigHeadlessUrlRoutes(store_id: $storeId) {
    items {
      route_id
      request_path
      appconfig_key
      store_id
    }
  }
}
```

Use **`store_id: null`** or omit the argument for every store; pass a store view id to filter.

Delete headless JSON keys / unregister URLs (writes — session + HMAC):

```graphql
mutation DelKeys($keys: [String!]!) {
  appConfigHeadlessDeleteJsonKeys(keys: $keys) {
    deleted
  }
}
```

```graphql
mutation Unreg($routeId: Int!, $storeIds: [Int]) {
  appConfigHeadlessUnregisterUrl(route_id: $routeId, store_ids: $storeIds) {
    deleted_rewrites
    route_removed
  }
}
```

---

## Routing reference (`urlResolver` / `route`)

Same mapping rules: **`type === APPCONFIG_HEADLESS`**, **`id`** = **`route_id`** (where applicable), **`app_config_key`** ⇒ **`key_name`**.

**`route(url: …)`** returns **`RoutableInterface`**. Fields such as **`type`** and **`app_config_key`** are on the concrete type **`RoutableUrl`**, so use an **inline fragment** (Next.js / Postman queries must match this):

```graphql
query RouteHeadless($url: String!) {
  route(url: $url) {
    relative_url
    redirect_code
    ... on RoutableUrl {
      type
      app_config_key
    }
  }
}
```

**`urlResolver`** exposes **`EntityUrl`**-shaped fields; **`app_config_key`** can usually be queried on that result without a fragment unless your storefront schema nests it differently.

Anonymous **`appConfig {}`** omits **`origin=headless`** keys; fetch JSON via **`appConfigHeadlessJson`** or **`GET …/headless/json`**. To enumerate all registered headless paths for ISR/build, use **`appConfigHeadlessUrlRoutes`** or **`GET …/headless/url-routes`**.

---

## Operations and limits

- Without a saved shared secret, headless endpoints **404**.
- Rewrites: collision on same **`request_path` + `store_id`** ⇒ **400**.
- After schema changes: **`bin/magento cache:flush`**.

## Troubleshooting

- **401 on writes** after upgrade: old JWT may be non-admin — run **Launch** and **exchange** again.
- **401 on reads** if you still send **`X-AppConfig-Session`**: remove it for read-only calls (or use a fresh admin session token only for writes).
- **Postman:** Import [postman.json](postman.json) — folder **1 — Headless** signs requests automatically; fill `base_url`, `shared_secret`, `delegation_code`, `headless_sample_key`. Wrong `pathInfo` (subdirectory installs) ⇒ set **`appconfig_pathinfo_override`** to the canonical path Magento logs (e.g. `/tr/graphql`).
- HMAC rejects: canonical string must match (**method**, **path**, timestamp, SHA-256 of **exact** raw HTTP body Magento receives).
