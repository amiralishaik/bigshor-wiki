# BIGSHOR WIKI — Themes

**Documentation baseline:** Consolidated release v34.10.0  
**Repository:** `amiralishaik/bigshor-wiki`  
**Default branch:** `main`

> This inventory reflects the project release records available at the time of writing. It does not claim that the package has been deployed or tested on a live WordPress installation.

## Theme inventory

| Component | Baseline version | Role |
|---|---:|---|
| BIGSHOR WIKI Parent Theme | 23.5.0 | Shared theme foundation, templates, styling and front-end presentation |
| BIGSHOR WIKI Child Theme | 23.6.0 | Site-specific presentation and customization layer built on the parent theme |

## Parent Theme — 23.5.0

The v34.9.0 platform upgrade notes, retained in the v34.10.0 consolidated release, record these theme-level improvements:

- Responsive and retina-aware brand images.
- Registered image sizes for theme presentation.
- Asynchronous image decoding where applicable.
- Deferred loading of theme JavaScript.
- Open Graph and Twitter metadata.
- Primary-image structured data.
- Account-related pages configured for noindex and sitemap exclusion.

## Child Theme — 23.6.0

The child theme is the customization layer for site-specific presentation. Its baseline version in v34.10.0 is 23.6.0. A detailed file-by-file feature audit has not yet been recorded; do not infer additional capabilities from the version number alone.

## Dependencies and compatibility

- The child theme is intended to run with the BIGSHOR WIKI parent theme.
- The BIGSHOR WIKI Core plugin provides platform functionality; theme presentation is not a replacement for core features.
- Confirm exact WordPress and PHP minimum versions from package headers/readme files before deployment; they are not established in this inventory.

## Release history

| Release milestone | Parent | Child | Notes |
|---|---:|---:|---|
| Earlier baseline | 23.4.0 | 23.5.0 | Earlier package lineage |
| v34.9.0 platform upgrade | 23.5.0 | 23.6.0 | Theme/platform upgrade |
| v34.10.0 consolidated release | 23.5.0 | 23.6.0 | Versions retained; consolidated with Core v34.10.0 |

## Installation and update checklist

1. Back up the WordPress site and database.
2. Install or update the parent theme before updating the child theme.
3. Keep the child theme active for site-specific customizations.
4. Avoid editing the parent theme directly for site-specific changes.
5. Test desktop, tablet and mobile layouts, navigation, account pages, metadata and image delivery after updates.
6. Check matching Core and companion plugin versions before deployment.

## Verification status

For consolidated release v34.10.0, package integrity checks passed. Release-level PHP lint covered PHP source files, not browser rendering or WordPress theme behavior. No live WordPress deployment test is recorded.

## Maintenance policy

For each future theme release, record exact versions, new/changed/fixed/removed functionality, parent-child compatibility, required Core/plugin versions, upgrade and rollback notes, test evidence and known limitations.
