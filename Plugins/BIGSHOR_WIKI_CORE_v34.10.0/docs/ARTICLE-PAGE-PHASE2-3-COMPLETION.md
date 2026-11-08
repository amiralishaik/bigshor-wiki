# BIGSHOR WIKI Article Page — Phases 2 and 3

## Scope

This delivery completes the article-page roadmap's Phase 2 (Wikipedia-style article structure) and Phase 3 (references and citation engine). These roadmap phases are distinct from the repository's historical platform phase numbering.

## Phase 2 — Wikipedia-style article structure

- Retains the canonical article layout, lead/preamble, infobox, action tabs and responsive article container.
- Generates a nested contents navigation from article headings (H2–H6).
- Uses stable, human-readable heading fragment IDs based on section titles.
- Disambiguates repeated section titles with deterministic numeric suffixes.
- Links the contents panel and its collapse control with accessible labels and aria-controls.
- Keeps section-edit links behind the WordPress edit_post capability check.
- Retains responsive and print-specific presentation rules.

## Phase 3 — References and citation engine

- Retains structured per-article source records in post metadata; no new database tables or destructive migrations.
- Retains nonce and capability checks, JSON validation, duplicate source-ID rejection, a 200 KB payload ceiling and HTTP/HTTPS URL filtering.
- Supports inline [bsw_cite] and [bigshor_cite] shortcodes.
- Reuses one reference number for repeated citations to the same source.
- Gives every citation occurrence a unique fragment ID.
- Provides a backlink for every occurrence from the reference entry.
- Supports explicit [bsw_references] placement and automatic reference-list insertion when citations exist and no list is present.
- Shows missing-source diagnostics to editors without exposing them to public readers.
- Keeps editorial notes private and renders source metadata with escaped output and safe external-link attributes.

## Regression coverage

The CI workflow now runs:
- tests/phase45-article-layout-regression.php
- tests/phase6-citation-management-regression.php

The repository's PHP lint matrix covers PHP 7.4, 8.2 and 8.4. CI packaging produces the Core ZIP after all quality checks pass.

## Acceptance boundary

Static regression and CI checks do not replace live WordPress verification. Before production release, test real article content with nested headings, duplicate heading titles, multiple citations to the same source, missing source IDs, explicit and automatic references, editor permissions, and desktop/tablet/mobile rendering. Production deployment remains a separate approval step.
