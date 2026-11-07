# BIGSHOR WIKI Phase 3 — Editorial Governance (v32.8.0)

## Included
- Moderation appeals: authenticated users can submit an appeal using `[bsw_moderation_appeal]`; editors review and record uphold, reject, or request-more-information decisions.
- Page protection tiers: open, registered editors, editors, administrators. Enforced through WordPress `map_meta_cap` checks; changes require an editor-level capability.
- Revision oversight: administrators can suppress/reveal individual native WordPress revisions. Suppressed revision title/content is hidden from users without `manage_options`; the revision record is retained.
- Governance dashboard: appeals, revision oversight, protected pages, and bounded moderation audit history.
- Trash/restore/permanent-delete events are recorded in the Phase 3 audit stream.

## Installation
1. Back up the database and files.
2. Deactivate the prior BIGSHOR Wiki Core plugin.
3. Replace the plugin folder with `BIGSHOR_WIKI_CORE_v32.8.0.zip` contents.
4. Activate BIGSHOR Wiki Core. WordPress registers the appeal content type on `init`; no destructive migration is required.
5. Assign editorial capabilities using WordPress roles. Place `[bsw_moderation_appeal]` on a restricted support/appeals page if you want a public-facing appeal form.
6. Test with administrator, editor, author, contributor and logged-out sessions.

## Important scope and limitations
- Uses WordPress native revisions and Trash; it is not a replacement for Wikimedia oversight or a legal-grade immutable audit system.
- Suppression is a display/access layer for WordPress revision retrieval. Direct database access, backups, exports, or privileged administrator access can still expose stored revision data.
- The audit log is a bounded WordPress option (last 1,000 Phase 3 events), not a tamper-proof append-only ledger.
- Existing Phase 8/48/49/50/51 features remain in place; this release adds the Phase 3 governance layer rather than duplicating those subsystems.
- No live WordPress site, database, role matrix, browser, penetration, load, or cross-browser test was performed in this build environment. Staging acceptance is required.
