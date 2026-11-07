BIGSHOR WIKI ARTICLE STUDIO v1.4.0

Requires BIGSHOR WIKI Core v34.2.0+, WordPress 6.2+, PHP 7.4+.

PHASE 3 FEATURES
- Text-selection anchored editorial comments and suggested replacements (stores the selected quote as an anchor; it does not yet provide resilient character-offset anchoring).
- Reviewer workflow: accept/reject suggested changes and resolve comments, restricted to users with edit_others_posts. Decisions are recorded; acceptance does not silently rewrite article content.
- Review assignments to users who can edit posts.
- Editorial activity timeline for saves, comments, suggestions, review decisions and assignment changes.
- Existing recovery snapshots, presence indicator, references, WordPress revisions and edit-conflict check retained.

COLLABORATION LIMIT
This is an editorial workflow layer, not real-time co-editing. There is no CRDT/OT merge engine or WebSocket collaboration server. Anchors are stored as selected text and may become ambiguous if the article changes. Suggested replacements require an editor to apply the accepted text in the article and save a new revision.

SECURITY
All new AJAX actions require the Phase 2 nonce and article edit permission. Review decisions and assignments additionally require edit_others_posts. User IDs are validated for edit_posts capability. Text is sanitized and rendered through textContent.


Version 1.4.0 carries forward the existing Article Studio workflow and adds responsive panel refinements, keyboard save shortcut, focus-visible styling and a conservative unsaved-navigation warning. The warning complements, but does not replace, server-side recovery/autosave.
