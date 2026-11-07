<?php
/**
 * BIGSHOR Wiki advanced knowledge, quality, analytics and AI connector layer.
 * WordPress-native implementation. External search/object storage and AI services
 * require their own credentials/infrastructure and are not silently simulated.
 */
if (!defined('ABSPATH')) { exit; }

/** ---------- Knowledge graph ---------- */
add_action('add_meta_boxes', function () {
    add_meta_box('bsw_v13_relations', 'Knowledge graph relationships', 'bsw_v13_relations_box', 'wiki_entity', 'normal', 'high');
});
function bsw_v13_relations_box($post) {
    wp_nonce_field('bsw_v13_relations_save', 'bsw_v13_relations_nonce');
    $relations = (string) get_post_meta($post->ID, '_bsw_entity_relations', true);
    echo '<p>One relationship per line: <code>TARGET-ENTITY-ID | relationship | source URL</code></p>';
    echo '<textarea name="bsw_v13_relations" rows="7" style="width:100%">' . esc_textarea($relations) . '</textarea>';
    echo '<p class="description">Use the target entity’s stable BIGSHOR ID (for example BSW-E000123). Only published entities are exposed in the public graph.</p>';
}
add_action('save_post_wiki_entity', function ($post_id) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
    if (!isset($_POST['bsw_v13_relations_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_v13_relations_nonce'])), 'bsw_v13_relations_save')) return;
    if (!current_user_can('edit_post', $post_id)) return;
    $raw = isset($_POST['bsw_v13_relations']) ? sanitize_textarea_field(wp_unslash($_POST['bsw_v13_relations'])) : '';
    $clean = array();
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $parts = array_map('trim', explode('|', $line, 3));
        if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') continue;
        $source = isset($parts[2]) ? esc_url_raw($parts[2]) : '';
        $clean[] = sanitize_text_field($parts[0]) . ' | ' . sanitize_text_field($parts[1]) . ' | ' . $source;
    }
    update_post_meta($post_id, '_bsw_entity_relations', implode("\n", $clean));
}, 30);

add_action('save_post_wiki_entity', function ($post_id, $post, $update) {
    if (!$post instanceof WP_Post || wp_is_post_revision($post_id) || $post->post_status === 'auto-draft') return;
    $current = trim((string) get_post_meta($post_id, '_bsw_entity_id', true));
    if ($current !== '') return;
    $seq = (int) get_option('bsw_entity_sequence', 0) + 1;
    update_option('bsw_entity_sequence', $seq, false);
    update_post_meta($post_id, '_bsw_entity_id', 'BSW-E' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT));
}, 40, 3);

