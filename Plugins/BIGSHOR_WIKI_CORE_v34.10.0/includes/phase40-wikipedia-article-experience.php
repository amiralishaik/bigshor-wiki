<?php
/**
 * Phase 40: Wikipedia-inspired article presentation and frontend editing.
 * Additive module; preserves the existing BIGSHOR article data model.
 */
if (!defined('ABSPATH')) exit;

final class BSW_Phase40_Article_Experience {
    public static function init() {
        add_filter('the_content', array(__CLASS__, 'article_shell'), 8);
        add_action('init', array(__CLASS__, 'handle_frontend_save'), 1);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function assets() {
        if (!is_singular('wiki_article')) return;
        $css = '
        .bsw40-article{max-width:1180px;margin:0 auto;color:inherit}.bsw40-tabs{display:flex;gap:0;flex-wrap:wrap;border-bottom:1px solid #d8dce2;margin:0 0 22px}.bsw40-tabs a{display:inline-block;padding:11px 16px;text-decoration:none;border-bottom:3px solid transparent;color:inherit;font-size:14px}.bsw40-tabs a:hover,.bsw40-tabs a:focus{border-color:#17365d}.bsw40-tabs .current{border-color:#17365d;font-weight:700}.bsw40-lead{display:grid;grid-template-columns:minmax(0,1fr) minmax(230px,310px);gap:28px;align-items:start}.bsw40-infobox{border:1px solid #aeb7c2;background:#f7f8fa;padding:14px;font-size:14px;min-width:0}.bsw40-infobox h3{margin:0 0 12px;text-align:center;font-size:18px}.bsw40-infobox figure{margin:0 0 12px}.bsw40-infobox img{width:100%;height:auto;display:block}.bsw40-infobox table{width:100%;border-collapse:collapse}.bsw40-infobox th,.bsw40-infobox td{padding:7px 6px;border-top:1px solid #d9dee5;text-align:left;vertical-align:top}.bsw40-infobox th{width:38%;font-weight:600}.bsw40-article-body{min-width:0}.bsw40-article-body h2{border-bottom:1px solid #d8dce2;padding-bottom:6px;margin-top:30px}.bsw40-editor{max-width:1000px;margin:24px auto;padding:22px;border:1px solid #d8dce2;border-radius:8px;background:#fff;color:#20242a}.bsw40-editor label{display:block;font-weight:600;margin:14px 0 6px}.bsw40-editor input[type=text],.bsw40-editor textarea{width:100%;max-width:100%;padding:10px;border:1px solid #aeb7c2;border-radius:4px}.bsw40-editor .button-primary{background:#17365d;color:#fff;border:0;padding:11px 18px;border-radius:4px;cursor:pointer}.bsw40-notice{padding:12px 14px;background:#f1f5f9;border-left:4px solid #17365d;margin:15px 0}.bsw40-editor-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:18px}.bsw40-editor .wp-editor-wrap{margin-bottom:15px}
        @media(max-width:760px){.bsw40-lead{grid-template-columns:1fr}.bsw40-infobox{max-width:100%;order:-1}.bsw40-tabs a{padding:10px 11px;font-size:13px}.bsw40-editor{padding:14px}}
        ';
        wp_register_style('bsw40-article', false, array(), '29.9.0');
        wp_enqueue_style('bsw40-article');
        wp_add_inline_style('bsw40-article', $css);
    }

    private static function tabs($post_id) {
        $base = get_permalink($post_id);
        $items = array(
            'read' => array('Read', $base),
            'edit' => array('Edit', add_query_arg('bsw_edit', $post_id, $base)),
            'history' => array('View history', add_query_arg('bsw_history', '1', $base) . '#bsw-history'),
            'talk' => array('Talk', $base . '#comments'),
        );
        $active = isset($_GET['bsw_edit']) ? 'edit' : (isset($_GET['bsw_history']) ? 'history' : 'read');
        $html = '<nav class="bsw40-tabs" aria-label="Article tools">';
        foreach ($items as $key => $item) {
            $html .= '<a class="' . ($active === $key ? 'current' : '') . '" href="' . esc_url($item[1]) . '">' . esc_html($item[0]) . '</a>';
        }
        $html .= '</nav>';
        return $html;
    }

    private static function infobox($post_id) {
        $data = get_post_meta($post_id, '_bsw_infobox', true);
        if (!is_array($data) || !$data) return '';
        $title = isset($data['title']) ? $data['title'] : get_the_title($post_id);
        $image = isset($data['image']) ? $data['image'] : '';
        $rows = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : $data;
        $out = '<aside class="bsw40-infobox"><h3>' . esc_html($title) . '</h3>';
        if ($image && filter_var($image, FILTER_VALIDATE_URL)) $out .= '<figure><img src="' . esc_url($image) . '" alt="' . esc_attr($title) . '"></figure>';
        $out .= '<table><tbody>';
        foreach ($rows as $label => $value) {
            if (in_array((string)$label, array('title','image','fields'), true) || is_array($value) || is_object($value) || $value === '') continue;
            $out .= '<tr><th>' . esc_html(ucwords(str_replace(array('_','-'), ' ', (string)$label))) . '</th><td>' . esc_html(wp_strip_all_tags((string)$value)) . '</td></tr>';
        }
        $out .= '</tbody></table></aside>';
        return $out;
    }

    public static function article_shell($content) {
        if (is_admin() || !is_singular('wiki_article') || !in_the_loop() || !is_main_query()) return $content;
        $id = get_the_ID();
        if (!$id) return $content;
        if (isset($_GET['bsw_edit']) && absint($_GET['bsw_edit']) === $id) {
            return '<div class="bsw40-article">' . self::tabs($id) . do_shortcode('[bigshor_wiki_frontend_editor]') . '</div>';
        }
        if (isset($_GET['bsw_history'])) {
            return '<div class="bsw40-article">' . self::tabs($id) . self::history($id) . '</div>';
        }
        $thumb = get_the_post_thumbnail($id, 'large', array('class'=>'bsw40-featured-image'));
        $lead = '<div class="bsw40-article"><h1 class="bsw40-title">' . esc_html(get_the_title($id)) . '</h1>' . self::tabs($id) . '<div class="bsw40-lead"><div class="bsw40-article-body">';
        if ($thumb) $lead .= '<div class="bsw40-lead-image">' . $thumb . '</div>';
        $lead .= '</div>' . self::infobox($id) . '</div><div class="bsw40-article-body">' . $content . '</div></div>';
        return $lead;
    }

    public static function editor_shortcode() {
        $id = isset($_GET['bsw_edit']) ? absint($_GET['bsw_edit']) : get_the_ID();
        $post = get_post($id);
        if (!$post || $post->post_type !== 'wiki_article' || !is_user_logged_in() || !current_user_can('edit_post', $id)) {
            return '<div class="bsw40-notice">You must be logged in with permission to edit this article.</div>';
        }
        ob_start();
        echo '<section class="bsw40-editor"><h2>Edit article</h2><p>Changes are saved to the existing BIGSHOR Wiki article. Your permissions determine whether the update is published or sent for review.</p>';
        if (isset($_GET['bsw_saved'])) echo '<div class="bsw40-notice">' . (current_user_can('manage_options') ? 'Article updated and published.' : 'Your changes have been submitted for editorial review.') . '</div>';
        echo '<form method="post">';
        wp_nonce_field('bsw40_save_' . $id, 'bsw40_nonce');
        echo '<input type="hidden" name="bsw40_action" value="save_article"><input type="hidden" name="bsw40_post_id" value="' . esc_attr($id) . '">';
        echo '<label for="bsw40_title">Article title</label><input id="bsw40_title" type="text" name="bsw40_title" required value="' . esc_attr($post->post_title) . '">';
        echo '<label for="bsw40_content">Article content</label>';
        wp_editor($post->post_content, 'bsw40_content', array('textarea_name'=>'bsw40_content','textarea_rows'=>22,'media_buttons'=>current_user_can('upload_files'),'teeny'=>false,'tinymce'=>true,'quicktags'=>true));
        echo '<label for="bsw40_excerpt">Article summary</label><textarea id="bsw40_excerpt" name="bsw40_excerpt" rows="4">' . esc_textarea($post->post_excerpt) . '</textarea>';
        echo '<div class="bsw40-editor-actions"><button class="button-primary" type="submit">' . (current_user_can('manage_options') ? 'Save and publish' : 'Submit changes for review') . '</button><a href="' . esc_url(get_permalink($id)) . '">Cancel</a></div>';
        echo '</form></section>';
        return ob_get_clean();
    }

    public static function handle_frontend_save() {
        if (empty($_POST['bsw40_action']) || $_POST['bsw40_action'] !== 'save_article') return;
        $id = isset($_POST['bsw40_post_id']) ? absint($_POST['bsw40_post_id']) : 0;
        $post = get_post($id);
        if (!$post || $post->post_type !== 'wiki_article' || !current_user_can('edit_post', $id)) wp_die(esc_html__('You are not allowed to edit this article.', 'bigshor-wiki-core'), 403);
        if (!isset($_POST['bsw40_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw40_nonce'])), 'bsw40_save_' . $id)) wp_die(esc_html__('Security check failed. Please reload and try again.', 'bigshor-wiki-core'), 403);
        $title = isset($_POST['bsw40_title']) ? sanitize_text_field(wp_unslash($_POST['bsw40_title'])) : '';
        $content = isset($_POST['bsw40_content']) ? wp_kses_post(wp_unslash($_POST['bsw40_content'])) : '';
        $excerpt = isset($_POST['bsw40_excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['bsw40_excerpt'])) : '';
        if ($title === '') wp_die(esc_html__('Article title is required.', 'bigshor-wiki-core'), 400);
        $is_admin = current_user_can('manage_options') && current_user_can('publish_posts');
        $status = $is_admin ? 'publish' : 'pending';
        $result = wp_update_post(wp_slash(array('ID'=>$id,'post_title'=>$title,'post_content'=>$content,'post_excerpt'=>$excerpt,'post_status'=>$status)), true);
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()), 500);
        wp_safe_redirect(add_query_arg('bsw_saved', '1', get_permalink($id)));
        exit;
    }

    private static function history($id) {
        $revisions = wp_get_post_revisions($id, array('posts_per_page'=>30));
        $out = '<section id="bsw-history"><h2>Article history</h2><p>Recent saved revisions of this article.</p>';
        if (!$revisions) return $out . '<p>No revisions are available yet.</p></section>';
        $out .= '<ol>';
        foreach ($revisions as $revision) {
            $author = get_the_author_meta('display_name', $revision->post_author);
            $out .= '<li>' . esc_html(get_date_from_gmt($revision->post_modified_gmt, get_option('date_format') . ' ' . get_option('time_format'))) . ' — ' . esc_html($author ?: 'Unknown contributor') . '</li>';
        }
        $out .= '</ol></section>';
        return $out;
    }
}
BSW_Phase40_Article_Experience::init();
