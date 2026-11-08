<?php
/** Regression checks for article-page Phase 8 community participation. */
$source=file_get_contents(dirname(__DIR__).'/includes/phase8-article-community.php');
$checks=array(
 'watchlist_toggle_is_registered'=>strpos($source,"admin_post_bsw_p8_watch")!==false,
 'watchlist_uses_user_meta_and_nonce'=>strpos($source,"'_bsw_watchlist'")!==false&&strpos($source,'check_admin_referer(\'bsw_p8_watch_\'.$id)')!==false,
 'watchlist_is_bounded'=>strpos($source,'count($ids)>=1000')!==false,
 'discussion_topic_submission_is_authenticated'=>strpos($source,"admin_post_bsw_p8_talk")!==false&&strpos($source,'is_user_logged_in()')!==false,
 'discussion_submission_uses_nonce_and_honeypot'=>strpos($source,'check_admin_referer(\'bsw_p8_talk_\'.$id)')!==false&&strpos($source,'!empty($_POST[\\'website\\'])')!==false,
 'discussion_topic_is_bound_to_article'=>strpos($source,"'_bsw_talk_article'")!==false,
 'replies_use_wordpress_comment_system'=>strpos($source,'get_comment_form(')!==false&&strpos($source,'get_comments(')!==false,
 'community_ui_is_appended_to_article'=>strpos($source,'append_community')!==false&&strpos($source,'bsw-article-discussion')!==false
);
$failed=0;foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL').' '.$name.PHP_EOL;if(!$ok)$failed++;}
echo 'TOTAL '.count($checks).' FAILED '.$failed.PHP_EOL;exit($failed?1:0);
