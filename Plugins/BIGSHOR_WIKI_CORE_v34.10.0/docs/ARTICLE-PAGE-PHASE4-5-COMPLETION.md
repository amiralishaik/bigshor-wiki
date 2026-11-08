# BIGSHOR WIKI Article Page — Phases 4 and 5

## Phase 4 — Infobox and media presentation

- Extended the existing structured `_bsw_infobox` record without changing its storage key or removing legacy field compatibility.
- Added editable image alternative text, caption, creator/credit and source URL fields to the existing WordPress infobox metabox.
- Renders captions, creator credit and a safe source link beneath the infobox image when supplied.
- Supports attachment IDs using WordPress responsive image markup, lazy loading and async decoding; supports validated external image URLs as a fallback.
- Infobox values may be plain text or a structured value with an optional validated URL.
- Retains the legacy Phase 1 infobox row migration/read fallback.
- Added responsive media caption and attribution styling.

## Phase 5 — Article sections and knowledge navigation

- Adds an accessible section-navigation footer with first-section, last-section and back-to-top shortcuts.
- Uses the same stable heading fragment IDs as the article contents tree, avoiding separate or mismatched anchors.
- Adds a topic navigation area using the article's existing `wiki_topic` taxonomy assignments.
- Lists up to four published related wiki articles sharing one or more topics, excluding the current article.
- Escapes topic/article labels and URLs and uses responsive columns for related article links.
- Leaves the existing table of contents, section edit permissions, citations, article metadata and Talk area intact.

## Regression and CI coverage

The code-quality workflow runs:
- `tests/phase4-infobox-media-regression.php`
- `tests/phase5-knowledge-navigation-regression.php`

The complete CI matrix lints all PHP sources and executes the standalone regression suite on PHP 7.4, 8.2 and 8.4, then packages BIGSHOR WIKI Core.

## Acceptance boundary

Automated checks validate static integration and guardrails. Final acceptance still requires real WordPress article testing with image attachment and external image examples, caption/credit/source metadata, empty and populated topic taxonomies, shared-topic articles, and desktop/tablet/mobile layouts. No live-site deployment or production branch change is included in this work.
