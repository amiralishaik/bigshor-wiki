# BIGSHOR WIKI — Phase 7 Completion (Core v33.3.0)

## Scope
Phase 7 completes the Global Knowledge Network with cross-wiki federation while retaining the existing Phase 7 knowledge graph, people and organisation profiles, verification workflow, multilingual relationships, expert directory, timeline, structured data, public API and media management.

## New federation capabilities
- Peer registry managed by administrators under **Settings → BIGSHOR Federation**.
- Peer identity, display name, HTTPS base URL and shared secret configuration.
- Shared secrets are encrypted at rest using AES-256-CBC with a key derived from the WordPress authentication salt; peer registration is refused if OpenSSL is unavailable.
- Public protocol manifest at `GET /wp-json/bigshor/v1/federation/manifest`.
- Bounded public published-article feed at `GET /wp-json/bigshor/v1/federation/articles` (maximum 50 records per page).
- Manual peer discovery and import from the administrator dashboard. Outbound HTTP uses `wp_safe_remote_get`, HTTPS peer URLs, redirects disabled and response-size limits.
- Authenticated inbound endpoint at `POST /wp-json/bigshor/v1/federation/receive`. Requests use HMAC-SHA256, five-minute timestamps, nonce replay protection and a 256 KB request limit.
- Imported records are sanitised, deduplicated by peer/external ID and always saved as **Pending Review**. They are never auto-published and do not grant a peer editorial privileges.
- Source URL, peer identity, receipt time and review-required state are recorded as metadata.

## Federation setup
1. Install and activate Core v33.3.0 on each participating wiki.
2. In **Settings → BIGSHOR Federation**, register each remote wiki using a unique peer ID, its HTTPS base URL and a strong random shared secret (32–256 characters).
3. Configure the same peer ID and secret on the receiving wiki. Peer IDs and secrets must match on both sides for signed inbound exchange.
4. Use **Discover & import updates** to fetch the peer's public article feed. Review imported records in the normal editorial queue before publication.
5. To push from an external compatible implementation, POST the federation JSON envelope to `/wp-json/bigshor/v1/federation/receive` with `X-BSW-Peer`, `X-BSW-Timestamp`, `X-BSW-Nonce` and `X-BSW-Signature`. The signature is the lowercase hex HMAC-SHA256 of `timestamp + newline + nonce + newline + raw_request_body`, keyed by the shared secret.

## Important limitations
- Federation is a defined BIGSHOR protocol, not full MediaWiki/Wikibase federation and not ActivityPub.
- Peer sync is manual; no background scheduler or continuous bidirectional replication is enabled.
- The public feed exports published article title, body, excerpt, URL, modified time and language. It does not automatically transfer revisions, categories, media binaries, user accounts, comments or deletion events.
- Inbound signed push is supported, but this release's dashboard uses pull-based sync.
- Imported articles require editorial review; reviewers must independently verify source quality, copyright/rights, citations and accuracy.
- Encryption at rest depends on the WordPress authentication salt remaining stable. Changing salts can make stored peer secrets undecryptable; re-register affected peers if this occurs.
- Public feed availability is controlled by the site's normal WordPress visibility and REST configuration.

## Verification
- 76 PHP files passed `php -l` with zero syntax failures.
- 25/25 static integration checks passed.
- ZIP integrity and SHA-256 checks are included in this release.

## Not performed
No live WordPress activation, real database, browser, multi-site peer handshake, network interoperability, role-matrix, penetration, load, or external production testing was performed. Complete those checks on an isolated staging environment before production rollout.
