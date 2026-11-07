<?php
/**
 * BIGSHOR Knowledge Standard (BKS) editorial controls.
 * Adds structured source and disclosure fields; it does not automatically certify content.
 * PHP 7.4+.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Knowledge_Standard')) {
final class BSW_Knowledge_Standard {
    const VERSION = '1.0.0';
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'boxes'));
        add_action('save_post', array(__CLASS__, 'save'), 40, 2);
        add_action('admin_notices', array(__CLASS__, 'notice'));
    }
    private static function supported($type) {
        return in_array($type, array('post','page','wiki_article','wiki_entity'), true);
    }
    public static function boxes() {
        foreach (array('post','page','wiki_article','wiki_entity') as $type) {
            if (post_type_exists($type)) add_meta_box('bsw_knowledge_standard', 'BIGSHOR Knowledge Standard (BKS)', array(__CLASS__, 'render'), $type, 'normal', 'high');
        }
    }
    public static function render($post) {
        wp_nonce_field('bsw_bks_save', 'bsw_bks_nonce');
        $fields = array(
            'bsw_bks_sources' => array('Primary sources and citations', 'textarea', 'List each source on a separate line. Include publisher, title, date and URL where available.'),
            'bsw_bks_secondary' => array('Independent secondary sources', 'textarea', 'List independent reporting, research or authoritative analysis.'),
            'bsw_bks_disclosure' => array('Conflict of interest / relationship disclosure', 'textarea', 'Disclose employment, payment, ownership, family or other relevant relationships. Write “None declared” only after checking.'),
            'bsw_bks_editor_note' => array('Editorial verification note', 'textarea', 'Explain what was checked, what remains uncertain and any limitations.'),
        );
        echo '<div class="bsw-bks-fields">';
        foreach ($fields as $key => $field) {
            $value = get_post_meta($post->ID, '_' . $key, true);
            echo '<p><label for="' . esc_attr($key) . '"><strong>' . esc_html($field[0]) . '</strong></label><br>';
            echo '<textarea class="widefat" rows="3" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '">' . esc_textarea($value) . '</textarea>';
            echo '<span class="description">' . esc_html($field[2]) . '</span></p>';
        }
        $status = get_post_meta($post->ID, '_bsw_bks_status', true);
        echo '<p><label for="bsw_bks_status"><strong>BKS editorial status</strong></label><br><select name="bsw_bks_status" id="bsw_bks_status">';
        foreach (array('not_reviewed'=>'Not reviewed','in_review'=>'In review','needs_changes'=>'Needs changes','verified'=>'Verified by editor','disputed'=>'Disputed / under review') as $value=>$label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($status ?: 'not_reviewed', $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select> <span class="description">“Verified” is an editorial decision, not an automated fact-check.</span></p></div>';
    }
    public static function save($post_id, $post) {
        if (!$post || !self::supported($post->post_type) || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
        if (!isset($_POST['bsw_bks_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_bks_nonce'])), 'bsw_bks_save')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        foreach (array('bsw_bks_sources','bsw_bks_secondary','bsw_bks_disclosure','bsw_bks_editor_note') as $key) {
            $value = isset($_POST[$key]) ? sanitize_textarea_field(wp_unslash($_POST[$key])) : '';
            update_post_meta($post_id, '_' . $key, $value);
        }
        $allowed = array('not_reviewed','in_review','needs_changes','verified','disputed');
        $status = isset($_POST['bsw_bks_status']) ? sanitize_key(wp_unslash($_POST['bsw_bks_status'])) : 'not_reviewed';
        if (!in_array($status, $allowed, true)) $status = 'not_reviewed';
        update_post_meta($post_id, '_bsw_bks_status', $status);
        update_post_meta($post_id, '_bsw_bks_last_updated', current_time('mysql'));
    }
    public static function notice() {
        if (!current_user_can('edit_posts')) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->base, array('post','post-new'), true)) return;
        echo '<div class="notice notice-info"><p><strong>BIGSHOR BKS:</strong> cite reliable sources, disclose relevant conflicts, distinguish fact from opinion, and obtain human editorial review before marking an article verified.</p></div>';
    }
}
BSW_Knowledge_Standard::init();
}
