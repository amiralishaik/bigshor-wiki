<?php
/**
 * BIGSHOR Wiki Phase 5.3 — final editorial governance and reporting.
 * All outputs are advisory; no automatic publishing or source credibility verdicts.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase5_Finalisation')) {
final class BSW_Phase5_Finalisation {
    const META_ARCHIVE_URL = '_bsw_p5_archive_url';
    const META_ARCHIVE_NOTES = '_bsw_p5_archive_notes';
    const META_SCHEMA_AUDIT = '_bsw_p5_schema_audit';

    public static function boot() {
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'metabox'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save'), 80, 2);
        add_action('admin_menu', array(__CLASS__, 'menu'), 65);
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function metabox() {
        add_meta_box('bsw-p5-final', 'BIGSHOR Citation Recovery & Structured Data', array(__CLASS__, 'metabox_html'), 'wiki_article', 'normal', 'low');
    }

    public static function metabox_html($post) {
        wp_nonce_field('bsw_p5_final_save', 'bsw_p5_final_nonce');
        $archive = get_post_meta($post->ID, self::META_ARCHIVE_URL, true);
        $notes = get_post_meta($post->ID, self::META_ARCHIVE_NOTES, true);
        $audit = get_post_meta($post->ID, self::META_SCHEMA_AUDIT, true);
        echo '<p>Record a stable archived copy when an original citation becomes unavailable. Only enter an archive URL after an editor has checked that it represents the cited source.</p>';
        echo '<p><label for="bsw_p5_archive_url"><strong>Verified archive URL (HTTPS)</strong></label><br/><input class="widefat" type="url" id="bsw_p5_archive_url" name="bsw_p5_archive_url" value="'.esc_attr($archive).'" placeholder="https://web.archive.org/web/..."/></p>';
        echo '<p><label for="bsw_p5_archive_notes"><strong>Recovery notes and original citation URL</strong></label><br/><textarea class="widefat" rows="3" id="bsw_p5_archive_notes" name="bsw_p5_archive_notes">'.esc_textarea($notes).'</textarea></p>';
        echo '<hr/><p><strong>Structured data readiness check</strong> — checks article fields needed for common Article schema output. It does not inspect the final page HTML or validate schema emitted by the theme/SEO plugin.</p>';
        echo '<button type="button" class="button" id="bsw-p5-schema-audit" data-post="'.esc_attr($post->ID).'">Run structured data readiness check</button> <span id="bsw-p5-schema-status">'.($audit ? 'Last check: '.esc_html($audit['checked_at'] ?? 'recorded') : 'Not checked yet').'</span><div id="bsw-p5-schema-results" style="margin-top:8px"></div>';
        echo '<hr/><p><strong>AI claim-by-claim review</strong> — asks the configured AI provider to extract checkable claims, identify missing evidence and list verification questions. It cannot independently verify facts.</p>';
        echo '<button type="button" class="button" id="bsw-p5-claim-review" data-post="'.esc_attr($post->ID).'">Generate claim review</button><div id="bsw-p5-claim-results" style="margin-top:8px;white-space:pre-wrap;max-height:360px;overflow:auto"></div>';
    }

    public static function save($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || !isset($_POST['bsw_p5_final_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p5_final_nonce'])), 'bsw_p5_final_save')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $url = isset($_POST['bsw_p5_archive_url']) ? esc_url_raw(trim(wp_unslash($_POST['bsw_p5_archive_url'])), array('https')) : '';
        if ($url && (!wp_http_validate_url($url) || strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)) !== 'https')) $url = '';
        update_post_meta($post_id, self::META_ARCHIVE_URL, $url);
        update_post_meta($post_id, self::META_ARCHIVE_NOTES, isset($_POST['bsw_p5_archive_notes']) ? sanitize_textarea_field(wp_unslash($_POST['bsw_p5_archive_notes'])) : '');
    }

    public static function routes() {
        register_rest_route('bigshor/v1', '/schema-readiness', array(
            'methods'=>'POST','callback'=>array(__CLASS__,'schema_readiness'),
            'permission_callback'=>function($r){$id=absint($r->get_param('post_id'));return $id && current_user_can('edit_post',$id);},
            'args'=>array('post_id'=>array('required'=>true,'sanitize_callback'=>'absint'))
        ));
        register_rest_route('bigshor/v1', '/claim-review', array(
            'methods'=>'POST','callback'=>array(__CLASS__,'claim_review'),
            'permission_callback'=>function($r){$id=absint($r->get_param('post_id'));return $id && current_user_can('edit_post',$id);},
            'args'=>array('post_id'=>array('required'=>true,'sanitize_callback'=>'absint'))
        ));
    }

    public static function schema_readiness($request) {
        $id=absint($request->get_param('post_id')); $p=get_post($id);
        if (!$p || $p->post_type!=='wiki_article') return new WP_Error('bsw_missing_article','Article not found.',array('status'=>404));
        $checks=array(
            'headline'=>array('pass'=>trim($p->post_title)!=='','detail'=>'Article title is present'),
            'description'=>array('pass'=>has_excerpt($p),'detail'=>'An editorial excerpt is present'),
            'author'=>array('pass'=>(bool)get_the_author_meta('display_name',$p->post_author),'detail'=>'An author account is assigned'),
            'datePublished'=>array('pass'=>!empty($p->post_date_gmt) && $p->post_date_gmt!=='0000-00-00 00:00:00','detail'=>'A publication date is recorded'),
            'dateModified'=>array('pass'=>!empty($p->post_modified_gmt),'detail'=>'A modification date is recorded'),
            'image'=>array('pass'=>(bool)get_post_thumbnail_id($id),'detail'=>'A featured image is assigned'),
        );
        $passed=0; foreach($checks as $c) if($c['pass']) $passed++;
        $result=array('score'=>$passed,'out_of'=>count($checks),'checks'=>$checks,'checked_at'=>current_time('mysql'),'notice'=>'Readiness checklist only. It does not fetch the rendered page, inspect third-party schema output, or certify Schema.org validity.');
        update_post_meta($id,self::META_SCHEMA_AUDIT,$result);
        return rest_ensure_response($result);
    }

    public static function claim_review($request) {
        $id=absint($request->get_param('post_id')); $p=get_post($id);
        if (!$p || $p->post_type!=='wiki_article') return new WP_Error('bsw_missing_article','Article not found.',array('status'=>404));
        if (!class_exists('BSW_Phase5_Assistance')) return new WP_Error('bsw_ai_unavailable','AI assistance module is unavailable.',array('status'=>503));
        $s=BSW_Phase5_Assistance::settings();
        if (empty($s['enabled']) || empty($s['api_key']) || empty($s['model'])) return new WP_Error('bsw_ai_not_configured','An administrator must enable and configure AI assistance first.',array('status'=>400));
        $slot=BSW_Phase5_Assistance::consume_ai_request_slot(get_current_user_id());
        if (is_wp_error($slot)) return $slot;
        $content=trim(wp_strip_all_tags(strip_shortcodes($p->post_content)));
        if ($content==='') return new WP_Error('bsw_empty_article','Add article content before requesting a claim review.',array('status'=>400));
        $content=function_exists('mb_substr')?mb_substr($content,0,18000):substr($content,0,18000);
        $system='You are a cautious editorial research assistant. Extract specific factual claims from the supplied encyclopedia draft. For each claim provide: claim wording, what evidence would be needed, whether the supplied draft contains a citation nearby (do not assume the citation supports it), questions an editor should ask, and a suggested priority. Never invent sources, URLs, quotations, dates, evidence or verification results. You have not opened or checked any cited source. Do not label claims true or false. Flag claims about living people for careful human review. Finish with a short list of claims that should not be published until independently checked.';
        $payload=array('model'=>$s['model'],'messages'=>array(array('role'=>'system','content'=>$system),array('role'=>'user','content'=>"Article title: ".$p->post_title."\n\nDraft:\n".$content)),'temperature'=>0.1);
        $response=wp_safe_remote_post($s['endpoint'],array('timeout'=>35,'redirection'=>0,'sslverify'=>true,'reject_unsafe_urls'=>true,'limit_response_size'=>65536,'headers'=>array('Authorization'=>'Bearer '.$s['api_key'],'Content-Type'=>'application/json'),'body'=>wp_json_encode($payload)));
        if(is_wp_error($response)) return new WP_Error('bsw_claim_request_failed','The AI provider request failed. Check provider settings and logs.',array('status'=>502));
        $code=(int)wp_remote_retrieve_response_code($response); $body=json_decode(wp_remote_retrieve_body($response),true);
        if($code<200 || $code>=300) return new WP_Error('bsw_claim_provider_error','The AI provider returned an error. Check provider configuration and usage.',array('status'=>502));
        $answer=$body['choices'][0]['message']['content']??'';
        if(!is_string($answer)||trim($answer)==='') return new WP_Error('bsw_claim_empty','The AI provider returned no usable review.',array('status'=>502));
        return rest_ensure_response(array('review'=>wp_strip_all_tags($answer),'notice'=>'AI-assisted claim triage only. No claim or citation was independently verified. Human source-by-source review is mandatory.'));
    }

    public static function menu() {
        add_submenu_page('edit.php?post_type=wiki_article','Phase 5 Analytics & Schema','Analytics & Schema','edit_others_posts','bsw-p5-analytics',array(__CLASS__,'dashboard'));
    }

    public static function dashboard() {
        if(!current_user_can('edit_others_posts')) return;
        $posts=get_posts(array('post_type'=>'wiki_article','post_status'=>array('publish','draft','pending','private'),'numberposts'=>1000,'orderby'=>'modified','order'=>'DESC'));
        $published=0; $views=0; $reviewed=0; $disputed=0; $low_sources=0; $schema_ready=0; $archive_count=0; $quality_total=0; $quality_n=0; $source_types=array('primary'=>0,'secondary'=>0,'tertiary'=>0,'mixed'=>0,'unclassified'=>0); $independence=array('independent'=>0,'mixed'=>0,'non_independent'=>0,'unassessed'=>0);
        foreach($posts as $p) {
            if($p->post_status==='publish') {$published++;$views+=(int)get_post_meta($p->ID,'_bsw_p4_view_count',true);}
            $fact=get_post_meta($p->ID,BSW_Phase5_Intelligence::META_FACT_STATUS,true);
            if($fact==='sources_checked') $reviewed++; if($fact==='disputed') $disputed++;
            $conf=get_post_meta($p->ID,BSW_Phase5_Completion::META_SOURCE_CONFIDENCE,true);
            if($conf==='low') $low_sources++;
            $stype=get_post_meta($p->ID,BSW_Phase5_Completion::META_SOURCE_CLASS,true); if(isset($source_types[$stype])) $source_types[$stype]++; else $source_types['unclassified']++;
            $ind=get_post_meta($p->ID,BSW_Phase5_Completion::META_INDEPENDENCE,true); if(isset($independence[$ind])) $independence[$ind]++; else $independence['unassessed']++;
            if(get_post_meta($p->ID,self::META_ARCHIVE_URL,true)) $archive_count++;
            $audit=BSW_Phase5_Intelligence::audit($p); if(isset($audit['score'])){$quality_total+=(int)$audit['score'];$quality_n++;}
            if(has_excerpt($p) && get_post_thumbnail_id($p->ID) && $p->post_title && $p->post_author) $schema_ready++;
        }
        $avg=$quality_n?round($quality_total/$quality_n):0;
        echo '<div class="wrap"><h1>BIGSHOR Phase 5 — Analytics & Schema</h1><p>Editorial reporting is based on available WordPress metadata. View totals use BIGSHOR’s approximate per-article counter, not Google Analytics or unique-user analytics.</p>';
        echo '<div style="display:flex;gap:12px;flex-wrap:wrap">';
        $cards=array('Articles'=>count($posts),'Published'=>$published,'Recorded views'=>$views,'Sources checked by editor'=>$reviewed,'Disputed / attention'=>$disputed,'Low-confidence source assessments'=>$low_sources,'Archive records'=>$archive_count,'Average completeness'=>$avg.'/100','Schema readiness signals'=>$schema_ready);
        foreach($cards as $label=>$value) echo '<div class="card" style="min-width:150px;padding:12px"><strong>'.esc_html($label).'</strong><p style="font-size:22px;margin:8px 0">'.esc_html($value).'</p></div>';
        echo '</div><h2>Editorial interpretation</h2><ul><li>Completeness score measures presence of editorial elements, not truth or neutrality.</li><li>Source classifications and confidence are human-entered judgements, not automated reputation scores.</li><li>Schema readiness is a checklist of article fields; validate the rendered page separately with an external structured-data testing tool.</li><li>Recorded views are approximate and should not be compared directly with analytics-platform sessions.</li></ul>';
        echo '<p><a class="button button-primary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=bsw_p5_export'),'bsw_p5_export')).'">Download editorial CSV</a> <a class="button" href="'.esc_url(admin_url('edit.php?post_type=wiki_article&page=bsw-p5-visibility')).'">Open Visibility & Trust</a></p></div>';
    }

    public static function assets($hook) {
        if(!in_array($hook,array('post.php','post-new.php'),true)) return;
        $screen=function_exists('get_current_screen')?get_current_screen():null; if(!$screen||$screen->post_type!=='wiki_article') return;
        wp_register_script('bsw-p5-final',false,array(),'19.3.0',true); wp_enqueue_script('bsw-p5-final');
        wp_localize_script('bsw-p5-final','BSWP5Final',array('root'=>esc_url_raw(rest_url('bigshor/v1/')),'nonce'=>wp_create_nonce('wp_rest')));
        $js="(function(){function run(id,box,button){button.disabled=true;box.textContent='Checking…';fetch(BSWP5Final.root+id,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':BSWP5Final.nonce},body:JSON.stringify({post_id:button.getAttribute('data-post')})}).then(function(r){return r.json().then(function(d){if(!r.ok)throw new Error(d.message||'Request failed');return d;});}).then(function(d){box.textContent='';if(d.checks){var p=document.createElement('p');p.textContent='Readiness: '+d.score+'/'+d.out_of+' — '+d.notice;box.appendChild(p);Object.keys(d.checks).forEach(function(k){var row=document.createElement('p');row.textContent=(d.checks[k].pass?'✓ ':'• ')+k+': '+d.checks[k].detail;box.appendChild(row);});}else{var n=document.createElement('p');n.textContent=d.notice;box.appendChild(n);var pre=document.createElement('pre');pre.style.whiteSpace='pre-wrap';pre.textContent=d.review;box.appendChild(pre);}}).catch(function(e){box.textContent=e.message;}).finally(function(){button.disabled=false;});}document.addEventListener('click',function(e){var a=e.target.closest('#bsw-p5-schema-audit');if(a){var b=document.getElementById('bsw-p5-schema-results');run('schema-readiness',b,a);var s=document.getElementById('bsw-p5-schema-status');if(s)s.textContent='Check completed; save article to retain results.';}var c=e.target.closest('#bsw-p5-claim-review');if(c)run('claim-review',document.getElementById('bsw-p5-claim-results'),c);});})();";
        wp_add_inline_script('bsw-p5-final',$js);
    }
}
BSW_Phase5_Finalisation::boot();
}
