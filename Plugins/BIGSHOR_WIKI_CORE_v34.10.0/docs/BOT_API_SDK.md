# BIGSHOR Wiki Bot API — v1

Base URL: `https://YOUR-WIKI.example/wp-json/bigshor/v1`

Authentication: send `Authorization: Bearer YOUR_TOKEN`. Create tokens in **WordPress Admin → Settings → BIGSHOR Advanced Platform**. Tokens are displayed once. Grant only the scopes required by the integration.

## Endpoints

| Method | Path | Scope | Behaviour |
|---|---|---|---|
| GET | `/bot/schema` | Public | Returns machine-readable endpoint and limit information |
| GET | `/bot/articles?page=1&per_page=25` | `articles:read` | Lists published articles |
| POST | `/bot/articles` | `articles:create` | Creates a draft (`title`, optional `content`) |
| PATCH | `/bot/articles/{id}` | `articles:update` | Updates a draft owned by the token creator |
| DELETE | `/bot/articles/{id}` | `articles:update` | Trashes an owned draft/pending article only; published articles are rejected |

Per-token limit: 600 requests per hour. Bot writes do not publish articles. Human editorial review remains required.

## JavaScript example

```js
const API = 'https://YOUR-WIKI.example/wp-json/bigshor/v1';
const TOKEN = process.env.BIGSHOR_WIKI_TOKEN;

async function wikiRequest(path, options = {}) {
  const response = await fetch(`${API}${path}`, {
    ...options,
    headers: {
      'Authorization': `Bearer ${TOKEN}`,
      'Content-Type': 'application/json',
      ...(options.headers || {})
    }
  });
  const body = await response.json();
  if (!response.ok) throw new Error(`${response.status}: ${JSON.stringify(body)}`);
  return body;
}

// Read published articles
const page = await wikiRequest('/bot/articles?page=1&per_page=25');

// Create a draft
const draft = await wikiRequest('/bot/articles', {
  method: 'POST',
  body: JSON.stringify({ title: 'Draft title', content: '<p>Draft content</p>' })
});
```

## PHP example

```php
<?php
$base = 'https://YOUR-WIKI.example/wp-json/bigshor/v1';
$token = getenv('BIGSHOR_WIKI_TOKEN');
$response = wp_remote_get($base . '/bot/articles?page=1&per_page=25', array(
    'headers' => array('Authorization' => 'Bearer ' . $token),
    'timeout' => 20,
));
if (is_wp_error($response)) {
    throw new RuntimeException($response->get_error_message());
}
$status = wp_remote_retrieve_response_code($response);
$body = json_decode(wp_remote_retrieve_body($response), true);
if ($status < 200 || $status >= 300) {
    throw new RuntimeException('BIGSHOR API error: ' . wp_json_encode($body));
}
```

## Operational and security guidance

- Store tokens in environment variables or a secrets manager; never commit them to source control or expose them in browser code.
- Use HTTPS only. Rotate/revoke a token immediately if it may have been exposed.
- Use separate tokens per integration and least-privilege scopes.
- Retry only idempotent reads automatically. A timed-out create request may have succeeded; check for the resulting draft before retrying.
- Respect HTTP 429 and back off. The current rate counter is per token, per hour.
- The API does not provide OAuth, webhooks, batch writes, automatic publishing, or a maintained language-specific SDK package in this release. This document provides starter client patterns, not a formally versioned SDK distribution.
