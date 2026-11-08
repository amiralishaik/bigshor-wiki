# BIGSHOR WIKI Article Page — Phases 6 and 7

## Phase 6 — Article Quality and Editorial Standards
- Expanded the article quality review into a reusable advisory checklist.
- Checks descriptive titles, lead/summary coverage, substantive article length, section structure and heading hierarchy.
- Checks citation shortcodes together with structured source records, featured images, embedded image alternative text and repeated long sentences.
- Shows a transparent checklist count and explains what each failed check means.
- Explicitly leaves neutrality, notability, factual accuracy, source independence, copyright and citation-to-claim verification to human editorial review.
- Quality checks do not block saves or publication and do not represent an approval or fact-check.

## Phase 7 — Revision History and Article Comparison
- Ensures the wiki_article post type supports native WordPress revisions.
- Provides bounded revision history with contributor and timestamp details.
- Registers both [bsw_article_history] and the existing [bigshor_wiki_history] alias used by the article page.
- Adds capability-checked comparison and restoration actions.
- Validates nonce, article type and revision parent before compare/restore.
- Uses WordPress native diff and restore APIs; restoration creates a new saved version and retains earlier revisions.

## Automated verification
Dedicated regression checks cover the quality checklist and revision-history safeguards. PHP syntax, existing regression suites and Core packaging remain enforced by GitHub Actions.

## Acceptance boundary
These changes are implemented in the development branch only. Automated checks do not replace browser testing in a real WordPress staging site, checking revision settings/retention, user-role testing, or editorial review of representative articles. Production is not changed by this work.
