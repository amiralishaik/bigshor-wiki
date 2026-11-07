<?php
/**
 * Conservative public-facing performance optimisations for BIGSHOR Wiki.
 * Avoids removing jQuery or changing CSS delivery, which can break interactive layouts.
 */
if (!defined('ABSPATH')) { exit; }

/** Remove WordPress emoji payloads; native browser emoji rendering remains available. */
function bsw_perf_disable_emojis() {
    if (is_admin()) return;
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('tiny_mce_plugins', function ($plugins) {
        return is_array($plugins) ? array_diff($plugins, array('wpemoji')) : $plugins;
    });
}
add_action('init', 'bsw_perf_disable_emojis', 1);

/** Remove assets that are not needed by anonymous front-end visitors. */
function bsw_perf_dequeue_unused_public_assets() {
    if (is_admin() || is_user_logged_in()) return;
    wp_dequeue_style('dashicons');
    wp_deregister_style('dashicons');
    wp_dequeue_script('wp-embed');
    wp_deregister_script('wp-embed');
}
add_action('wp_enqueue_scripts', 'bsw_perf_dequeue_unused_public_assets', 100);

/** Preload the actual featured image on singular content pages when one exists. */
function bsw_perf_preload_featured_image() {
    if (is_admin() || !is_singular()) return;
    $post_id = get_queried_object_id();
    if (!$post_id || !has_post_thumbnail($post_id)) return;
    $attachment_id = get_post_thumbnail_id($post_id);
    $image = wp_get_attachment_image_src($attachment_id, 'large');
    if (!$image || empty($image[0])) return;
    $srcset = wp_get_attachment_image_srcset($attachment_id, 'large');
    $sizes = wp_get_attachment_image_sizes($attachment_id, 'large');
    echo '<link rel="preload" as="image" href="' . esc_url($image[0]) . '"';
    if ($srcset) echo ' imagesrcset="' . esc_attr($srcset) . '"';
    if ($sizes) echo ' imagesizes="' . esc_attr($sizes) . '"';
    echo ' fetchpriority="high">' . "\n";
}
add_action('wp_head', 'bsw_perf_preload_featured_image', 2);

/** Ensure the matching featured image is eager/high priority, without affecting other images. */
function bsw_perf_featured_image_attributes($attr, $attachment, $size) {
    if (is_admin() || !is_singular() || !($attachment instanceof WP_Post)) return $attr;
    $post_id = get_queried_object_id();
    if (!$post_id || (int) get_post_thumbnail_id($post_id) !== (int) $attachment->ID) return $attr;
    $attr['fetchpriority'] = 'high';
    $attr['loading'] = 'eager';
    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'bsw_perf_featured_image_attributes', 30, 3);
