<?php
/**
 * Phase 68: dedicated public role-registration landing pages.
 * Privileged wiki roles are application-only; public visitors always receive
 * the least-privileged article-creator role until an authorised review.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase68_Role_Registration_Pages')):
final class BSW_Phase68_Role_Registration_Pages {
    public static function init() {
        add_action('init', array(__CLASS__, 'ensure_pages'), 25);
        add_filter('manage_users_columns', array(__CLASS__, 'user_column'));
        add_filter('manage_users_custom_column', array(__CLASS__, 'user_column_value'), 10, 3);
        add_action('show_user_profile', array(__CLASS__, 'profile_request'));
        add_action('edit_user_profile', array(__CLASS__, 'profile_request'));
        add_action('personal_options_update', array(__CLASS__, 'save_request_status'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_request_status'));
    }
    public static function ensure_pages() {
        if (get_option('bsw_phase68_pages_version') === '1.1.0') return;
        if (!class_exists('BSW_Phase66_User_Governance')) return;
        $defs = BSW_Phase66_User_Governance::definitions();
        $register_page = get_page_by_path('register');
        if (!$register_page) {
            $register_id = wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Register','post_name'=>'register','post_content'=>'[bigshor_account role="wiki_article_creator"]','comment_status'=>'closed'));
            $register_page = $register_id && !is_wp_error($register_id) ? get_post($register_id) : null;
        }
        if (!$register_page) return;
        $pages = array();
        foreach ($defs as $slug => $def) {
            // WordPress multisite Super Admin is a network-level privilege, not a public role.
            if ($slug === 'super_admin' || strpos($slug, 'wiki_') !== 0) continue;
            $short = str_replace('_','-',substr($slug, 5));
            $pages[$short] = array('Register — '.$def[0], '[bigshor_account role="'.$slug.'"]');
        }
        foreach ($pages as $slug => $data) {
            $path = 'register/'.$slug;
            $existing = get_page_by_path($path);
            if (!$existing) {
                $id = wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>$data[0],'post_name'=>$slug,'post_parent'=>$register_page->ID,'post_content'=>$data[1],'comment_status'=>'closed'));
                if ($id && !is_wp_error($id)) update_post_meta($id, '_bsw68_managed_registration_page', 1);
            } elseif (get_post_meta($existing->ID, '_bsw68_managed_registration_page', true)) {
                if ($existing->post_content !== $data[1]) wp_update_post(array('ID'=>$existing->ID,'post_content'=>$data[1]));
            }
        }
        update_option('bsw_phase68_pages_version', '1.1.0', false);
    }
    public static function user_column($columns) { $columns['bsw_role_request'] = 'Wiki role application'; return $columns; }
    public static function user_column_value($value, $column, $user_id) {
        if ($column !== 'bsw_role_request') return $value;
        $slug = get_user_meta($user_id, '_bsw68_requested_role', true);
        if (!$slug) return '—';
        $defs = BSW_Phase66_User_Governance::definitions();
        $label = isset($defs[$slug][0]) ? $defs[$slug][0] : $slug;
        $status = get_user_meta($user_id, '_bsw68_role_request_status', true) ?: 'pending';
        return esc_html($label.' — '.ucfirst($status));
    }
    public static function profile_request($user) {
        if (!current_user_can('edit_users') || !current_user_can('promote_users')) return;
        $slug = get_user_meta($user->ID, '_bsw68_requested_role', true);
        if (!$slug) return;
        $defs = BSW_Phase66_User_Governance::definitions();
        $label = isset($defs[$slug][0]) ? $defs[$slug][0] : $slug;
        $status = get_user_meta($user->ID, '_bsw68_role_request_status', true) ?: 'pending';
        wp_nonce_field('bsw68_review_role_'.$user->ID, 'bsw68_role_nonce');
        echo '<h2>BIGSHOR WIKI role application</h2><table class="form-table"><tr><th>Requested role</th><td>'.esc_html($label).'</td></tr><tr><th><label for="bsw68_role_status">Application status</label></th><td><select name="bsw68_role_status" id="bsw68_role_status"><option value="pending" '.selected($status,'pending',false).'>Pending</option><option value="approved" '.selected($status,'approved',false).'>Approved</option><option value="declined" '.selected($status,'declined',false).'>Declined</option></select><p class="description">Approving the application records the decision only. Assign the role separately using BIGSHOR Wiki → Roles &amp; Permissions.</p></td></tr></table>';
    }
    public static function save_request_status($user_id) {
        if (!current_user_can('edit_users') || !current_user_can('promote_users') || empty($_POST['bsw68_role_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw68_role_nonce'])), 'bsw68_review_role_'.$user_id)) return;
        $status = isset($_POST['bsw68_role_status']) ? sanitize_key(wp_unslash($_POST['bsw68_role_status'])) : 'pending';
        if (in_array($status, array('pending','approved','declined'), true)) update_user_meta($user_id, '_bsw68_role_request_status', $status);
    }
}
BSW_Phase68_Role_Registration_Pages::init();
endif;
