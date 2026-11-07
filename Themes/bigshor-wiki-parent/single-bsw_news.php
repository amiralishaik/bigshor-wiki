<?php
/**
 * BIGSHOR News single article.
 * Editorial approvals and source records remain managed by BIGSHOR Wiki Core.
 */
get_header();
while (have_posts()) :
    the_post();
    if (function_exists('bigshor_wiki_breadcrumbs')) {
        bigshor_wiki_breadcrumbs();
    }
    $terms = get_the_term_list(get_the_ID(), 'bsw_news_category', '', ', ');
    $read_minutes = max(1, (int) ceil(str_word_count(wp_strip_all_tags(get_post_field('post_content', get_the_ID()))) / 220));
?>
<article <?php post_class('bsw-news-article'); ?>>
    <header class="bsw-article-header bsw-news-article-header">
        <p class="bsw-eyebrow"><?php esc_html_e('BIGSHOR NEWS', 'bigshor-wiki'); ?></p>
        <h1 class="page-title"><?php the_title(); ?></h1>
        <?php if (has_excerpt()) : ?>
            <p class="bsw-article-deck"><?php echo esc_html(get_the_excerpt()); ?></p>
        <?php endif; ?>
        <?php if (!is_wp_error($terms) && $terms) : ?>
            <p class="bsw-news-categories"><?php echo wp_kses_post($terms); ?></p>
        <?php endif; ?>
        <div class="bsw-article-byline">
            <span><?php esc_html_e('By', 'bigshor-wiki'); ?> <?php the_author_posts_link(); ?></span>
            <span><?php esc_html_e('Published', 'bigshor-wiki'); ?> <?php echo esc_html(get_the_date()); ?></span>
            <span><?php esc_html_e('Updated', 'bigshor-wiki'); ?> <?php echo esc_html(get_the_modified_date()); ?></span>
            <span><?php echo esc_html(sprintf(_n('%s min read', '%s min read', $read_minutes, 'bigshor-wiki'), number_format_i18n($read_minutes))); ?></span>
        </div>
    </header>
    <?php if (has_post_thumbnail()) : ?>
        <figure class="bsw-news-featured-image">
            <?php the_post_thumbnail('large', array('loading' => 'eager')); ?>
            <?php if (get_the_post_thumbnail_caption()) : ?>
                <figcaption><?php echo esc_html(get_the_post_thumbnail_caption()); ?></figcaption>
            <?php endif; ?>
        </figure>
    <?php endif; ?>
    <div class="bsw-news-content article-body">
        <?php the_content(); ?>
        <?php wp_link_pages(array('before' => '<nav class="page-links">' . esc_html__('Pages:', 'bigshor-wiki'), 'after' => '</nav>')); ?>
    </div>
</article>
<?php
endwhile;
get_footer();
?>
