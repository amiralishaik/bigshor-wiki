(function () {
  'use strict';
  var doc=document, root=doc.documentElement;
  function setupMenu(){
    var btn=doc.querySelector('.bsw-mobile-menu-toggle'),nav=doc.querySelector('#bsw-primary-navigation'),backdrop=doc.querySelector('.bsw-menu-backdrop'),closeBtn=doc.querySelector('.bsw-menu-close');
    if(!btn||!nav)return;
    var mobileQuery=window.matchMedia('(max-width: 1023px)');
    nav.inert=mobileQuery.matches;nav.setAttribute('aria-hidden',String(mobileQuery.matches));
    function setOpen(open){
      nav.classList.toggle('is-open',open);btn.setAttribute('aria-expanded',String(open));
      btn.setAttribute('aria-label',open?'Close navigation menu':'Open navigation menu');
      nav.inert=!open;nav.setAttribute('aria-hidden',String(!open));
      if(backdrop){backdrop.hidden=!open;backdrop.classList.toggle('is-visible',open);}
      doc.body.classList.toggle('bsw-menu-open',open);
      if(open){var first=nav.querySelector('a,button');if(first)first.focus();}else{btn.focus({preventScroll:true});}
    }
    function close(){setOpen(false);}
    btn.addEventListener('click',function(){setOpen(btn.getAttribute('aria-expanded')!=='true');});
    if(backdrop)backdrop.addEventListener('click',close);
    if(closeBtn)closeBtn.addEventListener('click',close);
    nav.addEventListener('click',function(e){var link=e.target.closest('a');if(link&&mobileQuery.matches&&!e.target.closest('.bsw-submenu-toggle'))close();});
    doc.addEventListener('keydown',function(e){if(e.key==='Escape'&&btn.getAttribute('aria-expanded')==='true')close();});
    function syncSubmenus(){nav.querySelectorAll('li.menu-item-has-children > .sub-menu').forEach(function(submenu){var toggle=submenu.parentNode.querySelector(':scope > .bsw-submenu-toggle');if(!mobileQuery.matches){submenu.hidden=false;if(toggle)toggle.setAttribute('aria-expanded','true');}else{submenu.hidden=!(toggle&&toggle.getAttribute('aria-expanded')==='true');}});}
    window.addEventListener('resize',function(){if(!mobileQuery.matches&&btn.getAttribute('aria-expanded')==='true')close();syncSubmenus();});
    nav.querySelectorAll('li.menu-item-has-children').forEach(function(item){
      var submenu=item.querySelector(':scope > .sub-menu');if(!submenu)return;
      var link=item.querySelector(':scope > a');if(!link)return;
      var toggle=doc.createElement('button');toggle.type='button';toggle.className='bsw-submenu-toggle';toggle.setAttribute('aria-expanded',mobileQuery.matches?'false':'true');toggle.setAttribute('aria-label','Toggle '+link.textContent.trim()+' submenu');toggle.innerHTML='<span aria-hidden="true">⌄</span>';
      link.insertAdjacentElement('afterend',toggle);submenu.hidden=mobileQuery.matches;
      toggle.addEventListener('click',function(){var expanded=toggle.getAttribute('aria-expanded')==='true';toggle.setAttribute('aria-expanded',String(!expanded));submenu.hidden=expanded;item.classList.toggle('submenu-open',!expanded);});
    });
    syncSubmenus();
  }
  function setupTheme(){root.removeAttribute('data-theme-mode');root.setAttribute('data-theme-mode','light');var btn=doc.querySelector('.bsw-theme-toggle');if(btn){btn.remove();}}
  function setupProgress(){var bar=doc.querySelector('.bsw-reading-progress span'),article=doc.querySelector('.bsw-article-content');if(!bar||!article)return;var ticking=false;function update(){var rect=article.getBoundingClientRect(),top=window.pageYOffset+rect.top,bottom=top+article.offsetHeight-window.innerHeight,den=Math.max(1,bottom-top),pct=Math.max(0,Math.min(100,(window.pageYOffset-top)/den*100));bar.style.width=pct+'%';ticking=false;}window.addEventListener('scroll',function(){if(!ticking){window.requestAnimationFrame(update);ticking=true;}},{passive:true});window.addEventListener('resize',update);update();}
  function setupCopyLinks(){doc.querySelectorAll('[data-bsw-copy-link]').forEach(function(btn){btn.addEventListener('click',function(){if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(window.location.href).then(function(){btn.textContent='Link copied';}).catch(function(){btn.textContent='Copy unavailable';});}});});}
  function init(){setupMenu();setupTheme();setupProgress();setupCopyLinks();}
  if(doc.readyState==='loading')doc.addEventListener('DOMContentLoaded',init);else init();
}());


