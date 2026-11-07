<?php
/** Theme/plugin integration regression checks; static by design, no WordPress bootstrap required. */
$root = dirname(__DIR__, 3);
$parent = file_get_contents($root . '/Themes/bigshor-wiki-parent/functions.php');
$header = file_get_contents($root . '/Themes/bigshor-wiki-parent/header.php');
$css = file_get_contents($root . '/Themes/bigshor-wiki-parent/assets/wiki-core.css');
$child = file_get_contents($root . '/Themes/bigshor-wiki-child/functions.php');
$admin = file_get_contents($root . '/Plugins/bigshor-wiki-admin-ui/bigshor-wiki-admin-ui.php');
$studio = file_get_contents($root . '/Plugins/bigshor-wiki-article-studio/bigshor-wiki-article-studio.php');
$checks = array(
    'single_authoritative_search_hook' => substr_count($parent, "add_action('pre_get_posts', 'bsw_theme_search_post_types'") === 1,
    'search_includes_all_wiki_content' => strpos($parent, "'wiki_article', 'wiki_entity', 'wiki_media', 'bsw_news'") !== false,
    'search_respects_explicit_post_type' => strpos($parent, "|| \$query->get('post_type')") !== false,
    'legacy_search_override_removed' => strpos($parent, 'bsw_theme_v18_search_filter') === false,
    'theme_fallback_skips_active_core' => strpos($parent, "if (defined('BSW_CORE_PLUGIN_ACTIVE') && BSW_CORE_PLUGIN_ACTIVE) return;") !== false,
    'accessible_sidebar_toggle' => strpos($header, 'aria-controls="mw-sidebar"') !== false && strpos($header, 'aria-expanded="false"') !== false,
    'desktop_tablet_primary_navigation' => strpos($header, 'class="mw-primary-navigation"') !== false && strpos($css, '.mw-primary-navigation{display:block') !== false,
    'accessible_appearance_panel' => strpos($header, 'id="mw-appearance-panel"') !== false && strpos($header, 'hidden') !== false,
    'child_styles_load_after_parent_core_css' => strpos($child, "array('bigshor-wiki-core')") !== false,
    'admin_ui_enforces_supported_core_version' => strpos($admin, "version_compare(BSW_CORE_PLUGIN_VERSION, '34.2.0', '<')") !== false,
    'article_studio_requires_supported_core' => strpos($studio, "version_compare(BSW_CORE_PLUGIN_VERSION, '34.2.0', '<')") !== false,
);
$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
    if (!$passed) $failed++;
}
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
