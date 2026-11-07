<?php
/**
 * BIGSHOR News archive.
 * Content type registration and editorial governance are owned by BIGSHOR Wiki Core.
 */
get_header();
if (function_exists('bigshor_wiki_breadcrumbs')) {
    bigshor_wiki_breadcrumbs();
}
?>
<header class="bsw-archive-header bsw-news-archive-header">
    <p class="bsw-eyebrow"><?php esc_html_e('INDEPENDENT NEWS COVERAGE', 'bigshor-wiki'); ?></p>
    <h1 class="page-title"><?php post_type_archive_title(); ?></h1>
    <p class="subtitle"><?php esc_html_e('News reporting and updates published under BIGSHOR editorial standards.', 'bigshor-wiki'); ?></p>
    <?php get_search_form(); ?>
</header>
<?php if (have_posts()) : ?>
    <div class="bsw-archive-grid bsw-news-grid">
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class('bsw-archive-card bsw-news-card'); ?>>
                <?php if (has_post_thumbnail()) : ?>
                    <a class="bsw-archive-image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
                        <?php the_post_thumbnail('medium_large'); ?>
                    </a>
                <?php endif; ?>
                <div class="bsw-archive-card-body">
                    <p class="bsw-card-meta"><?php echo esc_html(get_the_date()); ?></p>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <?php
                    $terms = get_the_term_list(get_the_ID(), 'bsw_news_category', '', ', ');
                    if (!is_wp_error($terms) && $terms) :
                    ?>
                        <p class="bsw-news-categories"><?php echo wp_kses_post($terms); ?></p>
                    <?php endif; ?>
                    <div class="bsw-news-excerpt"><?php the_excerpt(); ?></div>
                    <a class="bsw-card-link" href="<?php the_permalink(); ?>"><?php esc_html_e('Read news', 'bigshor-wiki'); ?> <span aria-hidden="true">→</span></a>
                </div>
            </article>
        <?php endwhile; ?>
    </div>
    <nav class="pagination" aria-label="<?php esc_attr_e('News pagination', 'bigshor-wiki'); ?>">
        <?php the_posts_pagination(array('mid_size' => 2, 'prev_text' => esc_html__('← Previous', 'bigshor-wiki'), 'next_text' => esc_html__('Next →', 'bigshor-wiki'))); ?>
    </nav>
<?php else : ?>
    <p><?php esc_html_e('No news articles have been published yet.', 'bigshor-wiki'); ?></p>
<?php endif; ?>
<?php get_footer(); ?>
