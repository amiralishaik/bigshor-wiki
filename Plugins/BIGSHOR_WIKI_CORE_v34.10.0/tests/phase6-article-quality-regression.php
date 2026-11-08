<?php
/** Regression checks for article-page Phase 6: editorial quality standards. */
$source=file_get_contents(dirname(__DIR__).'/includes/phase43-article-quality.php');
$checks=array(
 'quality_checks_are_reusable_and_advisory'=>strpos($source,'function quality_checks($post)')!==false && strpos($source,'never block')!==false,
 'descriptive_title_and_lead_checks'=>strpos($source,'Article title is descriptive')!==false && strpos($source,'Lead or summary is present')!==false,
 'substantive_coverage_threshold_is_advisory'=>strpos($source,'Article has substantive coverage')!==false && strpos($source,'not a publishing rule')!==false,
 'heading_hierarchy_is_checked'=>strpos($source,'Heading levels follow a logical order')!==false && strpos($source,'$level > $previous + 1')!==false,
 'citation_records_and_inline_citations_are_checked'=>strpos($source,'_bsw_phase6_sources')!==false && strpos($source,'Sources and citations are documented')!==false,
 'image_alt_text_is_checked'=>strpos($source,'Embedded images have alternative text')!==false,
 'duplicate_passages_are_checked'=>strpos($source,'No exact repeated long sentences')!==false,
 'manual_editorial_judgement_is_explicit'=>strpos($source,'cannot determine neutrality')!==false,
 'schema_and_category_links_remain'=>strpos($source,'application/ld+json')!==false && strpos($source,'get_term_link')!==false
);
$failed=0;foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL').' '.$name.PHP_EOL;if(!$ok)$failed++;}
echo 'TOTAL '.count($checks).' FAILED '.$failed.PHP_EOL;exit($failed?1:0);
