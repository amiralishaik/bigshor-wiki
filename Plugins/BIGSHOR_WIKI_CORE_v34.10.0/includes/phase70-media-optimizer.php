<?php
/**
 * Phase 70 — automatic upload-time responsive image optimisation.
 * Creates AVIF/WebP sidecars asynchronously; never replaces the source file.
 */
if (!defined('ABSPATH')) { exit; }

if (!class_exists('BSW_Phase70_Media_Optimizer')) {
final class BSW_Phase70_Media_Optimizer {
    const META_KEY = '_bsw_media_optimizer_variants';
    const STATUS_KEY = '_bsw_media_optimizer_status';
    const CRON_HOOK = 'bsw_media_optimizer_process_attachment';

    public static function boot() {
        add_filter('wp_generate_attachment_metadata', array(__CLASS__, 'queue_after_upload'), 30, 3);
        add_action(self::CRON_HOOK, array(__CLASS__, 'process_attachment'), 10, 1);
        add_filter('wp_get_attachment_image', array(__CLASS__, 'picture_markup'), 30, 5);
        add_filter('wp_content_img_tag', array(__CLASS__, 'content_picture_markup'), 30, 3);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_post_bsw_media_optimizer_retry', array(__CLASS__, 'retry_failed'));
    }

    /** Queue work after WordPress has generated its normal image sizes. */
    public static function queue_after_upload($metadata, $attachment_id, $context = '') {
        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true)) return $metadata;
        if (wp_is_attachment($attachment_id) && !wp_attachment_is_image($attachment_id)) return $metadata;
        $status = get_post_meta($attachment_id, self::STATUS_KEY, true);
        if (in_array($status, array('queued', 'processing', 'complete'), true)) return $metadata;
        update_post_meta($attachment_id, self::STATUS_KEY, 'queued');
        if (!wp_next_scheduled(self::CRON_HOOK, array((int) $attachment_id))) {
            wp_schedule_single_event(time() + 15, self::CRON_HOOK, array((int) $attachment_id));
        }
        return $metadata;
    }

    /** Process original plus all WordPress-generated sizes. */
    public static function process_attachment($attachment_id) {
        $attachment_id = absint($attachment_id);
        if (!$attachment_id || get_post_type($attachment_id) !== 'attachment') return;
        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true)) return;
        $original = get_attached_file($attachment_id);
        if (!$original || !is_file($original) || !is_readable($original)) {
            update_post_meta($attachment_id, self::STATUS_KEY, 'failed: original file unavailable');
            return;
        }
        update_post_meta($attachment_id, self::STATUS_KEY, 'processing');
        $metadata = wp_get_attachment_metadata($attachment_id);
        $files = array($original);
        if (is_array($metadata) && !empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $dir = dirname($original);
            foreach ($metadata['sizes'] as $size) {
                if (!empty($size['file'])) {
                    $candidate = $dir . '/' . wp_basename($size['file']);
                    if (is_file($candidate)) $files[] = $candidate;
                }
            }
        }
        $files = array_values(array_unique($files));
        $variants = array();
        $created = 0;
        foreach ($files as $source) {
            $entry = self::convert_file($source);
            if (!empty($entry)) {
                $variants[wp_basename($source)] = $entry;
                $created += count($entry);
            }
        }
        if ($created > 0) {
            update_post_meta($attachment_id, self::META_KEY, $variants);
            update_post_meta($attachment_id, self::STATUS_KEY, 'complete');
            update_post_meta($attachment_id, '_bsw_media_optimizer_variant_count', $created);
        } else {
            update_post_meta($attachment_id, self::STATUS_KEY, 'unsupported: AVIF/WebP encoders unavailable or conversion failed');
        }
    }

    /** Save sidecar formats beside each source rendition; original remains untouched. */
    private static function convert_file($source) {
        $result = array();
        $editor = wp_get_image_editor($source);
        if (is_wp_error($editor)) return $result;
        $base = preg_replace('/\.[^.]+$/', '', $source);
        $source_mime = wp_check_filetype($source);
        $formats = ($source_mime['type'] === 'image/webp')
            ? array('image/avif' => 'avif')
            : array('image/avif' => 'avif', 'image/webp' => 'webp');
        foreach ($formats as $mime => $extension) {
            if (!method_exists($editor, 'supports_mime_type') || !$editor->supports_mime_type($mime)) continue;
            $target = $base . '.' . $extension;
            if (is_file($target) && filesize($target) > 0) {
                $result[$extension] = array('path' => wp_basename($target), 'mime' => $mime, 'bytes' => (int) filesize($target));
                continue;
            }
            $fresh = wp_get_image_editor($source);
            if (is_wp_error($fresh)) continue;
            if (method_exists($fresh, 'set_quality')) {
                $quality = ($extension === 'avif') ? 55 : 78;
                $fresh->set_quality(apply_filters('bsw_media_optimizer_quality', $quality, $extension, $source));
            }
            $saved = $fresh->save($target, $mime);
            if (!is_wp_error($saved) && !empty($saved['path']) && is_file($saved['path'])) {
                $result[$extension] = array('path' => wp_basename($saved['path']), 'mime' => $mime, 'bytes' => (int) filesize($saved['path']));
            }
        }
        return $result;
    }

    /** Add <picture> sources to WordPress-generated attachment image markup. */
    public static function picture_markup($html, $attachment_id, $size, $icon, $attr) {
        if (is_admin() || !$html || strpos($html, '<picture') !== false) return $html;
        return self::wrap_img($html, $attachment_id);
    }

    /** Also cover classic-editor/content images carrying WordPress attachment classes. */
    public static function content_picture_markup($html, $attachment_id, $context) {
        if (is_admin() || !$html || strpos($html, '<picture') !== false) return $html;
        return self::wrap_img($html, $attachment_id);
    }

    private static function wrap_img($html, $attachment_id) {
        $variants = get_post_meta($attachment_id, self::META_KEY, true);
        if (!is_array($variants) || !$variants) return $html;
        if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $html, $match)) return $html;
        $src_path = wp_parse_url(html_entity_decode($match[1]), PHP_URL_PATH);
        $basename = $src_path ? wp_basename($src_path) : '';
        if (!$basename || empty($variants[$basename])) return $html;
        $sources = $variants[$basename];
        $picture = '<picture>';
        $sizes = '';
        if (preg_match("/\\bsizes=[\"']([^\"']*)[\"']/i", $html, $sizes_match)) $sizes = $sizes_match[1];
        foreach (array('avif', 'webp') as $format) {
            if (empty($sources[$format]['path'])) continue;
            $srcset = self::format_srcset($html, $variants, $format);
            if (!$srcset) {
                $url = self::url_for_sidecar($match[1], $basename, $sources[$format]['path']);
                $srcset = $url ? esc_url_raw($url) : '';
            }
            if ($srcset) $picture .= '<source type="image/' . esc_attr($format) . '" srcset="' . esc_attr($srcset) . '"' . ($sizes ? ' sizes="' . esc_attr($sizes) . '"' : '') . '>';
        }
        if ($picture === '<picture>') return $html;
        return $picture . $html . '</picture>';
    }


    /** Build a modern-format srcset matching WordPress's responsive source candidates. */
    private static function format_srcset($html, $variants, $format) {
        if (!preg_match("/\\bsrcset=[\"']([^\"']+)[\"']/i", $html, $match)) return '';
        $items = array();
        foreach (explode(',', $match[1]) as $candidate) {
            $parts = preg_split('/\s+/', trim($candidate));
            if (empty($parts[0])) continue;
            $path = wp_parse_url(html_entity_decode($parts[0]), PHP_URL_PATH);
            $base = $path ? wp_basename($path) : '';
            if (!$base || empty($variants[$base][$format]['path'])) continue;
            $url = self::url_for_sidecar($parts[0], $base, $variants[$base][$format]['path']);
            if ($url) $items[] = esc_url_raw($url) . (isset($parts[1]) ? ' ' . sanitize_text_field($parts[1]) : '');
        }
        return implode(', ', $items);
    }

    private static function url_for_sidecar($original_url, $original_basename, $sidecar_basename) {
        // Preserve the original host, CDN hostname, path, query string and fragment.
        $decoded = html_entity_decode($original_url);
        $replacement = preg_replace('~' . preg_quote($original_basename, '~') . '(?=([?#].*)?$)~', $sidecar_basename, $decoded, 1);
        return ($replacement && $replacement !== $decoded) ? $replacement : '';
    }

    public static function admin_menu() {
        add_submenu_page('upload.php', 'BIGSHOR Media Optimizer', 'Media Optimizer', 'manage_options', 'bsw-media-optimizer', array(__CLASS__, 'render_admin'));
    }

    public static function render_admin() {
        if (!current_user_can('manage_options')) return;
        $counts = array('complete' => 0, 'queued' => 0, 'processing' => 0, 'failed' => 0, 'unsupported' => 0);
        $ids = get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => 500, 'fields' => 'ids', 'meta_key' => self::STATUS_KEY));
        foreach ($ids as $id) {
            $status = (string) get_post_meta($id, self::STATUS_KEY, true);
            if (strpos($status, 'failed') === 0) $counts['failed']++;
            elseif (strpos($status, 'unsupported') === 0) $counts['unsupported']++;
            elseif (isset($counts[$status])) $counts[$status]++;
        }
        echo '<div class="wrap"><h1>BIGSHOR Media Optimizer</h1><p>New JPEG and PNG uploads are queued for AVIF/WebP generation. Original files are retained and remain the fallback. Processing requires server-side AVIF/WebP support in GD or Imagick and a functioning WordPress cron runner.</p>';
        echo '<table class="widefat striped" style="max-width:760px"><tbody>';
        foreach ($counts as $label => $count) echo '<tr><th>' . esc_html(ucfirst($label)) . '</th><td>' . esc_html(number_format_i18n($count)) . '</td></tr>';
        echo '</tbody></table><h2>Recent optimised uploads</h2><table class="widefat striped" style="max-width:1000px"><thead><tr><th>Image</th><th>Status</th><th>Variants</th><th>Action</th></tr></thead><tbody>';
        $recent = get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => 30, 'orderby' => 'date', 'order' => 'DESC', 'meta_key' => self::STATUS_KEY));
        if (!$recent) echo '<tr><td colspan="4">No uploads have entered the optimiser yet.</td></tr>';
        foreach ($recent as $attachment) {
            $id = $attachment->ID;
            $status = (string) get_post_meta($id, self::STATUS_KEY, true);
            $variants = get_post_meta($id, self::META_KEY, true);
            $count = (int) get_post_meta($id, '_bsw_media_optimizer_variant_count', true);
            $retry = wp_nonce_url(add_query_arg(array('action' => 'bsw_media_optimizer_retry', 'attachment_id' => $id), admin_url('admin-post.php')), 'bsw_media_optimizer_retry');
            echo '<tr><td>' . esc_html(get_the_title($id)) . '</td><td>' . esc_html($status) . '</td><td>' . esc_html(number_format_i18n($count)) . '</td><td>';
            if (strpos($status, 'failed') === 0 || strpos($status, 'unsupported') === 0) echo '<a href="' . esc_url($retry) . '">Retry</a>';
            else echo '—';
            echo '</td></tr>';
        }
        echo '</tbody></table><p>Existing Media Library items are preserved. This release queues new uploads automatically; historical bulk regeneration is intentionally separate to avoid unexpected server load.</p></div>';
    }

    public static function retry_failed() {
        if (!current_user_can('manage_options')) wp_die('You are not allowed to perform this action.');
        check_admin_referer('bsw_media_optimizer_retry');
        $id = absint($_GET['attachment_id'] ?? 0);
        if ($id) {
            delete_post_meta($id, self::STATUS_KEY);
            self::queue_after_upload(wp_get_attachment_metadata($id), $id, 'retry');
        }
        wp_safe_redirect(admin_url('upload.php?page=bsw-media-optimizer'));
        exit;
    }
}
BSW_Phase70_Media_Optimizer::boot();
}
