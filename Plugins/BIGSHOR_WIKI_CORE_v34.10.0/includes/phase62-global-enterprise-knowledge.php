<?php
/**
 * Phase 9 — Global enterprise knowledge infrastructure.
 * Contributor reputation, staged editorial governance, source URL checks,
 * lexical knowledge discovery and WordPress privacy tools.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase62_Global_Enterprise_Knowledge')) {
final class BSW_Phase62_Global_Enterprise_Knowledge {
    const VERSION = '33.5.0';
    const GATE_OPTION = 'bsw_p62_publication_gate';
    const LEDGER_META = '_bsw_p62_reputation_ledger';
    const SCORE_META = '_bsw_p62_reputation_score';
    const STAGES = array('source_review'=>'Source review','fact_check'=>'Fact-check','copy_edit'=>'Copy-edit','final_approval'=>'Final approval');

    public static function init() {
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'add_box'), 30);
        add_action('save_post_wiki_article', array(__CLASS__, 'save_article'), 50, 2);
        add_action('transition_post_status', array(__CLASS__, 'award_publication_points'), 30, 3);
        add_action('admin_post_bsw_p62_verify_sources', array(__CLASS__, 'verify_sources'));
        add_action('admin_menu', array(__CLASS__, 'menu'), 95);
        add_filter('wp_insert_post_data', array(__CLASS__, 'publication_gate'), 20, 2);
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_shortcode('bsw_contributor_reputation', array(__CLASS__, 'reputation_shortcode'));
        add_filter('wp_privacy_personal_data_exporters', array(__CLASS__, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array(__CLASS__, 'register_eraser'));
    }

    public static function add_box() {
        add_meta_box('bsw_p62_editorial', 'BIGSHOR Enterprise Editorial Workflow', array(__CLASS__, 'render_box'), 'wiki_article', 'normal', 'high');
    }

    public static function render_box($post) {
        if (!current_user_can('edit_post', $post->ID)) return;
        $reviewer = absint(get_post_meta($post->ID, '_bsw_p62_reviewer', true));
        $stage_values = get_post_meta($post->ID, '_bsw_p62_stages', true);
        if (!is_array($stage_values)) $stage_values = array();
        $notes = (string)get_post_meta($post->ID, '_bsw_p62_editorial_notes', true);
        $sources = (string)get_post_meta($post->ID, '_bsw_p62_sources', true);
        $results = get_post_meta($post->ID, '_bsw_p62_source_results', true);
        if (!is_array($results)) $results = array();
        wp_nonce_field('bsw_p62_save_' . $post->ID, 'bsw_p62_nonce');
        echo '<p><label for="bsw_p62_reviewer"><strong>Assigned reviewer</strong></label><br><select name="bsw_p62_reviewer" id="bsw_p62_reviewer"><option value="0">Unassigned</option>';
        $users = get_users(array('capability'=>'edit_others_posts','orderby'=>'display_name','order'=>'ASC','fields'=>array('ID','display_name'),'number'=>200));
        foreach ($users as $user) echo '<option value="'.(int)$user->ID.'" '.selected($reviewer,$user->ID,false).'>'.esc_html($user->display_name).'</option>';
        echo '</select></p><fieldset><legend><strong>Editorial stages</strong></legend><p class="description">Mark each stage complete only after a human reviewer has performed it.</p>';
        foreach (self::STAGES as $key=>$label) echo '<label style="display:inline-block;margin:0 18px 10px 0"><input type="checkbox" name="bsw_p62_stages[]" value="'.esc_attr($key).'" '.checked(in_array($key,$stage_values,true),true,false).' /> '.esc_html($label).'</label>';
        echo '</fieldset><p><label for="bsw_p62_notes"><strong>Editorial notes</strong></label><textarea class="widefat" rows="3" id="bsw_p62_notes" name="bsw_p62_notes">'.esc_textarea($notes).'</textarea></p>';
        echo '<hr><p><label for="bsw_p62_sources"><strong>Source URLs (one per line)</strong></label><textarea class="widefat code" rows="5" id="bsw_p62_sources" name="bsw_p62_sources" placeholder="https://example.org/source">'.esc_textarea($sources).'</textarea></p>';
        $url = wp_nonce_url(admin_url('admin-post.php?action=bsw_p62_verify_sources&post_id='.(int)$post->ID), 'bsw_p62_verify_'.$post->ID);
        echo '<p><a class="button" href="'.esc_url($url).'">Check source URL availability</a> <span class="description">Checks HTTP availability only; it does not verify factual accuracy, authority or source independence.</span></p>';
        if ($results) {
            echo '<table class="widefat striped"><thead><tr><th>Source</th><th>HTTP</th><th>Checked (UTC)</th><th>Result</th></tr></thead><tbody>';
            foreach ($results as $r) echo '<tr><td><a href="'.esc_url($r['url']).'" target="_blank" rel="noopener noreferrer">'.esc_html(wp_parse_url($r['url'],PHP_URL_HOST)).'</a></td><td>'.(int)$r['code'].'</td><td>'.esc_html($r['checked']).'</td><td>'.esc_html($r['message']).'</td></tr>';
            echo '</tbody></table>';
        }
        echo '<p class="description">Publication gating is optional and configured under Tools → BIGSHOR Knowledge. Existing publishing behavior is unchanged by default.</p>';
    }

    public static function save_article($post_id, $post) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !$post || !isset($_POST['bsw_p62_nonce'])) return;
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p62_nonce'])), 'bsw_p62_save_'.$post_id) || !current_user_can('edit_post',$post_id)) return;
        $existing_reviewer = absint(get_post_meta($post_id,'_bsw_p62_reviewer',true));
        $reviewer = $existing_reviewer;
        if (current_user_can('manage_options') || current_user_can('edit_others_posts')) {
            $candidate = absint($_POST['bsw_p62_reviewer'] ?? 0);
            if ($candidate) { $u=get_userdata($candidate); if (!$u || !user_can($u,'edit_others_posts')) $candidate=0; }
            $reviewer = $candidate;
            update_post_meta($post_id,'_bsw_p62_reviewer',$reviewer);
        }
        $may_review = current_user_can('manage_options') || ($existing_reviewer && get_current_user_id()===$existing_reviewer) || (!$existing_reviewer && current_user_can('edit_others_posts'));
        if ($may_review) {
            $stages = isset($_POST['bsw_p62_stages']) ? array_map('sanitize_key',(array)wp_unslash($_POST['bsw_p62_stages'])) : array();
            $stages = array_values(array_intersect(array_keys(self::STAGES),$stages));
            update_post_meta($post_id,'_bsw_p62_stages',$stages);
        }
        update_post_meta($post_id,'_bsw_p62_editorial_notes',sanitize_textarea_field(wp_unslash($_POST['bsw_p62_notes'] ?? '')));
        $lines = preg_split('/\r\n|\r|\n/',(string)wp_unslash($_POST['bsw_p62_sources'] ?? ''));
        $urls = array(); foreach ($lines as $line) { $line=trim($line); if ($line && esc_url_raw($line) && in_array(wp_parse_url($line,PHP_URL_SCHEME),array('http','https'),true)) $urls[] = esc_url_raw($line); }
        update_post_meta($post_id,'_bsw_p62_sources',implode("\n",array_slice(array_unique($urls),0,50)));
    }

    public static function publication_gate($data, $postarr) {
        if (($data['post_type'] ?? '') !== 'wiki_article' || ($data['post_status'] ?? '') !== 'publish' || !get_option(self::GATE_OPTION,false)) return $data;
        if (current_user_can('manage_options')) return $data;
        $id = absint($postarr['ID'] ?? 0);
        $done = $id ? get_post_meta($id,'_bsw_p62_stages',true) : array();
        if (!is_array($done) || count(array_diff(array_keys(self::STAGES),$done)) > 0) $data['post_status'] = 'pending';
        return $data;
    }

    public static function award_publication_points($new, $old, $post) {
        if (!$post || $post->post_type !== 'wiki_article' || $new !== 'publish' || $old === 'publish' || wp_is_post_revision($post->ID)) return;
        if (get_post_meta($post->ID,'_bsw_p62_points_awarded',true)) return;
        $author = absint($post->post_author); if (!$author) return;
        self::add_points($author,10,'article_first_publication',(int)$post->ID);
        update_post_meta($post->ID,'_bsw_p62_points_awarded',1);
    }

    private static function add_points($user_id,$points,$event,$object_id=0) {
        $score=(int)get_user_meta($user_id,self::SCORE_META,true); $score=max(0,min(5000,$score+(int)$points));
        update_user_meta($user_id,self::SCORE_META,$score);
        $ledger=get_user_meta($user_id,self::LEDGER_META,true); if (!is_array($ledger)) $ledger=array();
        $ledger[] = array('time'=>gmdate('c'),'points'=>(int)$points,'event'=>sanitize_key($event),'object_id'=>absint($object_id));
        if (count($ledger)>250) $ledger=array_slice($ledger,-250);
        update_user_meta($user_id,self::LEDGER_META,$ledger);
    }

    private static function tier($score) {
        if ($score>=500) return 'Knowledge steward';
        if ($score>=200) return 'Senior contributor';
        if ($score>=50) return 'Established contributor';
        if ($score>=10) return 'Contributor';
        return 'New contributor';
    }

    public static function reputation_shortcode($atts) {
        $atts=shortcode_atts(array('user_id'=>get_current_user_id()),$atts,'bsw_contributor_reputation');
        $user=get_userdata(absint($atts['user_id'])); if (!$user) return '';
        $score=(int)get_user_meta($user->ID,self::SCORE_META,true);
        return '<div class="bsw-contributor-reputation"><strong>'.esc_html($user->display_name).'</strong><br><span>'.esc_html(self::tier($score)).' · '.(int)$score.' reputation points</span></div>';
    }

    private static function safe_source_url($url) {
        if (!is_string($url) || strlen($url)>2048) return false;
        $parts=wp_parse_url($url); if (!$parts || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''),array('https','http'),true)) return false;
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) && !in_array((int)$parts['port'],array(80,443),true)) return false;
        $host=strtolower($parts['host']); if ($host==='localhost' || substr($host,-10)==='.localhost' || substr($host,-6)==='.local' || filter_var($host,FILTER_VALIDATE_IP) && !filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) return false;
        return wp_http_validate_url($url) ? esc_url_raw($url) : false;
    }

    public static function verify_sources() {
        $id=absint($_GET['post_id'] ?? 0);
        if (!$id || !current_user_can('edit_post',$id) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')),'bsw_p62_verify_'.$id)) wp_die('Permission denied.',403);
        $lines=preg_split('/\r\n|\r|\n/',(string)get_post_meta($id,'_bsw_p62_sources',true)); $results=array();
        foreach (array_slice(array_unique(array_filter(array_map('trim',$lines))),0,20) as $url) {
            $safe=self::safe_source_url($url);
            if (!$safe) { $results[]=array('url'=>$url,'code'=>0,'checked'=>gmdate('c'),'message'=>'Rejected: URL is not an allowed public HTTP(S) address'); continue; }
            $response=wp_safe_remote_head($safe,array('timeout'=>8,'redirection'=>0,'reject_unsafe_urls'=>true,'sslverify'=>true,'limit_response_size'=>1024,'headers'=>array('User-Agent'=>'BIGSHOR-WIKI-Source-Checker/1.0')));
            if (is_wp_error($response)) { $code=0; $message='Request failed: '.$response->get_error_code(); }
            else { $code=(int)wp_remote_retrieve_response_code($response); $message=($code>=200 && $code<400)?'URL responded; content not evaluated':'HTTP response requires review'; }
            $results[]=array('url'=>$safe,'code'=>$code,'checked'=>gmdate('c'),'message'=>$message);
        }
        update_post_meta($id,'_bsw_p62_source_results',$results);
        wp_safe_redirect(add_query_arg('bsw_sources_checked','1',get_edit_post_link($id,'url'))); exit;
    }

    public static function register_routes() {
        register_rest_route('bigshor/v3','/knowledge-search',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'search'),'permission_callback'=>'__return_true','args'=>array('q'=>array('required'=>true,'sanitize_callback'=>'sanitize_text_field'))));
    }

    public static function search($request) {
        $term=trim((string)$request->get_param('q'));
        if (function_exists('mb_strlen')) $length=mb_strlen($term,'UTF-8'); else $length=strlen($term);
        if ($length<2 || $length>100) return new WP_Error('bsw_search_query_invalid','Search query must contain 2–100 characters.',array('status'=>400));
        $page=max(1,absint($request->get_param('page'))); $per_page=min(20,max(1,absint($request->get_param('per_page') ?: 10)));
        $q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','s'=>$term,'posts_per_page'=>min(100,$per_page*3),'paged'=>$page,'no_found_rows'=>true,'ignore_sticky_posts'=>true));
        $needle=function_exists('mb_strtolower')?mb_strtolower($term,'UTF-8'):strtolower($term); $items=array();
        foreach ($q->posts as $p) {
            $title=function_exists('mb_strtolower')?mb_strtolower(get_the_title($p),'UTF-8'):strtolower(get_the_title($p));
            $excerpt=function_exists('mb_strtolower')?mb_strtolower(wp_strip_all_tags(get_the_excerpt($p)),'UTF-8'):strtolower(wp_strip_all_tags(get_the_excerpt($p)));
            $content=function_exists('mb_strtolower')?mb_strtolower(wp_strip_all_tags($p->post_content),'UTF-8'):strtolower(wp_strip_all_tags($p->post_content));
            $score=0; if (strpos($title,$needle)!==false) $score+=10; if (strpos($excerpt,$needle)!==false) $score+=4; if (strpos($content,$needle)!==false) $score+=1;
            $items[]=array('id'=>(int)$p->ID,'title'=>get_the_title($p),'url'=>get_permalink($p),'excerpt'=>wp_trim_words(wp_strip_all_tags(get_the_excerpt($p) ?: $p->post_content),35),'modified'=>get_post_modified_time('c',true,$p),'relevance'=>$score);
        }
        usort($items,function($a,$b){return $b['relevance'] <=> $a['relevance'];});
        return rest_ensure_response(array('query'=>$term,'page'=>$page,'per_page'=>$per_page,'results'=>array_slice($items,0,$per_page),'discovery_type'=>'weighted_lexical','semantic_embeddings'=>false));
    }

    public static function menu() { add_management_page('BIGSHOR Knowledge Infrastructure','BIGSHOR Knowledge','manage_options','bsw-phase62',array(__CLASS__,'admin_page')); }
    public static function admin_page() {
        if (!current_user_can('manage_options')) wp_die('Permission denied.',403);
        if (isset($_POST['bsw_p62_save_settings'])) {
            check_admin_referer('bsw_p62_settings');
            update_option(self::GATE_OPTION,!empty($_POST['publication_gate']),false);
            echo '<div class="notice notice-success"><p>Editorial publication setting saved.</p></div>';
        }
        $gate=(bool)get_option(self::GATE_OPTION,false);
        $top=get_users(array('meta_key'=>self::SCORE_META,'orderby'=>'meta_value_num','order'=>'DESC','number'=>20));
        echo '<div class="wrap"><h1>BIGSHOR Knowledge Infrastructure</h1><p>Contributor reputation, editorial governance, source availability checks and public knowledge discovery.</p>';
        echo '<h2>Publication governance</h2><form method="post">'; wp_nonce_field('bsw_p62_settings');
        echo '<p><label><input type="checkbox" name="publication_gate" value="1" '.checked($gate,true,false).' /> Require all four editorial stages before non-administrator users can publish wiki articles</label></p><p class="description">When enabled, an attempted publication without all four completed stages is changed to Pending Review. Administrators retain an override. Default: disabled.</p><p><button class="button button-primary" name="bsw_p62_save_settings" value="1">Save settings</button></p></form>';
        echo '<h2>Contributor reputation</h2><p>10 points are awarded once when an authored wiki article is first published. Scores are capped at 5,000. This is a transparent activity signal, not a measure of expertise or truthfulness.</p><table class="widefat striped"><thead><tr><th>Contributor</th><th>Points</th><th>Tier</th></tr></thead><tbody>';
        foreach ($top as $u) { $s=(int)get_user_meta($u->ID,self::SCORE_META,true); echo '<tr><td>'.esc_html($u->display_name).'</td><td>'.$s.'</td><td>'.esc_html(self::tier($s)).'</td></tr>'; }
        echo '</tbody></table><h2>Knowledge discovery API</h2><code>GET '.esc_html(rest_url('bigshor/v3/knowledge-search')).'?q=your+search&amp;per_page=10</code><p>Public endpoint returns published wiki articles only. Results use weighted lexical relevance; this release does not claim vector or AI semantic search.</p><h2>Source verification</h2><p>Editors can check up to 20 source URLs from each article editor. The check reports HTTP reachability only and does not certify source quality, factual support or independence.</p></div>';
    }

    public static function register_exporter($exporters) { $exporters['bsw-contributor-reputation']=array('exporter_friendly_name'=>'BIGSHOR contributor reputation','callback'=>array(__CLASS__,'privacy_export')); return $exporters; }
    public static function privacy_export($email_address,$page=1) {
        $user=get_user_by('email',$email_address); if (!$user) return array('data'=>array(),'done'=>true);
        $score=(int)get_user_meta($user->ID,self::SCORE_META,true); $ledger=get_user_meta($user->ID,self::LEDGER_META,true); $data=array();
        if ($page===1 && ($score || $ledger)) $data[]=array('group_id'=>'bsw-contributor-reputation','group_label'=>'BIGSHOR contributor reputation','item_id'=>'reputation-'.$user->ID,'data'=>array(array('name'=>'Reputation points','value'=>$score),array('name'=>'Activity ledger','value'=>wp_json_encode(is_array($ledger)?$ledger:array()))));
        return array('data'=>$data,'done'=>true);
    }
    public static function register_eraser($erasers) { $erasers['bsw-contributor-reputation']=array('eraser_friendly_name'=>'BIGSHOR contributor reputation','callback'=>array(__CLASS__,'privacy_erase')); return $erasers; }
    public static function privacy_erase($email_address,$page=1) {
        $user=get_user_by('email',$email_address); if (!$user) return array('items_removed'=>false,'items_retained'=>false,'messages'=>array(),'done'=>true);
        delete_user_meta($user->ID,self::SCORE_META); delete_user_meta($user->ID,self::LEDGER_META);
        return array('items_removed'=>true,'items_retained'=>false,'messages'=>array('BIGSHOR contributor reputation score and activity ledger erased.'),'done'=>true);
    }
}
BSW_Phase62_Global_Enterprise_Knowledge::init();
}
