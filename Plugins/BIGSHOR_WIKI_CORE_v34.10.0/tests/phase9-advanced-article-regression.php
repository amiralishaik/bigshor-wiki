<?php
/** Regression checks for article-page Phase 9 advanced article features. */
$source=file_get_contents(dirname(__DIR__).'/includes/phase9-advanced-article.php');
$checks=array(
 'advanced_article_metabox_is_registered'=>strpos($source,'add_meta_box(')!==false,
 'metadata_save_checks_nonce_and_capability'=>strpos($source,'wp_verify_nonce(')!==false&&strpos($source,'current_user_can')!==false&&strpos($source,'edit_post')!==false,
 'alternative_names_are_bounded'=>strpos($source,'array_slice(array_values(array_unique($aliases)),0,30)')!==false,
 'external_links_require_valid_http_urls'=>strpos($source,'wp_http_validate_url($parts[1])')!==false&&strpos($source,"array('http','https')")!==false,
 'page_information_includes_word_count_and_reading_time'=>strpos($source,'number_format_i18n($words)')!==false&&strpos($source,'$minutes')!==false,
 'page_information_includes_author_and_timestamps'=>strpos($source,'get_userdata($post->post_author)')!==false&&strpos($source,'get_post_modified_time(')!==false,
 'citation_exports_include_plain_and_bibtex'=>strpos($source,"'plain'")!==false&&strpos($source,"'bibtex'")!==false,
 'copy_controls_have_fallback'=>strpos($source,'navigator.clipboard.writeText')!==false&&strpos($source,'window.prompt')!==false,
 'shortcode_is_registered'=>strpos($source,"add_shortcode('bsw_article_information'")!==false
);
$failed=0;foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL').' '.$name.PHP_EOL;if(!$ok)$failed++;}
echo 'TOTAL '.count($checks).' FAILED '.$failed.PHP_EOL;exit($failed?1:0);
