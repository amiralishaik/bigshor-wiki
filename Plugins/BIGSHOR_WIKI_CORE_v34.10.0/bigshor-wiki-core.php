<?php
/*
Plugin Name: BIGSHOR Wiki Core
Description: Independent content and community engine for BIGSHOR Wiki. Keeps wiki data and workflows active independently of the theme.
Version: 34.11.0
Author: BIGSHOR
Requires at least: 6.4
Requires PHP: 7.4
License: GPL-2.0-or-later
*/
if (!defined('ABSPATH')) exit;
if (!defined('BSW_CORE_PLUGIN_ACTIVE')) define('BSW_CORE_PLUGIN_ACTIVE', true);
if (!defined('BSW_CORE_PLUGIN_VERSION')) define('BSW_CORE_PLUGIN_VERSION', '34.11.0');
$bsw_core_dir = plugin_dir_path(__FILE__) . 'includes/';

/*
 * During plugin activation WordPress can load the theme before including this
 * plugin file. The theme's legacy fallback may therefore already have declared
 * the engine functions. Reuse it for this activation request instead of
 * redeclaring them. On normal requests the plugin loads first and the parent
 * theme skips its fallback because BSW_CORE_PLUGIN_ACTIVE is already defined.
 */
$bsw_legacy_engine_loaded = function_exists('bsw_v3_register_types') || function_exists('bsw_get_settings');
if (!$bsw_legacy_engine_loaded) {
    foreach (array('core.php','shortcodes.php','community.php','media.php','discovery.php','enhancements.php','advanced-platform.php','admin-auth.php','founders.php','complete-features.php','v13-intelligence.php') as $bsw_file) {
        $bsw_path = $bsw_core_dir . $bsw_file;
        if (is_readable($bsw_path)) require_once $bsw_path;
    }
}
$bsw_phase1_path = $bsw_core_dir . 'phase1-foundation.php';
if (is_readable($bsw_phase1_path)) require_once $bsw_phase1_path;
$bsw_phase2_path = $bsw_core_dir . 'phase2-community.php';
if (is_readable($bsw_phase2_path)) require_once $bsw_phase2_path;
$bsw_phase2_extended_path = $bsw_core_dir . 'phase2-extended.php';
if (is_readable($bsw_phase2_extended_path)) require_once $bsw_phase2_extended_path;
$bsw_phase3_path = $bsw_core_dir . 'phase3-knowledge-commons.php';
if (is_readable($bsw_phase3_path)) require_once $bsw_phase3_path;
$bsw_phase3_extended_path = $bsw_core_dir . 'phase3-extended.php';
if (is_readable($bsw_phase3_extended_path)) require_once $bsw_phase3_extended_path;
$bsw_phase3_completion_path = $bsw_core_dir . 'phase3-completion.php';
if (is_readable($bsw_phase3_completion_path)) require_once $bsw_phase3_completion_path;
$bsw_phase4_path = $bsw_core_dir . 'phase4-discovery.php';
if (is_readable($bsw_phase4_path)) require_once $bsw_phase4_path;
if (function_exists('bsw_v3_register_types')) add_action('init', 'bsw_v3_register_types');
register_activation_hook(__FILE__, function () {
    if (function_exists('bsw_v3_activate')) bsw_v3_activate();
    if (function_exists('bsw_v3_register_types')) bsw_v3_register_types();
    update_option('bsw_core_schema_version', BSW_CORE_PLUGIN_VERSION, false);
    if (class_exists('BSW_Phase7_Global_Network')) BSW_Phase7_Global_Network::activation();
    if (class_exists('BSW_Phase6_Ecosystem')) BSW_Phase6_Ecosystem::activation();
    if (class_exists('BSW_Phase4_Completion')) BSW_Phase4_Completion::activation();
    if (class_exists('BSW_Phase10_Editorial_Intelligence')) BSW_Phase10_Editorial_Intelligence::activate();
    if (class_exists('BSW_Phases_11_15')) BSW_Phases_11_15::activate();
    if (class_exists('BSW_Phases_21_34')) BSW_Phases_21_34::activate();
});
register_deactivation_hook(__FILE__, function () {
    if (function_exists('bsw_v3_deactivate')) bsw_v3_deactivate();
});

