<?php
/**
 * BIGSHOR Wiki Phase 9: Editorial Governance & Reader Corrections.
 * Reader-submitted correction reports are private and require editorial review.
 * Compatible with PHP 7.4+ and WordPress 6.4+.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase9_Editorial_Governance')) {
final class BSW_Phase9_Editorial_Governance {
    const VERSION = '23.1.0';
    const OPTION = 'bsw_phase9_settings';
    const POST_TYPE = 'bsw_correction';

    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 42);
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_shortcode('bigshor_correction_form', array(__CLASS__, 'shortcode'));
        add_action('admin_post_bsw_submit_correction', array(__CLASS__, 'submit'));
        add_action('admin_post_nopriv_bsw_submit_correction', array(__CLASS__, 'submit'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array(__CLASS__, 'columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array(__CLASS__, 'column_content'), 10, 2);
        add_filter('post_row_actions', array(__CLASS__, 'row_actions'), 10, 2);
        add_action('admin_post_bsw_correction_status', array(__CLASS__, 'change_status'));
    }

    public static function defaults() { return array('enabled' => 1, 'allow_email' => 1, 'notify_admin' => 1); }
    public static function settings() {
        $saved = get_option(self::OPTION, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), self::defaults());
    }
    public static function enabled() { $s = self::settings(); return !empty($s['enabled']); }

    public static function register_post_type() {
        register_post_type(self::POST_TYPE, array(
            'labels' => array('name' => 'Correction Reports', 'singular_name' => 'Correction Report', 'menu_name' => 'Correction Reports'),
            'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'show_in_rest' => false,
            'supports' => array('title', 'editor'), 'capability_type' => 'post', 'map_meta_cap' => true,
            'exclude_from_search' => true, 'publicly_queryable' => false, 'rewrite' => false
        ));
    }

    public static function shortcode($atts) {
        if (!self::enabled()) return '';
        $atts = shortcode_atts(array('post_id' => 0, 'label' => 'Suggest a correction'), $atts, 'bigshor_correction_form');
        $post_id = absint($atts['post_id']);
        if (!$post_id && is_singular()) $post_id = get_queried_object_id();
        if (!$post_id || get_post_status($post_id) !== 'publish') return '';
        $post = get_post($post_id);
        if (!$post || !is_object($post)) return '';
        $s = self::settings();
        $result = isset($_GET['bsw_correction']) && !is_array($_GET['bsw_correction']) ? sanitize_key(wp_unslash($_GET['bsw_correction'])) : '';
        ob_start();
        echo '<section id="bsw-correction" class="bsw-p9-correction" aria-label="Suggest a correction"><h3>' . esc_html($atts['label']) . '</h3>';
        if ($result === 'sent') echo '<p role="status">Thank you. Your correction has been sent to the editorial team for review.</p>';
        elseif ($result === 'error') echo '<p role="alert">We could not submit your report. Please check the form and try again.</p>';
        echo '<p>Found an error or have a reliable source that could improve this page? Send a suggestion to our editorial team.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="bsw_submit_correction"><input type="hidden" name="target_post_id" value="' . absint($post_id) . '">';
        wp_nonce_field('bsw_submit_correction_' . $post_id, 'bsw_correction_nonce');
        echo '<label for="bsw-correction-message">Correction or suggestion</label><textarea id="bsw-correction-message" name="correction_message" rows="5" minlength="15" maxlength="3000" required></textarea>';
        echo '<label for="bsw-correction-source">Supporting source URL (optional)</label><input id="bsw-correction-source" type="url" name="source_url" maxlength="500" placeholder="https://example.com/source">';
        if (!empty($s['allow_email'])) echo '<label for="bsw-correction-email">Email (optional, if editors need to contact you)</label><input id="bsw-correction-email" type="email" name="reporter_email" maxlength="190">';
        echo '<div class="bsw-p9-hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
        echo '<button type="submit">Send to editorial team</button></form></section>';
        return ob_get_clean();
    }

    public static function submit() {
        $post_id = isset($_POST['target_post_id']) ? absint($_POST['target_post_id']) : 0;
        $redirect = $post_id ? get_permalink($post_id) : home_url('/');
        $fail = function() use ($redirect) { wp_safe_redirect(add_query_arg('bsw_correction', 'error', $redirect) . '#bsw-correction'); exit; };
        if (!self::enabled() || !$post_id || get_post_status($post_id) !== 'publish') $fail();
        if (!isset($_POST['bsw_correction_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_correction_nonce'])), 'bsw_submit_correction_' . $post_id)) $fail();
        if (!empty($_POST['website'])) $fail();
        $message = isset($_POST['correction_message']) && !is_array($_POST['correction_message']) ? trim(sanitize_textarea_field(wp_unslash($_POST['correction_message']))) : '';
        if (strlen($message) < 15 || strlen($message) > 3000) $fail();
        // Rate limit without storing the submitter's raw IP address.
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $rate_key = 'bsw_p9_rate_' . hash('sha256', $ip);
        if (get_transient($rate_key)) $fail();
        set_transient($rate_key, 1, 5 * MINUTE_IN_SECONDS);
        $email = '';
        $settings = self::settings();
        if (!empty($settings['allow_email']) && isset($_POST['reporter_email']) && !is_array($_POST['reporter_email'])) {
            $candidate = sanitize_email(wp_unslash($_POST['reporter_email']));
            if ($candidate && is_email($candidate)) $email = $candidate;
        }
        $source_url = '';
        if (isset($_POST['source_url']) && !is_array($_POST['source_url'])) {
            $candidate_url = esc_url_raw(trim(wp_unslash($_POST['source_url'])));
            if ($candidate_url && wp_http_validate_url($candidate_url)) $source_url = $candidate_url;
        }
        $title = 'Correction for: ' . wp_strip_all_tags(get_the_title($post_id));
        $report_id = wp_insert_post(array(
            'post_type' => self::POST_TYPE, 'post_status' => 'private', 'post_title' => $title,
            'post_content' => $message, 'meta_input' => array(
                '_bsw_target_post_id' => $post_id, '_bsw_reporter_email' => $email,
                '_bsw_report_status' => 'new', '_bsw_report_submitted' => current_time('mysql'), '_bsw_report_source_url' => $source_url
            )
        ), true);
        if (is_wp_error($report_id) || !$report_id) $fail();
        if (!empty($settings['notify_admin'])) {
            $admin_email = get_option('admin_email');
            if (is_email($admin_email)) {
                $subject = '[BIGSHOR Wiki] New reader correction report';
                $body = "A reader submitted a correction report.\n\nArticle: " . get_the_title($post_id) . "\nReview report: " . admin_url('post.php?post=' . absint($report_id) . '&action=edit');
                wp_mail($admin_email, $subject, $body);
            }
        }
        wp_safe_redirect(add_query_arg('bsw_correction', 'sent', $redirect) . '#bsw-correction');
        exit;
    }

    public static function admin_menu() {
        add_submenu_page('options-general.php', 'BIGSHOR Editorial Governance', 'BIGSHOR Editorial', 'manage_options', 'bsw-phase9', array(__CLASS__, 'settings_page'));
    }
    public static function register_settings() {
        register_setting('bsw_phase9_group', self::OPTION, array('sanitize_callback' => array(__CLASS__, 'sanitize_settings')));
    }
    public static function sanitize_settings($input) {
        $input = is_array($input) ? $input : array();
        return array('enabled' => empty($input['enabled']) ? 0 : 1, 'allow_email' => empty($input['allow_email']) ? 0 : 1, 'notify_admin' => empty($input['notify_admin']) ? 0 : 1);
    }
    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = self::settings();
        $counts = wp_count_posts(self::POST_TYPE);
        echo '<div class="wrap"><h1>BIGSHOR Editorial Governance — Phase 9</h1><p>Reader correction reports are stored privately and do not modify published articles. Editors review every report before making changes.</p>';
        echo '<p><strong>Private reports:</strong> ' . absint(isset($counts->private) ? $counts->private : 0) . '</p><p><a class="button button-secondary" href="' . esc_url(admin_url('edit.php?post_type=' . self::POST_TYPE)) . '">Review correction reports</a></p>';
        echo '<form method="post" action="options.php">'; settings_fields('bsw_phase9_group');
        echo '<p><label><input type="checkbox" name="' . esc_attr(self::OPTION) . '[enabled]" value="1" ' . checked($s['enabled'], 1, false) . '> Enable reader correction submissions</label></p>';
        echo '<p><label><input type="checkbox" name="' . esc_attr(self::OPTION) . '[allow_email]" value="1" ' . checked($s['allow_email'], 1, false) . '> Allow an optional contact email</label></p>';
        echo '<p><label><input type="checkbox" name="' . esc_attr(self::OPTION) . '[notify_admin]" value="1" ' . checked($s['notify_admin'], 1, false) . '> Email the WordPress site administrator when a new report arrives</label></p>';
        submit_button('Save editorial settings'); echo '</form><hr><h2>Shortcode</h2><p><code>[bigshor_correction_form]</code> — displays a correction form for the current published page.</p><p><code>[bigshor_correction_form post_id="123"]</code> — targets a specific published article.</p></div>';
    }

    public static function add_meta_boxes() {
        add_meta_box('bsw_p9_report_details', 'Editorial Review', array(__CLASS__, 'meta_box'), self::POST_TYPE, 'side', 'high');
    }
    public static function meta_box($post) {
        $target = absint(get_post_meta($post->ID, '_bsw_target_post_id', true));
        $status = get_post_meta($post->ID, '_bsw_report_status', true);
        $email = get_post_meta($post->ID, '_bsw_reporter_email', true);
        $source_url = get_post_meta($post->ID, '_bsw_report_source_url', true);
        echo '<p><strong>Target:</strong> ' . ($target && get_post($target) ? '<a href="' . esc_url(get_edit_post_link($target)) . '">' . esc_html(get_the_title($target)) . '</a>' : 'Unavailable') . '</p>';
        echo '<p><strong>Reporter email:</strong> ' . ($email ? esc_html($email) : 'Not provided') . '</p>';
        echo '<p><strong>Supporting source:</strong> ' . ($source_url ? '<a href="' . esc_url($source_url) . '" target="_blank" rel="noopener noreferrer">Open source</a>' : 'Not provided') . '</p>';
        echo '<p><strong>Status:</strong> ' . esc_html($status ? ucfirst($status) : 'New') . '</p>';
        echo '<p>Change the report status using the actions in the report list. Update the target article separately after editorial verification.</p>';
    }
    public static function columns($columns) {
        $out = array();
        foreach ($columns as $key => $label) { $out[$key] = $label; if ($key === 'title') { $out['bsw_target'] = 'Target article'; $out['bsw_status'] = 'Review status'; } }
        return $out;
    }
    public static function column_content($column, $post_id) {
        if ($column === 'bsw_target') {
            $target = absint(get_post_meta($post_id, '_bsw_target_post_id', true));
            if ($target && get_post($target)) echo '<a href="' . esc_url(get_edit_post_link($target)) . '">' . esc_html(get_the_title($target)) . '</a>';
            else echo '—';
        } elseif ($column === 'bsw_status') {
            $status = get_post_meta($post_id, '_bsw_report_status', true);
            echo esc_html($status ? ucfirst($status) : 'New');
        }
    }
    public static function row_actions($actions, $post) {
        if (!is_object($post) || $post->post_type !== self::POST_TYPE || !current_user_can('edit_post', $post->ID)) return $actions;
        foreach (array('reviewing' => 'Mark reviewing', 'resolved' => 'Mark resolved', 'dismissed' => 'Dismiss') as $status => $label) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=bsw_correction_status&post_id=' . absint($post->ID) . '&status=' . $status), 'bsw_correction_status_' . $post->ID);
            $actions['bsw_' . $status] = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        return $actions;
    }
    public static function change_status() {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        if (!$post_id || get_post_type($post_id) !== self::POST_TYPE || !current_user_can('edit_post', $post_id)) wp_die(esc_html__('You are not allowed to update this report.', 'bigshor-wiki'));
        check_admin_referer('bsw_correction_status_' . $post_id);
        if (!in_array($status, array('reviewing', 'resolved', 'dismissed'), true)) wp_die(esc_html__('Invalid report status.', 'bigshor-wiki'));
        update_post_meta($post_id, '_bsw_report_status', $status);
        wp_safe_redirect(admin_url('edit.php?post_type=' . self::POST_TYPE)); exit;
    }
}
BSW_Phase9_Editorial_Governance::init();
add_action('wp_enqueue_scripts', function () {
    if (!BSW_Phase9_Editorial_Governance::enabled()) return;
    $css = '.bsw-p9-correction{margin:2em 0;padding:18px;border:1px solid #dfe3e8;border-radius:9px}.bsw-p9-correction form{display:grid;gap:10px}.bsw-p9-correction input[type=email],.bsw-p9-correction textarea{width:100%;padding:10px;border:1px solid #cbd2da;border-radius:5px}.bsw-p9-correction button{justify-self:start;padding:10px 16px;cursor:pointer}.bsw-p9-hp{position:absolute!important;left:-10000px!important;width:1px!important;height:1px!important;overflow:hidden!important}';
    wp_register_style('bsw-phase9-inline', false); wp_enqueue_style('bsw-phase9-inline'); wp_add_inline_style('bsw-phase9-inline', $css);
});
}
