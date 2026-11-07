<?php
/**
 * BIGSHOR Wiki Data Import / Export — Tools menu integration.
 * Exports selected BIGSHOR content types with metadata and taxonomy terms.
 * Imports are always drafts and never overwrite existing content.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Data_Portability_Tools')):
final class BSW_Data_Portability_Tools {
    const NONCE = 'bsw_data_portability_tools';
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_bsw_data_export', array(__CLASS__, 'export'));
        add_action('admin_post_bsw_data_import', array(__CLASS__, 'import'));
    }
    public static function types() {
        $types = array('wiki_article','wiki_entity','bsw_news','wiki_talk','wiki_media');
        return array_values(array_filter($types, 'post_type_exists'));
    }
    public static function menu() {
        add_management_page('BIGSHOR Import / Export', 'BIGSHOR Import / Export', 'manage_options', 'bsw-import-export', array(__CLASS__, 'page'));
    }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        $notice = isset($_GET['bsw_ie_result']) ? sanitize_key(wp_unslash($_GET['bsw_ie_result'])) : '';
        $count = isset($_GET['count']) ? absint($_GET['count']) : 0;
        echo '<div class="wrap"><h1>BIGSHOR Wiki — Import &amp; Export</h1><p>Back up and transfer BIGSHOR Wiki content, including article text, selected custom fields and taxonomy terms. This tool is available under <strong>Tools → BIGSHOR Import / Export</strong>.</p>';
        if ($notice === 'imported') echo '<div class="notice notice-success"><p>'.esc_html(sprintf('%d records imported as drafts. Existing records were not overwritten.', $count)).'</p></div>';
        if ($notice === 'error') echo '<div class="notice notice-error"><p>The import could not be completed. Check that the file is a valid BIGSHOR JSON export.</p></div>';
        echo '<div class="bsw-ie-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;max-width:1100px">';
        echo '<section style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px"><h2 style="margin-top:0">1. Export data</h2><p>Choose the areas you want to back up. Categories, tags, taxonomy terms and their hierarchy are included automatically.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('bsw_data_export'); echo '<input type="hidden" name="action" value="bsw_data_export">';
        $groups = array(
            'Knowledge' => array('wiki_article','wiki_entity'),
            'News &amp; publishing' => array('bsw_news'),
            'Community &amp; discussions' => array('wiki_talk'),
            'Media library records' => array('wiki_media')
        );
        foreach ($groups as $group => $group_types) {
            $available = array_values(array_intersect($group_types, self::types()));
            if (!$available) continue;
            echo '<fieldset style="border:1px solid #dcdcde;padding:12px 14px;margin:12px 0"><legend><strong>'.$group.'</strong></legend>';
            foreach ($available as $type) { $obj=get_post_type_object($type); echo '<label style="display:block;margin:7px 0"><input type="checkbox" name="post_types[]" value="'.esc_attr($type).'" checked> '.esc_html($obj ? $obj->labels->name : $type).'</label>'; }
            echo '</fieldset>';
        }
        echo '<p><label><input type="checkbox" name="include_private" value="1" checked> Include drafts, pending and private records</label></p><p><button class="button button-primary">Export selected data (JSON)</button></p></form></section>';
        echo '<section style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px"><h2 style="margin-top:0">2. Import data</h2><p>Restore or migrate a BIGSHOR JSON export. Choose a file and import its supported content types.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" enctype="multipart/form-data">';
        wp_nonce_field('bsw_data_import'); echo '<input type="hidden" name="action" value="bsw_data_import"><input type="file" name="bsw_import_file" accept=".json,application/json" required><p><button class="button button-primary">Import as drafts</button></p></form><p><strong>Review-first:</strong> imported records are saved as drafts and are never auto-published.</p></section></div>';
        echo '<section style="max-width:1100px;margin-top:18px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px"><h2 style="margin-top:0">3. What is included?</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px"><div><strong>Content</strong><p>Titles, article text, excerpts, slugs and dates.</p></div><div><strong>Categories &amp; tags</strong><p>Taxonomy assignments, category names, slugs and parent-child hierarchy.</p></div><div><strong>Custom data</strong><p>Supported post metadata and source fields.</p></div><div><strong>Media references</strong><p>Media record metadata and references; original binary files are not bundled.</p></div></div></section>';
        echo '<h2>Important safeguards</h2><ul><li>Only BIGSHOR-supported post types are imported.</li><li>Imports never publish, approve, verify or overwrite records.</li><li>Custom fields beginning with an underscore are retained only when they are registered or explicitly part of the exported post metadata; review sensitive/plugin-specific metadata before importing into production.</li><li>Run a database backup before large imports or migrations.</li></ul></div>';
    }
    public static function export() {
        if (!current_user_can('manage_options')) wp_die('Not authorised.');
        check_admin_referer('bsw_data_export');
        $allowed=self::types(); $selected=isset($_POST['post_types'])?(array)wp_unslash($_POST['post_types']):$allowed;
        $selected=array_values(array_intersect(array_map('sanitize_key',$selected),$allowed));
        if (!$selected) wp_die('Select at least one content type.');
        $statuses=!empty($_POST['include_private'])?array('publish','future','draft','pending','private') : array('publish');
        $items=get_posts(array('post_type'=>$selected,'post_status'=>$statuses,'numberposts'=>-1,'orderby'=>'ID','order'=>'ASC','suppress_filters'=>false));
        $records=array();
        foreach($items as $p) {
            $meta=get_post_meta($p->ID); $clean_meta=array();
            foreach($meta as $key=>$values) { if (in_array($key,array('_edit_lock','_edit_last'),true)) continue; $clean_meta[$key]=array_map('maybe_unserialize',$values); }
            $tax=array(); foreach(get_object_taxonomies($p->post_type,'names') as $taxonomy) { $terms=wp_get_object_terms($p->ID,$taxonomy,array('fields'=>'slugs')); if(!is_wp_error($terms)) $tax[$taxonomy]=$terms; }
            $author=get_userdata($p->post_author);
            $records[]=array('post_type'=>$p->post_type,'original_id'=>(int)$p->ID,'post_title'=>$p->post_title,'post_content'=>$p->post_content,'post_excerpt'=>$p->post_excerpt,'post_name'=>$p->post_name,'post_status'=>$p->post_status,'post_date'=>$p->post_date,'post_author_login'=>$author?$author->user_login:'','meta'=>$clean_meta,'taxonomies'=>$tax);
        }
        $term_data=array();
        foreach ($selected as $type) {
            foreach (get_object_taxonomies($type, 'objects') as $tax_obj) {
                $tax=$tax_obj->name;
                if (!isset($term_data[$tax])) $term_data[$tax]=array();
                $terms=get_terms(array('taxonomy'=>$tax,'hide_empty'=>false));
                if (!is_wp_error($terms)) foreach ($terms as $term) $term_data[$tax][$term->slug]=array('name'=>$term->name,'slug'=>$term->slug,'description'=>$term->description,'parent'=>(int)$term->parent ? (get_term((int)$term->parent,$tax)->slug ?? '') : '');
            }
        }
        $payload=array('format'=>'bigshor-wiki-export','format_version'=>1,'exported_at'=>gmdate('c'),'site_url'=>home_url('/'),'record_count'=>count($records),'terms'=>$term_data,'records'=>$records);
        nocache_headers(); header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="bigshor-wiki-export-'.gmdate('Ymd-His').'.json"');
        echo wp_json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); exit;
    }
    public static function import() {
        if (!current_user_can('manage_options')) wp_die('Not authorised.');
        check_admin_referer('bsw_data_import');
        if (empty($_FILES['bsw_import_file']['tmp_name']) || !is_uploaded_file($_FILES['bsw_import_file']['tmp_name'])) self::redirect('error');
        $raw=file_get_contents($_FILES['bsw_import_file']['tmp_name']);
        if (!$raw || strlen($raw)>25*1024*1024) self::redirect('error');
        $data=json_decode($raw,true);
        if (!is_array($data)||($data['format']??'')!=='bigshor-wiki-export'||(int)($data['format_version']??0)!==1||!is_array($data['records']??null)) self::redirect('error');
        $allowed=self::types(); $created=0;
        // Restore category/tag dictionaries first so assignments and parent hierarchy survive migrations.
        if (!empty($data['terms']) && is_array($data['terms'])) {
            foreach ($data['terms'] as $tax=>$terms) {
                if (!taxonomy_exists($tax) || !is_array($terms)) continue;
                foreach ($terms as $slug=>$term_data) {
                    if (!is_array($term_data) || get_term_by('slug', sanitize_title($slug), $tax)) continue;
                    wp_insert_term(sanitize_text_field($term_data['name'] ?? $slug), $tax, array('slug'=>sanitize_title($slug),'description'=>sanitize_textarea_field($term_data['description'] ?? '')));
                }
                foreach ($terms as $slug=>$term_data) {
                    if (empty($term_data['parent'])) continue;
                    $child=get_term_by('slug',sanitize_title($slug),$tax); $parent=get_term_by('slug',sanitize_title($term_data['parent']),$tax);
                    if ($child && $parent && !is_wp_error($child) && !is_wp_error($parent)) wp_update_term($child->term_id,$tax,array('parent'=>$parent->term_id));
                }
            }
        }
        foreach(array_slice($data['records'],0,5000) as $r) {
            if (!is_array($r)||!in_array($r['post_type']??'', $allowed,true)) continue;
            $title=sanitize_text_field($r['post_title']??''); if($title==='') continue;
            $slug=sanitize_title($r['post_name']??$title); if($slug && get_page_by_path($slug,OBJECT,$r['post_type'])) continue;
            $post_id=wp_insert_post(array('post_type'=>$r['post_type'],'post_status'=>'draft','post_title'=>$title,'post_content'=>wp_kses_post($r['post_content']??''),'post_excerpt'=>sanitize_textarea_field($r['post_excerpt']??''),'post_name'=>$slug,'post_author'=>get_current_user_id()),true);
            if(is_wp_error($post_id)) continue;
            $created++;
            if(!empty($r['meta'])&&is_array($r['meta'])) foreach($r['meta'] as $key=>$values) {
                $key=sanitize_key($key); if($key===''||in_array($key,array('_edit_lock','_edit_last'),true)) continue;
                if(!is_array($values)) $values=array($values);
                delete_post_meta($post_id,$key);
                foreach($values as $value) add_post_meta($post_id,$key,maybe_unserialize($value));
            }
            if(!empty($r['taxonomies'])&&is_array($r['taxonomies'])) foreach($r['taxonomies'] as $tax=>$slugs) {
                if(!taxonomy_exists($tax)||!is_object_in_taxonomy($r['post_type'],$tax)||!is_array($slugs)) continue;
                $term_ids=array(); foreach($slugs as $term_slug) { $term=get_term_by('slug',sanitize_title($term_slug),$tax); if($term&&!is_wp_error($term)) $term_ids[]=(int)$term->term_id; }
                wp_set_object_terms($post_id,$term_ids,$tax,false);
            }
        }
        self::redirect('imported',$created);
    }
    private static function redirect($result,$count=0) { wp_safe_redirect(add_query_arg(array('page'=>'bsw-import-export','bsw_ie_result'=>$result,'count'=>(int)$count),admin_url('tools.php'))); exit; }
}
BSW_Data_Portability_Tools::init();
endif;
