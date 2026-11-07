<?php
if (!defined('ABSPATH')) exit;

/* Expanded BIGSHOR Wiki discovery, editorial and reader experience. */
add_action('init', function(){
    $pages = array(
      'about'=>array('About BIGSHOR Wiki','<h2>About this encyclopedia</h2><p>BIGSHOR Wiki is an independently edited knowledge resource covering people, places, organisations, businesses, brands, history, culture, technology and ideas.</p><p>Articles should be neutral, verifiable, responsibly sourced and transparent about uncertainty or disputes.</p>'),
      'editorial-policy'=>array('Editorial Policy','<h2>Editorial principles</h2><ul><li>Use a neutral, factual point of view.</li><li>Support material claims with reliable, independent sources.</li><li>Clearly distinguish fact, opinion, allegation and promotional material.</li><li>Disclose conflicts of interest and paid contributions.</li><li>Correct errors transparently and protect privacy.</li><li>Do not publish defamatory, copyrighted or private material without a lawful basis.</li></ul>'),
      'style-guide'=>array('Style Guide','<h2>Article style</h2><ul><li>Use a concise lead that defines the subject.</li><li>Use descriptive headings and plain language.</li><li>Provide dates, context, citations and relevant categories.</li><li>Avoid marketing language, unsupported superlatives and first-person claims.</li><li>Use consistent spelling, dates, units and transliteration.</li></ul>'),
      'reliable-sources'=>array('Reliable Sources Guide','<h2>Source quality</h2><p>Prefer independent, reputable publications, official primary records where appropriate, scholarly work and recognised institutions. Evaluate authorship, editorial standards, date, relevance, independence and corroboration. A source being official does not automatically make every claim independent or conclusive.</p>'),
      'disclaimer'=>array('General Disclaimer','<p>BIGSHOR Wiki is a user-contributed information resource, not professional legal, medical, financial or other advice. Content may be incomplete or require correction. Verify important information against authoritative sources.</p>'),
      'contact'=>array('Contact BIGSHOR Wiki','<p>For editorial questions, corrections, privacy requests or copyright concerns, contact the site publisher using the contact details configured by the site administrator.</p>'),
      'faq'=>array('Frequently Asked Questions','<h2>Can anyone contribute?</h2><p>Registered users can submit content and suggestions subject to editorial review.</p><h2>Are submissions published automatically?</h2><p>New submissions and proposed edits are reviewed before publication.</p><h2>How do I report a concern?</h2><p>Use the Report an issue link on an article.</p>'),
      'browse-topics'=>array('Browse Topics','[bigshor_wiki_topics]'),
      'browse-categories'=>array('Browse Categories','[bigshor_wiki_category_index]'),
      'a-z-index'=>array('A–Z Index','[bigshor_wiki_az]'),
      'wiki-search'=>array('Search the Encyclopedia','[bigshor_wiki_advanced_search]'),
      'random-article'=>array('Random Article','[bigshor_wiki_random]'),
      'featured-articles'=>array('Featured Articles','[bigshor_wiki_featured limit="30"]'),
      'knowledge-entities'=>array('Knowledge Entities','[bigshor_entity_directory]'),
      'commons'=>array('BIGSHOR Commons','[bigshor_commons_gallery limit="48"]')
    );
    foreach($pages as $slug=>$data){ if(!get_page_by_path($slug)) wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_name'=>$slug,'post_title'=>$data[0],'post_content'=>$data[1])); }
});

add_shortcode('bigshor_wiki_advanced_search', function(){
    $term = sanitize_text_field(wp_unslash($_GET['wiki_q'] ?? ''));
    $topic = absint($_GET['wiki_topic_filter'] ?? 0);
    $lang = sanitize_key(wp_unslash($_GET['wiki_lang_filter'] ?? ''));
    $out = '<form class="bsw-advanced-search" method="get" action="'.esc_url(get_permalink()).'"><label>Search words<input type="search" name="wiki_q" value="'.esc_attr($term).'" placeholder="Search article titles and content"></label>';
    $terms=get_terms(array('taxonomy'=>'wiki_topic','hide_empty'=>false));
    if(!is_wp_error($terms)&&$terms){$out.='<label>Topic<select name="wiki_topic_filter"><option value="0">All topics</option>';foreach($terms as $t)$out.='<option value="'.(int)$t->term_id.'" '.selected($topic,$t->term_id,false).'>'.esc_html($t->name).'</option>';$out.='</select></label>';}
    $langs=get_terms(array('taxonomy'=>'wiki_language','hide_empty'=>false));
    if(!is_wp_error($langs)&&$langs){$out.='<label>Language<select name="wiki_lang_filter"><option value="">All languages</option>';foreach($langs as $l)$out.='<option value="'.esc_attr($l->slug).'" '.selected($lang,$l->slug,false).'>'.esc_html($l->name).'</option>';$out.='</select></label>';}
    $out.='<button class="wiki-button" type="submit">Find articles</button></form>';
    if($term||$topic||$lang){$args=array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>30,'s'=>$term);
      if($topic)$args['tax_query'][]=array('taxonomy'=>'wiki_topic','field'=>'term_id','terms'=>$topic);
      if($lang)$args['tax_query'][]=array('taxonomy'=>'wiki_language','field'=>'slug','terms'=>$lang);
      $q=new WP_Query($args);$out.='<h2>Search results</h2>';
      if($q->have_posts()){$out.='<ul class="wiki-list">';while($q->have_posts()){$q->the_post();$out.='<li><h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><div class="excerpt">'.esc_html(wp_trim_words(get_the_excerpt(),28)).'</div></li>'; }$out.='</ul>'; }else $out.='<p>No matching articles found.</p>';wp_reset_postdata();
    }
    return $out;
});

