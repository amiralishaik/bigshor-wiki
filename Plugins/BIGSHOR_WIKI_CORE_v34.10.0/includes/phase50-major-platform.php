<?php
/**
 * BIGSHOR major platform completion layer.
 * Adds bounded parser expansion, deletion review, account/IP restrictions,
 * event notifications, namespace taxonomy, translation workspace, bot tokens,
 * media integrity/history and operational diagnostics.
 * WordPress remains the canonical identity, content and media store.
 */
if (!defined('ABSPATH')) exit;
final class BSW_Phase50_Major_Platform {
    const VERSION = '50.0.0';
    const NOTICE_META = '_bsw_event_notifications';
    const BOT_OPTION = 'bsw_bot_tokens_v1';
    const DELETE_TYPE = 'bsw_deletion_request';
    const LOG_OPTION = 'bsw_phase50_audit_log';

    public static function boot() {
        add_action('init', array(__CLASS__, 'register_structures'), 25);
        add_action('init', array(__CLASS__, 'register_restriction_hooks'), 30);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('add_meta_boxes', array(__CLASS__, 'deletion_metabox'));
        add_action('save_post_wiki_article', array(__CLASS__, 'content_quality_scan'), 90, 3);
        add_action('admin_post_bsw50_delete_request', array(__CLASS__, 'submit_delete_request'));
        add_action('admin_post_bsw50_delete_decision', array(__CLASS__, 'decide_delete_request'));
        add_action('admin_post_bsw50_user_restriction', array(__CLASS__, 'save_restriction'));
        add_action('admin_post_bsw50_bot_token', array(__CLASS__, 'bot_token_action'));
        add_action('admin_post_bsw50_translation_create', array(__CLASS__, 'create_translation'));
        add_action('save_post_wiki_article', array(__CLASS__, 'notify_article_event'), 80, 3);
        add_action('transition_comment_status', array(__CLASS__, 'notify_comment_event'), 10, 3);
        add_action('add_attachment', array(__CLASS__, 'index_media_integrity'));
        add_action('edit_attachment', array(__CLASS__, 'index_media_integrity'));
        add_filter('rest_pre_dispatch', array(__CLASS__, 'enforce_restrictions_rest'), 10, 3);
        add_filter('wp_insert_post_data', array(__CLASS__, 'enforce_restrictions_post'), 10, 2);
        add_filter('the_content', array(__CLASS__, 'render_namespace_label'), 60);
        add_shortcode('bsw50_notifications', array(__CLASS__, 'notifications_shortcode'));
        add_shortcode('bsw50_translation_workspace', array(__CLASS__, 'translation_workspace_shortcode'));
        add_shortcode('bsw50_media_history', array(__CLASS__, 'media_history_shortcode'));
        add_shortcode('bsw50_delete_request', array(__CLASS__, 'delete_request_shortcode'));
        add_action('rest_api_init', array(__CLASS__, 'register_api'));
    }

    public static function register_structures() {
        if (!taxonomy_exists('wiki_namespace')) register_taxonomy('wiki_namespace', array('wiki_article'), array(
            'labels'=>array('name'=>'Wiki Namespaces','singular_name'=>'Wiki Namespace'), 'public'=>true,
            'hierarchical'=>false,'show_ui'=>true,'show_admin_column'=>true,'show_in_rest'=>true,
            'rewrite'=>array('slug'=>'wiki-namespace')
        ));
        if (!post_type_exists(self::DELETE_TYPE)) register_post_type(self::DELETE_TYPE, array(
            'labels'=>array('name'=>'Deletion Requests','singular_name'=>'Deletion Request'), 'public'=>false,
            'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=wiki_article','show_in_rest'=>false,
            'supports'=>array('title','editor','author'),'capability_type'=>'post','map_meta_cap'=>true
        ));
    }

