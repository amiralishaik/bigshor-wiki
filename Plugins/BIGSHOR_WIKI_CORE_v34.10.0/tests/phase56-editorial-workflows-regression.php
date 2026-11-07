<?php
/** Static regression assertions for Phase 56 editorial workflows. */
$source = file_get_contents(dirname(__DIR__) . '/includes/phase56-editorial-workflows.php');
$checks = array(
    'workflow_authorization_helper' => strpos($source, 'function can_manage_workflow()') !== false,
    'self_adjudication_prevented' => strpos($source, 'A different editor must resolve your deletion proposal.') !== false,
    'administrator_self_adjudication_override' => strpos($source, "!current_user_can('manage_options')") !== false,
    'resolution_note_required' => strpos($source, 'strlen($note) < 10') !== false && strpos($source, 'strlen($note) > 2000') !== false,
    'merge_preserves_source_content' => strpos($source, 'must not overwrite encyclopaedia content') !== false && strpos($source, "wp_update_post(array('ID'=>$article_id,'post_content'") === false,
    'merge_records_destination' => strpos($source, "_bsw56_merged_into") !== false,
    'trash_restore_remains_available' => strpos($source, 'wp_untrash_post($id)') !== false,
    'resolve_nonce_check_retained' => strpos($source, "check_admin_referer('bsw56_resolve_'") !== false,
    'restore_nonce_check_retained' => strpos($source, "check_admin_referer('bsw56_restore_'") !== false,
);
$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
    if (!$passed) $failed++;
}
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