add_shortcode('bigshor_wiki_category_index',function(){
    $terms=get_terms(array('taxonomy'=>'wiki_topic','hide_empty'=>false,'orderby'=>'name','order'=>'ASC'));
    if(is_wp_error($terms)||!$terms)return '<p>No topics have been created yet.</p>';
    $out='<div class="bsw-topic-grid">';foreach($terms as $t){$url=get_term_link($t);if(!is_wp_error($url))$out.='<a class="bsw-topic-card" href="'.esc_url($url).'"><strong>'.esc_html($t->name).'</strong><span>'.number_format_i18n($t->count).' articles</span></a>'; }return $out.'</div>';
});

add_shortcode('bigshor_wiki_az',function(){
    $letter=strtoupper(substr(sanitize_text_field(wp_unslash($_GET['letter']??'A')),0,1));
    if(!preg_match('/^[A-Z0-9]$/',$letter))$letter='A';
    $out='<nav class="bsw-letters" aria-label="Article alphabetical index">';foreach(range('A','Z') as $l)$out.='<a '.($l===$letter?'aria-current="page"':'').' href="'.esc_url(add_query_arg('letter',$l)).'">'.$l.'</a>'; $out.='</nav>';
    global $wpdb;$like=$wpdb->esc_like($letter).'%';$ids=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status=%s AND post_title LIKE %s ORDER BY post_title ASC LIMIT 500",'wiki_article','publish',$like));
    if(!$ids)return $out.'<p>No articles beginning with '.esc_html($letter).'.</p>';
    $out.='<ol class="bsw-az-list">';foreach($ids as $id)$out.='<li><a href="'.esc_url(get_permalink($id)).'">'.esc_html(get_the_title($id)).'</a></li>';return $out.'</ol>';
});

/* Reader tools: citation snippet, share links and stable permalink. */
add_filter('the_content',function($content){
    if(!is_singular('wiki_article')||!in_the_loop()||!is_main_query())return $content;
    $id=get_the_ID();$citation=get_the_title($id).'. BIGSHOR Wiki. '.get_permalink($id).'. Accessed '.wp_date('j F Y').'.';
    $tools='<section class="bsw-reader-tools"><strong>Article tools</strong><button type="button" class="wiki-button" data-bsw-copy="'.esc_attr($citation).'">Copy citation</button><button type="button" class="wiki-button" data-bsw-copy="'.esc_attr(get_permalink($id)).'">Copy permanent link</button><a class="wiki-button" href="mailto:?subject='.rawurlencode(get_the_title($id)).'&body='.rawurlencode(get_permalink($id)).'">Email article</a></section>';
    return $content.$tools;
},30);

add_action('wp_enqueue_scripts',function(){
    wp_enqueue_script('bigshor-wiki-enhancements',BSW_THEME_URL.'/assets/wiki-enhancements.js',array(),BSW_VERSION,true);
});

add_action('wp_head',function(){
    if(!is_singular('wiki_article'))return;
    $id=get_queried_object_id();$desc=has_excerpt($id)?get_the_excerpt($id):wp_trim_words(wp_strip_all_tags(get_post_field('post_content',$id)),35);
    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta property="og:title" content="'.esc_attr(get_the_title($id)).'">' . "\n";
    echo '<meta property="og:description" content="'.esc_attr($desc).'">' . "\n";
    echo '<meta property="og:url" content="'.esc_url(get_permalink($id)).'">' . "\n";
    if(has_post_thumbnail($id))echo '<meta property="og:image" content="'.esc_url(get_the_post_thumbnail_url($id,'full')).'">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
},8);

add_filter('document_title_parts',function($parts){if(is_singular('wiki_article'))$parts['site']='BIGSHOR Wiki';return $parts;});

add_action('admin_menu',function(){
    add_submenu_page('edit.php?post_type=wiki_article','Wiki Overview','Wiki Overview','manage_options','bigshor-wiki-overview',function(){
      $c=wp_count_posts('wiki_article');$pending=get_posts(array('post_type'=>'wiki_article','post_status'=>'pending','numberposts'=>-1,'fields'=>'ids'));
      echo '<div class="wrap"><h1>BIGSHOR Wiki Overview</h1><p>Editorial workspace and publishing health at a glance.</p><div style="display:flex;gap:14px;flex-wrap:wrap">';
      foreach(array('Published'=>(int)$c->publish,'Drafts'=>(int)$c->draft,'Pending'=>(int)$c->pending,'Pending proposals'=>count(get_posts(array('post_type'=>'wiki_edit_proposal','post_status'=>'pending','numberposts'=>-1,'fields'=>'ids'))),'Media'=>(int)wp_count_posts('wiki_media')->publish) as $label=>$n)echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:18px;min-width:150px"><strong style="font-size:26px">'.number_format_i18n($n).'</strong><br>'.esc_html($label).'</div>';
      echo '</div><h2>Quick links</h2><p><a class="button button-primary" href="'.esc_url(admin_url('post-new.php?post_type=wiki_article')).'">Create article</a> <a class="button" href="'.esc_url(admin_url('edit.php?post_type=wiki_edit_proposal')).'">Review edit proposals</a> <a class="button" href="'.esc_url(admin_url('edit.php?post_type=wiki_moderation_report')).'">Moderation reports</a> <a class="button" href="'.esc_url(home_url('/wiki-statistics/')).'">Public statistics</a></p></div>';
    });
});
