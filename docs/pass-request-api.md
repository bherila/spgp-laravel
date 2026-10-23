# Pass request REST API and MCP

SPGP provides separate user and administrator interfaces:

| Interface | URL |
| --- | --- |
| User REST API | `/api/v1/pass-requests` |
| Administrator REST API | `/api/v1/admin/pass-requests` |
| User MCP server | `/mcp/user` |
| Administrator MCP server | `/mcp/admin` |
| OAuth protected-resource metadata | `/.well-known/oauth-protected-resource` |

The website, REST API, and MCP tools share `UserPassRequestService` and `AdminPassRequestService`. REST and MCP also share operation authorization, pagination, and response serialization. The browser's existing session endpoints remain available.

## Authorization

REST and MCP require an OAuth bearer access token from the configured external issuer. Browser cookies and query-string tokens cannot authenticate these interfaces. SPGP introspects the token on every request, validates its issuer, audience/resource, expiry and subject, and maps that exact issuer/subject pair to an explicitly linked local account. Token revocation takes effect on the next request.

| Scope | Access |
| --- | --- |
| `pass-requests:read` | Your requests, active seasons, and pass types |
| `pass-requests:write` | Create, update, cancel, and report renewal details on your requests |
| `admin:pass-requests:read` | Read requests across users and browse seasons |
| `admin:pass-requests:write` | Edit/delete requests, assign/clear codes, and send notification emails |

Read and write scopes are independent. Administrator scopes also require the linked SPGP user to have `is_admin=true`; an OAuth scope does not grant an SPGP role. An administrator using a **user** endpoint is still limited to their own requests. The admin interfaces expose no local-account role management or identity-linking operation.

## Enable an environment

The interfaces are disabled by default. The application code supports an external Auth Manager **resource** profile; it does not replace SPGP's browser login with SSO.

Configure these environment values using the deployment's secret store:

```dotenv
PASS_REQUEST_API_ENABLED=false
OAUTH_RESOURCE_ISSUER=https://identity.example.test
OAUTH_RESOURCE_URI=https://spgp.example.test
OAUTH_INTROSPECTION_ENDPOINT=https://identity.example.test/oauth/introspect
OAUTH_INTROSPECTION_CLIENT_ID=<resource-server backchannel client ID>
OAUTH_INTROSPECTION_CLIENT_SECRET=<resource-server backchannel secret>
```

Use one canonical resource URI for REST and both MCP servers: the application's HTTPS origin, without a path or trailing slash. The authorization server must issue tokens for that exact resource, and must permit the backchannel client to introspect it. The introspection client is a server credential, not an end user's token. An optional `OAUTH_INTROSPECTION_TIMEOUT_SECONDS` defaults to 5.

**Auth Manager prerequisite:** its current resource profile has a fixed scope catalog (`identity:read`, `mcp:use`, `offers:read`). Add the four SPGP scopes above to the provider's permitted and resource-bound scope catalog before enabling this application. Its current profile also pins a single resource; use an SPGP-specific resource-profile instance, or extend the provider to support the additional resource. Do not repurpose another application's configured resource. Ordinary sign-in grants alone are insufficient for these API tokens. Auth Manager's `docs/deployment-profiles.md` describes that profile.

The new `2026_10_22_000000_add_api_identity_binding_to_users_table.php` migration adds nullable identity-binding columns and a uniqueness constraint. It must be included in the normal reviewed deployment before enabling API access. No migration is run as part of this change's implementation.

An operator then links each existing local account to a verified OAuth subject:

```bash
php artisan api:link-identity 123 verified-provider-subject
```

The command uses the configured issuer, refuses account creation, refuses replacing an existing binding, and refuses binding the same identity to another account. Verify the opaque subject using the provider's trusted administration tools. Email equality is not an account-linking mechanism. This link grants neither an OAuth scope nor a local administrator role.

After the provider, schema, credentials and account links are ready, set `PASS_REQUEST_API_ENABLED=true` through the normal deployment/config-cache process. OAuth metadata and authentication challenges use configured URLs rather than request Host headers. Missing configuration or provider outages return 503; disabled interfaces return 404. Requests with a browser Origin header must match the configured SPGP origin.

## REST usage

Send `Authorization: Bearer <access-token>` and `Accept: application/json`; send `Content-Type: application/json` for request bodies. The caller obtains the token from the external provider's authorization-code/PKCE flow, with the exact resource and required scopes. CSRF tokens are not required on these bearer-only endpoints.

```bash
curl --fail-with-body https://spgp.example.test/api/v1/pass-requests \
  -H "Authorization: Bearer $SPGP_ACCESS_TOKEN" \
  -H 'Accept: application/json'
```

