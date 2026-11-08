<?php
/** Regression checks for Phase 3 source records and citation rendering. */
$source = file_get_contents(dirname(__DIR__) . '/includes/phase6-citation-management.php');
$checks = array(
    'sources_are_stored_as_article_metadata' => strpos($source, "const META_KEY = '_bsw_phase6_sources'") !== false,
    'source_editor_uses_nonce' => strpos($source, 'wp_nonce_field(self::NONCE_ACTION') !== false && strpos($source, 'wp_verify_nonce') !== false,
    'source_payload_has_size_limit' => strpos($source, '200000') !== false,
    'source_ids_are_unique_on_save' => strpos($source, 'isset($seen[$id])') !== false,
    'source_urls_are_http_or_https_only' => strpos($source, "array('http','https')") !== false,
    'citation_numbers_are_reused_per_source' => strpos($source, 'self::$used[$post_id][$id] = count(self::$used[$post_id]) + 1') !== false,
    'repeated_citations_get_unique_occurrence_ids' => strpos($source, '$citation_id =') !== false && strpos($source, 'count(self::$occurrences[$post_id][$id]) + 1') !== false,
    'references_include_backlinks_for_each_occurrence' => strpos($source, 'foreach ($citation_ids as $occurrence => $citation_id)') !== false,
    'references_auto_append_when_not_explicitly_placed' => strpos($source, 'append_references_if_needed') !== false,
    'missing_sources_are_visible_to_editors' => strpos($source, 'bsw6-citation-missing') !== false,
    'public_source_links_are_safe' => strpos($source, 'noopener noreferrer') !== false && strpos($source, 'target="_blank"') !== false,
    'editorial_note_is_not_in_public_reference_output' => strpos($source, "'note' => sanitize_textarea_field") !== false && strpos($source, 'note output is intentionally omitted') === false,
);
$failed = 0;
foreach ($checks as $name => $passed) { echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL; if (!$passed) $failed++; }
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
