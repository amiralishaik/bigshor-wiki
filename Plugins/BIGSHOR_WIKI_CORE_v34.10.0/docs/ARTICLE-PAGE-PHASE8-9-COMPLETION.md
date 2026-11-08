# BIGSHOR WIKI Article Page — Phases 8 and 9

## Phase 8 — Discussion and Community Participation
- Added an article-level discussion area linked from the article Talk tab.
- Reuses the existing wiki_talk post type and WordPress comment/reply system.
- Supports authenticated topic creation, replies, topic-to-article association, a honeypot and per-user submission throttling.
- Adds authenticated add/remove watchlist controls using the existing _bsw_watchlist user metadata, so existing watch notifications continue to work.
- Existing notification inbox and email delivery modules remain the notification providers.

## Phase 9 — Advanced Article Features
- Adds an editor metabox for alternative names and external identifiers/authoritative links.
- Validates and bounds all submitted metadata; external links must use HTTP or HTTPS.
- Adds a collapsible article information panel with author, dates, article ID, word count and estimated reading time.
- Adds copyable plain-text and BibTeX citation formats plus a canonical article URL.
- Registers [bsw_article_information] for use on other article-information layouts.

## Automated verification
Dedicated Phase 8 and Phase 9 static regression suites are added to the Core quality workflow, alongside existing PHP version linting, regression suites and package creation.

## Acceptance boundary
Changes are committed to the development branch. Browser/staging tests are still required for comment settings, logged-in/logged-out roles, email delivery, accessibility and responsive presentation. Production is not changed.