| Method | User path | Action |
| --- | --- | --- |
| GET | `/api/v1/seasons` | Discover active/upcoming seasons and pass types |
| GET | `/api/v1/seasons/{season_id}/pass-types` | List a season's pass types |
| GET | `/api/v1/pass-requests` | List your requests; optional `season_id`, `page`, `per_page` |
| POST | `/api/v1/pass-requests` | Create a request (201) |
| GET | `/api/v1/pass-requests/{id}` | Read your request |
| PATCH | `/api/v1/pass-requests/{id}` | Update passholder details or renewal order number |
| DELETE | `/api/v1/pass-requests/{id}` | Cancel before a code is assigned |
| PUT | `/api/v1/pass-requests/{id}/renewal-order` | Set `renewal_order_number` and its reporting date |
| DELETE | `/api/v1/pass-requests/{id}/renewal-order` | Remove renewal order number/reporting date |
| PATCH | `/api/v1/pass-requests/missing-country` | Set `country` (USA/Canada) on your requests missing it |

Create payload:

```json
{
  "season_id": 1,
  "season_pass_type_id": 2,
  "passholder_first_name": "Alex",
  "passholder_last_name": "Example",
  "passholder_email": "alex@example.test",
  "passholder_birth_date": "1990-01-31",
  "country": "USA",
  "is_renewal": false
}
```

`renewal_pass_id` is optional. Updates support the four `passholder_*` fields above and nullable `renewal_order_number`. Setting or changing the renewal order records its reporting date; clearing it also clears that date. Repeating the same order or editing other details preserves its existing reporting date. Ownership, season, promo code, admin status and notification/redemption state cannot be set through user payloads. The pass type must belong to the selected season. Duplicate passholder identity within a season returns 422. Existing assignment/notification rules apply to every transport.

| Method | Administrator path | Action |
| --- | --- | --- |
| GET | `/api/v1/admin/seasons` | Discover seasons; optional `include_archived` |
| GET | `/api/v1/admin/pass-requests` | Paginated cross-user search |
| GET/PATCH/DELETE | `/api/v1/admin/pass-requests/{id}` | Read/edit/delete a selected request |
| POST | `/api/v1/admin/seasons/{season_id}/pass-requests/assign-codes` | Assign explicit codes |
| POST | `/api/v1/admin/seasons/{season_id}/pass-requests/auto-assign-codes` | Assign available country-matched repository codes |
| POST | `/api/v1/admin/seasons/{season_id}/pass-requests/clear-codes` | Release selected codes and reset notification/redemption state |
| POST | `/api/v1/admin/seasons/{season_id}/pass-requests/send-emails` | Notify selected passholders |
| POST | `/api/v1/admin/seasons/{season_id}/pass-requests/bulk-delete` | Delete selected requests after their codes are cleared |

Admin list filters are `season_id`, `user_id`, `is_renewal`, `recent_only` (assignment within 24 hours), `has_promo_code`, and `country`. Pagination uses `page` and `per_page` (default 25, maximum 100). Lists return `data` plus `meta.current_page`, `per_page`, `last_page`, and `total`; individual records return `data`.

Selected admin actions accept `pass_request_ids`, an ordered array of up to 1,000 distinct IDs from the URL's season. Assignment also accepts `codes`, one code per line in the same order. Unknown manually entered codes are added to the repository; existing codes must match the season/country and be available. A missing country is treated as USA. Email sends skip requests already notified; `force_send: true` explicitly requests a resend. Action responses contain `assigned`, `cleared`, `sent`, or `deleted` counts. A zero `sent` count can mean no eligible requests or delivery failure; existing email logs record outcomes.

Responses use 401 for missing/invalid/unlinked tokens, 403 for missing scopes or local permissions, 404 for missing records, 422 for validation/business rules, 429 for the 60-request/minute IP limit, and 503 for unavailable authorization. Personal responses have `Cache-Control: private, no-store`. Dates use the existing model ISO 8601 serialization; send birth dates as `YYYY-MM-DD`. A machine-readable [OpenAPI document](pass-request-api.openapi.json) describes the routes and inputs.

## MCP usage

Connect an HTTP MCP client to `https://spgp.example.test/mcp/user` or `https://spgp.example.test/mcp/admin`. Clients supporting OAuth protected-resource discovery can discover the external issuer from the 401 `WWW-Authenticate` challenge; authorize with the resource URI and required scopes. Clients supporting explicit access-token headers may send the same bearer token used by REST. The server uses Laravel MCP's stateless HTTP protocol (POST JSON-RPC); it does not require SPGP browser cookies or a local Passport server.

`tools/list` advertises only tools permitted by the token's scopes. The user server exposes list/get/create/update/cancel, season/pass-type discovery, renewal reporting and missing-country updates. The admin server exposes cross-user list/get/update/delete, season discovery, code assignment/clearing, and email notifications. Tools use the same names as the shared operation catalog (`list_pass_requests`, `assign_promo_codes`, etc.) and carry read-only/destructive/idempotence annotations. Scope filtering also prevents invoking a hidden write tool by name.

For example, a `tools/call` body for a user request lookup is:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "get_pass_request",
    "arguments": { "id": "01EXAMPLEPASSREQUESTID000000" }
  }
}
```

Successful tool results include structured content matching the REST envelope. Validation/ownership failures are MCP tool errors; missing scopes hide the corresponding tool. Both transports share the same local records and immediate role/ownership checks.
