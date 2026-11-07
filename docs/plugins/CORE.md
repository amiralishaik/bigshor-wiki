# BIGSHOR WIKI Core Plugin

**Documented version:** 34.10.0

## Role

The Core plugin is the principal platform layer for BIGSHOR WIKI.

## Release composition

The v34.10.0 consolidation uses the v34.9.0 Core as its canonical base and integrates `includes/phase70-media-optimizer.php` from the v34.8.0 branch. The Core plugin header and `BSW_CORE_PLUGIN_VERSION` were updated to 34.10.0.

## Recorded platform development areas

The source/release history includes modules or documentation related to:
- Wikipedia-style article experience and wiki framework.
- Wikitext and parser-engine development.
- Article quality and layout.
- Front-end editing and encyclopedia controls.
- Maintenance patrol and editorial governance/workflows.
- Template engine.
- Search routing and special pages.
- Multilingual workflows and knowledge-graph/interoperability development.
- User roles, permissions and account system.
- Role registration/application pages.
- Modern image delivery and media optimization.

These are development areas, not a blanket assertion of complete MediaWiki parity. Each feature requires independent functional testing.

## Account and role registration notes (v34.9.0)

Release notes record:
- Shared `/login/` page.
- Role registration/application pages for built-in wiki roles except network-level Super Admin.
- Public registration assigns the least-privileged `wiki_article_creator` role.
- Elevated roles are application-only, pending administrator approval and separate assignment.
- Password reset at `/reset-password/`.

## Phase69 — modern image delivery

Release notes describe responsive AVIF/WebP sidecars for JPEG/PNG uploads where supported, original-image fallback and responsive image markup.

## Phase70 — media optimizer

Integrated in v34.10.0 from the v34.8.0 branch. Recorded behavior:
- Queues work after `wp_generate_attachment_metadata`.
- Supports JPEG, PNG and WebP uploads.
- Generates AVIF/WebP sidecars for originals and WordPress-generated sizes where GD/Imagick support is available.
- Preserves original files.
- Adds modern-format sources to supported attachment/content markup using `<picture>`.
- Provides a Media → Media Optimizer admin screen and retry action.
- Uses WP-Cron for asynchronous processing.
- Does not perform historical bulk conversion.

## Outstanding capabilities requiring verification

The project backlog includes advanced parser/nested templates/parser functions; deletion discussions, undelete and revision suppression; account/IP blocking; event-driven notifications/watchlists; advanced search/disambiguation/namespaces; translation editor; API authentication, bot framework and export; media history/duplicate detection/rights moderation; and automated security/accessibility/performance testing.

Do not mark these complete without implementation review and test evidence.

## Verification

For v34.10.0, the package record reports 89 PHP files linted with zero syntax errors and all five component ZIPs passing integrity checks. No live WordPress deployment test was performed.
