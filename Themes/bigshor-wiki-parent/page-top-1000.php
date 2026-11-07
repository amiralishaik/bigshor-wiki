<?php
/** Template Name: BIGSHOR Top 1000 Directory */
if (!defined('ABSPATH')) exit;
get_header();
?>
<main id="primary" class="site-main bsw-top1000-page">
 <div class="bsw-top1000-intro"><p class="bsw-eyebrow">GLOBAL KNOWLEDGE COLLECTION</p><h1><?php the_title(); ?></h1><p>Discover independently reviewed knowledge resources covering people, places, organisations, businesses, history, culture, science, technology and ideas.</p></div>
 <?php while (have_posts()) : the_post(); if (trim(get_the_content())) : ?><div class="bsw-top1000-page-content"><?php the_content(); ?></div><?php endif; endwhile; ?>
 <?php echo do_shortcode('[bigshor_top_1000 limit="100"]'); ?>
</main>
<?php get_footer(); ?>
