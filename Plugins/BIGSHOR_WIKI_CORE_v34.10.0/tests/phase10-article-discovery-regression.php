<?php
/** Regression checks for article-page Phase 10 search and discovery. */
$source = file_get_contents(dirname(__DIR__) . '/includes/phase10-article-discovery.php');
$checks = array(
    'article_discovery_module_boots' => strpos($source, "BSW_Phase10_Article_Discovery::init()") !== false,
    'reusable_search_shortcode_registered' => strpos($source, "add_shortcode('bsw_article_search'") !== false,
    'searches_published_article_titles_and_content' => strpos($source, "'post_type'=>'wiki_article', 'post_status'=>'publish', 's'=>$term") !== false,
    'search_includes_editorial_alternative_names' => strpos($source, "'key'=>'_bsw_p9_aliases'") !== false && strpos($source, "'compare'=>'LIKE'") !== false,
    'search_input_is_bounded_and_minimum_length_checked' => strpos($source, "return mb_substr(\$value, 0, 100)") !== false && strpos($source, "strlen(\$term)<2") !== false,
    'search_results_are_public_only' => strpos($source, "get_post_status(\$id)==='publish'") !== false,
    'search_results_are_paginated' => strpos($source, 'self::PER_PAGE') !== false && strpos($source, 'bsw_article_page') !== false,
    'search_output_escapes_user_and_article_data' => strpos($source, 'esc_attr($term)') !== false && strpos($source, 'esc_html(get_the_title($id))') !== false,
    'related_articles_use_shared_wiki_topics' => strpos($source, "'taxonomy'=>'wiki_topic'") !== false,
    'curated_knowledge_graph_relationships_are_reused' => strpos($source, 'BSW_Phase58_Knowledge_Graph::related_articles') !== false,
    'incoming_links_are_bounded_and_verified' => strpos($source, 'LIMIT 30') !== false && strpos($source, 'strpos($content,$url)!==false') !== false,
    'discovery_panel_is_article_only_and_skips_edit_views' => strpos($source, "!is_singular('wiki_article')") !== false && strpos($source, "isset(\$_GET['bsw_edit'])") !== false,
    'accessible_discovery_regions_are_present' => strpos($source, 'aria-labelledby="bsw-p10-discovery-title"') !== false && strpos($source, 'aria-label="Search BIGSHOR Wiki"') !== false,
    'responsive_discovery_styles_are_registered' => strpos($source, '@media(max-width:600px)') !== false,
);
$failed = 0;
foreach ($checks as $name => $passed) { echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL; if (!$passed) $failed++; }
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