/* BIGSHOR mobile Wikipedia-style sidebar */
document.addEventListener('DOMContentLoaded', function () {
  var button = document.querySelector('.mw-sidebar-toggle');
  var sidebar = document.getElementById('mw-sidebar');
  if (!button || !sidebar) return;

  var mobile = window.matchMedia('(max-width: 650px)');
  function setOpen(open, restoreFocus) {
    sidebar.classList.toggle('is-open', open && mobile.matches);
    button.setAttribute('aria-expanded', open && mobile.matches ? 'true' : 'false');
    sidebar.setAttribute('aria-hidden', mobile.matches && !open ? 'true' : 'false');
    sidebar.inert = mobile.matches && !open;
    document.body.classList.toggle('mw-sidebar-open', open && mobile.matches);
    if (open && mobile.matches) {
      var first = sidebar.querySelector('a, button');
      if (first) first.focus();
    } else if (restoreFocus) {
      button.focus({preventScroll:true});
    }
  }

  sidebar.inert = mobile.matches;
  sidebar.setAttribute('aria-hidden', String(mobile.matches));
  button.addEventListener('click', function () {
    setOpen(button.getAttribute('aria-expanded') !== 'true', false);
  });
  document.addEventListener('click', function (event) {
    if (button.getAttribute('aria-expanded') !== 'true') return;
    if (!sidebar.contains(event.target) && !button.contains(event.target)) setOpen(false, false);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
      setOpen(false, true);
    }
  });
  sidebar.addEventListener('click', function (event) {
    if (mobile.matches && event.target.closest('a')) setOpen(false, false);
  });
  function syncViewport() {
    if (!mobile.matches) {
      sidebar.classList.remove('is-open');
      document.body.classList.remove('mw-sidebar-open');
      sidebar.inert = false;
      sidebar.setAttribute('aria-hidden', 'false');
      button.setAttribute('aria-expanded', 'false');
    } else if (button.getAttribute('aria-expanded') !== 'true') {
      sidebar.inert = true;
      sidebar.setAttribute('aria-hidden', 'true');
    }
  }
  if (mobile.addEventListener) mobile.addEventListener('change', syncViewport);
  else mobile.addListener(syncViewport);
});


