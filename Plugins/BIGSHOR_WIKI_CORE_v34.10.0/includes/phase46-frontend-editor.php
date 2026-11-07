<?php
/**
 * BIGSHOR Phase 3: frontend editor framework enhancements.
 * Adds client-side preview, recoverable local drafts, save-state feedback and
 * edit summaries to the canonical Phase 42 editor without bypassing its
 * capability, nonce, sanitisation, conflict or editorial workflow checks.
 */
if (!defined('ABSPATH')) exit;
final class BSW_Phase46_Frontend_Editor {
    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
    }
    public static function assets() {
        if (!is_singular('wiki_article') || !is_user_logged_in()) return;
        $post_id = get_queried_object_id();
        if (!$post_id || !current_user_can('edit_post', $post_id)) return;
        wp_register_style('bsw46-editor', false, array(), '30.8.0');
        wp_enqueue_style('bsw46-editor');
        wp_add_inline_style('bsw46-editor', '.bsw46-editor-tools{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:14px 0}.bsw46-editor-tools button{padding:8px 12px;border:1px solid #a2a9b1;background:#f8f9fa;border-radius:3px;cursor:pointer}.bsw46-save-state{font-size:13px;color:#54595d}.bsw46-preview{border:1px solid #a2a9b1;background:#fff;padding:16px;margin:12px 0;max-height:480px;overflow:auto}.bsw46-preview[hidden]{display:none}.bsw42-editor input[name="bsw42_edit_summary"]{width:100%;max-width:760px;padding:9px}.bsw46-preview h2,.bsw46-preview h3{border-bottom:1px solid #a2a9b1;padding-bottom:5px}.bsw46-preview img{max-width:100%;height:auto}');
        wp_register_script('bsw46-editor', false, array('jquery'), '30.8.0', true);
        wp_enqueue_script('bsw46-editor');
        $config = array('postId'=>(int)$post_id,'key'=>'bsw46-draft-'.$post_id,'interval'=>15000,'savedMessage'=>'Local recovery saved.','restoredMessage'=>'Recovered an unsaved local draft. Review before submitting.');
        wp_add_inline_script('bsw46-editor', 'window.BSW46_EDITOR='.wp_json_encode($config).';', 'before');
        wp_add_inline_script('bsw46-editor', self::script(), 'after');
    }
    private static function script() {
        return <<<'JS'
(function(){
 'use strict';
 var cfg=window.BSW46_EDITOR||{}, form=document.querySelector('.bsw42-editor form'); if(!form||!cfg.key)return;
 var field=form.querySelector('[name="bsw42_content"]'), state=form.querySelector('.bsw46-save-state'), preview=form.querySelector('.bsw46-preview');
 if(!field)return;
 function getValue(){try{if(window.tinymce){var ed=window.tinymce.get(field.id);if(ed)return ed.getContent();}}catch(e){}return field.value;}
 function setValue(v){try{if(window.tinymce){var ed=window.tinymce.get(field.id);if(ed){ed.setContent(v);return;}}}catch(e){}field.value=v;}
 function snapshot(){return {content:getValue(),title:(form.querySelector('[name="bsw42_title"]')||{}).value||'',excerpt:(form.querySelector('[name="bsw42_excerpt"]')||{}).value||'',summary:(form.querySelector('[name="bsw42_edit_summary"]')||{}).value||'',mode:(form.querySelector('[name="bsw42_mode"]')||{}).value||'visual',time:Date.now()};}
 function setState(s){if(state)state.textContent=s;}
 try{var raw=localStorage.getItem(cfg.key);if(raw){var draft=JSON.parse(raw);if(draft&&draft.time&&Date.now()-draft.time<7*24*60*60*1000){var original=field.value;if(draft.content&&draft.content!==original&&window.confirm('BIGSHOR found an unsaved local draft from this browser. Restore it?')){setValue(draft.content);['title','excerpt','summary'].forEach(function(k){var el=form.querySelector('[name="bsw42_'+(k==='summary'?'edit_summary':k)+'"]');if(el&&draft[k]!==undefined)el.value=draft[k];});setState(cfg.restoredMessage);}}}}catch(e){}
 var timer=window.setInterval(function(){try{localStorage.setItem(cfg.key,JSON.stringify(snapshot()));setState(cfg.savedMessage);}catch(e){setState('Local recovery unavailable in this browser.');}},cfg.interval);
 var toggle=form.querySelector('.bsw46-preview-toggle');if(toggle)toggle.addEventListener('click',function(){var open=preview.hidden; if(open){var html=getValue(); if((form.querySelector('[name="bsw42_mode"]')||{}).value==='wikitext'){preview.textContent=html;}else{preview.innerHTML=html;} } preview.hidden=!open;toggle.textContent=preview.hidden?'Preview':'Hide preview';});
 var clear=form.querySelector('.bsw46-recovery-clear');if(clear)clear.addEventListener('click',function(){try{localStorage.removeItem(cfg.key);setState('Local recovery cleared.');}catch(e){setState('Could not clear local recovery.');}});
 form.addEventListener('submit',function(){try{if(window.tinymce)window.tinymce.triggerSave();localStorage.removeItem(cfg.key);}catch(e){}window.clearInterval(timer);});
 window.addEventListener('beforeunload',function(){try{if(form.dataset.submitting!=='1')localStorage.setItem(cfg.key,JSON.stringify(snapshot()));}catch(e){}});
 form.addEventListener('submit',function(){form.dataset.submitting='1';});
})();
JS;
    }
}
BSW_Phase46_Frontend_Editor::init();
