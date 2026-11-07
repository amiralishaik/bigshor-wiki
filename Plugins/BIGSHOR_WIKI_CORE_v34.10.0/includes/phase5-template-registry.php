<?php
/**
 * Phase 5: reusable BIGSHOR template and infobox registry.
 * Stores definitions as validated configuration; article data remains in post content.
 */
if (!defined('ABSPATH')) exit;
final class BSW_Phase5_Template_Registry {
    const OPTION = 'bsw_template_registry_v1';
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_bsw_save_template_registry', array(__CLASS__, 'save'));
        add_shortcode('bsw_infobox', array(__CLASS__, 'shortcode'));
    }
    public static function defaults() {
        return array(
            'person' => array('label'=>'Person','fields'=>array(
                'name'=>array('label'=>'Name','type'=>'text','required'=>false),
                'image'=>array('label'=>'Image attachment ID','type'=>'image','required'=>false),
                'born'=>array('label'=>'Born','type'=>'text','required'=>false),
                'occupation'=>array('label'=>'Occupation','type'=>'text','required'=>false),
                'years_active'=>array('label'=>'Years active','type'=>'text','required'=>false),
                'nationality'=>array('label'=>'Nationality','type'=>'text','required'=>false),
                'website'=>array('label'=>'Website','type'=>'url','required'=>false)
            )),
            'organisation' => array('label'=>'Organisation','fields'=>array(
                'name'=>array('label'=>'Name','type'=>'text','required'=>false),
                'logo'=>array('label'=>'Logo attachment ID','type'=>'image','required'=>false),
                'type'=>array('label'=>'Type','type'=>'text','required'=>false),
                'founded'=>array('label'=>'Founded','type'=>'date','required'=>false),
                'headquarters'=>array('label'=>'Headquarters','type'=>'text','required'=>false),
                'website'=>array('label'=>'Website','type'=>'url','required'=>false)
            )),
            'company' => array('label'=>'Company','fields'=>array(
                'name'=>array('label'=>'Name','type'=>'text','required'=>false),
                'logo'=>array('label'=>'Logo attachment ID','type'=>'image','required'=>false),
                'industry'=>array('label'=>'Industry','type'=>'text','required'=>false),
                'founded'=>array('label'=>'Founded','type'=>'date','required'=>false),
                'founder'=>array('label'=>'Founder','type'=>'text','required'=>false),
                'headquarters'=>array('label'=>'Headquarters','type'=>'text','required'=>false),
                'website'=>array('label'=>'Website','type'=>'url','required'=>false)
            )),
            'place' => array('label'=>'Place','fields'=>array(
                'name'=>array('label'=>'Name','type'=>'text','required'=>false),
                'image'=>array('label'=>'Image attachment ID','type'=>'image','required'=>false),
                'country'=>array('label'=>'Country','type'=>'text','required'=>false),
                'region'=>array('label'=>'Region','type'=>'text','required'=>false),
                'coordinates'=>array('label'=>'Coordinates','type'=>'text','required'=>false),
                'population'=>array('label'=>'Population','type'=>'number','required'=>false)
            )),
            'event' => array('label'=>'Event','fields'=>array(
                'name'=>array('label'=>'Name','type'=>'text','required'=>false),
                'date'=>array('label'=>'Date','type'=>'date','required'=>false),
                'location'=>array('label'=>'Location','type'=>'text','required'=>false),
                'organiser'=>array('label'=>'Organiser','type'=>'text','required'=>false)
            )),
            'book' => array('label'=>'Book','fields'=>array(
                'title'=>array('label'=>'Title','type'=>'text','required'=>false),
                'author'=>array('label'=>'Author','type'=>'text','required'=>false),
                'language'=>array('label'=>'Language','type'=>'text','required'=>false),
                'published'=>array('label'=>'First published','type'=>'date','required'=>false),
                'isbn'=>array('label'=>'ISBN','type'=>'text','required'=>false)
            )),
            'creative_work' => array('label'=>'Creative work','fields'=>array(
                'title'=>array('label'=>'Title','type'=>'text','required'=>false),
                'creator'=>array('label'=>'Creator','type'=>'text','required'=>false),
                'genre'=>array('label'=>'Genre','type'=>'text','required'=>false),
                'release_date'=>array('label'=>'Release date','type'=>'date','required'=>false),
                'country'=>array('label'=>'Country','type'=>'text','required'=>false)
            ))
        );
    }
    public static function definitions() {
        $saved = get_option(self::OPTION, array());
        return is_array($saved) ? array_merge(self::defaults(), $saved) : self::defaults();
    }
    public static function parse_fields($raw) {
        $fields = array();
        foreach (preg_split('/\s*\|\s*(?=[a-z0-9_-]+\s*=)/i', (string)$raw) as $pair) {
            if (strpos($pair, '=') === false) continue;
            list($key,$value) = array_map('trim', explode('=', $pair, 2));
            $key = sanitize_key($key);
            if ($key !== '') $fields[$key] = $value;
        }
        return $fields;
    }
    public static function render($template, $fields) {
        $template = sanitize_key(str_replace(' ', '_', strtolower($template)));
        $aliases = array('person'=>'person','people'=>'person','organization'=>'organisation','organisation'=>'organisation','company'=>'company','place'=>'place','event'=>'event','book'=>'book','creative_work'=>'creative_work','creativework'=>'creative_work');
        $key = isset($aliases[$template]) ? $aliases[$template] : $template;
        $defs = self::definitions();
        if (!isset($defs[$key]) || empty($defs[$key]['fields']) || !is_array($fields)) return '';
        $def = $defs[$key]; $rows = '';
        foreach ($def['fields'] as $field => $meta) {
            if (!isset($fields[$field]) || trim((string)$fields[$field]) === '') continue;
            $value = trim((string)$fields[$field]);
            $type = isset($meta['type']) ? $meta['type'] : 'text';
            if ($type === 'image') {
                $attachment = absint($value);
                if (!$attachment || get_post_type($attachment) !== 'attachment') continue;
                $img = wp_get_attachment_image($attachment, 'medium', false, array('class'=>'bsw50-infobox-image','loading'=>'lazy'));
                if ($img) $rows .= '<tr class="bsw50-infobox-image-row"><td colspan="2">'.$img.'</td></tr>';
                continue;
            }
            if ($type === 'url') {
                $url = esc_url($value);
                if (!$url) continue;
                $display = esc_html(preg_replace('#^https?://#','',$url));
                $safe = '<a href="'.$url.'" rel="nofollow noopener">'.$display.'</a>';
            } else {
                if ($type === 'number') $value = preg_replace('/[^0-9,.+-]/','',$value);
                if ($type === 'date') $value = sanitize_text_field($value);
                $safe = esc_html($value);
            }
            $label = isset($meta['label']) ? $meta['label'] : ucwords(str_replace('_',' ',$field));
            $rows .= '<tr><th scope="row">'.esc_html($label).'</th><td>'.$safe.'</td></tr>';
        }
        if ($rows === '') return '';
        $caption = isset($fields['name']) ? $fields['name'] : (isset($fields['title']) ? $fields['title'] : $def['label']);
        return '<table class="bsw50-infobox" role="table"><caption>'.esc_html($caption).'</caption><tbody>'.$rows.'</tbody></table>';
    }
    public static function shortcode($atts, $content='') {
        $atts = shortcode_atts(array('type'=>'person'), $atts, 'bsw_infobox');
        $fields = array();
        if (is_array($atts)) { foreach ($atts as $k=>$v) if ($k !== 'type') $fields[sanitize_key($k)] = $v; }
        return self::render($atts['type'], $fields);
    }
    public static function menu() {
        add_management_page('BIGSHOR Templates','BIGSHOR Templates','manage_options','bsw-template-registry',array(__CLASS__,'page'));
    }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        $json = wp_json_encode(self::definitions(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        echo '<div class="wrap"><h1>BIGSHOR Template Registry</h1><p>Manage reusable infobox definitions. Existing article content is not modified. Supported field types: text, url, date, number, image (WordPress attachment ID).</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="bsw_save_template_registry">';
        wp_nonce_field('bsw_save_template_registry');
        echo '<textarea name="registry_json" rows="28" class="large-text code" spellcheck="false">'.esc_textarea($json).'</textarea>';
        submit_button('Save template definitions');
        echo '</form><h2>Usage in wikitext</h2><pre>{{Infobox Person|name=Example Name|occupation=Actor|nationality=Indian}}</pre>';
        echo '<p>Use a registered template name. Unrecognised templates are left visible to the editor as unsupported source rather than silently discarded.</p></div>';
    }
    public static function save() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('You are not allowed to manage templates.','bigshor'));
        check_admin_referer('bsw_save_template_registry');
        $raw = isset($_POST['registry_json']) ? wp_unslash($_POST['registry_json']) : '';
        $data = json_decode($raw, true);
        if (!is_array($data)) wp_die(esc_html__('Invalid JSON. No changes were saved.','bigshor'));
        $clean = array();
        foreach ($data as $slug=>$definition) {
            $slug = sanitize_key($slug);
            if (!$slug || !is_array($definition) || empty($definition['fields']) || !is_array($definition['fields'])) continue;
            $fields = array();
            foreach ($definition['fields'] as $field=>$meta) {
                $field = sanitize_key($field);
                if (!$field || !is_array($meta)) continue;
                $type = isset($meta['type']) ? sanitize_key($meta['type']) : 'text';
                if (!in_array($type,array('text','url','date','number','image'),true)) $type='text';
                $fields[$field] = array('label'=>isset($meta['label'])?sanitize_text_field($meta['label']):ucwords(str_replace('_',' ',$field)),'type'=>$type,'required'=>!empty($meta['required']));
            }
            if ($fields) $clean[$slug] = array('label'=>isset($definition['label'])?sanitize_text_field($definition['label']):ucwords($slug),'fields'=>$fields);
        }
        if (!$clean) wp_die(esc_html__('At least one valid template with fields is required.','bigshor'));
        update_option(self::OPTION, $clean, false);
        wp_safe_redirect(add_query_arg(array('page'=>'bsw-template-registry','updated'=>'1'),admin_url('tools.php')));
        exit;
    }
}
BSW_Phase5_Template_Registry::init();