/* BIGSHOR v20.4.0: persistent reader appearance controls. */
(function () {
  'use strict';
  var doc = document, root = doc.documentElement, key = 'bigshor-reader-appearance';
  function readPrefs() {
    try { return JSON.parse(window.localStorage.getItem(key) || '{}'); }
    catch (e) { return {}; }
  }
  function savePrefs(prefs) {
    try { window.localStorage.setItem(key, JSON.stringify(prefs)); } catch (e) {}
  }
  function applyPrefs(prefs) {
    root.setAttribute('data-mw-text-size', prefs.text || 'standard');
    root.setAttribute('data-mw-content-width', prefs.width || 'standard');
    root.setAttribute('data-mw-colour', prefs.colour || 'light');
    doc.querySelectorAll('[data-mw-text],[data-mw-width],[data-mw-colour]').forEach(function (button) {
      var active = (button.hasAttribute('data-mw-text') && button.getAttribute('data-mw-text') === (prefs.text || 'standard')) ||
        (button.hasAttribute('data-mw-width') && button.getAttribute('data-mw-width') === (prefs.width || 'standard')) ||
        (button.hasAttribute('data-mw-colour') && button.getAttribute('data-mw-colour') === (prefs.colour || 'light'));
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }
  function initAppearance() {
    var toggle = doc.querySelector('.mw-appearance-toggle');
    var panel = doc.getElementById('mw-appearance-panel');
    if (!toggle || !panel) return;
    var prefs = readPrefs();
    applyPrefs(prefs);
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      panel.hidden = !open;
    });
    panel.addEventListener('click', function (event) {
      var button = event.target.closest('button');
      if (!button) return;
      if (button.hasAttribute('data-mw-text')) prefs.text = button.getAttribute('data-mw-text');
      if (button.hasAttribute('data-mw-width')) prefs.width = button.getAttribute('data-mw-width');
      if (button.hasAttribute('data-mw-colour')) prefs.colour = button.getAttribute('data-mw-colour');
      if (button.hasAttribute('data-mw-reset')) prefs = {};
      applyPrefs(prefs);
      savePrefs(prefs);
    });
    doc.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden) {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', initAppearance);
  else initAppearance();
}());

/* BIGSHOR v20.5.0 global search suggestions */
(function(){
 'use strict';
 var timer=0;
 document.addEventListener('input',function(e){
  var input=e.target.closest('.bsw-global-search [data-bsw-live-search]');
  if(!input)return;
  var form=input.closest('.bsw-global-search'),box=form&&form.querySelector('[data-bsw-suggestions]');
  if(!box)return;
  var q=input.value.trim();
  if(q.length<2){box.hidden=true;box.innerHTML='';return;}
  window.clearTimeout(timer);
  timer=window.setTimeout(function(){
   var root=(window.BSWReader&&window.BSWReader.root)||((window.wpApiSettings&&window.wpApiSettings.root)||'/wp-json/')+'bigshor/v1/';
   fetch(root+'search-suggest?q='+encodeURIComponent(q),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(rows){
    box.innerHTML='';
    (rows||[]).forEach(function(row){
      var a=document.createElement('a');a.href=row.url;a.textContent=row.title;
      var small=document.createElement('small');small.textContent=(row.type||'Knowledge')+(row.excerpt?' · '+row.excerpt:'');a.appendChild(small);box.appendChild(a);
    });
    box.hidden=!box.children.length;
   }).catch(function(){box.hidden=true;});
  },180);
 });
 document.addEventListener('keydown',function(e){if(e.key==='Escape'){document.querySelectorAll('[data-bsw-suggestions]').forEach(function(x){x.hidden=true;});}});
}());


/* BIGSHOR article reader controls and accessible section navigation v23.2.0 */
(function(){'use strict';var d=document;function init(){var article=d.querySelector('.bsw-article-content'),toc=d.querySelector('.bsw-toc-card');if(article&&toc){var hs=article.querySelectorAll('h2,h3,h4'),ol=d.createElement('ol');if(!hs.length){toc.innerHTML='<h2>Contents</h2><p>No sections available.</p>';}else{hs.forEach(function(h,i){if(!h.id)h.id='bsw-heading-'+(i+1);var li=d.createElement('li'),a=d.createElement('a');li.className='bsw-toc-level-'+h.tagName.substring(1);a.href='#'+h.id;a.textContent=h.textContent.trim();li.appendChild(a);ol.appendChild(li);});toc.innerHTML='<h2>Contents</h2>';toc.appendChild(ol);}}
var root=d.documentElement,up=d.querySelector('[data-bsw-font-up]'),down=d.querySelector('[data-bsw-font-down]');function setSize(n){n=Math.max(15,Math.min(23,n));root.style.setProperty('--bsw-reader-font',n+'px');if(article)article.style.fontSize=n+'px';try{localStorage.setItem('bsw-reader-font',String(n));}catch(e){}}var initial=17;try{initial=parseInt(localStorage.getItem('bsw-reader-font'),10)||17;}catch(e){}setSize(initial);if(up)up.addEventListener('click',function(){setSize((parseInt(getComputedStyle(article).fontSize,10)||17)+1);});if(down)down.addEventListener('click',function(){setSize((parseInt(getComputedStyle(article).fontSize,10)||17)-1);});
var toggle=d.querySelector('.mw-sidebar-toggle'),side=d.getElementById('mw-sidebar');if(toggle&&side){d.addEventListener('keydown',function(e){if(e.key==='Escape'&&side.classList.contains('is-open')){side.classList.remove('is-open');toggle.setAttribute('aria-expanded','false');toggle.focus();}});}
}if(d.readyState==='loading')d.addEventListener('DOMContentLoaded',init);else init();}());
