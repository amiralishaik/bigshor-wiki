<?php
/** BIGSHOR Admin Navigation Hub — grouped entry point for existing controls. */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page(
        'BIGSHOR Control Centre',
        'BIGSHOR Control Centre',
        'manage_options',
        'bsw-control-centre',
        'bsw_render_control_centre',
        'dashicons-screenoptions',
        2
    );
    add_submenu_page('bsw-control-centre', 'Overview', 'Overview', 'manage_options', 'bsw-control-centre', 'bsw_render_control_centre');
}, 1);

function bsw_render_control_centre() {
    if (!current_user_can('manage_options')) return;
    $groups = array(
        'Content & Knowledge' => array(
            array('Wiki articles', 'edit.php?post_type=wiki_article', 'Manage knowledge articles, drafts and published pages.', 'dashicons-book'),
            array('Knowledge entities', 'edit.php?post_type=bsw_entity', 'Manage people, places, organisations, brands and other entities.', 'dashicons-networking'),
            array('News articles', 'edit.php?post_type=bsw_news', 'Manage BIGSHOR News content.', 'dashicons-media-document'),
            array('Founder collection', 'admin.php?page=bigshor-founders', 'Manage founder-related records.', 'dashicons-groups'),
            array('Categories', 'edit-tags.php?taxonomy=category', 'Organise content into a clear category hierarchy.', 'dashicons-category'),
            array('Tags', 'edit-tags.php?taxonomy=post_tag', 'Manage descriptive tags and topic labels.', 'dashicons-tag'),
        ),
        'Editorial & Review' => array(
            array('Editorial governance', 'edit.php?post_type=wiki_article&page=bsw-p36-governance', 'Policies, reviewer controls and editorial standards.', 'dashicons-shield-alt'),
            array('Editorial operations', 'edit.php?post_type=wiki_article&page=bsw-p35-operations', 'Review queues and operational workflows.', 'dashicons-clipboard'),
            array('Editorial intelligence', 'edit.php?post_type=wiki_article&page=bsw-editorial-intelligence', 'Editorial quality signals and intelligence.', 'dashicons-search'),
            array('Editorial quality', 'admin.php?page=bsw-editorial-quality', 'Quality reporting and checks.', 'dashicons-yes-alt'),
            array('Abuse rules', 'edit.php?post_type=wiki_article&page=bsw-abuse-rules', 'Configure content abuse and moderation rules.', 'dashicons-warning'),
            array('Translation workflow', 'edit.php?post_type=wiki_article&page=bsw-translation-workflow', 'Manage translated content and its review.', 'dashicons-translation'),
        ),
        'Discovery & Navigation' => array(
            array('Discovery & languages', 'edit.php?post_type=wiki_article&page=bsw-phase4-discovery', 'Configure discovery and language features.', 'dashicons-admin-site-alt3'),
            array('Knowledge tools', 'edit.php?post_type=bsw_entity&page=bsw-knowledge-tools', 'Knowledge management utilities.', 'dashicons-admin-tools'),
            array('Reconciliation', 'edit.php?post_type=bsw_entity&page=bsw-p3-reconcile', 'Review and reconcile knowledge records.', 'dashicons-randomize'),
            array('Language coverage', 'edit.php?post_type=wiki_article&page=bsw-language-coverage', 'Review language coverage and sitemap settings.', 'dashicons-translation'),
            array('Wikipedia importer', 'edit.php?post_type=wiki_article&page=bsw-wikipedia-importer', 'Import source material for editorial review.', 'dashicons-download'),
        ),
        'Community & Participation' => array(
            array('Comments & discussions', 'edit-comments.php', 'Moderate reader discussions and comments.', 'dashicons-format-chat'),
            array('Users & contributors', 'users.php', 'Manage accounts, roles and contributor access.', 'dashicons-admin-users'),
            array('Media library', 'upload.php', 'Manage uploaded images and media files.', 'dashicons-images-alt2'),
        ),
        'Platform & Integrations' => array(
            array('Platform settings', 'admin.php?page=bigshor-wiki-settings', 'Core platform configuration.', 'dashicons-admin-settings'),
            array('Advanced controls', 'admin.php?page=bsw-advanced-controls', 'Advanced platform and protection settings.', 'dashicons-admin-generic'),
            array('Integrations & API', 'edit.php?post_type=wiki_article&page=bsw-v13-integrations', 'Manage integrations and API configuration.', 'dashicons-rest-api'),
            array('Global network', 'options-general.php?page=bsw-phase7', 'Global network settings.', 'dashicons-admin-site'),
            array('BIGSHOR ecosystem', 'options-general.php?page=bsw-phase6', 'Connected platform ecosystem settings.', 'dashicons-share'),
            array('Analytics & schema', 'edit.php?post_type=wiki_article&page=bsw-p5-analytics', 'Analytics and structured data settings.', 'dashicons-chart-area'),
        ),
        'Tools & Data Management' => array(
            array('Import / Export', 'tools.php?page=bsw-data-portability', 'Back up, transfer and restore supported BIGSHOR content data.', 'dashicons-database-export'),
            array('WordPress tools', 'tools.php', 'Access standard WordPress maintenance tools.', 'dashicons-admin-tools'),
            array('Permalinks', 'options-permalink.php', 'Manage URL structure and rewrite settings.', 'dashicons-admin-links'),
        ),
    );
    echo '<div class="wrap bsw-control-centre"><h1>BIGSHOR Control Centre</h1><p class="description">A single, organised starting point for managing BIGSHOR Wiki. Existing WordPress screens and permissions remain in place.</p>';
    echo '<style>.bsw-control-centre{max-width:1440px}.bsw-hub-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:18px;margin-top:22px}.bsw-hub-group{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;min-width:0}.bsw-hub-group h2{font-size:16px;margin:0 0 12px;padding-bottom:10px;border-bottom:1px solid #eee}.bsw-hub-link{display:flex;gap:11px;padding:11px 8px;text-decoration:none;border-radius:5px;color:#1d2327}.bsw-hub-link:hover,.bsw-hub-link:focus{background:#f0f6fc;color:#135e96}.bsw-hub-link .dashicons{color:#646970;margin-top:2px}.bsw-hub-link strong{display:block;font-size:13px}.bsw-hub-link small{display:block;color:#646970;line-height:1.4;margin-top:3px}.bsw-hub-note{margin-top:18px;padding:12px 15px;background:#f6f7f7;border-left:4px solid #2271b1}</style>';
    echo '<div class="bsw-hub-grid">';
    foreach ($groups as $heading => $items) {
        echo '<section class="bsw-hub-group"><h2>' . esc_html($heading) . '</h2>';
        foreach ($items as $item) {
            $url = admin_url($item[1]);
            echo '<a class="bsw-hub-link" href="' . esc_url($url) . '"><span class="dashicons ' . esc_attr($item[3]) . '"></span><span><strong>' . esc_html($item[0]) . '</strong><small>' . esc_html($item[2]) . '</small></span></a>';
        }
        echo '</section>';
    }
    echo '</div>';
    // Dynamic directory ensures every registered submenu remains discoverable,
    // including modules added by later releases or third-party integrations.
    global $submenu, $menu;
    $parent_labels = array();
    if (is_array($menu)) foreach ($menu as $entry) { if (!empty($entry[2]) && !empty($entry[0])) $parent_labels[$entry[2]] = wp_strip_all_tags($entry[0]); }
    $registered = array();
    if (is_array($submenu)) foreach ($submenu as $parent => $entries) {
        foreach ((array) $entries as $entry) {
            if (empty($entry[2]) || !current_user_can(isset($entry[1]) ? $entry[1] : 'manage_options')) continue;
            $slug = $entry[2];
            if ($slug === 'bsw-control-centre' || isset($registered[$slug])) continue;
            $registered[$slug] = true;
            $label = wp_strip_all_tags($entry[0]);
            $group = isset($parent_labels[$parent]) ? $parent_labels[$parent] : 'Other administration';
            if (!isset($dynamic[$group])) $dynamic[$group] = array();
            $dynamic[$group][] = array($label, $parent, $slug);
        }
    }
    if (!empty($dynamic)) {
        echo '<section class="bsw-hub-group" style="margin-top:20px"><h2>Complete administration directory</h2><p class="description">Automatically lists the remaining registered administration screens available to your account.</p><div class="bsw-hub-grid">';
        foreach ($dynamic as $group => $entries) {
            echo '<div><h3>' . esc_html($group) . '</h3>';
            foreach ($entries as $entry) {
                if (strpos($entry[2], 'http') === 0) $url = $entry[2];
                elseif (strpos($entry[2], '.php') !== false) $url = admin_url($entry[2]);
                elseif (strpos($entry[1], '.php') !== false) $url = add_query_arg('page', $entry[2], admin_url($entry[1]));
                else $url = admin_url('admin.php?page=' . rawurlencode($entry[2]));
                echo '<p><a href="' . esc_url($url) . '">' . esc_html($entry[0]) . '</a></p>';
            }
            echo '</div>';
        }
        echo '</div></section>';
    }
    echo '<p class="bsw-hub-note"><strong>Navigation note:</strong> This hub groups existing controls and dynamically lists registered administration screens. It does not remove native menus or change editorial permissions.</p></div>';
}
