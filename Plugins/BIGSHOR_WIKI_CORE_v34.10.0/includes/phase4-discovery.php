<?php
/**
 * BIGSHOR Wiki Phase 4 — Discovery, multilingual publishing and accessibility.
 * Integrated in Core; uses native WordPress posts/taxonomies and explicit human-linked translations.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase4_Discovery')) {
final class BSW_Phase4_Discovery {
    public static function boot() {
        add_action('init', array(__CLASS__, 'register_meta'), 12);
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'translation_box'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save_translation'), 40, 2);
        add_action('wp_head', array(__CLASS__, 'hreflang'), 3);
        add_action('template_redirect', array(__CLASS__, 'track_article_view'), 20);
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_shortcode('bigshor_discovery_search', array(__CLASS__, 'search_shortcode'));
        add_shortcode('bigshor_language_directory', array(__CLASS__, 'language_directory'));
        add_shortcode('bigshor_reading_preferences', array(__CLASS__, 'accessibility_shortcode'));
        add_shortcode('bigshor_related_articles', array(__CLASS__, 'related_shortcode'));
        add_shortcode('bigshor_explore', array(__CLASS__, 'explore_shortcode'));
        add_shortcode('bigshor_trending_articles', array(__CLASS__, 'trending_shortcode'));
        add_filter('the_content', array(__CLASS__, 'append_translation_links'), 35);
        add_filter('body_class', array(__CLASS__, 'language_body_class'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'reader_assets'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
    }

    public static function register_meta() {
        register_post_meta('wiki_article', '_bsw_p4_translation_group', array('type'=>'string','single'=>true,'show_in_rest'=>false,'auth_callback'=>function(){return current_user_can('edit_posts');}));
        register_post_meta('wiki_article', '_bsw_p4_translations', array('type'=>'array','single'=>true,'show_in_rest'=>false,'auth_callback'=>function(){return current_user_can('edit_posts');}));
    }

    public static function translation_box($post) {
        if (!current_user_can('edit_post', $post->ID)) return;
        wp_nonce_field('bsw_p4_translation_'.$post->ID, 'bsw_p4_translation_nonce');
        $group = get_post_meta($post->ID, '_bsw_p4_translation_group', true);
        $links = get_post_meta($post->ID, '_bsw_p4_translations', true);
        if (!is_array($links)) $links = array();
        echo '<p><label for="bsw-p4-translation-group"><strong>Translation group ID</strong></label><input class="widefat" id="bsw-p4-translation-group" name="bsw_p4_translation_group" value="'.esc_attr($group).'" placeholder="Shared ID for translations"></p>';
        echo '<p>Link other published BIGSHOR articles that are genuine translations of this article. One article ID per line; use the same group ID on each translation.</p>';
        echo '<textarea class="widefat" rows="4" name="bsw_p4_translation_ids">'.esc_textarea(implode("\n", array_map('absint',$links))).'</textarea>';
        echo '<p class="description">This links independently written and reviewed translations. It does not machine-translate or synchronise article content.</p>';
    }

    public static function save_translation($post_id, $post) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post',$post_id)) return;
        if (empty($_POST['bsw_p4_translation_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p4_translation_nonce'])), 'bsw_p4_translation_'.$post_id)) return;
        $group = sanitize_text_field(wp_unslash($_POST['bsw_p4_translation_group'] ?? ''));
        update_post_meta($post_id, '_bsw_p4_translation_group', $group);
        update_post_meta($post_id, '_bsw_translation_group', $group);
        $current_language=wp_get_post_terms($post_id,'wiki_language');
        if(!is_wp_error($current_language) && $current_language) update_post_meta($post_id,'_bsw_language_code',sanitize_key($current_language[0]->slug));
        $raw = preg_split('/\r\n|\r|\n/', sanitize_textarea_field(wp_unslash($_POST['bsw_p4_translation_ids'] ?? '')));
        $ids = array();
        foreach ($raw as $candidate) { $id=absint($candidate); if ($id && $id !== (int)$post_id && get_post_type($id)==='wiki_article' && get_post_status($id)==='publish') $ids[]=$id; }
        update_post_meta($post_id, '_bsw_p4_translations', array_values(array_unique($ids)));
        foreach ($ids as $other) {
            $existing=get_post_meta($other,'_bsw_p4_translations',true); if(!is_array($existing))$existing=array();
            if(!in_array((int)$post_id,array_map('absint',$existing),true)) { $existing[]= (int)$post_id; update_post_meta($other,'_bsw_p4_translations',array_values(array_unique(array_map('absint',$existing)))); }
            if($group) { update_post_meta($other,'_bsw_p4_translation_group',$group); update_post_meta($other,'_bsw_translation_group',$group); }
            $other_language=wp_get_post_terms($other,'wiki_language');
            if(!is_wp_error($other_language) && $other_language) update_post_meta($other,'_bsw_language_code',sanitize_key($other_language[0]->slug));
        }
    }

    private static function translation_ids($id) {
        $ids=get_post_meta($id,'_bsw_p4_translations',true); if(!is_array($ids))return array();
        $valid=array(); foreach($ids as $other){$other=absint($other);if($other && $other!==$id && get_post_type($other)==='wiki_article' && get_post_status($other)==='publish')$valid[]=$other;}
        return array_values(array_unique($valid));
    }

    public static function hreflang() {
        if (!is_singular('wiki_article')) return;
        $id=get_queried_object_id(); $ids=self::translation_ids($id); $current_terms=wp_get_post_terms($id,'wiki_language');
        $current=(!is_wp_error($current_terms)&&$current_terms)?sanitize_key($current_terms[0]->slug):'';
        if($current) echo '<link rel="alternate" hreflang="'.esc_attr($current).'" href="'.esc_url(get_permalink($id)).'" />'."\n";
        foreach($ids as $other){$terms=wp_get_post_terms($other,'wiki_language');if(is_wp_error($terms)||!$terms)continue;$code=sanitize_key($terms[0]->slug);if($code)echo '<link rel="alternate" hreflang="'.esc_attr($code).'" href="'.esc_url(get_permalink($other)).'" />'."\n";}
    }

    public static function language_body_class($classes) {
        if (!is_singular('wiki_article')) return $classes;
        $terms=wp_get_post_terms(get_queried_object_id(),'wiki_language');
        if (!is_wp_error($terms) && $terms) {
            $code=strtolower($terms[0]->slug);
            if (in_array(substr($code,0,2),array('ar','ur','fa','he'),true)) $classes[]='bsw-article-rtl';
            $classes[]='bsw-language-'.sanitize_html_class($code);
        }
        return $classes;
    }

    public static function append_translation_links($content) {
        if(!is_singular('wiki_article')||!in_the_loop()||!is_main_query())return $content;
        $ids=self::translation_ids(get_the_ID());if(!$ids)return $content;
        $out='<nav class="bsw-translation-links" aria-label="Available article translations"><h2>Available languages</h2><ul>';
        foreach($ids as $other){$terms=wp_get_post_terms($other,'wiki_language');$lang=(!is_wp_error($terms)&&$terms)?$terms[0]->name:'Translation';$out.='<li><a href="'.esc_url(get_permalink($other)).'">'.esc_html($lang).' — '.esc_html(get_the_title($other)).'</a></li>';}
        return $content.$out.'</ul></nav>';
    }

    public static function search_shortcode($atts) {
        $a=shortcode_atts(array('per_page'=>12,'show_filters'=>'yes'),$atts);
        $term=sanitize_text_field(wp_unslash($_GET['bsw_search']??''));$topic=absint($_GET['bsw_topic']??0);$lang=sanitize_key(wp_unslash($_GET['bsw_language']??''));$author=absint($_GET['bsw_author']??0);$after=sanitize_text_field(wp_unslash($_GET['bsw_after']??''));$before=sanitize_text_field(wp_unslash($_GET['bsw_before']??''));$type=sanitize_key(wp_unslash($_GET['bsw_type']??''));$page=max(1,absint($_GET['bsw_page']??1));
        $base=array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>max(1,min(50,absint($a['per_page']))),'paged'=>$page,'s'=>$term,'orderby'=>array('relevance'=>'DESC','date'=>'DESC'),'order'=>'DESC');
        $tax=array();if($topic)$tax[]=array('taxonomy'=>'wiki_topic','field'=>'term_id','terms'=>$topic);if($lang)$tax[]=array('taxonomy'=>'wiki_language','field'=>'slug','terms'=>$lang);if($tax)$base['tax_query']=$tax;
        if($author)$base['author']=$author;if($type)$base['meta_query']=array(array('key'=>'_bsw_p1_article_type','value'=>$type));
        $date=array();if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$after))$date['after']=$after;if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$before))$date['before']=$before;if($date){$date['inclusive']=true;$base['date_query']=array($date);}
        $q=new WP_Query($base);$action=remove_query_arg('bsw_page');
        $out='<section class="bsw-discovery-search"><form method="get" action="'.esc_url($action).'" role="search"><label>Search articles<input type="search" name="bsw_search" value="'.esc_attr($term).'" placeholder="Search people, organisations, places and topics" autocomplete="off" data-bsw-suggest="1"></label>';
        if($a['show_filters']==='yes'){
            $topics=get_terms(array('taxonomy'=>'wiki_topic','hide_empty'=>false));if(!is_wp_error($topics)&&$topics){$out.='<label>Topic<select name="bsw_topic"><option value="0">All topics</option>';foreach($topics as $t)$out.='<option value="'.absint($t->term_id).'" '.selected($topic,$t->term_id,false).'>'.esc_html($t->name).'</option>';$out.='</select></label>';}
            $languages=get_terms(array('taxonomy'=>'wiki_language','hide_empty'=>false));if(!is_wp_error($languages)&&$languages){$out.='<label>Language<select name="bsw_language"><option value="">All languages</option>';foreach($languages as $l)$out.='<option value="'.esc_attr($l->slug).'" '.selected($lang,$l->slug,false).'>'.esc_html($l->name).'</option>';$out.='</select></label>';}
            $out.='<div class="bsw-search-row"><label>From<input type="date" name="bsw_after" value="'.esc_attr($after).'"></label><label>To<input type="date" name="bsw_before" value="'.esc_attr($before).'"></label></div>';
            $out.='<label>Article type<select name="bsw_type"><option value="">All types</option>';foreach(array('person'=>'Person','company'=>'Company','place'=>'Place','event'=>'Event','book'=>'Book','film'=>'Film','product'=>'Product','education'=>'Educational institution','general'=>'General') as $k=>$v)$out.='<option value="'.esc_attr($k).'" '.selected($type,$k,false).'>'.esc_html($v).'</option>';$out.='</select></label>';
        }
        $out.='<button type="submit">Search BIGSHOR Wiki</button></form><p class="bsw-search-count">'.number_format_i18n((int)$q->found_posts).' matching articles</p>';
        if($q->have_posts()){$out.='<ol class="bsw-search-results">';while($q->have_posts()){$q->the_post();$id=get_the_ID();$out.='<li><h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3>'; $excerpt=get_the_excerpt();if($excerpt)$out.='<p>'.esc_html(wp_trim_words($excerpt,32)).'</p>';$terms=wp_get_post_terms($id,'wiki_language');if(!is_wp_error($terms)&&$terms)$out.='<small>'.esc_html($terms[0]->name).'</small>';$out.='</li>';}$out.='</ol>';if($q->max_num_pages>1){$out.='<nav class="bsw-search-pagination" aria-label="Search pages">';for($i=1;$i<=$q->max_num_pages;$i++)$out.='<a '.($i===$page?'aria-current="page"':'').' href="'.esc_url(add_query_arg('bsw_page',$i)).'">'.absint($i).'</a>';$out.='</nav>';}}else{$out.='<p>No matching articles found. Try fewer keywords or remove a filter.</p>';}
        wp_reset_postdata();return $out.'</section>';
    }

    public static function register_routes() {
        register_rest_route('bigshor/v1','/search-suggest',array('methods'=>'GET','callback'=>array(__CLASS__,'suggestions'),'permission_callback'=>'__return_true','args'=>array('q'=>array('sanitize_callback'=>'sanitize_text_field','required'=>true))));
    }
    public static function suggestions($request) {
        $term = trim((string) $request->get_param('q'));
        if (function_exists('mb_strlen') ? mb_strlen($term) < 2 : strlen($term) < 2) return rest_ensure_response(array());
        $q = new WP_Query(array('post_type' => array('wiki_article', 'bsw_news'), 'post_status' => 'publish', 'posts_per_page' => 10, 's' => $term, 'orderby' => 'relevance', 'no_found_rows' => true));
        $rows = array();
        while ($q->have_posts()) {
            $q->the_post();
            $id = get_the_ID();
            $image = get_the_post_thumbnail_url($id, 'thumbnail');
            $rows[] = array(
                'id' => (int) $id,
                'title' => get_the_title(),
                'url' => get_permalink(),
                'excerpt' => wp_trim_words(get_the_excerpt(), 12),
                'type' => get_post_type() === 'bsw_news' ? 'News' : 'Knowledge',
                'image' => $image ? esc_url_raw($image) : '',
            );
        }
        wp_reset_postdata();
        return rest_ensure_response($rows);
    }

    public static function language_directory() {
        $terms=get_terms(array('taxonomy'=>'wiki_language','hide_empty'=>true,'orderby'=>'name','order'=>'ASC'));if(is_wp_error($terms)||!$terms)return '<p>No published article languages are available yet.</p>';
        $out='<div class="bsw-language-directory">';foreach($terms as $term){$url=get_term_link($term);if(is_wp_error($url))continue;$out.='<a class="bsw-language-card" href="'.esc_url($url).'"><strong>'.esc_html($term->name).'</strong><span>'.number_format_i18n((int)$term->count).' articles</span></a>'; }return $out.'</div>';
    }

    public static function accessibility_shortcode() {
        return '<section class="bsw-reading-preferences" aria-label="Reading preferences"><strong>Reading preferences</strong><div class="bsw-reader-controls"><button type="button" data-bsw-font="-1" aria-label="Decrease text size">A−</button><button type="button" data-bsw-font="1" aria-label="Increase text size">A+</button><button type="button" data-bsw-contrast="1">High contrast</button><button type="button" data-bsw-width="1">Wide reading</button><button type="button" data-bsw-reset="1">Reset</button></div><span class="screen-reader-text" aria-live="polite" data-bsw-reader-status></span></section>';
    }

    public static function related_shortcode($atts) {
        $a=shortcode_atts(array('id'=>0,'limit'=>6),$atts);$id=absint($a['id']);if(!$id)$id=get_the_ID();if(get_post_type($id)!=='wiki_article')return '';
        $terms=wp_get_post_terms($id,'wiki_topic',array('fields'=>'ids'));if(is_wp_error($terms)||!$terms)return '<p>No related topics are assigned.</p>';
        $q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>max(1,min(12,absint($a['limit']))),'post__not_in'=>array($id),'tax_query'=>array(array('taxonomy'=>'wiki_topic','field'=>'term_id','terms'=>$terms,'operator'=>'IN')),'orderby'=>'date','order'=>'DESC'));
        if(!$q->have_posts())return '<p>No related articles found.</p>';$out='<section class="bsw-related-articles"><h2>Related articles</h2><ul>';while($q->have_posts()){$q->the_post();$out.='<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></li>'; }wp_reset_postdata();return $out.'</ul></section>';
    }

    public static function explore_shortcode() {
        $out='<section class="bsw-explore"><h2>Explore BIGSHOR Wiki</h2><div class="bsw-explore-grid">';
        $cards=array(array('Search the encyclopedia','Find articles by keyword, topic, language and date','[bigshor_discovery_search]'),array('Browse by language','Explore published knowledge across languages','[bigshor_language_directory]'),array('Popular articles','See what readers are exploring','[bigshor_trending_articles limit="8"]'),array('Browse topics','Discover subjects and categories','[bigshor_wiki_category_index]'));
        foreach($cards as $card){$out.='<div class="bsw-explore-card"><h3>'.esc_html($card[0]).'</h3><p>'.esc_html($card[1]).'</p>';if($card[0]==='Search the encyclopedia')$out.='<a href="'.esc_url(home_url('/wiki-search/')).'">Open search</a>';elseif($card[0]==='Browse by language')$out.='<a href="'.esc_url(home_url('/wiki-languages/')).'">Browse languages</a>';elseif($card[0]==='Popular articles')$out.='<a href="'.esc_url(home_url('/')).'">Explore articles</a>';else $out.='<a href="'.esc_url(home_url('/browse-topics/')).'">Browse topics</a>';$out.='</div>';}
        return $out.'</div></section>';
    }

    public static function track_article_view() {
        if(!is_singular('wiki_article')||is_preview())return;$id=get_queried_object_id();if(!$id)return;
        $cookie='bsw_viewed_'.$id;if(isset($_COOKIE[$cookie]))return;
        $count=(int)get_post_meta($id,'_bsw_p4_view_count',true);update_post_meta($id,'_bsw_p4_view_count',$count+1);
        if(!headers_sent())setcookie($cookie,'1',time()+12*HOUR_IN_SECONDS,COOKIEPATH?:'/',COOKIE_DOMAIN,is_ssl(),true);
    }
    public static function trending_shortcode($atts) {
        $a=shortcode_atts(array('limit'=>8),$atts);$q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>max(1,min(20,absint($a['limit']))),'meta_key'=>'_bsw_p4_view_count','orderby'=>'meta_value_num','order'=>'DESC','no_found_rows'=>true));if(!$q->have_posts())return '<p>Popular articles will appear as readership grows.</p>';$out='<ol class="bsw-trending-articles">';while($q->have_posts()){$q->the_post();$out.='<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></li>'; }wp_reset_postdata();return $out.'</ol>';
    }

    public static function reader_assets() {
        if(is_admin())return;
        $css='.bsw-discovery-search form{display:grid;gap:12px;max-width:760px}.bsw-discovery-search label{display:grid;gap:5px}.bsw-discovery-search input,.bsw-discovery-search select{max-width:100%;padding:9px}.bsw-search-row,.bsw-reader-controls{display:flex;gap:10px;flex-wrap:wrap}.bsw-search-results>li{margin:0 0 18px}.bsw-search-pagination a{display:inline-block;padding:7px 10px;margin:3px;border:1px solid currentColor}.bsw-language-directory,.bsw-explore-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}.bsw-language-card,.bsw-explore-card{display:block;padding:16px;border:1px solid #bbb;border-radius:8px}.bsw-language-card span{display:block}.bsw-reading-preferences{margin:18px 0;padding:12px;border:1px solid #bbb}.bsw-reader-controls button{padding:7px 12px;cursor:pointer}.bsw-translation-links{margin-top:24px;padding:14px;border-top:1px solid #bbb}body.bsw-article-rtl .entry-content,body.bsw-article-rtl .wiki-content,body.bsw-article-rtl .bsw-translation-links{direction:rtl;text-align:right}body.bsw-article-rtl .entry-content ul,body.bsw-article-rtl .entry-content ol{padding-right:2em;padding-left:0}.bsw-reader-large{font-size:1.18em}.bsw-reader-high-contrast{background:#000!important;color:#fff!important}.bsw-reader-high-contrast a{color:#ff0!important}.bsw-reader-wide .entry-content,.bsw-reader-wide .wiki-content{max-width: none!important}';
        wp_register_style('bsw-phase4-inline',false);wp_enqueue_style('bsw-phase4-inline');wp_add_inline_style('bsw-phase4-inline',$css);
        $js="(function(){function ready(){var root=document.documentElement;var pref={large:false,contrast:false,wide:false};try{pref=Object.assign(pref,JSON.parse(localStorage.getItem('bswReaderPrefs')||'{}'));}catch(e){}function apply(){root.classList.toggle('bsw-reader-large',!!pref.large);root.classList.toggle('bsw-reader-high-contrast',!!pref.contrast);root.classList.toggle('bsw-reader-wide',!!pref.wide);try{localStorage.setItem('bswReaderPrefs',JSON.stringify(pref));}catch(e){}}apply();document.addEventListener('click',function(e){var b=e.target.closest('[data-bsw-font],[data-bsw-contrast],[data-bsw-width],[data-bsw-reset]');if(!b)return;if(b.hasAttribute('data-bsw-font')){pref.large=Number(b.getAttribute('data-bsw-font'))>0?true:false;}if(b.hasAttribute('data-bsw-contrast'))pref.contrast=!pref.contrast;if(b.hasAttribute('data-bsw-width'))pref.wide=!pref.wide;if(b.hasAttribute('data-bsw-reset'))pref={large:false,contrast:false,wide:false};apply();});var input=document.querySelector('[data-bsw-suggest]');if(input){var box=document.createElement('div');box.className='bsw-search-suggestions';box.setAttribute('role','listbox');input.parentNode.appendChild(box);var timer;input.addEventListener('input',function(){clearTimeout(timer);var val=input.value.trim();if(val.length<2){box.innerHTML='';return;}timer=setTimeout(function(){fetch(".wp_json_encode(esc_url_raw(rest_url('bigshor/v1/search-suggest')))."+'?q='+encodeURIComponent(val)).then(function(r){return r.json();}).then(function(rows){box.innerHTML='';rows.forEach(function(row){var a=document.createElement('a');a.href=row.url;a.textContent=row.title; a.style.display='block';box.appendChild(a);});if(!rows.length){fetch(".wp_json_encode(esc_url_raw(rest_url('bigshor/v1/search-correct')))."+'?q='+encodeURIComponent(val)).then(function(r){return r.json();}).then(function(data){(data.suggestions||[]).forEach(function(row){var a=document.createElement('a');a.href=row.url;a.textContent='Did you mean: '+row.title;a.style.display='block';box.appendChild(a);});}).catch(function(){});}}).catch(function(){});},250);});}}if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready);else ready();})();";
        wp_register_script('bsw-phase4-inline',false,array(), '18.0.0',true);wp_enqueue_script('bsw-phase4-inline');wp_add_inline_script('bsw-phase4-inline',$js);
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=wiki_article','Discovery & Languages','Discovery & Languages','manage_options','bsw-phase4-discovery',array(__CLASS__,'admin_page'));
    }
    public static function admin_page() {
        if(!current_user_can('manage_options'))return;
        $articles=wp_count_posts('wiki_article');$langs=wp_count_terms('wiki_language');
        echo '<div class="wrap"><h1>BIGSHOR Wiki — Discovery & Internationalisation</h1><p>Phase 4 provides advanced search, language discovery, manually linked translations, related articles and reader accessibility controls.</p><div class="card"><h2>Platform overview</h2><p>Published articles: <strong>'.number_format_i18n((int)$articles->publish).'</strong> · Language terms: <strong>'.number_format_i18n((int)$langs).'</strong></p><h2>Available shortcodes</h2><ul><li><code>[bigshor_discovery_search]</code> — filtered search and suggestions</li><li><code>[bigshor_language_directory]</code> — language directory</li><li><code>[bigshor_reading_preferences]</code> — reader accessibility controls</li><li><code>[bigshor_related_articles]</code> — related articles by shared topic</li><li><code>[bigshor_explore]</code> — discovery landing section</li><li><code>[bigshor_trending_articles]</code> — popular articles by recorded views</li></ul><p>Translations must be authored and reviewed separately. BIGSHOR does not automatically translate or publish article content.</p></div></div>';
    }
}
BSW_Phase4_Discovery::boot();
}
