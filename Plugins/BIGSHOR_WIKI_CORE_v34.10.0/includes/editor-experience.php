<?php
/**
 * BIGSHOR Core 29.8.2 — Friendly editor navigation and field guidance.
 * Additive UI enhancement: does not remove fields or change required-field rules.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('BSW_Friendly_Editor_Experience')) {
final class BSW_Friendly_Editor_Experience {
    public static function init() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function assets($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->post_type, array('wiki_article', 'wiki_entity'), true)) return;

        wp_register_style('bsw-friendly-editor', false, array(), BSW_CORE_PLUGIN_VERSION);
        wp_enqueue_style('bsw-friendly-editor');
        $css = '
        body.post-type-wiki_article #poststuff, body.post-type-wiki_entity #poststuff { max-width: 1480px; }
        .bsw-editor-guide { box-sizing:border-box; margin:18px 0 22px; padding:18px 20px; border:1px solid #d7e0ea; border-left:4px solid #183b63; border-radius:10px; background:#fff; box-shadow:0 2px 8px rgba(20,40,65,.04); }
        .bsw-editor-guide-head { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:8px; }
        .bsw-editor-guide h2 { margin:0; font-size:18px; line-height:1.35; color:#142b45; }
        .bsw-editor-guide p { margin:6px 0 12px; color:#536477; font-size:13px; }
        .bsw-editor-guide-tools { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
        .bsw-editor-guide input[type=search] { min-width:240px; max-width:100%; min-height:36px; border:1px solid #c7d1dc; border-radius:6px; padding:5px 10px; }
        .bsw-editor-guide .button { min-height:34px; border-radius:6px; }
        .bsw-editor-progress { height:7px; max-width:420px; margin-top:12px; overflow:hidden; border-radius:99px; background:#edf1f5; }
        .bsw-editor-progress span { display:block; width:0; height:100%; background:#27845c; transition:width .2s ease; }
        .bsw-editor-progress-label { display:block; margin-top:5px; color:#64748b; font-size:12px; }
        #poststuff .postbox { border:1px solid #dce3eb; border-radius:9px; overflow:hidden; box-shadow:0 2px 7px rgba(20,40,65,.035); margin-bottom:14px; }
        #poststuff .postbox .postbox-header { background:#f7f9fc; border-bottom:1px solid #e5eaf0; }
        #poststuff .postbox .hndle { padding:12px 14px; color:#183653; font-size:14px; }
        #poststuff .postbox .inside { padding:14px 16px 16px; }
        #poststuff .postbox .inside p { line-height:1.55; }
        #poststuff .postbox input[type=text], #poststuff .postbox input[type=url], #poststuff .postbox input[type=date], #poststuff .postbox input[type=number], #poststuff .postbox select, #poststuff .postbox textarea { max-width:100%; box-sizing:border-box; }
        #poststuff .postbox textarea { width:100%; }
        .bsw-editor-guide .bsw-guide-links { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
        .bsw-editor-guide .bsw-guide-links a { display:inline-block; padding:5px 9px; border:1px solid #d8e1ea; border-radius:5px; background:#f8fafc; color:#214a70; text-decoration:none; font-size:12px; }
        .bsw-editor-guide .bsw-guide-links a:hover, .bsw-editor-guide .bsw-guide-links a:focus { background:#eaf2fa; border-color:#9eb6ce; }
        .bsw-editor-filter-hidden { display:none !important; }
        @media(max-width:782px) { .bsw-editor-guide { padding:14px; } .bsw-editor-guide input[type=search] { width:100%; min-width:0; } .bsw-editor-guide-tools { width:100%; } #poststuff .postbox .inside { padding:12px; } }
        ';
        wp_add_inline_style('bsw-friendly-editor', $css);

        wp_register_script('bsw-friendly-editor', false, array(), BSW_CORE_PLUGIN_VERSION, true);
        wp_enqueue_script('bsw-friendly-editor');
        $js = <<<'JS'
(function(){
  function ready(fn){ if(document.readyState!=='loading'){fn();}else{document.addEventListener('DOMContentLoaded',fn);} }
  ready(function(){
    var poststuff=document.getElementById('poststuff');
    if(!poststuff || document.querySelector('.bsw-editor-guide')) return;
    var boxes=Array.prototype.slice.call(poststuff.querySelectorAll('.postbox'));
    var guide=document.createElement('section');
    guide.className='bsw-editor-guide';
    guide.setAttribute('aria-label','BIGSHOR editor navigation');
    guide.innerHTML='<div class="bsw-editor-guide-head"><h2>BIGSHOR Editor Workspace</h2><span class="description">All existing fields and publishing requirements are retained.</span></div><p>Use the quick links to jump to a section, or search the editor fields below. Complete every field marked as required before saving or publishing.</p><div class="bsw-editor-guide-tools"><input type="search" id="bsw-editor-field-search" placeholder="Find a field or section…" aria-label="Find a field or section"><button type="button" class="button" id="bsw-editor-expand">Expand all sections</button><button type="button" class="button" id="bsw-editor-collapse">Collapse all sections</button></div><div class="bsw-guide-links" id="bsw-editor-section-links"></div><div class="bsw-editor-progress"><span id="bsw-editor-progress-bar"></span></div><span class="bsw-editor-progress-label" id="bsw-editor-progress-label">Checking required fields…</span>';
    var firstBox=poststuff.querySelector('#post-body');
    if(firstBox){ poststuff.insertBefore(guide, firstBox); } else { poststuff.insertBefore(guide, poststuff.firstChild); }
    var links=guide.querySelector('#bsw-editor-section-links');
    boxes.forEach(function(box,i){
      if(!box.id) box.id='bsw-editor-section-'+i;
      var title=box.querySelector('.postbox-header .hndle, h2.hndle, .hndle');
      var label=title ? title.textContent.trim() : 'Editor section '+(i+1);
      if(label){ var a=document.createElement('a'); a.href='#'+box.id; a.textContent=label; a.addEventListener('click',function(e){e.preventDefault();box.scrollIntoView({behavior:'smooth',block:'start'});box.classList.remove('closed');}); links.appendChild(a); }
      box.style.scrollMarginTop='35px';
    });
    var search=guide.querySelector('#bsw-editor-field-search');
    search.addEventListener('input',function(){
      var q=this.value.toLowerCase().trim();
      boxes.forEach(function(box){
        var match=!q || box.textContent.toLowerCase().indexOf(q)!==-1;
        box.classList.toggle('bsw-editor-filter-hidden',!match);
        if(q && match) box.classList.remove('closed');
      });
    });
    guide.querySelector('#bsw-editor-expand').addEventListener('click',function(){boxes.forEach(function(b){b.classList.remove('closed');});});
    guide.querySelector('#bsw-editor-collapse').addEventListener('click',function(){boxes.forEach(function(b){b.classList.add('closed');});});
    function updateProgress(){
      var fields=Array.prototype.slice.call(poststuff.querySelectorAll('input[required],select[required],textarea[required]')).filter(function(el){return !el.disabled && el.type!=='hidden' && el.offsetParent!==null;});
      var filled=fields.filter(function(el){ if(el.type==='checkbox'||el.type==='radio') return el.checked; return String(el.value||'').trim()!==''; }).length;
      var pct=fields.length ? Math.round((filled/fields.length)*100) : 100;
      var bar=guide.querySelector('#bsw-editor-progress-bar'); bar.style.width=pct+'%';
      guide.querySelector('#bsw-editor-progress-label').textContent=fields.length ? (filled+' of '+fields.length+' required fields completed ('+pct+'%) — WordPress and BIGSHOR validation remains in control.') : 'No visible required fields detected in the current editor view.';
    }
    poststuff.addEventListener('input',updateProgress); poststuff.addEventListener('change',updateProgress); updateProgress();
    // Preserve WordPress' native required attributes and validation; this is navigation only.
  });
})();
JS;
        wp_add_inline_script('bsw-friendly-editor', $js);
    }
}
BSW_Friendly_Editor_Experience::init();
}
