<?php
/**
 * BIGSHOR Wiki Phase 51 — advanced governance, discovery, translation, API and media.
 * Additive only. Requires WordPress 6.2+ recommended. No destructive migrations.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase51_Advanced_Platform')) {
final class BSW_Phase51_Advanced_Platform {
    const VERSION = '51.0.0';
    const QUEUE = 'bsw51_mail_queue';
    const TOKENS = 'bsw51_bot_tokens';
    const AUDIT = 'bsw51_audit';
    const MEMORY = 'bsw51_translation_memory';
    private static $bot_context = array();

    public static function boot() {
        add_action('init', array(__CLASS__, 'enforce_cidr_blocks'), 1);
        add_action('init', array(__CLASS__, 'register_shortcodes'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'revision_metabox'));
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'disambiguation_metabox'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save_disambiguation'), 20, 2);
        add_filter('the_content', array(__CLASS__, 'render_disambiguation'), 55);
        add_action('add_meta_boxes_bsw_appeal', array(__CLASS__, 'appeal_metabox'));
        add_action('admin_post_bsw51_revision', array(__CLASS__, 'revision_action'));
        add_action('admin_post_bsw51_ip_block', array(__CLASS__, 'ip_block_action'));
        add_action('admin_post_bsw51_appeal', array(__CLASS__, 'submit_appeal'));
        add_action('admin_post_bsw51_appeal_decision', array(__CLASS__, 'appeal_decision'));
        add_action('admin_post_bsw51_translation_save', array(__CLASS__, 'translation_save'));
        add_action('admin_post_bsw51_mail_preferences', array(__CLASS__, 'mail_preferences'));
        add_action('admin_post_bsw51_bot_token', array(__CLASS__, 'bot_token_action'));
        add_action('admin_post_bsw51_media_restore', array(__CLASS__, 'media_restore'));
        add_action('admin_post_bsw51_media_reindex', array(__CLASS__, 'media_reindex'));
        add_action('save_post_wiki_article', array(__CLASS__, 'queue_watch_emails'), 95, 3);
        add_action('transition_comment_status', array(__CLASS__, 'queue_talk_emails'), 20, 3);
        add_action('bsw51_process_mail_queue', array(__CLASS__, 'process_mail_queue'));
        if (!wp_next_scheduled('bsw51_process_mail_queue')) wp_schedule_event(time()+120, 'hourly', 'bsw51_process_mail_queue');
        add_filter('update_attached_file', array(__CLASS__, 'snapshot_before_file_update'), 5, 2);
        add_filter('get_children', array(__CLASS__, 'filter_suppressed_revisions'), 10, 2);
        add_action('rest_api_init', array(__CLASS__, 'register_api'));
    }

    public static function register_shortcodes() {
        add_shortcode('bsw51_search', array(__CLASS__, 'search_shortcode'));
        add_shortcode('bsw51_translation_editor', array(__CLASS__, 'translation_editor_shortcode'));
        add_shortcode('bsw51_notification_preferences', array(__CLASS__, 'preferences_shortcode'));
        add_shortcode('bsw51_appeal', array(__CLASS__, 'appeal_shortcode'));
        add_shortcode('bsw51_media_versions', array(__CLASS__, 'media_versions_shortcode'));
    }

    private static function audit($event, $object=0, $data=array()) {
        $rows=get_option(self::AUDIT,array()); if(!is_array($rows))$rows=array();
        $rows[]=array('event'=>$event,'object'=>absint($object),'user'=>get_current_user_id(),'at'=>current_time('mysql'),'data'=>$data);
        update_option(self::AUDIT,array_slice($rows,-1000),false);
    }

    /* Revision suppression is reversible: revisions remain stored and oversight can reveal/restore them. */
    public static function filter_suppressed_revisions($children, $args) {
        if (!is_array($children) || current_user_can('manage_options') || current_user_can('bsw_view_suppressed_content')) return $children;
        foreach ($children as $id=>$revision) {
            if ($revision instanceof WP_Post && get_post_meta($revision->ID,'_bsw51_suppressed',true)) unset($children[$id]);
        }
        return $children;
    }
    public static function disambiguation_metabox($post) {
        add_meta_box('bsw51_disambiguation','BIGSHOR Disambiguation Page',array(__CLASS__,'render_disambiguation_metabox'),'wiki_article','side','default');
    }
    public static function render_disambiguation_metabox($post) {
        if(!current_user_can('edit_post',$post->ID))return;
        wp_nonce_field('bsw51_disambiguation_'.$post->ID,'bsw51_disambiguation_nonce');
        $enabled=get_post_meta($post->ID,'_bsw51_is_disambiguation',true);
        $ids=get_post_meta($post->ID,'_bsw51_disambiguation_ids',true);
        echo '<p><label><input type="checkbox" name="bsw51_is_disambiguation" value="1" '.checked($enabled,1,false).'> Mark as a disambiguation page</label></p>';
        echo '<p>Related article IDs (comma-separated)</p><input class="widefat" name="bsw51_disambiguation_ids" value="'.esc_attr(is_array($ids)?implode(',',$ids):'').'">';
    }
    public static function save_disambiguation($id,$post) {
        if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;
        if(!isset($_POST['bsw51_disambiguation_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw51_disambiguation_nonce'])),'bsw51_disambiguation_'.$id))return;
        if(!current_user_can('edit_post',$id))return;
        update_post_meta($id,'_bsw51_is_disambiguation',!empty($_POST['bsw51_is_disambiguation'])?1:0);
        $raw=preg_split('/\s*,\s*/',sanitize_text_field(wp_unslash($_POST['bsw51_disambiguation_ids']??'')));
        $ids=array();
        foreach($raw as $candidate){$candidate=absint($candidate);if($candidate&&$candidate!==$id&&get_post_type($candidate)==='wiki_article'&&get_post_status($candidate)==='publish')$ids[]=$candidate;}
        update_post_meta($id,'_bsw51_disambiguation_ids',array_values(array_unique($ids)));
    }
    public static function render_disambiguation($content) {
        if(!is_singular('wiki_article')||!in_the_loop()||!is_main_query())return $content;
        $id=get_the_ID();if(!get_post_meta($id,'_bsw51_is_disambiguation',true))return $content;
        $ids=get_post_meta($id,'_bsw51_disambiguation_ids',true);$out='<aside class="bsw51-disambiguation" aria-label="Disambiguation"><strong>This article lists topics with similar names.</strong><ul>';
        foreach((array)$ids as $other){$other=absint($other);if(get_post_type($other)==='wiki_article'&&get_post_status($other)==='publish')$out.='<li><a href="'.esc_url(get_permalink($other)).'">'.esc_html(get_the_title($other)).'</a></li>';}
        return $out.'</ul></aside>'.$content;
    }

    public static function revision_metabox($post) {
        if (!current_user_can('manage_options')) return;
        add_meta_box('bsw51_revision_oversight','BIGSHOR Revision Oversight',array(__CLASS__,'render_revision_metabox'),'wiki_article','side','low');
    }
    public static function render_revision_metabox($post) {
        if (!current_user_can('manage_options')) return;
        $revs=wp_get_post_revisions($post->ID,array('posts_per_page'=>15,'orderby'=>'date','order'=>'DESC'));
        if (!$revs) { echo '<p>No revisions available.</p>'; return; }
        echo '<p>Suppression hides a revision from ordinary history views; it does not erase the stored revision.</p><ul>';
        foreach($revs as $rev) {
            $suppressed=get_post_meta($rev->ID,'_bsw51_suppressed',true);
            echo '<li>'.esc_html(get_date_from_gmt($rev->post_modified_gmt,get_option('date_format').' '.get_option('time_format'))).' — '.($suppressed?'Suppressed':'Visible');
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw51_revision"><input type="hidden" name="revision_id" value="'.absint($rev->ID).'">'.wp_nonce_field('bsw51_revision_'.$rev->ID,'_wpnonce',true,false).'<input type="hidden" name="mode" value="'.($suppressed?'reveal':'suppress').'"><input name="reason" placeholder="Oversight reason"><button class="button-link">'.($suppressed?'Reveal':'Suppress').'</button></form></li>';
        }
        echo '</ul>';
    }
    public static function appeal_metabox() {
        add_meta_box('bsw51_appeal_review','Appeal review',array(__CLASS__,'render_appeal_metabox'),'bsw_appeal','side','high');
    }
    public static function render_appeal_metabox($post) {
        if(!current_user_can('manage_options')){echo '<p>Administrators only.</p>';return;}
        $decision=get_post_meta($post->ID,'_bsw51_appeal_decision',true);
        echo '<p>Status: '.esc_html($decision?:'Pending').'</p>';
        if(!$decision) echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw51_appeal_decision"><input type="hidden" name="appeal_id" value="'.absint($post->ID).'">'.wp_nonce_field('bsw51_appeal_decision_'.$post->ID,'_wpnonce',true,false).'<button class="button button-primary" name="decision" value="uphold">Uphold appeal</button> <button class="button" name="decision" value="dismiss">Dismiss</button></form>';
    }

    public static function revision_action() {
        if(!current_user_can('manage_options')) wp_die('Oversight permission required.');
        $rid=absint($_POST['revision_id']??0); check_admin_referer('bsw51_revision_'.$rid);
        $rev=get_post($rid); if(!$rev || $rev->post_type!=='revision') wp_die('Invalid revision.');
        $suppress=sanitize_key($_POST['mode']??'suppress')==='suppress';
        update_post_meta($rid,'_bsw51_suppressed',$suppress?1:0);
        update_post_meta($rid,'_bsw51_suppression_reason',sanitize_textarea_field(wp_unslash($_POST['reason']??'')));
        update_post_meta($rid,'_bsw51_suppressed_by',get_current_user_id());
        self::audit($suppress?'revision_suppressed':'revision_unsuppressed',$rid);
        wp_safe_redirect(get_edit_post_link($rev->post_parent,'url')); exit;
    }

    /* CIDR blocks: HMAC stored network key; request IP is never persisted in plaintext. */
    private static function ip_in_cidr($ip,$cidr) {
        if (strpos($cidr,'/')===false) { $left=@inet_pton($cidr); $right=@inet_pton($ip); return $left!==false && $right!==false && strlen($left)===strlen($right) && hash_equals($left,$right); }
        list($subnet,$bits)=explode('/',$cidr,2); $a=@inet_pton($ip); $b=@inet_pton($subnet);
        if($a===false||$b===false||strlen($a)!==strlen($b)) return false;
        $max=strlen($a)*8; if(!ctype_digit((string)$bits)||(int)$bits<0||(int)$bits>$max)return false;
        $bits=(int)$bits; $bytes=intdiv($bits,8); $remain=$bits%8;
        if($bytes && substr($a,0,$bytes)!==substr($b,0,$bytes))return false;
        if($remain){$mask=(0xff << (8-$remain)) & 0xff;if((ord($a[$bytes])&$mask)!==(ord($b[$bytes])&$mask))return false;}
        return true;
    }
    /** Enforce stored IP/CIDR restrictions before rendering public or authenticated pages. */
    public static function enforce_cidr_blocks() {
        // Preserve an administrator's ability to review and lift an accidental block.
        if (is_user_logged_in() && current_user_can('manage_options')) return;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
        if ($ip !== '' && self::is_cidr_restricted($ip)) {
            status_header(403);
            nocache_headers();
            wp_die('Access to BIGSHOR WIKI is restricted for this network.', 'Access restricted', array('response' => 403));
        }
    }

    public static function is_cidr_restricted($ip=null) {
        $ip=$ip?:($_SERVER['REMOTE_ADDR']??''); if(!filter_var($ip,FILTER_VALIDATE_IP))return false;
        $rows=get_option('bsw51_cidr_blocks',array()); if(!is_array($rows))return false;
        foreach($rows as $row) {
            if(empty($row['network_hash']) || (!empty($row['expires']) && (int)$row['expires']<time()))continue;
            // Network text is encrypted with authenticated WordPress salt so the CIDR can be matched without plaintext storage.
            if(empty($row['network_enc']))continue;
            $network=self::decrypt_network($row['network_enc']);
            if($network && hash_equals($row['network_hash'],hash_hmac('sha256',$network,wp_salt('auth'))) && self::ip_in_cidr($ip,$network))return true;
        }
        return false;
    }
    private static function encrypt_network($value) {
        if(!function_exists('openssl_encrypt'))return '';
        $key=hash('sha256',wp_salt('auth'),true);$iv=random_bytes(16);
        $cipher=openssl_encrypt($value,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);
        return $cipher===false?'':base64_encode($iv.$cipher);
    }
    private static function decrypt_network($value) {
        if(!function_exists('openssl_decrypt'))return '';
        $raw=base64_decode($value,true);if(!$raw||strlen($raw)<17)return '';
        return (string)openssl_decrypt(substr($raw,16),'AES-256-CBC',hash('sha256',wp_salt('auth'),true),OPENSSL_RAW_DATA,substr($raw,0,16));
    }
    public static function ip_block_action() {
        if(!current_user_can('manage_options'))wp_die('Not authorised.');check_admin_referer('bsw51_ip_block');
        $network=trim(sanitize_text_field(wp_unslash($_POST['network']??'')));
        if(strpos($network,'/')===false){if(!filter_var($network,FILTER_VALIDATE_IP))wp_die('Invalid IP or CIDR.');}
        else {list($ip,$bits)=explode('/',$network,2);$packed=@inet_pton($ip);if($packed===false||!ctype_digit($bits)||(int)$bits<0||(int)$bits>strlen($packed)*8)wp_die('Invalid CIDR range.');}
        $rows=get_option('bsw51_cidr_blocks',array());if(!is_array($rows))$rows=array();$hash=hash_hmac('sha256',$network,wp_salt('auth'));
        if(sanitize_key($_POST['mode']??'block')==='unblock'){unset($rows[$hash]);}
        else {$days=min(3650,max(0,absint($_POST['days']??0)));$encrypted=self::encrypt_network($network);if(!$encrypted)wp_die('OpenSSL encryption is required to store CIDR blocks securely.');$rows[$hash]=array('network_hash'=>$hash,'network_enc'=>$encrypted,'reason'=>sanitize_textarea_field(wp_unslash($_POST['reason']??'')),'created'=>time(),'expires'=>$days?time()+$days*DAY_IN_SECONDS:0,'by'=>get_current_user_id());}
        update_option('bsw51_cidr_blocks',$rows,false);self::audit('cidr_'.sanitize_key($_POST['mode']??'block'),0,array('network_hash'=>$hash));
        wp_safe_redirect(admin_url('options-general.php?page=bsw51-advanced'));exit;
    }
    public static function submit_appeal() {
        $uid=get_current_user_id();if(!$uid)wp_die('Sign in to submit an appeal.');check_admin_referer('bsw51_appeal');
        $reason=sanitize_textarea_field(wp_unslash($_POST['reason']??''));if(!$reason)wp_die('Please explain your appeal.');
        $id=wp_insert_post(array('post_type'=>'bsw_appeal','post_status'=>'pending','post_title'=>'Access appeal — user '.$uid,'post_content'=>$reason,'post_author'=>$uid),true);
        if(is_wp_error($id))wp_die(esc_html($id->get_error_message()));self::audit('appeal_submitted',$id);wp_safe_redirect(wp_get_referer()?:home_url('/'));exit;
    }
    public static function appeal_shortcode() {
        if(!is_user_logged_in())return '<p>Please sign in to submit an access appeal.</p>';
        return '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw51_appeal">'.wp_nonce_field('bsw51_appeal','_wpnonce',true,false).'<label>Appeal reason <textarea name="reason" required maxlength="4000"></textarea></label><button type="submit">Submit appeal</button></form>';
    }
    public static function appeal_decision() {
        if(!current_user_can('manage_options'))wp_die('Not authorised.');$id=absint($_POST['appeal_id']??0);check_admin_referer('bsw51_appeal_decision_'.$id);
        if(get_post_type($id)!=='bsw_appeal')wp_die('Invalid appeal.');$decision=sanitize_key($_POST['decision']??'');
        if(!in_array($decision,array('uphold','dismiss'),true))wp_die('Invalid decision.');
        update_post_meta($id,'_bsw51_appeal_decision',$decision);update_post_meta($id,'_bsw51_appeal_decided_by',get_current_user_id());wp_update_post(array('ID'=>$id,'post_status'=>'private'));self::audit('appeal_'.$decision,$id);wp_safe_redirect(admin_url('edit.php?post_type=bsw_appeal'));exit;
    }

    /* Durable email queue with retry/backoff; user can opt out. */
    private static function enqueue_mail($uid,$subject,$body,$event_key) {
        $uid=absint($uid);$u=get_userdata($uid);if(!$u)return;
        $prefs=get_user_meta($uid,'_bsw51_mail_preferences',true);if(is_array($prefs)&&isset($prefs[$event_key])&&!$prefs[$event_key])return;
        $queue=get_option(self::QUEUE,array());if(!is_array($queue))$queue=array();
        $dedupe=md5($uid.'|'.$event_key.'|'.$subject);
        foreach($queue as $item)if(!empty($item['dedupe'])&&hash_equals($item['dedupe'],$dedupe))return;
        $queue[]=array('uid'=>$uid,'to'=>$u->user_email,'subject'=>$subject,'body'=>$body,'dedupe'=>$dedupe,'attempts'=>0,'next'=>time(),'created'=>time());
        update_option(self::QUEUE,array_slice($queue,-1000),false);
    }
    public static function queue_watch_emails($id,$post,$update) {
        if(!$post instanceof WP_Post||$post->post_type!=='wiki_article'||$post->post_status!=='publish'||wp_is_post_revision($id)||wp_is_post_autosave($id))return;
        $watchers=get_users(array('fields'=>'ID','number'=>1000,'meta_key'=>'_bsw_watchlist'));
        foreach($watchers as $uid){$watch=get_user_meta($uid,'_bsw_watchlist',true);if(is_array($watch)&&in_array((int)$id,array_map('absint',$watch),true))self::enqueue_mail($uid,($update?'Article updated: ':'Article published: ').get_the_title($id),'A watched BIGSHOR Wiki article changed: '.get_permalink($id),'watchlist');}
    }
    public static function queue_talk_emails($new,$old,$comment) {
        if($new!=='approved'||!$comment instanceof WP_Comment)return;$post=get_post($comment->comment_post_ID);if(!$post||$post->post_type!=='wiki_talk')return;
        $uid=(int)$post->post_author;if($uid&&$uid!==(int)$comment->user_id)self::enqueue_mail($uid,'New reply in BIGSHOR Wiki discussion','A discussion you started has a new reply. '.get_permalink($post->ID),'talk');
    }
    public static function process_mail_queue() {
        $queue=get_option(self::QUEUE,array());if(!is_array($queue)||!$queue)return;$remain=array();$sent=0;
        foreach($queue as $item){if($sent>=40||!empty($item['next'])&&$item['next']>time()){$remain[]=$item;continue;}
            $ok=wp_mail($item['to'],$item['subject'],$item['body']);
            if(!$ok){$item['attempts']=(int)$item['attempts']+1;if($item['attempts']<5){$item['next']=time()+min(86400,(2**$item['attempts'])*900);$remain[]=$item;}}
            $sent++;
        }
        update_option(self::QUEUE,$remain,false);
    }
    public static function preferences_shortcode() {
        if(!is_user_logged_in())return '<p>Sign in to manage email notifications.</p>';$uid=get_current_user_id();$p=get_user_meta($uid,'_bsw51_mail_preferences',true);if(!is_array($p))$p=array('watchlist'=>true,'talk'=>true);
        $out='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw51_mail_preferences">'.wp_nonce_field('bsw51_mail_preferences','_wpnonce',true,false);
        foreach(array('watchlist'=>'Watched article updates','talk'=>'Discussion replies') as $k=>$label)$out.='<p><label><input type="checkbox" name="prefs['.$k.']" value="1" '.checked(!empty($p[$k]),true,false).'> '.esc_html($label).'</label></p>';
        return $out.'<button type="submit">Save email preferences</button></form>';
    }
    public static function mail_preferences() {
        if(!is_user_logged_in())wp_die('Sign in required.');check_admin_referer('bsw51_mail_preferences');$raw=$_POST['prefs']??array();$p=array('watchlist'=>!empty($raw['watchlist']),'talk'=>!empty($raw['talk']));
        update_user_meta(get_current_user_id(),'_bsw51_mail_preferences',$p);wp_safe_redirect(wp_get_referer()?:home_url('/'));exit;
    }

    /* Search/disambiguation: exact title first, then relevance; never auto-redirect ambiguous matches. */
    public static function search_shortcode($atts) {
        $atts=shortcode_atts(array('placeholder'=>'Search the encyclopedia','per_page'=>10),$atts,'bsw51_search');
        $term=isset($_GET['bswq'])?sanitize_text_field(wp_unslash($_GET['bswq'])):'';
        $out='<form method="get" class="bsw51-search"><label class="screen-reader-text" for="bsw51-q">Search articles</label><input id="bsw51-q" name="bswq" value="'.esc_attr($term).'" placeholder="'.esc_attr($atts['placeholder']).'">';
        if(taxonomy_exists('wiki_namespace')){$out.='<label>Namespace <select name="bsw_namespace"><option value="">All namespaces</option>';$namespace_terms=get_terms(array('taxonomy'=>'wiki_namespace','hide_empty'=>false));if(!is_wp_error($namespace_terms))foreach($namespace_terms as $term_obj){$selected=selected($_GET['bsw_namespace']??'',$term_obj->slug,false);$out.='<option value="'.esc_attr($term_obj->slug).'" '.$selected.'>'.esc_html($term_obj->name).'</option>';}$out.='</select></label>';}
        $out.='<label>Language <input name="bsw_language" value="'.esc_attr($_GET['bsw_language']??'').'" placeholder="en"></label><button type="submit">Search</button></form>';
        if($term==='')return $out;
        $args=array('post_type'=>'wiki_article','post_status'=>'publish','s'=>$term,'posts_per_page'=>min(50,max(1,absint($atts['per_page']))),'orderby'=>'relevance');
        $ns=isset($_GET['bsw_namespace'])?sanitize_title(wp_unslash($_GET['bsw_namespace'])):'';if($ns)$args['tax_query']=array(array('taxonomy'=>'wiki_namespace','field'=>'slug','terms'=>$ns));
        $lang=isset($_GET['bsw_language'])?sanitize_key(wp_unslash($_GET['bsw_language'])):'';if($lang)$args['meta_query']=array(array('key'=>'_bsw_language_code','value'=>$lang));
        $q=new WP_Query($args);
        $exact=get_page_by_title($term,OBJECT,'wiki_article');
        if($exact&&$exact->post_status==='publish')$out.='<p class="bsw51-exact-match"><a href="'.esc_url(get_permalink($exact)).'">Exact title match: '.esc_html(get_the_title($exact)).'</a></p>';
        if($q->have_posts()){$out.='<h2>Search results</h2><ul>';while($q->have_posts()){$q->the_post();$out.='<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a><p>'.esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt()),28)).'</p></li>';}$out.='</ul>';wp_reset_postdata();}
        else $out.='<p>No matching articles found.</p>';
        return $out;
    }

    /* Side-by-side human translation editor and reusable source-segment memory. */
    public static function translation_editor_shortcode() {
        if(!is_singular('wiki_article')||!is_user_logged_in()||!current_user_can('edit_posts'))return '';
        $source=get_queried_object_id();$translations=get_post_meta($source,'_bsw_p4_translations',true);$target=isset($_GET['bsw_translation'])?absint($_GET['bsw_translation']):0;
        if(!$target&&is_array($translations))foreach($translations as $tid)if(get_post_status($tid)==='draft'){$target=(int)$tid;break;}
        if(!$target||get_post_type($target)!=='wiki_article')return '<p>Select or create a translation draft using the translation workspace.</p>';
        $src=get_post($source);$dst=get_post($target);if(!current_user_can('edit_post',$target))return '';
        $memory=get_option(self::MEMORY,array());$target_lang=get_post_meta($target,'_bsw_language_code',true)?:'und';$src_segments=preg_split('/(?<=[.!?])\s+|\n+/',wp_strip_all_tags($src->post_content));$dst_segments=preg_split('/(?<=[.!?])\s+|\n+/',wp_strip_all_tags($dst->post_content));
        $out='<section class="bsw51-translation-editor"><h2>Side-by-side translation editor</h2><p>Source: '.esc_html(get_the_title($source)).' · Target: '.esc_html(get_the_title($target)).'</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw51_translation_save"><input type="hidden" name="source_id" value="'.$source.'"><input type="hidden" name="target_id" value="'.$target.'">'.wp_nonce_field('bsw51_translation_save_'.$target,'_wpnonce',true,false).'<table><thead><tr><th>Source segment</th><th>Translation</th></tr></thead><tbody>';
        foreach($src_segments as $i=>$segment){$segment=trim($segment);if($segment==='')continue;$key=md5(function_exists('mb_strtolower')?mb_strtolower($segment):strtolower($segment));$suggest=$memory[$key][$target_lang]??'';$translated=$dst_segments[$i]??$suggest;
            $out.='<tr><td>'.esc_html($segment).'</td><td><textarea name="segments[]">'.esc_textarea($translated).'</textarea></td></tr>';}
        return $out.'</tbody></table><label>Translated article title <input name="translated_title" value="'.esc_attr($dst->post_title).'"></label><button type="submit">Save translation draft</button></form></section>';
    }
    public static function translation_save() {
        $source=absint($_POST['source_id']??0);$target=absint($_POST['target_id']??0);
        if(!is_user_logged_in()||get_post_type($source)!=='wiki_article'||get_post_type($target)!=='wiki_article'||!current_user_can('edit_post',$target))wp_die('Not authorised.');
        check_admin_referer('bsw51_translation_save_'.$target);$src=get_post($source);$parts=preg_split('/(?<=[.!?])\s+|\n+/',wp_strip_all_tags($src->post_content));$values=array_map('sanitize_textarea_field',wp_unslash($_POST['segments']??array()));$render=array();$memory=get_option(self::MEMORY,array());$j=0;
        foreach($parts as $segment){$segment=trim($segment);if($segment==='')continue;$value=trim($values[$j]??'');$render[]=$value;$key=md5(function_exists('mb_strtolower')?mb_strtolower($segment):strtolower($segment));if($value!=='')$memory[$key][sanitize_text_field(get_post_meta($target,'_bsw_language_code',true)?:'und')]=$value;$j++;}
        update_option(self::MEMORY,$memory,false);wp_update_post(array('ID'=>$target,'post_title'=>sanitize_text_field(wp_unslash($_POST['translated_title']??'')),'post_content'=>implode("\n\n",$render),'post_status'=>'draft'));
        self::audit('translation_draft_saved',$target,array('source'=>$source));wp_safe_redirect(get_edit_post_link($target,'url'));exit;
    }

    /* Scoped bot API: separate tokens from legacy export tokens. */
    public static function bot_token_action() {
        if(!current_user_can('manage_options'))wp_die('Not authorised.');check_admin_referer('bsw51_bot_token');$plain='bsw51_'.wp_generate_password(56,false,false);
        $scopes=array_map('sanitize_key',(array)($_POST['scopes']??array('articles:read')));$allowed=array('articles:read','articles:create','articles:update');$scopes=array_values(array_intersect($scopes,$allowed));if(!$scopes)$scopes=array('articles:read');
        $rows=get_option(self::TOKENS,array());if(!is_array($rows))$rows=array();$rows[]=array('id'=>wp_generate_uuid4(),'hash'=>hash('sha256',$plain),'label'=>sanitize_text_field(wp_unslash($_POST['label']??'API bot')),'scopes'=>$scopes,'created'=>current_time('mysql'),'created_by'=>get_current_user_id(),'revoked'=>0,'count'=>0,'window'=>time());
        update_option(self::TOKENS,$rows,false);set_transient('bsw51_token_'.get_current_user_id(),$plain,120);wp_safe_redirect(admin_url('options-general.php?page=bsw51-advanced&token=1'));exit;
    }
    public static function bot_auth_for_phase($request,$scope) { return self::bot_auth($request,$scope); }
    public static function bot_context_for_phase() { return self::$bot_context; }
    private static function bot_auth($request,$scope) {
        $header=$request->get_header('authorization');if(!$header||stripos($header,'Bearer ')!==0)return new WP_Error('bsw51_auth','Bearer token required.',array('status'=>401));
        $hash=hash('sha256',trim(substr($header,7)));$rows=get_option(self::TOKENS,array());if(!is_array($rows))return new WP_Error('bsw51_auth','Invalid token.',array('status'=>401));
        foreach($rows as &$row){if(empty($row['hash'])||!hash_equals($row['hash'],$hash)||!empty($row['revoked']))continue;
            if(!in_array($scope,(array)$row['scopes'],true))return new WP_Error('bsw51_scope','Token lacks required scope.',array('status'=>403));
            if(time()-(int)$row['window']>HOUR_IN_SECONDS){$row['window']=time();$row['count']=0;}
            if((int)$row['count']>=600)return new WP_Error('bsw51_rate','Bot rate limit exceeded.',array('status'=>429));
            $row['count']++;$copy=$row;unset($row);update_option(self::TOKENS,$rows,false);self::$bot_context=$copy;return $copy;
        }
        unset($row);return new WP_Error('bsw51_auth','Invalid or revoked token.',array('status'=>401));
    }
    public static function register_api() {
        register_rest_route('bigshor/v1','/bot/articles',array('methods'=>'GET','callback'=>array(__CLASS__,'api_list'),'permission_callback'=>function($r){return self::bot_auth($r,'articles:read');}));
        register_rest_route('bigshor/v1','/bot/articles',array('methods'=>'POST','callback'=>array(__CLASS__,'api_create'),'permission_callback'=>function($r){return self::bot_auth($r,'articles:create');}));
        register_rest_route('bigshor/v1','/bot/articles/(?P<id>\d+)',array('methods'=>'PATCH','callback'=>array(__CLASS__,'api_update'),'permission_callback'=>function($r){return self::bot_auth($r,'articles:update');}));
    }
    public static function api_list($r) {
        $q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>min(100,max(1,absint($r['per_page']?:25))),'paged'=>max(1,absint($r['page']?:1))));
        return rest_ensure_response(array('total'=>(int)$q->found_posts,'articles'=>array_map(function($p){return array('id'=>$p->ID,'title'=>$p->post_title,'url'=>get_permalink($p),'modified'=>$p->post_modified_gmt);},$q->posts)));
    }
    public static function api_create($r) {
        $title=sanitize_text_field($r->get_param('title'));$content=wp_kses_post($r->get_param('content'));if(!$title)return new WP_Error('bsw51_title','Title required.',array('status'=>400));
        $id=wp_insert_post(array('post_type'=>'wiki_article','post_status'=>'draft','post_title'=>$title,'post_content'=>$content,'post_author'=>absint(self::$bot_context['created_by']??0)),true);
        if(is_wp_error($id))return $id;return new WP_REST_Response(array('id'=>$id,'status'=>'draft','edit_url'=>get_edit_post_link($id,'url')),201);
    }
    public static function api_update($r) {
        $id=absint($r['id']);if(get_post_type($id)!=='wiki_article')return new WP_Error('bsw51_missing','Article not found.',array('status'=>404));
        if(get_post_status($id)==='publish')return new WP_Error('bsw51_published','Bots may update drafts only; published articles require the editorial workflow.',array('status'=>409));
        $owner=absint(self::$bot_context['created_by']??0);if($owner && (int)get_post_field('post_author',$id)!==$owner && !user_can($owner,'edit_others_posts'))return new WP_Error('bsw51_owner','This bot cannot update another author’s draft.',array('status'=>403));
        $data=array('ID'=>$id);if($r->has_param('title'))$data['post_title']=sanitize_text_field($r['title']);if($r->has_param('content'))$data['post_content']=wp_kses_post($r['content']);$data['post_status']='draft';
        $saved=wp_update_post($data,true);if(is_wp_error($saved))return $saved;return rest_ensure_response(array('id'=>$id,'status'=>'draft'));
    }

    /* Media snapshots retain prior binaries before edits; restoration is capability/nonce protected. */
    public static function snapshot_before_file_update($new_file,$id) {
        $old_file=get_attached_file($id);
        if(!$old_file||!is_file($old_file)||!is_readable($old_file))return $new_file;
        $old_hash=hash_file('sha256',$old_file);$known=get_post_meta($id,'_bsw50_sha256',true);
        if(!$old_hash||!$known||!hash_equals((string)$known,(string)$old_hash))return $new_file;
        $uploads=wp_upload_dir();$dir=trailingslashit($uploads['basedir']).'bigshor-media-history/'.$id;
        if(!wp_mkdir_p($dir))return $new_file;$ext=pathinfo($old_file,PATHINFO_EXTENSION);$dest=trailingslashit($dir).$old_hash.($ext?'.'.$ext:'');
        if(!file_exists($dest)&&@copy($old_file,$dest)){
            $history=get_post_meta($id,'_bsw51_binary_history',true);if(!is_array($history))$history=array();
            $history[]=array('hash'=>$old_hash,'file'=>str_replace(trailingslashit($uploads['basedir']),'',$dest),'date'=>current_time('mysql'));
            update_post_meta($id,'_bsw51_binary_history',array_slice($history,-20));
        }
        return $new_file;
    }

    public static function snapshot_media($id) {
        if(!current_user_can('upload_files'))return;$path=get_attached_file($id);if(!$path||!is_file($path)||!is_readable($path))return;
        $hash=hash_file('sha256',$path);$old=get_post_meta($id,'_bsw50_sha256',true);if(!$old||hash_equals($old,$hash))return;
        $uploads=wp_upload_dir();$dir=trailingslashit($uploads['basedir']).'bigshor-media-history/'.$id;
        if(!wp_mkdir_p($dir))return;$ext=pathinfo($path,PATHINFO_EXTENSION);$dest=trailingslashit($dir).$old.($ext?'.'.$ext:'');
        if(!file_exists($dest)&&@copy($path,$dest)){$history=get_post_meta($id,'_bsw51_binary_history',true);if(!is_array($history))$history=array();$history[]=array('hash'=>$old,'file'=>str_replace(trailingslashit($uploads['basedir']),'',$dest),'date'=>current_time('mysql'));update_post_meta($id,'_bsw51_binary_history',array_slice($history,-20));}
    }
    public static function media_versions_shortcode($atts) {
        $atts=shortcode_atts(array('id'=>0),$atts,'bsw51_media_versions');$id=absint($atts['id']);
        if(!$id||get_post_type($id)!=='attachment'||!current_user_can('upload_files'))return '';
        $rows=get_post_meta($id,'_bsw51_binary_history',true);if(!is_array($rows)||!$rows)return '<p>No binary versions are archived for this file.</p>';
        $out='<section class="bsw51-media-versions"><h3>Archived file versions</h3><ul>';
        foreach(array_reverse($rows) as $row){$out.='<li>'.esc_html($row['date']??'').' · SHA-256 <code>'.esc_html($row['hash']??'').'</code>';
            $out.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw51_media_restore"><input type="hidden" name="attachment_id" value="'.$id.'"><input type="hidden" name="hash" value="'.esc_attr($row['hash']??'').'">'.wp_nonce_field('bsw51_media_restore_'.$id,'_wpnonce',true,false).'<button type="submit">Restore this version</button></form></li>';}
        return $out.'</ul></section>';
    }

    public static function media_restore() {
        $id=absint($_POST['attachment_id']??0);if(!current_user_can('upload_files')||!current_user_can('edit_post',$id))wp_die('Not authorised.');check_admin_referer('bsw51_media_restore_'.$id);
        $hash=sanitize_text_field($_POST['hash']??'');$history=get_post_meta($id,'_bsw51_binary_history',true);$uploads=wp_upload_dir();$found=null;
        foreach((array)$history as $row)if(!empty($row['hash'])&&hash_equals($row['hash'],$hash))$found=$row;
        if(!$found)wp_die('Version not found.');$source=trailingslashit($uploads['basedir']).ltrim($found['file'],'/');$dest=get_attached_file($id);
        if(!is_file($source)||!$dest||!@copy($source,$dest))wp_die('Could not restore media binary.');
        update_post_meta($id,'_bsw50_sha256',hash_file('sha256',$dest));self::audit('media_binary_restored',$id,array('hash'=>$hash));wp_safe_redirect(get_edit_post_link($id,'url'));exit;
    }
    public static function media_reindex() {
        if(!current_user_can('manage_options'))wp_die('Not authorised.');check_admin_referer('bsw51_media_reindex');
        $page=max(1,absint($_POST['page']??1));$ids=get_posts(array('post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>100,'paged'=>$page,'fields'=>'ids','orderby'=>'ID','order'=>'ASC'));
        foreach($ids as $id){if(class_exists('BSW_Phase50_Major_Platform'))BSW_Phase50_Major_Platform::index_media_integrity($id);else{$path=get_attached_file($id);if($path&&is_file($path))update_post_meta($id,'_bsw50_sha256',hash_file('sha256',$path));}}
        $next=count($ids)===100?$page+1:0;
        if($next){wp_safe_redirect(add_query_arg(array('page'=>'bsw51-advanced','reindex'=>$next),admin_url('options-general.php')));exit;}
        self::audit('legacy_media_reindex_complete',0,array('last_page'=>$page));wp_safe_redirect(admin_url('options-general.php?page=bsw51-advanced&reindexed=1'));exit;
    }

    public static function admin_menu() { add_options_page('BIGSHOR Advanced Platform','BIGSHOR Advanced Platform','manage_options','bsw51-advanced',array(__CLASS__,'admin_page')); }
    public static function admin_page() {
        if(!current_user_can('manage_options'))return;
        echo '<div class="wrap"><h1>BIGSHOR Advanced Platform — Phase 51</h1><p>Governance, CIDR moderation, notification delivery, bot API scopes and media maintenance.</p>';
        if(isset($_GET['token'])){$t=get_transient('bsw51_token_'.get_current_user_id());if($t){echo '<div class="notice notice-success"><p>Copy this API token now: <code>'.esc_html($t).'</code></p></div>';delete_transient('bsw51_token_'.get_current_user_id());}}
        echo '<h2>Scoped bot token</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw51_bot_token">'.wp_nonce_field('bsw51_bot_token','_wpnonce',true,false).'<input name="label" placeholder="Bot label" required><p><label><input type="checkbox" name="scopes[]" value="articles:read" checked> Read</label> <label><input type="checkbox" name="scopes[]" value="articles:create"> Create drafts</label> <label><input type="checkbox" name="scopes[]" value="articles:update"> Update drafts</label></p><button class="button button-primary">Create scoped token</button></form>';
        echo '<h2>CIDR/IP restrictions</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw51_ip_block">'.wp_nonce_field('bsw51_ip_block','_wpnonce',true,false).'<input name="network" placeholder="192.0.2.0/24 or IPv6" required><input type="number" name="days" min="0" max="3650" placeholder="Expiry days (0 = never)"><input name="reason" placeholder="Reason"><select name="mode"><option value="block">Block</option><option value="unblock">Unblock</option></select><button class="button">Apply</button></form>';
        echo '<h2>Legacy media reindex</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="bsw51_media_reindex">'.wp_nonce_field('bsw51_media_reindex','_wpnonce',true,false).'<input type="hidden" name="page" value="'.(isset($_GET['reindex'])?absint($_GET['reindex']):1).'"><button class="button">'.(isset($_GET['reindex'])?'Continue reindex':'Start reindex (100 files per batch)').'</button></form>';
        echo '<h2>Mail queue</h2><p>Pending messages: '.count((array)get_option(self::QUEUE,array())).'. Delivery runs hourly with retry/backoff. Add <code>[bsw51_notification_preferences]</code> to a private account page.</p>';
        echo '<h2>Revision oversight</h2><p>Revision records remain stored. Oversight actions can suppress or reveal a revision without deleting it; add the controls below to an admin-only workflow.</p>';
        echo '<h2>Appeals</h2><p>Add <code>[bsw51_appeal]</code> to an access-help page. Review submissions under the Appeals post type.</p>';
        echo '</div>';
    }
}
add_action('init', function(){
    if(!post_type_exists('bsw_appeal'))register_post_type('bsw_appeal',array('labels'=>array('name'=>'Access Appeals','singular_name'=>'Access Appeal'),'public'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=wiki_article','supports'=>array('title','editor','author'),'capability_type'=>'post','map_meta_cap'=>true));
},26);
BSW_Phase51_Advanced_Platform::boot();
}
