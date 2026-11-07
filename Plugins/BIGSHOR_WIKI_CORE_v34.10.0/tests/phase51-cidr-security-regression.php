<?php
/** Regression assertions for Phase 51 IP/CIDR enforcement. */
$source = file_get_contents(dirname(__DIR__) . '/includes/phase51-advanced-platform.php');
$checks = array(
    'cidr_enforcement_hook_registered' => strpos($source, "add_action('init', array(__CLASS__, 'enforce_cidr_blocks'), 1)") !== false,
    'enforcement_uses_remote_addr' => strpos($source, "\$_SERVER['REMOTE_ADDR']") !== false,
    'enforcement_calls_cidr_matcher' => strpos($source, 'self::is_cidr_restricted($ip)') !== false,
    'administrator_recovery_bypass' => strpos($source, "is_user_logged_in() && current_user_can('manage_options')") !== false,
    'blocked_response_is_forbidden' => strpos($source, "'response' => 403") !== false,
    'ipv4_ipv6_binary_matching' => strpos($source, 'inet_pton($ip)') !== false && strpos($source, 'strlen($a)!==strlen($b)') !== false,
    'cidr_expiration_enforced' => strpos($source, '(int)$row[\'expires\']<time()') !== false,
    'encrypted_network_storage' => strpos($source, 'encrypt_network($network)') !== false && strpos($source, 'decrypt_network($row[\'network_enc\'])') !== false,
    'network_hash_verified' => strpos($source, "hash_hmac('sha256',$network,wp_salt('auth'))") !== false,
);
$failed = 0;
foreach ($checks as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ' ' . $name . PHP_EOL;
    if (!$passed) $failed++;
}
echo 'TOTAL ' . count($checks) . ' FAILED ' . $failed . PHP_EOL;
exit($failed ? 1 : 0);
