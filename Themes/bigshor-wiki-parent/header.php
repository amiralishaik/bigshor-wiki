<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
if ( ! function_exists('has_site_icon') || ! has_site_icon() ) {
    $bsw_icon_uri = get_template_directory_uri() . '/assets/brand/bigshor-bs-monogram.jpg';
    echo '<link rel="icon" href="' . esc_url($bsw_icon_uri) . '" type="image/jpeg" sizes="512x512">' . "\\n";
    echo '<link rel="apple-touch-icon" href="' . esc_url($bsw_icon_uri) . '">' . "\\n";
}
?>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main-content"><?php esc_html_e('Skip to content', 'bigshor-wiki'); ?></a>
<?php $bs = bsw_theme_get_settings(); ?>
<header class="mw-header">
  <div class="mw-header-inner">
    <button class="mw-sidebar-toggle" type="button" aria-expanded="false" aria-controls="mw-sidebar"><span aria-hidden="true">☰</span> <span>Main menu</span></button>
    <a class="mw-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr($bs['brand_name']); ?> home">
      <picture class="mw-brand-mark">
        <source type="image/avif" srcset="<?php echo esc_url(get_template_directory_uri() . '/assets/brand/bigshor-bs-monogram.avif'); ?>">
        <source type="image/webp" srcset="<?php echo esc_url(get_template_directory_uri() . '/assets/brand/bigshor-bs-monogram.webp'); ?>">
        <img src="<?php echo esc_url(get_template_directory_uri() . '/assets/brand/bigshor-bs-monogram.jpg'); ?>" srcset="<?php echo esc_url(get_template_directory_uri() . '/assets/brand/bigshor-bs-monogram.jpg'); ?> 2x" alt="" width="42" height="42" decoding="async">
      </picture>
      <span><strong><?php echo esc_html($bs['brand_name']); ?></strong><small>THE FREE KNOWLEDGE ENCYCLOPEDIA</small></span>
    </a>
    <div class="mw-search"><?php get_search_form(); ?></div>
    <div class="mw-header-actions">
      <a class="mw-header-language" href="<?php echo esc_url(home_url('/wiki-languages/')); ?>" aria-label="Browse languages">Languages</a>
      <button class="mw-appearance-toggle" type="button" aria-expanded="false" aria-controls="mw-appearance-panel">
        <span aria-hidden="true">A</span> Appearance
      </button>
      <div class="mw-personal-tools">
      <?php if (is_user_logged_in()) : ?>
        <a href="<?php echo esc_url(home_url('/my-account/')); ?>">My account</a>
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log out</a>
      <?php else : ?>
        <a href="<?php echo esc_url(home_url('/register/')); ?>">Create account</a>
        <a href="<?php echo esc_url(home_url('/login/')); ?>">Log in</a>
      <?php endif; ?>
      </div>
    </div>
  </div>
  <section id="mw-appearance-panel" class="mw-appearance-panel" hidden aria-label="Reading appearance">
    <div class="mw-appearance-inner">
      <div class="mw-appearance-group">
        <strong>Text size</strong>
        <button type="button" data-mw-text="small">Small</button>
        <button type="button" data-mw-text="standard" aria-pressed="true">Standard</button>
        <button type="button" data-mw-text="large">Large</button>
      </div>
      <div class="mw-appearance-group">
        <strong>Content width</strong>
        <button type="button" data-mw-width="standard" aria-pressed="true">Standard</button>
        <button type="button" data-mw-width="wide">Wide</button>
      </div>
      <div class="mw-appearance-group">
        <strong>Colour</strong>
        <button type="button" data-mw-colour="light" aria-pressed="true">Light</button>
        <button type="button" data-mw-colour="dark">Dark</button>
      </div>
      <button class="mw-appearance-reset" type="button" data-mw-reset>Reset appearance</button>
    </div>
  </section>
</header>
<div class="mw-site-layout">
  <aside id="mw-sidebar" class="mw-sidebar" aria-label="Main navigation">
    <div class="mw-sidebar-section"><h2>Navigation</h2>
      <?php if (has_nav_menu('primary')) : ?>
        <?php wp_nav_menu(array('theme_location'=>'primary','container'=>false,'menu_class'=>'mw-sidebar-menu','fallback_cb'=>'bigshor_wiki_sidebar_fallback')); ?>
      <?php else : bigshor_wiki_sidebar_fallback(); endif; ?>
    </div>
    <div class="mw-sidebar-section"><h2>Contribute</h2>
      <ul>
        <li><a href="<?php echo esc_url(home_url('/help-center/')); ?>">Help</a></li>
        <li><a href="<?php echo esc_url(home_url('/contributor-guide/')); ?>">Learn to contribute</a></li>
        <li><a href="<?php echo esc_url(home_url('/community-portal/')); ?>">Community portal</a></li>
        <li><a href="<?php echo esc_url(home_url('/recent-changes/')); ?>">Recent changes</a><?php if (is_user_logged_in()) : ?><a href="<?php echo esc_url(home_url('/my-watchlist/')); ?>">Watchlist</a><?php endif; ?></li>
        <li><a href="<?php echo esc_url(home_url('/wiki-contributors/')); ?>">Contributors</a><a href="<?php echo esc_url(home_url('/my-watchlist/')); ?>">My watchlist</a></li>
      </ul>
    </div>
    <div class="mw-sidebar-section"><h2>BIGSHOR projects</h2>
      <ul><li><a href="<?php echo esc_url(get_post_type_archive_link('bsw_news') ?: home_url('/news/')); ?>">BIGSHOR News</a></li><li><a href="<?php echo esc_url(home_url('/media-library/')); ?>">Media library</a></li></ul>
    </div>
  </aside>
  <div class="mw-main-column">
    <div class="mw-page-tabs">
      <div class="mw-tabs-left"><a class="<?php echo is_front_page() ? 'is-active' : ''; ?>" href="<?php echo esc_url(home_url('/')); ?>">Main page</a><a href="<?php echo esc_url(is_singular('wiki_article') ? get_comments_link() : home_url('/community-portal/')); ?>"><?php echo is_singular('wiki_article') ? 'Talk' : 'Community'; ?></a></div>
      <div class="mw-tabs-right">
        <a class="is-active" href="<?php echo esc_url(is_singular() ? get_permalink() : home_url('/')); ?>">Read</a>
        <?php if (is_singular() && current_user_can('edit_post', get_queried_object_id())) : ?>
          <a href="<?php echo esc_url(get_edit_post_link()); ?>">Edit</a>
        <?php elseif (is_singular() && get_post_type() === 'wiki_article') : ?>
          <a href="<?php echo esc_url(home_url('/contribute/')); ?>">Suggest an edit</a>
        <?php endif; ?>
        <?php if (is_singular('wiki_article')) : ?>
          <a href="<?php echo esc_url(add_query_arg('bsw_history',1,get_permalink())); ?>">View history</a>
          <a href="#article-tools">Tools</a>
        <?php endif; ?>
        <a href="<?php echo esc_url(home_url('/recent-changes/')); ?>">Recent changes</a><?php if (is_user_logged_in()) : ?><a href="<?php echo esc_url(home_url('/my-watchlist/')); ?>">Watchlist</a><?php endif; ?>
      </div>
    </div>
    <main id="main-content" class="mw-content" tabindex="-1">
