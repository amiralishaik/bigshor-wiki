# BIGSHOR WIKI Theme and Plugin Installation / Updates

This guide records a conservative deployment checklist for the components in the v34.10.0 consolidated release. Always follow any more specific instructions shipped with a component package.

## Before deployment

1. Confirm the target environment and take a complete file and database backup.
2. Review the release notes and component versions.
3. Confirm WordPress/PHP requirements from the actual package metadata.
4. Deploy to staging first where possible.

## Components in the v34.10.0 baseline

| Component | Version |
|---|---:|
| BIGSHOR WIKI Core | 34.10.0 |
| BIGSHOR WIKI Parent Theme | 23.5.0 |
| BIGSHOR WIKI Child Theme | 23.6.0 |
| BIGSHOR WIKI Admin UI | 1.5.0 |
| BIGSHOR WIKI Article Studio | 1.4.0 |

## Update considerations

- The child theme is designed to run with the parent theme.
- Core provides platform functionality separately from theme presentation.
- Review plugin dependency declarations and release-specific upgrade instructions before choosing the exact plugin update order.
- Do not overwrite custom child-theme work without reviewing the changes.
- Run database migrations only when explicitly provided by the release.

## Post-deployment smoke tests

- Site front page and representative article pages.
- Desktop, tablet and mobile navigation/layout.
- Login, registration, role application and password reset.
- Article creation/editing and permissions.
- Media upload and image delivery.
- Admin UI screens and settings.
- SEO/social metadata and sitemap behavior for account pages.
- PHP logs, WordPress debug logs, scheduled tasks and browser console.

## Rollback

If a deployment fails, use the verified backup and the previous known-good component packages. Restore database and files as a coordinated set when database changes have occurred. Record the cause and corrective action in the release history.
