<?php
/**
 * BIGSHOR Wiki Phase 4.1 — Translation editorial oversight and language QA.
 * Provides an administrator-only translation inventory; it never auto-publishes translations.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase4_Extended')) {
final class BSW_Phase4_Extended {
    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 30);
        add_action('admin_notices', array(__CLASS__, 'notice'));
    }

    public static function menu() {
        add_submenu_page('edit.php?post_type=wiki_article', 'Translation Management', 'Translation Management', 'manage_options', 'bsw-translation-management', array(__CLASS__, 'page'));
    }

    public static function notice() {
        if (!is_admin() || !current_user_can('manage_options') || empty($_GET['bsw_translation_saved'])) return;
        echo '<div class="notice notice-success is-dismissible"><p>Translation inventory refreshed. No article content was changed.</p></div>';
    }

    private static function get_rows($search = '', $language = '', $page = 1) {
        $args = array('post_type'=>'wiki_article','post_status'=>array('publish','draft','pending','private'),'posts_per_page'=>25,'paged'=>max(1,$page),'orderby'=>'modified','order'=>'DESC');
        if ($search !== '') $args['s'] = $search;
        if ($language !== '') $args['tax_query'] = array(array('taxonomy'=>'wiki_language','field'=>'slug','terms'=>$language));
        return new WP_Query($args);
    }

    public static function page() {
        if (!current_user_can('manage_options')) return;
        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $language = sanitize_key(wp_unslash($_GET['language'] ?? ''));
        $page = max(1, absint($_GET['paged'] ?? 1));
        $q = self::get_rows($search, $language, $page);
        $languages = get_terms(array('taxonomy'=>'wiki_language','hide_empty'=>false));
        $published = wp_count_posts('wiki_article');
        $published_count = isset($published->publish) ? (int)$published->publish : 0;
        echo '<div class="wrap"><h1>BIGSHOR Wiki — Translation Management</h1>';
        echo '<p>Editorial inventory for multilingual articles. Translation links are manually maintained and each language version must be independently researched, sourced and reviewed.</p>';
        echo '<div class="notice notice-info inline"><p><strong>Coverage overview:</strong> '.number_format_i18n($published_count).' published articles. This page reports links and metadata; it does not certify translation accuracy or completeness.</p></div>';
        echo '<form method="get"><input type="hidden" name="post_type" value="wiki_article"><input type="hidden" name="page" value="bsw-translation-management"><p class="search-box"><label class="screen-reader-text" for="bsw-translation-search">Search articles</label><input id="bsw-translation-search" type="search" name="s" value="'.esc_attr($search).'" placeholder="Search article titles"></p><select name="language"><option value="">All languages</option>';
        if (!is_wp_error($languages)) foreach ($languages as $lang) echo '<option value="'.esc_attr($lang->slug).'" '.selected($language,$lang->slug,false).'>'.esc_html($lang->name).'</option>';
        echo '</select> <button class="button">Filter</button></form>';
        echo '<table class="widefat striped"><thead><tr><th>Article</th><th>Status</th><th>Language</th><th>Translation group</th><th>Linked versions</th><th>Editorial action</th></tr></thead><tbody>';
        if ($q->have_posts()) {
            while ($q->have_posts()) { $q->the_post(); $id=get_the_ID(); $terms=wp_get_post_terms($id,'wiki_language'); $language_name=(!is_wp_error($terms)&&$terms)?$terms[0]->name:'Not assigned'; $group=get_post_meta($id,'_bsw_p4_translation_group',true); $ids=get_post_meta($id,'_bsw_p4_translations',true); if(!is_array($ids))$ids=array(); $valid=array(); foreach($ids as $other){$other=absint($other);if($other && get_post_type($other)==='wiki_article' && get_post_status($other)==='publish')$valid[]=$other;} $edit=get_edit_post_link($id,'');
                echo '<tr><td><strong>'.esc_html(get_the_title()).'</strong><br><small>#'.absint($id).'</small></td><td>'.esc_html(get_post_status_object(get_post_status($id))->label ?? get_post_status($id)).'</td><td>'.esc_html($language_name).'</td><td>'.($group?'<code>'.esc_html($group).'</code>':'<em>Not assigned</em>').'</td><td>'.number_format_i18n(count(array_unique($valid))).'</td><td><a href="'.esc_url($edit).'">Edit article / translations</a></td></tr>';
            }
            wp_reset_postdata();
        } else echo '<tr><td colspan="6">No articles match these filters.</td></tr>';
        echo '</tbody></table>';
        if ($q->max_num_pages > 1) { echo '<div class="tablenav"><div class="tablenav-pages">'; for($i=1;$i<=$q->max_num_pages;$i++){ $url=add_query_arg(array('post_type'=>'wiki_article','page'=>'bsw-translation-management','s'=>$search,'language'=>$language,'paged'=>$i),admin_url('edit.php')); echo '<a class="button '.($i===$page?'button-primary':'').'" href="'.esc_url($url).'">'.absint($i).'</a> '; } echo '</div></div>'; }
        echo '<h2>Editorial checklist</h2><ol><li>Assign the correct language taxonomy to every article.</li><li>Use the same translation group ID for genuine translations of the same subject page.</li><li>Link only published, independently reviewed translation articles.</li><li>Check names, dates, citations, legal context and culturally specific terminology in each language.</li><li>Review canonical URLs and hreflang output after publishing.</li></ol>';
        echo '<p><strong>Note:</strong> A linked article is not automatically considered complete, verified or equivalent to another language version.</p></div>';
    }
}
BSW_Phase4_Extended::boot();
}
