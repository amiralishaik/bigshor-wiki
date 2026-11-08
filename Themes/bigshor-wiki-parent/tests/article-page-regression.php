<?php
/** Static regression checks for the public BIGSHOR encyclopedia article template. */
$template = dirname(__DIR__) . '/single-wiki_article.php';
$source = is_readable($template) ? file_get_contents($template) : '';
$checks = array(
    'article_page_template_exists' => $source !== '',
    'accessible_page_tools_navigation' => strpos($source, 'aria-label="Article tools"') !== false,
    'copy_link_control_uses_theme_handler' => strpos($source, 'data-bsw-copy-link') !== false,
    'print_action_available' => strpos($source, 'window.print()') !== false,
    'edit_action_permission_gated' => strpos($source, "current_user_can( 'edit_post', \$article_id )") !== false,
    'revision_history_link' => strpos($source, '\$history_url') !== false,
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
