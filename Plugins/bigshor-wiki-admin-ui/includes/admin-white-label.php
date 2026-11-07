<?php
/**
 * BIGSHOR WIKI — Full white-label administration shell.
 * Version: 1.5.0
 *
 * Uses WordPress administration APIs and screens; does not modify WordPress core.
 */
if (!defined('ABSPATH')) exit;

add_filter('admin_body_class', function ($classes) {
    return trim($classes . ' bsw-white-label');
}, 30);

add_action('admin_enqueue_scripts', function () {
    $base = plugin_dir_url(BSW_ADMIN_UI_FILE);
    wp_enqueue_style('bsw-white-label-admin', $base . 'assets/admin-white-label.css', array(), '1.5.0');
    wp_enqueue_script('bsw-white-label-admin', $base . 'assets/admin-white-label.js', array(), '1.5.0', true);
});

function bsw_white_label_navigation_items() {
    // One canonical destination per navigation item. Creation is a contextual action in the topbar.
    return array(
        array('Overview', 'admin.php?page=bsw-control-centre', 'read', 'grid'),
        array('Articles', 'edit.php?post_type=wiki_article', 'edit_posts', 'book'),
        array('Article Studio', 'admin.php?page=bsw-article-studio', 'edit_posts', 'edit'),
        array('Editorial review', 'edit.php?post_type=wiki_article&post_status=pending', 'edit_others_posts', 'check'),
        array('Media library', 'upload.php', 'upload_files', 'image'),
        array('Pages', 'edit.php?post_type=page', 'edit_pages', 'page'),
        array('Knowledge entities', 'edit.php?post_type=bsw_entity', 'edit_posts', 'nodes'),
        array('Knowledge graph', 'admin.php?page=bsw-p5-knowledge-graph', 'edit_others_posts', 'graph'),
        array('Categories', 'edit-tags.php?taxonomy=category', 'manage_categories', 'folder'),
        array('Tags', 'edit-tags.php?taxonomy=post_tag', 'manage_categories', 'tag'),
        array('Wiki templates', 'edit.php?post_type=wiki_template', 'edit_posts', 'layers'),
        array('Talk pages', 'edit.php?post_type=wiki_talk', 'edit_posts', 'messages'),
        array('Editorial governance', 'admin.php?page=bsw-p36-governance', 'edit_others_posts', 'shield'),
        array('Moderation', 'admin.php?page=bsw-advanced-controls', 'moderate_comments', 'flag'),
        array('Deletion discussions', 'edit.php?post_type=bsw_deletion_discussion', 'edit_others_posts', 'messages'),
        array('Founder collection', 'admin.php?page=bigshor-founders', 'manage_options', 'users'),
        array('Contributors', 'users.php', 'list_users', 'users'),
        array('Comments', 'edit-comments.php', 'moderate_comments', 'messages'),
        array('Import / export', 'tools.php?page=bsw-data-portability', 'manage_options', 'database'),
        array('System readiness', 'tools.php?page=bsw-phase63-readiness', 'manage_options', 'activity'),
        array('Platform settings', 'options-general.php', 'manage_options', 'settings'),
        array('Extensions', 'plugins.php', 'activate_plugins', 'layers'),
        array('Themes & design', 'themes.php', 'edit_theme_options', 'image'),
        array('Cache management', 'admin.php?page=litespeed', 'manage_options', 'activity'),
    );
}

function bsw_white_label_current_title() {
    if (function_exists('get_admin_page_title')) {
        $title = get_admin_page_title();
        if ($title) return wp_strip_all_tags($title);
    }
    if (is_admin()) {
        global $pagenow;
        $map = array(
            'index.php' => 'Overview',
            'edit.php' => 'Content library',
            'post.php' => 'Edit content',
            'post-new.php' => 'Create content',
            'upload.php' => 'Media library',
            'users.php' => 'Contributors',
            'profile.php' => 'My profile',
            'edit-comments.php' => 'Discussion',
            'options-general.php' => 'Platform settings',
            'tools.php' => 'Platform tools',
            'plugins.php' => 'Extensions',
            'themes.php' => 'Appearance',
        );
        if (isset($map[$pagenow])) return $map[$pagenow];
    }
    return 'Workspace';
}

