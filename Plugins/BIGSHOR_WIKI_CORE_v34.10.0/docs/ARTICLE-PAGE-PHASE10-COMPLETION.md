# Article Page Roadmap — Phase 10 Completion

## Scope

Phase 10 adds article-level Search and Discovery Integration. It builds on the existing BIGSHOR Wiki search index, wiki_topic taxonomy, Phase 9 alternative-name metadata, and Phase 58 knowledge graph. It does not create a second index or modify existing article records.

## Implemented

- Added includes/phase10-article-discovery.php and loaded it from BIGSHOR Wiki Core.
- Added the reusable [bsw_article_search] shortcode.
- Search covers published article titles/content and editor-maintained alternative names stored in _bsw_p9_aliases.
- Search input is sanitised and bounded; queries require at least two characters.
- Results are restricted to published wiki articles, escaped, and paginated.
- Article pages now show topic links, related articles sharing topics, and additional editor-curated related articles from the existing knowledge graph when available.
- Added a bounded “What links here” panel that checks candidate article content for the current article permalink.
- Added responsive styles and accessible labels.
- Added a dedicated regression test and CI step.

## Use

Place [bsw_article_search] on a search page to provide a reusable search interface. Article pages automatically receive the discovery panel after the article information tools.

## Verification boundary

The code and regression checks are committed to the development branch. GitHub Actions must complete successfully before calling automated verification passed. Live WordPress behaviour, database-backed search, theme rendering, and desktop/tablet/mobile visual QA still require a staging or production WordPress environment. No production branch or live website changes are made by this phase.
