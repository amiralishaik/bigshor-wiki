<?php
/**
 * BIGSHOR Wiki Phase 7 completion: secure cross-wiki federation.
 * Peer discovery, bounded public article feeds and signed inbound exchange.
 * Imported content is always held for editorial review; nothing is auto-published.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('BSW_Phase60_Federation')) {
final class BSW_Phase60_Federation {
    const VERSION = '1.0.0';
    const OPTION = 'bsw_phase60_federation_peers';
    const NONCE = 'bsw_phase60_federation_action';
    const MAX_BODY = 262144;

    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_post_bsw_phase60_add_peer', array(__CLASS__, 'add_peer'));
        add_action('admin_post_bsw_phase60_remove_peer', array(__CLASS__, 'remove_peer'));
        add_action('admin_post_bsw_phase60_sync_peer', array(__CLASS__, 'sync_peer'));
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
    }

    public static function peers() {
        $peers = get_option(self::OPTION, array());
        return is_array($peers) ? $peers : array();
    }

    private static function encrypt_secret($secret) {
        if (!function_exists('openssl_encrypt')) return false;
        $iv = random_bytes(16);
        $key = hash('sha256', wp_salt('auth'), true);
        $cipher = openssl_encrypt($secret, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $cipher === false ? false : base64_encode($iv . $cipher);
    }

    private static function decrypt_secret($peer) {
        if (empty($peer['secret_box']) || !function_exists('openssl_decrypt')) return '';
        $packed = base64_decode((string) $peer['secret_box'], true);
        if ($packed === false || strlen($packed) < 33) return '';
        $iv = substr($packed, 0, 16); $cipher = substr($packed, 16);
        $key = hash('sha256', wp_salt('auth'), true);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return is_string($plain) ? $plain : '';
    }

    public static function routes() {
        register_rest_route('bigshor/v1', '/federation/manifest', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'manifest'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route('bigshor/v1', '/federation/articles', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'article_feed'),
            'permission_callback' => '__return_true',
            'args' => array('page' => array('default' => 1, 'sanitize_callback' => 'absint'), 'per_page' => array('default' => 20, 'sanitize_callback' => 'absint')),
        ));
        register_rest_route('bigshor/v1', '/federation/receive', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array(__CLASS__, 'receive'),
            'permission_callback' => '__return_true',
        ));
    }

    public static function manifest() {
        return rest_ensure_response(array(
            'protocol' => 'bigshor-federation',
            'protocol_version' => 1,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            'software' => 'BIGSHOR Wiki',
            'core_version' => defined('BSW_CORE_PLUGIN_VERSION') ? BSW_CORE_PLUGIN_VERSION : 'unknown',
            'endpoints' => array(
                'manifest' => rest_url('bigshor/v1/federation/manifest'),
                'articles' => rest_url('bigshor/v1/federation/articles'),
                'receive' => rest_url('bigshor/v1/federation/receive'),
            ),
            'exchange_policy' => 'public-read; authenticated-inbound; editorial-review-required',
        ));
    }

    public static function article_feed($request) {
        $page = max(1, absint($request->get_param('page')));
        $per_page = min(50, max(1, absint($request->get_param('per_page'))));
        $type = post_type_exists('wiki_article') ? 'wiki_article' : 'post';
        $query = new WP_Query(array(
            'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => $per_page,
            'paged' => $page, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => false,
        ));
        $items = array();
        foreach ($query->posts as $post) {
            $items[] = array(
                'external_id' => (string) $post->ID,
                'title' => get_the_title($post),
                'content' => (string) $post->post_content,
                'excerpt' => (string) get_the_excerpt($post),
                'url' => get_permalink($post),
                'modified_gmt' => mysql2date('c', $post->post_modified_gmt, false),
                'language' => (string) get_post_meta($post->ID, '_bsw_p7_language', true),
            );
        }
        return rest_ensure_response(array(
            'protocol' => 'bigshor-federation', 'protocol_version' => 1,
            'page' => $page, 'per_page' => $per_page, 'total' => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages, 'items' => $items,
        ));
    }

    private static function error($code, $message, $status) {
        return new WP_Error($code, $message, array('status' => $status));
    }

    public static function receive($request) {
        $raw = $request->get_body();
        if (!is_string($raw) || $raw === '' || strlen($raw) > self::MAX_BODY) return self::error('bsw_federation_body_limit', 'Request body is empty or exceeds 256 KB.', 413);
        $peer_id = sanitize_key((string) $request->get_header('x-bsw-peer'));
        $timestamp = (string) $request->get_header('x-bsw-timestamp');
        $nonce = sanitize_text_field((string) $request->get_header('x-bsw-nonce'));
        $signature = strtolower((string) $request->get_header('x-bsw-signature'));
        if (!$peer_id || !ctype_digit($timestamp) || !$nonce || strlen($nonce) > 100 || !preg_match('/^[a-f0-9]{64}$/', $signature)) return self::error('bsw_federation_auth', 'Federation authentication headers are missing or malformed.', 401);
        if (abs(time() - (int) $timestamp) > 300) return self::error('bsw_federation_expired', 'Federation request timestamp is outside the five-minute window.', 401);
        $peers = self::peers();
        $peer_secret = isset($peers[$peer_id]) ? self::decrypt_secret($peers[$peer_id]) : '';
        if ($peer_secret === '') return self::error('bsw_federation_unknown_peer', 'Peer is not registered for inbound exchange.', 403);
        $replay_key = 'bsw_fed_nonce_' . hash('sha256', $peer_id . '|' . $nonce);
        if (get_transient($replay_key)) return self::error('bsw_federation_replay', 'This federation request has already been processed.', 409);
        $message = $timestamp . "\n" . $nonce . "\n" . $raw;
        $expected = hash_hmac('sha256', $message, $peer_secret);
        if (!hash_equals($expected, $signature)) return self::error('bsw_federation_signature', 'Federation signature is invalid.', 401);
        $payload = json_decode($raw, true);
        if (!is_array($payload) || ($payload['protocol'] ?? '') !== 'bigshor-federation' || (int) ($payload['protocol_version'] ?? 0) !== 1 || empty($payload['article']) || !is_array($payload['article'])) return self::error('bsw_federation_payload', 'Unsupported federation payload.', 400);
        $article = $payload['article'];
        $external_id = sanitize_text_field((string) ($article['external_id'] ?? ''));
        $title = sanitize_text_field((string) ($article['title'] ?? ''));
        $content = (string) ($article['content'] ?? '');
        $source_url = esc_url_raw((string) ($article['url'] ?? ''), array('http', 'https'));
        if (!$external_id || !$title || $content === '' || strlen($content) > 200000 || !$source_url || !wp_http_validate_url($source_url)) return self::error('bsw_federation_article_invalid', 'Article fields are incomplete or invalid.', 422);
        $post_type = post_type_exists('wiki_article') ? 'wiki_article' : 'post';
        $existing = get_posts(array('post_type' => $post_type, 'post_status' => array('pending','draft','publish','private'), 'meta_key' => '_bsw_federation_source_key', 'meta_value' => $peer_id . ':' . $external_id, 'numberposts' => 1, 'fields' => 'ids'));
        $postarr = array(
            'post_type' => $post_type, 'post_status' => 'pending', 'post_title' => $title,
            'post_content' => wp_kses_post($content), 'post_excerpt' => sanitize_textarea_field((string) ($article['excerpt'] ?? '')),
        );
        if (!empty($existing[0])) $postarr['ID'] = absint($existing[0]);
        $post_id = wp_insert_post(wp_slash($postarr), true);
        if (is_wp_error($post_id)) return $post_id;
        update_post_meta($post_id, '_bsw_federation_source_key', $peer_id . ':' . $external_id);
        update_post_meta($post_id, '_bsw_federation_peer', $peer_id);
        update_post_meta($post_id, '_bsw_federation_source_url', $source_url);
        update_post_meta($post_id, '_bsw_federation_received_gmt', gmdate('c'));
        update_post_meta($post_id, '_bsw_federation_review_required', '1');
        $language = sanitize_text_field((string) ($article['language'] ?? ''));
        if ($language) update_post_meta($post_id, '_bsw_p7_language', $language);
        set_transient($replay_key, 1, 10 * MINUTE_IN_SECONDS);
        return rest_ensure_response(array('accepted' => true, 'status' => 'pending', 'review_required' => true, 'post_id' => (int) $post_id));
    }

    public static function admin_menu() {
        add_submenu_page('options-general.php', 'BIGSHOR Federation', 'BIGSHOR Federation', 'manage_options', 'bsw-phase60-federation', array(__CLASS__, 'settings_page'));
    }

    public static function add_peer() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('You are not allowed to manage federation peers.', 'bigshor-wiki'));
        check_admin_referer(self::NONCE);
        $id = sanitize_key(wp_unslash($_POST['peer_id'] ?? ''));
        $name = sanitize_text_field(wp_unslash($_POST['peer_name'] ?? ''));
        $url = esc_url_raw(wp_unslash($_POST['peer_url'] ?? ''), array('https'));
        $secret = trim((string) wp_unslash($_POST['peer_secret'] ?? ''));
        if (!$id || !$name || !$url || !wp_http_validate_url($url) || strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)) !== 'https' || strlen($secret) < 32 || strlen($secret) > 256) {
            self::redirect('invalid_peer');
        }
        if (!function_exists('openssl_encrypt')) self::redirect('encryption_unavailable');
        $secret_box = self::encrypt_secret($secret);
        if (!$secret_box) self::redirect('encryption_unavailable');
        $peers = self::peers();
        $peers[$id] = array('id' => $id, 'name' => $name, 'url' => untrailingslashit($url), 'secret_box' => $secret_box, 'added_gmt' => gmdate('c'), 'last_sync_gmt' => '', 'last_status' => 'not_checked');
        update_option(self::OPTION, $peers, false);
        self::redirect('peer_saved');
    }

    public static function remove_peer() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('You are not allowed to manage federation peers.', 'bigshor-wiki'));
        check_admin_referer(self::NONCE);
        $id = sanitize_key(wp_unslash($_POST['peer_id'] ?? ''));
        $peers = self::peers(); unset($peers[$id]); update_option(self::OPTION, $peers, false);
        self::redirect('peer_removed');
    }

    public static function sync_peer() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('You are not allowed to sync federation peers.', 'bigshor-wiki'));
        check_admin_referer(self::NONCE);
        $id = sanitize_key(wp_unslash($_POST['peer_id'] ?? ''));
        $peers = self::peers();
        if (empty($peers[$id]['url'])) self::redirect('peer_missing');
        $base = untrailingslashit($peers[$id]['url']);
        $manifest_url = $base . '/wp-json/bigshor/v1/federation/manifest';
        $manifest_response = wp_safe_remote_get($manifest_url, array('timeout' => 12, 'redirection' => 0, 'reject_unsafe_urls' => true, 'limit_response_size' => 32768, 'headers' => array('Accept' => 'application/json')));
        if (is_wp_error($manifest_response) || wp_remote_retrieve_response_code($manifest_response) !== 200) {
            $peers[$id]['last_status'] = 'manifest_failed'; $peers[$id]['last_sync_gmt'] = gmdate('c'); update_option(self::OPTION, $peers, false); self::redirect('sync_failed');
        }
        $manifest = json_decode(wp_remote_retrieve_body($manifest_response), true);
        if (!is_array($manifest) || ($manifest['protocol'] ?? '') !== 'bigshor-federation' || (int) ($manifest['protocol_version'] ?? 0) !== 1) {
            $peers[$id]['last_status'] = 'protocol_mismatch'; $peers[$id]['last_sync_gmt'] = gmdate('c'); update_option(self::OPTION, $peers, false); self::redirect('sync_failed');
        }
        $feed_url = $base . '/wp-json/bigshor/v1/federation/articles?per_page=25&page=1';
        $feed_response = wp_safe_remote_get($feed_url, array('timeout' => 15, 'redirection' => 0, 'reject_unsafe_urls' => true, 'limit_response_size' => 524288, 'headers' => array('Accept' => 'application/json')));
        if (is_wp_error($feed_response) || wp_remote_retrieve_response_code($feed_response) !== 200) {
            $peers[$id]['last_status'] = 'feed_failed'; $peers[$id]['last_sync_gmt'] = gmdate('c'); update_option(self::OPTION, $peers, false); self::redirect('sync_failed');
        }
        $feed = json_decode(wp_remote_retrieve_body($feed_response), true);
        if (!is_array($feed) || ($feed['protocol'] ?? '') !== 'bigshor-federation' || !is_array($feed['items'] ?? null)) {
            $peers[$id]['last_status'] = 'feed_invalid'; $peers[$id]['last_sync_gmt'] = gmdate('c'); update_option(self::OPTION, $peers, false); self::redirect('sync_failed');
        }
        $imported = 0; $skipped = 0;
        foreach (array_slice($feed['items'], 0, 25) as $article) {
            if (!is_array($article)) { $skipped++; continue; }
            $external = sanitize_text_field((string) ($article['external_id'] ?? ''));
            $title = sanitize_text_field((string) ($article['title'] ?? ''));
            $content = (string) ($article['content'] ?? '');
            $source = esc_url_raw((string) ($article['url'] ?? ''), array('http','https'));
            if (!$external || !$title || !$content || strlen($content) > 200000 || !$source || !wp_http_validate_url($source)) { $skipped++; continue; }
            $type = post_type_exists('wiki_article') ? 'wiki_article' : 'post';
            $key = $id . ':' . $external;
            $existing = get_posts(array('post_type' => $type, 'post_status' => array('pending','draft','publish','private'), 'meta_key' => '_bsw_federation_source_key', 'meta_value' => $key, 'numberposts' => 1, 'fields' => 'ids'));
            $postarr = array('post_type' => $type, 'post_status' => 'pending', 'post_title' => $title, 'post_content' => wp_kses_post($content), 'post_excerpt' => sanitize_textarea_field((string) ($article['excerpt'] ?? '')));
            if (!empty($existing[0])) $postarr['ID'] = absint($existing[0]);
            $new_id = wp_insert_post(wp_slash($postarr), true);
            if (is_wp_error($new_id)) { $skipped++; continue; }
            update_post_meta($new_id, '_bsw_federation_source_key', $key);
            update_post_meta($new_id, '_bsw_federation_peer', $id);
            update_post_meta($new_id, '_bsw_federation_source_url', $source);
            update_post_meta($new_id, '_bsw_federation_received_gmt', gmdate('c'));
            update_post_meta($new_id, '_bsw_federation_review_required', '1');
            $lang = sanitize_text_field((string) ($article['language'] ?? '')); if ($lang) update_post_meta($new_id, '_bsw_p7_language', $lang);
            $imported++;
        }
        $peers[$id]['last_status'] = 'ok'; $peers[$id]['last_sync_gmt'] = gmdate('c'); $peers[$id]['last_imported'] = $imported; $peers[$id]['last_skipped'] = $skipped;
        update_option(self::OPTION, $peers, false);
        self::redirect('sync_done_' . $imported . '_' . $skipped);
    }

    private static function redirect($status) {
        wp_safe_redirect(add_query_arg(array('page' => 'bsw-phase60-federation', 'bsw_fed_status' => rawurlencode($status)), admin_url('options-general.php')));
        exit;
    }

    public static function admin_notice() {
        if (!is_admin() || !current_user_can('manage_options') || empty($_GET['bsw_fed_status'])) return;
        $status = sanitize_text_field(wp_unslash($_GET['bsw_fed_status']));
        $message = 'Federation operation completed.';
        if ($status === 'invalid_peer') $message = 'Peer details are invalid. Use an HTTPS URL and a shared secret of at least 32 characters.';
        elseif ($status === 'peer_saved') $message = 'Federation peer saved. The shared secret is encrypted using the WordPress authentication salt.';
        elseif ($status === 'encryption_unavailable') $message = 'Peer was not saved because OpenSSL encryption is unavailable on this server.';
        elseif ($status === 'peer_removed') $message = 'Federation peer removed.';
        elseif ($status === 'sync_failed') $message = 'Peer sync failed. Check the peer URL, protocol version and remote availability.';
        elseif (strpos($status, 'sync_done_') === 0) { $parts = explode('_', substr($status, 10)); $message = 'Peer sync completed. Imported to review: ' . absint($parts[0] ?? 0) . '; skipped: ' . absint($parts[1] ?? 0) . '.'; }
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $peers = self::peers();
        echo '<div class="wrap"><h1>BIGSHOR Cross-Wiki Federation</h1><p>Connect independently operated BIGSHOR-compatible wikis. Peer feeds are read-only. Imported articles are always saved as Pending Review and must be checked before publication.</p>';
        echo '<h2>Registered peers</h2>';
        if (!$peers) echo '<p>No federation peers have been registered.</p>';
        foreach ($peers as $id => $peer) {
            echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:14px;margin:12px 0;max-width:850px"><strong>' . esc_html($peer['name'] ?? $id) . '</strong> <code>' . esc_html($id) . '</code><br><a href="' . esc_url($peer['url'] ?? '') . '" target="_blank" rel="noopener noreferrer">' . esc_html($peer['url'] ?? '') . '</a><p>Last sync: ' . esc_html($peer['last_sync_gmt'] ?: 'Never') . ' | Status: ' . esc_html($peer['last_status'] ?: 'Not checked') . ' | Imported: ' . absint($peer['last_imported'] ?? 0) . ' | Skipped: ' . absint($peer['last_skipped'] ?? 0) . '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:8px">'; wp_nonce_field(self::NONCE); echo '<input type="hidden" name="action" value="bsw_phase60_sync_peer"><input type="hidden" name="peer_id" value="' . esc_attr($id) . '"><button class="button button-primary">Discover & import updates</button></form>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block">'; wp_nonce_field(self::NONCE); echo '<input type="hidden" name="action" value="bsw_phase60_remove_peer"><input type="hidden" name="peer_id" value="' . esc_attr($id) . '"><button class="button" onclick="return confirm(\'Remove this federation peer?\')">Remove peer</button></form></div>';
        }
        echo '<h2>Add a federation peer</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:650px;background:#fff;border:1px solid #ccd0d4;padding:18px">'; wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="bsw_phase60_add_peer"><p><label>Peer ID<br><input class="regular-text" name="peer_id" required pattern="[a-z0-9_-]+" maxlength="64"></label></p><p><label>Display name<br><input class="regular-text" name="peer_name" required maxlength="120"></label></p><p><label>Peer base URL (HTTPS)<br><input class="regular-text" type="url" name="peer_url" required placeholder="https://wiki.example.org"></label></p><p><label>Shared secret (minimum 32 characters)<br><input class="large-text" type="password" name="peer_secret" required minlength="32" maxlength="256" autocomplete="new-password"></label></p><p class="description">Configure the same peer ID and secret on the remote wiki to authorise signed inbound article exchange. The secret is encrypted in the local WordPress options table using the site authentication salt.</p><p><button class="button button-primary">Add peer</button></p></form>';
        echo '<h2>Federation endpoints</h2><ul><li><code>' . esc_html(rest_url('bigshor/v1/federation/manifest')) . '</code> — protocol discovery</li><li><code>' . esc_html(rest_url('bigshor/v1/federation/articles')) . '</code> — public published article feed</li><li><code>' . esc_html(rest_url('bigshor/v1/federation/receive')) . '</code> — signed inbound exchange</li></ul>';
        echo '<p><strong>Security:</strong> Inbound requests use HMAC-SHA256 signatures, five-minute timestamps and replay protection. Requests are size-limited. Remote sync uses safe HTTPS requests with redirects disabled. Federation does not grant remote peers publishing privileges.</p></div>';
    }
}
BSW_Phase60_Federation::init();
}
