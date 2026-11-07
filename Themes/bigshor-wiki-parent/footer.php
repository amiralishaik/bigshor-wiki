    </main>
  </div>
</div>
<?php $bsw_footer_settings = bsw_theme_get_settings(); ?>
<footer class="site-footer mw-site-footer">
  <div class="footer-inner mw-footer-grid">
    <div class="mw-footer-about"><a class="mw-footer-brand" href="<?php echo esc_url(home_url('/')); ?>"><strong><?php echo esc_html($bsw_footer_settings['brand_name']); ?></strong></a><p><?php echo esc_html($bsw_footer_settings['tagline']); ?></p><small>An independent knowledge project. Content is contributed and reviewed by the BIGSHOR community.</small></div>
    <nav class="mw-footer-column" aria-label="About BIGSHOR"><h2>About</h2><a href="<?php echo esc_url(home_url('/about/')); ?>">About BIGSHOR</a><a href="<?php echo esc_url(home_url('/editorial-policy/')); ?>">Editorial policy</a><a href="<?php echo esc_url(home_url('/contact/')); ?>">Contact</a></nav>
    <nav class="mw-footer-column" aria-label="Participation"><h2>Participate</h2><a href="<?php echo esc_url(home_url('/contributor-guide/')); ?>">How to contribute</a><a href="<?php echo esc_url(home_url('/community-portal/')); ?>">Community portal</a><a href="<?php echo esc_url(home_url('/recent-changes/')); ?>">Recent changes</a></nav>
    <nav class="mw-footer-column" aria-label="Legal and help"><h2>Legal &amp; help</h2><a href="<?php echo esc_url(home_url('/help-center/')); ?>">Help centre</a><a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">Privacy</a><a href="<?php echo esc_url(home_url('/terms/')); ?>">Terms of use</a></nav>
    <div class="mw-footer-bottom"><span>Text is available under the site's published editorial and reuse policies.</span><span>© <?php echo esc_html(date_i18n('Y')); ?> BIGSHOR.</span><?php if ($bsw_footer_settings['show_footer_menu'] === '1' && has_nav_menu('footer')) : ?><nav aria-label="Additional footer links"><?php wp_nav_menu(array('theme_location'=>'footer','container'=>false)); ?></nav><?php endif; ?></div>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
