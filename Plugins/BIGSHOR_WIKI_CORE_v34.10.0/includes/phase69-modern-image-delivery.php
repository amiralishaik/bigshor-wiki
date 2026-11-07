<?php
/**
 * Phase 69: generate WebP/AVIF attachment variants where the active WP image
 * editor supports them and serve responsive <picture> sources with the
 * original WordPress image as the crawlable compatibility fallback.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase69_Modern_Image_Delivery')):
final class BSW_Phase69_Modern_Image_Delivery {
    const META = '_bsw_phase69_modern_variants';
    public static function init() {
        add_filter('wp_generate_attachment_metadata', array(__CLASS__, 'generate'), 20, 2);
        add_filter('wp_get_attachment_image', array(__CLASS__, 'picture_markup'), 20, 5);
    }
    private static function create_variant($source, $mime, $suffix) {
        if (!file_exists($source) || !wp_image_editor_supports(array('mime_type'=>$mime))) return false;
        $editor = wp_get_image_editor($source);
        if (is_wp_error($editor)) return false;
        $destination = preg_replace('/\.(jpe?g|png)$/i', '', $source).'.'.$suffix;
        $saved = $editor->save($destination, $mime);
        if (is_wp_error($saved) || empty($saved['path']) || !file_exists($saved['path'])) return false;
        return array('path'=>$saved['path'],'width'=>(int)$saved['width'],'height'=>(int)$saved['height']);
    }
    public static function generate($metadata, $attachment_id) {
        if (empty($metadata['file']) || !wp_attachment_is_image($attachment_id)) return $metadata;
        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, array('image/jpeg','image/png'), true)) return $metadata;
        $uploads = wp_upload_dir();
        $original = get_attached_file($attachment_id);
        if (!$original || !file_exists($original)) return $metadata;
        $sources = array(array('path'=>$original,'width'=>isset($metadata['width'])?(int)$metadata['width']:0,'height'=>isset($metadata['height'])?(int)$metadata['height']:0));
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size) {
                if (empty($size['file'])) continue;
                $path = trailingslashit(dirname($original)).$size['file'];
                if (file_exists($path)) $sources[] = array('path'=>$path,'width'=>(int)$size['width'],'height'=>(int)$size['height']);
            }
        }
        $variants = array();
        foreach ($sources as $source) {
            $key = basename($source['path']);
            $entry = array('width'=>$source['width'],'height'=>$source['height']);
            $webp = self::create_variant($source['path'],'image/webp','webp');
            $avif = self::create_variant($source['path'],'image/avif','avif');
            foreach (array('webp'=>$webp,'avif'=>$avif) as $format=>$variant) {
                if ($variant && strpos($variant['path'], trailingslashit($uploads['basedir'])) === 0) {
                    $relative = ltrim(str_replace(wp_normalize_path($uploads['basedir']), '', wp_normalize_path($variant['path'])), '/');
                    $entry[$format] = array('url'=>trailingslashit($uploads['baseurl']).str_replace('%2F','/',rawurlencode($relative)),'width'=>$variant['width']);
                }
            }
            if (!empty($entry['webp']) || !empty($entry['avif'])) $variants[$key] = $entry;
        }
        if ($variants) update_post_meta($attachment_id, self::META, $variants);
        return $metadata;
    }
    public static function picture_markup($html, $attachment_id, $size, $icon, $attr) {
        if (!$html || !wp_attachment_is_image($attachment_id) || strpos($html, '<picture') !== false) return $html;
        $variants = get_post_meta($attachment_id, self::META, true);
        if (!is_array($variants) || !$variants) return $html;
        $avif = array(); $webp = array();
        foreach ($variants as $entry) {
            $w = isset($entry['width'])?(int)$entry['width']:0;
            if ($w < 1) continue;
            if (!empty($entry['avif']['url'])) $avif[] = esc_url($entry['avif']['url']).' '.$w.'w';
            if (!empty($entry['webp']['url'])) $webp[] = esc_url($entry['webp']['url']).' '.$w.'w';
        }
        if (!$avif && !$webp) return $html;
        $sizes = isset($attr['sizes']) ? esc_attr($attr['sizes']) : '(max-width: 640px) 100vw, 1100px';
        $sources = '';
        if ($avif) $sources .= '<source type="image/avif" srcset="'.implode(', ',$avif).'" sizes="'.$sizes.'">';
        if ($webp) $sources .= '<source type="image/webp" srcset="'.implode(', ',$webp).'" sizes="'.$sizes.'">';
        return '<picture>'.$sources.$html.'</picture>';
    }
}
BSW_Phase69_Modern_Image_Delivery::init();
endif;