function bsw_v13_entity_by_stable_id($stable_id) {
    $q = new WP_Query(array('post_type'=>'wiki_entity','post_status'=>'publish','posts_per_page'=>1,'meta_key'=>'_bsw_entity_id','meta_value'=>sanitize_text_field($stable_id),'no_found_rows'=>true));
    return $q->have_posts() ? $q->posts[0] : null;
}
function bsw_v13_entity_relations($entity_id, $direction = 'out') {
    $entity_id = absint($entity_id);
    $stable = (string) get_post_meta($entity_id, '_bsw_entity_id', true);
    $items = array();
    if ($direction === 'out' || $direction === 'both') {
        $raw = (string) get_post_meta($entity_id, '_bsw_entity_relations', true);
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) < 2) continue;
            $target = bsw_v13_entity_by_stable_id($parts[0]);
            if ($target) $items[] = array('from'=>$stable,'to'=>(string)get_post_meta($target->ID,'_bsw_entity_id',true),'relationship'=>$parts[1],'source'=>$parts[2] ?? '', 'title'=>get_the_title($target),'url'=>get_permalink($target));
        }
    }
    if ($direction === 'in' || $direction === 'both') {
        $all = get_posts(array('post_type'=>'wiki_entity','post_status'=>'publish','numberposts'=>500,'fields'=>'ids','meta_query'=>array(array('key'=>'_bsw_entity_relations','compare'=>'EXISTS'))));
        foreach ($all as $source_id) {
            if ($source_id === $entity_id) continue;
            $raw = (string) get_post_meta($source_id, '_bsw_entity_relations', true);
            foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
                $parts = array_map('trim', explode('|', $line, 3));
                if (count($parts) >= 2 && $parts[0] === $stable) $items[] = array('from'=>(string)get_post_meta($source_id,'_bsw_entity_id',true),'to'=>$stable,'relationship'=>$parts[1],'source'=>$parts[2] ?? '', 'title'=>get_the_title($source_id),'url'=>get_permalink($source_id));
            }
        }
    }
    return $items;
}
add_shortcode('bigshor_knowledge_graph', function ($atts) {
    $atts = shortcode_atts(array('id'=>''), $atts);
    $post = $atts['id'] !== '' ? bsw_v13_entity_by_stable_id($atts['id']) : (is_singular('wiki_entity') ? get_post() : null);
    if (!$post) return '<p>Select a published knowledge entity to explore its connections.</p>';
    $rels = bsw_v13_entity_relations($post->ID, 'both');
    if (!$rels) return '<p>No verified graph relationships have been added to this entity yet.</p>';
    $out = '<section class="bsw-knowledge-graph"><h2>Connected knowledge</h2><ul>';
    foreach ($rels as $rel) $out .= '<li><a href="' . esc_url($rel['url']) . '">' . esc_html($rel['title']) . '</a> <span>' . esc_html($rel['relationship']) . '</span>' . (!empty($rel['source']) ? ' <a rel="nofollow noopener" target="_blank" href="' . esc_url($rel['source']) . '">[source]</a>' : '') . '</li>';
    return $out . '</ul></section>';
});

