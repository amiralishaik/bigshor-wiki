# BIGSHOR WIKI Phase 3 Completion — v32.9.0

This is the Phase 3 completion pass on top of v32.8.0.

## Added in v32.9.0
1. **Deletion discussions** — logged-in editors can open a deletion proposal with a reason; a discussion record is created privately and comments are available while it remains open. An editor closes it with Keep, Delete (moves the article to WordPress Trash), or Merge (sets a BIGSHOR redirect to another published article).
2. **Editorial approval queue** — pending `wiki_article` submissions appear in the governance dashboard. Editors can approve and publish or return a submission to draft, with a review note.
3. **Restoration interface** — a dashboard lists trashed wiki articles and offers nonce-protected restoration using WordPress `wp_untrash_post`.
4. **Audit integration** — workflow actions are recorded in a bounded Phase 56 audit option and mirrored to the Phase 55 moderation audit stream.

## Existing Phase 3 functions retained
- Native WordPress revision compare and restore (Phase 8).
- Rollback and patrol workflow (Phase 49).
- Existing article talk pages and discussion replies (Phase 48).
- Page protection and account/IP restriction controls (Phases 48, 50 and 51).
- Phase 55 moderation appeals, revision suppression and protection tiers.

## Use
- Place `[bsw_deletion_discussion id="123"]` on an article page to display its discussion and (for editors) the proposal form.
- Submit articles for editorial review using the existing WordPress Pending status.
- Open **Wiki Articles → Editorial Workflow** for approval queue, deletion discussions and Trash restoration.
- Open **Wiki Articles → Governance & Moderation** for appeals, revision oversight, protected pages and audit history.

## Operational limitations
- The merge resolution creates a redirect from the source article to the selected published destination; it does not automatically reconcile/merge article text, references, categories or media. Editors must perform content reconciliation separately before resolving as Merge.
- WordPress Trash retention and restore behaviour are controlled by the site's WordPress configuration. Permanent deletion cannot be reversed by this interface.
- Existing account/IP restriction controls are in Phases 50–51; appeal submissions are available through `[bsw_moderation_appeal]`. Block/unblock actions remain administrator-only.
- No live WordPress activation, real database, role matrix, browser, penetration, load or cross-browser tests were run in this build. Production staging and editorial acceptance remain required.
