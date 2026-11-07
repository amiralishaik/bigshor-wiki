<?php
/**
 * BIGSHOR Wiki Phase 5 completion: citation link checks, source records,
 * duplicate suggestions, freshness monitoring and editorial reporting.
 * All checks are advisory. No articles are merged, deleted or auto-published.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase5_Completion')) {
final class BSW_Phase5_Completion {
    const META_SOURCES = '_bsw_p5_source_records';
    const META_LINK_AUDIT = '_bsw_p5_link_audit';
    const META_SOURCE_CLASS = '_bsw_p5_source_classification';
    const META_INDEPENDENCE = '_bsw_p5_source_independence';
    const META_SOURCE_CONFIDENCE = '_bsw_p5_source_confidence';
    const META_LAST_LINK_CHECK = '_bsw_p5_last_link_check';

    public static function boot() {
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'metabox'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save'), 70, 2);
        add_action('admin_menu', array(__CLASS__, 'menu'), 55);
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_filter('manage_wiki_article_posts_columns', array(__CLASS__, 'columns'));
        add_action('manage_wiki_article_posts_custom_column', array(__CLASS__, 'column'), 20, 2);
        add_action('admin_post_bsw_p5_export', array(__CLASS__, 'export_csv'));
    }

    public static function metabox() {
        add_meta_box('bsw-p5-evidence', 'BIGSHOR Evidence & Citation Monitor', array(__CLASS__, 'metabox_html'), 'wiki_article', 'normal', 'default');
    }

    public static function metabox_html($post) {
        wp_nonce_field('bsw_p5_evidence_save', 'bsw_p5_evidence_nonce');
        $class = get_post_meta($post->ID, self::META_SOURCE_CLASS, true);
        $ind = get_post_meta($post->ID, self::META_INDEPENDENCE, true);
        $confidence = get_post_meta($post->ID, self::META_SOURCE_CONFIDENCE, true);
        $notes = get_post_meta($post->ID, self::META_SOURCES, true);
        $last = get_post_meta($post->ID, self::META_LAST_LINK_CHECK, true);
        echo '<p>Record the editorial assessment of the article’s sources. These are human-entered notes, not an automated credibility verdict.</p>';
        echo '<p><label><strong>Predominant source type</strong><br/><select name="bsw_p5_source_class"><option value="">Not classified</option>';
        foreach (array('primary'=>'Primary','secondary'=>'Secondary','tertiary'=>'Tertiary','mixed'=>'Mixed / varies') as $v=>$label) echo '<option value="'.esc_attr($v).'" '.selected($class,$v,false).'>'.esc_html($label).'</option>';
        echo '</select></label> &nbsp; <label><strong>Independence</strong><br/><select name="bsw_p5_independence"><option value="">Not assessed</option><option value="independent" '.selected($ind,'independent',false).'>Independent sources</option><option value="mixed" '.selected($ind,'mixed',false).'>Mixed / unclear</option><option value="non_independent" '.selected($ind,'non_independent',false).'>Not independent</option></select></label> &nbsp; <label><strong>Editor confidence</strong><br/><select name="bsw_p5_confidence"><option value="">Not assessed</option><option value="high" '.selected($confidence,'high',false).'>High</option><option value="medium" '.selected($confidence,'medium',false).'>Medium</option><option value="low" '.selected($confidence,'low',false).'>Low</option></select></label></p>';
        echo '<p><label for="bsw_p5_source_notes"><strong>Source-by-source notes, archives, access dates and limitations</strong></label><br/><textarea class="widefat" rows="4" id="bsw_p5_source_notes" name="bsw_p5_source_notes">'.esc_textarea($notes).'</textarea></p>';
        echo '<hr/><p><strong>Citation URL availability check</strong> — checks up to 10 external links in the saved article. A reachable page is not proof that it supports the claim.</p>';
        echo '<button type="button" class="button" id="bsw-p5-check-links" data-post="'.esc_attr($post->ID).'">Check citation links</button> <span id="bsw-p5-link-check-status">'.($last ? 'Last checked: '.esc_html($last) : 'Not checked yet').'</span><div id="bsw-p5-link-check-results" style="margin-top:8px"></div>';
    }

    public static function save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || !isset($_POST['bsw_p5_evidence_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p5_evidence_nonce'])), 'bsw_p5_evidence_save')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $allowed = array('bsw_p5_source_class'=>array(''=>'','primary'=>'primary','secondary'=>'secondary','tertiary'=>'tertiary','mixed'=>'mixed'), 'bsw_p5_independence'=>array(''=>'','independent'=>'independent','mixed'=>'mixed','non_independent'=>'non_independent'), 'bsw_p5_confidence'=>array(''=>'','high'=>'high','medium'=>'medium','low'=>'low'));
        $keys = array('bsw_p5_source_class'=>self::META_SOURCE_CLASS,'bsw_p5_independence'=>self::META_INDEPENDENCE,'bsw_p5_confidence'=>self::META_SOURCE_CONFIDENCE);
        foreach ($keys as $field=>$meta) { $value = isset($_POST[$field]) ? sanitize_key(wp_unslash($_POST[$field])) : ''; if (isset($allowed[$field][$value])) update_post_meta($post_id,$meta,$value); }
        if (isset($_POST['bsw_p5_source_notes'])) update_post_meta($post_id,self::META_SOURCES,sanitize_textarea_field(wp_unslash($_POST['bsw_p5_source_notes'])));
    }

    public static function routes() {
        register_rest_route('bigshor/v1','/citation-check',array(
            'methods'=>'POST','callback'=>array(__CLASS__,'check_links'),
            'permission_callback'=>function($request){$id=absint($request->get_param('post_id'));return $id && current_user_can('edit_post',$id);},
            'args'=>array('post_id'=>array('required'=>true,'sanitize_callback'=>'absint'))
        ));
    }

    private static function valid_public_url($url) {
        $url = esc_url_raw($url, array('http','https'));
        if (!$url || !wp_http_validate_url($url)) return false;
        $parts = wp_parse_url($url);
        if (empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return false;
        $host = strtolower($parts['host']);
        if ($host === 'localhost' || substr($host,-6) === '.local' || substr($host,-4) === '.lan' || substr($host,-5) === '.test') return false;
        return true;
    }

    public static function check_links($request) {
        $id = absint($request->get_param('post_id'));
        $post = get_post($id);
        if (!$post || $post->post_type !== 'wiki_article') return new WP_Error('bsw_article_missing','Article not found.',array('status'=>404));
        $content = $post->post_content;
        preg_match_all('/<a\b[^>]*href\s*=\s*(["\'])(.*?)\1/i',$content,$matches);
        $urls = array();
        if (!empty($matches[2])) foreach ($matches[2] as $raw) { $url = html_entity_decode(trim($raw),ENT_QUOTES,'UTF-8'); if (self::valid_public_url($url) && !isset($urls[$url])) $urls[$url]=$url; if (count($urls)>=10) break; }
        $results = array();
        foreach ($urls as $url) {
            $response = wp_safe_remote_head($url,array('timeout'=>8,'redirection'=>0,'limit_response_size'=>1024,'sslverify'=>true,'user-agent'=>'BIGSHOR-Wiki-Citation-Checker/1.0'));
            if (is_wp_error($response)) { $results[] = array('url'=>$url,'status'=>'error','detail'=>'Request blocked or failed: '.$response->get_error_code()); continue; }
            $code = (int) wp_remote_retrieve_response_code($response);
            $results[] = array('url'=>$url,'status'=>($code>=200 && $code<400)?'reachable':'needs_attention','http_code'=>$code,'detail'=>($code>=200 && $code<400)?'Server responded; source relevance still needs human review':'Server returned an unsuccessful response; redirects are not followed automatically');
        }
        update_post_meta($id,self::META_LINK_AUDIT,$results);
        $stamp = current_time('mysql'); update_post_meta($id,self::META_LAST_LINK_CHECK,$stamp);
        return rest_ensure_response(array('checked'=>count($results),'results'=>$results,'checked_at'=>$stamp,'notice'=>'Availability only. This does not verify that a source is reliable or supports the cited statement.'));
    }

    public static function menu() {
        add_submenu_page('edit.php?post_type=wiki_article','Phase 5 Visibility & Trust','Visibility & Trust','edit_others_posts','bsw-p5-visibility',array(__CLASS__,'dashboard'));
    }

    private static function articles($limit=150) {
        return get_posts(array('post_type'=>'wiki_article','post_status'=>array('publish','draft','pending','private'),'numberposts'=>$limit,'orderby'=>'modified','order'=>'ASC'));
    }

    private static function title_similarity($a,$b) {
        $clean = function($s){$s=remove_accents(strtolower(wp_strip_all_tags($s)));$s=preg_replace('/[^a-z0-9\s]/',' ',$s);$words=preg_split('/\s+/',trim($s));$stop=array('the','and','for','with','from','about','into','what','who','when','where');return array_values(array_unique(array_diff($words,$stop,array(''))));};
        $x=$clean($a);$y=$clean($b); if (!$x || !$y) return 0; $intersection=count(array_intersect($x,$y)); $union=count(array_unique(array_merge($x,$y))); return $union ? $intersection/$union : 0;
    }

    public static function dashboard() {
        if (!current_user_can('edit_others_posts')) return;
        $posts=self::articles(150); $stale=array(); $duplicates=array(); $count=0; $fact_gaps=0;
        foreach ($posts as $p) {
            $count++; $review=get_post_meta($p->ID,BSW_Phase5_Intelligence::META_REVIEW_DATE,true); $basis=$review ? strtotime($review) : strtotime($p->post_modified_gmt.' GMT');
            if (!$basis || $basis < (time()-365*DAY_IN_SECONDS)) $stale[]=$p;
            $fact=get_post_meta($p->ID,BSW_Phase5_Intelligence::META_FACT_STATUS,true); if (!$fact || $fact==='not_reviewed') $fact_gaps++;
        }
        $published=array_values(array_filter($posts,function($p){return $p->post_status==='publish';}));
        $n=count($published); for($i=0;$i<$n;$i++){for($j=$i+1;$j<$n;$j++){ $score=self::title_similarity($published[$i]->post_title,$published[$j]->post_title); if($score>=0.60){$duplicates[]=array($published[$i],$published[$j],$score); if(count($duplicates)>=40) break 2;} }}
        echo '<div class="wrap"><h1>BIGSHOR Phase 5 — Visibility & Trust</h1><p>Editorial intelligence tools provide signals and review queues. They do not establish factual truth, source reliability, notability or neutrality.</p>';
        echo '<p><a class="button button-primary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=bsw_p5_export'),'bsw_p5_export')).'">Export editorial CSV</a></p>';
        echo '<h2>Editorial overview</h2><p><strong>'.esc_html($count).'</strong> articles inspected (maximum 150) &nbsp; <strong>'.esc_html(count($stale)).'</strong> due for review &nbsp; <strong>'.esc_html($fact_gaps).'</strong> without a recorded fact-check status.</p>';
        echo '<h2>Potential duplicate titles</h2><p>Suggestions are based on title-word overlap (60% or more). Review manually; no articles are changed.</p><table class="widefat striped"><thead><tr><th>Article A</th><th>Article B</th><th>Title overlap</th><th>Review</th></tr></thead><tbody>';
        foreach($duplicates as $pair) echo '<tr><td>'.esc_html($pair[0]->post_title).'</td><td>'.esc_html($pair[1]->post_title).'</td><td>'.esc_html(round($pair[2]*100).'%').'</td><td><a href="'.esc_url(get_edit_post_link($pair[0]->ID)).'">Compare A</a> · <a href="'.esc_url(get_edit_post_link($pair[1]->ID)).'">Compare B</a></td></tr>';
        if(!$duplicates) echo '<tr><td colspan="4">No likely title duplicates found in this scan.</td></tr>';
        echo '</tbody></table><h2>Articles due for editorial review (365+ days)</h2><table class="widefat striped"><thead><tr><th>Article</th><th>Status</th><th>Last reviewed / modified</th><th>Action</th></tr></thead><tbody>';
        foreach(array_slice($stale,0,60) as $p){$d=get_post_meta($p->ID,BSW_Phase5_Intelligence::META_REVIEW_DATE,true);if(!$d)$d=$p->post_modified;echo '<tr><td>'.esc_html($p->post_title).'</td><td>'.esc_html($p->post_status).'</td><td>'.esc_html($d).'</td><td><a href="'.esc_url(get_edit_post_link($p->ID)).'">Review article</a></td></tr>';}
        if(!$stale) echo '<tr><td colspan="4">No stale articles in this scan.</td></tr>';
        echo '</tbody></table><p class="description">Scan limit: 150 articles ordered by oldest modification. Duplicate detection compares titles only; content-level semantic matching is not performed.</p></div>';
    }

    public static function columns($columns) { $columns['bsw_p5_freshness']='Last reviewed'; $columns['bsw_p5_source']='Source assessment'; return $columns; }
    public static function column($column,$post_id) {
        if($column==='bsw_p5_freshness'){ $d=get_post_meta($post_id,BSW_Phase5_Intelligence::META_REVIEW_DATE,true); echo esc_html($d ? $d : 'Not recorded'); }
        if($column==='bsw_p5_source'){ $c=get_post_meta($post_id,self::META_SOURCE_CLASS,true); $i=get_post_meta($post_id,self::META_INDEPENDENCE,true); echo esc_html(($c?$c:'Unclassified').' / '.($i?$i:'not assessed')); }
    }

    public static function assets($hook) {
        if(!in_array($hook,array('post.php','post-new.php'),true)) return;
        $screen=function_exists('get_current_screen')?get_current_screen():null; if(!$screen || $screen->post_type!=='wiki_article') return;
        wp_register_script('bsw-p5-evidence',false,array(),'19.2.0',true); wp_enqueue_script('bsw-p5-evidence');
        wp_localize_script('bsw-p5-evidence','BSWP5Evidence',array('root'=>esc_url_raw(rest_url('bigshor/v1/')),'nonce'=>wp_create_nonce('wp_rest')));
        $js="(function(){document.addEventListener('click',function(e){var b=e.target.closest('#bsw-p5-check-links');if(!b)return;var box=document.getElementById('bsw-p5-link-check-results'),status=document.getElementById('bsw-p5-link-check-status');b.disabled=true;status.textContent=' Checking…';fetch(BSWP5Evidence.root+'citation-check',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':BSWP5Evidence.nonce},body:JSON.stringify({post_id:b.getAttribute('data-post')})}).then(function(r){return r.json().then(function(d){if(!r.ok)throw new Error(d.message||'Check failed');return d;});}).then(function(d){status.textContent=' Last checked: '+d.checked_at;box.innerHTML='';var p=document.createElement('p');p.textContent=d.notice+' Links checked: '+d.checked;box.appendChild(p);d.results.forEach(function(x){var row=document.createElement('p'),a=document.createElement('a');a.href=x.url;a.textContent=x.url;a.target='_blank';a.rel='noopener noreferrer';row.appendChild(a);row.appendChild(document.createTextNode(' — '+x.status+' '+(x.http_code||'')+' — '+x.detail));box.appendChild(row);});if(!d.checked)box.textContent='No safe external HTTP/HTTPS links were found in the saved article.';}).catch(function(err){box.textContent=err.message;status.textContent='';}).finally(function(){b.disabled=false;});});})();";
        wp_add_inline_script('bsw-p5-evidence',$js);
    }

    public static function export_csv() {
        if(!current_user_can('edit_others_posts')) wp_die('You are not allowed to export this report.');
        check_admin_referer('bsw_p5_export');
        nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=bigshor-editorial-report.csv');
        $out=fopen('php://output','w'); fputcsv($out,array('Article ID','Title','Status','Modified','Review date','Fact-check status','Completeness score','Source type','Independence','Confidence'));
        foreach(self::articles(1000) as $p){$audit=BSW_Phase5_Intelligence::audit($p);fputcsv($out,array($p->ID,$p->post_title,$p->post_status,$p->post_modified,get_post_meta($p->ID,BSW_Phase5_Intelligence::META_REVIEW_DATE,true),get_post_meta($p->ID,BSW_Phase5_Intelligence::META_FACT_STATUS,true),$audit['score'],get_post_meta($p->ID,self::META_SOURCE_CLASS,true),get_post_meta($p->ID,self::META_INDEPENDENCE,true),get_post_meta($p->ID,self::META_SOURCE_CONFIDENCE,true)));}
        fclose($out); exit;
    }
}
BSW_Phase5_Completion::boot();
}
