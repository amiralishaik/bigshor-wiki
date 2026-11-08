<?php
/**
 * Article-page Phase 12: final integration and production release gate.
 * Read-only administrator diagnostics; never deploys, publishes or migrates.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase12_Article_Release_Integration')) {
final class BSW_Phase12_Article_Release_Integration {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 101);
    }

    private static function row($label, $ok, $detail, $warning = false) {
        return array('label'=>$label,'status'=>$ok?'pass':($warning?'warning':'fail'),'detail'=>$detail);
    }

    public static function diagnostics() {
        $core = defined('BSW_CORE_PLUGIN_VERSION') ? BSW_CORE_PLUGIN_VERSION : 'unknown';
        $checks = array();
        $checks[] = self::row('BIGSHOR Core bootstrap', defined('BSW_CORE_PLUGIN_ACTIVE') && BSW_CORE_PLUGIN_ACTIVE, 'Core active flag is present.');
        $checks[] = self::row('Core release version', $core === '34.12.0', 'Detected Core version: '.$core.'. Expected 34.12.0.');
        $checks[] = self::row('Canonical article layout', class_exists('BSW_Phase45_Article_Layout'), 'Phase 45 article layout class is loaded.');
        $checks[] = self::row('Article community module', class_exists('BSW_Phase8_Article_Community'), 'Phase 8 discussion and watchlist module is loaded.');
        $checks[] = self::row('Advanced article tools', class_exists('BSW_Phase9_Advanced_Article'), 'Phase 9 information and citation tools are loaded.');
        $checks[] = self::row('Article discovery module', class_exists('BSW_Phase10_Article_Discovery') && shortcode_exists('bsw_article_search'), 'Phase 10 module and [bsw_article_search] shortcode are available.');
        $checks[] = self::row('Article hardening module', class_exists('BSW_Phase11_Article_Hardening'), 'Phase 11 cache and media hardening module is loaded.');
        $checks[] = self::row('Article release integration', class_exists(__CLASS__), 'Phase 12 read-only release gate is loaded.');
        $checks[] = self::row('Discovery cache invalidation', has_action('save_post_wiki_article', array('BSW_Phase11_Article_Hardening','invalidate')) !== false, 'Article saves invalidate cached discovery results.');
        $checks[] = self::row('Asynchronous media optimizer', has_action('bsw_media_optimizer_process_attachment') !== false, 'Phase 70 background image processing hook is registered.');
        $sync = class_exists('BSW_Phase69_Modern_Image_Delivery') && has_filter('wp_generate_attachment_metadata', array('BSW_Phase69_Modern_Image_Delivery','generate')) !== false;
        $checks[] = self::row('No duplicate synchronous image conversion', !$sync, $sync ? 'Legacy synchronous image conversion is still attached.' : 'Legacy picture markup remains available; synchronous duplicate conversion is disabled.');
        $checks[] = self::row('PHP runtime', version_compare(PHP_VERSION,'7.4','>='), 'Detected PHP '.PHP_VERSION.'. Minimum supported version is 7.4.');
        $wp = get_bloginfo('version');
        $checks[] = self::row('WordPress runtime', version_compare($wp,'6.4','>='), 'Detected WordPress '.$wp.'. Minimum supported version is 6.4.');
        $passed=0;$warnings=0;$failed=0;
        foreach($checks as $check){if($check['status']==='pass')$passed++;elseif($check['status']==='warning')$warnings++;else$failed++;}
        return array('generated_at'=>gmdate('c'),'release'=>$core,'summary'=>array('total'=>count($checks),'passed'=>$passed,'warnings'=>$warnings,'failed'=>$failed),'status'=>$failed===0?'ready_for_staging_validation':'blocked','checks'=>$checks,'scope'=>'Read-only integration checks. Passing does not replace WordPress staging tests, browser accessibility review, security assessment, load testing or an approved production deployment.');
    }

    public static function menu() {
        add_management_page('BIGSHOR Article Release Check','Article Release Check','manage_options','bsw-article-release-check',array(__CLASS__,'render'));
    }

    public static function render() {
        if(!current_user_can('manage_options'))return;
        $report=self::diagnostics();
        echo '<div class="wrap"><h1>BIGSHOR Article Release Check</h1><p>Read-only checks for article roadmap integration, cache invalidation, runtime compatibility and image-processing conflicts.</p>';
        echo '<p><strong>Status:</strong> '.esc_html(strtoupper(str_replace('_',' ',$report['status']))).' · <strong>Passed:</strong> '.(int)$report['summary']['passed'].' / '.(int)$report['summary']['total'].'</p>';
        echo '<table class="widefat striped"><thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead><tbody>';
        foreach($report['checks'] as $check) echo '<tr><th>'.esc_html($check['label']).'</th><td>'.esc_html(strtoupper($check['status'])).'</td><td>'.esc_html($check['detail']).'</td></tr>';
        echo '</tbody></table><p class="description">'.esc_html($report['scope']).'</p></div>';
    }
}
BSW_Phase12_Article_Release_Integration::init();
}
