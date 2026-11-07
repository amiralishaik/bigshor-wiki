(function($){
  'use strict';
  var form=document.getElementById('bsw-studio-form'); if(!form)return;
  var visual=document.getElementById('bsw-visual-panel'), source=document.getElementById('bsw-source-panel'), mode=document.getElementById('bsw_editor_mode');
  var sourceArea=document.getElementById('bsw_source_content'), title=document.getElementById('bsw_article_title'), saveState=document.getElementById('bsw-studio-save-indicator');
  var storageKey=(window.BSWStudio&&BSWStudio.storageKey)||'bsw-studio-recovery';
  function editor(){return window.tinymce&&tinymce.get('bsw_studio_visual_content');}
  function getVisual(){var ed=editor();if(ed)return ed.getContent();var el=document.getElementById('bsw_studio_visual_content');return el?el.value:'';}
  function setVisual(value){var ed=editor();if(ed)ed.setContent(value||'');else{var el=document.getElementById('bsw_studio_visual_content');if(el)el.value=value||'';}}
  function htmlToWiki(html){var box=document.createElement('div');box.innerHTML=html||'';box.querySelectorAll('h2,h3,h4').forEach(function(n){var level=Number(n.tagName.substring(1));n.replaceWith(document.createTextNode('\n\n'+ '='.repeat(level)+' '+n.textContent.trim()+' '+'='.repeat(level)+'\n\n'));});box.querySelectorAll('strong,b').forEach(function(n){n.replaceWith(document.createTextNode("'''"+n.textContent+"'''"));});box.querySelectorAll('em,i').forEach(function(n){n.replaceWith(document.createTextNode("''"+n.textContent+"''"));});box.querySelectorAll('a').forEach(function(n){var href=n.getAttribute('href')||'';var text=n.textContent||href;n.replaceWith(document.createTextNode(href.indexOf(location.origin)===0?'[['+decodeURIComponent(href.split('/').filter(Boolean).pop()||text)+'|'+text+']]':'['+href+' '+text+']'));});box.querySelectorAll('li').forEach(function(n){n.insertBefore(document.createTextNode('* '),n.firstChild);});return box.innerText.replace(/\n{3,}/g,'\n\n').trim();}
  function inlineWiki(value){var t=escapeHtml(value);t=t.replace(/'''(.+?)'''/g,'<strong>$1</strong>').replace(/''(.+?)''/g,'<em>$1</em>');t=t.replace(/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]/g,function(_,target,label){var slug=target.trim().toLowerCase().replace(/[^a-z0-9 -]/g,'').replace(/\s+/g,'-');return '<a href="'+location.origin+'/wiki/'+encodeURIComponent(slug)+'/">'+(label||target)+'</a>';});t=t.replace(/\[(https?:\/\/[^\s\]]+)(?:\s+([^\]]+))?\]/g,function(_,url,label){return '<a href="'+url+'" rel="nofollow noopener">'+(label||url)+'</a>';});return t;}
  function wikiToHtml(text){var lines=(text||'').split(/\r?\n/),out=[],list=null;lines.forEach(function(line){var m=line.match(/^(={2,4})\s*(.*?)\s*\1$/);if(m){if(list){out.push('</'+list+'>');list=null;}var level=Math.min(4,m[1].length);out.push('<h'+level+'>'+inlineWiki(m[2])+'</h'+level+'>');return;}var bullet=line.match(/^\*\s+(.*)$/),number=line.match(/^#\s+(.*)$/);if(bullet||number){var wanted=bullet?'ul':'ol';if(list!==wanted){if(list)out.push('</'+list+'>');out.push('<'+wanted+'>');list=wanted;}out.push('<li>'+inlineWiki((bullet||number)[1])+'</li>');return;}if(list){out.push('</'+list+'>');list=null;}if(/^\s*$/.test(line))return;if(/^\s*>/.test(line)){out.push('<blockquote><p>'+inlineWiki(line.replace(/^\s*>\s?/,''))+'</p></blockquote>');return;}out.push('<p>'+inlineWiki(line)+'</p>');});if(list)out.push('</'+list+'>');return out.join('\n');}
  function escapeHtml(s){return String(s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
  function setMode(next){var current=mode.value;if(next===current)return;if(next==='source'){sourceArea.value=htmlToWiki(getVisual());visual.hidden=true;source.hidden=false;mode.value='source';}else{setVisual(wikiToHtml(sourceArea.value));source.hidden=true;visual.hidden=false;mode.value='visual';}document.querySelectorAll('.bsw-mode-switch button').forEach(function(b){b.classList.toggle('is-active',b.dataset.mode===next);});updateStats();}
  document.querySelectorAll('.bsw-mode-switch button').forEach(function(b){b.addEventListener('click',function(){setMode(b.dataset.mode);});});
  function currentContent(){return mode.value==='source'?sourceArea.value:getVisual();}
  function plainText(){var div=document.createElement('div');div.innerHTML=mode.value==='source'?sourceArea.value.replace(/<[^>]*>/g,' '):getVisual();return (div.textContent||div.innerText||'').trim();}
  function updateStats(){var words=plainText().split(/\s+/).filter(Boolean).length;var w=document.getElementById('bsw-word-count');var r=document.getElementById('bsw-read-time');if(w)w.textContent=words.toLocaleString()+' words';if(r)r.textContent=Math.max(1,Math.ceil(words/220))+' min read';updateOutline();}
  function updateOutline(){var nav=document.getElementById('bsw-article-outline');if(!nav)return;var headings=[];if(mode.value==='source'){var lines=sourceArea.value.split(/\r?\n/);lines.forEach(function(line,index){var m=line.match(/^(={2,4})\s*(.*?)\s*\1$/);if(m)headings.push({title:m[2].trim(),level:m[1].length,line:index});});}else{var ed=editor();var html=ed?ed.getContent():getVisual();var box=document.createElement('div');box.innerHTML=html;box.querySelectorAll('h2,h3,h4').forEach(function(h,index){headings.push({title:h.textContent.trim(),level:Number(h.tagName.substring(1)),index:index});});}nav.innerHTML='';if(!headings.length){nav.innerHTML='<p class=\"bsw-outline-empty\">Add headings to build your outline.</p>';return;}headings.forEach(function(h){var b=document.createElement('button');b.type='button';b.className='bsw-outline-item level-'+h.level;b.textContent=h.title;b.addEventListener('click',function(){if(mode.value==='source'){var lines=sourceArea.value.split(/\r?\n/),pos=0;for(var i=0;i<h.line;i++)pos+=lines[i].length+1;sourceArea.focus();sourceArea.setSelectionRange(pos,pos+lines[h.line].length);}else{var ed=editor();if(ed){var hs=ed.getBody().querySelectorAll('h2,h3,h4');if(hs[h.index]){ed.focus();ed.selection.select(hs[h.index]);ed.selection.collapse(true);}}}});nav.appendChild(b);});}
  function fieldValue(name){var el=form.querySelector('[name="'+name+'"]');if(!el&&name==='article_topic')el=form.querySelector('[name="article_topic[]"]');if(!el)return '';if(el.multiple)return Array.prototype.map.call(el.selectedOptions,function(o){return o.value;});return el.value;}
  function saveRecovery(){try{var data={title:title.value,mode:mode.value,content:currentContent(),excerpt:fieldValue('article_excerpt'),slug:fieldValue('article_slug'),sources:fieldValue('sources_json'),infoboxTitle:fieldValue('infobox_title'),infoboxImage:fieldValue('infobox_image'),infoboxFields:fieldValue('infobox_fields'),seoTitle:fieldValue('seo_title'),seoDescription:fieldValue('seo_description'),language:fieldValue('article_language'),topic:fieldValue('article_topic'),review:fieldValue('editorial_status'),featured:fieldValue('featured_image_id'),summary:fieldValue('edit_summary'),updated:Date.now()};localStorage.setItem(storageKey,JSON.stringify(data));if(saveState)saveState.textContent='Recovery saved on this device';}catch(e){if(saveState)saveState.textContent='Local recovery unavailable';}}
  function restoreRecovery(){try{var raw=localStorage.getItem(storageKey);if(!raw)return;var data=JSON.parse(raw);if(!data||!data.updated||Date.now()-data.updated>86400000)return;if(!window.confirm('A local recovery copy from this device is available. Restore it?'))return;title.value=data.title||title.value;var mapping={article_excerpt:'excerpt',article_slug:'slug',sources_json:'sources',infobox_title:'infoboxTitle',infobox_image:'infoboxImage',infobox_fields:'infoboxFields',seo_title:'seoTitle',seo_description:'seoDescription',article_language:'language',article_topic:'topic',editorial_status:'review',featured_image_id:'featured',edit_summary:'summary'};Object.keys(mapping).forEach(function(name){var el=form.querySelector('[name="'+name+'"]');if(!el&&name==='article_topic')el=form.querySelector('[name="article_topic[]"]');if(el&&data[mapping[name]]!==undefined){if(el.multiple){var values=Array.isArray(data[mapping[name]])?data[mapping[name]]:[];Array.prototype.forEach.call(el.options,function(o){o.selected=values.indexOf(o.value)!==-1;});}else{el.value=data[mapping[name]];}}});if(data.mode==='source'){setMode('source');sourceArea.value=data.content||'';}else{setVisual(data.content||'');}if(saveState)saveState.textContent='Recovered unsaved changes';updateStats();}catch(e){}}
  restoreRecovery();
  try{var params=new URLSearchParams(location.search);if(params.has('bsw_studio_saved')){localStorage.removeItem(storageKey);if(BSWStudio&&BSWStudio.newStorageKey)localStorage.removeItem(BSWStudio.newStorageKey);}}catch(e){}
  var recoveryTimer=setInterval(saveRecovery,10000);
  form.addEventListener('input',updateStats);form.addEventListener('change',updateStats);
  form.addEventListener('submit',function(){if(mode.value==='visual'&&editor())editor().save();saveRecovery();var btn=document.activeElement;if(btn&&btn.name==='save_intent'&&btn.value==='submit'){if(!window.confirm('Submit this article using your current publishing permissions?'))return false;}});
  updateStats();
  var citationButton=document.getElementById('bsw-insert-citation');if(citationButton)citationButton.addEventListener('click',function(){var id=window.prompt('Enter the source ID from Sources & citations (for example source-1):');if(!id)return;var code='[bsw_cite id=\"'+id.replace(/[^a-zA-Z0-9_-]/g,'')+'\"]';if(mode.value==='source'){var a=sourceArea.selectionStart,b=sourceArea.selectionEnd;sourceArea.setRangeText(code,a,b,'end');sourceArea.focus();}else{var ed=editor();if(ed){ed.focus();ed.insertContent(code);}else{var ta=document.getElementById('bsw_studio_visual_content');if(ta)ta.value+=code;}}updateStats();});
  var linkButton=document.getElementById('bsw-insert-wikilink');if(linkButton)linkButton.addEventListener('click',function(){var target=window.prompt('Wiki article title:');if(!target)return;var label=window.prompt('Link label (leave blank to use the title):')||target;var code='[['+target+(label!==target?'|'+label:'')+']]';if(mode.value==='source'){sourceArea.setRangeText(code,sourceArea.selectionStart,sourceArea.selectionEnd,'end');sourceArea.focus();}else{var ed=editor();if(ed){ed.focus();ed.insertContent('<a href=\"'+location.origin+'/wiki/'+encodeURIComponent(target.toLowerCase().replace(/[^a-z0-9 -]/g,'').replace(/\s+/g,'-'))+'/\">'+escapeHtml(label)+'</a>');}}updateStats();});
  var templateButton=document.getElementById('bsw-insert-template');if(templateButton)templateButton.addEventListener('click',function(){if(mode.value!=='source'){window.alert('Switch to Wiki source mode to insert a parser template.');return;}var name=window.prompt('Template name (for example Infobox person):');if(!name)return;var code='{{'+name+'}}';sourceArea.setRangeText(code,sourceArea.selectionStart,sourceArea.selectionEnd,'end');sourceArea.focus();updateStats();});
  var tableButton=document.getElementById('bsw-insert-table');if(tableButton)tableButton.addEventListener('click',function(){var html='<table><tbody><tr><th>Heading</th><th>Details</th></tr><tr><td>Item</td><td>Description</td></tr></tbody></table><p></p>';if(mode.value==='source'){sourceArea.setRangeText('\n{| class=\"wikitable\"\n! Heading !! Details\n| Item || Description\n|}\n',sourceArea.selectionStart,sourceArea.selectionEnd,'end');sourceArea.focus();}else{var ed=editor();if(ed){ed.focus();ed.insertContent(html);}}updateStats();});
  if(window.tinymce){tinymce.on('AddEditor',function(event){if(event.editor.id==='bsw_studio_visual_content'){event.editor.on('keyup change SetContent',function(){updateStats();saveRecovery();});}});}
  var preview=document.getElementById('bsw-live-preview');var previewContent=document.getElementById('bsw-preview-content');
  function openPreview(){var html=mode.value==='source'?wikiToHtml(sourceArea.value):getVisual();var iframe=document.createElement('iframe');iframe.setAttribute('sandbox','');iframe.title='Article preview';iframe.className='bsw-preview-frame';var doc='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font:16px/1.75 Georgia,serif;color:#252b35;max-width:780px;margin:35px auto;padding:0 24px}h1,h2,h3{font-family:Arial,sans-serif;line-height:1.3}img{max-width:100%;height:auto}blockquote{border-left:3px solid #c62828;padding-left:18px;color:#555}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:8px}</style></head><body><h1>'+escapeHtml(title.value||'Untitled article')+'</h1>'+html+'</body></html>';previewContent.innerHTML='';previewContent.appendChild(iframe);iframe.srcdoc=doc;preview.hidden=false;preview.scrollIntoView({behavior:'smooth',block:'start'});}
  document.getElementById('bsw-preview-toggle').addEventListener('click',openPreview);document.getElementById('bsw-preview-close').addEventListener('click',function(){preview.hidden=true;});
  document.getElementById('bsw-source-example').addEventListener('click',function(){var example='[\n  {\n    "id": "source-1",\n    "author": "Author or organisation",\n    "title": "Title of the source",\n    "publisher": "Publisher",\n    "date": "2026-01-15",\n    "url": "https://example.com/article",\n    "accessed": "2026-11-07",\n    "doi": "",\n    "page": "",\n    "note": ""\n  }\n]';var area=document.getElementById('bsw_sources_json');if(area.value.trim()&&area.value.trim()!=='[]'&&!window.confirm('Replace the current source records with an example?'))return;area.value=example;});
  var mediaButton=document.getElementById('bsw-select-featured');if(mediaButton&&window.wp&&wp.media){var frame;mediaButton.addEventListener('click',function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:'Choose article featured image',button:{text:'Use featured image'},multiple:false,library:{type:'image'}});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();document.getElementById('bsw_featured_image_id').value=a.id;document.getElementById('bsw-featured-preview').innerHTML='<img src="'+escapeHtml(a.url)+'" alt="">';mediaButton.textContent='Change image';document.getElementById('bsw-remove-featured').hidden=false;});frame.open();});}
  var remove=document.getElementById('bsw-remove-featured');if(remove)remove.addEventListener('click',function(){document.getElementById('bsw_featured_image_id').value='';document.getElementById('bsw-featured-preview').innerHTML='<span>Choose an image for this article</span>';remove.hidden=true;mediaButton.textContent='Select featured image';});
  var seo=document.querySelector('[name="seo_description"]');var seoCount=document.getElementById('bsw-seo-count');function seoUpdate(){if(seoCount)seoCount.textContent=(seo.value||'').length+' / 180';}if(seo){seo.addEventListener('input',seoUpdate);seoUpdate();}
  window.addEventListener('beforeunload',function(){clearInterval(recoveryTimer);saveRecovery();});

  // Phase 2: structured references, server recovery, presence and editorial collaboration tools.
  function phase2Request(action, data, done) {
    if (!window.BSWStudio || !BSWStudio.ajaxUrl) return;
    var payload = new URLSearchParams(); payload.set('action', action); payload.set('nonce', BSWStudio.ajaxNonce || '');
    Object.keys(data || {}).forEach(function(k){ payload.set(k, data[k]); });
    fetch(BSWStudio.ajaxUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'}, body:payload.toString()})
      .then(function(r){return r.json();}).then(function(r){if(done)done(r);})
      .catch(function(){if(saveState)saveState.textContent='Server connection unavailable; local recovery remains active';});
  }
  var postId = Number((window.BSWStudio||{}).postId||0);
  var refArea = document.getElementById('bsw_sources_json');
  var refList = document.getElementById('bsw-reference-list');
  function readRefs(){try{var v=JSON.parse(refArea.value||'[]');return Array.isArray(v)?v:[];}catch(e){return [];}}
  function writeRefs(rows){refArea.value=JSON.stringify(rows,null,2);refArea.dispatchEvent(new Event('input',{bubbles:true}));}
  function renderRefs(){
    if(!refList||!refArea)return;
    var rows=readRefs();refList.innerHTML='';
    rows.forEach(function(row,index){
      var card=document.createElement('div');card.className='bsw-reference-card';
      var fields=[['id','Reference ID'],['author','Author'],['title','Source title'],['publisher','Publisher'],['date','Publication date'],['url','Source URL'],['doi','DOI'],['page','Page / section'],['accessed','Accessed date']];
      fields.forEach(function(f){var label=document.createElement('label');label.textContent=f[1];var input=document.createElement('input');input.type=f[0]==='url'?'url':'text';input.value=row[f[0]]||'';input.addEventListener('change',function(){rows[index][f[0]]=input.value;writeRefs(rows);});label.appendChild(input);card.appendChild(label);});
      var note=document.createElement('textarea');note.rows=2;note.placeholder='Research note (optional)';note.value=row.note||'';note.addEventListener('change',function(){rows[index].note=note.value;writeRefs(rows);});card.appendChild(note);
      var cite=document.createElement('button');cite.type='button';cite.className='bsw-btn bsw-btn-light';cite.textContent='Insert citation';cite.addEventListener('click',function(){var id=String(rows[index].id||'').replace(/[^a-zA-Z0-9_-]/g,'');if(!id){window.alert('Add a unique reference ID first.');return;}var token='[bsw_cite id="'+id+'"]';if(mode.value==='source'){sourceArea.setRangeText(token,sourceArea.selectionStart,sourceArea.selectionEnd,'end');sourceArea.focus();}else{var ed=editor();if(ed){ed.focus();ed.insertContent(token);}}});card.appendChild(cite);
      var remove=document.createElement('button');remove.type='button';remove.className='bsw-text-button';remove.textContent='Remove reference';remove.addEventListener('click',function(){rows.splice(index,1);writeRefs(rows);renderRefs();});card.appendChild(remove);refList.appendChild(card);
    });
  }
  var addRef=document.getElementById('bsw-add-reference');
  if(addRef)addRef.addEventListener('click',function(){var rows=readRefs();var n=rows.length+1;rows.push({id:'source-'+n,author:'',title:'',publisher:'',date:'',url:'',accessed:'',doi:'',page:'',note:''});writeRefs(rows);renderRefs();});
  if(refArea){renderRefs();refArea.addEventListener('change',renderRefs);}
  var selectedQuote='';
  function captureSelection(){
    var value='';
    if(mode.value==='source'){var a=sourceArea.selectionStart,b=sourceArea.selectionEnd;value=sourceArea.value.slice(a,b);}
    else {var ed=editor();if(ed){var node=ed.selection.getNode();value=ed.selection.getContent({format:'text'})||'';}}
    selectedQuote=String(value||'').trim().slice(0,500);
    var anchor=document.getElementById('bsw-anchor-quote');if(anchor)anchor.value=selectedQuote;
  }
  if(sourceArea)sourceArea.addEventListener('mouseup',captureSelection);
  if(sourceArea)sourceArea.addEventListener('keyup',captureSelection);
  if(window.tinymce)tinymce.on('AddEditor',function(e){e.editor.on('mouseup keyup',captureSelection);});
  function renderDiscussion(target,items,kind){
    if(!target)return;target.innerHTML='';
    (items||[]).forEach(function(item){var card=document.createElement('div');card.className='bsw-editorial-item';var head=document.createElement('strong');head.textContent=(item.author||'Contributor')+' · '+(item.created||'');var body=document.createElement('p');body.textContent=item.text||'';card.appendChild(head);if(item.quote){var quote=document.createElement('blockquote');quote.textContent='“'+item.quote+'”';card.appendChild(quote);}card.appendChild(body);
      var state=document.createElement('small');state.textContent='Status: '+(item.status|| (kind==='comment'?'open':'pending'));card.appendChild(state);
      if(kind==='suggestion'&&item.status==='pending'&&window.BSWStudio&&window.BSWStudio.canReview){['accepted','rejected'].forEach(function(decision){var btn=document.createElement('button');btn.type='button';btn.className='bsw-btn bsw-btn-light bsw-review-action';btn.textContent=decision==='accepted'?'Accept':'Reject';btn.addEventListener('click',function(){phase2Request('bsw_studio_review_item',{post_id:postId,kind:kind,item_id:item.id,decision:decision},function(r){if(r.success){renderDiscussion(suggestionList,r.data.items,'suggestion');renderActivity(r.data.activity);}else window.alert(r.data&&r.data.message||'Review action failed.');});});card.appendChild(btn);});}
      if(kind==='comment'&&item.status==='open'&&window.BSWStudio&&window.BSWStudio.canReview){var resolve=document.createElement('button');resolve.type='button';resolve.className='bsw-btn bsw-btn-light bsw-review-action';resolve.textContent='Resolve';resolve.addEventListener('click',function(){phase2Request('bsw_studio_review_item',{post_id:postId,kind:'comment',item_id:item.id,decision:'resolved'},function(r){if(r.success){renderDiscussion(commentList,r.data.items,'comment');renderActivity(r.data.activity);}});});card.appendChild(resolve);}
      target.appendChild(card);
    });
    if(!items||!items.length)target.textContent=kind==='comment'?'No editorial comments yet.':'No suggested changes yet.';
  }
  var commentList=document.getElementById('bsw-editorial-comments'),suggestionList=document.getElementById('bsw-suggestion-list');
  renderDiscussion(commentList,(window.BSWStudio||{}).comments,'comment');renderDiscussion(suggestionList,(window.BSWStudio||{}).suggestions,'suggestion');
  function renderActivity(items){var target=document.getElementById('bsw-activity-timeline');if(!target)return;target.innerHTML='';(items||[]).slice().reverse().forEach(function(item){var row=document.createElement('div');row.className='bsw-editorial-item';var title=document.createElement('strong');title.textContent=(item.event||'Activity')+' · '+(item.user||'Editor');var detail=document.createElement('p');detail.textContent=(item.detail||'')+' '+(item.created||'');row.appendChild(title);row.appendChild(detail);target.appendChild(row);});if(!items||!items.length)target.textContent='No editorial activity recorded yet.';}
  renderActivity((window.BSWStudio||{}).activity);
  var commentBtn=document.getElementById('bsw-add-comment');
  if(commentBtn)commentBtn.addEventListener('click',function(){var area=document.getElementById('bsw-comment-text');if(!postId){window.alert('Save the article once before adding editorial comments.');return;}phase2Request('bsw_studio_comment',{post_id:postId,comment:area.value,quote:selectedQuote},function(r){if(r.success){renderDiscussion(commentList,r.data.items,'comment');renderActivity(r.data.activity);area.value='';selectedQuote='';document.getElementById('bsw-anchor-quote').value='';}else window.alert(r.data&&r.data.message||'Comment could not be saved.');});});
  var suggestionBtn=document.getElementById('bsw-add-suggestion');
  if(suggestionBtn)suggestionBtn.addEventListener('click',function(){var area=document.getElementById('bsw-suggestion-text');if(!postId){window.alert('Save the article once before submitting suggestions.');return;}phase2Request('bsw_studio_suggestion',{post_id:postId,suggestion:area.value,quote:selectedQuote},function(r){if(r.success){renderDiscussion(suggestionList,r.data.items,'suggestion');renderActivity(r.data.activity);area.value='';selectedQuote='';document.getElementById('bsw-anchor-quote').value='';}else window.alert(r.data&&r.data.message||'Suggestion could not be saved.');});});
  var assignBtn=document.getElementById('bsw-save-assignees');if(assignBtn)assignBtn.addEventListener('click',function(){var select=document.getElementById('bsw-review-assignees');var ids=Array.from(select.selectedOptions).map(function(o){return o.value;});phase2Request('bsw_studio_assign',{post_id:postId,user_ids:ids},function(r){var msg=document.getElementById('bsw-assignment-state');if(r.success){if(msg)msg.textContent='Assignments saved.';renderActivity(r.data.activity);}else if(msg)msg.textContent=r.data&&r.data.message||'Could not save assignments.';});});
  function serverRecovery(){
    if(!postId)return;
    phase2Request('bsw_studio_autosave',{post_id:postId,mode:mode.value,content:currentContent(),title:title.value},function(r){if(r.success&&saveState)saveState.textContent='Saved on this device and server';});
  }
  if(postId){serverRecovery();setInterval(serverRecovery,30000);}
  function presenceHeartbeat(){
    if(!postId)return;
    phase2Request('bsw_studio_presence',{post_id:postId},function(r){
      var el=document.getElementById('bsw-presence-indicator');
      if(el&&r.success&&r.data&&r.data.users){var names=r.data.users.map(function(u){return u.name;});el.textContent='Presence · '+names.length+' editor'+(names.length===1?'':'s')+' (live co-editing not enabled)';}
    });
  }
  if(postId){presenceHeartbeat();setInterval(presenceHeartbeat,20000);}
  // A server-side recovery record is kept separately from the published/draft revision.
  if(postId)phase2Request('bsw_studio_recovery',{post_id:postId},function(r){
    if(r.success&&r.data&&r.data.recovery&&r.data.recovery.updated){
      var rec=r.data.recovery;
      if(Date.now()/1000-rec.updated<86400&&window.confirm('A server recovery copy is available from '+new Date(rec.updated*1000).toLocaleString()+'. Restore its article text?')){
        if(rec.mode==='source'){setMode('source');sourceArea.value=rec.content||'';}else{setVisual(rec.content||'');}
        if(saveState)saveState.textContent='Restored server recovery copy';updateStats();
      }
    }
  });


  // Phase 4: workspace UX, command palette, focus mode and editorial quality tools.
  var app=document.querySelector('.bsw-studio-app');
  if(app){
    var ux=document.createElement('div');ux.className='bsw-ux-commandbar';
    ux.innerHTML='<div class="bsw-ux-group"><button type="button" data-ux="focus">Focus mode</button><button type="button" data-ux="preview">Preview</button><button type="button" data-ux="quality">Quality check</button></div><div class="bsw-ux-group"><button type="button" data-ux="heading">＋ Heading</button><button type="button" data-ux="list">＋ Bullet list</button><button type="button" data-ux="table">＋ Table</button><button type="button" data-ux="command">⌘ Commands</button></div>';
    var toolbar=app.querySelector('.bsw-studio-toolbar');if(toolbar)toolbar.insertAdjacentElement('afterend',ux);
    var palette=document.createElement('div');palette.className='bsw-command-palette';palette.hidden=true;palette.innerHTML='<div class="bsw-command-dialog"><div class="bsw-command-top"><input type="search" aria-label="Search commands" placeholder="Search actions…"><button type="button" data-close>✕</button></div><div class="bsw-command-results"></div><small>Tip: press Ctrl/⌘ + K to open commands · Esc to close</small></div>';app.appendChild(palette);
    var quality=document.createElement('section');quality.className='bsw-quality-panel';quality.hidden=true;quality.innerHTML='<div class="bsw-quality-head"><strong>Article quality review</strong><button type="button" data-close>Close</button></div><div class="bsw-quality-results"></div>';var main=app.querySelector('.bsw-studio-main');if(main)main.appendChild(quality);
    var actions=[['Focus mode','focus'],['Open article preview','preview'],['Run quality check','quality'],['Insert heading','heading'],['Insert bullet list','list'],['Insert table','table'],['Insert citation','citation'],['Insert wiki link','wikilink'],['Open media library','media'],['Save recovery copy','recovery']];
    function insertText(text){if(mode.value==='source'){var a=sourceArea.selectionStart,b=sourceArea.selectionEnd;sourceArea.setRangeText(text,a,b,'end');sourceArea.focus();}else{var ed=editor();if(ed){ed.focus();ed.insertContent(text.replace(/\n/g,'<br>'));}else{var ta=document.getElementById('bsw_studio_visual_content');if(ta)ta.value+=text;}}updateStats();}
    function runAction(a){if(a==='focus'){app.classList.toggle('bsw-focus-mode');return;}if(a==='preview'){var p=document.querySelector('.bsw-live-preview');if(p){p.scrollIntoView({behavior:'smooth'});p.hidden=false;}else{var link=app.querySelector('a[href*="preview=true"]');if(link)window.open(link.href,'_blank','noopener');}return;}if(a==='quality'){runQuality();return;}if(a==='heading'){insertText(mode.value==='source'?'\n\n== Section heading ==\n\n':'<h2>Section heading</h2>');return;}if(a==='list'){insertText(mode.value==='source'?'\n* First item\n* Second item\n':'<ul><li>First item</li><li>Second item</li></ul>');return;}if(a==='table'){insertText(mode.value==='source'?'\n{| class="wikitable"\n! Header 1 !! Header 2\n|}\n':'<table><tbody><tr><th>Header 1</th><th>Header 2</th></tr><tr><td>Value</td><td>Value</td></tr></tbody></table>');return;}if(a==='citation'){var b=document.getElementById('bsw-insert-citation');if(b)b.click();return;}if(a==='wikilink'){var b=document.getElementById('bsw-insert-wikilink');if(b)b.click();return;}if(a==='media'){if(window.wp&&wp.media){wp.media({title:'Select article media',button:{text:'Use media'},multiple:false}).open();}else window.alert('Media library is unavailable on this screen.');return;}if(a==='recovery'){saveRecovery();if(saveState)saveState.textContent='Recovery copy saved';}}
    function openPalette(){palette.hidden=false;var input=palette.querySelector('input');input.value='';renderCommands('');input.focus();}
    function renderCommands(q){var target=palette.querySelector('.bsw-command-results');target.innerHTML='';actions.filter(function(x){return x[0].toLowerCase().indexOf(q.toLowerCase())!==-1;}).forEach(function(x){var b=document.createElement('button');b.type='button';b.textContent=x[0];b.addEventListener('click',function(){palette.hidden=true;runAction(x[1]);});target.appendChild(b);});}
    palette.querySelector('input').addEventListener('input',function(){renderCommands(this.value);});palette.querySelector('[data-close]').addEventListener('click',function(){palette.hidden=true;});
    ux.addEventListener('click',function(e){var b=e.target.closest('[data-ux]');if(b){if(b.dataset.ux==='command')openPalette();else runAction(b.dataset.ux);}});
    palette.addEventListener('click',function(e){if(e.target===palette)palette.hidden=true;});
    document.addEventListener('keydown',function(e){if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();openPalette();}if(e.key==='Escape'){palette.hidden=true;}});
    function runQuality(){var text=plainText(),words=text.split(/\s+/).filter(Boolean),checks=[];var refs=readRefs();checks.push([title&&title.value.trim().length>0,'Article title is present']);checks.push([words.length>=300,'Article has at least 300 words ('+words.length+' found)']);checks.push([/\n|<h[2-4]/i.test(currentContent()),'Article has section structure']);checks.push([refs.length>0,'At least one source is listed']);checks.push([refs.some(function(r){return r.url||r.doi;}),'At least one source has a URL or DOI']);checks.push([/\[\[/.test(currentContent())||/href=/i.test(currentContent()),'Internal links are included']);checks.push([!/(TODO|FIXME|lorem ipsum)/i.test(text),'No obvious drafting placeholders']);var target=quality.querySelector('.bsw-quality-results');target.innerHTML='';var passed=checks.filter(function(c){return c[0];}).length;var summary=document.createElement('p');summary.className='bsw-quality-summary';summary.textContent=passed+' of '+checks.length+' editorial checks passed. These are drafting checks, not factual verification.';target.appendChild(summary);checks.forEach(function(c){var row=document.createElement('div');row.className='bsw-quality-row '+(c[0]?'is-pass':'is-warn');row.textContent=(c[0]?'✓ ':'! ')+c[1];target.appendChild(row);});quality.hidden=false;quality.scrollIntoView({behavior:'smooth',block:'nearest'});}
    quality.querySelector('[data-close]').addEventListener('click',function(){quality.hidden=true;});
  }

})(jQuery);


/* BIGSHOR Article Studio 1.4.0 — workspace navigation and safety affordances */
(function(){
 'use strict';
 document.addEventListener('DOMContentLoaded',function(){
  var app=document.querySelector('.bsw-studio-app'); if(!app)return;
  // Keyboard shortcuts that do not override browser/editor-native shortcuts.
  document.addEventListener('keydown',function(e){
   if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){
    e.preventDefault(); var save=app.querySelector('[type="submit"],.bsw-btn-red'); if(save)save.click();
   }
   if(e.key==='Escape'){var drawer=app.querySelector('.bsw-studio-drawer.is-open');if(drawer)drawer.classList.remove('is-open');}
  });
  // Add accessible titles to icon-only controls.
  app.querySelectorAll('button').forEach(function(b){if(!b.getAttribute('aria-label')&&!b.textContent.trim())b.setAttribute('aria-label','Editor action');});
  // Warn only when the form is genuinely dirty; existing autosave remains authoritative.
  var form=app.querySelector('form'),dirty=false;
  if(form){
   form.addEventListener('input',function(){dirty=true;});
   form.addEventListener('change',function(){dirty=true;});
   form.addEventListener('submit',function(){dirty=false;});
   window.addEventListener('beforeunload',function(e){
    var indicator=document.getElementById('bsw-studio-save-indicator');
    var saved=indicator&&/saved/i.test(indicator.textContent||'');
    if(dirty&&!saved){e.preventDefault();e.returnValue='';}
   });
  }
  // Improve editor panel semantics for assistive technology.
  app.querySelectorAll('[data-panel],.bsw-studio-section,.bsw-sidebar-card').forEach(function(el){
   if(!el.hasAttribute('tabindex')&&el.querySelector('h2,h3'))el.setAttribute('tabindex','-1');
  });
 });
})();
