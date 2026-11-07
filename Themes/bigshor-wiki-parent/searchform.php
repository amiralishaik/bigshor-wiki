<form role="search" method="get" class="search-form bsw-global-search" action="<?php echo esc_url(home_url('/')); ?>">
  <label class="screen-reader-text" for="bsw-global-search"><?php echo esc_html_x('Search BIGSHOR Wiki','label','bigshor-wiki'); ?></label>
  <input id="bsw-global-search" type="search" placeholder="<?php echo esc_attr_x('Search BIGSHOR Wiki…','placeholder','bigshor-wiki'); ?>" value="<?php echo esc_attr(get_search_query()); ?>" name="s" autocomplete="off" data-bsw-live-search>
  <button type="submit"><?php esc_html_e('Search','bigshor-wiki'); ?></button>
  <div class="bsw-global-suggestions" data-bsw-suggestions hidden role="listbox" aria-label="Search suggestions"></div>
</form>
