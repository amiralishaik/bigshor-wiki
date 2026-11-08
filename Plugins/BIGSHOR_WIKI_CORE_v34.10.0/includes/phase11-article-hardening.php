<?php
/**
 * Article-page Phase 11: performance, accessibility and security hardening.
 * Adds bounded transient caching, reliable invalidation and removes duplicate
 * synchronous image conversion while retaining legacy image delivery markup.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase11_Article_Hardening')) {
final class BSW_Phase11_Article_Hardening {
    const GENERATION_OPTION = 'bsw_article_discovery_cache_generation';
    const CACHE_TTL = 600;

    public static function init() {
        add_action('save_post_wiki_article', array(__CLASS__, 'invalidate'), 10, 3);
        add_action('set_object_terms', array(__CLASS__, 'terms_changed'), 10, 6);
        add_action('deleted_post', array(__CLASS__, 'deleted'), 10, 2);
        add_action('trashed_post', array(__CLASS__, 'invalidate_id'));
        add_action('untrashed_post', array(__CLASS__, 'invalidate_id'));
        add_action('created_wiki_topic', array(__CLASS__, 'invalidate_all'));
        add_action('edited_wiki_topic', array(__CLASS__, 'invalidate_all'));
        add_action('delete_wiki_topic', array(__CLASS__, 'invalidate_all'));
        // Phase 70 owns asynchronous upload-time conversion. Phase 69's
        // synchronous converter is disabled to avoid duplicate CPU and I/O.
        add_action('init', array(__CLASS__, 'disable_duplicate_sync_conversion'), 1);
    }

    public static function generation() {
        return max(1, (int)get_option(self::GENERATION_OPTION, 1));
    }

    public static function cache_key($kind, $key) {
        return 'bsw_p10_' . sanitize_key($kind) . '_' . md5(self::generation() . '|' . (string)$key);
    }

    public static function invalidate($post_id, $post = null, $update = false) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        self::invalidate_all();
    }

    public static function terms_changed($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids = array()) {
        if ($taxonomy === 'wiki_topic' && get_post_type($object_id) === 'wiki_article') self::invalidate_all();
    }

    public static function deleted($post_id, $post = null) {
        if ($post && isset($post->post_type) && $post->post_type === 'wiki_article') self::invalidate_all();
    }

    public static function invalidate_id($post_id) {
        if (get_post_type($post_id) === 'wiki_article') self::invalidate_all();
    }

    public static function invalidate_all() {
        update_option(self::GENERATION_OPTION, self::generation() + 1, false);
    }

    public static function disable_duplicate_sync_conversion() {
        if (class_exists('BSW_Phase69_Modern_Image_Delivery')) {
            remove_filter('wp_generate_attachment_metadata', array('BSW_Phase69_Modern_Image_Delivery', 'generate'), 20);
        }
    }
}
BSW_Phase11_Article_Hardening::init();
}