/** ---------- Article verification and source coverage ---------- */
add_action('add_meta_boxes', function () {
    add_meta_box('bsw_v13_verification', 'Verification & article quality', 'bsw_v13_verification_box', 'wiki_article', 'side', 'high');
    add_meta_box('bsw_v13_event', 'Timeline / On This Day', 'bsw_v13_event_box', 'wiki_article', 'side', 'default');
});
function bsw_v13_verification_box($post) {
    wp_nonce_field('bsw_v13_verification_save', 'bsw_v13_verification_nonce');
    $status = get_post_meta($post->ID, '_bsw_v13_verification', true) ?: 'not_checked';
    $date = get_post_meta($post->ID, '_bsw_v13_last_reviewed', true);
    $notes = get_post_meta($post->ID, '_bsw_v13_review_notes', true);
    echo '<p><label for="bsw_v13_verification">Editorial assessment</label><select id="bsw_v13_verification" name="bsw_v13_verification" style="width:100%">';
    foreach (array('not_checked'=>'Not checked','needs_sources'=>'Needs sources','in_review'=>'In review','reviewed'=>'Reviewed','fact_checked'=>'Fact-checked by editor','disputed'=>'Disputed') as $key=>$label) echo '<option value="' . esc_attr($key) . '" ' . selected($status,$key,false) . '>' . esc_html($label) . '</option>';
    echo '</select></p><p><label>Last editorial review</label><input type="date" name="bsw_v13_last_reviewed" value="' . esc_attr($date) . '" style="width:100%"></p><p><label>Review notes (internal)</label><textarea name="bsw_v13_review_notes" rows="3" style="width:100%">' . esc_textarea($notes) . '</textarea></p><p class="description">This is an editorial workflow label, not an automatic truth score. Only an authorised editor should mark an article fact-checked.</p>';
}
function bsw_v13_event_box($post) {
    wp_nonce_field('bsw_v13_event_save', 'bsw_v13_event_nonce');
    echo '<p><label>Event date (for On This Day)<input type="date" name="bsw_v13_event_date" value="' . esc_attr(get_post_meta($post->ID,'_bsw_event_date',true)) . '" style="width:100%"></label></p>';
    echo '<p class="description">Optional historical event date. Include a reliable citation in the article body.</p>';
}
add_action('save_post_wiki_article', function ($post_id) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post',$post_id)) return;
    if (isset($_POST['bsw_v13_verification_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_v13_verification_nonce'])), 'bsw_v13_verification_save')) {
        $status = sanitize_key(wp_unslash($_POST['bsw_v13_verification'] ?? 'not_checked'));
        $allowed = array('not_checked','needs_sources','in_review','reviewed','fact_checked','disputed');
        if (!in_array($status,$allowed,true)) $status='not_checked';
        if ($status === 'fact_checked' && !current_user_can('publish_posts')) $status='reviewed';
        update_post_meta($post_id,'_bsw_v13_verification',$status);
        update_post_meta($post_id,'_bsw_v13_last_reviewed',sanitize_text_field(wp_unslash($_POST['bsw_v13_last_reviewed'] ?? '')));
        update_post_meta($post_id,'_bsw_v13_review_notes',sanitize_textarea_field(wp_unslash($_POST['bsw_v13_review_notes'] ?? '')));
    }
    if (isset($_POST['bsw_v13_event_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_v13_event_nonce'])), 'bsw_v13_event_save')) {
        $date = sanitize_text_field(wp_unslash($_POST['bsw_v13_event_date'] ?? ''));
        if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) $date='';
        update_post_meta($post_id,'_bsw_event_date',$date);
    }
}, 50);
add_filter('the_content', function ($content) {
    if (!is_singular('wiki_article') || !in_the_loop() || !is_main_query()) return $content;
    $status = get_post_meta(get_the_ID(),'_bsw_v13_verification',true) ?: 'not_checked';
    $date = get_post_meta(get_the_ID(),'_bsw_v13_last_reviewed',true);
    $labels = array('not_checked'=>'Not independently reviewed','needs_sources'=>'Additional sources requested','in_review'=>'Under editorial review','reviewed'=>'Editorially reviewed','fact_checked'=>'Fact-checked by an editor','disputed'=>'Disputed — see article discussion');
    $out = '<aside class="bsw-v13-quality"><strong>Editorial status:</strong> ' . esc_html($labels[$status] ?? $labels['not_checked']);
    if ($date) $out .= ' <span>· Last reviewed: ' . esc_html($date) . '</span>';
    return $out . '</aside>' . $content;
}, 7);

add_shortcode('bigshor_on_this_day', function ($atts) {
    $atts = shortcode_atts(array('limit'=>12),$atts);
    $today = current_time('m-d');
    $q = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>max(1,min(30,absint($atts['limit']))),'meta_query'=>array(array('key'=>'_bsw_event_date','value'=>'-'.$today,'compare'=>'LIKE')),'orderby'=>'title','order'=>'ASC'));
    if (!$q->have_posts()) return '<p>No historical entries are indexed for today yet.</p>';
    $out='<ul class="bsw-on-this-day">'; while($q->have_posts()){$q->the_post();$date=get_post_meta(get_the_ID(),'_bsw_event_date',true);$out.='<li><time>' . esc_html($date) . '</time> — <a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></li>'; } wp_reset_postdata(); return $out.'</ul>';
});

