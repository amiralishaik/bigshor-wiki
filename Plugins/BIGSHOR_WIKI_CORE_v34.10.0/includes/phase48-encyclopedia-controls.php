<?php
/**
 * Phase 48: encyclopedia page lifecycle, protection, watch notifications and talk replies.
 * Additive module. Uses existing wiki_article, wiki_talk and WordPress comment/user systems.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase48_Encyclopedia_Controls')) {
final class BSW_Phase48_Encyclopedia_Controls {
    const VERSION = '48.0.0';
    const INBOX_META = '_bsw_notification_inbox';

    public static function boot() {
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'add_box'));
        add_action('admin_post_bsw_phase48_move_article', array(__CLASS__, 'move_article'));
        add_filter('map_meta_cap', array(__CLASS__, 'protect_edit_cap'), 20, 4);
        add_action('template_redirect', array(__CLASS__, 'resolve_redirect'), 1);
        add_action('transition_post_status', array(__CLASS__, 'notify_watchers_on_publish'), 30, 3);
        add_action('post_updated', array(__CLASS__, 'notify_watchers_on_update'), 30, 3);
        add_shortcode('bsw_notification_inbox', array(__CLASS__, 'notification_inbox'));
        add_shortcode('bsw_talk_discussions', array(__CLASS__, 'talk_discussions'));
        add_shortcode('bsw_special_pages', array(__CLASS__, 'special_pages'));
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
    }

    public static function add_box() {
        add_meta_box('bsw_phase48_controls', 'BIGSHOR Page Management', array(__CLASS__, 'render_box'), 'wiki_article', 'side', 'default');
    }

    public static function render_box($post) {
        if (!current_user_can('edit_post', $post->ID)) { echo '<p>You do not have permission to manage this article.</p>'; return; }
        $protection = get_post_meta($post->ID, '_bsw_page_protection', true);
        $redirect = get_post_meta($post->ID, '_bsw_redirect_target', true);
        wp_nonce_field('bsw_phase48_controls_' . $post->ID, 'bsw_phase48_nonce');
        echo '<p><label for="bsw_phase48_protection"><strong>Editing protection</strong></label></p><select style="width:100%" id="bsw_phase48_protection" name="bsw_phase48_protection">';
        foreach (array('none'=>'No protection','editors'=>'Editors and administrators','admins'=>'Administrators only') as $key=>$label) {
            echo '<option value="'.esc_attr($key).'" '.selected($protection, $key, false).'>'.esc_html($label).'</option>';
        }
        echo '</select><p class="description">Protection is enforced by WordPress edit capabilities, including frontend edit requests that use those capabilities.</p>';
        echo '<p><label for="bsw_phase48_redirect"><strong>Redirect target URL</strong></label><input type="url" id="bsw_phase48_redirect" name="bsw_phase48_redirect" value="'.esc_attr($redirect).'" style="width:100%" placeholder="https://..." /></p>';
        echo '<p class="description">Use only for an intentional redirect page. Clear the field to disable it.</p>';
        echo '<p><button type="submit" class="button" name="bsw_phase48_save_controls" value="1">Save page controls</button></p>';
        echo '<hr><p><strong>Move / rename article</strong></p><label class="screen-reader-text" for="bsw_phase48_new_title">New article title</label><input type="text" id="bsw_phase48_new_title" name="bsw_phase48_new_title" form="bsw-phase48-move-form" style="width:100%" placeholder="New article title" />';
        echo '<p class="description">Moves this article while keeping its post ID, revisions and talk-page association. An old-title redirect is created.</p>';
        echo '<form id="bsw-phase48-move-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="bsw_phase48_move_article"><input type="hidden" name="post_id" value="'.(int)$post->ID.'">';
        wp_nonce_field('bsw_phase48_move_' . $post->ID, 'bsw_phase48_move_nonce');
        echo '<button type="submit" class="button" onclick="return confirm(\'Move this article and create a redirect from its old title?\')">Move article</button></form>';
    }


    public static function handle_box_save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['bsw_phase48_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_phase48_nonce'])), 'bsw_phase48_controls_' . $post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $protection = isset($_POST['bsw_phase48_protection']) ? sanitize_key(wp_unslash($_POST['bsw_phase48_protection'])) : 'none';
        if (!in_array($protection, array('none','editors','admins'), true)) $protection = 'none';
        if (!current_user_can('manage_options')) {
            $existing = get_post_meta($post_id, '_bsw_page_protection', true);
            if ($protection !== $existing) return;
        }
        if ($protection === 'none') delete_post_meta($post_id, '_bsw_page_protection');
        else update_post_meta($post_id, '_bsw_page_protection', $protection);
        $redirect = isset($_POST['bsw_phase48_redirect']) ? esc_url_raw(wp_unslash($_POST['bsw_phase48_redirect'])) : '';
        if ($redirect && wp_http_validate_url($redirect) && strtolower((string)wp_parse_url($redirect, PHP_URL_HOST)) === strtolower((string)wp_parse_url(home_url('/'), PHP_URL_HOST))) update_post_meta($post_id, '_bsw_redirect_target', $redirect);
        else delete_post_meta($post_id, '_bsw_redirect_target');
    }

    public static function protect_edit_cap($caps, $cap, $user_id, $args) {
        if ($cap !== 'edit_post' || empty($args[0])) return $caps;
        $post_id = absint($args[0]);
        if (get_post_type($post_id) !== 'wiki_article') return $caps;
        $level = get_post_meta($post_id, '_bsw_page_protection', true);
        if (!$level || (int)$user_id <= 0) return $caps;
        if ($level === 'admins' && !user_can($user_id, 'manage_options')) return array('do_not_allow');
        if ($level === 'editors' && !user_can($user_id, 'edit_others_posts') && !user_can($user_id, 'manage_options')) return array('do_not_allow');
        return $caps;
    }

    public static function move_article() {
        $id = absint($_POST['post_id'] ?? 0);
        if (!$id || get_post_type($id) !== 'wiki_article') wp_die('Invalid article.');
        check_admin_referer('bsw_phase48_move_' . $id, 'bsw_phase48_move_nonce');
        if (!current_user_can('manage_options') && !current_user_can('edit_others_posts')) wp_die('You are not allowed to move articles.');
        $new_title = sanitize_text_field(wp_unslash($_POST['bsw_phase48_new_title'] ?? ''));
        if ($new_title === '') self::redirect_admin($id, 'bsw_move_empty');
        $old = get_post($id);
        if (!$old || strcasecmp($old->post_title, $new_title) === 0) self::redirect_admin($id, 'bsw_move_unchanged');
        $existing = get_page_by_title($new_title, OBJECT, 'wiki_article');
        if ($existing && (int)$existing->ID !== $id) self::redirect_admin($id, 'bsw_move_exists');
        $old_title = $old->post_title;
        $old_slug = $old->post_name;
        $updated = wp_update_post(array('ID'=>$id, 'post_title'=>$new_title, 'post_name'=>sanitize_title($new_title)), true);
        if (is_wp_error($updated)) self::redirect_admin($id, 'bsw_move_failed');
        $new_url = get_permalink($id);
        $redirect_id = wp_insert_post(array(
            'post_type'=>'wiki_article', 'post_status'=>'publish', 'post_title'=>$old_title,
            'post_name'=>sanitize_title($old_slug), 'post_content'=>'This article has moved to <a href="'.esc_url($new_url).'">'.esc_html($new_title).'</a>.',
            'post_author'=>get_current_user_id(), 'comment_status'=>'closed', 'ping_status'=>'closed'
        ), true);
        if (!is_wp_error($redirect_id)) {
            update_post_meta($redirect_id, '_bsw_redirect_target', $new_url);
            update_post_meta($redirect_id, '_bsw_redirect_created_by_move', $id);
        }
        self::log('Article moved', $id, array('old_title'=>$old_title,'new_title'=>$new_title,'redirect_id'=>is_wp_error($redirect_id)?0:$redirect_id));
        self::redirect_admin($id, 'bsw_move_success');
    }

    public static function resolve_redirect() {
        if (!is_singular('wiki_article')) return;
        $id = get_queried_object_id();
        $target = get_post_meta($id, '_bsw_redirect_target', true);
        if (!$target || !wp_http_validate_url($target)) return;
        $current = get_permalink($id);
        if ($current && untrailingslashit($current) === untrailingslashit($target)) return;
        wp_safe_redirect($target, 301, 'BIGSHOR Wiki');
        exit;
    }

    public static function notify_watchers_on_publish($new_status, $old_status, $post) {
        if ($new_status === 'publish' && $old_status !== 'publish') self::dispatch_watch_notifications($post);
    }

    public static function notify_watchers_on_update($post_id, $post_after, $post_before) {
        if (!$post_after instanceof WP_Post || !$post_before instanceof WP_Post || $post_after->post_type !== 'wiki_article') return;
        if ($post_after->post_status !== 'publish' || $post_before->post_status !== 'publish') return;
        if ($post_after->post_title === $post_before->post_title && $post_after->post_content === $post_before->post_content && $post_after->post_excerpt === $post_before->post_excerpt) return;
        self::dispatch_watch_notifications($post_after);
    }

    private static function dispatch_watch_notifications($post) {
        if (!$post instanceof WP_Post || $post->post_type !== 'wiki_article' || $post->post_status !== 'publish' || wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID)) return;
        $users = get_users(array('fields'=>'ID', 'number'=>5000));
        foreach ($users as $uid) {
            $watched = get_user_meta($uid, '_bsw_watchlist', true);
            if (!is_array($watched) || !in_array((int)$post->ID, array_map('absint', $watched), true)) continue;
            $inbox = get_user_meta($uid, self::INBOX_META, true);
            if (!is_array($inbox)) $inbox = array();
            $inbox[] = array('id'=>wp_generate_uuid4(), 'type'=>'article_update', 'post_id'=>(int)$post->ID, 'title'=>get_the_title($post), 'url'=>get_permalink($post), 'date'=>current_time('mysql'), 'read'=>false);
            update_user_meta($uid, self::INBOX_META, array_slice($inbox, -100));
        }
    }

    public static function notification_inbox() {
        if (!is_user_logged_in()) return '<p>Please sign in to view your notifications.</p>';
        $uid = get_current_user_id();
        $inbox = get_user_meta($uid, self::INBOX_META, true);
        if (!is_array($inbox) || !$inbox) return '<section class="bsw-notification-inbox"><h2>Notifications</h2><p>You have no new notifications.</p></section>';
        if (isset($_GET['bsw_notice_read'], $_GET['_wpnonce'])) {
            $notice_id = sanitize_text_field(wp_unslash($_GET['bsw_notice_read']));
            if (wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'bsw_notice_read_' . $notice_id)) {
                foreach ($inbox as &$item) if (!empty($item['id']) && hash_equals((string)$item['id'], $notice_id)) $item['read'] = true;
                unset($item); update_user_meta($uid, self::INBOX_META, $inbox);
                wp_safe_redirect(remove_query_arg(array('bsw_notice_read','_wpnonce'))); exit;
            }
        }
        $unread = 0; $out = '<section class="bsw-notification-inbox"><h2>Notifications</h2><ul>';
        foreach (array_reverse($inbox) as $item) {
            if (empty($item['read'])) $unread++;
            $title = isset($item['title']) ? $item['title'] : 'Article update';
            $url = !empty($item['url']) ? $item['url'] : '#';
            $date = !empty($item['date']) ? $item['date'] : '';
            $out .= '<li'.(empty($item['read'])?' class="is-unread"':'').'><a href="'.esc_url($url).'">'.esc_html($title).'</a> <time>'.esc_html($date).'</time>';
            if (empty($item['read']) && !empty($item['id'])) $out .= ' <a href="'.esc_url(wp_nonce_url(add_query_arg('bsw_notice_read', rawurlencode($item['id'])), 'bsw_notice_read_' . $item['id'])).'">Mark read</a>';
            $out .= '</li>';
        }
        return $out.'</ul><p>'.number_format_i18n($unread).' unread notification(s).</p></section>';
    }

    public static function talk_discussions($atts) {
        if (!is_singular('wiki_article')) return '';
        $article_id = get_queried_object_id();
        $q = new WP_Query(array('post_type'=>'wiki_talk','post_status'=>'publish','posts_per_page'=>30,'meta_key'=>'_bsw_talk_article','meta_value'=>$article_id,'orderby'=>'date','order'=>'ASC'));
        if (!$q->have_posts()) return '<section class="bsw-talk-discussions"><h2>Article discussion</h2><p>No discussion topics yet.</p></section>';
        $out = '<section class="bsw-talk-discussions"><h2>Article discussion</h2>';
        while ($q->have_posts()) { $q->the_post(); $talk_id=get_the_ID();
            $out .= '<article class="bsw-talk-topic"><h3>'.esc_html(get_the_title()).'</h3><div>'.wpautop(wp_kses_post(get_post_field('post_content', $talk_id))).'</div><div class="bsw-talk-replies">';
            $comments = get_comments(array('post_id'=>$talk_id,'status'=>'approve','order'=>'ASC','orderby'=>'comment_date_gmt'));
            if ($comments) $out .= wp_list_comments(array('style'=>'ol','short_ping'=>true,'echo'=>false), $comments);
            if (is_user_logged_in() && comments_open($talk_id)) $out .= '<div class="bsw-talk-reply-form">'.get_comment_form(array('title_reply'=>'Reply to this discussion','label_submit'=>'Post reply','logged_in_as'=>'','comment_notes_before'=>'','comment_notes_after'=>''), $talk_id).'</div>';
            elseif (!is_user_logged_in()) $out .= '<p><a href="'.esc_url(wp_login_url(get_permalink($article_id))).'">Sign in</a> to reply.</p>';
            $out .= '</div></article>';
        }
        wp_reset_postdata(); return $out.'</section>';
    }

    public static function special_pages() {
        $pages = array(
            'Recent changes' => home_url('/recent-changes/'),
            'My watchlist' => home_url('/my-watchlist/'),
            'Submit an article' => home_url('/submit-article/'),
            'Contributors' => home_url('/contributors/'),
            'Topics' => get_post_type_archive_link('wiki_article'),
            'Media library' => get_post_type_archive_link('wiki_media'),
        );
        $out = '<nav class="bsw-special-pages"><h2>BIGSHOR Wiki special pages</h2><ul>';
        foreach ($pages as $label=>$url) if ($url) $out .= '<li><a href="'.esc_url($url).'">'.esc_html($label).'</a></li>';
        return $out.'</ul></nav>';
    }

    public static function log($action, $post_id, $details = array()) {
        $message = $action . ' — article #' . (int)$post_id . ' — ' . wp_json_encode($details);
        if (post_type_exists('bsw_mod_log')) wp_insert_post(array('post_type'=>'bsw_mod_log','post_status'=>'private','post_title'=>sanitize_text_field($action . ' #' . (int)$post_id),'post_content'=>$message,'post_author'=>get_current_user_id()), true);
        else { $logs=get_option('bsw_phase48_logs',array()); if(!is_array($logs))$logs=array(); $logs[]=array('time'=>current_time('mysql'),'user'=>get_current_user_id(),'message'=>$message); update_option('bsw_phase48_logs',array_slice($logs,-250),false); }
    }

    private static function redirect_admin($id, $notice) { wp_safe_redirect(add_query_arg('bsw_phase48_notice', $notice, get_edit_post_link($id, 'url'))); exit; }

    public static function admin_notice() {
        if (empty($_GET['bsw_phase48_notice'])) return;
        $key = sanitize_key(wp_unslash($_GET['bsw_phase48_notice']));
        $messages = array('bsw_move_success'=>'Article moved. The old title now redirects to the new article.','bsw_move_empty'=>'Enter a new article title.','bsw_move_unchanged'=>'The article title was not changed.','bsw_move_exists'=>'An article with that title already exists.','bsw_move_failed'=>'The article could not be moved.');
        if (isset($messages[$key])) echo '<div class="notice notice-info is-dismissible"><p>'.esc_html($messages[$key]).'</p></div>';
    }
}
BSW_Phase48_Encyclopedia_Controls::boot();
add_action('save_post_wiki_article', array('BSW_Phase48_Encyclopedia_Controls', 'handle_box_save'), 50, 2);
}
