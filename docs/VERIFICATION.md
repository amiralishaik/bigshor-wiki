# BIGSHOR WIKI Verification and Testing Status

## v34.10.0 recorded checks

| Check | Recorded result | What it establishes |
|---|---|---|
| PHP syntax lint | 89 PHP files; zero syntax errors | PHP source parses successfully |
| ZIP integrity | All five component ZIPs passed | Archives are structurally readable |
| Live WordPress deployment | Not performed | Runtime integration remains unverified |

## Not established by these checks

The recorded checks do not establish:
- Full MediaWiki feature parity.
- Security certification or penetration-test completion.
- Accessibility conformance.
- Performance under production traffic.
- Compatibility with every WordPress/PHP version or hosting provider.
- Correct behavior with every theme/plugin combination.
- Successful CDN, cache or image-processing integration.

## Recommended test areas

### Themes
- Responsive layouts and navigation at desktop/tablet/mobile sizes.
- Parent/child theme compatibility.
- Template rendering and account page indexing behavior.
- Metadata and structured data.
- Image sizing, responsive sources and fallback behavior.

### Plugins
- Activation/deactivation and dependency handling.
- Role and capability enforcement.
- Login, registration, role applications and password reset.
- Article creation/editing and editorial workflows.
- Parser/template behavior against a defined fixture suite.
- Media upload, optimization, retry and original fallback.
- Search, special pages, notifications and watchlists where implemented.
- API authentication, export and bot workflows where implemented.

### Operational checks
- PHP and WordPress debug logs.
- Browser console and network errors.
- WP-Cron event execution.
- Database migration and rollback.
- Accessibility and performance audits.

Record the test environment, WordPress/PHP versions, test cases, results and evidence for each release. Mark a feature complete only when its acceptance tests pass.
