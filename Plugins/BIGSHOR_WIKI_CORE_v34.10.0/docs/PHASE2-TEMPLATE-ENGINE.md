# Phase 2 — Advanced template and parser engine

## Template authoring

Create a **Wiki Template** in the WordPress administration area. The template title is its invocation name; the editor body is wikitext. Only published templates are expanded, and editing/publishing is restricted to administrators.

Example template body:

```text
'''{{{name|Unnamed}}}''' is a {{{occupation|person}}}.
```

Invocation:

```text
{{Person summary|name=Amir Ali Shaik|occupation=performer}}
```

Positional parameters use `{{{1}}}`, `{{{2}}}`, and so on. Defaults use `{{{parameter|default text}}}`. Template calls may be nested.

## Supported parser functions

| Function | Example | Behaviour |
|---|---|---|
| `#if` | `{{#if:value|yes|no}}` | Tests whether the condition is empty |
| `#ifeq` | `{{#ifeq:a|a|yes|no}}` | String comparison |
| `#ifexist` | `{{#ifexist:Article|yes|no}}` | Checks for a published wiki article |
| `#switch` | `{{#switch:a|a=Alpha|default=Other}}` | Matches a case or default |
| `#expr` | `{{#expr:(2+3)*4}}` | Bounded arithmetic parser |
| `#len` | `{{#len:BIGSHOR}}` | String length |
| `#uc`, `#lc` | `{{#uc:wiki}}` | Upper/lower case |
| `#sub` | `{{#sub:encyclopedia|0|4}}` | Substring by start and optional length |
| `#replace` | `{{#replace:BIGSHOR Wiki|Wiki|WIKI}}` | Literal string replacement |

Magic words: `SITENAME`, `CURRENTYEAR`, `CURRENTMONTH`, `CURRENTDAY`, `CURRENTMONTHNAME`, `PAGENAME`, `FULLPAGENAME`.

Escapes: `{{!}}` produces a literal pipe and `{{=}}` produces a literal equals sign.

## Safety model

The engine uses a balanced-brace scanner and strict depth/operation/source-size budgets. It does not use `eval`, execute PHP from templates, or expose templates as public posts. `#expr` accepts only numeric arithmetic tokens and parentheses. Unknown template calls remain visible in the source output rather than silently disappearing.

## Compatibility boundary

This implementation is not full MediaWiki parser parity. It does not provide Scribunto/Lua, arbitrary parser extensions, every magic word/function, language-specific aliases, full parser hook compatibility, or complete Wikimedia template semantics. Existing BIGSHOR wikitext parsing and infobox registry remain in place.