    /* Parser expansion: recursive balanced-brace scanner; never evaluates PHP or arbitrary code. */
    public static function expand_wikitext($source, $depth=0, &$budget=100) {
        $source = (string)$source;
        if ($depth > 8 || $budget <= 0) return $source;
        $out=''; $len=strlen($source); $i=0;
        while ($i < $len) {
            if ($i+1 < $len && $source[$i]==='{' && $source[$i+1]==='{') {
                $start=$i; $level=0; $quote=false; $end=null;
                for ($j=$i; $j<$len-1; $j++) {
                    if ($source[$j]==='{' && $source[$j+1]==='{') { $level++; $j++; continue; }
                    if ($source[$j]==='}' && $source[$j+1]==='}') { $level--; $j++; if ($level===0) { $end=$j+1; break; } }
                }
                if ($end!==null) {
                    $budget--; $inside=substr($source,$start+2,$end-$start-4);
                    $expanded=self::expand_template_expression($inside,$depth+1,$budget);
                    $out.=$expanded; $i=$end; continue;
                }
            }
            $out.=$source[$i]; $i++;
        }
        return $out;
    }
    private static function split_template_args($raw) {
        $parts=array(); $buf=''; $level=0; $len=strlen($raw);
        for($i=0;$i<$len;$i++) {
            if($i+1<$len && $raw[$i]==='{' && $raw[$i+1]==='{'){$level++;$buf.='{{';$i++;continue;}
            if($i+1<$len && $raw[$i]==='}' && $raw[$i+1]==='}'){$level=max(0,$level-1);$buf.='}}';$i++;continue;}
            if($raw[$i]==='|' && $level===0){$parts[]=$buf;$buf='';continue;}
            $buf.=$raw[$i];
        }
        $parts[]=$buf; return $parts;
    }
    private static function expand_template_expression($inside,$depth,&$budget) {
        $parts=self::split_template_args($inside); $name=trim(array_shift($parts));
        $args=array();$pos=1;
        foreach($parts as $part){$pair=explode('=',$part,2);if(count($pair)===2 && preg_match('/^[a-zA-Z0-9 _-]+$/',trim($pair[0])))$args[sanitize_key(trim($pair[0]))]=self::expand_wikitext(trim($pair[1]),$depth,$budget);else $args[(string)$pos++]=self::expand_wikitext(trim($part),$depth,$budget);}
        $lower=strtolower(trim($name));
        if (strpos($lower,'#if:')===0) { $test=trim(substr($name,4)); return !empty($test) ? ($args['1']??'') : ($args['2']??''); }
        if (strpos($lower,'#ifeq:')===0) { $test=trim(substr($name,6)); return ((string)$test===(string)($args['1']??'')) ? ($args['2']??'') : ($args['3']??''); }
        if (strpos($lower,'#switch:')===0) { $test=trim(substr($name,8)); $default=''; foreach($args as $k=>$v){if($k==='default')$default=$v;elseif((string)$k===(string)$test)return $v;} return $default; }
        if (strpos($lower,'#len:')===0) return (string)strlen(trim(substr($name,5)));
        if (strpos($lower,'#uc:')===0) return esc_html(strtoupper(trim(substr($name,4))));
        if (strpos($lower,'#lc:')===0) return esc_html(strtolower(trim(substr($name,4))));
        if (strpos($lower,'#tag:')===0) return '';
        $template=sanitize_key(str_replace(' ','_',strtolower($name)));
        if (class_exists('BSW_Phase5_Template_Registry')) {
            $fields=$args; unset($fields['1'],$fields['2'],$fields['3'],$fields['default']);
            $html=BSW_Phase5_Template_Registry::render($template,$fields);
            if($html!=='') return $html;
        }
        return '{{'.$inside.'}}';
    }

