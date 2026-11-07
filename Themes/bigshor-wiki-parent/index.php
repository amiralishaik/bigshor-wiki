<?php
get_header();
if ( is_front_page() && ! is_paged() ) :
  $count = wp_count_posts('wiki_article');
  $published = $count && isset($count->publish) ? (int) $count->publish : 0;
  $users = count_users();
  $editor_count = isset($users['total_users']) ? (int) $users['total_users'] : 0;
  $featured = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>1,'meta_key'=>'_bsw_featured','meta_value'=>'1','ignore_sticky_posts'=>true));
  if (!$featured->have_posts()) { wp_reset_postdata(); $featured = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>1,'orderby'=>'modified','order'=>'DESC')); }
  $recent = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>6,'orderby'=>'modified','order'=>'DESC','no_found_rows'=>true));
  $news = new WP_Query(array('post_type'=>'bsw_news','post_status'=>'publish','posts_per_page'=>5,'orderby'=>'date','order'=>'DESC','no_found_rows'=>true));
?>
<section class="mw-welcome">
  <h1>Welcome to <strong><?php echo esc_html(bsw_theme_get_settings()['brand_name']); ?></strong></h1>
  <p>the free knowledge encyclopedia that brings together independently reviewed information.</p>
  <div class="mw-welcome-stats"><span><strong><?php echo esc_html(number_format_i18n($published)); ?></strong> published articles</span><span><strong><?php echo esc_html(number_format_i18n($editor_count)); ?></strong> registered contributors</span></div>