$bsw_phase4_extended_path = $bsw_core_dir . 'phase4-extended.php';
if (is_readable($bsw_phase4_extended_path)) require_once $bsw_phase4_extended_path;

$bsw_phase4_completion_path = $bsw_core_dir . 'phase4-completion.php';
if (is_readable($bsw_phase4_completion_path)) require_once $bsw_phase4_completion_path;

$bsw_phase4_workflow_path = $bsw_core_dir . 'phase4-workflow.php';
if (is_readable($bsw_phase4_workflow_path)) require_once $bsw_phase4_workflow_path;

$bsw_phase5_path = $bsw_core_dir . 'phase5-intelligence.php';
if (is_readable($bsw_phase5_path)) require_once $bsw_phase5_path;

$bsw_phase5_assistance_path = $bsw_core_dir . 'phase5-assistance.php';
if (is_readable($bsw_phase5_assistance_path)) require_once $bsw_phase5_assistance_path;

$bsw_phase5_completion_path = $bsw_core_dir . 'phase5-completion.php';
if (is_readable($bsw_phase5_completion_path)) require_once $bsw_phase5_completion_path;

$bsw_phase5_finalisation_path = $bsw_core_dir . 'phase5-finalisation.php';
if (is_readable($bsw_phase5_finalisation_path)) require_once $bsw_phase5_finalisation_path;

$bsw_phase6_path = $bsw_core_dir . 'phase6-ecosystem.php';
if (is_readable($bsw_phase6_path)) require_once $bsw_phase6_path;

$bsw_phase7_path = $bsw_core_dir . 'phase7-global-network.php';
if (is_readable($bsw_phase7_path)) require_once $bsw_phase7_path;

$bsw_phase8_path = $bsw_core_dir . 'phase8-discovery.php';
if (is_readable($bsw_phase8_path)) require_once $bsw_phase8_path;

$bsw_phase9_path = $bsw_core_dir . 'phase9-editorial-governance.php';
if (is_readable($bsw_phase9_path)) require_once $bsw_phase9_path;

$bsw_phase10_path = $bsw_core_dir . 'phase10-editorial-intelligence.php';
if (is_readable($bsw_phase10_path)) require_once $bsw_phase10_path;

$bsw_phases_11_15_path = $bsw_core_dir . 'phase11-15-suite.php';
if (is_readable($bsw_phases_11_15_path)) require_once $bsw_phases_11_15_path;

$bsw_phases_11_15_platform_path = $bsw_core_dir . 'phase11-15-platform.php';
if (is_readable($bsw_phases_11_15_platform_path)) require_once $bsw_phases_11_15_platform_path;

$bsw_phases_16_20_path = $bsw_core_dir . 'phase16-20-suite.php';
if (is_readable($bsw_phases_16_20_path)) require_once $bsw_phases_16_20_path;

$bsw_phases_21_34_path = $bsw_core_dir . 'phase21-34-suite.php';
if (is_readable($bsw_phases_21_34_path)) require_once $bsw_phases_21_34_path;

$bsw_bks_path = $bsw_core_dir . 'knowledge-standard.php';
if (is_readable($bsw_bks_path)) require_once $bsw_bks_path;

$bsw_wikipedia_importer_path = $bsw_core_dir . 'wikipedia-importer.php';
if (is_readable($bsw_wikipedia_importer_path)) require_once $bsw_wikipedia_importer_path;

$bsw_phase35_path = $bsw_core_dir . 'phase35-governance-trust.php';
if (is_readable($bsw_phase35_path)) require_once $bsw_phase35_path;

$bsw_reader_library_path = $bsw_core_dir . 'reader-library.php';
if (is_readable($bsw_reader_library_path)) require_once $bsw_reader_library_path;

$bsw_phase36_path = $bsw_core_dir . 'phase36-editorial-system.php';
if (is_readable($bsw_phase36_path)) require_once $bsw_phase36_path;

$bsw_phase37_path = $bsw_core_dir . 'phase37-theme-integration.php';
if (is_readable($bsw_phase37_path)) require_once $bsw_phase37_path;

