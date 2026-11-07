<?php
/**
 * BIGSHOR structured infobox editing for wiki articles.
 * Adds a dashboard metabox and keeps _bsw_infobox in sync with article editors.
 */
if (!defined('ABSPATH')) exit;

final class BSW_Infobox_Editor {
    public static function init() {
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'add_metabox'));
        add_action('save_post_wiki_article', array(__CLASS__, 'save_metabox'), 20, 2);
        add_action('admin_head', array(__CLASS__, 'admin_styles'));
    }

    public static function add_metabox($post) {
        if (!current_user_can('edit_post', $post->ID)) return;
        add_meta_box(
            'bsw_article_infobox',
            'BIGSHOR Wiki Infobox',
            array(__CLASS__, 'metabox'),
            'wiki_article',
            'normal',
            'high'
        );
    }

    private static function current_data($post_id) {
        $data = get_post_meta($post_id, '_bsw_infobox', true);
        if (!is_array($data)) $data = array();
        if (empty($data)) {
            $legacy = get_post_meta($post_id, '_bsw_p1_infobox_rows', true);
            if (is_array($legacy) && $legacy) {
                $fields = array();
                foreach ($legacy as $row) {
                    if (is_array($row) && !empty($row['label'])) {
                        $fields[sanitize_key($row['label'])] = isset($row['value']) ? $row['value'] : '';
                    }
                }
                $data = array(
                    'title' => get_post_meta($post_id, '_bsw_p1_infobox_title', true),
                    'image' => get_post_meta($post_id, '_bsw_p1_infobox_image', true),
                    'fields' => $fields,
                );
            }
        }
        return $data;
    }

    public static function metabox($post) {
        $data = self::current_data($post->ID);
        $fields = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : array();
        $lines = array();
        foreach ($fields as $key => $value) {
            if (is_scalar($value) && (string)$value !== '') {
                $label = ucwords(str_replace(array('_', '-'), ' ', (string)$key));
                $lines[] = $label . ': ' . (string)$value;
            }
        }
        wp_nonce_field('bsw_save_article_infobox_' . $post->ID, 'bsw_article_infobox_nonce');
        echo '<p><label for="bsw_infobox_title"><strong>Infobox heading</strong></label></p>';
        echo '<input class="widefat" type="text" id="bsw_infobox_title" name="bsw_infobox_title" value="' . esc_attr(isset($data['title']) ? $data['title'] : '') . '" placeholder="e.g. Amir Ali Shaik">';
        echo '<p><label for="bsw_infobox_image"><strong>Image URL</strong></label></p>';
        echo '<input class="widefat" type="url" id="bsw_infobox_image" name="bsw_infobox_image" value="' . esc_attr(isset($data['image']) && is_string($data['image']) ? $data['image'] : '') . '" placeholder="https://example.com/image.jpg">';
        echo '<p><label for="bsw_infobox_fields"><strong>Infobox fields</strong></label></p>';
        echo '<textarea class="widefat" rows="9" id="bsw_infobox_fields" name="bsw_infobox_fields" placeholder="Born: 26 July 2001&#10;Occupation: Actor and entrepreneur&#10;Nationality: Indian">' . esc_textarea(implode("\n", $lines)) . '</textarea>';
        echo '<p class="description">Enter one field per line in <code>Label: Value</code> format. Leave the heading, image and fields empty to remove the infobox. The infobox appears beside the article introduction on the published article page.</p>';
    }

    public static function save_metabox($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!$post || $post->post_type !== 'wiki_article') return;
        if (!isset($_POST['bsw_article_infobox_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_article_infobox_nonce'])), 'bsw_save_article_infobox_' . $post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $title = sanitize_text_field(wp_unslash($_POST['bsw_infobox_title'] ?? ''));
        $image = esc_url_raw(wp_unslash($_POST['bsw_infobox_image'] ?? ''));
        $raw = sanitize_textarea_field(wp_unslash($_POST['bsw_infobox_fields'] ?? ''));
        $fields = array();
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            if (strpos($line, ':') === false) continue;
            list($label, $value) = array_map('trim', explode(':', $line, 2));
            if ($label === '' || $value === '') continue;
            $key = sanitize_key(str_replace(' ', '_', $label));
            if ($key !== '') $fields[$key] = sanitize_text_field($value);
        }

        if ($title === '' && $image === '' && !$fields) {
            delete_post_meta($post_id, '_bsw_infobox');
            return;
        }
        update_post_meta($post_id, '_bsw_infobox', array(
            'title' => $title,
            'image' => $image,
            'fields' => $fields,
        ));
    }

    public static function admin_styles() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'wiki_article') return;
        echo '<style>#bsw_article_infobox .inside input,#bsw_article_infobox .inside textarea{max-width:100%;}#bsw_article_infobox .description{margin-top:8px;}</style>';
    }
}
BSW_Infobox_Editor::init();
