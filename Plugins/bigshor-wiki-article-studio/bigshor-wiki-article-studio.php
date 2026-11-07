<?php
/**
 * Plugin Name: BIGSHOR WIKI Article Studio
 * Description: Custom visual and wiki-source article editor for BIGSHOR WIKI.
 * Version: 1.3.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: BIGSHOR WIKI
 * Text Domain: bigshor-wiki-article-studio
 */
if (!defined('ABSPATH')) exit;

define('BSW_STUDIO_VERSION', '1.3.0');
define('BSW_STUDIO_FILE', __FILE__);

final class BSW_Article_Studio {
    const PAGE = 'bsw-article-studio';
    const NONCE = 'bsw_studio_save_article';
    const SOURCE_META = '_bsw_phase6_sources';
    const RECOVERY_META = '_bsw_studio_server_recovery';
    const COMMENTS_META = '_bsw_studio_editorial_comments';
    const SUGGESTIONS_META = '_bsw_studio_suggestions';
    const ACTIVITY_META = '_bsw_studio_activity';
    const ASSIGNEE_META = '_bsw_studio_assignees';

    public static function init() {
        add_action('plugins_loaded', array(__CLASS__, 'load'), 30);
        add_action('admin_menu', array(__CLASS__, 'menu'), 40);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_post_bsw_studio_save', array(__CLASS__, 'save'));
        add_action('wp_ajax_bsw_studio_autosave', array(__CLASS__, 'ajax_autosave'));
        add_action('wp_ajax_bsw_studio_presence', array(__CLASS__, 'ajax_presence'));
        add_action('wp_ajax_bsw_studio_comment', array(__CLASS__, 'ajax_comment'));
        add_action('wp_ajax_bsw_studio_suggestion', array(__CLASS__, 'ajax_suggestion'));
        add_action('wp_ajax_bsw_studio_recovery', array(__CLASS__, 'ajax_recovery'));
        add_action('wp_ajax_bsw_studio_review_item', array(__CLASS__, 'ajax_review_item'));
        add_action('wp_ajax_bsw_studio_assign', array(__CLASS__, 'ajax_assign'));
        add_action('load-post-new.php', array(__CLASS__, 'redirect_new'));
        add_action('load-post.php', array(__CLASS__, 'redirect_edit'));
        add_filter('get_edit_post_link', array(__CLASS__, 'edit_link'), 20, 3);
    }

    public static function load() {
        if (!defined('BSW_CORE_PLUGIN_VERSION') || version_compare(BSW_CORE_PLUGIN_VERSION, '34.2.0', '<')) {
            add_action('admin_notices', function () { if (current_user_can('activate_plugins')) echo '<div class="notice notice-error"><p><strong>BIGSHOR WIKI Article Studio</strong> requires BIGSHOR WIKI Core v34.2.0 or later.</p></div>'; });
        }
    }

    public static function menu() {
        if (!defined('BSW_CORE_PLUGIN_VERSION') || version_compare(BSW_CORE_PLUGIN_VERSION, '34.2.0', '<')) return;
        add_menu_page('Article Studio', 'Article Studio', 'edit_posts', self::PAGE, array(__CLASS__, 'render'), 'dashicons-edit-page', 3);
    }

    public static function redirect_new() {
        if (!isset($_GET['post_type']) || sanitize_key(wp_unslash($_GET['post_type'])) !== 'wiki_article') return;
        if (!current_user_can('edit_posts')) return;
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&action=new'));
        exit;
    }

    public static function redirect_edit() {
        if (isset($_GET['action']) && sanitize_key(wp_unslash($_GET['action'])) !== 'edit') return;
        if (!isset($_GET['post'])) return;
        $id = absint($_GET['post']);
        if (!$id || get_post_type($id) !== 'wiki_article' || !current_user_can('edit_post', $id)) return;
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&id=' . $id));
        exit;
    }

    public static function edit_link($link, $post_id, $context) {
        if (get_post_type($post_id) !== 'wiki_article' || !current_user_can('edit_post', $post_id)) return $link;
        return admin_url('admin.php?page=' . self::PAGE . '&id=' . absint($post_id));
    }

