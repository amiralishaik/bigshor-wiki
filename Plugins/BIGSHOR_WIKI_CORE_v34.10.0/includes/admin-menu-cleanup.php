<?php
/**
 * BIGSHOR Wiki — consolidated WordPress administration navigation.
 * Version: 33.8.0
 * This changes navigation only; it does not delete posts, terms, users or media.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'bsw_v338_consolidate_admin_navigation', 99999);
function bsw_v338_consolidate_admin_navigation() {
    if (!current_user_can('read')) return;

    // Keep one BIGSHOR entry point and remove duplicate/overlapping top-level links.
    $remove = array(
        'bigshor-wiki-settings',
        'edit.php',                         // Standard Posts are not the Wiki article library.
        'upload.php',                       // Reintroduced below under BIGSHOR Wiki.
        'edit.php?post_type=wiki_article',
        'edit.php?post_type=bsw_entity',
        'edit.php?post_type=wiki_entity',
        'edit.php?post_type=wiki_media',
        'edit.php?post_type=wiki_talk',
        'edit.php?post_type=wiki_template',
        'edit-comments.php',
        'users.php',
    );
    foreach ($remove as $slug) remove_menu_page($slug);

    // Remove redundant legacy submenu links from the old parent. Their target
    // screens remain available through the consolidated menu below.
    global $submenu;
    if (isset($submenu['bigshor-wiki-settings'])) unset($submenu['bigshor-wiki-settings']);

    $parent = 'bsw-control-centre';
    $items = array(
        array('Workspace', '', 'manage_options', 'bsw-v338-heading-workspace', null),
        array('Dashboard', 'admin.php?page=bsw-control-centre', 'manage_options', 'bsw-control-centre', null),
        array('Wiki Articles', 'edit.php?post_type=wiki_article', 'edit_posts', 'edit.php?post_type=wiki_article', null),
        array('Add New Article', 'post-new.php?post_type=wiki_article', 'edit_posts', 'post-new.php?post_type=wiki_article', null),
        array('Pages', 'edit.php?post_type=page', 'edit_pages', 'edit.php?post_type=page', null),

        array('Knowledge & Media', '', 'manage_options', 'bsw-v338-heading-knowledge', null),
        array('Knowledge Entities', 'edit.php?post_type=bsw_entity', 'edit_posts', 'edit.php?post_type=bsw_entity', null),
        array('Knowledge Graph', 'edit.php?post_type=wiki_article&page=bsw-p5-knowledge-graph', 'edit_others_posts', 'bsw-p5-knowledge-graph', null),
        array('Categories', 'edit-tags.php?taxonomy=category', 'manage_categories', 'edit-tags.php?taxonomy=category', null),
        array('Tags', 'edit-tags.php?taxonomy=post_tag', 'manage_categories', 'edit-tags.php?taxonomy=post_tag', null),
        array('Media Library', 'upload.php', 'upload_files', 'upload.php', null),
        array('Wiki Media Repository', 'edit.php?post_type=wiki_media', 'edit_posts', 'edit.php?post_type=wiki_media', null),
        array('Wiki Templates', 'edit.php?post_type=wiki_template', 'edit_posts', 'edit.php?post_type=wiki_template', null),
        array('Talk Pages', 'edit.php?post_type=wiki_talk', 'edit_posts', 'edit.php?post_type=wiki_talk', null),

        array('Editorial & Community', '', 'edit_others_posts', 'bsw-v338-heading-editorial', null),
        array('Editorial Workflow', 'edit.php?post_type=wiki_article&page=bsw-translation-workflow', 'edit_posts', 'bsw-translation-workflow', null),
        array('Editorial Governance', 'edit.php?post_type=wiki_article&page=bsw-p36-governance', 'edit_others_posts', 'bsw-p36-governance', null),
        array('Editorial Quality', 'admin.php?page=bsw-editorial-quality', 'edit_others_posts', 'bsw-editorial-quality', null),
        array('Comments', 'edit-comments.php', 'moderate_comments', 'edit-comments.php', null),
        array('Users & Contributors', 'users.php', 'list_users', 'users.php', null),
        array('Founder Collection', 'admin.php?page=bigshor-founders', 'manage_options', 'bigshor-founders', null),

        array('Platform & Integrations', '', 'manage_options', 'bsw-v338-heading-platform', null),
        array('Platform Settings', 'admin.php?page=bigshor-wiki-settings', 'manage_options', 'bigshor-wiki-settings', null),
        array('Moderation & Advanced Controls', 'admin.php?page=bsw-advanced-controls', 'manage_options', 'bsw-advanced-controls', null),
        array('Import / Export', 'tools.php?page=bsw-data-portability', 'manage_options', 'tools.php?page=bsw-data-portability', null),
        array('Integrations & API', 'edit.php?post_type=wiki_article&page=bsw-v13-integrations', 'manage_options', 'bsw-v13-integrations', null),
        array('Global Network', 'options-general.php?page=bsw-phase7', 'manage_options', 'bsw-phase7', null),
        array('Federation', 'options-general.php?page=bsw-phase60-federation', 'manage_options', 'bsw-phase60-federation', null),
        array('System Readiness', 'tools.php?page=bsw-phase63-readiness', 'manage_options', 'tools.php?page=bsw-phase63-readiness', null),
        array('WordPress Tools', 'tools.php', 'manage_options', 'tools.php', null),
        array('WordPress Settings', 'options-general.php', 'manage_options', 'options-general.php', null),
    );

    // Rebuild this one submenu from a curated list. Existing page hooks remain
    // registered by their original modules; only the visible menu array changes.
    $submenu[$parent] = array();
    $seen = array();
    foreach ($items as $item) {
        list($title, $target, $capability, $slug) = $item;
        if (isset($seen[$slug])) continue;
        $seen[$slug] = true;
        if ($target === '') {
            // Non-clickable visual grouping labels are intentionally omitted from
            // WordPress submenu registration; headings are rendered in the hub.
            continue;
        }
        if (!current_user_can($capability)) continue;
        $submenu[$parent][] = array($title, $capability, $slug);
    }
}
