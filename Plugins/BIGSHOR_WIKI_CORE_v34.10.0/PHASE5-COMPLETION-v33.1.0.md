# BIGSHOR WIKI — Phase 5 Completion (v33.1.0)

## Scope
Phase 5 completes the Editorial Intelligence & Visibility layer. This release builds on the existing deterministic article-quality checks, source provenance records, citation URL availability checks, freshness and duplicate-title review queues, schema-readiness checks, CSV reporting, optional AI-assisted editorial review, claim triage, and internal-link suggestions.

## New in v33.1.0 — Structured Knowledge Graph
- Editor-maintained entity records for people, organisations, places, events, works, concepts and other subjects.
- Optional HTTPS reference URL per entity; URLs are stored but are not fetched or certified.
- Explicit article relationships with controlled relation types and published wiki-article targets.
- Duplicate entity labels are collapsed per article; input is bounded to 30 entities and 20 relationships.
- Exact-label related-article suggestions, limited to six public articles per page.
- Read-only public REST endpoint: `GET /wp-json/bigshor/v1/knowledge-graph/{article_id}`. Only published wiki articles are exposed.
- Optional frontend display shortcode: `[bsw_knowledge_graph]` (also accepts `id` and `title`).
- Schema.org `Thing` JSON-LD nodes are emitted for editor-entered entities on published wiki articles.
- Editorial dashboard showing graph coverage and articles that still need entity metadata.

## AI request safety hardening
- Both AI editorial critique and claim-triage routes use WordPress safe HTTP requests, TLS verification, no redirects, unsafe-URL rejection, response-size limits and shorter timeouts.
- Shared per-user throttle: maximum six AI requests per ten minutes across the two routes.
- Existing opt-in provider configuration, human review requirement and no-auto-publish behaviour remain.

## Existing Phase 5 capabilities retained
- Deterministic editorial completeness score and human fact-check status.
- Source type, independence and editor confidence records.
- Citation URL reachability check (availability only, not source reliability or claim support).
- Freshness and possible duplicate-title review dashboard.
- Editorial CSV export.
- Schema readiness and claim review assistance.
- Optional external AI editorial assistance and topic-based internal-link suggestions.

## Installation
1. Back up database and `wp-content`; install on staging first.
2. Replace the existing BIGSHOR Wiki Core plugin with `BIGSHOR_WIKI_CORE_v33.1.0.zip` and activate it.
3. Keep Parent Theme v23.1.0 and Child Theme v23.2.0 if those versions are already installed; they are unchanged in this release.
4. Open Wiki Articles → Knowledge Graph and test with non-sensitive sample articles.
5. Add `[bsw_knowledge_graph]` to the article template only if the frontend related-topics section is desired.
6. Test the public graph endpoint against a published article and a draft article; the draft must return 404.
7. If AI assistance is enabled, verify the provider endpoint, privacy terms and rate-limit behaviour before editor rollout.

## Safety and limitations
- Entity names, URLs and relationships are editorial assertions. BIGSHOR does not independently verify identity, source quality, or relationship truth.
- Related-article suggestions use exact shared entity labels, not semantic similarity.
- JSON-LD output describes supplied entities only; it does not assert that an external URL is an authoritative identity record.
- AI assistance sends article draft text to the administrator-configured provider only when an authorised editor explicitly requests it. AI output is advisory and must be checked by a human.
- The rate limit is per WordPress user and uses transients; it is an application-level cost guard, not a substitute for provider-side quotas or a WAF.
- No live WordPress staging, browser, database migration, provider connectivity, penetration, load, or WCAG conformance testing was performed in this build environment.
