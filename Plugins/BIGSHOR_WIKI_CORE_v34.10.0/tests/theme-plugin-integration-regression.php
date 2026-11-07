<?php
/** Theme/plugin integration regression checks; static by design, no WordPress bootstrap required. */
$root = dirname(__DIR__, 3);
$parent = file_get_contents($root . '/Themes/bigshor-wiki-parent/functions.php');
$header = file_get_contents($root . '/Themes/bigshor-wiki-parent/header.php');
$css = file_get_contents($root . '/Themes/bigshor-wiki-parent/assets/wiki-core.css');
$js = file_get_contents($root . '/Themes/bigshor-wiki-parent/assets/wiki-enhancements.js');
$core = file_get_contents($root . '/Plugins/BIGSHOR_WIKI_CORE_v34.10.0/bigshor-wiki-core.php');
$child = file_get_contents($root . '/Themes/bigshor-wiki-child/functions.php');
$admin = file_get_contents($root . '/Plugins/bigshor-wiki-admin-ui/bigshor-wiki-admin-ui.php');
$studio = file_get_contents($root . '/Plugins/bigshor-wiki-article-studio/bigshor-wiki-article-studio.php');
$performance = file_get_contents($root . '/Themes/bigshor-wiki-parent/inc/performance-optimizations.php');
$checks = array(
    'single_authoritative_search_hook' => substr_count($parent, "add_action('pre_get_posts', 'bsw_theme_search_post_types'") === 1,
    'search_includes_all_wiki_content' => strpos($parent, "'wiki_article', 'wiki_entity', 'wiki_media', 'bsw_news'") !== false,
    'search_respects_explicit_post_type' => strpos($parent, "|| \$query->get('post_type')") !== false,
    'legacy_search_override_removed' => strpos($parent, 'bsw_theme_v18_search_filter') === false,
    'theme_fallback_skips_active_core' => strpos($parent, "if (defined('BSW_CORE_PLUGIN_ACTIVE') && BSW_CORE_PLUGIN_ACTIVE) return;") !== false,
    'accessible_sidebar_toggle' => strpos($header, 'aria-controls="mw-sidebar"') !== false && strpos($header, 'aria-expanded="false"') !== false,
    'desktop_tablet_primary_navigation' => strpos($header, 'class="mw-primary-navigation"') !== false && strpos($css, '.mw-primary-navigation{display:block') !== false,
    'mobile_drawer_keyboard_focus_trap' => strpos($js, "event.key !== 'Tab'") !== false && strpos($js, 'last.focus()') !== false && strpos($js, "event.key === 'Escape'") !== false,
    'tablet_drawer_breakpoint_matches_styles' => strpos($js, "matchMedia('(max-width: 900px)')") !== false && strpos($css, '@media (max-width: 900px)') !== false && strpos($css, 'body.mw-sidebar-open') !== false,
    'fallback_navigation_has_navy_styles' => strpos($css, '.mw-primary-navigation .side-nav') !== false,
    'phase70_media_optimizer_is_bootstrapped' => strpos($core, "'phase70-media-optimizer.php'") !== false,
    'accessible_appearance_panel' => strpos($header, 'id="mw-appearance-panel"') !== false && strpos($header, 'hidden') !== false,
    'child_styles_load_after_parent_core_css' => strpos($child, "array('bigshor-wiki-core')") !== false,
    'admin_ui_enforces_supported_core_version' => strpos($admin, "version_compare(BSW_CORE_PLUGIN_VERSION, '34.2.0', '<')") !== false,
    'article_studio_requires_supported_core' => strpos($studio, "version_compare(BSW_CORE_PLUGIN_VERSION, '34.2.0', '<')") !== false,
    'article_studio_release_version_aligned' => strpos($studio, 'Version: 1.4.0') !== false && strpos($studio, "define('BSW_STUDIO_VERSION', '1.4.0')") !== false,
    'performance_module_loaded' => strpos($parent, "'/inc/performance-optimizations.php'") !== false,
    'anonymous_dashicons_and_embed_removed' => strpos($performance, "wp_dequeue_style('dashicons')") !== false && strpos($performance, "wp_deregister_script('wp-embed')") !== false,
    'emoji_payloads_disabled_on_frontend' => strpos($performance, "remove_action('wp_head', 'print_emoji_detection_script', 7)") !== false,
    'featured_image_preloaded' => strpos($performance, 'wp_get_attachment_image_srcset') !== false && strpos($performance, 'fetchpriority') !== false,
);
$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
    if (!$passed) $failed++;
}
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
