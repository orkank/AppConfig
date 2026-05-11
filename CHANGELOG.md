# Changelog

All notable changes to this project are documented in this file.

## [2.0.0] — 2026-05-10

### Added

- **Admin menu:** **Stores → App Config → Launch headless** — one-click access to the admin delegation POST flow (same behavior as **Headless Integration → Launch headless**), without opening system configuration.
- **Headless — list storefront routes:** `GET /V1/appconfig/headless/url-routes` and GraphQL `appConfigHeadlessUrlRoutes` — enumerate all `register-url` mappings (`request_path`, `appconfig_key`, `store_id`, `route_id`). Read-only, HMAC-only (no session).
- **Headless — remove routes:** `POST /V1/appconfig/headless/unregister-url` and GraphQL `appConfigHeadlessUnregisterUrl` — delete `url_rewrite` rows / `headless_route` as appropriate; optional `store_ids` for partial removal.
- **Headless — delete JSON keys:** `POST /V1/appconfig/headless/delete-json-keys` and GraphQL `appConfigHeadlessDeleteJsonKeys` — permanently remove **headless-origin** keyvalue rows (prefix + group rules apply); does not delete admin-created keys.
- **Documentation:** This changelog; expanded [README](README.md); [HEADLESS.md](HEADLESS.md) and [postman.json](postman.json) updated for the above.

### Changed

- Headless Integration screen: delegation button label shortened to **Launch headless**; note text references the new admin menu shortcut.

## [1.0.0] — baseline

Initial packaged release (key-value groups, rich types, REST/GraphQL `appConfig`, Import/Export, and headless integration with HMAC, delegation exchange, JSON sync, and `register-url` / `urlResolver` / `route` mapping). Earlier history is not split further in this file.