    public static function assets($hook) {
        if (strpos((string)$hook, self::PAGE) === false) return;
        wp_enqueue_editor();
        wp_enqueue_media();
        wp_enqueue_style('bsw-studio', plugins_url('assets/article-studio.css', __FILE__), array(), BSW_STUDIO_VERSION);
        wp_enqueue_script('bsw-studio', plugins_url('assets/article-studio.js', __FILE__), array('jquery', 'wp-tinymce'), BSW_STUDIO_VERSION, true);
        wp_localize_script('bsw-studio', 'BSWStudio', array(
            'postId' => isset($_GET['id']) ? absint($_GET['id']) : 0,
            'storageKey' => 'bsw-studio-recovery-' . get_current_user_id() . '-' . (isset($_GET['id']) ? absint($_GET['id']) : 'new'),
            'newStorageKey' => 'bsw-studio-recovery-' . get_current_user_id() . '-new',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'ajaxNonce' => wp_create_nonce('bsw_studio_phase2'),
            'userName' => wp_get_current_user()->display_name,
            'collaborationMode' => 'presence-preview',
            'comments' => ($id = (isset($_GET['id']) ? absint($_GET['id']) : 0)) ? (get_post_meta($id, self::COMMENTS_META, true) ?: array()) : array(),
            'suggestions' => ($id) ? (get_post_meta($id, self::SUGGESTIONS_META, true) ?: array()) : array(),
            'activity' => ($id) ? (get_post_meta($id, self::ACTIVITY_META, true) ?: array()) : array(),
            'assignees' => ($id) ? (get_post_meta($id, self::ASSIGNEE_META, true) ?: array()) : array(),
            'canReview' => current_user_can('edit_others_posts'),
        ));
    }

    private static function current_post() {
        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if (!$id) return null;
        $post = get_post($id);
        if (!$post || $post->post_type !== 'wiki_article' || !current_user_can('edit_post', $id)) return null;
        return $post;
    }

    private static function infobox_lines($data) {
        $fields = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : array();
        $lines = array();
        foreach ($fields as $key => $value) if (is_scalar($value)) $lines[] = ucwords(str_replace(array('_','-'), ' ', (string)$key)) . ': ' . (string)$value;
        return implode("\n", $lines);
    }