</section>
<div class="mw-home-grid">
  <section class="mw-panel mw-featured">
    <h2>From today's featured article</h2>
    <?php if ($featured->have_posts()) : while ($featured->have_posts()) : $featured->the_post(); ?>
      <div class="mw-featured-entry">
        <?php if (has_post_thumbnail()) : ?><a class="mw-float-image" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('medium'); ?></a><?php endif; ?>
        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        <p><?php echo esc_html(wp_trim_words(get_the_excerpt() ?: wp_strip_all_tags(get_the_content()), 95)); ?> <a href="<?php the_permalink(); ?>">Full article...</a></p>
      </div>
    <?php endwhile; else : ?><p>Featured knowledge articles will appear here after publication.</p><?php endif; wp_reset_postdata(); ?>
    <h3 class="mw-subheading">Recently featured</h3>
    <ul class="mw-link-list"><?php
      $mini = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>3,'orderby'=>'modified','order'=>'DESC','no_found_rows'=>true));
      while($mini->have_posts()):$mini->the_post(); ?><li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li><?php endwhile; wp_reset_postdata(); ?></ul>
  </section>

  <section class="mw-panel mw-did-you-know">
    <h2>Did you know ...</h2>
    <div class="mw-panel-content">
      <?php
      $dyk = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>5,'orderby'=>'rand','no_found_rows'=>true));
      if($dyk->have_posts()): ?><ul class="mw-bullet-list"><?php while($dyk->have_posts()):$dyk->the_post(); ?><li>... <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><?php if(get_the_excerpt()): ?> — <?php echo esc_html(wp_trim_words(get_the_excerpt(),22)); ?><?php endif; ?></li><?php endwhile; ?></ul><?php else: ?><p>New knowledge entries will appear here as the encyclopedia grows.</p><?php endif; wp_reset_postdata(); ?>
    </div>
  </section>

  <section class="mw-panel mw-in-news">
    <h2>In the news</h2>
    <?php if($news->have_posts()): ?><ul class="mw-news-list"><?php while($news->have_posts()):$news->the_post(); ?><li><?php if(has_post_thumbnail()): ?><a class="mw-news-thumb" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('thumbnail'); ?></a><?php endif; ?><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><small><?php echo esc_html(get_the_date()); ?></small></li><?php endwhile; ?></ul><?php else: ?><p>BIGSHOR News updates will appear here when published.</p><?php endif; wp_reset_postdata(); ?>
    <p class="mw-more"><a href="<?php echo esc_url(get_post_type_archive_link('bsw_news') ?: home_url('/news/')); ?>">More news...</a></p>
  </section>

  <section class="mw-panel mw-on-this-day">
    <h2>From the knowledge archives</h2>
    <p>Explore recently updated entries from across the encyclopedia.</p>
    <ul class="mw-bullet-list"><?php
      $archive = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>5,'orderby'=>'modified','order'=>'ASC','no_found_rows'=>true));
      if($archive->have_posts()):while($archive->have_posts()):$archive->the_post(); ?><li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a> <small>(updated <?php echo esc_html(get_the_modified_date()); ?>)</small></li><?php endwhile; else: ?><li>Archive entries will appear here as articles are published.</li><?php endif; wp_reset_postdata(); ?></ul>
  </section>

  <section class="mw-panel mw-featured-picture">
    <h2>Today's featured picture</h2>
    <?php
      $picture = new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>1,'meta_key'=>'_thumbnail_id','orderby'=>'modified','order'=>'DESC','no_found_rows'=>true));
      if($picture->have_posts()):while($picture->have_posts()):$picture->the_post(); ?>
        <a class="mw-picture" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('large'); ?></a>
        <p><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></p>
        <?php if(get_the_excerpt()): ?><p><?php echo esc_html(wp_trim_words(get_the_excerpt(),35)); ?></p><?php endif; ?>
      <?php endwhile; else: ?><p>Featured images will appear here when added to knowledge articles.</p><?php endif; wp_reset_postdata(); ?>
  </section>

  <section class="mw-panel mw-other-areas">
    <h2>Other areas of BIGSHOR</h2>
    <ul class="mw-project-list">
      <li><a href="<?php echo esc_url(home_url('/community-portal/')); ?>">Community portal</a> – contributor resources and announcements.</li>
      <li><a href="<?php echo esc_url(home_url('/help-center/')); ?>">Help centre</a> – guidance for readers and contributors.</li>
      <li><a href="<?php echo esc_url(home_url('/browse-topics/')); ?>">Topic directory</a> – browse knowledge by subject.</li>
      <li><a href="<?php echo esc_url(home_url('/recent-changes/')); ?>">Recent changes</a> – follow updates to the encyclopedia.</li>
    </ul>
  </section>

  <section class="mw-panel mw-sister-projects">
    <h2>BIGSHOR projects</h2>
    <div class="mw-project-grid">
      <a href="<?php echo esc_url(get_post_type_archive_link('bsw_news') ?: home_url('/news/')); ?>"><strong>BIGSHOR News</strong><small>Independent news coverage</small></a>
      <a href="<?php echo esc_url(home_url('/media-library/')); ?>"><strong>BIGSHOR Media</strong><small>Media and visual resources</small></a>
      <a href="<?php echo esc_url(home_url('/wiki-contributors/')); ?>"><strong>Contributor network</strong><small>People behind the knowledge</small></a>
    </div>
  </section>

  <section class="mw-panel mw-ongoing"><h2>Ongoing events</h2><?php $q=new WP_Query(array('post_type'=>'bsw_portal_item','post_status'=>'publish','posts_per_page'=>5,'meta_key'=>'_bsw_portal_kind','meta_value'=>'ongoing')); if($q->have_posts()):?><ul class="mw-bullet-list"><?php while($q->have_posts()):$q->the_post();?><li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a> — <?php echo esc_html(wp_trim_words(get_the_excerpt()?:get_the_content(),22)); ?></li><?php endwhile;?></ul><?php else:?><p>Editorially reviewed ongoing events will appear here.</p><?php endif; wp_reset_postdata();?></section>
  <section class="mw-panel mw-deaths"><h2>Recent deaths</h2><?php $q=new WP_Query(array('post_type'=>'bsw_portal_item','post_status'=>'publish','posts_per_page'=>5,'meta_key'=>'_bsw_portal_kind','meta_value'=>'recent_death')); if($q->have_posts()):?><ul class="mw-bullet-list"><?php while($q->have_posts()):$q->the_post();?><li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><?php if(get_the_excerpt()):?> — <?php echo esc_html(wp_trim_words(get_the_excerpt(),18));?><?php endif;?></li><?php endwhile;?></ul><?php else:?><p>Verified biographical notices will appear here following editorial review.</p><?php endif; wp_reset_postdata();?></section>
  <section class="mw-panel mw-on-this-day"><h2>On this day</h2><?php $md=wp_date('m-d');$q=new WP_Query(array('post_type'=>'bsw_portal_item','post_status'=>'publish','posts_per_page'=>8,'meta_query'=>array(array('key'=>'_bsw_portal_kind','value'=>'on_this_day'),array('key'=>'_bsw_portal_date','value'=>$md))));if($q->have_posts()):?><ul class="mw-bullet-list"><?php while($q->have_posts()):$q->the_post();?><li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a> — <?php echo esc_html(wp_trim_words(get_the_excerpt()?:get_the_content(),24));?></li><?php endwhile;?></ul><?php else:?><p>Historical anniversaries for <?php echo esc_html(wp_date('F j'));?> will appear here when curated and published.</p><?php endif;wp_reset_postdata();?></section>
  <section class="mw-panel mw-languages"><h2>Languages</h2><?php echo do_shortcode('[bigshor_languages]');?></section>
  <section class="mw-panel mw-community"><h2>Community and activity</h2><p><a href="<?php echo esc_url(home_url('/recent-changes/'));?>">Recent changes</a> · <a href="<?php echo esc_url(home_url('/wiki-contributors/'));?>">Contributors</a> · <a href="<?php echo esc_url(home_url('/community-portal/'));?>">Community portal</a></p><p><?php echo do_shortcode('[bigshor_recent_changes]');?></p></section>
</div>
<?php
else :
  if (function_exists('bigshor_wiki_breadcrumbs')) bigshor_wiki_breadcrumbs();
?>
<header class="mw-listing-header"><h1 class="page-title"><?php if (is_search()) { printf('Search results for: %s',esc_html(get_search_query())); } elseif(is_archive()){the_archive_title();} else { echo esc_html__('Latest articles','bigshor-wiki'); } ?></h1></header>
<?php if(have_posts()): ?><div class="mw-listing"><?php while(have_posts()):the_post(); ?><article class="mw-listing-item"><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><p class="mw-listing-meta"><?php echo esc_html(get_the_modified_date()); ?> · <?php the_author(); ?></p><?php the_excerpt(); ?></article><?php endwhile; ?></div><div class="pagination"><?php the_posts_pagination(); ?></div><?php else: ?><p>No pages found. Try another search.</p><?php endif; ?>
<?php endif; get_footer(); ?>
