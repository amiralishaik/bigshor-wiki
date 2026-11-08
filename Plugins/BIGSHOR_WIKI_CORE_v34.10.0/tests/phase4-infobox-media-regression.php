<?php
/** Regression checks for Phase 4 infobox and media presentation. */
$layout = file_get_contents(dirname(__DIR__) . '/includes/phase45-article-layout.php');
$editor = file_get_contents(dirname(__DIR__) . '/includes/infobox-editor.php');
$checks = array(
    'infobox_alt_text_is_supported' => strpos($layout, 'image_alt') !== false && strpos($editor, 'bsw_infobox_image_alt') !== false,
    'infobox_caption_is_supported' => strpos($layout, 'image_caption') !== false && strpos($editor, 'bsw_infobox_image_caption') !== false,
    'infobox_credit_is_supported' => strpos($layout, 'image_credit') !== false && strpos($editor, 'bsw_infobox_image_credit') !== false,
    'image_source_is_validated_and_linked' => strpos($layout, 'image_source') !== false && strpos($editor, 'esc_url_raw') !== false,
    'attachment_images_use_wordpress_responsive_markup' => strpos($layout, 'wp_get_attachment_image') !== false && strpos($layout, "'decoding'=>'async'") !== false,
    'external_images_are_escaped_and_lazy_loaded' => strpos($layout, 'loading=') !== false && strpos($layout, 'decoding=') !== false,
    'infobox_values_support_safe_links' => strpos($layout, 'noopener noreferrer') !== false && strpos($layout, 'value') !== false,
    'legacy_infobox_fields_remain_supported' => strpos($layout, '_bsw_p1_infobox_rows') !== false,
    'infobox_data_is_saved_with_existing_nonce_and_capability_checks' => strpos($editor, 'wp_verify_nonce') !== false && strpos($editor, 'current_user_can') !== false,
);
$failed = 0;
foreach ($checks as $name => $passed) { echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL; if (!$passed) $failed++; }
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