add_action('admin_footer', function () {
    if (!is_user_logged_in() || !current_user_can('read')) return;
    $user = wp_get_current_user();
    $initial = strtoupper(substr((string) $user->display_name, 0, 1));
    $title = bsw_white_label_current_title();
    $sections = array(
        'WORKSPACE' => array(0,1,2,3,4,5),
        'KNOWLEDGE' => array(6,7,8,9,10,11),
        'GOVERNANCE' => array(12,13,14,15,16,17),
        'PLATFORM' => array(18,19,20),
        'SYSTEM' => array(21,22,23),
    );
    $items = bsw_white_label_navigation_items();
    echo '<aside class="bsw-shell-sidebar" aria-label="BIGSHOR WIKI navigation">';
    echo '<a class="bsw-shell-brand" href="' . esc_url(admin_url('admin.php?page=bsw-control-centre')) . '"><span class="bsw-shell-mark">B</span><span><strong>BIGSHOR</strong><small>WIKI PLATFORM</small></span></a>';
    echo '<div class="bsw-shell-site"><span class="bsw-site-indicator"></span><span>' . esc_html(get_bloginfo('name')) . '</span></div><nav>';
    $rendered_urls = array();
    foreach ($sections as $heading => $indexes) {
        echo '<div class="bsw-shell-group"><span class="bsw-shell-heading">' . esc_html($heading) . '</span>';
        foreach ($indexes as $index) {
            if (!isset($items[$index])) continue;
            if ($index === 2 && !class_exists('BSW_Article_Studio')) continue;
            list($label, $path, $cap, $icon) = $items[$index];
            if (!current_user_can($cap)) continue;
            $url = admin_url($path);
            if (isset($rendered_urls[$url])) continue;
            $rendered_urls[$url] = true;
            $active = false;
            $current_file = basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
            $current_post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : '';
            if ($index === 0) $active = isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'bsw-control-centre';
            elseif ($index === 1) $active = $current_file === 'edit.php' && $current_post_type === 'wiki_article';
            elseif ($index === 2) $active = isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'bsw-article-studio';
            elseif ($index === 4) $active = $current_file === 'upload.php';
            elseif ($index === 5) $active = $current_file === 'edit.php' && $current_post_type === 'page';
            elseif ($index === 16) $active = $current_file === 'users.php';
            elseif ($index === 17) $active = $current_file === 'edit-comments.php';
            $class = $active ? ' is-current' : '';
            echo '<a class="bsw-shell-link' . esc_attr($class) . '" href="' . esc_url($url) . '"><span class="bsw-shell-icon" aria-hidden="true">' . esc_html(bsw_white_label_icon($icon)) . '</span><span>' . esc_html($label) . '</span></a>';
        }
        echo '</div>';
    }
    echo '</nav><div class="bsw-shell-sidebar-bottom"><span class="bsw-live-dot"></span> Knowledge platform active</div></aside>';
    $new_article_url = current_user_can('edit_posts') ? (class_exists('BSW_Article_Studio') ? admin_url('admin.php?page=bsw-article-studio&action=new') : admin_url('post-new.php?post_type=wiki_article')) : '';
    $is_editor_screen = in_array(basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : ''), array('post.php','post-new.php'), true);
    echo '<header class="bsw-shell-topbar"><div class="bsw-shell-top-left"><button type="button" class="bsw-shell-toggle" aria-label="Toggle navigation" aria-expanded="false">☰</button><span class="bsw-shell-page-title">' . esc_html($title) . '</span></div><div class="bsw-shell-top-right">';
    echo '<label class="bsw-nav-search"><span aria-hidden="true">⌕</span><input type="search" class="bsw-nav-search-input" placeholder="Find a menu item…" aria-label="Find a menu item"></label>';
    if ($new_article_url) echo '<a class="bsw-topbar-create" href="' . esc_url($new_article_url) . '"><span aria-hidden="true">＋</span> New article</a>';
    if ($is_editor_screen) echo '<button type="button" class="bsw-editor-mode-toggle" aria-pressed="false">Focus mode</button>';
    echo '<button type="button" class="bsw-theme-toggle" aria-label="Switch colour theme" title="Switch colour theme"><span class="bsw-theme-icon" aria-hidden="true">◐</span><span class="bsw-theme-label">Dark mode</span></button>';
    echo '<a class="bsw-view-site" href="' . esc_url(home_url('/')) . '" target="_blank" rel="noopener">View website ↗</a><span class="bsw-shell-user"><span class="bsw-shell-avatar">' . esc_html($initial) . '</span><span><strong>' . esc_html($user->display_name) . '</strong><small>Workspace account</small></span></span><a class="bsw-shell-logout" href="' . esc_url(wp_logout_url(wp_login_url())) . '">Sign out</a></div></header>';
}, 1);

