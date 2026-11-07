<?php
/**
 * BIGSHOR Wiki Core 29.3.0 — Phase 38: Search, discussion, citations and page utilities.
 * Uses native WordPress posts, comments, revisions and REST APIs. PHP 7.4+.
 */
if (!defined('ABSPATH')) { exit; }
if (!class_exists('BSW_Phase38_Reader_Experience')) {
final class BSW_Phase38_Reader_Experience {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_support'), 30);
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        add_shortcode('bigshor_advanced_search', array(__CLASS__, 'search_shortcode'));
        add_shortcode('bigshor_wiki_citation', array(__CLASS__, 'citation_shortcode'));
        add_shortcode('bigshor_wiki_discussion', array(__CLASS__, 'discussion_shortcode'));
        add_shortcode('bigshor_wiki_history_compare', array(__CLASS__, 'history_shortcode'));
        add_filter('pre_get_posts', array(__CLASS__, 'search_post_types'));
        add_filter('preprocess_comment', array(__CLASS__, 'require_registered_discussion'));
        add_filter('comment_post_redirect', array(__CLASS__, 'discussion_redirect'), 10, 2);
        add_filter('the_content', array(__CLASS__, 'append_reader_tools'), 40);
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function register_support() {
        if (post_type_exists('wiki_article')) {
            add_post_type_support('wiki_article', array('comments', 'revisions', 'author', 'excerpt', 'thumbnail'));
        }
    }

    public static function register_routes() {
        register_rest_route('bigshor/v1', '/search', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'rest_search'),
            'permission_callback' => '__return_true',
            'args' => array(
                'q' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
                'type' => array('default' => 'all', 'sanitize_callback' => 'sanitize_key'),
                'topic' => array('default' => 0, 'sanitize_callback' => 'absint'),
                'page' => array('default' => 1, 'sanitize_callback' => 'absint'),
            ),
        ));
    }

    public static function rest_search($request) {
        $term = trim((string) $request->get_param('q'));
        if (strlen($term) < 2) return rest_ensure_response(array('items'=>array(), 'total'=>0, 'pages'=>0));
        $type = $request->get_param('type');
        $post_types = ($type === 'news') ? array('bsw_news') : (($type === 'knowledge') ? array('wiki_article') : array('wiki_article','bsw_news'));
        $args = array(
            'post_type'=>$post_types, 'post_status'=>'publish', 's'=>$term,
            'posts_per_page'=>10, 'paged'=>max(1,absint($request->get_param('page'))),
            'orderby'=>'relevance', 'no_found_rows'=>false,
        );
        $topic = absint($request->get_param('topic'));
        if ($topic && taxonomy_exists('wiki_topic')) $args['tax_query'] = array(array('taxonomy'=>'wiki_topic','field'=>'term_id','terms'=>$topic));
        $q = new WP_Query($args); $items=array();
        foreach ($q->posts as $post) {
            $items[] = array(
                'id'=>(int)$post->ID, 'title'=>get_the_title($post), 'url'=>get_permalink($post),
                'type'=>$post->post_type==='bsw_news'?'News':'Knowledge',
                'excerpt'=>wp_trim_words(get_the_excerpt($post),28),
                'modified'=>get_post_modified_time('c',true,$post),
                'image'=>get_the_post_thumbnail_url($post,'thumbnail') ?: '',
            );
        }
        return rest_ensure_response(array('items'=>$items,'total'=>(int)$q->found_posts,'pages'=>(int)$q->max_num_pages));
    }

    public static function search_post_types($query) {
        if (is_admin() || !$query->is_main_query() || !$query->is_search()) return;
        $query->set('post_type', array('wiki_article','bsw_news'));
    }

    public static function search_shortcode($atts=array()) {
        $term = sanitize_text_field(wp_unslash($_GET['wiki_q'] ?? ''));
        $type = sanitize_key(wp_unslash($_GET['wiki_type'] ?? 'all'));
        if (!in_array($type,array('all','knowledge','news'),true)) $type='all';
        $topic = absint($_GET['wiki_topic'] ?? 0);
        $page = max(1,absint($_GET['wiki_page'] ?? 1));
        $out = '<section class="bsw-advanced-search" data-bsw-advanced-search>';
        $out .= '<form method="get" action="'.esc_url(get_permalink()).'" role="search" class="bsw-advanced-search-form">';
        $out .= '<label for="bsw-wiki-search-input">Search BIGSHOR</label><div class="bsw-search-field"><input id="bsw-wiki-search-input" name="wiki_q" type="search" minlength="2" value="'.esc_attr($term).'" placeholder="Search people, topics, organisations and events" autocomplete="off" data-bsw-live-search><button type="submit">Search</button></div><div class="bsw-live-suggestions" data-bsw-suggestions hidden role="listbox" aria-label="Search suggestions"></div>';
        $out .= '<div class="bsw-search-filters"><label>Search in <select name="wiki_type"><option value="all"'.selected($type,'all',false).'>All BIGSHOR</option><option value="knowledge"'.selected($type,'knowledge',false).'>Knowledge</option><option value="news"'.selected($type,'news',false).'>News</option></select></label>';
        if (taxonomy_exists('wiki_topic')) {
            $terms=get_terms(array('taxonomy'=>'wiki_topic','hide_empty'=>true));
            if (!is_wp_error($terms) && $terms) { $out.='<label>Topic <select name="wiki_topic"><option value="0">All topics</option>'; foreach($terms as $t) $out.='<option value="'.(int)$t->term_id.'"'.selected($topic,$t->term_id,false).'>'.esc_html($t->name).'</option>'; $out.='</select></label>'; }
        }
        $out .= '</div></form>';
        if ($term !== '') {
            $types=$type==='news'?array('bsw_news'):($type==='knowledge'?array('wiki_article'):array('wiki_article','bsw_news'));
            $args=array('post_type'=>$types,'post_status'=>'publish','s'=>$term,'posts_per_page'=>10,'paged'=>$page,'orderby'=>'relevance');
            if ($topic && taxonomy_exists('wiki_topic')) $args['tax_query']=array(array('taxonomy'=>'wiki_topic','field'=>'term_id','terms'=>$topic));
            $q=new WP_Query($args);
            $out.='<p class="bsw-search-count">'.number_format_i18n($q->found_posts).' results</p>';
            if ($q->have_posts()) {
                $out.='<ol class="bsw-search-result-list">';
                while($q->have_posts()) { $q->the_post(); $out.='<li><article><p class="bsw-search-result-meta">'.(get_post_type()==='bsw_news'?'BIGSHOR News':'Knowledge article').' · '.esc_html(get_the_modified_date()).'</p><h2><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h2><p>'.esc_html(wp_trim_words(get_the_excerpt(),32)).'</p></article></li>'; }
                $out.='</ol>';
                if ($q->max_num_pages>1) {
                    $out.='<nav class="bsw-search-pagination" aria-label="Search pages">';
                    for($i=1;$i<=$q->max_num_pages;$i++) $out.='<a '.($i===$page?'aria-current="page" ':'').'href="'.esc_url(add_query_arg(array('wiki_q'=>$term,'wiki_type'=>$type,'wiki_topic'=>$topic,'wiki_page'=>$i),get_permalink())).'">'.(int)$i.'</a>';
                    $out.='</nav>';
                }
            } else $out.='<p>No matching published BIGSHOR pages were found. Try a broader search.</p>';
            wp_reset_postdata();
        } else $out.='<p>Search the published BIGSHOR knowledge library and news archive.</p>';
        return $out.'</section>';
    }

    public static function citation_shortcode($atts=array()) {
        $id=get_the_ID(); if(!$id || !in_array(get_post_type($id),array('wiki_article','bsw_news'),true)) return '';
        $author=get_the_author_meta('display_name',get_post_field('post_author',$id));
        $title=get_the_title($id); $site=get_bloginfo('name'); $date=get_the_date('F j, Y',$id); $url=get_permalink($id);
        $citation=trim($author).'. “'.$title.'.” '.$site.'. '.$date.'. '.$url.'.';
        return '<section class="bsw-citation-box"><h2>Cite this page</h2><p class="bsw-citation-text">'.esc_html($citation).'</p><button type="button" class="bsw-copy-citation" data-bsw-copy-text="'.esc_attr($citation).'">Copy citation</button></section>';
    }

    public static function discussion_shortcode($atts=array()) {
        $id=absint($atts['id'] ?? get_the_ID());
        if(!$id || get_post_type($id)!=='wiki_article' || get_post_status($id)!=='publish') return '<p>Discussion is available on published Knowledge articles.</p>';
        $comments=get_comments(array('post_id'=>$id,'status'=>'approve','type'=>'comment','orderby'=>'comment_date_gmt','order'=>'ASC'));
        $out='<section class="bsw-talk-page" id="bsw-talk-page"><header><p class="bsw-eyebrow">ARTICLE DISCUSSION</p><h2>Talk: '.esc_html(get_the_title($id)).'</h2><p>Use this space to discuss improvements, sources and editorial questions about this article. Keep comments civil, specific and evidence-based.</p></header>';
        if($comments) {
            $out.='<ol class="bsw-talk-thread">';
            foreach($comments as $comment) {
                $out.='<li id="comment-'.(int)$comment->comment_ID.'"><article><header><strong>'.esc_html(get_comment_author($comment)).'</strong><time datetime="'.esc_attr(get_comment_time('c',true,$comment)).'">'.esc_html(get_comment_date('', $comment).' '.get_comment_time('',true,$comment)).'</time></header><div>'.wp_kses_post(wpautop($comment->comment_content)).'</div></article></li>';
            }
            $out.='</ol>';
        } else $out.='<p>No approved discussion posts yet.</p>';
        if(comments_open($id)) {
            if(is_user_logged_in()) {
                ob_start();
                comment_form(array(
                    'title_reply'=>'Start a discussion',
                    'label_submit'=>'Post discussion comment',
                    'comment_notes_before'=>'<p class="comment-notes">Comments are moderated and must follow BIGSHOR community guidelines.</p>',
                    'comment_notes_after'=>'',
                    'logged_in_as'=>'',
                    'comment_field'=>'<p class="comment-form-comment"><label for="comment">Your comment</label><textarea id="comment" name="comment" rows="6" required></textarea></p>',
                ),$id);
                $out.=ob_get_clean();
            } else $out.='<p><a href="'.esc_url(wp_login_url(get_permalink($id).'#bsw-talk-page')).'">Log in</a> to join this discussion. Only registered users may post.</p>';
        } else $out.='<p>Discussion is closed for this article.</p>';
        return $out.'</section>';
    }

    public static function require_registered_discussion($commentdata) {
        $post=get_post(absint($commentdata['comment_post_ID'] ?? 0));
        if($post && $post->post_type==='wiki_article' && empty($commentdata['user_id'])) {
            wp_die('Please log in with your registered BIGSHOR account to participate in article discussions.', 'Login required', array('response'=>403,'back_link'=>true));
        }
        return $commentdata;
    }

    public static function discussion_redirect($location,$comment) {
        $post=get_post($comment->comment_post_ID);
        if($post && $post->post_type==='wiki_article') return get_permalink($post).'#bsw-talk-page';
        return $location;
    }

    public static function history_shortcode($atts=array()) {
        $id=absint($_GET['article_id'] ?? ($atts['id'] ?? get_the_ID()));
        if(!$id || get_post_type($id)!=='wiki_article' || get_post_status($id)!=='publish') return '<p>Select a published Knowledge article.</p>';
        return do_shortcode('[bigshor_wiki_history]');
    }

    public static function append_reader_tools($content) {
        if(is_admin() || !is_singular(array('wiki_article','bsw_news')) || !in_the_loop() || !is_main_query()) return $content;
        if(strpos($content,'bsw-citation-box')===false) $content .= self::citation_shortcode();
        return $content;
    }

    public static function assets() {
        if(!is_singular(array('wiki_article','bsw_news')) && !is_page()) return;
        wp_register_script('bsw-phase38-reader', '', array(), '29.3.0', true);
        wp_enqueue_script('bsw-phase38-reader');
        wp_add_inline_script('bsw-phase38-reader', 'window.BSWReader=' . wp_json_encode(array('root'=>esc_url_raw(rest_url('bigshor/v1/')), 'nonce'=>wp_create_nonce('wp_rest'))) . ';');
        $css='.bsw-advanced-search{max-width:900px;margin:1.5rem auto}.bsw-advanced-search-form{border:1px solid #a2a9b1;background:#f8f9fa;padding:18px}.bsw-advanced-search-form>label{display:block;font-weight:700;margin-bottom:8px}.bsw-search-field{display:flex;gap:8px}.bsw-search-field input{flex:1;min-width:0;padding:12px;border:1px solid #72777d;font-size:16px}.bsw-search-field button,.bsw-copy-citation{border:1px solid #36c;background:#36c;color:white;padding:9px 16px;cursor:pointer}.bsw-search-filters{display:flex;gap:18px;flex-wrap:wrap;margin-top:12px}.bsw-search-filters label{display:flex;align-items:center;gap:7px;font-size:13px}.bsw-live-suggestions{position:relative;background:white;border:1px solid #a2a9b1;box-shadow:0 3px 8px #0002;z-index:5}.bsw-live-suggestions a{display:block;padding:9px 12px;border-bottom:1px solid #eaecf0;text-decoration:none}.bsw-live-suggestions small{display:block;color:#54595d}.bsw-search-result-list{list-style:none;padding:0}.bsw-search-result-list>li{border-bottom:1px solid #eaecf0;padding:14px 0}.bsw-search-result-list h2{font-size:20px;margin:4px 0}.bsw-search-result-meta{font-size:12px;color:#54595d}.bsw-search-pagination{display:flex;gap:6px;margin:20px 0}.bsw-search-pagination a{border:1px solid #a2a9b1;padding:5px 10px;text-decoration:none}.bsw-search-pagination [aria-current=page]{background:#eaecf0;font-weight:700}.bsw-talk-page{border-top:1px solid #a2a9b1;margin:2rem 0;padding-top:1.2rem}.bsw-talk-thread{list-style:none;padding:0}.bsw-talk-thread>li{border-bottom:1px solid #eaecf0;padding:12px 0}.bsw-talk-thread article>header{display:flex;gap:12px;align-items:baseline}.bsw-talk-thread time{font-size:12px;color:#54595d}.bsw-citation-box{border-top:1px solid #a2a9b1;margin:2rem 0;padding:12px 0}.bsw-citation-text{padding:10px;background:#f8f9fa;overflow-wrap:anywhere}.bsw-copy-citation{font-size:13px}';
        wp_register_style('bsw-phase38-reader',false,array(),'29.3.0'); wp_enqueue_style('bsw-phase38-reader'); wp_add_inline_style('bsw-phase38-reader',$css);
        $js="(function(){'use strict';document.addEventListener('input',function(e){var input=e.target.closest('[data-bsw-live-search]');if(!input)return;var wrap=input.closest('[data-bsw-advanced-search]');var box=wrap&&wrap.querySelector('[data-bsw-suggestions]');if(!box||!window.BSWReader)return;var term=input.value.trim();if(term.length<2){box.hidden=true;box.innerHTML='';return;}window.clearTimeout(window.BSWSearchTimer);window.BSWSearchTimer=window.setTimeout(function(){fetch(window.BSWReader.root+'search-suggest?q='+encodeURIComponent(term),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(items){box.innerHTML='';(items||[]).forEach(function(item){var a=document.createElement('a');a.href=item.url;a.textContent=item.title;var small=document.createElement('small');small.textContent=item.type+' · '+item.excerpt;a.appendChild(small);box.appendChild(a);});box.hidden=!box.children.length;}).catch(function(){box.hidden=true;});},180);});document.addEventListener('click',function(e){var btn=e.target.closest('[data-bsw-copy-text]');if(btn&&navigator.clipboard){navigator.clipboard.writeText(btn.getAttribute('data-bsw-copy-text')).then(function(){btn.textContent='Copied';});}});})();";
        wp_add_inline_script('bsw-phase38-reader',$js);
    }
}
BSW_Phase38_Reader_Experience::init();
}
