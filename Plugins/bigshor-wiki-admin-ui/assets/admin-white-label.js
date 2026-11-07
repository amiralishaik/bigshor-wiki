(function(){
  'use strict';
  var STORAGE_THEME='bsw-admin-theme';
  var STORAGE_SIDEBAR='bsw-admin-sidebar';
  var STORAGE_FOCUS='bsw-editor-focus';
  function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
  function safeGet(key){try{return window.localStorage.getItem(key);}catch(e){return null;}}
  function safeSet(key,value){try{window.localStorage.setItem(key,value);}catch(e){}}
  ready(function(){
    var body=document.body;
    var sidebar=document.querySelector('.bsw-shell-sidebar');
    var toggle=document.querySelector('.bsw-shell-toggle');
    var themeToggle=document.querySelector('.bsw-theme-toggle');
    var themeLabel=document.querySelector('.bsw-theme-label');
    var themeIcon=document.querySelector('.bsw-theme-icon');
    var focusToggle=document.querySelector('.bsw-editor-mode-toggle');
    var mobileQuery=window.matchMedia('(max-width: 900px)');

    // Persist the user's colour preference on this browser, without changing WordPress settings.
    var savedTheme=safeGet(STORAGE_THEME);
    if(savedTheme==='dark') body.classList.add('bsw-dark-mode');
    function syncThemeControl(){
      var dark=body.classList.contains('bsw-dark-mode');
      if(themeToggle) themeToggle.setAttribute('aria-pressed',dark?'true':'false');
      if(themeLabel) themeLabel.textContent=dark?'Light mode':'Dark mode';
      if(themeIcon) themeIcon.textContent=dark?'☀':'◐';
    }
    syncThemeControl();
    if(themeToggle){
      themeToggle.addEventListener('click',function(){
        body.classList.toggle('bsw-dark-mode');
        safeSet(STORAGE_THEME,body.classList.contains('bsw-dark-mode')?'dark':'light');
        syncThemeControl();
      });
    }

    // Desktop: compact/collapsed sidebar. Mobile/tablet: accessible slide-in drawer.
    var savedSidebar=safeGet(STORAGE_SIDEBAR);
    if(savedSidebar==='collapsed' && !mobileQuery.matches) body.classList.add('bsw-sidebar-collapsed');
    var overlay=null;
    if(sidebar){
      overlay=document.createElement('button');
      overlay.type='button';
      overlay.className='bsw-shell-overlay';
      overlay.setAttribute('aria-label','Close navigation');
      overlay.setAttribute('tabindex','-1');
      overlay.style.cssText='display:none;position:fixed;inset:0;z-index:100000;border:0;background:rgba(20,24,40,.42);';
      document.body.appendChild(overlay);
      function closeDrawer(){
        body.classList.remove('bsw-nav-open');
        if(toggle) toggle.setAttribute('aria-expanded','false');
        overlay.style.display='none';
      }
      function openDrawer(){
        body.classList.add('bsw-nav-open');
        if(toggle) toggle.setAttribute('aria-expanded','true');
        overlay.style.display='block';
      }
      if(toggle){
        toggle.addEventListener('click',function(){
          if(mobileQuery.matches){
            body.classList.contains('bsw-nav-open')?closeDrawer():openDrawer();
          }else{
            body.classList.toggle('bsw-sidebar-collapsed');
            safeSet(STORAGE_SIDEBAR,body.classList.contains('bsw-sidebar-collapsed')?'collapsed':'expanded');
          }
        });
      }
      overlay.addEventListener('click',closeDrawer);
      document.addEventListener('keydown',function(e){
        if(e.key==='Escape') closeDrawer();
        if((e.ctrlKey||e.metaKey) && e.key==='\\'){
          if(!mobileQuery.matches && toggle){e.preventDefault();toggle.click();}
        }
      });
      sidebar.querySelectorAll('a').forEach(function(a){
        a.addEventListener('click',function(){if(mobileQuery.matches)closeDrawer();});
      });
      if(mobileQuery.addEventListener){
        mobileQuery.addEventListener('change',function(){
          closeDrawer();
          if(mobileQuery.matches) body.classList.remove('bsw-sidebar-collapsed');
          else if(savedSidebar==='collapsed') body.classList.add('bsw-sidebar-collapsed');
        });
      }
    }

    // Standard editor stays intact. Editors can switch into a distraction-reduced workspace.
    var savedFocus=safeGet(STORAGE_FOCUS);
    if(savedFocus==='on') body.classList.add('bsw-editor-focus');
    function syncFocus(){
      var active=body.classList.contains('bsw-editor-focus');
      if(focusToggle){
        focusToggle.setAttribute('aria-pressed',active?'true':'false');
        focusToggle.textContent=active?'Exit focus':'Focus mode';
      }
    }
    syncFocus();
    if(focusToggle){
      focusToggle.addEventListener('click',function(){
        body.classList.toggle('bsw-editor-focus');
        safeSet(STORAGE_FOCUS,body.classList.contains('bsw-editor-focus')?'on':'off');
        syncFocus();
      });
    }

    // Prevent duplicate custom dashboard sidebars where the legacy control-centre app
    // renders its own navigation inside the new platform shell.
    var params=new URLSearchParams(window.location.search);
    if(params.get('page')==='bsw-control-centre'){
      var root=document.querySelector('#wpbody-content');
      if(root){
        var candidates=root.querySelectorAll('aside, nav, [role="navigation"], [class*="sidebar"], [class*="side-nav"]');
        candidates.forEach(function(el){
          if(el.closest('.bsw-shell-sidebar')) return;
          var rect=el.getBoundingClientRect();
          var txt=(el.innerText||'').toLowerCase();
          var repeated=(txt.indexOf('dashboard')!==-1 && txt.indexOf('articles')!==-1 && (txt.indexOf('bigshor')!==-1 || txt.indexOf('wiki')!==-1));
          var narrow=rect.width>=65 && rect.width<=360;
          if(repeated && narrow){
            el.style.setProperty('display','none','important');
            var parent=el.parentElement;
            if(parent && parent.children.length===1 && parent!==root) parent.style.setProperty('display','none','important');
          }
        });
      }
    }
    // Quick menu search filters only the single canonical BIGSHOR sidebar.
    var menuSearch=document.querySelector('.bsw-nav-search-input');
    if(menuSearch&&sidebar){
      var menuLinks=Array.prototype.slice.call(sidebar.querySelectorAll('.bsw-shell-link'));
      menuSearch.addEventListener('input',function(){
        var q=(menuSearch.value||'').trim().toLowerCase(), visible=0;
        menuLinks.forEach(function(link){
          var show=!q||(link.textContent||'').toLowerCase().indexOf(q)!==-1;
          link.style.display=show?'flex':'none';
          if(show)visible++;
        });
        sidebar.querySelectorAll('.bsw-shell-group').forEach(function(group){
          var has=Array.prototype.some.call(group.querySelectorAll('.bsw-shell-link'),function(link){return link.style.display!=='none';});
          group.style.display=has?'':'none';
        });
        var empty=sidebar.querySelector('.bsw-nav-empty');
        if(q&&visible===0){
          if(!empty){empty=document.createElement('p');empty.className='bsw-nav-empty';empty.textContent='No matching menu items';sidebar.querySelector('nav').appendChild(empty);}
          empty.style.display='block';
        }else if(empty){empty.style.display='none';}
      });
      document.addEventListener('keydown',function(e){
        if(e.key==='/'&&!e.ctrlKey&&!e.metaKey&&!e.altKey&&!/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)){
          e.preventDefault();menuSearch.focus();
        }
        if(e.key==='Escape'&&document.activeElement===menuSearch){
          menuSearch.value='';menuSearch.dispatchEvent(new Event('input'));menuSearch.blur();
        }
      });
    }
    var content=document.querySelector('#wpbody-content');
    if(content) content.setAttribute('data-bsw-workspace','true');
  });
})();