<?php
/**
 * BIGSHOR Core 29.1.0 — Phase 36 Editorial Governance System.
 * Adds separate News publishing, registered/verified submissions, evidence capture,
 * reviewer attribution and mandatory independent second review for high-risk content.
 * PHP 7.4 compatible. Human editors retain final publication authority.
 */
if (!defined('ABSPATH')) { exit; }

if (!class_exists('BSW_Phase36_Editorial_System')) {
final class BSW_Phase36_Editorial_System {
    const VERSION = '1.0.0';
    const POLICY_VERSION = '1.0';
    const META_DIVISION = '_bsw_p36_division';
    const META_SOURCES = '_bsw_p36_source_urls';
    const META_NOTABILITY = '_bsw_p36_notability_rationale';
    const META_COI = '_bsw_p36_coi';
    const META_HIGH_RISK = '_bsw_p36_high_risk';
    const META_RISK_ASSESSED = '_bsw_p36_risk_assessed';
    const META_CHECKS = '_bsw_p36_checks';
    const META_NOTABILITY_DECISION = '_bsw_p36_notability_decision';
    const META_SOURCE_DECISION = '_bsw_p36_source_decision';
    const META_REVIEW1_USER = '_bsw_p36_review1_user';
    const META_REVIEW1_DECISION = '_bsw_p36_review1_decision';
    const META_REVIEW1_NOTES = '_bsw_p36_review1_notes';
    const META_REVIEW1_DATE = '_bsw_p36_review1_date';
    const META_REVIEW2_USER = '_bsw_p36_review2_user';
    const META_REVIEW2_DECISION = '_bsw_p36_review2_decision';
    const META_REVIEW2_NOTES = '_bsw_p36_review2_notes';
    const META_REVIEW2_DATE = '_bsw_p36_review2_date';
    const META_POLICY_ACCEPTED = '_bsw_p36_policy_version';
    const AUDIT_OPTION = 'bsw_p36_editorial_audit';
    const FLUSH_OPTION = 'bsw_p36_rewrite_flushed';

    public static function init() {
        add_action('init', array(__CLASS__, 'register_news_type'), 20);
        add_action('init', array(__CLASS__, 'register_shortcodes'), 99);
        add_action('init', array(__CLASS__, 'disable_legacy_submission_route'), 1000);
        add_action('add_meta_boxes', array(__CLASS__, 'register_meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_editorial_meta'), 30, 2);
        add_action('transition_post_status', array(__CLASS__, 'publication_guard'), 1, 3);
        add_action('admin_notices', array(__CLASS__, 'admin_notices'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 60);
        add_action('admin_post_bsw_p36_hold_seed_profiles', array(__CLASS__, 'hold_unreviewed_seed_profiles'));
        add_action('admin_init', array(__CLASS__, 'maybe_flush_rewrite_rules'));
        add_filter('manage_bsw_news_posts_columns', array(__CLASS__, 'news_columns'));
        add_action('manage_bsw_news_posts_custom_column', array(__CLASS__, 'news_column_value'), 10, 2);
    }

    public static function register_news_type() {
        if (!post_type_exists('bsw_news')) {
            register_post_type('bsw_news', array(
                'labels' => array('name' => 'BIGSHOR News', 'singular_name' => 'News Article', 'add_new_item' => 'Add News Article', 'edit_item' => 'Edit News Article'),
                'public' => true,
                'publicly_queryable' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'has_archive' => true,
                'rewrite' => array('slug' => 'news', 'with_front' => false),
                'menu_icon' => 'dashicons-megaphone',
                'supports' => array('title', 'editor', 'author', 'excerpt', 'thumbnail', 'revisions'),
                'capability_type' => 'post',
                'map_meta_cap' => true,
            ));
        }
        if (!taxonomy_exists('bsw_news_category')) {
            register_taxonomy('bsw_news_category', array('bsw_news'), array(
                'labels' => array('name' => 'News Categories', 'singular_name' => 'News Category'),
                'public' => true, 'show_in_rest' => true, 'hierarchical' => true,
                'rewrite' => array('slug' => 'news-category'),
            ));
        }
    }

    public static function register_shortcodes() {
        // Deliberately overrides the legacy form so new submissions carry governance metadata.
        add_shortcode('bigshor_wiki_submit', array(__CLASS__, 'submission_form'));
        add_shortcode('bigshor_news_recent', array(__CLASS__, 'news_recent'));
        add_shortcode('bigshor_contributor_dashboard', array(__CLASS__, 'contributor_dashboard'));
    }

    public static function maybe_flush_rewrite_rules() {
        if (!current_user_can('manage_options')) { return; }
        if (get_option(self::FLUSH_OPTION) !== self::VERSION) {
            self::register_news_type();
            flush_rewrite_rules(false);
            update_option(self::FLUSH_OPTION, self::VERSION, false);
        }
    }

    public static function disable_legacy_submission_route() {
        if (class_exists('BSW_Phases_11_15')) {
            remove_action('admin_post_bsw_contributor_submit', array('BSW_Phases_11_15', 'contributor_submit'));
        }
    }

    public static function contributor_dashboard() {
        if (!is_user_logged_in()) {
            return '<p>Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> to view your contributor dashboard.</p>';
        }
        $user_id = get_current_user_id();
        if (!self::contributor_can_submit()) {
            return '<p>Your account must have verified email and BIGSHOR contributor access to submit content.</p>';
        }
        $posts = get_posts(array('author' => $user_id, 'post_type' => array('wiki_article', 'bsw_news'), 'post_status' => array('draft', 'pending', 'publish', 'future'), 'numberposts' => 30, 'orderby' => 'modified', 'order' => 'DESC'));
        $html = '<section class="bsw-contributor-dashboard"><h2>My BIGSHOR contributions</h2><ul>';
        foreach ($posts as $item) {
            $title_html = current_user_can('edit_post', $item->ID) ? '<a href="' . esc_url(get_edit_post_link($item->ID)) . '">' . esc_html(get_the_title($item)) . '</a>' : esc_html(get_the_title($item));
            $html .= '<li>' . $title_html . ' — ' . esc_html(ucfirst($item->post_type)) . ' — ' . esc_html($item->post_status) . '</li>';
        }
        if (!$posts) { $html .= '<li>No contributions yet.</li>'; }
        $html .= '</ul><h3>Submit new content</h3>' . do_shortcode('[bigshor_wiki_submit]') . '</section>';
        return $html;
    }

    public static function news_recent($atts) {
        $atts = shortcode_atts(array('limit' => 10), $atts, 'bigshor_news_recent');
        $limit = max(1, min(30, absint($atts['limit'])));
        $q = new WP_Query(array('post_type' => 'bsw_news', 'post_status' => 'publish', 'posts_per_page' => $limit, 'no_found_rows' => true));
        if (!$q->have_posts()) { return '<p>No news articles have been published yet.</p>'; }
        $html = '<div class="bsw-news-list">';
        while ($q->have_posts()) {
            $q->the_post();
            $html .= '<article class="bsw-news-item"><h3><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3><p>' . esc_html(get_the_excerpt()) . '</p><small>' . esc_html(get_the_date()) . ' · ' . esc_html(get_the_author()) . '</small></article>';
        }
        wp_reset_postdata();
        return $html . '</div>';
    }

    private static function contributor_can_submit() {
        if (!is_user_logged_in()) { return false; }
        $user = wp_get_current_user();
        if (!($user instanceof WP_User)) { return false; }
        if (current_user_can('edit_others_posts')) { return true; }
        if (!in_array('wiki_contributor', (array) $user->roles, true)) { return false; }
        return get_user_meta($user->ID, '_bsw_email_verified', true) === '1';
    }

    private static function parse_source_urls($raw) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw);
        $urls = array();
        foreach ($lines as $line) {
            $url = esc_url_raw(trim($line), array('http', 'https'));
            if ($url && wp_http_validate_url($url)) { $urls[] = $url; }
        }
        return array_values(array_unique(array_slice($urls, 0, 20)));
    }

    private static function duplicate_title_exists($title) {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type IN ('wiki_article','bsw_news') AND post_status NOT IN ('trash','auto-draft') LIMIT 1",
            $title
        );
        return (bool) $wpdb->get_var($sql);
    }

    public static function submission_form() {
        if (!is_user_logged_in()) {
            return '<div class="bsw-editorial-notice"><p>BIGSHOR accepts submissions from registered contributors only. Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> or create an account.</p></div>';
        }
        if (!self::contributor_can_submit()) {
            return '<div class="bsw-editorial-notice"><p>Your account is not currently authorised to submit articles. Contributor access and verified email are required. Contact the BIGSHOR editorial team if you believe this is an error.</p></div>';
        }

        $notice = '';
        if (isset($_POST['bsw_submit_article'])) {
            if (!isset($_POST['bsw_p36_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p36_nonce'])), 'bsw_p36_submit')) {
                $notice = '<p class="wiki-alert">Security validation failed. Please refresh and try again.</p>';
            } else {
                $division = sanitize_key(wp_unslash($_POST['bsw_division'] ?? 'knowledge'));
                $title = sanitize_text_field(wp_unslash($_POST['bsw_title'] ?? ''));
                $body = wp_kses_post(wp_unslash($_POST['bsw_body'] ?? ''));
                $summary = sanitize_textarea_field(wp_unslash($_POST['bsw_summary'] ?? ''));
                $rationale = sanitize_textarea_field(wp_unslash($_POST['bsw_notability'] ?? ''));
                $coi = sanitize_textarea_field(wp_unslash($_POST['bsw_coi'] ?? ''));
                $sources = self::parse_source_urls(wp_unslash($_POST['bsw_sources'] ?? ''));
                $accepted = !empty($_POST['bsw_policy_accept']);
                $user_id = get_current_user_id();
                $rate_key = 'bsw_p36_submit_' . $user_id;

                if (!in_array($division, array('knowledge', 'news'), true)) {
                    $notice = '<p class="wiki-alert">Choose a valid publishing division.</p>';
                } elseif (strlen($title) < 5 || strlen(wp_strip_all_tags($body)) < 100) {
                    $notice = '<p class="wiki-alert">Provide a descriptive title and at least 100 characters of content.</p>';
                } elseif (!$accepted) {
                    $notice = '<p class="wiki-alert">You must accept the BIGSHOR Contributor and Editorial Policies.</p>';
                } elseif (count($sources) < ($division === 'knowledge' ? 2 : 1)) {
                    $notice = '<p class="wiki-alert">' . ($division === 'knowledge' ? 'Knowledge submissions require at least two source URLs.' : 'News submissions require at least one supporting source URL.') . '</p>';
                } elseif ($division === 'knowledge' && strlen($rationale) < 40) {
                    $notice = '<p class="wiki-alert">Explain why this subject merits an encyclopaedic article, with reference to independent coverage.</p>';
                } elseif (self::duplicate_title_exists($title)) {
                    $notice = '<p class="wiki-alert">A submission with this exact title already exists. Please check the existing BIGSHOR content before submitting.</p>';
                } elseif (get_transient($rate_key)) {
                    $notice = '<p class="wiki-alert">Please wait before submitting another article. BIGSHOR applies a submission cooldown to protect the editorial queue.</p>';
                } else {
                    $post_type = $division === 'news' ? 'bsw_news' : 'wiki_article';
                    $post_id = wp_insert_post(array(
                        'post_type' => $post_type,
                        'post_title' => $title,
                        'post_content' => $body,
                        'post_excerpt' => $summary,
                        'post_status' => 'pending',
                        'post_author' => $user_id,
                    ), true);
                    if (!is_wp_error($post_id)) {
                        update_post_meta($post_id, self::META_DIVISION, $division);
                        update_post_meta($post_id, self::META_SOURCES, $sources);
                        update_post_meta($post_id, self::META_NOTABILITY, $rationale);
                        update_post_meta($post_id, self::META_COI, $coi);
                        update_post_meta($post_id, self::META_POLICY_ACCEPTED, self::POLICY_VERSION);
                        update_post_meta($post_id, '_bsw_p36_submitted_at', current_time('mysql'));
                        update_post_meta($post_id, '_bsw_p36_workflow', 'submitted');
                        set_transient($rate_key, 1, 10 * MINUTE_IN_SECONDS);
                        self::audit('submission_created', $post_id, 'Division: ' . $division);
                        $notice = '<p class="bsw-success">Your submission has entered BIGSHOR editorial review. Reference #' . absint($post_id) . '. It will not be published automatically.</p>';
                    } else {
                        $notice = '<p class="wiki-alert">Submission failed. Please contact the BIGSHOR editorial team.</p>';
                    }
                }
            }
        }

        $html = $notice . '<form class="bsw-v3-form bsw-p36-submission" method="post">' . wp_nonce_field('bsw_p36_submit', 'bsw_p36_nonce', true, false);
        $html .= '<p><label>Publishing division<select name="bsw_division" required><option value="knowledge">BIGSHOR Knowledge — encyclopaedic article</option><option value="news">BIGSHOR News — news report</option></select></label></p>';
        $html .= '<p><label>Article title<input required minlength="5" maxlength="180" name="bsw_title"></label></p>';
        $html .= '<p><label>Summary<textarea name="bsw_summary" rows="3" maxlength="500"></textarea></label></p>';
        $html .= '<p><label>Article content<textarea required minlength="100" name="bsw_body" rows="16"></textarea></label></p>';
        $html .= '<p><label>Why is this subject notable? <small>(Required for BIGSHOR Knowledge)</small><textarea name="bsw_notability" rows="4" placeholder="Describe independent coverage and the subject’s significance."></textarea></label></p>';
        $html .= '<p><label>Supporting source URLs <small>(one URL per line; at least two for Knowledge and one for News)</small><textarea required name="bsw_sources" rows="5" placeholder="https://example.com/source-one\nhttps://example.com/source-two"></textarea></label></p>';
        $html .= '<p><label>Conflict-of-interest disclosure <small>(Describe any personal, professional, financial or organisational relationship with the subject. Write “None” if none.)</small><textarea required name="bsw_coi" rows="3"></textarea></label></p>';
        $html .= '<p><label><input type="checkbox" name="bsw_policy_accept" value="1" required> I confirm that the submission is made in good faith, sources are genuine, claims are not knowingly misleading, conflicts are disclosed, and I agree to BIGSHOR editorial, contributor, copyright and correction policies.</label></p>';
        $html .= '<p class="wiki-meta">All submissions remain unpublished until the required human editorial approvals are recorded. Submission does not guarantee acceptance.</p>';
        $html .= '<button class="wiki-button" type="submit" name="bsw_submit_article" value="1">Submit for editorial review</button></form>';
        return $html;
    }

    public static function register_meta_boxes() {
        foreach (array('wiki_article', 'bsw_news') as $type) {
            add_meta_box('bsw-p36-editorial', 'BIGSHOR Editorial Review & Evidence', array(__CLASS__, 'render_editorial_box'), $type, 'normal', 'high');
        }
    }

    public static function render_editorial_box($post) {
        if (!current_user_can('edit_others_posts')) {
            echo '<p>Editorial review controls are restricted to authorised editors.</p>';
            return;
        }
        wp_nonce_field('bsw_p36_review_' . $post->ID, 'bsw_p36_review_nonce');
        $division = get_post_meta($post->ID, self::META_DIVISION, true) ?: ($post->post_type === 'bsw_news' ? 'news' : 'knowledge');
        $sources = get_post_meta($post->ID, self::META_SOURCES, true);
        if (!is_array($sources)) { $sources = array(); }
        $checks = get_post_meta($post->ID, self::META_CHECKS, true);
        if (!is_array($checks)) { $checks = array(); }
        $high = get_post_meta($post->ID, self::META_HIGH_RISK, true) === '1';
        $r1 = absint(get_post_meta($post->ID, self::META_REVIEW1_USER, true));
        $r2 = absint(get_post_meta($post->ID, self::META_REVIEW2_USER, true));
        $author = absint($post->post_author);
        $current = get_current_user_id();
        $can_review = ($current !== $author);
        echo '<p><strong>Division:</strong> ' . esc_html(ucfirst($division)) . ' · <strong>Contributor:</strong> ' . esc_html(get_the_author_meta('display_name', $author)) . '</p>';
        echo '<p><strong>Notability rationale:</strong><br>' . nl2br(esc_html(get_post_meta($post->ID, self::META_NOTABILITY, true))) . '</p>';
        echo '<p><strong>Conflict-of-interest disclosure:</strong><br>' . nl2br(esc_html(get_post_meta($post->ID, self::META_COI, true))) . '</p>';
        echo '<p><strong>Submitted sources:</strong></p><ul>';
        foreach ($sources as $url) { echo '<li><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a></li>'; }
        if (!$sources) { echo '<li>No source URLs supplied.</li>'; }
        echo '</ul><p><label><strong>Source URLs (one per line)</strong><br><textarea name="bsw_p36_sources_admin" rows=4 style="width:100%">' . esc_textarea(implode("\n", $sources)) . '</textarea></label></p><p><label><strong>Notability rationale / editorial context</strong><br><textarea name="bsw_p36_notability_admin" rows=3 style="width:100%">' . esc_textarea(get_post_meta($post->ID, self::META_NOTABILITY, true)) . '</textarea></label></p><hr><p><label><input type="checkbox" name="bsw_p36_risk_assessed" value="1" ' . checked(get_post_meta($post->ID, self::META_RISK_ASSESSED, true), '1', false) . '> I have assessed the subject for sensitivity and editorial risk.</label></p><p><label><input type="checkbox" name="bsw_p36_high_risk" value="1" ' . checked($high, true, false) . '> <strong>High-risk / sensitive article</strong> — requires two independent approvals. Once marked, this flag cannot be removed through the ordinary editor form.</label></p>';
        if ($post->post_type === 'wiki_article') {
            $notability = get_post_meta($post->ID, self::META_NOTABILITY_DECISION, true) ?: 'not_assessed';
            echo '<p><label><strong>Notability assessment</strong><br><select name="bsw_p36_notability_decision"><option value="not_assessed" ' . selected($notability, 'not_assessed', false) . '>Not assessed</option><option value="meets" ' . selected($notability, 'meets', false) . '>Meets BIGSHOR notability standard</option><option value="more_evidence" ' . selected($notability, 'more_evidence', false) . '>More evidence required</option><option value="does_not_meet" ' . selected($notability, 'does_not_meet', false) . '>Does not meet standard</option></select></label></p>';
        }
        $source_decision = get_post_meta($post->ID, self::META_SOURCE_DECISION, true) ?: 'not_assessed';
        echo '<p><label><strong>Source assessment</strong><br><select name="bsw_p36_source_decision"><option value="not_assessed" ' . selected($source_decision, 'not_assessed', false) . '>Not assessed</option><option value="adequate" ' . selected($source_decision, 'adequate', false) . '>Adequate and relevant</option><option value="insufficient" ' . selected($source_decision, 'insufficient', false) . '>Insufficient</option><option value="unreliable" ' . selected($source_decision, 'unreliable', false) . '>Unreliable / disputed</option></select></label></p>';
        echo '<p><strong>Required editorial checks</strong></p>';
        $labels = array('claims' => 'Material claims checked against cited sources', 'independence' => 'Source independence and quality assessed', 'neutrality' => 'Neutrality and balance assessed', 'conflict' => 'Conflict-of-interest disclosure assessed', 'rights' => 'Copyright, attribution and media rights assessed');
        foreach ($labels as $key => $label) { echo '<p><label><input type="checkbox" name="bsw_p36_checks[]" value="' . esc_attr($key) . '" ' . checked(in_array($key, $checks, true), true, false) . '> ' . esc_html($label) . '</label></p>'; }
        echo '<hr><h4>Independent reviewer records</h4>';
        echo '<p>Reviewer 1: ' . ($r1 ? esc_html(get_the_author_meta('display_name', $r1)) . ' — ' . esc_html(get_post_meta($post->ID, self::META_REVIEW1_DECISION, true)) : 'Not assigned') . '</p>';
        echo '<p>Reviewer 2: ' . ($r2 ? esc_html(get_the_author_meta('display_name', $r2)) . ' — ' . esc_html(get_post_meta($post->ID, self::META_REVIEW2_DECISION, true)) : 'Not assigned') . '</p>';
        if (!$can_review) {
            echo '<p><strong>You submitted this article and cannot review or approve it.</strong></p>';
        } else {
            $slot = (!$r1 || $r1 === $current) ? 'review1' : (($high && (!$r2 || $r2 === $current)) ? 'review2' : 'locked');
            if ($slot === 'locked') {
                echo '<p>This review slot is assigned to another editor. A different authorised editor must complete the next review.</p>';
            } else {
                $decision_key = $slot === 'review1' ? self::META_REVIEW1_DECISION : self::META_REVIEW2_DECISION;
                $notes_key = $slot === 'review1' ? self::META_REVIEW1_NOTES : self::META_REVIEW2_NOTES;
                $decision = get_post_meta($post->ID, $decision_key, true) ?: 'pending';
                $notes = get_post_meta($post->ID, $notes_key, true);
                echo '<p><strong>Your review slot: ' . ($slot === 'review1' ? 'Reviewer 1' : 'Reviewer 2') . '</strong></p>';
                echo '<p><label>Decision<br><select name="bsw_p36_review_decision"><option value="pending" ' . selected($decision, 'pending', false) . '>Pending</option><option value="approved" ' . selected($decision, 'approved', false) . '>Approved</option><option value="changes_requested" ' . selected($decision, 'changes_requested', false) . '>Changes requested</option><option value="rejected" ' . selected($decision, 'rejected', false) . '>Rejected</option></select></label></p>';
                echo '<p><label>Review notes<br><textarea name="bsw_p36_review_notes" rows="3" style="width:100%">' . esc_textarea($notes) . '</textarea></label></p>';
            }
        }
        echo '<p class="description">Save the editorial review first. Publication is a separate action and is blocked until all applicable evidence checks and independent approvals are complete.</p>';
    }

    public static function save_editorial_meta($post_id, $post) {
        if (!$post instanceof WP_Post || !in_array($post->post_type, array('wiki_article', 'bsw_news'), true)) { return; }
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { return; }
        if (!isset($_POST['bsw_p36_review_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p36_review_nonce'])), 'bsw_p36_review_' . $post_id)) { return; }
        if (!current_user_can('edit_others_posts')) { return; }

        $existing_high = get_post_meta($post_id, self::META_HIGH_RISK, true) === '1';
        $high = ($existing_high || !empty($_POST['bsw_p36_high_risk'])) ? '1' : '0';
        update_post_meta($post_id, self::META_HIGH_RISK, $high);
        update_post_meta($post_id, self::META_RISK_ASSESSED, !empty($_POST['bsw_p36_risk_assessed']) ? '1' : '0');
        $checks_in = isset($_POST['bsw_p36_checks']) && is_array($_POST['bsw_p36_checks']) ? array_map('sanitize_key', wp_unslash($_POST['bsw_p36_checks'])) : array();
        $valid_checks = array('claims', 'independence', 'neutrality', 'conflict', 'rights');
        $checks = array_values(array_intersect($valid_checks, $checks_in));
        update_post_meta($post_id, self::META_CHECKS, $checks);
        $source_decision = sanitize_key(wp_unslash($_POST['bsw_p36_source_decision'] ?? 'not_assessed'));
        if (!in_array($source_decision, array('not_assessed', 'adequate', 'insufficient', 'unreliable'), true)) { $source_decision = 'not_assessed'; }
        update_post_meta($post_id, self::META_SOURCE_DECISION, $source_decision);
        if (isset($_POST['bsw_p36_sources_admin'])) {
            update_post_meta($post_id, self::META_SOURCES, self::parse_source_urls(wp_unslash($_POST['bsw_p36_sources_admin'])));
        }
        if (isset($_POST['bsw_p36_notability_admin'])) {
            update_post_meta($post_id, self::META_NOTABILITY, sanitize_textarea_field(wp_unslash($_POST['bsw_p36_notability_admin'])));
        }
        if ($post->post_type === 'wiki_article') {
            $notability = sanitize_key(wp_unslash($_POST['bsw_p36_notability_decision'] ?? 'not_assessed'));
            if (!in_array($notability, array('not_assessed', 'meets', 'more_evidence', 'does_not_meet'), true)) { $notability = 'not_assessed'; }
            update_post_meta($post_id, self::META_NOTABILITY_DECISION, $notability);
        }

        $author = absint($post->post_author);
        $user_id = get_current_user_id();
        if ($user_id === $author) {
            self::audit('self_review_blocked', $post_id, 'Author attempted to review own submission');
            return;
        }
        $r1 = absint(get_post_meta($post_id, self::META_REVIEW1_USER, true));
        $r2 = absint(get_post_meta($post_id, self::META_REVIEW2_USER, true));
        $slot = '';
        if (!$r1 || $r1 === $user_id) { $slot = 'review1'; }
        elseif ($high === '1' && (!$r2 || $r2 === $user_id)) { $slot = 'review2'; }
        if (!$slot) { return; }
        $decision = sanitize_key(wp_unslash($_POST['bsw_p36_review_decision'] ?? 'pending'));
        if (!in_array($decision, array('pending', 'approved', 'changes_requested', 'rejected'), true)) { $decision = 'pending'; }
        $notes = sanitize_textarea_field(wp_unslash($_POST['bsw_p36_review_notes'] ?? ''));
        $now = current_time('mysql');
        if ($slot === 'review1') {
            update_post_meta($post_id, self::META_REVIEW1_USER, $user_id);
            update_post_meta($post_id, self::META_REVIEW1_DECISION, $decision);
            update_post_meta($post_id, self::META_REVIEW1_NOTES, $notes);
            update_post_meta($post_id, self::META_REVIEW1_DATE, $now);
        } else {
            if ($r1 === $user_id) { return; }
            update_post_meta($post_id, self::META_REVIEW2_USER, $user_id);
            update_post_meta($post_id, self::META_REVIEW2_DECISION, $decision);
            update_post_meta($post_id, self::META_REVIEW2_NOTES, $notes);
            update_post_meta($post_id, self::META_REVIEW2_DATE, $now);
        }
        update_post_meta($post_id, '_bsw_p36_workflow', $decision === 'approved' ? 'editorially_approved' : $decision);
        self::audit($slot . '_' . $decision, $post_id, 'Reviewer user ID ' . $user_id);
    }

    private static function publication_requirements_met($post) {
        $sources = get_post_meta($post->ID, self::META_SOURCES, true);
        if (!is_array($sources)) { $sources = array(); }
        if (get_post_meta($post->ID, self::META_RISK_ASSESSED, true) !== '1') { return 'An editor must complete the sensitivity and risk assessment.'; }
        $minimum_sources = $post->post_type === 'wiki_article' ? 2 : 1;
        if (count(array_unique($sources)) < $minimum_sources) { return 'Required source URLs are missing.'; }
        $author = get_user_by('id', absint($post->post_author));
        if ($author && in_array('wiki_contributor', (array) $author->roles, true) && get_post_meta($post->ID, self::META_POLICY_ACCEPTED, true) !== self::POLICY_VERSION) { return 'The contributor policy acceptance record is missing or outdated.'; }
        $checks = get_post_meta($post->ID, self::META_CHECKS, true);
        $required = array('claims', 'independence', 'neutrality', 'conflict', 'rights');
        if (!is_array($checks) || count(array_intersect($required, $checks)) !== count($required)) { return 'Required editorial checks are incomplete.'; }
        if (get_post_meta($post->ID, self::META_SOURCE_DECISION, true) !== 'adequate') { return 'Source assessment must be marked adequate.'; }
        if ($post->post_type === 'wiki_article' && get_post_meta($post->ID, self::META_NOTABILITY_DECISION, true) !== 'meets') { return 'The article has not passed BIGSHOR notability assessment.'; }
        if (get_post_meta($post->ID, self::META_REVIEW1_DECISION, true) !== 'approved' || !absint(get_post_meta($post->ID, self::META_REVIEW1_USER, true))) { return 'A first independent editorial approval is required.'; }
        if (absint(get_post_meta($post->ID, self::META_REVIEW1_USER, true)) === absint($post->post_author)) { return 'The contributor cannot approve their own submission.'; }
        if (get_post_meta($post->ID, self::META_HIGH_RISK, true) === '1') {
            $r1 = absint(get_post_meta($post->ID, self::META_REVIEW1_USER, true));
            $r2 = absint(get_post_meta($post->ID, self::META_REVIEW2_USER, true));
            if (!$r2 || $r2 === $r1 || $r2 === absint($post->post_author) || get_post_meta($post->ID, self::META_REVIEW2_DECISION, true) !== 'approved') {
                return 'A second independent approval from a different editor is required for high-risk content.';
            }
        }
        return '';
    }

    public static function publication_guard($new_status, $old_status, $post) {
        if (!$post instanceof WP_Post || !in_array($post->post_type, array('wiki_article', 'bsw_news'), true) || $new_status !== 'publish' || $old_status === 'publish') { return; }

        // Administrators have full editorial and publishing authority. Do not
        // revert their explicit Publish action to Pending because of workflow gates.
        if (current_user_can('manage_options') && current_user_can('publish_posts')) {
            update_post_meta($post->ID, '_bsw_p36_workflow', 'administrator_published');
            update_post_meta($post->ID, '_bsw_p36_published_at', current_time('mysql'));
            self::audit('administrator_publication_override', $post->ID, 'Administrator published directly; editorial workflow gates bypassed.');
            return;
        }

        $reason = self::publication_requirements_met($post);
        if ($reason !== '') {
            remove_action('transition_post_status', array(__CLASS__, 'publication_guard'), 1);
            wp_update_post(array('ID' => $post->ID, 'post_status' => 'pending'));
            add_action('transition_post_status', array(__CLASS__, 'publication_guard'), 1, 3);
            self::audit('publication_blocked', $post->ID, $reason);
            set_transient('bsw_p36_publish_blocked_' . get_current_user_id(), $reason, 90);
            return;
        }
        update_post_meta($post->ID, '_bsw_p36_workflow', 'published');
        update_post_meta($post->ID, '_bsw_p36_published_at', current_time('mysql'));
        self::audit('publication_approved', $post->ID, 'All editorial gates satisfied');
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=wiki_article', 'BIGSHOR Editorial Governance', 'Editorial Governance', 'edit_others_posts', 'bsw-p36-governance', array(__CLASS__, 'dashboard'));
    }

    public static function dashboard() {
        if (!current_user_can('edit_others_posts')) { return; }
        $types = array('wiki_article' => 'Knowledge', 'bsw_news' => 'News');
        echo '<div class="wrap"><h1>BIGSHOR Editorial Governance</h1><p>Publication is controlled by evidence checks and reviewer approvals. High-risk content requires two different reviewers.</p><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">';
        foreach ($types as $type => $label) {
            $q = new WP_Query(array('post_type' => $type, 'post_status' => array('pending', 'draft', 'publish'), 'posts_per_page' => 1, 'fields' => 'ids'));
            echo '<div style="background:#fff;border:1px solid #dcdcde;padding:16px"><strong>' . esc_html($label) . '</strong><h2>' . absint($q->found_posts) . '</h2><a href="' . esc_url(admin_url('edit.php?post_type=' . $type . '&post_status=pending')) . '">Open review queue</a></div>';
            wp_reset_postdata();
        }
        $legacy_seed_count = self::unreviewed_seed_profile_count();
        if ($legacy_seed_count > 0) {
            $hold_url = wp_nonce_url(admin_url('admin-post.php?action=bsw_p36_hold_seed_profiles'), 'bsw_p36_hold_seed_profiles');
            echo '<div class="notice notice-warning"><p><strong>Legacy starter profiles need review:</strong> ' . absint($legacy_seed_count) . ' founder starter profile(s) are currently published without the new Phase 36 approval record. Review them or <a href="' . esc_url($hold_url) . '">move them to Pending review</a>.</p></div>';
        }
        echo '</div><h2>Recent editorial events</h2><table class="widefat striped"><thead><tr><th>Date</th><th>User</th><th>Event</th><th>Record</th><th>Details</th></tr></thead><tbody>';
        $rows = get_option(self::AUDIT_OPTION, array());
        if (!is_array($rows)) { $rows = array(); }
        foreach (array_slice($rows, 0, 60) as $row) {
            $id = absint($row['post_id'] ?? 0);
            $user = absint($row['user'] ?? 0);
            $record = $id && get_post($id) ? '<a href="' . esc_url(get_edit_post_link($id)) . '">#' . $id . ' ' . esc_html(get_the_title($id)) . '</a>' : '—';
            echo '<tr><td>' . esc_html($row['time'] ?? '') . '</td><td>' . esc_html($user ? get_the_author_meta('display_name', $user) : 'System') . '</td><td>' . esc_html($row['event'] ?? '') . '</td><td>' . $record . '</td><td>' . esc_html($row['details'] ?? '') . '</td></tr>';
        }
        if (!$rows) { echo '<tr><td colspan="5">No editorial events recorded yet.</td></tr>'; }
        echo '</tbody></table><p class="description">The local audit trail is operational evidence, not a tamper-proof compliance ledger. Include it in independent backups and restrict administrator access.</p></div>';
    }

    private static function unreviewed_seed_profile_count() {
        $ids = get_posts(array('post_type' => 'wiki_article', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_bsw_founder_profile', 'meta_value' => '1'));
        $count = 0;
        foreach ($ids as $id) {
            if (get_post_meta($id, self::META_REVIEW1_DECISION, true) !== 'approved' || get_post_meta($id, self::META_NOTABILITY_DECISION, true) !== 'meets') { $count++; }
        }
        return $count;
    }

    public static function hold_unreviewed_seed_profiles() {
        if (!current_user_can('manage_options')) { wp_die('Permission denied.'); }
        check_admin_referer('bsw_p36_hold_seed_profiles');
        $ids = get_posts(array('post_type' => 'wiki_article', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_bsw_founder_profile', 'meta_value' => '1'));
        $held = 0;
        foreach ($ids as $id) {
            if (get_post_meta($id, self::META_REVIEW1_DECISION, true) === 'approved' && get_post_meta($id, self::META_NOTABILITY_DECISION, true) === 'meets') { continue; }
            $result = wp_update_post(array('ID' => absint($id), 'post_status' => 'pending'), true);
            if (!is_wp_error($result)) { $held++; self::audit('legacy_seed_moved_to_pending', $id, 'Held for Phase 36 editorial review'); }
        }
        wp_safe_redirect(add_query_arg('bsw_p36_seed_held', $held, admin_url('edit.php?post_type=wiki_article&page=bsw-p36-governance')));
        exit;
    }

    public static function audit($event, $post_id = 0, $details = '') {
        $rows = get_option(self::AUDIT_OPTION, array());
        if (!is_array($rows)) { $rows = array(); }
        array_unshift($rows, array('time' => current_time('mysql'), 'user' => get_current_user_id(), 'event' => sanitize_key($event), 'post_id' => absint($post_id), 'details' => sanitize_text_field($details)));
        update_option(self::AUDIT_OPTION, array_slice($rows, 0, 500), false);
    }

    public static function admin_notices() {
        if (!is_admin() || !current_user_can('edit_others_posts')) { return; }
        $key = 'bsw_p36_publish_blocked_' . get_current_user_id();
        $reason = get_transient($key);
        if ($reason) {
            delete_transient($key);
            echo '<div class="notice notice-error is-dismissible"><p><strong>BIGSHOR blocked publication:</strong> ' . esc_html($reason) . ' Save the completed review record, then publish again.</p></div>';
        }
        if (isset($_GET['bsw_p36_seed_held'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . absint($_GET['bsw_p36_seed_held']) . ' unreviewed founder starter profile(s) moved to Pending review.</p></div>';
        }
    }

    public static function news_columns($columns) {
        $columns['bsw_p36_review'] = 'Editorial review';
        return $columns;
    }

    public static function news_column_value($column, $post_id) {
        if ($column === 'bsw_p36_review') {
            $r1 = get_post_meta($post_id, self::META_REVIEW1_DECISION, true) ?: 'pending';
            $r2 = get_post_meta($post_id, self::META_REVIEW2_DECISION, true);
            echo esc_html('R1: ' . $r1 . (get_post_meta($post_id, self::META_HIGH_RISK, true) === '1' ? ' · R2: ' . ($r2 ?: 'pending') : ''));
        }
    }
}
BSW_Phase36_Editorial_System::init();
}