function bsw_white_label_icon($key) {
    $icons = array('grid'=>'▦','book'=>'▤','plus'=>'+','check'=>'✓','image'=>'▧','nodes'=>'◎','graph'=>'⌘','page'=>'▤','menu'=>'☰','toolbox'=>'⚒','folder'=>'▰','tag'=>'⌑','layers'=>'▱','messages'=>'☷','shield'=>'⬡','flag'=>'⚑','users'=>'♙','database'=>'▤','network'=>'◉','globe'=>'◎','activity'=>'⌁','settings'=>'⚙');
    return isset($icons[$key]) ? $icons[$key] : '•';
}

/* Replace the default WordPress login identity with BIGSHOR WIKI branding. */
add_action('login_enqueue_scripts', function () {
    $logo = esc_url(plugins_url('../assets/bigshor-login-mark.svg', __FILE__));
    echo '<style>
    body.login{background:#f5f6fb!important;font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif!important}
    body.login #login h1 a{background-image:none!important;width:100%!important;height:auto!important;text-indent:0!important;font-size:25px!important;font-weight:800!important;letter-spacing:-.7px;color:#20264a!important;text-decoration:none!important;margin:0 auto 22px!important}
    body.login #login h1 a:after{content:"BIGSHOR WIKI";font-size:25px;vertical-align:middle}\n    body.login #login h1 a:before{content:"B";display:inline-grid;place-items:center;width:42px;height:42px;margin-right:11px;border-radius:12px;background:#c62828;color:#fff;font-size:23px;vertical-align:middle}
    body.login #loginform,body.login #lostpasswordform,body.login #registerform{border:1px solid #e8eaf2!important;border-radius:15px!important;box-shadow:0 12px 35px rgba(28,35,70,.07)!important}
    body.login .button-primary{background:#c62828!important;border-color:#c62828!important;text-shadow:none!important;box-shadow:none!important;border-radius:8px!important}
    body.login #backtoblog,body.login #nav{color:#777f98!important}
    body.login #login_error,body.login .message,body.login .success{border-left-color:#c62828!important}
    </style>';
});
add_filter('login_headerurl', function () { return home_url('/'); });
add_filter('login_headertext', function () { return 'BIGSHOR WIKI'; });


/* v34.1.0: remove remaining WordPress identity from the admin chrome, not from core files. */
add_filter('admin_footer_text', function () { return 'BIGSHOR WIKI · Knowledge platform'; }, 100);
add_filter('update_footer', function () { return 'BIGSHOR WIKI'; }, 100);
add_action('admin_bar_menu', function ($bar) {
    if (!is_object($bar)) return;
    $bar->remove_node('wp-logo');
    $bar->remove_node('about');
    $bar->remove_node('wporg');
    $bar->remove_node('documentation');
    $bar->remove_node('support-forums');
    $bar->remove_node('feedback');
}, 999);
add_filter('admin_title', function ($admin_title, $title) {
    return trim(wp_strip_all_tags($title)) . ' · BIGSHOR WIKI';
}, 20, 2);
add_action('admin_head', function () {
    echo '<meta name="application-name" content="BIGSHOR WIKI">';
});


/**
 * v1.5.0 workspace enhancements.
 * These enhancements improve navigation and usability of native and BIGSHOR admin screens.
 */
add_filter('admin_body_class', function ($classes) {
    global $pagenow;
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $type = ($screen && isset($screen->post_type)) ? sanitize_html_class($screen->post_type) : 'general';
    return trim($classes . ' bsw-admin-workspace bsw-screen-' . sanitize_html_class($pagenow) . ' bsw-type-' . $type);
}, 40);

add_action('admin_enqueue_scripts', function () {
    $base = plugin_dir_url(BSW_ADMIN_UI_FILE);
    wp_enqueue_style('bsw-admin-ux-v15', $base . 'assets/admin-ux-v15.css', array('bsw-white-label-admin'), '1.5.0');
    wp_enqueue_script('bsw-admin-ux-v15', $base . 'assets/admin-ux-v15.js', array('bsw-white-label-admin'), '1.5.0', true);
    wp_localize_script('bsw-admin-ux-v15', 'BSWAdminUX', array(
        'home' => admin_url('admin.php?page=bsw-control-centre'),
        'studio' => admin_url('admin.php?page=bsw-article-studio'),
        'search' => admin_url('edit.php?post_type=wiki_article'),
        'nonce' => wp_create_nonce('bsw_admin_ux'),
        'strings' => array('search' => 'Search BIGSHOR WIKI', 'noResults' => 'No matching destinations')
    ));
}, 50);

/** Add a role-aware dashboard landing page without replacing the existing Core dashboard. */
add_action('admin_menu', function () {
    add_menu_page(
        'BIGSHOR WIKI Workspace',
        'Workspace',
        'read',
        'bsw-workspace-v15',
        'bsw_admin_ux_workspace_render',
        'dashicons-screenoptions',
        2
    );
}, 5);

function bsw_admin_ux_workspace_render() {
    if (!current_user_can('read')) return;
    $counts = array();
    $types = array('wiki_article' => 'Articles', 'wiki_template' => 'Templates', 'bsw_entity' => 'Knowledge entities', 'wiki_talk' => 'Talk pages');
    foreach ($types as $type => $label) {
        if (!post_type_exists($type)) continue;
        $count = wp_count_posts($type);
        $counts[$label] = isset($count->publish) ? (int) $count->publish : 0;
    }
    $user = wp_get_current_user();
    echo '<div class="wrap bsw-workspace-v15">';
    echo '<div class="bsw-workspace-heading"><div><p class="bsw-eyebrow">YOUR EDITORIAL WORKSPACE</p><h1>Welcome back, ' . esc_html($user->display_name) . '</h1><p>Pick up where you left off or jump directly to a workspace.</p></div><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=bsw-article-studio&action=new')) . '">＋ Create article</a></div>';
    echo '<div class="bsw-workspace-grid">';
    foreach ($counts as $label => $count) {
        echo '<a class="bsw-workspace-stat" href="' . esc_url(admin_url('edit.php?post_type=' . ($label === 'Articles' ? 'wiki_article' : ($label === 'Templates' ? 'wiki_template' : ($label === 'Knowledge entities' ? 'bsw_entity' : 'wiki_talk'))))) . '"><span>' . esc_html($label) . '</span><strong>' . esc_html(number_format_i18n($count)) . '</strong><small>Published items</small></a>';
    }
    echo '</div><div class="bsw-workspace-columns"><section class="bsw-workspace-panel"><h2>Quick access</h2><div class="bsw-quick-links">';
    $links = array(
        array('Article Studio', 'admin.php?page=bsw-article-studio', 'Create and edit knowledge articles'),
        array('All articles', 'edit.php?post_type=wiki_article', 'Find, filter and manage content'),
        array('Editorial review', 'edit.php?post_type=wiki_article&post_status=pending', 'Review submitted articles'),
        array('Media library', 'upload.php', 'Find and manage uploaded files'),
        array('Contributors', 'users.php', 'Manage contributor accounts'),
        array('Categories', 'edit-tags.php?taxonomy=category', 'Organise the knowledge base')
    );
    foreach ($links as $link) {
        echo '<a href="' . esc_url(admin_url($link[1])) . '"><strong>' . esc_html($link[0]) . '</strong><span>' . esc_html($link[2]) . '</span><b>→</b></a>';
    }
    echo '</div></section><section class="bsw-workspace-panel"><h2>My workspace guidance</h2><ul class="bsw-guidance"><li><strong>Start with your tasks.</strong><span>Check assigned reviews and editorial requests.</span></li><li><strong>Use global search.</strong><span>Find content and administration destinations without navigating menus.</span></li><li><strong>Check save status.</strong><span>In Article Studio, wait for the saved confirmation before leaving.</span></li><li><strong>Respect permissions.</strong><span>Some actions are available only to editors or administrators.</span></li></ul></section></div></div>';
}
