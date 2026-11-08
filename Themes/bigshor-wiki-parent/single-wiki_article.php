<?php
/** BIGSHOR Wiki article view; Core owns revision, source-edit and history routes. */
get_header();
while ( have_posts() ) : the_post();
    $article_id = get_the_ID();
    $terms = get_the_term_list( $article_id, 'wiki_topic', '', ' · ' );
    $history_url = add_query_arg( 'bsw_history', 1, get_permalink( $article_id ) );
    $edit_url = add_query_arg( 'bsw_edit', $article_id, get_permalink( $article_id ) );
    if ( function_exists( 'bigshor_wiki_breadcrumbs' ) ) bigshor_wiki_breadcrumbs();
?>
<header class="bsw-article-header">
  <div class="bsw-article-kicker">From BIGSHOR Wiki, the free knowledge encyclopedia</div>
  <h1 class="page-title"><?php the_title(); ?></h1>
  <?php if ( has_excerpt() ) : ?><p class="bsw-article-deck"><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?>
  <div class="bsw-article-byline"><span>Last updated <?php echo esc_html( get_the_modified_date() ); ?></span><span><?php echo esc_html( get_the_author() ); ?></span></div>
</header>
<div class="bsw-article-language-row">
  <span>BIGSHOR Wiki article</span>
  <span class="bsw-article-page-meta">Article · <a href="<?php echo esc_url( $history_url ); ?>">Revision history</a></span>
</div>
<nav class="bsw-page-tools" aria-label="Article tools">
  <a href="<?php echo esc_url( get_permalink( $article_id ) ); ?>" aria-current="page">Read</a>
  <?php if ( current_user_can( 'edit_post', $article_id ) ) : ?>
    <a href="<?php echo esc_url( $edit_url ); ?>">Edit</a>
  <?php endif; ?>
  <a href="<?php echo esc_url( $history_url ); ?>">View history</a>
  <a href="#comments">Talk</a>
  <button type="button" data-bsw-copy-link>Copy link</button>
  <button type="button" onclick="window.print();return false;">Print</button>
</nav>
<div class="bsw-reading-progress" aria-hidden="true"><span></span></div>
<article id="article-content" <?php post_class( 'article-body bsw-article-content' ); ?>>
  <?php the_content(); wp_link_pages( array( 'before' => '<nav class="bsw-page-links" aria-label="Article pages">Pages: ', 'after' => '</nav>' ) ); ?>
</article>
<section class="bsw-article-bottom-meta" aria-label="Article metadata">
  <div class="bsw-article-topics"><strong>Topics:</strong> <?php echo $terms ? wp_kses_post( $terms ) : esc_html__( 'Not yet categorised', 'bigshor-wiki' ); ?></div>
  <div class="bsw-article-dates">First published <?php echo esc_html( get_the_date() ); ?> · Last updated <?php echo esc_html( get_the_modified_date() ); ?></div>
</section>
<?php if ( ! function_exists( 'bsw_theme_get_settings' ) || ( bsw_theme_get_settings()['show_related'] ?? '1' ) === '1' ) : ?>
<section class="bsw-related-section"><h2>Related articles</h2><?php echo do_shortcode( '[bigshor_wiki_related limit="5"]' ); ?></section>
<?php endif; ?>
<section class="bsw-related-section bsw-article-talk"><h2>Talk</h2><p>Discuss improvements to this article and suggest reliable sources.</p><?php echo do_shortcode( '[bigshor_wiki_discussion]' ); ?></section>
<?php comments_template(); endwhile; get_footer();