/** ---------- Privacy-conscious aggregate analytics ---------- */
add_action('template_redirect', function () {
    if (is_singular('wiki_article') && !is_preview()) {
        $id = get_queried_object_id();
        if ($id && !get_transient('bsw_v13_view_' . $id . '_' . gmdate('YmdHi'))) {
            $count = (int)get_post_meta($id,'_bsw_view_count',true);
            update_post_meta($id,'_bsw_view_count',$count+1);
            set_transient('bsw_v13_view_' . $id . '_' . gmdate('YmdHi'),1,90);
        }
    }
}, 20);
add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) return;
    add_action('wp', function () use ($query) {
        if ($query->found_posts === 0) {
            $n = (int)get_option('bsw_v13_no_result_searches',0);
            update_option('bsw_v13_no_result_searches',$n+1,false);
        }
    }, 20);
}, 30);
add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=wiki_article','Platform Analytics','Platform Analytics','edit_others_posts','bsw-v13-analytics','bsw_v13_analytics_page');
});
function bsw_v13_analytics_page() {
    if (!current_user_can('edit_others_posts')) return;
    $ids = get_posts(array('post_type'=>'wiki_article','post_status'=>array('publish','pending','draft'),'numberposts'=>-1,'fields'=>'ids'));
    $views=0; $needs=0; $disputed=0; $stale=0;
    foreach($ids as $id){$views+=(int)get_post_meta($id,'_bsw_view_count',true);$s=get_post_meta($id,'_bsw_v13_verification',true)?:'not_checked';if(in_array($s,array('not_checked','needs_sources'),true))$needs++;if($s==='disputed')$disputed++;$mod=get_post_modified_time('U',true,$id);if($mod && $mod < time()-180*DAY_IN_SECONDS)$stale++;}
    $counts=array('Wiki articles'=>count($ids),'Approximate article views (sampled, privacy-preserving)'=>$views,'Needs editorial/source review'=>$needs,'Disputed articles'=>$disputed,'Not updated in 180+ days'=>$stale,'No-result searches (aggregate)'=> (int)get_option('bsw_v13_no_result_searches',0));
    echo '<div class="wrap"><h1>BIGSHOR Wiki Platform Analytics</h1><p>Aggregate operational indicators only. No visitor IP addresses or raw search terms are stored by this module.</p><table class="widefat striped" style="max-width:900px"><tbody>';foreach($counts as $label=>$value)echo '<tr><th>'.esc_html($label).'</th><td>'.number_format_i18n($value).'</td></tr>';echo '</tbody></table></div>';
}

