<?php
/** BIGSHOR Wiki child theme v23.6.0: brand-only layer. */
if (!defined('ABSPATH')) { exit; }
if (!function_exists('bigshor_wiki_child_enqueue_styles')) {
function bigshor_wiki_child_enqueue_styles() {
    $parent = wp_get_theme(get_template());
    $parent_version = $parent->get('Version') ?: '23.5.0';
    $child_file = get_stylesheet_directory() . '/style.css';
    $child_version = file_exists($child_file) ? (string) filemtime($child_file) : (wp_get_theme()->get('Version') ?: '23.5.0');
    wp_enqueue_style('bigshor-wiki-child-style', get_stylesheet_uri(), array('bigshor-wiki-core'), $child_version ?: $parent_version);
}
}
add_action('wp_enqueue_scripts', 'bigshor_wiki_child_enqueue_styles', 20);
