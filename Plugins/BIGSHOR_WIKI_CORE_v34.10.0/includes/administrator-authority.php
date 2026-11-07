<?php
/**
 * BIGSHOR Administrator Authority
 * Grants users with the WordPress Administrator role full WordPress
 * capability authority, including BIGSHOR custom post types and modules.
 * Editorial workflow restrictions must never block an Administrator.
 */
if (!defined('ABSPATH')) exit;

/**
 * Identify the actual Administrator role (not merely a display name).
 */
function bsw_is_full_administrator($user_id = 0) {
    $user_id = $user_id ? absint($user_id) : get_current_user_id();
    if (!$user_id) return false;
    $user = get_userdata($user_id);
    return ($user instanceof WP_User) && in_array('administrator', (array) $user->roles, true);
}

/**
 * WordPress routes capability checks through map_meta_cap(). Returning the
 * primitive 'exist' capability for Administrators grants access to standard
 * and custom capabilities, including custom BIGSHOR post-type permissions.
 * Nonces, authentication, sanitisation and explicit data-integrity checks
 * remain in force; this filter does not bypass those security mechanisms.
 */
function bsw_administrator_capability_override($caps, $cap, $user_id, $args) {
    if (bsw_is_full_administrator($user_id)) {
        return array('exist');
    }
    return $caps;
}
add_filter('map_meta_cap', 'bsw_administrator_capability_override', PHP_INT_MAX, 4);

/**
 * Explicit helper for BIGSHOR workflow modules. Use this before applying
 * contributor/editor approval gates or editorial status restrictions.
 */
function bsw_administrator_can_bypass_editorial_workflow($user_id = 0) {
    return bsw_is_full_administrator($user_id);
}