    public static function render() {
        if (!defined('BSW_CORE_PLUGIN_VERSION') || version_compare(BSW_CORE_PLUGIN_VERSION, '34.2.0', '<')) {
            echo '<div class="wrap"><h1>BIGSHOR WIKI Article Studio</h1><div class="notice notice-error"><p>Activate BIGSHOR WIKI Core first.</p></div></div>';
            return;
        }
        $post = self::current_post();
        if (isset($_GET['id']) && !$post) wp_die(esc_html__('You cannot edit this article.', 'bigshor-wiki-article-studio'), 'Forbidden', array('response'=>403));
        if (!$post && !current_user_can('edit_posts')) wp_die('You cannot create articles.', 'Forbidden', array('response'=>403));
        $id = $post ? $post->ID : 0;
        $title = $post ? $post->post_title : '';
        $content = $post ? $post->post_content : '';
        $excerpt = $post ? $post->post_excerpt : '';
        $slug = $post ? $post->post_name : '';
        $modified = $post ? $post->post_modified_gmt : '';
        $status = $post ? $post->post_status : 'draft';
        $infobox = $post ? get_post_meta($id, '_bsw_infobox', true) : array();
        if (!is_array($infobox)) $infobox = array();
        $sources = $post ? get_post_meta($id, self::SOURCE_META, true) : array();
        if (!is_array($sources)) $sources = array();
        $sources_json = wp_json_encode($sources, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $topics = $id && taxonomy_exists('wiki_topic') ? wp_get_object_terms($id, 'wiki_topic', array('fields'=>'ids')) : array();
        $topic_terms = taxonomy_exists('wiki_topic') ? get_terms(array('taxonomy'=>'wiki_topic','hide_empty'=>false)) : array();
        $language = $post ? get_post_meta($id, '_bsw_language_code', true) : 'en';
        $review_status = $post ? get_post_meta($id, '_bsw_review_status', true) : 'unreviewed';
        $seo_title = $post ? get_post_meta($id, '_bsw_p5_seo_title', true) : '';
        $seo_description = $post ? get_post_meta($id, '_bsw_p5_seo_description', true) : '';
        $featured = $post ? get_post_thumbnail_id($id) : 0;
        $featured_url = $featured ? wp_get_attachment_image_url($featured, 'medium') : '';
        $source_text = $content;
        if ($post && class_exists('BSW_Phase42_Wikitext')) $source_text = BSW_Phase42_Wikitext::html_to_wikitext($content);
        $visual_id = 'bsw_studio_visual_content';
        $notice = isset($_GET['bsw_studio_saved']) ? sanitize_key(wp_unslash($_GET['bsw_studio_saved'])) : '';
        echo '<div class="bsw-studio-app">';
        echo '<header class="bsw-studio-header"><div class="bsw-studio-brand"><span class="bsw-studio-mark">B</span><span><strong>BIGSHOR WIKI</strong><small>ARTICLE STUDIO</small></span></div><div class="bsw-studio-head-meta"><span class="bsw-studio-status"><i></i>'.esc_html(ucfirst($status)).'</span><a class="bsw-studio-quiet" href="'.esc_url(admin_url('edit.php?post_type=wiki_article')).'">All articles</a>'.($post?'<a class="bsw-studio-quiet" target="_blank" rel="noopener" href="'.esc_url(get_preview_post_link($post)).'">Preview article ↗</a>':'').'</div></header>';
        if ($notice === '1') echo '<div class="bsw-studio-notice success">Article saved. Your changes are stored in BIGSHOR WIKI.</div>';
        if ($notice === 'pending') echo '<div class="bsw-studio-notice success">Article submitted for editorial review.</div>';
        echo '<form id="bsw-studio-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="bsw_studio_save"><input type="hidden" name="post_id" value="'.esc_attr($id).'"><input type="hidden" name="modified_gmt" value="'.esc_attr($modified).'"><input type="hidden" name="featured_image_id" id="bsw_featured_image_id" value="'.esc_attr($featured).'">';
        wp_nonce_field(self::NONCE, 'bsw_studio_nonce');
        echo '<div class="bsw-studio-toolbar"><div class="bsw-studio-toolbar-left"><span class="bsw-studio-breadcrumb">Workspace</span><span class="bsw-studio-sep">/</span><strong>'.($post?'Edit article':'New article').'</strong><span class="bsw-studio-save-indicator" id="bsw-studio-save-indicator">All changes saved</span><span class="bsw-presence-indicator" id="bsw-presence-indicator">Collaboration ready · 1 editor</span></div><div class="bsw-studio-toolbar-actions"><button type="button" class="bsw-btn bsw-btn-light" id="bsw-preview-toggle">Preview</button><button type="submit" name="save_intent" value="draft" class="bsw-btn bsw-btn-light">Save draft</button><button type="submit" name="save_intent" value="submit" class="bsw-btn bsw-btn-red">'.(current_user_can('publish_posts')?'Publish article':'Submit for review').'</button></div></div>';
        echo '<div class="bsw-studio-layout"><aside class="bsw-studio-outline"><div class="bsw-outline-head">ARTICLE OUTLINE</div><nav id="bsw-article-outline"><p class="bsw-outline-empty">Add headings to build your outline.</p></nav></aside><main class="bsw-studio-main"><div class="bsw-title-area"><label for="bsw_article_title">Article title</label><input id="bsw_article_title" name="article_title" type="text" maxlength="240" required value="'.esc_attr($title).'" placeholder="Enter a clear, descriptive article title"><div class="bsw-slug-row"><span>URL slug</span><input name="article_slug" value="'.esc_attr($slug).'" placeholder="generated-from-title"></div><textarea name="article_excerpt" rows="2" maxlength="500" placeholder="Write a concise article introduction or summary...">'.esc_textarea($excerpt).'</textarea></div>';
        echo '<div class="bsw-editor-card"><div class="bsw-editor-card-head"><div><strong>Article content</strong><small>Write clearly, structure the article and cite important claims.</small><div class="bsw-inline-tools"><button type="button" id="bsw-insert-citation">＋ Citation</button><button type="button" id="bsw-insert-wikilink">＋ Wiki link</button><button type="button" id="bsw-insert-table">＋ Table</button><button type="button" id="bsw-insert-template">＋ Template</button></div></div><div class="bsw-mode-switch" role="group" aria-label="Editing mode"><button type="button" class="is-active" data-mode="visual">Visual</button><button type="button" data-mode="source">Wiki source</button></div></div><div id="bsw-visual-panel" class="bsw-editor-panel">';
        wp_editor($content, $visual_id, array('textarea_name'=>'visual_content','textarea_rows'=>24,'media_buttons'=>true,'teeny'=>false,'quicktags'=>false,'tinymce'=>array('toolbar1'=>'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo','toolbar2'=>'alignleft,aligncenter,alignright,outdent,indent,removeformat,wp_adv','content_style'=>'body{font-family:Inter,Arial,sans-serif;font-size:16px;line-height:1.75;color:#222b38;max-width:820px;margin:28px auto;padding:0 24px} h2,h3{color:#172033}')));
        echo '</div><div id="bsw-source-panel" class="bsw-editor-panel" hidden><div class="bsw-source-help">Wiki source supports headings, bold, italic, internal and external links, lists and references. Switching modes converts the supported common syntax; advanced templates and parser constructs should be edited in source mode to preserve their exact syntax.</div><textarea id="bsw_source_content" name="source_content" spellcheck="true">'.esc_textarea($source_text).'</textarea></div><input type="hidden" name="editor_mode" id="bsw_editor_mode" value="visual"><div class="bsw-editor-footer"><span id="bsw-word-count">0 words</span><span id="bsw-read-time">1 min read</span><span id="bsw-local-state">Local recovery is enabled</span></div></div>';
        echo '<section class="bsw-studio-section"><div class="bsw-section-title"><div><strong>Infobox</strong><small>Structured facts displayed alongside the article.</small></div></div><div class="bsw-infobox-grid"><label>Infobox title<input name="infobox_title" value="'.esc_attr($infobox['title']??'').'" placeholder="Person, organisation, place..."></label><label>Image URL<input name="infobox_image" type="url" value="'.esc_attr(is_string($infobox['image']??'')?$infobox['image']:'').'" placeholder="https://..."></label><label class="wide">Fields <small>One field per line in Label: Value format.</small><textarea name="infobox_fields" rows="5" placeholder="Founded: 2020&#10;Headquarters: Dubai">'.esc_textarea(self::infobox_lines($infobox)).'</textarea></label></div></section>';
        echo '<section class="bsw-studio-section"><div class="bsw-section-title"><div><strong>Sources & citations</strong><small>Reusable references for claims in the article.</small></div><button type="button" class="bsw-btn bsw-btn-light" id="bsw-source-example">Insert example</button></div><textarea name="sources_json" id="bsw_sources_json" rows="8" spellcheck="false">'.esc_textarea($sources_json?:'[]').'</textarea><p class="bsw-field-help">Use unique source IDs and cite them in the body with <code>[bsw_cite id="source-1"]</code>. This editor stores source records using the existing BIGSHOR citation metadata key.</p></section>';
        echo '<section class="bsw-studio-section bsw-phase2-tools"><div class="bsw-section-title"><div><strong>Reference manager</strong><small>Add and manage structured references without hand-editing JSON.</small></div><button type="button" class="bsw-btn bsw-btn-light" id="bsw-add-reference">＋ Add reference</button></div><div id="bsw-reference-list" class="bsw-reference-list"></div><p class="bsw-field-help">Reference IDs are used in article citations. A source URL is stored as supplied; this editor does not certify source accuracy.</p></section>';
        echo '<section class="bsw-studio-section bsw-phase2-tools"><div class="bsw-section-title"><div><strong>Editorial discussion</strong><small>Select text in the editor to anchor a comment or proposed replacement.</small></div></div><div class="bsw-anchored-compose"><label>Selected text / anchor<input id="bsw-anchor-quote" readonly placeholder="Select text in the article first (optional)"></label><label>Comment<textarea id="bsw-comment-text" rows="2" placeholder="Comment on the selected passage…"></textarea></label><button type="button" class="bsw-btn bsw-btn-light" id="bsw-add-comment">Add anchored comment</button></div><div id="bsw-editorial-comments" class="bsw-editorial-list"><p>Loading discussion…</p></div><div class="bsw-suggestion-form"><label>Suggested replacement text<textarea id="bsw-suggestion-text" rows="3" placeholder="Propose a replacement for the selected passage…"></textarea></label><button type="button" class="bsw-btn bsw-btn-light" id="bsw-add-suggestion">Submit suggestion</button></div><div id="bsw-suggestion-list" class="bsw-editorial-list"></div></section><section class="bsw-studio-section bsw-phase3-tools"><div class="bsw-section-title"><div><strong>Review assignments</strong><small>Assign editors and reviewers to this article.</small></div></div><label>Assigned reviewers<select id="bsw-review-assignees" multiple size="4">'; $assigned = $id ? (array)get_post_meta($id, self::ASSIGNEE_META, true) : array(); foreach (get_users(array('capability'=>'edit_posts','fields'=>array('ID','display_name'))) as $reviewer) echo '<option value="'.absint($reviewer->ID).'" '.selected(in_array((int)$reviewer->ID, array_map('intval',$assigned), true), true, false).'>'.esc_html($reviewer->display_name).'</option>'; echo '</select></label><button type="button" class="bsw-btn bsw-btn-light" id="bsw-save-assignees">Save assignments</button><div id="bsw-assignment-state" class="bsw-field-help"></div></section><section class="bsw-studio-section bsw-phase3-tools"><div class="bsw-section-title"><div><strong>Editorial activity</strong><small>Chronological record of collaboration and review actions.</small></div></div><div id="bsw-activity-timeline" class="bsw-editorial-list"></div></section>';
        echo '<section class="bsw-studio-section bsw-edit-summary"><div class="bsw-section-title"><div><strong>Edit summary</strong><small>Briefly explain the changes made in this revision.</small></div></div><input type="text" name="edit_summary" maxlength=240 placeholder="Describe your changes..."></section>';
        echo '<div id="bsw-live-preview" class="bsw-live-preview" hidden><div class="bsw-preview-head"><strong>Article preview</strong><button type="button" class="bsw-btn bsw-btn-light" id="bsw-preview-close">Close</button></div><div id="bsw-preview-content"></div></div>';
        echo '</main><aside class="bsw-studio-sidebar"><div class="bsw-sidebar-card"><div class="bsw-sidebar-card-head"><strong>Publishing</strong><span class="bsw-status-pill">'.esc_html(ucfirst($status)).'</span></div><label>Language<select name="article_language">';
        $languages = array('en'=>'English','ar'=>'Arabic','hi'=>'Hindi','ur'=>'Urdu','fr'=>'French','es'=>'Spanish','de'=>'German','zh'=>'Chinese');
        foreach ($languages as $code=>$label) echo '<option value="'.esc_attr($code).'" '.selected($language?:'en',$code,false).'>'.esc_html($label).'</option>';
        echo '</select></label><label>Article topics<select name="article_topic[]" multiple size=4 aria-label="Article topics">';
        if (is_array($topic_terms)) foreach ($topic_terms as $term) echo '<option value="'.(int)$term->term_id.'" '.selected(in_array((int)$term->term_id,(array)$topics,true),true,false).'>'.esc_html($term->name).'</option>';
        echo '</select></label>';
        if (current_user_can('edit_others_posts')) { echo '<label>Editorial status<select name="editorial_status">'; foreach (array('unreviewed'=>'Unreviewed','in_review'=>'In review','reviewed'=>'Reviewed','needs_sources'=>'Needs sources','disputed'=>'Disputed') as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($review_status?:'unreviewed',$key,false).'>'.esc_html($label).'</option>'; echo '</select></label>'; }
        echo '<div class="bsw-sidebar-separator"></div><div class="bsw-sidebar-subhead">Featured image</div><div class="bsw-featured-preview" id="bsw-featured-preview">'.($featured_url?'<img src="'.esc_url($featured_url).'" alt="">':'<span>Choose an image for this article</span>').'</div><button type="button" class="bsw-btn bsw-btn-light bsw-btn-full" id="bsw-select-featured">'.($featured?'Change image':'Select featured image').'</button><button type="button" class="bsw-text-button" id="bsw-remove-featured" '.(!$featured?'hidden':'').'>Remove image</button></div>';
        echo '<div class="bsw-sidebar-card"><div class="bsw-sidebar-card-head"><strong>SEO & discovery</strong></div><label>Search title<input name="seo_title" maxlength="180" value="'.esc_attr($seo_title).'" placeholder="Optional search title"></label><label>Meta description<textarea name="seo_description" rows="4" maxlength="180" placeholder="Describe this article for search results">'.esc_textarea($seo_description).'</textarea></label><div class="bsw-character-meter"><span id="bsw-seo-count">0 / 180</span></div></div>';
        echo '<div class="bsw-sidebar-card"><div class="bsw-sidebar-card-head"><strong>Editorial checklist</strong></div><ul class="bsw-checklist"><li><span>✓</span> Clear article title</li><li><span>✓</span> Structured sections</li><li><span>✓</span> Reliable references</li><li><span>✓</span> Neutral encyclopedic tone</li><li><span>✓</span> Image alt text</li></ul><p class="bsw-sidebar-footnote">Checklist is guidance only; it does not certify article accuracy or automatically approve publication.</p></div>';
        echo '</aside></div></form><footer class="bsw-studio-footer"><span>BIGSHOR WIKI Article Studio</span><span>Autosave recovery · Revision history retained by WordPress</span></footer></div>';
    }

    private static function ajax_guard($post_id = 0) {
        check_ajax_referer('bsw_studio_phase2', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(array('message'=>'Permission denied.'), 403);
        if ($post_id && (get_post_type($post_id) !== 'wiki_article' || !current_user_can('edit_post', $post_id))) wp_send_json_error(array('message'=>'You cannot access this article.'), 403);
    }

    public static function ajax_autosave() {
        $id = absint($_POST['post_id'] ?? 0); self::ajax_guard($id);
        if (!$id) wp_send_json_error(array('message'=>'Save the article once before server recovery is enabled.'), 400);
        $mode = sanitize_key(wp_unslash($_POST['mode'] ?? 'visual'));
        $content = wp_unslash($_POST['content'] ?? '');
        if (!in_array($mode, array('visual','source'), true) || strlen($content) > 2000000) wp_send_json_error(array('message'=>'Invalid or oversized recovery data.'), 413);
        $record = array('user_id'=>get_current_user_id(),'mode'=>$mode,'content'=>$content,'title'=>sanitize_text_field(wp_unslash($_POST['title'] ?? '')),'updated'=>time());
        update_post_meta($id, self::RECOVERY_META . '_' . get_current_user_id(), $record);
        $key = 'bsw_studio_presence_' . $id;
        $presence = get_transient($key); if (!is_array($presence)) $presence = array();
        $presence[get_current_user_id()] = array('name'=>wp_get_current_user()->display_name,'seen'=>time());
        set_transient($key, $presence, 90);
        wp_send_json_success(array('saved'=>true,'updated'=>time(),'message'=>'Server recovery saved.'));
    }

    public static function ajax_recovery() {
        $id = absint($_POST['post_id'] ?? 0); self::ajax_guard($id);
        if (!$id) wp_send_json_error(array('message'=>'No saved article.'), 400);
        $record = get_post_meta($id, self::RECOVERY_META . '_' . get_current_user_id(), true);
        if (!is_array($record) || empty($record['updated']) || time() - (int)$record['updated'] > 604800) wp_send_json_success(array('recovery'=>null));
        wp_send_json_success(array('recovery'=>$record));
    }

    public static function ajax_presence() {
        $id = absint($_POST['post_id'] ?? 0); self::ajax_guard($id);
        if (!$id) wp_send_json_success(array('enabled'=>false,'users'=>array(),'message'=>'Presence starts after the first save.'));
        $key = 'bsw_studio_presence_' . $id; $presence = get_transient($key); if (!is_array($presence)) $presence = array();
        $uid = get_current_user_id(); $presence[$uid] = array('name'=>wp_get_current_user()->display_name,'seen'=>time());
        foreach ($presence as $user_id=>$person) if (time() - (int)($person['seen'] ?? 0) > 60) unset($presence[$user_id]);
        set_transient($key, $presence, 90);
        $users = array_values($presence);
        wp_send_json_success(array('enabled'=>false,'presence'=>true,'users'=>$users,'message'=>'Presence heartbeat is active. Simultaneous editing is not enabled in this release.'));
    }

    public static function ajax_comment() {
        $id = absint($_POST['post_id'] ?? 0); self::ajax_guard($id);
        $text = sanitize_textarea_field(wp_unslash($_POST['comment'] ?? ''));
        if (!$id || $text === '' || strlen($text) > 5000) wp_send_json_error(array('message'=>'Enter a comment of up to 5,000 characters.'), 400);
        $items = get_post_meta($id, self::COMMENTS_META, true); if (!is_array($items)) $items = array();
        $quote = sanitize_text_field(wp_unslash($_POST['quote'] ?? '')); $items[] = array('id'=>wp_generate_uuid4(),'user_id'=>get_current_user_id(),'author'=>wp_get_current_user()->display_name,'text'=>$text,'quote'=>$quote,'status'=>'open','created'=>current_time('mysql', true));
        update_post_meta($id, self::COMMENTS_META, array_slice($items, -200)); self::log_activity($id, 'Editorial comment added', $quote ? 'Anchored: '.$quote : 'General comment');
        wp_send_json_success(array('items'=>get_post_meta($id, self::COMMENTS_META, true),'activity'=>get_post_meta($id,self::ACTIVITY_META,true)));
    }

    public static function ajax_suggestion() {
        $id = absint($_POST['post_id'] ?? 0); self::ajax_guard($id);
        $text = sanitize_textarea_field(wp_unslash($_POST['suggestion'] ?? ''));
        if (!$id || $text === '' || strlen($text) > 10000) wp_send_json_error(array('message'=>'Enter a suggested change of up to 10,000 characters.'), 400);
        $items = get_post_meta($id, self::SUGGESTIONS_META, true); if (!is_array($items)) $items = array();
        $quote = sanitize_text_field(wp_unslash($_POST['quote'] ?? '')); $items[] = array('id'=>wp_generate_uuid4(),'user_id'=>get_current_user_id(),'author'=>wp_get_current_user()->display_name,'text'=>$text,'quote'=>$quote,'status'=>'pending','created'=>current_time('mysql', true));
        update_post_meta($id, self::SUGGESTIONS_META, array_slice($items, -100)); self::log_activity($id, 'Suggested change submitted', $quote ? 'Anchored: '.$quote : 'General suggestion');
        wp_send_json_success(array('items'=>get_post_meta($id, self::SUGGESTIONS_META, true),'activity'=>get_post_meta($id,self::ACTIVITY_META,true)));
    }

    private static function log_activity($id, $event, $detail = '') {
        $items = get_post_meta($id, self::ACTIVITY_META, true); if (!is_array($items)) $items = array();
        $items[] = array('id'=>wp_generate_uuid4(),'user'=>wp_get_current_user()->display_name,'event'=>sanitize_text_field($event),'detail'=>sanitize_text_field($detail),'created'=>current_time('mysql', true));
        update_post_meta($id, self::ACTIVITY_META, array_slice($items, -300));
    }

    public static function ajax_review_item() {
        $id = absint($_POST['post_id'] ?? 0); self::ajax_guard($id);
        if (!current_user_can('edit_others_posts')) wp_send_json_error(array('message'=>'Reviewer permission required.'), 403);
        $kind = sanitize_key(wp_unslash($_POST['kind'] ?? '')); $item_id = sanitize_text_field(wp_unslash($_POST['item_id'] ?? '')); $decision = sanitize_key(wp_unslash($_POST['decision'] ?? ''));
        $meta = $kind === 'suggestion' ? self::SUGGESTIONS_META : ($kind === 'comment' ? self::COMMENTS_META : '');
        if (!$meta || !in_array($decision, array('accepted','rejected','resolved'), true)) wp_send_json_error(array('message'=>'Invalid review action.'), 400);
        $items = get_post_meta($id, $meta, true); if (!is_array($items)) $items = array(); $found = false;
        foreach ($items as &$item) if (($item['id'] ?? '') === $item_id) { $item['status'] = $decision; $item['reviewed_by'] = wp_get_current_user()->display_name; $item['reviewed'] = current_time('mysql', true); $found = true; break; } unset($item);
        if (!$found) wp_send_json_error(array('message'=>'Discussion item not found.'), 404);
        update_post_meta($id, $meta, $items); self::log_activity($id, ucfirst($kind).' '.$decision, 'Item '.$item_id);
        wp_send_json_success(array('items'=>$items,'activity'=>get_post_meta($id,self::ACTIVITY_META,true)));
    }

    public static function ajax_assign() {
        $id = absint($_POST['post_id'] ?? 0); self::ajax_guard($id);
        if (!current_user_can('edit_others_posts')) wp_send_json_error(array('message'=>'Assignment permission required.'), 403);
        $raw_ids = wp_unslash($_POST['user_ids'] ?? ''); if (is_string($raw_ids)) $raw_ids = $raw_ids === '' ? array() : explode(',', $raw_ids); $ids = array_values(array_unique(array_filter(array_map('absint', (array)$raw_ids))));
        $valid = array(); foreach ($ids as $uid) if (user_can($uid, 'edit_posts')) $valid[] = $uid;
        update_post_meta($id, self::ASSIGNEE_META, $valid); self::log_activity($id, 'Review assignments updated', count($valid).' reviewer(s) assigned');
        wp_send_json_success(array('user_ids'=>$valid,'activity'=>get_post_meta($id,self::ACTIVITY_META,true)));
    }

    public static function save() {
        if (!current_user_can('edit_posts')) wp_die('You are not allowed to create articles.', 'Forbidden', array('response'=>403));
        if (!isset($_POST['bsw_studio_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_studio_nonce'])), self::NONCE)) wp_die('Security check failed.', 'Forbidden', array('response'=>403));
        $id = absint($_POST['post_id'] ?? 0);
        $existing = $id ? get_post($id) : null;
        if ($id && (!$existing || $existing->post_type !== 'wiki_article' || !current_user_can('edit_post', $id))) wp_die('You cannot edit this article.', 'Forbidden', array('response'=>403));
        if ($existing) {
            $expected = sanitize_text_field(wp_unslash($_POST['modified_gmt'] ?? ''));
            if ($expected && !hash_equals((string)$existing->post_modified_gmt, (string)$expected)) wp_die('This article was changed after you opened it. Reload the editor and reapply your edits to prevent overwriting another revision.', 'Edit conflict', array('response'=>409));
        }
        $title = sanitize_text_field(wp_unslash($_POST['article_title'] ?? ''));
        if ($title === '') wp_die('An article title is required.', 'Invalid article', array('response'=>400));
        $mode = sanitize_key(wp_unslash($_POST['editor_mode'] ?? 'visual'));
        $raw = wp_unslash($_POST[$mode === 'source' ? 'source_content' : 'visual_content'] ?? '');
        if (strlen($raw) > 2000000) wp_die('Article content exceeds the 2 MB editor limit.', 'Content too large', array('response'=>413));
        if ($mode === 'source') {
            if (class_exists('BSW_Phase42_Wikitext')) $content = BSW_Phase42_Wikitext::wikitext_to_html($raw);
            elseif (class_exists('BSW_Phase47_Parser')) $content = BSW_Phase47_Parser::render($raw);
            else $content = wpautop(esc_html($raw));
        } else $content = wp_kses_post($raw);
        $sources_raw = wp_unslash($_POST['sources_json'] ?? '[]');
        if (trim($sources_raw) === '') $sources_raw = '[]';
        if (strlen($sources_raw) > 200000) wp_die('Source records exceed the 200 KB safety limit.', 'Source data too large', array('response'=>413));
        $sources = json_decode($sources_raw, true);
        if (!is_array($sources) || json_last_error() !== JSON_ERROR_NONE) wp_die('Sources & citations must contain valid JSON. No article changes were saved.', 'Invalid source data', array('response'=>400));
        $clean_sources = array(); $seen = array();
        foreach (array_slice($sources, 0, 200) as $row) {
            if (!is_array($row)) continue;
            $source_id = sanitize_key($row['id'] ?? '');
            $source_title = sanitize_text_field($row['title'] ?? '');
            if (!$source_id || !$source_title || isset($seen[$source_id])) continue;
            $seen[$source_id] = true;
            $clean_sources[] = array('id'=>$source_id,'author'=>sanitize_text_field($row['author'] ?? ''),'title'=>$source_title,'publisher'=>sanitize_text_field($row['publisher'] ?? ''),'date'=>sanitize_text_field($row['date'] ?? ''),'url'=>esc_url_raw($row['url'] ?? '', array('http','https')),'accessed'=>sanitize_text_field($row['accessed'] ?? ''),'doi'=>sanitize_text_field($row['doi'] ?? ''),'page'=>sanitize_text_field($row['page'] ?? ''),'note'=>sanitize_textarea_field($row['note'] ?? ''));
        }
        $intent = sanitize_key(wp_unslash($_POST['save_intent'] ?? 'draft'));
        $can_publish = current_user_can('publish_posts');
        $status = ($intent === 'submit' && $can_publish) ? 'publish' : (($intent === 'submit') ? 'pending' : 'draft');
        $post_data = array('post_type'=>'wiki_article','post_title'=>$title,'post_content'=>$content,'post_excerpt'=>sanitize_textarea_field(wp_unslash($_POST['article_excerpt'] ?? '')),'post_name'=>sanitize_title(wp_unslash($_POST['article_slug'] ?? '')),'post_status'=>$status);
        if ($existing) $post_data['ID'] = $id;
        else $post_data['post_author'] = get_current_user_id();
        $saved = $existing ? wp_update_post(wp_slash($post_data), true) : wp_insert_post(wp_slash($post_data), true);
        if (is_wp_error($saved)) wp_die(esc_html($saved->get_error_message()), 'Article save failed', array('response'=>500));
        $id = (int)$saved;

        $box_fields = array();
        $box_raw = sanitize_textarea_field(wp_unslash($_POST['infobox_fields'] ?? ''));
        foreach (preg_split('/\r\n|\r|\n/', $box_raw) as $line) {
            if (strpos($line, ':') === false) continue;
            list($label, $value) = array_map('trim', explode(':', $line, 2));
            $key = sanitize_key(str_replace(' ', '_', $label));
            if ($key !== '' && $value !== '') $box_fields[$key] = sanitize_text_field($value);
        }
        $box_title = sanitize_text_field(wp_unslash($_POST['infobox_title'] ?? ''));
        $box_image = esc_url_raw(wp_unslash($_POST['infobox_image'] ?? ''));
        if ($box_title || $box_image || $box_fields) update_post_meta($id, '_bsw_infobox', array('title'=>$box_title,'image'=>$box_image,'fields'=>$box_fields));
        else delete_post_meta($id, '_bsw_infobox');

        if ($clean_sources) update_post_meta($id, self::SOURCE_META, $clean_sources); else delete_post_meta($id, self::SOURCE_META);

        $language = sanitize_text_field(wp_unslash($_POST['article_language'] ?? 'en'));
        if (preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/', $language)) {
            update_post_meta($id, '_bsw_language_code', $language);
            if (taxonomy_exists('wiki_language')) { $term = get_term_by('slug', sanitize_title($language), 'wiki_language'); if (!$term) { $created = wp_insert_term(strtoupper($language), 'wiki_language', array('slug'=>sanitize_title($language))); if (!is_wp_error($created)) $term = get_term($created['term_id'], 'wiki_language'); } if ($term && !is_wp_error($term)) wp_set_object_terms($id, array((int)$term->term_id), 'wiki_language', false); }
        }
        if (current_user_can('edit_others_posts')) { $review = sanitize_key(wp_unslash($_POST['editorial_status'] ?? 'unreviewed')); if (in_array($review, array('unreviewed','in_review','reviewed','needs_sources','disputed'), true)) update_post_meta($id, '_bsw_review_status', $review); }
        update_post_meta($id, '_bsw_p5_seo_title', sanitize_text_field(wp_unslash($_POST['seo_title'] ?? '')));
        update_post_meta($id, '_bsw_p5_seo_description', sanitize_textarea_field(wp_unslash($_POST['seo_description'] ?? '')));
        $image_id = absint($_POST['featured_image_id'] ?? 0);
        if ($image_id && get_post_type($image_id) === 'attachment' && current_user_can('edit_post', $image_id)) set_post_thumbnail($id, $image_id); else delete_post_thumbnail($id);
        if (taxonomy_exists('wiki_topic')) { $topic_ids = array_values(array_filter(array_map('absint', (array) wp_unslash($_POST['article_topic'] ?? array())))); $topic_ids = array_values(array_filter($topic_ids, function($term_id){ return term_exists($term_id, 'wiki_topic'); })); wp_set_object_terms($id, $topic_ids, 'wiki_topic', false); }
        $summary = sanitize_text_field(wp_unslash($_POST['edit_summary'] ?? '')); // Stored as the article's latest edit-summary metadata.
        if ($summary !== '') update_post_meta($id, '_bsw_last_edit_summary', $summary);
        self::log_activity($id, 'Article saved', $summary ?: 'Article content or metadata updated');

        $redirect = add_query_arg(array('page'=>self::PAGE,'id'=>$id,'bsw_studio_saved'=>($status==='pending'?'pending':'1')), admin_url('admin.php'));
        wp_safe_redirect($redirect); exit;
    }

}
register_activation_hook(__FILE__, function () {
    if (!defined('BSW_CORE_PLUGIN_VERSION') || version_compare(BSW_CORE_PLUGIN_VERSION, '34.2.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('BIGSHOR WIKI Article Studio requires BIGSHOR WIKI Core v34.2.0 or later.', 'BIGSHOR WIKI Core required', array('back_link'=>true));
    }
});
BSW_Article_Studio::init();

