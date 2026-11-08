<?php
/** Static regression checks for the native BIGSHOR Tools Hub. */
$root = dirname(__DIR__);
$main = file_get_contents($root . '/bigshor-wiki-core.php');
$module = file_get_contents($root . '/includes/tools-hub.php');
$checks = array(
    'release_version_matches_current_core' => strpos($main, 'Version: 34.12.0') !== false && strpos($main, "BSW_CORE_PLUGIN_VERSION', '34.12.0'") !== false,
    'module_bootstrap' => strpos($main, "tools-hub.php") !== false,
    'control_centre_parent' => strpos($module, "'bsw-control-centre'") !== false,
    'administrator_only' => strpos($module, "current_user_can('manage_options')") !== false,
    'catalog_present' => strpos($module, 'function bsw_tools_hub_catalog()') !== false,
    'search_filter' => strpos($module, 'bsw-tools-search') !== false,
    'category_filter' => strpos($module, 'bsw-tools-category') !== false,
    'access_filter' => strpos($module, 'bsw-tools-access') !== false,
    'safe_external_links' => strpos($module, 'rel="noopener noreferrer"') !== false,
    'no_external_api_dependency' => strpos($module, 'wp_remote_get(') === false,
);
$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
    if (!$passed) $failed++;
}
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
