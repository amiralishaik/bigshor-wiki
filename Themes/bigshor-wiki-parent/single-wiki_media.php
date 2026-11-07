<?php get_header(); while(have_posts()):the_post(); bigshor_wiki_breadcrumbs(); ?>
<h1 class="page-title"><?php the_title(); ?></h1>
<div class="article-tools"><span>BIGSHOR Wiki Media Repository</span><span><?php echo esc_html(get_the_date()); ?></span></div>
<article class="article-body">
<?php if(has_post_thumbnail()): ?><p><?php the_post_thumbnail('large'); ?></p><?php endif; ?>
<?php the_content(); ?>
<h2>Media information</h2>
<table class="wiki-history">
<?php foreach(array('source'=>'Original source','creator'=>'Creator / rights holder','licence'=>'Licence','credit'=>'Attribution / credit','date'=>'Creation date') as $key=>$label): $v=get_post_meta(get_the_ID(),'_bsw_media_'.$key,true); if($v): ?>
<tr><th><?php echo esc_html($label); ?></th><td><?php if($key==='source'): ?><a href="<?php echo esc_url($v); ?>" rel="nofollow noopener"><?php echo esc_html($v); ?></a><?php else: echo esc_html($v); endif; ?></td></tr>
<?php endif; endforeach; ?>
</table>
<p class="wiki-meta">Verify the licence and attribution requirements before reusing this file elsewhere.</p>
</article>
<?php endwhile; get_footer(); ?>
