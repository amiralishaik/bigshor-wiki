<?php
/** Static regression checks for article-page Phase 11 hardening. */
$hardening = file_get_contents(dirname(__DIR__) . '/includes/phase11-article-hardening.php');
$discovery = file_get_contents(dirname(__DIR__) . '/includes/phase10-article-discovery.php');
$checks = array(
    'hardening_module_boots' => strpos($hardening, 'BSW_Phase11_Article_Hardening::init()') !== false,
    'cache_generation_is_versioned' => strpos($hardening, 'GENERATION_OPTION') !== false && strpos($hardening, 'function generation()') !== false,
    'article_save_invalidates_discovery_cache' => strpos($hardening, "add_action('save_post_wiki_article'") !== false,
    'topic_changes_invalidate_discovery_cache' => strpos($hardening, "add_action('set_object_terms'") !== false && strpos($hardening, "taxonomy === 'wiki_topic'") !== false,
    'delete_and_trash_invalidate_cache' => strpos($hardening, "add_action('deleted_post'") !== false && strpos($hardening, "add_action('trashed_post'") !== false,
    'cache_invalidation_uses_non_autoloaded_option' => strpos($hardening, "update_option(self::GENERATION_OPTION, self::generation() + 1, false)") !== false,
    'phase69_sync_conversion_is_disabled' => strpos($hardening, "remove_filter('wp_generate_attachment_metadata'") !== false,
    'phase10_search_results_are_cached' => strpos($discovery, "cache_key('search'") !== false && strpos($discovery, "set_transient(\$cache_key, \$result, 10 * MINUTE_IN_SECONDS)") !== false,
    'phase10_related_articles_are_cached' => strpos($discovery, "cache_key('related'") !== false,
    'phase10_backlinks_are_cached' => strpos($discovery, "cache_key('backlinks'") !== false,
    'cache_lifetime_is_bounded' => strpos($discovery, '10 * MINUTE_IN_SECONDS') !== false,
    'phase70_async_processor_remains_authoritative' => strpos($hardening, 'BSW_Phase69_Modern_Image_Delivery') !== false && strpos($hardening, 'disabled to avoid duplicate CPU and I/O') !== false,
);
$failed=0;
foreach($checks as $name=>$passed){echo ($passed?'PASS':'FAIL').' '.$name.PHP_EOL;if(!$passed)$failed++;}
echo 'TOTAL '.count($checks).' FAILED '.$failed.PHP_EOL;
exit($failed?1:0);
