BIGSHOR WIKI CORE — VERSION 33.6.0

Phase 9 adds contributor reputation, assigned multi-stage editorial review, optional publication gating, safe source URL availability checks, weighted-lexical public knowledge search, and WordPress privacy exporter/eraser integration. See PHASE9-COMPLETION-v33.5.0.md in the release package.

Historical release notes below are retained for reference.

BIGSHOR WIKI CORE — VERSION 30.1.0

This additive update builds on the existing BIGSHOR Wiki Core 29.9.0 package.
It does not replace the existing article post type, workflows, users, taxonomies,
importer, community modules or settings.

PHASE 41 FEATURES
- Wikipedia-inspired Read / Edit / Edit source / View history / Talk tabs.
- Automatically generated contents navigation from article H2-H4 headings.
- Per-section edit links and section-only saves that preserve other article sections.
- Visual editor for full article editing and source-mode editing of stored WordPress HTML.
- Optimistic concurrency check: a stale editor is asked to reload instead of overwriting newer edits.
- Structured infobox display, including compatibility with existing BIGSHOR infobox metadata.
- Administrator infobox fields in the frontend editor.
- Basic exact repeated-passage warning; it never deletes or blocks content.
- WordPress revision history and links to the native revision compare/restore screen.
- Existing WordPress capabilities and BIGSHOR review workflow remain in force.

IMPORTANT LIMITATIONS
This is a WordPress-native encyclopaedia editing experience, not full MediaWiki parity.
The Edit source view edits WordPress HTML, not MediaWiki wikitext. There is no semantic
near-duplicate AI detector, universal citation parser, or guaranteed section merge for
concurrent edits. WordPress revisions must be enabled (they are enabled for wiki_article).

INSTALLATION
1. Back up the site and database.
2. In WordPress, Plugins > Add New Plugin > Upload Plugin.
3. Upload BIGSHOR_CORE_v30.1.0.zip and choose Replace current with uploaded if prompted.
4. Activate/refresh the plugin. Do not deactivate or delete the existing plugin first.
5. Confirm an Administrator can edit a test wiki article from the frontend and dashboard.
6. Test a non-admin contributor account to confirm existing permissions and review flow.

The theme packages are separate. Install the parent theme first, then activate the child theme.
Keep the child theme active for the site design.


Phase 3 v32.8.0 adds governance and moderation. See docs/PHASE3-EDITORIAL-GOVERNANCE.md.


Phase 3 completion v32.9.0: see docs/PHASE3-COMPLETION-v32.9.0.md. Adds deletion discussions, editorial approval queue and Trash restoration dashboard.

Phase 10 adds administrator-only production preflight, component and API route smoke checks, enhanced weighted-lexical search API v4, federation integration inventory, bounded citation hygiene review, and a redacted deployment inventory manifest. See docs/PHASE10-PRODUCTION-READINESS-v33.6.0.md.


BIGSHOR WIKI v34.1.0 — Universal Admin White-label Interface
- Applies BIGSHOR branding and responsive navigation to WordPress administration screens.
- Provides a unified white-label style layer for core and third-party plugin screens.
- Keeps WordPress core files unchanged; underlying screens and capabilities remain native.
- Third-party plugin interfaces are visually themed where selectors are compatible; plugin-specific custom apps may retain their own UI.
- Security, update and error notices remain visible.
