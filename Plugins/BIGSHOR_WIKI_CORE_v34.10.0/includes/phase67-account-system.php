<?php
/**
 * Phase 67: Public account portal inspired by Wikipedia's open-account model.
 * Uses WordPress authentication, password storage and reset mechanisms.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase67_Account_System')):
final class BSW_Phase67_Account_System {
    const VERSION = '1.1.0';
    const VERIFY_TTL = 2 * DAY_IN_SECONDS;
    public static function init() {
        add_shortcode('bigshor_account', array(__CLASS__, 'shortcode'));
        add_action('admin_post_nopriv_bsw67_register', array(__CLASS__, 'register'));
        add_action('admin_post_nopriv_bsw67_verify', array(__CLASS__, 'verify'));
        add_action('admin_post_bsw67_verify', array(__CLASS__, 'verify'));
        add_action('admin_post_nopriv_bsw67_login', array(__CLASS__, 'login'));
        add_action('admin_post_bsw67_login', array(__CLASS__, 'login'));
        add_action('admin_post_nopriv_bsw67_resend', array(__CLASS__, 'resend'));
        add_action('admin_post_nopriv_bsw67_lost_password', array(__CLASS__, 'lost_password'));
        add_action('admin_post_nopriv_bsw67_reset_password', array(__CLASS__, 'reset_password'));
        add_action('admin_post_bsw67_profile', array(__CLASS__, 'profile'));
        add_action('admin_post_bsw67_logout', array(__CLASS__, 'logout'));
        add_filter('authenticate', array(__CLASS__, 'require_verified_email'), 35, 3);
        add_action('init', array(__CLASS__, 'ensure_pages'), 20);
        add_action('wp_login', array(__CLASS__, 'mark_login'), 10, 2);
    }
    public static function activate() {
        self::ensure_pages();
        update_option('bsw_phase67_account_version', self::VERSION, false);
    }
    public static function ensure_pages() {
        if (get_option('bsw_phase67_pages_created') === self::VERSION) return;
        $pages = array(
            'register' => array('Register', 'Create a BIGSHOR WIKI account.'),
            'login' => array('Log in', 'Log in to BIGSHOR WIKI.'),
            'my-account' => array('My account', 'Your BIGSHOR WIKI account and profile.'),
            'account-verification' => array('Verify email', 'Verify your BIGSHOR WIKI email address.'),
            'reset-password' => array('Reset password', 'Recover access to your BIGSHOR WIKI account.')
        );
        foreach ($pages as $slug => $data) {
            $page = get_page_by_path($slug);
            if (!$page) wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>$data[0],'post_name'=>$slug,'post_content'=>'[bigshor_account]'));
            elseif (strpos((string)$page->post_content, '[bigshor_account]') === false && get_option('bsw_phase67_managed_page_'.$slug) === '1') wp_update_post(array('ID'=>$page->ID,'post_content'=>'[bigshor_account]'));
        }
        update_option('bsw_phase67_pages_created', self::VERSION, false);
    }
    private static function url($slug, $args=array()) { $p=get_page_by_path($slug); $base=$p?get_permalink($p):home_url('/'.$slug.'/'); return add_query_arg($args,$base); }
    private static function notice($message, $type='info') { return '<div class="bsw-account-notice bsw-account-'.$type.'" role="status">'.esc_html($message).'</div>'; }
    private static function form_start($action) { return '<form class="bsw-account-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="'.esc_attr($action).'">'.wp_nonce_field('bsw67_'.$action,'_wpnonce',true,false); }
    private static function field($name,$label,$type='text',$attrs='') { return '<p><label for="bsw67_'.esc_attr($name).'">'.esc_html($label).'</label><input id="bsw67_'.esc_attr($name).'" name="'.esc_attr($name).'" type="'.esc_attr($type).'" '.$attrs.' required></p>'; }
    public static function shortcode($atts=array()) {
        $atts=shortcode_atts(array('role'=>'wiki_article_creator'),$atts,'bigshor_account');
        $requested_role=sanitize_key($atts['role']);
        $role_defs=class_exists('BSW_Phase66_User_Governance')?BSW_Phase66_User_Governance::definitions():array();
        if(!isset($role_defs[$requested_role]))$requested_role='wiki_article_creator';
        $out='<div class="bsw-account-portal"><style>.bsw-account-portal{max-width:560px;margin:28px auto;padding:24px;border:1px solid #d9dee7;border-radius:12px;background:#fff;color:#182230}.bsw-account-portal h2{margin-top:0}.bsw-account-form p{margin:0 0 16px}.bsw-account-form label{display:block;font-weight:600;margin-bottom:6px}.bsw-account-form input[type=text],.bsw-account-form input[type=email],.bsw-account-form input[type=password]{box-sizing:border-box;width:100%;padding:12px;border:1px solid #aeb8c5;border-radius:6px}.bsw-account-form button{padding:11px 18px;border:0;border-radius:6px;background:#18345b;color:#fff;font-weight:600;cursor:pointer}.bsw-account-notice{padding:12px;margin:12px 0;border-radius:6px;background:#eef3f8}.bsw-account-error{background:#fff0f0;color:#8b1e1e}.bsw-account-success{background:#edf8ef;color:#155724}.bsw-account-links{display:flex;gap:14px;flex-wrap:wrap;margin-top:16px}.bsw-account-portal a{color:#174a7e}</style>';
        $message=isset($_GET['bsw_message'])?sanitize_key(wp_unslash($_GET['bsw_message'])):'';
        $messages=array('check_email'=>'Check your inbox for the verification link.','reset_sent'=>'If the account exists, a password reset link has been sent.','password_reset'=>'Your password has been updated. You can now log in.','verified'=>'Your email is verified. You can now log in.','invalid'=>'That verification link is invalid or has expired.','registered'=>'Your account has been created. Verify your email to continue.','logged_out'=>'You have been logged out.','profile_saved'=>'Your profile was updated.');
        if(isset($messages[$message]))$out.=self::notice($messages[$message],in_array($message,array('verified','profile_saved'),true)?'success':'info');
        $page=is_singular()?(string)get_post_field('post_name',get_queried_object_id()):'';
        if(is_page() && $page!=='register') { $parent=(int)wp_get_post_parent_id(get_queried_object_id()); if($parent && get_post_field('post_name',$parent)==='register') $page='register-'.$page; }
        if (is_user_logged_in()) {
            $u=wp_get_current_user(); $out.='<h2>My BIGSHOR account</h2><p>Signed in as <strong>'.esc_html($u->display_name).'</strong>.</p><p><strong>Username:</strong> '.esc_html($u->user_login).'<br><strong>Email:</strong> '.esc_html($u->user_email).'<br><strong>Account role:</strong> '.esc_html(implode(', ',array_map('translate_user_role',(array)$u->roles))).'</p>';
            $out.=self::form_start('bsw67_profile').'<h3>Profile settings</h3>'.self::field('display_name','Display name','text','value="'.esc_attr($u->display_name).'" maxlength="60"').self::field('description','Short biography','text','value="'.esc_attr(get_user_meta($u->ID,'description',true)).'" maxlength="240"').'<p><button type="submit">Save profile</button></p></form>';
            $out.='<div class="bsw-account-links"><a href="'.esc_url(home_url('/wiki-contributors/')).'">Contributor directory</a><a href="'.esc_url(home_url('/my-watchlist/')).'">My watchlist</a></div>';
            $out.=self::form_start('bsw67_logout').'<p><button type="submit">Log out</button></p></form>';
        } elseif (strpos($page,'register')===0) {
            $role_label=isset($role_defs[$requested_role][0])?$role_defs[$requested_role][0]:'Article Creator';
            $is_elevated=!in_array($requested_role,array('wiki_article_creator','wiki_autoconfirmed'),true);
            $intro=$is_elevated?'Submit an application for this community role. Your account starts with basic contributor permissions; this role requires review and cannot be self-assigned.':'Join BIGSHOR WIKI to improve articles, add reliable sources and participate in the community.';
            $out.='<h2>Register as '.esc_html($role_label).'</h2><p>'.esc_html($intro).'</p>'.self::form_start('bsw67_register').'<input type="hidden" name="requested_role" value="'.esc_attr($requested_role).'">'.self::field('username','Username','text','autocomplete="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+"').self::field('email','Email address','email','autocomplete="email"').self::field('password','Password','password','autocomplete="new-password" minlength="12"').self::field('password_confirm','Confirm password','password','autocomplete="new-password" minlength="12"').'<p><label><input type="checkbox" name="terms" value="1" required> I agree to the community rules and privacy policy.</label></p><p class="bsw-hp" aria-hidden="true" style="position:absolute;left:-9999px"><label>Leave blank <input name="website" tabindex="-1" autocomplete="off"></label></p><p><button type="submit">Create account / submit application</button></p></form><div class="bsw-account-links"><a href="'.esc_url(self::url('login')).'">Already have an account? Log in</a></div>';
        } elseif ($page==='reset-password') {
            $out.='<h2>Reset your password</h2>';
            if(isset($_GET['key'],$_GET['login'])) {
                $key=sanitize_text_field(wp_unslash($_GET['key']));$login=sanitize_user(wp_unslash($_GET['login']));$user=check_password_reset_key($key,$login);
                if(is_wp_error($user)) $out.=self::notice('This password reset link is invalid or has expired. Request another link.','error');
                else $out.=self::form_start('bsw67_reset_password').'<input type="hidden" name="key" value="'.esc_attr($key).'"><input type="hidden" name="login" value="'.esc_attr($login).'">'.self::field('new_password','New password','password','autocomplete="new-password" minlength="12"').self::field('new_password_confirm','Confirm new password','password','autocomplete="new-password" minlength="12"').'<p><button type="submit">Set new password</button></p></form>';
            } else {
                $out.='<p>Enter your username or email address and we will send a secure reset link.</p>'.self::form_start('bsw67_lost_password').self::field('user_login','Username or email','text','autocomplete="username"').'<p><button type="submit">Send reset link</button></p></form>';
            }
            $out.='<div class="bsw-account-links"><a href="'.esc_url(self::url('login')).'">Back to login</a></div>';
        } elseif ($page==='account-verification' && isset($_GET['token'],$_GET['uid'])) {
            $out.='<h2>Email verification</h2><p>Complete the verification process to activate your account.</p>'.self::form_start('bsw67_verify').'<input type="hidden" name="uid" value="'.absint($_GET['uid']).'"><input type="hidden" name="token" value="'.esc_attr(sanitize_text_field(wp_unslash($_GET['token']))).'"> <p><button type="submit">Verify email address</button></p></form>';
        } else {
            $out.='<h2>Log in</h2>'.self::form_start('bsw67_login').self::field('log','Username or email','text','autocomplete="username"').self::field('pwd','Password','password','autocomplete="current-password"').'<p><label><input type="checkbox" name="rememberme" value="1"> Keep me logged in</label></p><p><button type="submit">Log in</button></p></form><div class="bsw-account-links"><a href="'.esc_url(self::url('register')).'">Create account</a><a href="'.esc_url(wp_lostpassword_url()).'">Forgot password?</a></div>';
        }
        return $out.'</div>';
    }
    private static function throttle($key) { $k='bsw67_rl_'.hash('sha256',$key);$n=(int)get_transient($k);if($n>=5)return false;set_transient($k,$n+1,15*MINUTE_IN_SECONDS);return true; }
    private static function redirect_message($slug,$message) { wp_safe_redirect(self::url($slug,array('bsw_message'=>$message)));exit; }
    public static function register() {
        check_admin_referer('bsw67_bsw67_register');
        if(!empty($_POST['website']))wp_die('Registration could not be completed.',400);
        $ip=isset($_SERVER['REMOTE_ADDR'])?sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])):'unknown';if(!self::throttle('register:'.$ip))wp_die('Too many registration attempts. Please try again later.',429);
        $name=isset($_POST['username'])?sanitize_user(wp_unslash($_POST['username']),true):'';$email=isset($_POST['email'])?sanitize_email(wp_unslash($_POST['email'])):'';$pass=isset($_POST['password'])?(string)wp_unslash($_POST['password']):'';$confirm=isset($_POST['password_confirm'])?(string)wp_unslash($_POST['password_confirm']):'';
        if(empty($_POST['terms'])||strlen($name)<3||strlen($name)>30||!validate_username($name)||!is_email($email)||strlen($pass)<12||$pass!==$confirm||username_exists($name)||email_exists($email))wp_die('Please check your details. Usernames and emails must be unique, and passwords must be at least 12 characters.',400);
        $requested=isset($_POST['requested_role'])?sanitize_key(wp_unslash($_POST['requested_role'])):'wiki_article_creator';$defs=class_exists('BSW_Phase66_User_Governance')?BSW_Phase66_User_Governance::definitions():array();if(!isset($defs[$requested])||$requested==='super_admin')$requested='wiki_article_creator';
        // Public sign-ups always receive the least-privileged contributor role.
        $uid=wp_insert_user(array('user_login'=>$name,'user_email'=>$email,'user_pass'=>$pass,'display_name'=>$name,'role'=>'wiki_article_creator'));
        if(!is_wp_error($uid) && $requested!=='wiki_article_creator') { update_user_meta($uid,'_bsw68_requested_role',$requested);update_user_meta($uid,'_bsw68_role_request_status','pending');$admin=get_option('admin_email');wp_mail($admin,'BIGSHOR WIKI role application',sprintf('User %s (%s) requested the %s role. Review the request in WordPress user management.', $name,$email,$defs[$requested][0])); }
        if(is_wp_error($uid))wp_die(esc_html($uid->get_error_message()),400);
        $token=wp_generate_password(48,false,false);update_user_meta($uid,'_bsw67_verify_hash',wp_hash_password($token));update_user_meta($uid,'_bsw67_verify_expiry',time()+self::VERIFY_TTL);update_user_meta($uid,'_bsw67_email_verified',0);
        $link=self::url('account-verification',array('uid'=>$uid,'token'=>$token));$subject='Verify your BIGSHOR WIKI account';$body="Hello {$name},\n\nPlease verify your email address to activate your BIGSHOR WIKI account:\n{$link}\n\nThis link expires in 48 hours. If you did not create this account, you can ignore this email.";
        if(!wp_mail($email,$subject,$body)){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($uid);wp_die('We could not send the verification email. Please try again later.',500);}
        self::redirect_message('register','registered');
    }
    public static function verify() {
        check_admin_referer('bsw67_bsw67_verify');$uid=isset($_POST['uid'])?absint($_POST['uid']):0;$token=isset($_POST['token'])?sanitize_text_field(wp_unslash($_POST['token'])):'';$hash=get_user_meta($uid,'_bsw67_verify_hash',true);$expiry=(int)get_user_meta($uid,'_bsw67_verify_expiry',true);
        if(!$uid||!$hash||!$token||$expiry<time()||!wp_check_password($token,$hash,$uid))self::redirect_message('account-verification','invalid');
        update_user_meta($uid,'_bsw67_email_verified',1);delete_user_meta($uid,'_bsw67_verify_hash');delete_user_meta($uid,'_bsw67_verify_expiry');self::redirect_message('login','verified');
    }
    public static function require_verified_email($user,$username,$password) {
        if($user instanceof WP_User && get_user_meta($user->ID,'_bsw67_email_verified',true)==='0')return new WP_Error('bsw_email_unverified','Please verify your email address before logging in.');return $user;
    }
    public static function login() {
        check_admin_referer('bsw67_bsw67_login');$ip=isset($_SERVER['REMOTE_ADDR'])?sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])):'unknown';if(!self::throttle('login:'.$ip))wp_die('Too many login attempts. Please try again later.',429);
        $login=isset($_POST['log'])?sanitize_text_field(wp_unslash($_POST['log'])):'';$user=is_email($login)?get_user_by('email',$login):get_user_by('login',$login);$creds=array('user_login'=>$user?$user->user_login:$login,'user_password'=>isset($_POST['pwd'])?(string)wp_unslash($_POST['pwd']):'','remember'=>!empty($_POST['rememberme']));$result=wp_signon($creds,is_ssl());if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),401);wp_safe_redirect(self::url('my-account'));exit;
    }
    public static function profile() { if(!is_user_logged_in())auth_redirect();check_admin_referer('bsw67_bsw67_profile');$uid=get_current_user_id();$display=isset($_POST['display_name'])?sanitize_text_field(wp_unslash($_POST['display_name'])):'';$bio=isset($_POST['description'])?sanitize_text_field(wp_unslash($_POST['description'])):'';if($display==='')wp_die('Display name is required.',400);wp_update_user(array('ID'=>$uid,'display_name'=>$display));update_user_meta($uid,'description',substr($bio,0,240));self::redirect_message('my-account','profile_saved'); }
    public static function logout() { if(!is_user_logged_in())wp_safe_redirect(home_url('/'));check_admin_referer('bsw67_bsw67_logout');wp_logout();self::redirect_message('login','logged_out'); }
    public static function resend() { self::redirect_message('register','check_email'); }
    public static function lost_password() {
        check_admin_referer('bsw67_bsw67_lost_password');
        $login=isset($_POST['user_login'])?sanitize_text_field(wp_unslash($_POST['user_login'])):'';
        if($login==='') self::redirect_message('reset-password','invalid');
        $user=is_email($login)?get_user_by('email',$login):get_user_by('login',$login);
        if($user) retrieve_password($user->user_login);
        self::redirect_message('reset-password','reset_sent');
    }
    public static function reset_password() {
        check_admin_referer('bsw67_bsw67_reset_password');
        $key=isset($_POST['key'])?sanitize_text_field(wp_unslash($_POST['key'])):'';$login=isset($_POST['login'])?sanitize_user(wp_unslash($_POST['login'])):'';
        $user=check_password_reset_key($key,$login);
        $pass=isset($_POST['new_password'])?(string)wp_unslash($_POST['new_password']):'';$confirm=isset($_POST['new_password_confirm'])?(string)wp_unslash($_POST['new_password_confirm']):'';
        if(is_wp_error($user)||strlen($pass)<12||$pass!==$confirm)wp_die('The reset link may have expired or the passwords do not match. Request a new link.',400);
        reset_password($user,$pass);self::redirect_message('login','password_reset');
    }
    public static function mark_login($login,$user) { update_user_meta($user->ID,'_bsw67_last_login',time()); }
}
BSW_Phase67_Account_System::init();
register_activation_hook(dirname(__DIR__).'/bigshor-wiki-core.php',array('BSW_Phase67_Account_System','activate'));
add_action('admin_init',function(){if(get_option('bsw_phase67_account_version')!==BSW_Phase67_Account_System::VERSION)BSW_Phase67_Account_System::activate();});
endif;
