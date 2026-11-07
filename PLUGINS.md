# BIGSHOR WIKI — Plugins

**Documentation baseline:** Consolidated release v34.10.0  
**Repository:** `amiralishaik/bigshor-wiki`  
**Default branch:** `main`

> This inventory reflects project release records available at the time of writing. A listed feature is not, by itself, proof of successful live-site operation. Verify behavior in staging before production deployment.

## Plugin inventory

| Plugin | Baseline version | Primary responsibility |
|---|---:|---|
| BIGSHOR WIKI Core | 34.10.0 | Core encyclopedia platform and shared functionality |
| BIGSHOR WIKI Admin UI | 1.5.0 | Administrative interface enhancements |
| BIGSHOR WIKI Article Studio | 1.4.0 | Article creation and editorial workspace |

## 1. BIGSHOR WIKI Core — 34.10.0

The consolidated v34.10.0 release uses Core v34.9.0 as its canonical base and integrates the Phase70 media optimizer module from the v34.8.0 branch.

### Recorded platform development areas

Core source/release history includes modules or documentation covering:

- Wikipedia-style article experience and wiki framework.
- Wikitext and parser-engine development.
- Article quality and article layout.
- Front-end editing and encyclopedia controls.
- Maintenance patrol and editorial governance/workflows.
- Template engine and parser-related development.
- Search routing and special pages.
- Multilingual workflows and knowledge-graph/interoperability development.
- User roles, permissions and account system.
- Role registration/application pages.
- Modern image delivery and media optimization.

**Completeness caveat:** The presence of a phase module or documentation file does not prove the entire corresponding capability is complete. Advanced parser parity, moderation, notifications, search, translation and API capabilities require separate functional verification.

### Account and role registration — recorded in v34.9.0

Project release notes record:

- Shared `/login/` page.
- Dedicated role registration/application pages for built-in wiki roles, excluding network-level Super Admin.
- Public registration assigns the least-privileged `wiki_article_creator` role.
- Elevated roles are application-only, pending administrator approval and separate role assignment.
- Native password reset at `/reset-password/`.

### Phase69 — modern image delivery

The v34.9.0 notes record responsive AVIF/WebP sidecars for JPEG/PNG uploads where supported by the editor, original-image fallback and responsive image markup.

### Phase70 — media optimizer (integrated in v34.10.0)

The v34.10.0 consolidation integrates `includes/phase70-media-optimizer.php` from the v34.8.0 Core branch into the v34.9.0 canonical Core base.

Recorded Phase70 behavior:

- Queues optimization after `wp_generate_attachment_metadata`.
- Supports JPEG, PNG and WebP uploads.
- Generates AVIF/WebP sidecars for originals and WordPress-generated image sizes, subject to available GD/Imagick support.
- Preserves original media files.
- Adds modern-format sources to supported attachment/content image markup using `<picture>`.
- Provides a Media → Media Optimizer administrative screen and retry action.
- Uses WP-Cron for asynchronous processing.
- Does not perform historical bulk conversion.

## 2. BIGSHOR WIKI Admin UI — 1.5.0

Administrative interface companion plugin. Baseline version 1.5.0 in the consolidated v34.10.0 release.

A verified file-by-file feature breakdown has not yet been recorded in this inventory. Inspect its source and readme before documenting individual screens, settings or capabilities.

## 3. BIGSHOR WIKI Article Studio — 1.4.0

Article creation/editorial companion plugin. Baseline version 1.4.0 in the consolidated v34.10.0 release.

A verified file-by-file feature breakdown has not yet been recorded in this inventory. Inspect its source and readme before documenting individual editor workflows, controls or capabilities.

## Version history

| Consolidated milestone | Core | Admin UI | Article Studio | Notes |
|---|---:|---:|---:|---|
| Earlier baseline | 34.5.0 | 1.5.0 | 1.4.0 | Earlier project baseline |
| Role permissions | 34.6.0 | 1.5.0 | 1.4.0 | Core role/permission work |
| Account system | 34.7.0 | 1.5.0 | 1.4.0 | Core account-system work |
| Media optimizer branch | 34.8.0 | 1.5.0 | 1.4.0 | Phase70 media optimizer developed in this branch |
| Platform upgrade | 34.9.0 | 1.5.0 | 1.4.0 | Account/role registration and Phase69 image delivery |
| Consolidated release | 34.10.0 | 1.5.0 | 1.4.0 | Phase70 integrated into canonical Core base |

## Installation and update checklist

1. Back up files and database.
2. Update BIGSHOR WIKI Core.
3. Update companion plugins (Admin UI and Article Studio).
4. Confirm compatible parent and child theme versions.
5. Run database and migration checks if included in the release.
6. Test login, registration, role applications, article creation/editing, media uploads, admin screens and front-end rendering.
7. Review PHP logs, WordPress debug logs, scheduled events and browser console errors.

Follow release-specific installation notes and actual package dependency declarations; do not assume every component must always be updated independently or in a fixed order.

## Verification limits

The v34.10.0 package records state:
- 89 PHP files passed PHP syntax lint.
- All five component ZIP archives passed ZIP integrity checks.
- No live WordPress installation/deployment test was performed.

These checks establish package and syntax integrity only. They do not establish complete feature parity, security certification, accessibility conformance, production-load performance or successful integration with a particular hosting environment.

## Outstanding capabilities requiring explicit verification

The project backlog identifies these areas; do not describe them as complete without tests and evidence:

- Advanced MediaWiki parser, nested templates and parser functions.
- Full deletion discussions, undelete and revision suppression.
- Account/IP blocking and comprehensive moderation.
- Event-driven notifications and watchlist change feeds.
- Advanced search, disambiguation and namespace management.
- Translation editor and multilingual workflows.
- Full API authentication, bot framework and data export.
- Media file history, duplicate detection and rights moderation.
- Automated security, accessibility and performance testing.

## Maintenance policy

For every future plugin release, record exact plugin name/slug/version, dependencies and compatible Core/theme versions, new/changed/fixed/removed functionality, migrations and rollback instructions, API/hooks/capabilities/settings changes, test evidence and known limitations.
