<?php
/**
 * Phase 66: BIGSHOR WIKI granular roles, permissions, role administration and audit trail.
 * Additive only. Does not remove or downgrade existing WordPress roles.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase66_User_Governance')):
final class BSW_Phase66_User_Governance {
    const VERSION = '1.0.0';
    const OPTION = 'bsw_phase66_custom_roles';
    const AUDIT = 'bsw_phase66_role_audit';
    const NONCE = 'bsw_phase66_manage_roles';

    public static function definitions() {
        $edit = array('read'=>true,'edit_posts'=>true,'edit_published_posts'=>true,'upload_files'=>true,'edit_wiki_articles'=>true);
        $review = array_merge($edit, array('edit_others_posts'=>true,'publish_posts'=>true,'read_private_posts'=>true,'bsw_review_edits'=>true,'bsw_review_articles'=>true,'bsw_view_editorial_queue'=>true));
        $moderate = array_merge($review, array('bsw_moderate_discussions'=>true,'bsw_handle_reports'=>true,'bsw_revert_edits'=>true,'bsw_protect_articles'=>true));
        return array(
            'wiki_bureaucrat'=>array('BIGSHOR Bureaucrat','Assign and revoke wiki roles; manage community permissions.',array('read'=>true,'bsw_manage_roles'=>true,'bsw_manage_permissions'=>true,'bsw_view_role_audit'=>true)),
            'wiki_interface_admin'=>array('Interface Administrator','Manage approved wiki interface assets and appearance settings.',array('read'=>true,'bsw_manage_interface'=>true)),
            'wiki_oversighter'=>array('Oversighter','Request and execute approved suppression actions.',array('read'=>true,'bsw_suppress_revisions'=>true,'bsw_view_suppressed_content'=>true,'bsw_view_role_audit'=>true)),
            'wiki_suppression_admin'=>array('Suppression Administrator','Manage suppression workflow and audit trail.',array('read'=>true,'bsw_suppress_revisions'=>true,'bsw_unsuppress_revisions'=>true,'bsw_view_suppressed_content'=>true,'bsw_view_role_audit'=>true)),
            'wiki_checkuser'=>array('CheckUser','Access privacy-restricted abuse investigation tools.',array('read'=>true,'bsw_checkuser'=>true,'bsw_view_security_audit'=>true)),
            'wiki_administrator'=>array('Wiki Administrator','Manage wiki operations, users, articles and editorial settings without WordPress plugin/theme administration.',array('read'=>true,'edit_posts'=>true,'edit_others_posts'=>true,'publish_posts'=>true,'delete_posts'=>true,'delete_others_posts'=>true,'delete_published_posts'=>true,'read_private_posts'=>true,'upload_files'=>true,'bsw_manage_wiki'=>true,'bsw_manage_users'=>true,'bsw_manage_roles'=>true,'bsw_manage_permissions'=>true,'bsw_view_role_audit'=>true,'bsw_review_edits'=>true,'bsw_review_articles'=>true,'bsw_view_editorial_queue'=>true,'bsw_moderate_discussions'=>true,'bsw_handle_reports'=>true,'bsw_revert_edits'=>true,'bsw_protect_articles'=>true,'bsw_manage_deletions'=>true,'bsw_manage_media_rights'=>true)),
            'wiki_senior_editor'=>array('Senior Editor','Lead editorial review and guide other editors.',$moderate),
            'wiki_moderator'=>array('Moderator','Moderate reports, vandalism and community conduct.',$moderate),
            'wiki_reviewer'=>array('Reviewer','Review and approve submitted article edits.',$review),
            'wiki_content_reviewer'=>array('Content Reviewer','Check sources, neutrality, citations and copyright.',$review),
            'wiki_editor'=>array('Editor','Edit and improve published articles.',$edit),
            'wiki_article_creator'=>array('Article Creator','Create and submit articles for review.',array('read'=>true,'edit_posts'=>true,'edit_wiki_articles'=>true)),
            'wiki_translator'=>array('Translator','Create and update translations.',array('read'=>true,'edit_posts'=>true,'edit_wiki_articles'=>true,'bsw_manage_translations'=>true)),
            'wiki_importer'=>array('Importer','Run approved content import workflows.',array('read'=>true,'bsw_import_content'=>true)),
            'wiki_discussion_moderator'=>array('Discussion Moderator','Moderate article and user discussions.',array('read'=>true,'bsw_moderate_discussions'=>true,'bsw_handle_reports'=>true)),
            'wiki_bot'=>array('Bot','Run approved automated API tasks.',array('read'=>true,'edit_posts'=>true,'bsw_use_bot_api'=>true)),
            'wiki_autoconfirmed'=>array('Autoconfirmed User','Additional editing privileges granted after account eligibility checks.',array('read'=>true,'edit_posts'=>true,'edit_wiki_articles'=>true,'bsw_autoconfirmed'=>true)),
        );
    }
    public static function caps_catalog() {
        return array(
            'bsw_manage_wiki'=>'Manage wiki operations','bsw_manage_users'=>'Manage wiki users','bsw_manage_roles'=>'Assign and revoke roles','bsw_manage_permissions'=>'Manage custom permissions','bsw_view_role_audit'=>'View role assignment audit','bsw_review_edits'=>'Review submitted edits','bsw_review_articles'=>'Approve article submissions','bsw_view_editorial_queue'=>'View editorial queue','bsw_moderate_discussions'=>'Moderate discussions','bsw_handle_reports'=>'Handle abuse reports','bsw_revert_edits'=>'Revert edits','bsw_protect_articles'=>'Protect articles','bsw_manage_deletions'=>'Manage deletion workflows','bsw_manage_media_rights'=>'Manage media rights','bsw_manage_interface'=>'Manage wiki interface','bsw_suppress_revisions'=>'Suppress revisions','bsw_unsuppress_revisions'=>'Lift revision suppression','bsw_view_suppressed_content'=>'View suppressed content','bsw_checkuser'=>'Use privacy-restricted CheckUser tools','bsw_view_security_audit'=>'View security audit','bsw_manage_translations'=>'Manage translations','bsw_import_content'=>'Import approved content','bsw_use_bot_api'=>'Use bot API','bsw_autoconfirmed'=>'Autoconfirmed editing privileges'
        );
    }
    public static function init() {
        add_action('init', array(__CLASS__,'expire_temporary_roles'), 1);
        add_action('admin_menu', array(__CLASS__,'menu'));
        add_action('admin_post_bsw66_assign_role', array(__CLASS__,'assign_role'));
        add_action('admin_post_bsw66_create_role', array(__CLASS__,'create_role'));
        add_action('admin_post_bsw66_delete_role', array(__CLASS__,'delete_role'));
        add_action('admin_post_bsw66_remove_role', array(__CLASS__,'remove_role'));
        add_action('rest_api_init', array(__CLASS__,'register_rest'));
        add_filter('user_has_cap', array(__CLASS__,'enforce_expiry'), 10, 4);
    }
    public static function activate() {
        foreach (self::definitions() as $slug=>$def) {
            $role = get_role($slug);
            if (!$role) $role = add_role($slug, $def[0], $def[2]);
            if ($role) foreach ($def[2] as $cap=>$grant) { if ($grant) $role->add_cap($cap); }
        }
        $admin = get_role('administrator');
        if ($admin) foreach (array_keys(self::caps_catalog()) as $cap) $admin->add_cap($cap);
        update_option('bsw_phase66_roles_version', self::VERSION, false);
    }
    public static function menu() {
        add_users_page('BIGSHOR Wiki Roles','Wiki Roles','manage_options','bsw-wiki-roles',array(__CLASS__,'render_page'));
    }
    private static function log_event($event, $user_id, $role, $details=array()) {
        $rows = get_option(self::AUDIT, array()); if (!is_array($rows)) $rows=array();
        array_unshift($rows,array('time'=>current_time('mysql',true),'actor'=>get_current_user_id(),'user'=>absint($user_id),'event'=>sanitize_key($event),'role'=>sanitize_key($role),'details'=>sanitize_text_field(wp_json_encode($details)),'ip_hash'=>isset($_SERVER['REMOTE_ADDR'])?hash_hmac('sha256',sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])),wp_salt('auth')):''));
        update_option(self::AUDIT,array_slice($rows,0,500),false);
    }
    private static function valid_role($role) { return is_string($role) && preg_match('/^[a-z0-9_]{3,50}$/',$role) && get_role($role); }
    public static function assign_role() {
        if (!current_user_can('manage_options') && !current_user_can('bsw_manage_roles')) wp_die('You are not allowed to manage wiki roles.',403);
        check_admin_referer(self::NONCE);
        $uid=isset($_POST['user_id'])?absint($_POST['user_id']):0; $role=isset($_POST['role'])?sanitize_key(wp_unslash($_POST['role'])):'';
        $days=isset($_POST['duration_days'])?absint($_POST['duration_days']):0; $user=get_user_by('id',$uid);
        if (!$user || !$role || !get_role($role) || in_array($role,array('administrator','super_admin'),true) || $uid===get_current_user_id()) wp_die('Invalid or unsafe role assignment.',400);
        if (!current_user_can('manage_options') && (strpos($role,'wiki_custom_')===0 || in_array($role,array('wiki_administrator','wiki_bureaucrat','wiki_oversighter','wiki_suppression_admin','wiki_checkuser','wiki_interface_admin'),true))) wp_die('Only a site administrator can assign privileged or custom roles.',403);
        $user->add_role($role);
        $exp=$days?time()+min($days,365)*DAY_IN_SECONDS:0;
        $meta=get_user_meta($uid,'_bsw_phase66_role_expiries',true); if(!is_array($meta))$meta=array();
        if($exp)$meta[$role]=$exp;else unset($meta[$role]); update_user_meta($uid,'_bsw_phase66_role_expiries',$meta);
        self::log_event('assign',$uid,$role,array('expires'=>$exp?gmdate('c',$exp):'never'));
        wp_safe_redirect(add_query_arg(array('page'=>'bsw-wiki-roles','bsw_notice'=>'assigned'),admin_url('users.php'))); exit;
    }
    public static function remove_role() {
        if (!current_user_can('manage_options') && !current_user_can('bsw_manage_roles')) wp_die('Permission denied.',403);
        check_admin_referer(self::NONCE); $uid=isset($_POST['user_id'])?absint($_POST['user_id']):0; $role=isset($_POST['role'])?sanitize_key(wp_unslash($_POST['role'])):'';
        $u=get_user_by('id',$uid); if(!$u || !$role || in_array($role,array('administrator','super_admin'),true))wp_die('Invalid role removal.',400);
        if($uid===get_current_user_id())wp_die('You cannot remove your own role.',403);
        $u->remove_role($role); $m=get_user_meta($uid,'_bsw_phase66_role_expiries',true); if(is_array($m)){unset($m[$role]);update_user_meta($uid,'_bsw_phase66_role_expiries',$m);} self::log_event('remove',$uid,$role);
        wp_safe_redirect(add_query_arg(array('page'=>'bsw-wiki-roles','bsw_notice'=>'removed'),admin_url('users.php'))); exit;
    }
    public static function create_role() {
        if(!current_user_can('manage_options') && !current_user_can('bsw_manage_permissions'))wp_die('Permission denied.',403);
        check_admin_referer(self::NONCE); $slug=isset($_POST['role_slug'])?sanitize_key(wp_unslash($_POST['role_slug'])):''; $label=isset($_POST['role_label'])?sanitize_text_field(wp_unslash($_POST['role_label'])):'';
        if(!$slug || !preg_match('/^wiki_custom_[a-z0-9_]{1,35}$/',$slug) || !$label || get_role($slug))wp_die('Use a unique wiki_custom_ role slug and a role name.',400);
        $allowed=array_keys(self::caps_catalog()); if(!current_user_can('manage_options'))$allowed=array_values(array_diff($allowed,array('bsw_manage_wiki','bsw_manage_users','bsw_manage_roles','bsw_manage_permissions','bsw_view_role_audit','bsw_suppress_revisions','bsw_unsuppress_revisions','bsw_view_suppressed_content','bsw_checkuser','bsw_view_security_audit','bsw_manage_interface'))); $selected=isset($_POST['caps'])?(array)wp_unslash($_POST['caps']):array(); $caps=array('read'=>true);
        foreach($selected as $cap){$cap=sanitize_key($cap);if(in_array($cap,$allowed,true))$caps[$cap]=true;}
        add_role($slug,$label,$caps); self::log_event('create_role',get_current_user_id(),$slug,array('label'=>$label,'caps'=>array_keys($caps)));
        wp_safe_redirect(add_query_arg(array('page'=>'bsw-wiki-roles','bsw_notice'=>'created'),admin_url('users.php')));exit;
    }
    public static function delete_role() {
        if(!current_user_can('manage_options'))wp_die('Only site administrators can delete custom roles.',403);
        check_admin_referer(self::NONCE);$slug=isset($_POST['role'])?sanitize_key(wp_unslash($_POST['role'])):'';
        if(strpos($slug,'wiki_custom_')!==0 || !get_role($slug))wp_die('Only custom wiki roles can be deleted.',400);
        global $wpdb; $users=get_users(array('role'=>$slug,'fields'=>'ID','number'=>-1)); foreach($users as $uid){$u=get_user_by('id',$uid);if($u)$u->remove_role($slug);}
        remove_role($slug);self::log_event('delete_role',get_current_user_id(),$slug,array('affected_users'=>count($users)));
        wp_safe_redirect(add_query_arg(array('page'=>'bsw-wiki-roles','bsw_notice'=>'deleted'),admin_url('users.php')));exit;
    }
    public static function expire_temporary_roles() {
        if(!is_admin() && !wp_doing_cron())return;
        $users=get_users(array('meta_key'=>'_bsw_phase66_role_expiries','number'=>200,'fields'=>'all'));
        foreach($users as $u){$meta=get_user_meta($u->ID,'_bsw_phase66_role_expiries',true);if(!is_array($meta))continue;$changed=false;foreach($meta as $role=>$expiry){if($expiry && (int)$expiry<=time()){$u->remove_role($role);unset($meta[$role]);self::log_event('expire',$u->ID,$role);$changed=true;}}if($changed)update_user_meta($u->ID,'_bsw_phase66_role_expiries',$meta);}
    }
    public static function enforce_expiry($allcaps,$caps,$args,$user) {
        if(!$user || empty($user->ID))return $allcaps;$meta=get_user_meta($user->ID,'_bsw_phase66_role_expiries',true);if(!is_array($meta))return $allcaps;
        foreach($meta as $role=>$expiry){if($expiry && (int)$expiry<=time() && in_array($role,(array)$user->roles,true)){ $def=get_role($role);if($def)foreach($def->capabilities as $cap=>$grant){$provided_elsewhere=false;foreach((array)$user->roles as $other_role){if($other_role===$role)continue;$other=get_role($other_role);if($other && !empty($other->capabilities[$cap])){$other_exp=isset($meta[$other_role])?(int)$meta[$other_role]:0;if(!$other_exp || $other_exp>time()){$provided_elsewhere=true;break;}}}if(!$provided_elsewhere)unset($allcaps[$cap]);} }}return $allcaps;
    }
    public static function register_rest() {
        register_rest_route('bigshor-wiki/v1','/roles',array('methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_options')||current_user_can('bsw_manage_roles');},'callback'=>function(){ $out=array();foreach(wp_roles()->roles as $slug=>$r){if(strpos($slug,'wiki_')===0)$out[]=array('slug'=>$slug,'name'=>translate_user_role($r['name']),'capabilities'=>array_keys(array_filter($r['capabilities'])));}return rest_ensure_response($out);}));
    }
    public static function render_page() {
        if(!current_user_can('manage_options') && !current_user_can('bsw_manage_roles'))wp_die('Permission denied.',403);
        $defs=self::definitions();$roles=wp_roles()->roles;$users=get_users(array('number'=>100,'orderby'=>'display_name','order'=>'ASC'));$nonce=wp_nonce_field(self::NONCE,'_wpnonce',true,false);
        echo '<div class="wrap"><h1>BIGSHOR WIKI — Roles &amp; Permissions</h1><p>Assign multiple wiki roles, set temporary access, create custom roles and review the role audit trail. WordPress administrator and super-admin access are not modified or removable here.</p>';
        if(isset($_GET['bsw_notice']))echo '<div class="notice notice-success"><p>Role management action completed.</p></div>';
        echo '<h2>Assign a role</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.$nonce.'<input type="hidden" name="action" value="bsw66_assign_role"><table class="form-table"><tr><th><label for="bsw66_user">User</label></th><td><select required name="user_id" id="bsw66_user"><option value="">Select user</option>';foreach($users as $u)echo '<option value="'.(int)$u->ID.'">'.esc_html($u->display_name.' — '.$u->user_email).'</option>';echo '</select></td></tr><tr><th><label for="bsw66_role">Role</label></th><td><select required name="role" id="bsw66_role"><option value="">Select role</option>';foreach($roles as $slug=>$r)if(strpos($slug,'wiki_')===0)echo '<option value="'.esc_attr($slug).'">'.esc_html(translate_user_role($r['name'])).'</option>';echo '</select></td></tr><tr><th><label for="bsw66_days">Duration</label></th><td><input type="number" min="0" max="365" name="duration_days" id="bsw66_days" value="0"> <span class="description">0 = permanent; 1–365 = temporary</span></td></tr></table><p><button class="button button-primary">Assign role</button></p></form>';
        echo '<h2>Current user role assignments</h2><table class="widefat striped"><thead><tr><th>User</th><th>Assigned wiki roles</th><th>Action</th></tr></thead><tbody>';foreach($users as $u){$wiki_roles=array_values(array_filter((array)$u->roles,function($r){return strpos($r,'wiki_')===0;}));if(!$wiki_roles)continue;echo '<tr><td>'.esc_html($u->display_name.' ('.$u->user_email.')').'</td><td>'.esc_html(implode(', ',$wiki_roles)).'</td><td>';foreach($wiki_roles as $assigned){echo '<form style="display:inline-block;margin:0 6px 4px 0" method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field(self::NONCE,'_wpnonce',true,false).'<input type="hidden" name="action" value="bsw66_remove_role"><input type="hidden" name="user_id" value="'.(int)$u->ID.'"><input type="hidden" name="role" value="'.esc_attr($assigned).'"><button class="button">Remove '.esc_html($assigned).'</button></form>'; }echo '</td></tr>';}echo '</tbody></table>';
        echo '<h2>Existing wiki roles</h2><table class="widefat striped"><thead><tr><th>Role</th><th>Slug</th><th>Permissions</th><th>Type</th><th>Action</th></tr></thead><tbody>';foreach($roles as $slug=>$r){if(strpos($slug,'wiki_')!==0)continue;$custom=strpos($slug,'wiki_custom_')===0;echo '<tr><td>'.esc_html(translate_user_role($r['name'])).'</td><td><code>'.esc_html($slug).'</code></td><td>'.esc_html(implode(', ',array_keys(array_filter($r['capabilities'])))).'</td><td>'.($custom?'Custom':'Built-in wiki').'</td><td>';if($custom){echo '<form style="display:inline" method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field(self::NONCE,'_wpnonce',true,false).'<input type="hidden" name="action" value="bsw66_delete_role"><input type="hidden" name="role" value="'.esc_attr($slug).'"><button class="button" onclick="return confirm(\'Delete this custom role and remove it from assigned users?\')">Delete role</button></form>';}else echo 'Protected';echo '</td></tr>';}echo '</tbody></table>';
        echo '<h2>Create a custom role</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field(self::NONCE,'_wpnonce',true,false).'<input type="hidden" name="action" value="bsw66_create_role"><p><label>Role name <input required name="role_label" maxlength="60"></label> &nbsp; <label>Slug <input required name="role_slug" pattern="wiki_custom_[a-z0-9_]{1,35}" placeholder="wiki_custom_fact_checker"></label></p><fieldset><legend>Grant permissions</legend>';foreach(self::caps_catalog() as $cap=>$label)echo '<label style="display:inline-block;min-width:260px;margin:6px 12px 6px 0"><input type="checkbox" name="caps[]" value="'.esc_attr($cap).'"> '.esc_html($label).'</label>';echo '</fieldset><p><button class="button button-primary">Create custom role</button></p></form>';
        if(current_user_can('manage_options')||current_user_can('bsw_view_role_audit')){$audit=get_option(self::AUDIT,array());echo '<h2>Role audit trail</h2><table class="widefat striped"><thead><tr><th>UTC time</th><th>Actor</th><th>User</th><th>Event</th><th>Role</th><th>Details</th></tr></thead><tbody>';foreach(array_slice((array)$audit,0,100) as $a){$actor=get_userdata((int)$a['actor']);$target=get_userdata((int)$a['user']);echo '<tr><td>'.esc_html($a['time']).'</td><td>'.esc_html($actor?$actor->user_login:'Deleted user').'</td><td>'.esc_html($target?$target->user_login:'Deleted user').'</td><td>'.esc_html($a['event']).'</td><td>'.esc_html($a['role']).'</td><td>'.esc_html($a['details']).'</td></tr>';}if(!$audit)echo '<tr><td colspan="6">No role changes recorded yet.</td></tr>';echo '</tbody></table>';}
        echo '<p class="description">Built-in wiki roles are additive. This module does not claim to implement revision hiding, CheckUser IP investigations or interface editing workflows by role alone; those sensitive tools must separately enforce the corresponding capabilities before use.</p></div>';
    }
}
BSW_Phase66_User_Governance::init();
register_activation_hook(dirname(__DIR__).'/bigshor-wiki-core.php',array('BSW_Phase66_User_Governance','activate'));
add_action('admin_init',function(){if(get_option('bsw_phase66_roles_version')!==BSW_Phase66_User_Governance::VERSION)BSW_Phase66_User_Governance::activate();});
endif;