$bsw_phase38_path = $bsw_core_dir . 'phase38-reader-experience.php';
if (is_readable($bsw_phase38_path)) require_once $bsw_phase38_path;
$bsw_phase39_path = $bsw_core_dir . 'phase39-platform-completion.php';
if (is_readable($bsw_phase39_path)) require_once $bsw_phase39_path;

// Additive release marker; Phase 37 introduces no destructive schema migration.
add_action('admin_init', function () {
    if (get_option('bsw_core_schema_version') !== BSW_CORE_PLUGIN_VERSION) {
        update_option('bsw_core_schema_version', BSW_CORE_PLUGIN_VERSION, false);
    }
});

// Tools → BIGSHOR Import / Export: general Wiki data portability.
$bsw_data_portability_path = $bsw_core_dir . 'data-portability-tools.php';
if (is_readable($bsw_data_portability_path)) require_once $bsw_data_portability_path;

// Centralised administration navigation hub (additive; preserves existing screens).
$bsw_admin_navigation_path = $bsw_core_dir . 'admin-navigation-hub.php';
if (is_readable($bsw_admin_navigation_path)) require_once $bsw_admin_navigation_path;

// Full WordPress capability authority for the Administrator role.
$bsw_admin_authority_path = $bsw_core_dir . 'administrator-authority.php';
if (is_readable($bsw_admin_authority_path)) require_once $bsw_admin_authority_path;

// Friendly article editor navigation; all existing fields and validation remain intact.
$bsw_editor_experience_path = $bsw_core_dir . 'editor-experience.php';
if (is_readable($bsw_editor_experience_path)) require_once $bsw_editor_experience_path;

// Wikipedia-inspired article presentation and secure frontend editing.
$bsw_phase40_path = $bsw_core_dir . 'phase40-wikipedia-article-experience.php';
if (is_readable($bsw_phase40_path)) require_once $bsw_phase40_path;

// Phase 41: structured article editing, section navigation and revision-safe saves.
$bsw_phase41_path = $bsw_core_dir . 'phase41-wiki-framework.php';
if (is_readable($bsw_phase41_path)) require_once $bsw_phase41_path;

$bsw_phase42_path = $bsw_core_dir . 'phase42-wikitext.php';
if (is_readable($bsw_phase42_path)) require_once $bsw_phase42_path;

// Phase 4: structured parser engine and source diagnostics.
$bsw_phase47_path = $bsw_core_dir . 'phase47-parser-engine.php';
if (is_readable($bsw_phase47_path)) require_once $bsw_phase47_path;

// Phase 5: reusable template registry and typed infobox definitions.
$bsw_phase5_templates_path = $bsw_core_dir . 'phase5-template-registry.php';
if (is_readable($bsw_phase5_templates_path)) require_once $bsw_phase5_templates_path;

// Phase 43: article quality diagnostics and structured data.
$bsw_phase43_path = $bsw_core_dir . 'phase43-article-quality.php';
if (is_readable($bsw_phase43_path)) require_once $bsw_phase43_path;

// Phase 1 architecture consolidation: one canonical frontend editor shortcode controller.
$bsw_phase44_path = $bsw_core_dir . 'phase44-architecture-consolidation.php';
if (is_readable($bsw_phase44_path)) require_once $bsw_phase44_path;

// Phase 2: canonical encyclopaedia article layout and responsive structure.
$bsw_phase45_path = $bsw_core_dir . 'phase45-article-layout.php';
if (is_readable($bsw_phase45_path)) require_once $bsw_phase45_path;

// Phase 3: frontend editor framework enhancements (preview, recovery, summaries).
$bsw_phase46_path = $bsw_core_dir . 'phase46-frontend-editor.php';
if (is_readable($bsw_phase46_path)) require_once $bsw_phase46_path;

// Phase 6: structured source records, reusable citations and article reference lists.
$bsw_phase6_citations_path = $bsw_core_dir . 'phase6-citation-management.php';
if (is_readable($bsw_phase6_citations_path)) require_once $bsw_phase6_citations_path;

