<?php
/** Static regression checks for the Article Studio Core integration. */
$root = dirname(__DIR__);
$main = file_get_contents($root . '/bigshor-wiki-core.php');
$module = file_get_contents($root . '/includes/article-studio.php');
$checks = array(
    'core_release_version' => strpos($main, 'Version: 34.11.0') !== false && strpos($main, "BSW_CORE_PLUGIN_VERSION', '34.11.0'") !== false,
    'module_bootstrap' => strpos($main, "article-studio.php") !== false,
    'studio_class_loaded' => strpos($module, 'class BSW_Article_Studio') !== false && strpos($module, 'BSW_Article_Studio::init();') !== false,
    'visual_editor' => strpos($module, 'wp_editor(') !== false,
    'source_editor' => strpos($module, 'bsw_source_content') !== false,
    'ajax_workflows' => strpos($module, 'ajax_autosave') !== false && strpos($module, 'ajax_suggestion') !== false && strpos($module, 'ajax_assign') !== false,
    'core_asset_urls' => strpos($module, "../bigshor-wiki-core.php") !== false,
    'no_standalone_activation_hook' => strpos($module, 'register_activation_hook(') === false,
    'article_editor_redirects' => strpos($module, 'redirect_new') !== false && strpos($module, 'redirect_edit') !== false,
);
$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
    if (!$passed) $failed++;
}
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
