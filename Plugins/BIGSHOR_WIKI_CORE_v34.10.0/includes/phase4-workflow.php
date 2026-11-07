<?php
/**
 * BIGSHOR Wiki Phase 4 — translation editorial workflow and QA inventory.
 * Human-reviewed workflow only: no automatic translation or automatic publication.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase4_Workflow')) {
final class BSW_Phase4_Workflow {
    const META_STATUS = '_bsw_p4_translation_review_status';
    const META_REVIEWER = '_bsw_p4_translation_reviewer';
    const META_SOURCE = '_bsw_p4_translation_source';
    const META_NOTES = '_bsw_p4_translation_review_notes';
    const META_REVIEWED = '_bsw_p4_translation_reviewed_at';

    public static function boot() {
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'add_box'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save_box'), 50, 2);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 36);
        add_filter('manage_wiki_article_posts_columns', array(__CLASS__, 'add_column'));
        add_action('manage_wiki_article_posts_custom_column', array(__CLASS__, 'render_column'), 10, 2);
    }

    public static function add_box($post) {
        add_meta_box('bsw-p4-translation-review', 'BIGSHOR Translation Review', array(__CLASS__, 'box_html'), 'wiki_article', 'side', 'default');
    }

    public static function box_html($post) {
        if (!current_user_can('edit_post', $post->ID)) return;
        wp_nonce_field('bsw_p4_workflow_' . $post->ID, 'bsw_p4_workflow_nonce');
        $status = get_post_meta($post->ID, self::META_STATUS, true);
        if (!$status) $status = 'not_started';
        $reviewer = absint(get_post_meta($post->ID, self::META_REVIEWER, true));
        $source = absint(get_post_meta($post->ID, self::META_SOURCE, true));
        $notes = get_post_meta($post->ID, self::META_NOTES, true);
        $reviewed = get_post_meta($post->ID, self::META_REVIEWED, true);
        $assigned_reviewer = absint(get_post_meta($post->ID, self::META_REVIEWER, true));
        $can_approve = current_user_can('manage_options') || current_user_can('edit_others_posts') || ($assigned_reviewer && $assigned_reviewer === get_current_user_id());
        echo '<p><label for="bsw-p4-review-status"><strong>Editorial status</strong></label><select class="widefat" id="bsw-p4-review-status" name="bsw_p4_review_status">';
        $states = array('not_started'=>'Not started','in_progress'=>'In progress','in_review'=>'Submitted for review','changes_requested'=>'Changes requested','approved'=>'Approved');
        foreach ($states as $key=>$label) {
            if ($key === 'approved' && !$can_approve) continue;
            echo '<option value="'.esc_attr($key).'" '.selected($status,$key,false).'>'.esc_html($label).'</option>';
        }
        echo '</select></p>';
        $users = get_users(array('capability'=>'edit_posts','orderby'=>'display_name','order'=>'ASC','number'=>200,'fields'=>array('ID','display_name')));
        echo '<p><label for="bsw-p4-reviewer"><strong>Assigned reviewer</strong></label><select class="widefat" id="bsw-p4-reviewer" name="bsw_p4_reviewer"><option value="0">— Unassigned —</option>';
        foreach ($users as $user) echo '<option value="'.absint($user->ID).'" '.selected($reviewer,(int)$user->ID,false).'>'.esc_html($user->display_name).'</option>';
        echo '</select></p>';
        echo '<p><label for="bsw-p4-source"><strong>Source/original article ID</strong></label><input class="widefat" type="number" min="0" id="bsw-p4-source" name="bsw_p4_source" value="'.esc_attr($source).'" placeholder="Optional article ID"></p>';
        echo '<p><label for="bsw-p4-review-notes"><strong>Reviewer notes</strong></label><textarea class="widefat" rows="4" id="bsw-p4-review-notes" name="bsw_p4_review_notes">'.esc_textarea($notes).'</textarea></p>';
        if ($reviewed) echo '<p class="description">Last marked approved: '.esc_html($reviewed).'</p>';
        echo '<p class="description">Approval records editorial review only. It does not verify factual accuracy automatically or publish the article.</p>';
    }

    public static function save_box($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || !$post || $post->post_type !== 'wiki_article') return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (empty($_POST['bsw_p4_workflow_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p4_workflow_nonce'])), 'bsw_p4_workflow_' . $post_id)) return;
        $allowed = array('not_started','in_progress','in_review','changes_requested','approved');
        $status = sanitize_key(wp_unslash($_POST['bsw_p4_review_status'] ?? 'not_started'));
        if (!in_array($status, $allowed, true)) $status = 'not_started';
        $previous_reviewer = absint(get_post_meta($post_id, self::META_REVIEWER, true));
        $can_approve = current_user_can('manage_options') || current_user_can('edit_others_posts') || ($previous_reviewer && $previous_reviewer === get_current_user_id());
        $old_status = get_post_meta($post_id, self::META_STATUS, true);
        if ($status === 'approved' && !$can_approve) $status = ($old_status === 'approved') ? 'approved' : 'in_review';
        update_post_meta($post_id, self::META_STATUS, $status);

        // Only editorial managers may assign or reassign reviewers. Assigned reviewers
        // can update their review status and notes without changing the assignment.
        $reviewer = $previous_reviewer;
        if (current_user_can('manage_options') || current_user_can('edit_others_posts')) {
            $reviewer = absint($_POST['bsw_p4_reviewer'] ?? 0);
            if ($reviewer && !user_can($reviewer, 'edit_posts')) $reviewer = 0;
        }
        update_post_meta($post_id, self::META_REVIEWER, $reviewer);
        if ($reviewer && $reviewer !== $previous_reviewer) {
            $assigned_user = get_userdata($reviewer);
            if ($assigned_user && is_email($assigned_user->user_email)) {
                $subject = sprintf('[%s] Translation review assigned', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
                $message = "A wiki translation has been assigned to you for editorial review.\n\nArticle: " . get_the_title($post_id) . "\nReview status: " . $status . "\nEdit article: " . get_edit_post_link($post_id, 'url') . "\n\nPlease verify language quality, facts, citations, accessibility and RTL presentation where applicable.";
                wp_mail($assigned_user->user_email, $subject, $message);
            }
        }

        $source = absint($_POST['bsw_p4_source'] ?? 0);
        if ($source && (get_post_type($source) !== 'wiki_article' || $source === (int)$post_id)) $source = 0;
        update_post_meta($post_id, self::META_SOURCE, $source);
        update_post_meta($post_id, self::META_NOTES, sanitize_textarea_field(wp_unslash($_POST['bsw_p4_review_notes'] ?? '')));
        if ($status === 'approved' && $old_status !== 'approved') update_post_meta($post_id, self::META_REVIEWED, current_time('mysql'));
        if ($status !== 'approved' && $old_status === 'approved') delete_post_meta($post_id, self::META_REVIEWED);
    }

    public static function add_column($columns) {
        $columns['bsw_translation_review'] = 'Translation review';
        return $columns;
    }

    public static function render_column($column, $post_id) {
        if ($column !== 'bsw_translation_review') return;
        $status = get_post_meta($post_id, self::META_STATUS, true);
        $labels = array('not_started'=>'Not started','in_progress'=>'In progress','in_review'=>'In review','changes_requested'=>'Changes requested','approved'=>'Approved');
        echo esc_html($labels[$status] ?? 'Not started');
        $reviewer = absint(get_post_meta($post_id, self::META_REVIEWER, true));
        if ($reviewer) { $u = get_userdata($reviewer); if ($u) echo '<br><small>Reviewer: '.esc_html($u->display_name).'</small>'; }
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=wiki_article', 'Translation Editorial Workflow', 'Translation Workflow', 'edit_posts', 'bsw-translation-workflow', array(__CLASS__, 'page'));
    }

    public static function page() {
        if (!current_user_can('edit_posts')) return;
        $status_filter = sanitize_key(wp_unslash($_GET['review_status'] ?? ''));
        $valid = array('not_started','in_progress','in_review','changes_requested','approved');
        $args = array('post_type'=>'wiki_article','post_status'=>array('publish','draft','pending','private'),'posts_per_page'=>50,'orderby'=>'modified','order'=>'DESC','no_found_rows'=>true);
        if (in_array($status_filter, $valid, true)) $args['meta_query'] = array(array('key'=>self::META_STATUS,'value'=>$status_filter));
        if (!current_user_can('edit_others_posts') && !current_user_can('manage_options')) { $args['meta_query'] = array('relation'=>'AND', array('key'=>self::META_REVIEWER,'value'=>get_current_user_id(),'compare'=>'=')); if (in_array($status_filter, $valid, true)) $args['meta_query'][] = array('key'=>self::META_STATUS,'value'=>$status_filter); }
        $q = new WP_Query($args);
        $counts = array_fill_keys($valid, 0);
        $all_args = array('post_type'=>'wiki_article','post_status'=>array('publish','draft','pending','private'),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true);
        if (!current_user_can('edit_others_posts') && !current_user_can('manage_options')) $all_args['meta_query'] = array(array('key'=>self::META_REVIEWER,'value'=>get_current_user_id(),'compare'=>'='));
        $all = new WP_Query($all_args);
        foreach ($all->posts as $id) { $s=get_post_meta($id,self::META_STATUS,true); if(isset($counts[$s]))$counts[$s]++; else $counts['not_started']++; }
        echo '<div class="wrap"><h1>BIGSHOR Wiki — Translation Editorial Workflow</h1>';
        echo '<p>Track translation preparation, reviewer assignment and human approval. This workflow is advisory and does not automatically publish or certify translations.</p>';
        echo '<div style="display:flex;flex-wrap:wrap;gap:12px;margin:16px 0">';
        foreach ($counts as $key=>$count) echo '<div class="card" style="min-width:130px;margin:0;padding:12px"><strong>'.esc_html(ucwords(str_replace('_',' ',$key))).'</strong><br><span style="font-size:22px">'.number_format_i18n($count).'</span></div>';
        echo '</div><form method="get"><input type="hidden" name="post_type" value="wiki_article"><input type="hidden" name="page" value="bsw-translation-workflow"><label for="bsw-review-filter">Filter status </label><select name="review_status" id="bsw-review-filter"><option value="">All statuses</option>';
        foreach ($valid as $key) echo '<option value="'.esc_attr($key).'" '.selected($status_filter,$key,false).'>'.esc_html(ucwords(str_replace('_',' ',$key))).'</option>';
        echo '</select> <button class="button">Apply</button></form>';
        echo '<table class="widefat striped" style="margin-top:12px"><thead><tr><th>Article</th><th>Language</th><th>Review status</th><th>Assigned reviewer</th><th>Source article</th><th>Last approval</th></tr></thead><tbody>';
        if ($q->have_posts()) { while($q->have_posts()) { $q->the_post(); $id=get_the_ID(); $status=get_post_meta($id,self::META_STATUS,true); $labels=array('not_started'=>'Not started','in_progress'=>'In progress','in_review'=>'In review','changes_requested'=>'Changes requested','approved'=>'Approved'); $terms=wp_get_post_terms($id,'wiki_language'); $lang=(!is_wp_error($terms)&&$terms)?$terms[0]->name:'Not assigned'; $rid=absint(get_post_meta($id,self::META_REVIEWER,true)); $ru=$rid?get_userdata($rid):false; $sid=absint(get_post_meta($id,self::META_SOURCE,true)); $source=$sid?get_the_title($sid):''; $edit=get_edit_post_link($id,'');
            echo '<tr><td><a href="'.esc_url($edit).'"><strong>'.esc_html(get_the_title()).'</strong></a><br><small>#'.absint($id).' · '.esc_html(get_post_status($id)).'</small></td><td>'.esc_html($lang).'</td><td>'.esc_html($labels[$status]??'Not started').'</td><td>'.esc_html($ru?$ru->display_name:'Unassigned').'</td><td>'.($source?esc_html($source).' (#'.absint($sid).')':'—').'</td><td>'.esc_html(get_post_meta($id,self::META_REVIEWED,true)?:'—').'</td></tr>';
        } wp_reset_postdata(); } else echo '<tr><td colspan="6">No articles found for this filter.</td></tr>';
        echo '</tbody></table><h2>Required reviewer checks</h2><ol><li>Confirm the translated page describes the same subject and scope as the source.</li><li>Check names, dates, numbers, quotations and terminology against reliable sources.</li><li>Verify citations independently; do not assume source citations transfer automatically.</li><li>Check language quality, local conventions, accessibility and RTL rendering where applicable.</li><li>Confirm language taxonomy, translation group and reciprocal language links.</li><li>Only mark approved after the reviewer has completed these checks.</li></ol>';
        echo '<p><strong>URL policy:</strong> Existing article permalinks are preserved. This release does not silently migrate URLs into language prefixes; any future URL redesign must include redirects, canonicals and Search Console validation.</p></div>';
    }
}
BSW_Phase4_Workflow::boot();
}
