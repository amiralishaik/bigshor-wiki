<?php
/** Directory of entity records and categorized encyclopedia articles. */
get_header();
?>
<header class="bsw-archive-header">
    <h1>Knowledge entities</h1>
    <?php get_search_form(); ?>
</header>
<?php
$topics = get_terms( array(
    'taxonomy' => 'wiki_topic',
    'slug' => array( 'people', 'companies', 'business-groups', 'cities', 'states', 'education-and-research', 'government-and-public-institutions' ),
    'hide_empty' => true,
) );
if ( ! is_wp_error( $topics ) && $topics ) :
?>
<nav aria-label="Browse entities by topic"><ul class="wiki-list">
    <?php foreach ( $topics as $topic ) :
        $url = get_term_link( $topic );
        if ( is_wp_error( $url ) ) continue;
        ?>
        <li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $topic->name ); ?></a> (<?php echo esc_html( number_format_i18n( $topic->count ) ); ?>)</li>
    <?php endforeach; ?>
</ul></nav>
<?php endif; ?>
<?php if ( have_posts() ) : ?>
<section><h2>Entity records</h2>
    <ul class="wiki-list">
        <?php while ( have_posts() ) : the_post(); ?>
        <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><?php the_excerpt(); ?></li>
        <?php endwhile; ?>
    </ul>
    <?php the_posts_pagination(); ?>
</section>
<?php elseif ( is_wp_error( $topics ) || ! $topics ) : ?>
<p><a href="<?php echo esc_url( get_post_type_archive_link( 'wiki_article' ) ); ?>">Browse published articles</a></p>
<?php endif; get_footer(); ?>
