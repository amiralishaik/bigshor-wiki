<?php
/** Static regression checks for the public BIGSHOR encyclopedia article template and controls. */
$template = dirname(__DIR__) . '/single-wiki_article.php';
$script = dirname(__DIR__) . '/assets/wiki-enhancements.js';
$source = is_readable($template) ? file_get_contents($template) : '';
$js = is_readable($script) ? file_get_contents($script) : '';
$checks = array(
    'article_page_template_exists' => $source !== '',
    'accessible_page_tools_navigation' => strpos($source, 'aria-label="Article tools"') !== false,
    'article_title_has_stable_accessible_id' => strpos($source, 'id="article-title"') !== false,
    'article_content_is_labelled_by_title' => strpos($source, 'aria-labelledby="article-title"') !== false,
    'copy_link_control_uses_theme_handler' => strpos($source, 'data-bsw-copy-link') !== false && strpos($js, 'function setupCopyLinks()') !== false,
    'copy_link_has_legacy_browser_fallback' => strpos($js, "doc.execCommand('copy')") !== false,
    'print_control_uses_external_handler' => strpos($source, 'data-bsw-print') !== false && strpos($js, 'window.print()') !== false,
    'edit_action_permission_gated' => strpos($source, "current_user_can( 'edit_post', \$article_id )") !== false,
    'revision_history_link' => strpos($source, '\$history_url') !== false,
    'published_and_modified_dates_have_machine_readable_values' => substr_count($source, 'datetime="<?php echo esc_attr( \$') >= 2,
    'author_archive_link_available' => strpos($source, 'get_author_posts_url') !== false,
    'progress_bar_not_duplicated_in_template' => strpos($source, 'bsw-reading-progress') === false,
    'topic_taxonomy_preserved' => strpos($source, "'wiki_topic'") !== false,
    'talk_anchor_preserved' => strpos($source, 'href="#comments"') !== false,
);
$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
    if (!$passed) $failed++;
}
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
