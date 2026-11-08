<?php
/** Static regression checks for article-page Phase 12 integration and release gate. */
$base=dirname(__DIR__);
$core=file_get_contents($base.'/bigshor-wiki-core.php');
$gate=file_get_contents($base.'/includes/phase12-article-release-integration.php');
$readiness=file_get_contents($base.'/includes/phase63-production-readiness.php');
$checks=array(
    'core_release_version_is_34_12_0' => strpos($core,"Version: 34.12.0")!==false && strpos($core,"define('BSW_CORE_PLUGIN_VERSION', '34.12.0')")!==false,
    'phase11_is_bootstrapped' => strpos($core,"phase11-article-hardening.php")!==false,
    'phase12_is_bootstrapped' => strpos($core,"phase12-article-release-integration.php")!==false,
    'phase10_precedes_phase11_and_phase12' => strpos($core,'phase10-article-discovery.php') < strpos($core,'phase11-article-hardening.php') && strpos($core,'phase11-article-hardening.php') < strpos($core,'phase12-article-release-integration.php'),
    'release_gate_is_read_only' => strpos($gate,'Read-only administrator diagnostics')!==false && strpos($gate,'add_management_page')!==false,
    'release_gate_requires_admin_capability' => strpos($gate,"'manage_options'")!==false && strpos($gate,'current_user_can')!==false,
    'release_gate_checks_article_modules' => strpos($gate,'BSW_Phase45_Article_Layout')!==false && strpos($gate,'BSW_Phase8_Article_Community')!==false && strpos($gate,'BSW_Phase9_Advanced_Article')!==false && strpos($gate,'BSW_Phase10_Article_Discovery')!==false && strpos($gate,'BSW_Phase11_Article_Hardening')!==false,
    'release_gate_checks_media_conversion_conflict' => strpos($gate,'No duplicate synchronous image conversion')!==false,
    'release_gate_does_not_claim_production_certification' => strpos($gate,'does not replace WordPress staging tests')!==false,
    'production_readiness_version_matches_core' => strpos($readiness,"const VERSION = '34.12.0'")!==false && strpos($readiness,"expected ' . self::VERSION")!==false,
);
$failed=0;
foreach($checks as $name=>$passed){echo ($passed?'PASS':'FAIL').' '.$name.PHP_EOL;if(!$passed)$failed++;}
echo 'TOTAL '.count($checks).' FAILED '.$failed.PHP_EOL;
exit($failed?1:0);