/** ---------- Configurable AI research assistant (opt-in) ---------- */
function bsw_v13_ai_configured() {
    return defined('BSW_AI_API_KEY') && BSW_AI_API_KEY && defined('BSW_AI_API_URL') && BSW_AI_API_URL && wp_http_validate_url(BSW_AI_API_URL) && strpos(BSW_AI_API_URL,'https://') === 0;
}
add_action('rest_api_init', function () {
    register_rest_route('bigshor/v1','/ai-assist',array(
        'methods'=>'POST',
        'permission_callback'=>function(){return current_user_can('edit_posts');},
        'args'=>array('prompt'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_textarea_field')),
        'callback'=>function(WP_REST_Request $request){
            if (!bsw_v13_ai_configured()) return new WP_Error('bsw_ai_not_configured','AI assistant is not configured. Add BSW_AI_API_KEY and BSW_AI_API_URL to wp-config.php.',array('status'=>503));
            $uid=get_current_user_id(); if(get_transient('bsw_v13_ai_rate_'.$uid))return new WP_Error('bsw_ai_rate_limited','Please wait before sending another research request.',array('status'=>429));
            $prompt=trim((string)$request->get_param('prompt')); if(strlen($prompt)<10||strlen($prompt)>5000)return new WP_Error('bsw_ai_prompt_length','Prompt must be between 10 and 5,000 characters.',array('status'=>400));
            set_transient('bsw_v13_ai_rate_'.$uid,1,20);
            $model=defined('BSW_AI_MODEL')&&BSW_AI_MODEL?BSW_AI_MODEL:'gpt-4o-mini';
            $response=wp_remote_post(BSW_AI_API_URL,array('timeout'=>45,'redirection'=>0,'headers'=>array('Authorization'=>'Bearer '.BSW_AI_API_KEY,'Content-Type'=>'application/json'),'body'=>wp_json_encode(array('model'=>$model,'messages'=>array(array('role'=>'system','content'=>'You are a research assistant for an encyclopedia. Suggest research directions, source types and neutral wording. Never claim a fact is verified unless the user supplies reliable evidence. Clearly label uncertain claims and remind the editor to independently verify every citation.'),array('role'=>'user','content'=>$prompt)),'temperature'=>0.2))));
            if(is_wp_error($response))return new WP_Error('bsw_ai_request_failed','The configured AI service could not be reached.',array('status'=>502));
            $code=(int)wp_remote_retrieve_response_code($response);$body=json_decode(wp_remote_retrieve_body($response),true);
            if($code<200||$code>=300||!is_array($body))return new WP_Error('bsw_ai_bad_response','The AI service returned an unsuccessful or unreadable response.',array('status'=>502));
            $text=$body['choices'][0]['message']['content']??''; if(!is_string($text)||$text==='')return new WP_Error('bsw_ai_empty','The AI service did not return a response.',array('status'=>502));
            return rest_ensure_response(array('draft'=>wp_kses_post($text),'notice'=>'AI-generated research assistance. Verify all claims and sources independently before publication.'));
        }
    ));
    register_rest_route('bigshor/v1','/graph',array(
        'methods'=>'GET','permission_callback'=>'__return_true',
        'callback'=>function(WP_REST_Request $request){
            $stable=sanitize_text_field((string)$request->get_param('entity_id'));$post=bsw_v13_entity_by_stable_id($stable);
            if(!$post)return new WP_Error('bsw_entity_not_found','Published entity not found.',array('status'=>404));
            return rest_ensure_response(array('entity'=>array('id'=>$stable,'title'=>get_the_title($post),'url'=>get_permalink($post)),'relationships'=>bsw_v13_entity_relations($post->ID,'both')));
        }
    ));
});
add_shortcode('bigshor_ai_research_assistant', function () {
    if (!is_user_logged_in() || !current_user_can('edit_posts')) return '<p>The research assistant is available to authorised editors only.</p>';
    if (!bsw_v13_ai_configured()) return '<p>The AI research assistant is not configured yet. An administrator must add the AI service URL and API key in <code>wp-config.php</code>.</p>';
    $endpoint=esc_url(rest_url('bigshor/v1/ai-assist'));$nonce=esc_attr(wp_create_nonce('wp_rest'));
    return '<section class="bsw-ai-assistant"><h2>BIGSHOR Research Assistant</h2><p>Use this tool for research planning and neutral drafting. Independently verify every factual claim and source.</p><label for="bsw-ai-prompt">Research question</label><textarea id="bsw-ai-prompt" rows="5" maxlength="5000"></textarea><button type="button" id="bsw-ai-run">Generate research notes</button><div id="bsw-ai-result" aria-live="polite"></div></section><script>(function(){const b=document.getElementById("bsw-ai-run");if(!b)return;b.addEventListener("click",async function(){const p=document.getElementById("bsw-ai-prompt").value;const o=document.getElementById("bsw-ai-result");o.textContent="Working…";b.disabled=true;try{const r=await fetch(' . wp_json_encode($endpoint) . ',{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":' . wp_json_encode($nonce) . '},body:JSON.stringify({prompt:p})});const j=await r.json();if(!r.ok)throw new Error(j.message||"Request failed");o.textContent=j.draft+"\n\n"+j.notice;}catch(e){o.textContent=e.message;}finally{b.disabled=false;}});})();</script>';
});

/** ---------- Admin integration status and API discoverability ---------- */
add_action('admin_menu', function () {
    add_submenu_page('edit.php?post_type=wiki_article','Integrations & API','Integrations & API','manage_options','bsw-v13-integrations','bsw_v13_integrations_page');
});
function bsw_v13_integrations_page() {
    if (!current_user_can('manage_options')) return;
    echo '<div class="wrap"><h1>BIGSHOR Wiki integrations</h1><p><strong>Core engine:</strong> Active. <strong>Knowledge graph API:</strong> <code>' . esc_html(rest_url('bigshor/v1/graph?entity_id=BSW-E000001')) . '</code></p><h2>Optional external services</h2><ul><li><strong>AI:</strong> Add <code>BSW_AI_API_URL</code>, <code>BSW_AI_API_KEY</code> and optional <code>BSW_AI_MODEL</code> constants to <code>wp-config.php</code>. Use an HTTPS OpenAI-compatible chat-completions endpoint. The key is not stored in WordPress options.</li><li><strong>Search at scale:</strong> WordPress search is the default. OpenSearch / Elasticsearch requires a separately deployed service and a compatible connector such as ElasticPress.</li><li><strong>Media at scale:</strong> WordPress Media Library is the default. S3-compatible object storage and a CDN require hosting credentials and a separately configured offload plugin.</li><li><strong>Mail:</strong> Configure authenticated SMTP using a trusted mail provider for account and moderation notifications.</li><li><strong>Backups:</strong> Keep independent database and uploads backups outside the web host.</li><li><strong>Wikibase:</strong> This release provides a WordPress-native entity and relationship API, not a full Wikibase repository, SPARQL endpoint or MediaWiki installation.</li></ul><p>External integrations remain disabled until configured. No service credentials are bundled in this package.</p></div>';
}

/** ---------- Contribution quality signals (not raw edit-count ranking) ---------- */
function bsw_v13_award_reputation($user_id, $points, $reason) {
    $user_id = absint($user_id);
    if (!$user_id || $points < 1) return;
    $score = (int)get_user_meta($user_id, '_bsw_reputation_score', true);
    update_user_meta($user_id, '_bsw_reputation_score', min(1000000, $score + absint($points)));
    $log = get_user_meta($user_id, '_bsw_reputation_log', true);
    if (!is_array($log)) $log = array();
    $log[] = array('points'=>absint($points),'reason'=>sanitize_text_field($reason),'date'=>current_time('mysql'));
    update_user_meta($user_id, '_bsw_reputation_log', array_slice($log, -50));
}
function bsw_v13_award_approved_proposal($meta_id, $object_id, $meta_key, $meta_value) {
    if ($meta_key !== '_bsw_proposal_state' || $meta_value !== 'approved') return;
    $proposal = get_post($object_id);
    if ($proposal && $proposal->post_type === 'wiki_edit_proposal') bsw_v13_award_reputation((int)$proposal->post_author, 3, 'Accepted article edit proposal');
}
add_action('added_post_meta','bsw_v13_award_approved_proposal',10,4);
add_action('updated_post_meta','bsw_v13_award_approved_proposal',10,4);
function bsw_v13_award_published_contributor_article($new_status, $old_status, $post) {
    if (!$post instanceof WP_Post || $post->post_type !== 'wiki_article' || $old_status === 'publish' || $new_status !== 'publish') return;
    if (user_can((int)$post->post_author, 'manage_options')) return;
    if (get_post_meta($post->ID, '_bsw_v13_reputation_awarded', true)) return;
    bsw_v13_award_reputation((int)$post->post_author, 5, 'Article accepted for publication');
    update_post_meta($post->ID, '_bsw_v13_reputation_awarded', '1');
}
add_action('transition_post_status','bsw_v13_award_published_contributor_article',20,3);
add_shortcode('bigshor_contributor_reputation', function ($atts) {
    $atts = shortcode_atts(array('limit'=>20),$atts);
    $users = get_users(array('number'=>max(1,min(50,absint($atts['limit']))),'meta_key'=>'_bsw_reputation_score','orderby'=>'meta_value_num','order'=>'DESC'));
    if (!$users) return '<p>No contribution reputation scores have been recorded yet.</p>';
    $out='<ol class="bsw-reputation-leaderboard">';
    foreach($users as $user){$score=(int)get_user_meta($user->ID,'_bsw_reputation_score',true);if(!$score)continue;$out.='<li><a href="'.esc_url(get_author_posts_url($user->ID)).'">'.esc_html($user->display_name).'</a> <span>'.number_format_i18n($score).' quality points</span></li>';}
    return $out.'</ol>';
});
