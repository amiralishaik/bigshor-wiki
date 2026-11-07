<?php
if (!defined('ABSPATH')) exit;

/* Wikipedia-style discovery and maintenance reports. */
add_shortcode('bigshor_wiki_all_pages', function($atts){
 $settings=function_exists('bsw_get_settings')?bsw_get_settings():array('items_per_page'=>50);$a=shortcode_atts(array('per_page'=>$settings['items_per_page']??50),$atts);$page=max(1,absint($_GET['wiki_page']??1));$q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>max(1,min(100,(int)$a['per_page'])),'paged'=>$page,'orderby'=>'title','order'=>'ASC'));
 if(!$q->have_posts())return '<p>No articles have been published yet.</p>';
 $out='<div class="bsw-index"><p>'.number_format_i18n($q->found_posts).' published articles</p><ol start="'.(($page-1)*$q->get('posts_per_page')+1).'">';
 while($q->have_posts()){$q->the_post();$out.='<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></li>';}
 wp_reset_postdata();$out.='</ol>';
 if($q->max_num_pages>1){$out.='<nav class="bsw-pager" aria-label="Article pages">';for($i=1;$i<=$q->max_num_pages;$i++)$out.='<a '.($i===$page?'aria-current="page"':'').' href="'.esc_url(add_query_arg('wiki_page',$i)).'">'.$i.'</a>'; $out.='</nav>';}
 return $out.'</div>';
});

add_shortcode('bigshor_wiki_stats',function(){
 $articles=wp_count_posts('wiki_article');$users=count_users();$topics=wp_count_terms('wiki_topic');
 $revisions=(int)$GLOBALS['wpdb']->get_var("SELECT COUNT(ID) FROM {$GLOBALS['wpdb']->posts} WHERE post_type='revision'");
 return '<div class="bsw-stat-grid"><div><strong>'.number_format_i18n((int)$articles->publish).'</strong><span>Published articles</span></div><div><strong>'.number_format_i18n((int)$topics).'</strong><span>Topics</span></div><div><strong>'.number_format_i18n((int)$users['total_users']).'</strong><span>Registered contributors</span></div><div><strong>'.number_format_i18n($revisions).'</strong><span>Saved revisions (site-wide)</span></div></div>';
});

add_shortcode('bigshor_wiki_orphaned',function($atts){
 $a=shortcode_atts(array('limit'=>100),$atts);$posts=get_posts(array('post_type'=>'wiki_article','post_status'=>'publish','numberposts'=>max(1,min(300,(int)$a['limit']))));$all=array();foreach($posts as $p)$all[$p->ID]=$p;
 $linked=array();foreach($posts as $p){if(preg_match_all('/href=["\\\']([^"\\\']+)["\\\']/i',$p->post_content,$m))foreach($m[1] as $url){$id=url_to_postid($url);if($id)$linked[$id]=true;}}
 $out='<ul class="wiki-list">';$n=0;foreach($all as $id=>$p){if(!isset($linked[$id])){$out.='<li><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a></li>';$n++;}}return $n?$out.'</ul>':'<p>No orphaned articles found in the scanned set.</p>';
});

add_shortcode('bigshor_wiki_contributors',function($atts){$a=shortcode_atts(array('limit'=>30),$atts);$users=get_users(array('number'=>max(1,min(100,(int)$a['limit'])),'orderby'=>'post_count','order'=>'DESC'));$out='<ul class="bsw-contributors">';foreach($users as $u){$count=count_user_posts($u->ID,'wiki_article',true);if(!$count)continue;$out.='<li>'.get_avatar($u->ID,36).' <a href="'.esc_url(get_author_posts_url($u->ID)).'">'.esc_html($u->display_name).'</a> <span>'.number_format_i18n($count).' articles</span></li>'; }return $out.'</ul>';});

add_shortcode('bigshor_wiki_backlinks',function(){if(!is_singular('wiki_article'))return '';global $wpdb;$url=get_permalink();$like='%'.$wpdb->esc_like($url).'%';$ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='wiki_article' AND post_status='publish' AND post_content LIKE %s AND ID != %d LIMIT 100",$like,get_the_ID()));if(!$ids)return '<section class="wiki-panel"><h2>What links here</h2><p>No incoming links found.</p></section>';$out='<section class="wiki-panel"><h2>What links here</h2><ul>';foreach($ids as $id)$out.='<li><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a></li>';return $out.'</ul></section>';});

add_shortcode('bigshor_wiki_recent_uploads',function($atts){$a=shortcode_atts(array('limit'=>20),$atts);$q=new WP_Query(array('post_type'=>'wiki_media','post_status'=>'publish','posts_per_page'=>max(1,min(50,(int)$a['limit'])),'orderby'=>'date','order'=>'DESC'));if(!$q->have_posts())return '<p>No media has been published.</p>';$out='<ul>';while($q->have_posts()){$q->the_post();$out.='<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a> <span class="wiki-meta">'.esc_html(get_the_date()).'</span></li>'; }wp_reset_postdata();return $out.'</ul>';});

add_action('add_meta_boxes',function(){add_meta_box('bsw_protection','BIGSHOR page protection','bsw_protection_box','wiki_article','side','high');});
if (!function_exists('bsw_protection_box')) {
    function bsw_protection_box($post) {
        if (!current_user_can('manage_options') && !current_user_can('edit_others_posts')) {
            echo '<p>Only editors and administrators can change protection.</p>';
            return;
        }
        wp_nonce_field('bsw_protection_save', 'bsw_protection_nonce');
        $v = get_post_meta($post->ID, '_bsw_protected', true);
        echo '<label><input type="checkbox" name="bsw_protected" value="1" ' . checked($v, '1', false) . '> Protect article from contributor edits</label><p class="description">Protected articles can still receive edit proposals for editorial review.</p>';
    }
}
add_action('save_post_wiki_article',function($id){if(!isset($_POST['bsw_protection_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_protection_nonce'])),'bsw_protection_save'))return;if(!current_user_can('edit_post',$id))return;if(!current_user_can('manage_options')&&!current_user_can('edit_others_posts'))return;update_post_meta($id,'_bsw_protected',isset($_POST['bsw_protected'])?'1':'0');});
add_filter('map_meta_cap',function($caps,$cap,$user_id,$args){if($cap==='edit_post'&&!empty($args[0])&&get_post_type((int)$args[0])==='wiki_article'&&get_post_meta((int)$args[0],'_bsw_protected',true)==='1'){$u=get_userdata($user_id);$caps=$u?$u->allcaps:array();if(empty($caps['manage_options'])&&empty($caps['edit_others_posts']))return array('do_not_allow');}return $caps;},20,4);

