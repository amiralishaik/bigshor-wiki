# BIGSHOR WIKI Parent Theme

**Documented version:** 23.5.0  
**Baseline release:** BIGSHOR WIKI v34.10.0 consolidated release

## Responsibility

The parent theme provides the shared BIGSHOR WIKI WordPress presentation foundation, templates and styling. Site-specific customizations should normally be maintained in the child theme rather than by editing parent-theme files directly.

## Recorded capabilities

Release notes for the v34.9.0 platform upgrade, retained in v34.10.0, record:
- Responsive and retina-aware brand images.
- Registered image sizes.
- Asynchronous image decoding where applicable.
- Deferred theme JavaScript.
- Open Graph and Twitter metadata.
- Primary-image structured data.
- Account-related pages set to noindex and excluded from sitemaps.

## Compatibility and requirements

The child theme is intended to run on this parent theme. Exact WordPress/PHP minimums and any additional plugin dependencies must be read from the actual package metadata; they are not specified in this document.

## Update checklist

1. Back up the site and database.
2. Review release notes and package metadata.
3. Update the parent theme before the child theme when both are being updated.
4. Test desktop, tablet and mobile layouts.
5. Verify navigation, account pages, metadata and image presentation.
6. Review PHP and browser-console errors.

## Verification caveat

The v34.10.0 consolidated package passed ZIP integrity checks. Package-level PHP lint does not validate front-end theme behavior. No live WordPress deployment test is recorded.
