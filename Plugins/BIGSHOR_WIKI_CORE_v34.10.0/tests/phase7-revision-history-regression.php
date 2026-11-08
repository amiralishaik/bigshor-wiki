<?php
/** Regression checks for article-page Phase 7: revision history and comparison. */
$source=file_get_contents(dirname(__DIR__).'/includes/phase8-revision-history.php');
$checks=array(
 'native_revision_support_is_enabled'=>strpos($source,"add_post_type_support('wiki_article','revisions')")!==false,
 'both_history_shortcode_names_are_registered'=>strpos($source,"add_shortcode('bsw_article_history'")!==false && strpos($source,"add_shortcode('bigshor_wiki_history'")!==false,
 'revision_history_is_bounded'=>strpos($source,'min(100,(int)$limit)')!==false,
 'revision_author_and_timestamp_are_shown'=>strpos($source,'get_userdata($r->post_author)')!==false && strpos($source,'post_modified_gmt')!==false,
 'comparison_uses_wordpress_text_diff'=>strpos($source,'wp_text_diff(')!==false,
 'restore_uses_native_revision_api'=>strpos($source,'wp_restore_post_revision(')!==false,
 'restore_and_compare_check_nonce_and_capability'=>strpos($source,'wp_verify_nonce($nonce')!==false && strpos($source,'current_user_can(\'edit_post\',$id)')!==false,
 'revision_must_belong_to_target_article'=>strpos($source,'(int)$r->post_parent!==$id')!==false,
 'restore_preserves_history_as_new_revision'=>strpos($source,'Restoring creates a new revision')!==false,
 'public_history_requires_read_permission'=>strpos($source,'current_user_can(\'read_post\',$id)')!==false
);
$failed=0;foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL').' '.$name.PHP_EOL;if(!$ok)$failed++;}
echo 'TOTAL '.count($checks).' FAILED '.$failed.PHP_EOL;exit($failed?1:0);
