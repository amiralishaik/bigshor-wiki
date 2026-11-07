<?php
/**
 * BIGSHOR Wiki Core — Phase 7: media and file management.
 * WordPress attachment library remains the canonical file store. Additive metadata only.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('BSW_Phase7_Media_Management')) {
final class BSW_Phase7_Media_Management {
    const VERSION = '31.2.0';
    const NONCE_ACTION = 'bsw7_save_media_metadata';
    const META_PREFIX = '_bsw7_media_';

    public static function init() {
        add_action('add_meta_boxes_attachment', array(__CLASS__, 'add_metabox'));
        add_action('add_attachment', array(__CLASS__, 'attachment_defaults'));
        add_action('edit_attachment', array(__CLASS__, 'save_metadata'));
        add_filter('manage_media_columns', array(__CLASS__, 'add_columns'));
        add_action('manage_media_custom_column', array(__CLASS__, 'render_column'), 10, 2);
        add_filter('wp_prepare_attachment_for_js', array(__CLASS__, 'attachment_js_metadata'), 10, 3);
        add_shortcode('bsw_media_gallery', array(__CLASS__, 'gallery_shortcode'));
        add_shortcode('bsw_media_file', array(__CLASS__, 'file_shortcode'));
        add_shortcode('bsw_media_download', array(__CLASS__, 'download_shortcode'));
        add_action('admin_notices', array(__CLASS__, 'rights_notice'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'));
    }

    public static function attachment_defaults($id) {
        if (!get_post_meta($id, '_bsw7_media_rights_status', true)) update_post_meta($id, '_bsw7_media_rights_status', 'needs_review');
        if (wp_attachment_is_image($id) && !get_post_meta($id, '_bsw7_media_alt_review', true)) update_post_meta($id, '_bsw7_media_alt_review', 'needs_review');
    }

    private static function fields() {
        return array(
            'creator' => 'Creator / rights holder',
            'source' => 'Original source URL',
            'licence' => 'Licence / rights statement',
            'credit' => 'Required attribution / credit',
            'permission' => 'Permission notes / evidence reference',
            'created' => 'Date created (YYYY-MM-DD)',
            'alt_review' => 'Alt text review status'
        );
    }

    private static function get_value($id, $key) {
        $value = get_post_meta($id, self::META_PREFIX . $key, true);
        if ($value !== '' && $value !== null) return $value;
        // Read legacy Phase 3 / legacy media metadata without destructive migration.
        $legacy = array('licence' => '_bsw_media_licence', 'credit' => '_bsw_media_credit', 'permission' => '_bsw_media_permission', 'creator' => '_bsw_media_creator', 'source' => '_bsw_media_source');
        if (isset($legacy[$key])) {
            $value = get_post_meta($id, $legacy[$key], true);
            if ($value !== '' && $value !== null) return $value;
            if ($key === 'credit') { $value = get_post_meta($id, '_bsw_media_attribution', true); if ($value !== '' && $value !== null) return $value; }
        }
        $commons = array('creator' => '_bsw_commons_creator', 'source' => '_bsw_commons_source', 'licence' => '_bsw_commons_license', 'credit' => '_bsw_commons_attribution');
        return isset($commons[$key]) ? get_post_meta($id, $commons[$key], true) : '';
    }

    public static function add_metabox($post) {
        add_meta_box('bsw7-media-management', 'BIGSHOR — Media details, rights & accessibility', array(__CLASS__, 'metabox_html'), 'attachment', 'normal', 'high');
    }

    public static function metabox_html($post) {
        if (!current_user_can('edit_post', $post->ID)) return;
        wp_nonce_field(self::NONCE_ACTION, 'bsw7_media_nonce');
        $alt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        echo '<p class="description">The WordPress Media Library remains the source of the original file. Complete these fields before using media in a published article. Rights status is editorial metadata, not a legal determination.</p>';
        if (wp_attachment_is_image($post->ID)) {
            echo '<p><label><strong>Alternative text (describe the information conveyed by the image)</strong><br><input class="widefat" name="bsw7_media_alt" value="'.esc_attr($alt).'" maxlength="1000"></label></p>';
        }
        foreach (self::fields() as $key => $label) {
            $value = self::get_value($post->ID, $key);
            if ($key === 'alt_review') {
                echo '<p><label><strong>'.esc_html($label).'</strong><br><select name="bsw7_media_alt_review"><option value="needs_review" '.selected($value, 'needs_review', false).'>Needs review</option><option value="reviewed" '.selected($value, 'reviewed', false).'>Reviewed</option><option value="not_applicable" '.selected($value, 'not_applicable', false).'>Not applicable</option></select></label></p>';
            } elseif ($key === 'permission') {
                echo '<p><label><strong>'.esc_html($label).'</strong><br><textarea class="widefat" rows="3" name="bsw7_media_permission">'.esc_textarea($value).'</textarea></label></p>';
            } else {
                $type = $key === 'source' ? 'url' : ($key === 'created' ? 'date' : 'text');
                echo '<p><label><strong>'.esc_html($label).'</strong><br><input class="widefat" type="'.esc_attr($type).'" name="bsw7_media_'.esc_attr($key).'" value="'.esc_attr($value).'"'.($key === 'source' ? ' placeholder="https://"' : '').'></label></p>';
            }
        }
        $status = get_post_meta($post->ID, '_bsw7_media_rights_status', true);
        if (!$status) $status = get_post_meta($post->ID, '_bsw_commons_rights_status', true);
        echo '<p><strong>Rights review:</strong> '.($status === 'verified' ? '<span style="color:#18753c">Verified by an authorised editor</span>' : '<span style="color:#9a6700">Pending review</span>').'</p>';
        if (current_user_can('manage_options')) {
            echo '<p><label><input type="checkbox" name="bsw7_media_verify" value="1"> Confirm that the creator, source and licence have been checked and the licence permits the intended use</label></p>';
            echo '<p><label><input type="checkbox" name="bsw7_media_mark_pending" value="1"> Return this item to rights review</label></p>';
        }
        echo '<p class="description">Only an authorised editor with media-review capability can mark rights as verified. A file upload or a populated licence field does not establish permission.</p>';
    }

    public static function save_metadata($id) {
        if (!isset($_POST['bsw7_media_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw7_media_nonce'])), self::NONCE_ACTION)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($id) || !current_user_can('edit_post', $id)) return;
        if (wp_attachment_is_image($id) && isset($_POST['bsw7_media_alt'])) update_post_meta($id, '_wp_attachment_image_alt', sanitize_text_field(wp_unslash($_POST['bsw7_media_alt'])));
        foreach (self::fields() as $key => $label) {
            $field = 'bsw7_media_' . $key;
            if (!isset($_POST[$field])) continue;
            $raw = wp_unslash($_POST[$field]);
            $value = $key === 'source' ? esc_url_raw($raw, array('http', 'https')) : sanitize_textarea_field($raw);
            if ($key === 'alt_review' && !in_array($value, array('needs_review', 'reviewed', 'not_applicable'), true)) $value = 'needs_review';
            update_post_meta($id, self::META_PREFIX . $key, $value);
            // Keep legacy fields in sync for existing BIGSHOR galleries and tools.
            $legacy = array('creator' => '_bsw_media_creator', 'source' => '_bsw_media_source', 'licence' => '_bsw_media_licence', 'credit' => '_bsw_media_credit', 'permission' => '_bsw_media_permission');
            if (isset($legacy[$key])) update_post_meta($id, $legacy[$key], $value);
            $commons = array('creator' => '_bsw_commons_creator', 'source' => '_bsw_commons_source', 'licence' => '_bsw_commons_license', 'credit' => '_bsw_commons_attribution');
            if (isset($commons[$key])) update_post_meta($id, $commons[$key], $value);
        }
        // Rights verification is deliberately restricted to administrators / authorised media reviewers.
        if (isset($_POST['bsw7_media_verify']) && current_user_can('manage_options')) {
            $complete = self::get_value($id, 'creator') && self::get_value($id, 'licence') && self::get_value($id, 'source');
            update_post_meta($id, '_bsw7_media_rights_status', $complete ? 'verified' : 'needs_review');
            update_post_meta($id, '_bsw_commons_rights_status', $complete ? 'verified' : 'needs_review');
        } elseif (current_user_can('manage_options') && isset($_POST['bsw7_media_mark_pending'])) {
            update_post_meta($id, '_bsw7_media_rights_status', 'needs_review');
            update_post_meta($id, '_bsw_commons_rights_status', 'needs_review');
        }
    }

    public static function add_columns($columns) {
        $columns['bsw7_rights'] = 'BIGSHOR rights';
        $columns['bsw7_alt'] = 'Alt text';
        return $columns;
    }

    public static function render_column($column, $id) {
        if ($column === 'bsw7_rights') {
            $status = get_post_meta($id, '_bsw7_media_rights_status', true);
            if (!$status) $status = get_post_meta($id, '_bsw_commons_rights_status', true);
            echo $status === 'verified' ? '<span style="color:#18753c">Verified</span>' : '<span style="color:#9a6700">Review needed</span>';
        } elseif ($column === 'bsw7_alt') {
            if (!wp_attachment_is_image($id)) { echo '—'; return; }
            echo get_post_meta($id, '_wp_attachment_image_alt', true) !== '' ? 'Present' : '<span style="color:#b32d2e">Missing</span>';
        }
    }

    public static function attachment_js_metadata($response, $attachment, $meta) {
        $response['bsw7_rights_status'] = get_post_meta($attachment->ID, '_bsw7_media_rights_status', true) ?: 'needs_review';
        $response['bsw7_creator'] = self::get_value($attachment->ID, 'creator');
        $response['bsw7_licence'] = self::get_value($attachment->ID, 'licence');
        return $response;
    }

    public static function rights_notice() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'attachment' || !isset($_GET['post']) || !current_user_can('upload_files')) return;
        $id = absint($_GET['post']);
        if ($id && get_post_meta($id, '_bsw7_media_rights_status', true) !== 'verified' && get_post_meta($id, '_bsw_commons_rights_status', true) !== 'verified') {
            echo '<div class="notice notice-warning"><p><strong>BIGSHOR Media:</strong> Rights review is pending. Confirm the original source, creator and licence before publishing or redistributing this file.</p></div>';
        }
    }

    public static function gallery_shortcode($atts) {
        $atts = shortcode_atts(array('limit' => 24, 'ids' => '', 'columns' => 3, 'size' => 'large'), $atts, 'bsw_media_gallery');
        $args = array('post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => min(100, max(1, absint($atts['limit']))), 'orderby' => 'date', 'order' => 'DESC');
        if (trim($atts['ids']) !== '') {
            $ids = array_filter(array_map('absint', explode(',', $atts['ids'])));
            if (!$ids) return '';
            $args['post__in'] = $ids; $args['orderby'] = 'post__in'; $args['posts_per_page'] = count($ids);
        }
        $query = new WP_Query($args);
        if (!$query->have_posts()) return '<p class="bsw7-media-empty">No published media files were found.</p>';
        $columns = min(5, max(1, absint($atts['columns'])));
        $size = in_array($atts['size'], array('thumbnail', 'medium', 'large', 'full'), true) ? $atts['size'] : 'large';
        $out = '<div class="bsw7-media-gallery" style="--bsw7-gallery-columns:'.$columns.'">';
        while ($query->have_posts()) {
            $query->the_post(); $id = get_the_ID();
            $image = wp_get_attachment_image($id, $size, false, array('loading' => 'lazy', 'decoding' => 'async'));
            if (!$image) continue;
            $title = get_the_title($id); $creator = self::get_value($id, 'creator'); $credit = self::get_value($id, 'credit'); $licence = self::get_value($id, 'licence');
            $out .= '<figure class="bsw7-media-item"><a href="'.esc_url(wp_get_attachment_url($id)).'">'.$image.'</a><figcaption><strong>'.esc_html($title).'</strong>';
            if ($creator || $credit) $out .= '<span>'.esc_html($credit ?: $creator).'</span>';
            if ($licence) $out .= '<small>'.esc_html($licence).'</small>';
            $out .= '</figcaption></figure>';
        }
        wp_reset_postdata();
        return $out . '</div>';
    }

    public static function file_shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0, 'size' => 'large', 'caption' => '1'), $atts, 'bsw_media_file');
        $id = absint($atts['id']);
        if (!$id || get_post_type($id) !== 'attachment') return '';
        $url = wp_get_attachment_url($id); if (!$url) return '';
        $mime = get_post_mime_type($id); $title = get_the_title($id);
        if (wp_attachment_is_image($id)) {
            $html = wp_get_attachment_image($id, in_array($atts['size'], array('thumbnail','medium','large','full'), true) ? $atts['size'] : 'large', false, array('loading'=>'lazy','decoding'=>'async'));
            if (!$html) return '';
            return '<figure class="bsw7-media-embed">'.$html.(!empty($atts['caption']) ? '<figcaption>'.esc_html($title).'</figcaption>' : '').'</figure>';
        }
        $label = $mime ? strtoupper(str_replace(array('application/','audio/','video/'), '', $mime)) : 'FILE';
        $path = get_attached_file($id);
        $bytes = $path && file_exists($path) ? (int) filesize($path) : 0;
        return '<p class="bsw7-file-link"><span class="bsw7-file-type">'.esc_html($label).'</span> <a href="'.esc_url($url).'">'.esc_html($title ?: basename($url)).'</a> <span>'.esc_html($bytes ? size_format($bytes) : '').'</span></p>';
    }

    public static function download_shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0, 'label' => ''), $atts, 'bsw_media_download');
        $id = absint($atts['id']);
        if (!$id || get_post_type($id) !== 'attachment') return '';
        $url = wp_get_attachment_url($id); if (!$url) return '';
        $label = trim($atts['label']) !== '' ? sanitize_text_field($atts['label']) : 'Download ' . get_the_title($id);
        return '<a class="bsw7-download-link" href="'.esc_url($url).'" download>'.esc_html($label).'</a>';
    }

    public static function enqueue_styles() {
        if (is_admin()) return;
        $css = '.bsw7-media-gallery{display:grid;grid-template-columns:repeat(var(--bsw7-gallery-columns,3),minmax(0,1fr));gap:18px;margin:1.5rem 0}.bsw7-media-item{margin:0;min-width:0;border:1px solid #dfe5ed;border-radius:10px;overflow:hidden;background:#fff}.bsw7-media-item>a{display:block;background:#f5f7fa}.bsw7-media-item img{display:block;width:100%;height:auto;max-height:360px;object-fit:contain}.bsw7-media-item figcaption{display:grid;gap:4px;padding:10px 12px;font-size:.88em}.bsw7-media-item figcaption span,.bsw7-media-item figcaption small{color:#657184}.bsw7-media-embed{margin:1.25rem 0}.bsw7-media-embed img{max-width:100%;height:auto}.bsw7-media-embed figcaption{font-size:.88em;color:#657184}.bsw7-file-link,.bsw7-download-link{display:inline-flex;align-items:center;gap:9px;border:1px solid #dfe5ed;border-radius:8px;padding:9px 12px;overflow-wrap:anywhere}.bsw7-file-type{font-size:.72em;font-weight:800;color:#536176;background:#f1f4f8;padding:4px 6px;border-radius:4px}.bsw7-download-link{background:#f8fafc;font-weight:650}@media(max-width:700px){.bsw7-media-gallery{grid-template-columns:repeat(min(2,var(--bsw7-gallery-columns,2)),minmax(0,1fr));gap:10px}}@media(max-width:420px){.bsw7-media-gallery{grid-template-columns:1fr}}';
        wp_register_style('bsw7-media-management', false, array(), self::VERSION);
        wp_enqueue_style('bsw7-media-management');
        wp_add_inline_style('bsw7-media-management', $css);
    }
}
BSW_Phase7_Media_Management::init();
}