// Phase 7: WordPress-native media library, file metadata, rights and accessible galleries.
$bsw_phase7_media_path = $bsw_core_dir . 'phase7-media-management.php';
if (is_readable($bsw_phase7_media_path)) require_once $bsw_phase7_media_path;

// Phase 8: native revision history, comparison and capability-checked restoration.
$bsw_phase8_revision_path = $bsw_core_dir . 'phase8-revision-history.php';
if (is_readable($bsw_phase8_revision_path)) require_once $bsw_phase8_revision_path;

// Phase 9: additive editorial review records and governance activity audit.
$bsw_phase9_governance_path = $bsw_core_dir . 'phase9-governance-controls.php';
if (is_readable($bsw_phase9_governance_path)) require_once $bsw_phase9_governance_path;

// Phase 10: advisory content-quality checks and intelligence report.
$bsw_phase10_quality_path = $bsw_core_dir . 'phase10-quality-intelligence.php';
if (is_readable($bsw_phase10_quality_path)) require_once $bsw_phase10_quality_path;

// Infobox editor: connects structured article infobox data to dashboard and frontend editing.
$bsw_infobox_editor_path = $bsw_core_dir . 'infobox-editor.php';
if (is_readable($bsw_infobox_editor_path)) require_once $bsw_infobox_editor_path;

// Phase 48: article lifecycle controls, page protection, watch notifications and discussion replies.
$bsw_phase48_path = $bsw_core_dir . 'phase48-encyclopedia-controls.php';
if (is_readable($bsw_phase48_path)) require_once $bsw_phase48_path;

// Phase 49: editorial maintenance reports, revision rollback and patrol workflow.
$bsw_phase49_path = $bsw_core_dir . 'phase49-maintenance-patrol.php';
if (is_readable($bsw_phase49_path)) require_once $bsw_phase49_path;

// Phase 50: major platform completion layer.
$bsw_phase50_path = $bsw_core_dir . 'phase50-major-platform.php';
if (is_readable($bsw_phase50_path)) require_once $bsw_phase50_path;

$bsw_phase51_path = $bsw_core_dir . 'phase51-advanced-platform.php';
if (is_readable($bsw_phase51_path)) require_once $bsw_phase51_path;

// Phase 52: indexed search, namespace-aware canonical routes and bot API schema.
$bsw_phase52_path = $bsw_core_dir . 'phase52-search-routing.php';
if (is_readable($bsw_phase52_path)) require_once $bsw_phase52_path;

$bsw_phase54_path = $bsw_core_dir . 'phase54-template-engine.php';
if (is_readable($bsw_phase54_path)) require_once $bsw_phase54_path;

// Phase 53: read-only production stability diagnostics for administrators.
$bsw_phase53_path = $bsw_core_dir . 'phase53-stability.php';
if (is_readable($bsw_phase53_path)) require_once $bsw_phase53_path;

// Phase 3: editorial governance, moderation appeals, revision oversight and protection tiers.
$bsw_phase55_path = $bsw_core_dir . 'phase55-editorial-governance.php';
if (is_readable($bsw_phase55_path)) require_once $bsw_phase55_path;

// Phase 3 completion: deletion discussions, editorial approval queue and Trash restoration.
$bsw_phase56_path = $bsw_core_dir . 'phase56-editorial-workflows.php';
if (is_readable($bsw_phase56_path)) require_once $bsw_phase56_path;

// Phase 4 completion: multilingual routes, translation QA, search relevance and RTL accessibility.
$bsw_phase57_path = $bsw_core_dir . 'phase57-multilingual-completion.php';
if (is_readable($bsw_phase57_path)) require_once $bsw_phase57_path;

// Phase 5 completion: structured knowledge graph, article relationships and topic discovery.
$bsw_phase58_path = $bsw_core_dir . 'phase58-knowledge-graph-intelligence.php';
if (is_readable($bsw_phase58_path)) require_once $bsw_phase58_path;

// Phase 6 completion: authenticated interoperability API and moderated JSON/CSV exchange.
$bsw_phase59_path = $bsw_core_dir . 'phase59-ecosystem-interoperability.php';
if (is_readable($bsw_phase59_path)) require_once $bsw_phase59_path;

