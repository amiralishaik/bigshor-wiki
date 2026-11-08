<?php
/** Regression checks for the Phase 2 encyclopaedia article structure. */
$source = file_get_contents(dirname(__DIR__) . '/includes/phase45-article-layout.php');
$checks = array(
    'article_layout_is_bootstrapped' => strpos($source, "BSW_Phase45_Article_Layout','init'") !== false,
    'table_of_contents_is_semantically_labelled' => strpos($source, 'aria-labelledby="bsw45-toc-title-') !== false,
    'table_of_contents_toggle_controls_panel' => strpos($source, 'aria-controls="bsw45-toc-body-') !== false,
    'nested_section_hierarchy_is_rendered' => strpos($source, 'self::toc_tree($items)') !== false && strpos($source, 'self::toc_nodes($tree, $post_id)') !== false,
    'stable_heading_slug_anchors' => strpos($source, 'sanitize_title($title)') !== false && strpos($source, 'bsw-section-') !== false,
    'duplicate_heading_slugs_are_disambiguated' => strpos($source, 'while(isset($seen[$id]))') !== false,
    'section_edit_links_are_capability_gated' => strpos($source, "current_user_can('edit_post', \$post_id)") !== false,
    'responsive_infobox_and_toc' => strpos($source, '@media(max-width:760px)') !== false,
    'print_layout_rules' => strpos($source, '@media print') !== false,
    'article_content_is_not_mutated_in_admin' => strpos($source, 'is_admin() || !is_singular') !== false,
);
$failed = 0;
foreach ($checks as $name => $passed) { echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL; if (!$passed) $failed++; }
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
