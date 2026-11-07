<?php
/** Phase 1: canonical architecture and editor registration. */
if (!defined('ABSPATH')) exit;
final class BSW_Phase44_Architecture_Consolidation {
    const SHORTCODE = 'bigshor_wiki_frontend_editor';
    public static function init() {
        remove_shortcode(self::SHORTCODE);
        if (class_exists('BSW_Phase40_Article_Experience')) {
            remove_action('init', array('BSW_Phase40_Article_Experience', 'handle_frontend_save'), 1);
        }
        add_shortcode(self::SHORTCODE, array(__CLASS__, 'render_editor'));
        add_action('admin_notices', array(__CLASS__, 'diagnostic_notice'));
    }
    public static function render_editor($attributes = array(), $content = '', $tag = '') {
        $attributes = shortcode_atts(array('id' => 0), (array) $attributes, self::SHORTCODE);
        $id = absint($attributes['id']);
        if (class_exists('BSW_Phase42_Wikitext')) return BSW_Phase42_Wikitext::editor($id);
        if (class_exists('BSW_Phase41_Wiki_Framework')) return BSW_Phase41_Wiki_Framework::editor($id);
        return '<div class="bsw41-note">The BIGSHOR article editor is currently unavailable.</div>';
    }
    public static function diagnostic_notice() {
        if (!current_user_can('manage_options') || !post_type_exists('wiki_article')) return;
        if (!shortcode_exists(self::SHORTCODE)) echo '<div class="notice notice-warning"><p><strong>BIGSHOR Wiki:</strong> the canonical article editor shortcode is not registered.</p></div>';
    }
}
add_action('init', array('BSW_Phase44_Architecture_Consolidation', 'init'), 40);
