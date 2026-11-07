# BIGSHOR WIKI

Source repository for the BIGSHOR WIKI WordPress platform.

## Release baseline

The current supplied release baseline is **v34.10.0**:

| Component | Version |
|---|---:|
| BIGSHOR WIKI Core | 34.10.0 |
| Parent Theme | 23.5.0 |
| Child Theme | 23.6.0 |
| Admin UI | 1.5.0 |
| Article Studio | 1.4.0 |

## Intended source layout

```
plugins/
  bigshor-wiki-core/
  bigshor-wiki-admin-ui/
  bigshor-wiki-article-studio/
themes/
  bigshor-wiki-parent/
  bigshor-wiki-child/
docs/
tests/
```

See [source import status](docs/SOURCE-IMPORT-STATUS.md) and [v34.10.0 package manifest](docs/SOURCE-MANIFEST-v34.10.0.md).

**Important:** This repository is being populated from the supplied release packages. A component is not considered source-imported until its actual PHP/CSS/JS/template files are committed. Do not treat a manifest or release ZIP inventory as the source code itself.
