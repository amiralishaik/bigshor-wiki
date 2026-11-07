<?php
/**
 * BIGSHOR Wiki Phase 5.1 — opt-in AI editorial assistance and internal-link discovery.
 * Requires an administrator-configured OpenAI-compatible HTTPS endpoint.
 * AI output is advisory only and is never saved or published automatically.
 */
if (!defined('ABSPATH')) exit;
if (!class_exists('BSW_Phase5_Assistance')) {
final class BSW_Phase5_Assistance {
    const OPTION = 'bsw_p5_ai_settings';

    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 46);
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('add_meta_boxes_wiki_article', array(__CLASS__, 'metabox'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function settings() {
        $saved = get_option(self::OPTION, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), array(
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'model' => '',
            'api_key' => '',
            'enabled' => 0,
        ));
    }

    public static function menu() {
        add_submenu_page(
            'edit.php?post_type=wiki_article',
            'AI Editorial Settings',
            'AI Editorial Settings',
            'manage_options',
            'bsw-ai-editorial-settings',
            array(__CLASS__, 'settings_page')
        );
    }

    public static function register_settings() {
        register_setting('bsw_p5_ai_group', self::OPTION, array(__CLASS__, 'sanitize_settings'));
    }

    public static function sanitize_settings($input) {
        $old = self::settings();
        $input = is_array($input) ? $input : array();
        $endpoint = isset($input['endpoint']) ? esc_url_raw(trim($input['endpoint'])) : $old['endpoint'];
        if (!$endpoint || strtolower((string) wp_parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
            $endpoint = $old['endpoint'];
        }
        $host = wp_parse_url($endpoint, PHP_URL_HOST);
        if (!$host || preg_match('/(^|\.)((localhost)|(\d{1,3}\.){3}\d{1,3})$/i', $host)) {
            $endpoint = $old['endpoint'];
        }
        $model = isset($input['model']) ? sanitize_text_field($input['model']) : $old['model'];
        $key = $old['api_key'];
        if (!empty($input['clear_key'])) {
            $key = '';
        } elseif (isset($input['api_key']) && trim((string) $input['api_key']) !== '') {
            $key = trim(sanitize_text_field($input['api_key']));
        }
        return array(
            'endpoint' => $endpoint,
            'model' => substr($model, 0, 120),
            'api_key' => $key,
            'enabled' => !empty($input['enabled']) ? 1 : 0,
        );
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = self::settings();
        echo '<div class="wrap"><h1>BIGSHOR AI Editorial Settings</h1>';
        echo '<p>Connect an OpenAI-compatible chat-completions endpoint. AI requests run only when an authorised editor clicks the review button. Draft content is sent to the configured provider; review its privacy and data-retention terms before enabling.</p>';
        echo '<form method="post" action="options.php">';
        settings_fields('bsw_p5_ai_group');
        echo '<table class="form-table"><tr><th><label for="bsw-ai-enabled">Enable AI assistance</label></th><td><label><input id="bsw-ai-enabled" type="checkbox" name="' . esc_attr(self::OPTION) . '[enabled]" value="1" ' . checked(1, (int)$s['enabled'], false) . '/> Allow editors to request editorial assistance</label></td></tr>';
        echo '<tr><th><label for="bsw-ai-endpoint">API endpoint</label></th><td><input class="regular-text" type="url" id="bsw-ai-endpoint" name="' . esc_attr(self::OPTION) . '[endpoint]" value="' . esc_attr($s['endpoint']) . '" required/><p class="description">Must use HTTPS. Example: https://api.openai.com/v1/chat/completions</p></td></tr>';
        echo '<tr><th><label for="bsw-ai-model">Model name</label></th><td><input class="regular-text" type="text" id="bsw-ai-model" name="' . esc_attr(self::OPTION) . '[model]" value="' . esc_attr($s['model']) . '" required/><p class="description">Enter a model supported by your provider.</p></td></tr>';
        echo '<tr><th><label for="bsw-ai-key">API key</label></th><td><input class="regular-text" autocomplete="new-password" type="password" id="bsw-ai-key" name="' . esc_attr(self::OPTION) . '[api_key]" value="" placeholder="' . ($s['api_key'] ? 'Saved key is configured; leave blank to retain' : 'Paste API key') . '"/><p class="description">The saved key is never displayed. It is stored in the WordPress database; restrict administrator access and database backups.</p>';
        if ($s['api_key']) echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION) . '[clear_key]" value="1"/> Clear saved API key</label>';
        echo '</td></tr></table>';
        submit_button('Save AI settings');
        echo '</form><hr/><p><strong>Safety:</strong> AI suggestions are not fact-checks. They must be checked against reliable sources and approved by a human editor. The plugin does not auto-save or auto-publish AI responses.</p></div>';
    }

    public static function routes() {
        register_rest_route('bigshor/v1', '/editorial-assist', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'editorial_assist'),
            'permission_callback' => function($request) {
                $id = absint($request->get_param('post_id'));
                return $id && current_user_can('edit_post', $id);
            },
            'args' => array('post_id'=>array('required'=>true,'sanitize_callback'=>'absint')),
        ));
        register_rest_route('bigshor/v1', '/internal-link-suggestions', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'link_suggestions'),
            'permission_callback' => function($request) {
                $id = absint($request->get_param('post_id'));
                return $id && current_user_can('edit_post', $id);
            },
            'args' => array('post_id'=>array('required'=>true,'sanitize_callback'=>'absint')),
        ));
    }

    /** Shared throttle for AI endpoints; limits provider spend and accidental repeat clicks. */
    public static function consume_ai_request_slot($user_id) {
        $user_id = absint($user_id);
        if (!$user_id) return new WP_Error('bsw_ai_auth_required', 'Sign in before requesting AI assistance.', array('status'=>401));
        $key = 'bsw_p5_ai_rl_' . $user_id;
        $count = (int) get_transient($key);
        if ($count >= 6) return new WP_Error('bsw_ai_rate_limited', 'AI assistance is limited to six requests per user every ten minutes. Please try again later.', array('status'=>429));
        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    public static function editorial_assist($request) {
        $settings = self::settings();
        if (empty($settings['enabled'])) return new WP_Error('bsw_ai_disabled', 'AI assistance is disabled by an administrator.', array('status'=>403));
        if (empty($settings['api_key']) || empty($settings['model'])) return new WP_Error('bsw_ai_not_configured', 'An administrator must configure the API key and model first.', array('status'=>400));
        $post = get_post(absint($request->get_param('post_id')));
        if (!$post || $post->post_type !== 'wiki_article') return new WP_Error('bsw_invalid_article', 'Article not found.', array('status'=>404));
        $slot = self::consume_ai_request_slot(get_current_user_id());
        if (is_wp_error($slot)) return $slot;
        $content = trim(wp_strip_all_tags(strip_shortcodes($post->post_content)));
        if ($content === '') return new WP_Error('bsw_empty_article', 'Add article content before requesting a review.', array('status'=>400));
        $content = function_exists('mb_substr') ? mb_substr($content, 0, 18000) : substr($content, 0, 18000);
        $system = 'You are an editorial research assistant for BIGSHOR Wiki. Review the supplied encyclopedia draft critically and neutrally. Never invent facts, citations, URLs, quotations, publication dates, named sources, or source contents. Do not claim to have independently verified a claim. Clearly distinguish observations, hypotheses, and items requiring human verification. Do not write promotional copy. Do not publish or rewrite the article as if verified. Return concise sections: 1) neutral summary, 2) specific factual claims needing source checks, 3) citation and source gaps, 4) neutrality and conflict-of-interest concerns, 5) structure/readability improvements, 6) questions for the human editor.';
        $payload = array(
            'model' => $settings['model'],
            'messages' => array(
                array('role'=>'system','content'=>$system),
                array('role'=>'user','content'=>"Article title: " . get_the_title($post) . "\n\nDraft text:\n" . $content),
            ),
            'temperature' => 0.2,
        );
        $response = wp_safe_remote_post($settings['endpoint'], array(
            'timeout' => 35,
            'redirection' => 0,
            'sslverify' => true,
            'reject_unsafe_urls' => true,
            'limit_response_size' => 65536,
            'headers' => array('Authorization'=>'Bearer ' . $settings['api_key'], 'Content-Type'=>'application/json'),
            'body' => wp_json_encode($payload),
        ));
        if (is_wp_error($response)) return new WP_Error('bsw_ai_request_failed', 'The AI provider request failed: ' . $response->get_error_message(), array('status'=>502));
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            $message = isset($body['error']['message']) ? sanitize_text_field($body['error']['message']) : 'The configured AI provider returned an error.';
            return new WP_Error('bsw_ai_provider_error', $message, array('status'=>502));
        }
        $answer = isset($body['choices'][0]['message']['content']) ? $body['choices'][0]['message']['content'] : '';
        if (!is_string($answer) || trim($answer) === '') return new WP_Error('bsw_ai_empty_response', 'The AI provider returned no usable editorial review.', array('status'=>502));
        $answer = wp_strip_all_tags($answer);
        return rest_ensure_response(array(
            'review' => $answer,
            'notice' => 'AI-generated editorial assistance only. Claims and sources have not been independently verified. Human review is required.',
        ));
    }

    public static function link_suggestions($request) {
        $id = absint($request->get_param('post_id'));
        $post = get_post($id);
        if (!$post || $post->post_type !== 'wiki_article') return new WP_Error('bsw_invalid_article', 'Article not found.', array('status'=>404));
        $terms = wp_get_object_terms($id, 'wiki_topic', array('fields'=>'ids'));
        if (is_wp_error($terms) || empty($terms)) return rest_ensure_response(array('suggestions'=>array(),'message'=>'Assign one or more shared topics to discover related articles.'));
        $query = new WP_Query(array(
            'post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>8,
            'post__not_in'=>array($id),'ignore_sticky_posts'=>true,'no_found_rows'=>true,
            'tax_query'=>array(array('taxonomy'=>'wiki_topic','field'=>'term_id','terms'=>array_map('absint',$terms))),
        ));
        $items = array();
        foreach ($query->posts as $candidate) {
            $items[] = array('title'=>get_the_title($candidate),'url'=>get_permalink($candidate),'edit_url'=>get_edit_post_link($candidate->ID, 'raw'));
        }
        return rest_ensure_response(array('suggestions'=>$items));
    }

    public static function metabox() {
        add_meta_box('bsw-p5-assistance', 'BIGSHOR AI Editorial Assistance & Internal Links', array(__CLASS__, 'metabox_html'), 'wiki_article', 'side', 'default');
    }

    public static function metabox_html($post) {
        $s = self::settings();
        echo '<p>Request a source-aware editorial critique. Draft text is sent to the AI provider configured by your administrator.</p>';
        if (!empty($s['enabled']) && !empty($s['api_key']) && !empty($s['model'])) {
            echo '<button type="button" class="button button-primary" id="bsw-p5-run-ai" data-post="' . esc_attr($post->ID) . '">Request AI editorial review</button>';
        } else {
            echo '<p><em>AI is not configured or enabled. An administrator can configure it under Wiki Articles → AI Editorial Settings.</em></p>';
        }
        echo '<div id="bsw-p5-ai-result" style="margin-top:10px;max-height:320px;overflow:auto;white-space:pre-wrap"></div>';
        echo '<hr/><p><strong>Related article suggestions</strong></p><button type="button" class="button" id="bsw-p5-links" data-post="' . esc_attr($post->ID) . '">Find internal links</button><div id="bsw-p5-links-result" style="margin-top:8px"></div>';
        echo '<p class="description">Suggestions are based on shared Wiki Topics. Add links manually after checking relevance.</p>';
    }

    public static function assets($hook) {
        if (!in_array($hook, array('post.php','post-new.php'), true)) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'wiki_article') return;
        wp_register_script('bsw-p5-assistance', false, array(), '19.1.0', true);
        wp_enqueue_script('bsw-p5-assistance');
        wp_localize_script('bsw-p5-assistance', 'BSWPhase5Assist', array(
            'root'=>esc_url_raw(rest_url('bigshor/v1/')),
            'nonce'=>wp_create_nonce('wp_rest'),
            'error'=>'The request could not be completed. Please check the settings and try again.',
        ));
        $js = "(function(){function request(url,opts,box,button){button.disabled=true;box.textContent='Working…';fetch(BSWPhase5Assist.root+url,opts).then(function(r){return r.json().then(function(d){if(!r.ok)throw new Error(d.message||BSWPhase5Assist.error);return d;});}).then(function(d){if(d.review){box.textContent=d.notice+'\\n\\n'+d.review;}else if(d.suggestions){if(!d.suggestions.length){box.textContent=d.message||'No related articles found.';return;}box.innerHTML='';d.suggestions.forEach(function(item){var p=document.createElement('p');var a=document.createElement('a');a.href=item.url;a.textContent=item.title;a.target='_blank';a.rel='noopener';p.appendChild(a);box.appendChild(p);});}}).catch(function(e){box.textContent=e.message;}).finally(function(){button.disabled=false;});}document.addEventListener('click',function(e){var ai=e.target.closest('#bsw-p5-run-ai');if(ai){var box=document.getElementById('bsw-p5-ai-result');request('editorial-assist',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':BSWPhase5Assist.nonce},body:JSON.stringify({post_id:ai.getAttribute('data-post')})},box,ai);}var links=e.target.closest('#bsw-p5-links');if(links){var box2=document.getElementById('bsw-p5-links-result');request('internal-link-suggestions?post_id='+encodeURIComponent(links.getAttribute('data-post')),{method:'GET',headers:{'X-WP-Nonce':BSWPhase5Assist.nonce}},box2,links);}});})();";
        wp_add_inline_script('bsw-p5-assistance', $js);
    }
}
BSW_Phase5_Assistance::boot();
}
