
(function(){
'use strict';
function ready(fn){if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',fn);else fn();}
ready(function(){
 var nav=document.querySelector('.bsw-shell-sidebar nav');
 var items=[];
 if(nav){nav.querySelectorAll('a[href]').forEach(function(a){items.push({label:(a.innerText||a.textContent||'').trim(),url:a.href,kind:'Navigation'});});}
 var shortcuts=[
  {label:'Create a new article',url:(window.BSWAdminUX||{}).studio+'&action=new',kind:'Action'},
  {label:'Open Article Studio',url:(window.BSWAdminUX||{}).studio,kind:'Workspace'},
  {label:'Browse all articles',url:(window.BSWAdminUX||{}).search,kind:'Content'},
  {label:'Open media library',url:'/wp-admin/upload.php',kind:'Content'},
  {label:'Manage contributors',url:'/wp-admin/users.php',kind:'Administration'}
 ];
 shortcuts.forEach(function(x){try{x.url=new URL(x.url,location.origin).href;}catch(e){}});
 var overlay=document.createElement('div');overlay.className='bsw-global-search';overlay.setAttribute('role','dialog');overlay.setAttribute('aria-modal','true');overlay.setAttribute('aria-label','Search BIGSHOR WIKI');
 overlay.innerHTML='<div class="bsw-global-search-dialog"><input type="search" aria-label="Search destinations" placeholder="Search pages, tools and actions…"><div class="bsw-global-search-results" role="listbox"></div><div class="bsw-search-hint">Navigate with ↑ ↓ · Enter to open · Esc to close <span class="bsw-ux-shortcut">Ctrl K</span></div></div>';
 document.body.appendChild(overlay);
 var input=overlay.querySelector('input'),results=overlay.querySelector('.bsw-global-search-results'),active=0,shown=[];
 function open(){overlay.classList.add('is-open');input.value='';render('');input.focus();}
 function close(){overlay.classList.remove('is-open');}
 function render(q){
  q=(q||'').toLowerCase();shown=items.concat(shortcuts).filter(function(x){return !q||(x.label+' '+x.kind).toLowerCase().indexOf(q)!==-1;}).slice(0,18);active=0;
  results.innerHTML='';
  if(!shown.length){results.textContent='No matching destinations';return;}
  shown.forEach(function(x,i){var a=document.createElement('a');a.href=x.url;a.setAttribute('role','option');a.setAttribute('aria-selected',i===active?'true':'false');var b=document.createElement('span');b.textContent=x.label;var sm=document.createElement('small');sm.textContent=x.kind;a.appendChild(b);a.appendChild(sm);a.addEventListener('mouseenter',function(){active=i;sync();});results.appendChild(a);});
 }
 function sync(){Array.prototype.forEach.call(results.children,function(a,i){a.setAttribute('aria-selected',i===active?'true':'false');});}
 input.addEventListener('input',function(){render(input.value);});
 input.addEventListener('keydown',function(e){
  if(e.key==='Escape'){close();return;}
  if(e.key==='ArrowDown'){e.preventDefault();active=Math.min(active+1,shown.length-1);sync();}
  if(e.key==='ArrowUp'){e.preventDefault();active=Math.max(0,active-1);sync();}
  if(e.key==='Enter'&&shown[active]){location.href=shown[active].url;}
 });
 overlay.addEventListener('click',function(e){if(e.target===overlay)close();});
 document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();open();}if(e.key==='Escape'&&overlay.classList.contains('is-open'))close();});
 // Add a discoverable search control to the global shell.
 var top=document.querySelector('.bsw-shell-topbar,.bsw-shell-header');
 if(top&&!top.querySelector('.bsw-open-global-search')){var b=document.createElement('button');b.type='button';b.className='button bsw-open-global-search';b.textContent='⌕  Search';b.addEventListener('click',open);top.appendChild(b);}
 // Improve destructive-action affordance without intercepting native submit flows.
 document.querySelectorAll('a.submitdelete, .row-actions .delete a').forEach(function(a){a.setAttribute('aria-label',(a.textContent||'Delete').trim()+' — destructive action');});
});
})();