// Phase 7 completion: secure cross-wiki federation and moderated content exchange.
$bsw_phase60_path = $bsw_core_dir . 'phase60-federation.php';
if (is_readable($bsw_phase60_path)) require_once $bsw_phase60_path;

// Phase 8: enterprise trust, operational resilience, privacy-conscious analytics and caching.
$bsw_phase61_path = $bsw_core_dir . 'phase61-enterprise-trust-infrastructure.php';
if (is_readable($bsw_phase61_path)) require_once $bsw_phase61_path;

// Phase 9: global enterprise knowledge infrastructure, reputation, editorial workflow and source discovery.
$bsw_phase62_path = $bsw_core_dir . 'phase62-global-enterprise-knowledge.php';
if (is_readable($bsw_phase62_path)) require_once $bsw_phase62_path;

// Phase 10: production readiness, deployment preflight, regression smoke checks and citation hygiene.
$bsw_phase63_path = $bsw_core_dir . 'phase63-production-readiness.php';
if (is_readable($bsw_phase63_path)) require_once $bsw_phase63_path;

// Phase UI: modern BIGSHOR administration dashboard (additive; existing admin screens retained).
$bsw_modern_dashboard_path = $bsw_core_dir . 'admin-dashboard-modern.php';
if (is_readable($bsw_modern_dashboard_path)) require_once $bsw_modern_dashboard_path;

// Version 33.8.0: consolidate duplicate WordPress and BIGSHOR administration menus.
$bsw_admin_menu_cleanup_path = $bsw_core_dir . 'admin-menu-cleanup.php';
if (is_readable($bsw_admin_menu_cleanup_path)) require_once $bsw_admin_menu_cleanup_path;


// Phase 11: read-only editorial governance, stale-work and media accessibility audit.
$bsw_phase64_path = $bsw_core_dir . 'phase64-content-governance-audit.php';
if (is_readable($bsw_phase64_path)) require_once $bsw_phase64_path;

// Admin UI is now delivered by the separately installable BIGSHOR WIKI Admin UI plugin.

// Phase 12: encyclopedia special pages, contributor contributions and discovery endpoints.
$bsw_phase65_path = $bsw_core_dir . 'phase65-special-pages.php';
if (is_readable($bsw_phase65_path)) require_once $bsw_phase65_path;

// Phase 66: granular wiki roles, temporary permissions, custom roles and role audit.
$bsw_phase66_path = $bsw_core_dir . 'phase66-user-roles-permissions.php';
if (is_readable($bsw_phase66_path)) require_once $bsw_phase66_path;

// Phase 67: public registration, verified email login and account profile portal.
$bsw_phase67_path = $bsw_core_dir . 'phase67-account-system.php';
if (is_readable($bsw_phase67_path)) require_once $bsw_phase67_path;

// Phase 68: role-specific public registration pages; privileged roles remain approval-gated.
$bsw_phase68_path = $bsw_core_dir . 'phase68-role-registration-pages.php';
if (is_readable($bsw_phase68_path)) require_once $bsw_phase68_path;

// Phase 69: responsive AVIF/WebP image variants with original-image fallback.
$bsw_phase69_path = $bsw_core_dir . 'phase69-modern-image-delivery.php';
if (is_readable($bsw_phase69_path)) require_once $bsw_phase69_path;

// Phase 70: automatic upload-time AVIF/WebP sidecar generation; originals are preserved.
$bsw_phase70_path = $bsw_core_dir . 'phase70-media-optimizer.php';
if (is_readable($bsw_phase70_path)) require_once $bsw_phase70_path;

// Native Tools Hub: curated third-party resources inside BIGSHOR Control Centre.
$bsw_tools_hub_path = $bsw_core_dir . 'tools-hub.php';
if (is_readable($bsw_tools_hub_path)) require_once $bsw_tools_hub_path;

// Native Article Studio: full visual/source editor and editorial collaboration workflow.
$bsw_article_studio_path = $bsw_core_dir . 'article-studio.php';
if (is_readable($bsw_article_studio_path)) require_once $bsw_article_studio_path;
