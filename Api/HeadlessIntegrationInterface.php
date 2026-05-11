<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Api;

/**
 * Next.js-style headless JSON sync (authenticated via headers).
 */
interface HeadlessIntegrationInterface
{
    /**
     * Consume a single-use admin delegation code and return a short-lived session JWT for write operations.
     * Body JSON: {"delegationCode":"…"}. Signed with shared HMAC only (no session header yet).
     *
     * @param string|null $delegationCode One-time code from admin Launch POST redirect (consumed on success).
     * @return array{session_token:string,expires_at:string,expires_in:int,user_id:int,user_type:int}
     */
    public function exchangeSession(?string $delegationCode = null): array;

    /**
     * Read JSON payloads for prefixed keys belonging to configured headless group.
     *
     * @param string[] $keys
     * @return mixed[]
     */
    public function getJson(array $keys): array;

    /**
     * Upsert JSON payloads.
     *
     * @param mixed[] $items Array of associative maps: [["key"=>"…","json"=>mixed], ...]
     * @return mixed[]
     */
    public function saveJson(array $items): array;

    /**
     * Register a storefront path → App Config key via url_rewrite (for urlResolver / route).
     *
     * @param mixed[] $payload Keys: request_path, appconfig_key, optional store_ids (int[])
     * @return mixed[]
     */
    public function registerHeadlessUrl(array $payload): array;

    /**
     * List every stored headless url_rewrite row (register-url): request path, key, store.
     *
     * @param int|null $storeId Restrict to this store view, or null for all.
     * @return array{items: array<int, array{route_id:int,request_path:string,appconfig_key:string,store_id:int}>}
     */
    public function listHeadlessUrlRoutes(?int $storeId = null): array;

    /**
     * Remove register-url mappings: drop url_rewrite rows and headless_route when empty.
     *
     * @param mixed[] $payload Keys: route_id (int) required; optional store_ids (int[]) — omit for full route removal
     * @return array{deleted_rewrites:int,route_removed:bool}
     */
    public function unregisterHeadlessUrl(array $payload): array;

    /**
     * Delete headless JSON key rows (origin=headless in configured group); keys must match prefix.
     *
     * @param mixed[] $payload Keys: keys (string[])
     * @return array{deleted:int}
     */
    public function deleteHeadlessJsonKeys(array $payload): array;
}
