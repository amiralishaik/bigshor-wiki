<?php
/** Standalone static regression assertions for Phase 10; no WordPress bootstrap required. */
$root = dirname(__DIR__);
$main = file_get_contents($root . '/bigshor-wiki-core.php');
$module = file_get_contents($root . '/includes/phase63-production-readiness.php');
$checks = array(
    'release_version' => strpos($main, "Version: 33.6.0") !== false && strpos($main, "BSW_CORE_PLUGIN_VERSION', '33.6.0'") !== false,
    'module_bootstrap' => strpos($main, "phase63-production-readiness.php") !== false,
    'admin_capability' => strpos($module, "current_user_can('manage_options')") !== false,
    'nonce_protection' => strpos($module, 'check_admin_referer') !== false,
    'read_only_preflight' => strpos($module, 'function diagnostics()') !== false && strpos($module, "'SELECT 1'") !== false,
    'release_gate' => strpos($module, "preflight_blocked") !== false && strpos($module, "preflight_passed") !== false,
    'citation_scan_bounded' => strpos($module, 'MAX_ARTICLES = 500') !== false,
    'citation_duplicate_detection' => strpos($module, 'duplicate_source_url') !== false,
    'citation_https_detection' => strpos($module, 'non_https_source') !== false,
    'deployment_manifest_redacted' => strpos($module, 'deployment_inventory_only') !== false && strpos($module, 'Secret values are never displayed') !== false,
    'no_automatic_deployment' => strpos($module, 'does not change publishing or deployment state') !== false,
    'admin_tools_menu' => strpos($module, 'add_management_page') !== false,
    'enhanced_search_route' => strpos($module, "register_rest_route('bigshor/v4', '/search'") !== false,
    'weighted_search_signals' => strpos($module, 'title_exact') !== false && strpos($module, 'content_match') !== false,
    'language_filter' => strpos($module, "_bsw_p7_language") !== false,
    'federation_monitoring' => strpos($module, 'bsw_phase60_federation_peers') !== false && strpos($module, 'last_sync_gmt') !== false,
);
$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
    if (!$passed) $failed++;
}
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
