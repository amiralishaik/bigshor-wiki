<?php
/** Regression checks for Phase 5 article sections and knowledge navigation. */
$source = file_get_contents(dirname(__DIR__) . '/includes/phase45-article-layout.php');
$checks = array(
    'section_navigation_is_rendered_after_article_sections' => strpos($source, 'self::knowledge_navigation($id,$items)') !== false,
    'section_navigation_has_accessible_label' => strpos($source, 'aria-label=\"Navigate article sections\"') !== false,
    'first_and_last_section_shortcuts_exist' => strpos($source, 'First section: ') !== false && strpos($source, 'Last section: ') !== false,
    'back_to_top_link_uses_article_title_anchor' => strpos($source, 'href=\"#article-title\"') !== false,
    'topic_navigation_uses_wiki_topic_taxonomy' => strpos($source, "get_the_terms($post_id, 'wiki_topic')") !== false && strpos($source, 'get_term_link($term)') !== false,
    'related_articles_are_limited_and_exclude_current_article' => strpos($source, "'posts_per_page'=>4") !== false && strpos($source, "'post__not_in'=>array((int)$post_id)") !== false,
    'related_articles_are_published_wiki_articles' => strpos($source, "'post_type'=>'wiki_article','post_status'=>'publish'") !== false,
    'related_article_navigation_is_responsive' => strpos($source, '.bsw45-related-knowledge{columns:1}') !== false,
    'topic_links_are_escaped' => strpos($source, 'esc_url($url)') !== false && strpos($source, 'esc_html($term->name)') !== false,
);
$failed = 0;
foreach ($checks as $name => $passed) { echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL; if (!$passed) $failed++; }
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