    /* Deletion discussion: request first; authorised reviewer decides; trash preserves recoverability. */
    public static function delete_request_shortcode($atts=array()) {
        if (!is_singular('wiki_article')) return '';
        $id=get_queried_object_id();
        if (!is_user_logged_in()) return '<p><a href="'.esc_url(wp_login_url(get_permalink($id))).'">Sign in</a> to propose deletion.</p>';
        $url=esc_url(admin_url('admin-post.php'));
        return '<form method="post" action="'.$url.'"><input type="hidden" name="action" value="bsw50_delete_request"><input type="hidden" name="post_id" value="'.absint($id).'">'.wp_nonce_field('bsw50_delete_'.$id,'_wpnonce',true,false).'<label>Reason for deletion <textarea name="reason" required maxlength="3000"></textarea></label><button type="submit">Open deletion discussion</button></form>';
    }
    public static function submit_delete_request() {
        $id=absint($_POST['post_id']??0); if(!$id || !is_user_logged_in() || get_post_type($id)!=='wiki_article') wp_die('Invalid deletion request.');
        check_admin_referer('bsw50_delete_'.$id); $reason=sanitize_textarea_field(wp_unslash($_POST['reason']??'')); if($reason==='') wp_die('A reason is required.');
        $rid=wp_insert_post(array('post_type'=>self::DELETE_TYPE,'post_status'=>'pending','post_title'=>'Deletion discussion: '.get_the_title($id),'post_content'=>$reason,'post_author'=>get_current_user_id()),true);
        if(is_wp_error($rid)) wp_die(esc_html($rid->get_error_message())); update_post_meta($rid,'_bsw50_target_article',$id); update_post_meta($rid,'_bsw50_decision','pending'); self::audit('deletion_requested',$id,array('request_id'=>$rid)); wp_safe_redirect(get_permalink($id)); exit;
    }
    public static function deletion_metabox() {
        add_meta_box('bsw50_delete_decision','BIGSHOR Deletion Discussion',array(__CLASS__,'render_deletion_metabox'),self::DELETE_TYPE,'side','high');
    }
    public static function render_deletion_metabox($post) {
        if(!current_user_can('delete_others_posts')) { echo '<p>Only authorised reviewers can decide deletion requests.</p>'; return; }
        $target=absint(get_post_meta($post->ID,'_bsw50_target_article',true));$decision=get_post_meta($post->ID,'_bsw50_decision',true)?:'pending';
        echo '<p>Target: '.($target?'<a href="'.esc_url(get_edit_post_link($target)).'">'.esc_html(get_the_title($target)).'</a>':'Missing article').'</p><p>Status: '.esc_html($decision).'</p>';
        if($decision==='pending'&&$target){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw50_delete_decision"><input type="hidden" name="request_id" value="'.absint($post->ID).'">'.wp_nonce_field('bsw50_decide_'.$post->ID,'_wpnonce',true,false).'<button class="button button-primary" name="decision" value="approve">Approve and move article to Trash</button> <button class="button" name="decision" value="decline">Decline request</button></form>';}
        echo '<p>Approved deletion uses WordPress Trash so administrators can restore the article.</p>';
    }
    public static function decide_delete_request() {
        if(!current_user_can('delete_others_posts')) wp_die('Not authorised.'); $rid=absint($_POST['request_id']??0); check_admin_referer('bsw50_decide_'.$rid);
        $decision=sanitize_key($_POST['decision']??''); if(!in_array($decision,array('approve','decline'),true)) wp_die('Invalid decision.');
        $target=absint(get_post_meta($rid,'_bsw50_target_article',true)); if(get_post_type($rid)!==self::DELETE_TYPE || get_post_type($target)!=='wiki_article') wp_die('Invalid request.');
        update_post_meta($rid,'_bsw50_decision',$decision); update_post_meta($rid,'_bsw50_decided_by',get_current_user_id()); update_post_meta($rid,'_bsw50_decided_at',current_time('mysql'));
        if($decision==='approve') { $result=wp_trash_post($target); if(!$result) wp_die('Article could not be moved to Trash.'); self::audit('article_trashed_after_deletion_review',$target,array('request_id'=>$rid)); }
        wp_update_post(array('ID'=>$rid,'post_status'=>'private')); wp_safe_redirect(admin_url('edit.php?post_type='.self::DELETE_TYPE)); exit;
    }

    /* Restrictions: account and IP blocks are enforced for article edits, comments and REST writes. */
    private static function current_ip_hash() { $ip=isset($_SERVER['REMOTE_ADDR'])?sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])):''; return $ip!==''?hash_hmac('sha256',$ip,wp_salt('auth')):''; }
    public static function register_restriction_hooks() { add_filter('comment_flood_filter',array(__CLASS__,'block_comment'),10,3); }
    public static function is_restricted() {
        $uid=get_current_user_id(); if($uid && get_user_meta($uid,'_bsw50_blocked',true)) return true;
        if (class_exists('BSW_Phase51_Advanced_Platform') && BSW_Phase51_Advanced_Platform::is_cidr_restricted()) return true;
        $hash=self::current_ip_hash(); $list=get_option('bsw50_ip_blocks',array()); return $hash && is_array($list) && !empty($list[$hash]) && (empty($list[$hash]['expires']) || $list[$hash]['expires']>time());
    }
    public static function enforce_restrictions_post($data,$postarr) {
        if(self::is_restricted() && !empty($postarr['post_type']) && in_array($postarr['post_type'],array('wiki_article','wiki_talk','wiki_edit_proposal'),true) && !current_user_can('manage_options')) wp_die('Your BIGSHOR editing access is currently restricted.');
        return $data;
    }
    public static function enforce_restrictions_rest($result,$server,$request) {
        if(self::is_restricted() && in_array($request->get_method(),array('POST','PUT','PATCH','DELETE'),true) && strpos($request->get_route(),'/wp/v2/')===0 && !current_user_can('manage_options')) return new WP_Error('bsw_user_restricted','Your BIGSHOR editing access is restricted.',array('status'=>403));
        return $result;
    }
    public static function block_comment($flood,$time_lastcomment,$time_newcomment) { return self::is_restricted() ? true : $flood; }
    public static function save_restriction() {
        if(!current_user_can('manage_options')) wp_die('Not authorised.'); check_admin_referer('bsw50_user_restriction');
        $mode=sanitize_key($_POST['mode']??''); $reason=sanitize_textarea_field(wp_unslash($_POST['reason']??''));
        if($mode==='ip_block' || $mode==='ip_unblock') {
            $ip=sanitize_text_field(wp_unslash($_POST['ip_address']??'')); if(!filter_var($ip,FILTER_VALIDATE_IP)) wp_die('Enter a valid IPv4 or IPv6 address.');
            $hash=hash_hmac('sha256',$ip,wp_salt('auth'));$list=get_option('bsw50_ip_blocks',array());if(!is_array($list))$list=array();
            if($mode==='ip_block')$list[$hash]=array('reason'=>$reason,'created'=>time(),'expires'=>0,'by'=>get_current_user_id());else unset($list[$hash]);update_option('bsw50_ip_blocks',$list,false);self::audit($mode,0,array('ip_hash'=>$hash,'reason'=>$reason));wp_safe_redirect(admin_url('options-general.php?page=bsw50-platform'));exit;
        }
        $uid=absint($_POST['user_id']??0); if(!$uid || !get_user_by('id',$uid) || $uid===get_current_user_id()) wp_die('Invalid target account.');
        update_user_meta($uid,'_bsw50_blocked',$mode==='block'?1:0); update_user_meta($uid,'_bsw50_block_reason',$reason); update_user_meta($uid,'_bsw50_blocked_by',get_current_user_id()); self::audit('account_'.$mode,$uid,array('reason'=>$reason));
        wp_safe_redirect(admin_url('users.php')); exit;
    }

    /* Event notifications: per-user inbox; deduped by event/revision, bounded to 150 records. */
    private static function push_notice($uid,$event,$post_id,$message) {
        $uid=absint($uid); if(!$uid) return; $items=get_user_meta($uid,self::NOTICE_META,true); if(!is_array($items))$items=array();
        $rev=(int)get_post_modified_time('U',true,$post_id); $key=md5($event.'|'.$post_id.'|'.$rev);
        foreach($items as $item) if(!empty($item['key']) && hash_equals($item['key'],$key)) return;
        $items[]=array('id'=>wp_generate_uuid4(),'key'=>$key,'event'=>$event,'post_id'=>$post_id,'message'=>$message,'url'=>get_permalink($post_id),'created'=>current_time('mysql'),'read'=>false);
        update_user_meta($uid,self::NOTICE_META,array_slice($items,-150));
    }
    public static function notify_article_event($post_id,$post,$update) {
        if(wp_is_post_revision($post_id)||wp_is_post_autosave($post_id)||$post->post_type!=='wiki_article'||$post->post_status!=='publish') return;
        $users=get_users(array('fields'=>'ID','number'=>3000)); foreach($users as $uid){$watch=get_user_meta($uid,'_bsw_watchlist',true);if(is_array($watch)&&in_array((int)$post_id,array_map('absint',$watch),true))self::push_notice($uid,$update?'article_updated':'article_published',$post_id,($update?'Updated: ':'Published: ').get_the_title($post_id));}
    }
    public static function notify_comment_event($new_status,$old_status,$comment) {
        if($new_status!=='approved'||!$comment instanceof WP_Comment) return; $post=get_post($comment->comment_post_ID); if(!$post||$post->post_type!=='wiki_talk') return;
        $article=absint(get_post_meta($post->ID,'_bsw_talk_article',true)); $author=absint($post->post_author); if($author && $author!==(int)$comment->user_id) self::push_notice($author,'talk_reply',$article,'New reply in discussion: '.$post->post_title);
    }
    public static function notifications_shortcode() {
        if(!is_user_logged_in()) return '<p>Please sign in to view notifications.</p>'; $uid=get_current_user_id(); $items=get_user_meta($uid,self::NOTICE_META,true); if(!is_array($items))$items=array();
        if(isset($_GET['bsw50_read'])&&isset($_GET['_wpnonce'])){$id=sanitize_text_field(wp_unslash($_GET['bsw50_read']));if(wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])),'bsw50_read_'.$id)){foreach($items as &$it)if(!empty($it['id'])&&hash_equals($it['id'],$id))$it['read']=true;unset($it);update_user_meta($uid,self::NOTICE_META,$items);}}
        $out='<section class="bsw50-notifications"><h2>Notifications</h2><ul>';foreach(array_reverse($items) as $it){$out.='<li'.(empty($it['read'])?' class="unread"':'').'><a href="'.esc_url($it['url']??'#').'">'.esc_html($it['message']??'Update').'</a> <time>'.esc_html($it['created']??'').'</time>';if(empty($it['read'])&&!empty($it['id']))$out.=' <a href="'.esc_url(wp_nonce_url(add_query_arg('bsw50_read',$it['id']),'bsw50_read_'.$it['id'])).'">Mark read</a>';$out.='</li>'; } return $out.'</ul></section>';
    }

    /* Translation workspace: creates a draft linked to source article; translation remains human-reviewed. */
    public static function translation_workspace_shortcode() {
        if(!is_singular('wiki_article'))return ''; $id=get_queried_object_id(); $group=get_post_meta($id,'_bsw_p4_translation_group',true); $code=get_post_meta($id,'_bsw_language_code',true)?:'en';
        $out='<section class="bsw50-translation"><h2>Translations</h2><p>Current language: '.esc_html($code).'. Translations are separate drafts and require independent sourcing and review.</p>';
        if(is_user_logged_in()&&current_user_can('edit_posts'))$out.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw50_translation_create"><input type="hidden" name="source_id" value="'.absint($id).'">'.wp_nonce_field('bsw50_translation_'.$id,'_wpnonce',true,false).'<label>Target language code <input name="language" pattern="[a-zA-Z-]{2,12}" required></label><button type="submit">Create translation draft</button></form>';
        $out.='<ul>'; $ids=get_post_meta($id,'_bsw_p4_translations',true); if(is_array($ids))foreach($ids as $other){$other=absint($other);if(get_post_type($other)==='wiki_article')$out.='<li><a href="'.esc_url(get_permalink($other)).'">'.esc_html(get_the_title($other)).'</a></li>';}$out.='</ul></section>';return $out;
    }
    public static function create_translation() {
        $source=absint($_POST['source_id']??0); if(!is_user_logged_in()||!current_user_can('edit_posts')||get_post_type($source)!=='wiki_article')wp_die('Not authorised.');check_admin_referer('bsw50_translation_'.$source);
        $lang=sanitize_key(strtolower(wp_unslash($_POST['language']??''))); if(!preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/',$lang))wp_die('Invalid language code.');
        $src=get_post($source);$draft=wp_insert_post(array('post_type'=>'wiki_article','post_status'=>'draft','post_title'=>$src->post_title.' ('.$lang.')','post_content'=>'','post_author'=>get_current_user_id()),true);if(is_wp_error($draft))wp_die(esc_html($draft->get_error_message()));
        $group=get_post_meta($source,'_bsw_p4_translation_group',true);if(!$group)$group='bsw-'.$source;update_post_meta($source,'_bsw_p4_translation_group',$group);update_post_meta($draft,'_bsw_p4_translation_group',$group);update_post_meta($draft,'_bsw_language_code',$lang);
        $links=get_post_meta($source,'_bsw_p4_translations',true);if(!is_array($links))$links=array();$links[]=$draft;update_post_meta($source,'_bsw_p4_translations',array_values(array_unique(array_map('absint',$links))));update_post_meta($draft,'_bsw_p4_translations',array($source));self::audit('translation_draft_created',$draft,array('source'=>$source,'language'=>$lang));wp_safe_redirect(get_edit_post_link($draft,'url'));exit;
    }

    /* Bot API: hashed bearer tokens; WordPress application passwords remain supported as well. */
    public static function register_api() {
        register_rest_route('bigshor/v1','/bot/status',array('methods'=>'GET','callback'=>function(){return array('platform'=>'BIGSHOR Wiki','api_version'=>'1','bot_auth'=>'bearer token or WordPress Application Password');},'permission_callback'=>array(__CLASS__,'bot_permission')));
        register_rest_route('bigshor/v1','/articles/export',array('methods'=>'GET','callback'=>array(__CLASS__,'api_export'),'permission_callback'=>array(__CLASS__,'bot_permission')));
    }
    public static function bot_permission($request) {
        if(current_user_can('edit_others_posts'))return true; $header=$request->get_header('authorization'); if(!$header||stripos($header,'Bearer ')!==0)return new WP_Error('bsw_api_auth','A valid BIGSHOR bot bearer token is required.',array('status'=>401));
        $token=trim(substr($header,7));$tokens=get_option(self::BOT_OPTION,array());if(!is_array($tokens))return new WP_Error('bsw_api_auth','Invalid token.',array('status'=>401));$hash=hash('sha256',$token);
        foreach($tokens as $row)if(!empty($row['hash'])&&hash_equals($row['hash'],$hash)&&empty($row['revoked']))return true;
        return new WP_Error('bsw_api_auth','Invalid or revoked token.',array('status'=>401));
    }
    public static function api_export($request) {
        $page=max(1,absint($request->get_param('page')));$per=min(100,max(1,absint($request->get_param('per_page')?:25)));
        $q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>$per,'paged'=>$page,'orderby'=>'modified','order'=>'DESC','no_found_rows'=>false));$items=array();
        foreach($q->posts as $p)$items[]=array('id'=>$p->ID,'title'=>$p->post_title,'slug'=>$p->post_name,'url'=>get_permalink($p),'modified_gmt'=>$p->post_modified_gmt,'content'=>apply_filters('the_content',$p->post_content),'language'=>get_post_meta($p->ID,'_bsw_language_code',true)?:'en');
        return rest_ensure_response(array('page'=>$page,'per_page'=>$per,'total'=>$q->found_posts,'pages'=>$q->max_num_pages,'articles'=>$items));
    }
    public static function bot_token_action() {
        if(!current_user_can('manage_options'))wp_die('Not authorised.');check_admin_referer('bsw50_bot_token');$tokens=get_option(self::BOT_OPTION,array());if(!is_array($tokens))$tokens=array();$action=sanitize_key($_POST['token_action']??'create');
        if($action==='revoke'){$id=sanitize_text_field($_POST['token_id']??'');foreach($tokens as &$t)if(!empty($t['id'])&&hash_equals($t['id'],$id))$t['revoked']=1;unset($t);update_option(self::BOT_OPTION,$tokens,false);wp_safe_redirect(admin_url('options-general.php?page=bsw50-platform'));exit;}
        $plain='bsw_'.wp_generate_password(48,false,false);$tokens[]=array('id'=>wp_generate_uuid4(),'hash'=>hash('sha256',$plain),'label'=>sanitize_text_field(wp_unslash($_POST['label']??'Wiki bot')),'created'=>current_time('mysql'),'created_by'=>get_current_user_id(),'revoked'=>0);update_option(self::BOT_OPTION,$tokens,false);set_transient('bsw50_new_bot_token_'.get_current_user_id(),$plain,90);wp_safe_redirect(admin_url('options-general.php?page=bsw50-platform&token_created=1'));exit;
    }

    /* Media history and duplicate fingerprints. */
    public static function index_media_integrity($id) {
        $path=get_attached_file($id);if(!$path||!is_file($path)||!is_readable($path))return;$hash=hash_file('sha256',$path);if(!$hash)return;
        $old=get_post_meta($id,'_bsw50_sha256',true);
        if($old && !hash_equals((string)$old,(string)$hash)) {
            $history=get_post_meta($id,'_bsw50_media_history',true);if(!is_array($history))$history=array();
            $history[]=array('date'=>current_time('mysql'),'event'=>'file content changed','hash'=>$old,'user'=>get_current_user_id());update_post_meta($id,'_bsw50_media_history',array_slice($history,-50));
        }
        update_post_meta($id,'_bsw50_sha256',$hash);$matches=get_posts(array('post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>5,'meta_key'=>'_bsw50_sha256','meta_value'=>$hash,'exclude'=>array($id),'fields'=>'ids'));
        update_post_meta($id,'_bsw50_duplicate_ids',array_map('absint',$matches));
    }
    public static function media_history_shortcode($atts) {
        $atts=shortcode_atts(array('id'=>0),$atts,'bsw50_media_history');$id=absint($atts['id']);if(!$id||get_post_type($id)!=='attachment')return '';if(!current_user_can('upload_files'))return '';
        $history=get_post_meta($id,'_bsw50_media_history',true);if(!is_array($history))$history=array();$out='<section class="bsw50-media-history"><h3>Media file history</h3><ul>';foreach(array_reverse($history) as $h)$out.='<li>'.esc_html($h['date']??'').' — '.esc_html($h['event']??'metadata update').' — '.esc_html($h['hash']??'').'</li>';$dupes=get_post_meta($id,'_bsw50_duplicate_ids',true);if(is_array($dupes)&&$dupes)$out.='<li>Possible duplicate attachment IDs: '.esc_html(implode(', ',array_map('absint',$dupes))).'</li>';$out.='</ul></section>';return $out;
    }

    public static function content_quality_scan($post_id,$post,$update) {
        if(!$post instanceof WP_Post || $post->post_type!=='wiki_article' || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        $content=(string)$post->post_content;$issues=array();
        if(preg_match('/<img\b(?![^>]*\balt\s*=)[^>]*>/i',$content))$issues[]='One or more images may be missing alt text.';
        if(preg_match('/<h([1-6])\b/i',$content,$m) && (int)$m[1]>2)$issues[]='Heading hierarchy may skip introductory levels.';
        if(preg_match('/<a\b[^>]*>\s*<\/a>/i',$content))$issues[]='One or more links have no accessible text.';
        update_post_meta($post_id,'_bsw50_accessibility_flags',$issues);
        update_post_meta($post_id,'_bsw50_content_scan_at',current_time('mysql'));
    }
    public static function render_namespace_label($content) {
        if(!is_singular('wiki_article')||!in_the_loop()||!is_main_query()||!taxonomy_exists('wiki_namespace'))return $content;$terms=get_the_terms(get_the_ID(),'wiki_namespace');if(is_wp_error($terms)||!$terms)return $content;
        $term=$terms[0];return '<div class="bsw50-namespace-label">'.esc_html($term->name).'</div>'.$content;
    }

    /* Admin diagnostics: actionable static/runtime checks; no claim of full penetration testing. */
    public static function admin_menu() { add_options_page('BIGSHOR Platform Health','BIGSHOR Platform Health','manage_options','bsw50-platform',array(__CLASS__,'health_page')); }
    public static function health_page() {
        if(!current_user_can('manage_options'))return;$checks=array(
            'WordPress version'=>get_bloginfo('version'), 'PHP version'=>PHP_VERSION,
            'HTTPS'=>is_ssl()?'Enabled':'Not detected', 'Debug display'=>defined('WP_DEBUG_DISPLAY')&&WP_DEBUG_DISPLAY?'Enabled — disable on production':'Not enabled',
            'REST API'=>function_exists('rest_url')?'Available':'Unavailable', 'Article post type'=>post_type_exists('wiki_article')?'Registered':'Missing',
            'Revision support'=>post_type_supports('wiki_article','revisions')?'Enabled':'Missing', 'Namespace taxonomy'=>taxonomy_exists('wiki_namespace')?'Registered':'Missing', 'Persistent object cache'=>wp_using_ext_object_cache()?'Enabled':'Not detected', 'Permalink structure'=>get_option('permalink_structure')?'Pretty permalinks enabled':'Plain permalinks',
            'Application passwords'=>function_exists('wp_is_application_passwords_supported')?(wp_is_application_passwords_supported()?'Supported':'Not supported'):'Check WordPress version'
        );
        echo '<div class="wrap"><h1>BIGSHOR Platform Health</h1><p>Operational checks only. This page does not replace external penetration testing, load testing or manual accessibility review.</p><table class="widefat striped"><tbody>';foreach($checks as $label=>$value)echo '<tr><th>'.esc_html($label).'</th><td>'.esc_html($value).'</td></tr>';echo '</tbody></table><h2>Automated article accessibility scan</h2><p>Newly saved articles are scanned for missing image alt attributes, empty links and possible heading-level skips. Results are advisory and require human review.</p><h2>Bot API tokens</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw50_bot_token">'.wp_nonce_field('bsw50_bot_token','_wpnonce',true,false).'<input name="label" placeholder="Bot name" required><input type="hidden" name="token_action" value="create">'.get_submit_button('Create bot token','primary','submit',false).'</form>';
        if(isset($_GET['token_created'])){$plain=get_transient('bsw50_new_bot_token_'.get_current_user_id());if($plain){echo '<div class="notice notice-success"><p>Copy this token now. It will not be shown again: <code>'.esc_html($plain).'</code></p></div>';delete_transient('bsw50_new_bot_token_'.get_current_user_id());}}
        $tokens=get_option(self::BOT_OPTION,array());if(!is_array($tokens))$tokens=array();echo '<h3>Issued bot tokens</h3><table class="widefat striped"><thead><tr><th>Label</th><th>Created</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        foreach($tokens as $token){echo '<tr><td>'.esc_html($token['label']??'Bot').'</td><td>'.esc_html($token['created']??'').'</td><td>'.(!empty($token['revoked'])?'Revoked':'Active').'</td><td>';if(empty($token['revoked']))echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw50_bot_token"><input type="hidden" name="token_action" value="revoke"><input type="hidden" name="token_id" value="'.esc_attr($token['id']).'">'.wp_nonce_field('bsw50_bot_token','_wpnonce',true,false).'<button class="button">Revoke</button></form>';echo '</td></tr>';}
        echo '</tbody></table><h2>Account restrictions</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw50_user_restriction">'.wp_nonce_field('bsw50_user_restriction','_wpnonce',true,false).'<input type="number" name="user_id" min="1" placeholder="User ID"><select name="mode"><option value="block">Block account editing</option><option value="unblock">Unblock account editing</option></select><input name="reason" placeholder="Reason"><button class="button button-primary">Apply account action</button></form>';
        echo '<h2>IP restrictions</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw50_user_restriction">'.wp_nonce_field('bsw50_user_restriction','_wpnonce',true,false).'<input name="ip_address" placeholder="IPv4 or IPv6 address" required><select name="mode"><option value="ip_block">Block IP</option><option value="ip_unblock">Unblock IP</option></select><input name="reason" placeholder="Reason"><button class="button button-primary">Apply IP action</button></form></div>';
    }
    private static function audit($event,$object_id,$data=array()) { $rows=get_option(self::LOG_OPTION,array());if(!is_array($rows))$rows=array();$rows[]=array('event'=>$event,'object_id'=>absint($object_id),'user'=>get_current_user_id(),'date'=>current_time('mysql'),'data'=>$data);update_option(self::LOG_OPTION,array_slice($rows,-500),false); }
}
BSW_Phase50_Major_Platform::boot();
