<?php
/**
 * BIGSHOR Wiki Phase 7: Global Knowledge Network.
 * Adds knowledge entities/relationships, profile verification, language links,
 * expert directory and event timelines without altering existing article data.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('BSW_Phase7_Global_Network')) {
final class BSW_Phase7_Global_Network {
    const VERSION = '21.1.0';
    const OPTION = 'bsw_phase7_settings';

    public static function init() {
        add_action('init', array(__CLASS__, 'register_types'), 9);
        add_action('add_meta_boxes', array(__CLASS__, 'meta_boxes'));
        add_action('save_post', array(__CLASS__, 'save_metadata'), 20, 2);
        add_action('init', array(__CLASS__, 'register_shortcodes'), 25);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'styles'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
        add_action('wp_head', array(__CLASS__, 'output_schema'), 30);
    }

    public static function defaults() {
        return array('knowledge_graph'=>1, 'profiles'=>1, 'multilingual'=>1, 'experts'=>1, 'timeline'=>1, 'public_api'=>1, 'structured_data'=>1);
    }
    public static function settings() { return wp_parse_args(get_option(self::OPTION, array()), self::defaults()); }
    private static function enabled($key) { $s=self::settings(); return !empty($s[$key]); }

    public static function register_types() {
        $types = array(
            'bsw_knowledge_entity' => array('Knowledge Entities','Knowledge Entity','dashicons-networking','knowledge_graph','knowledge-entities'),
            'bsw_profile' => array('People & Organisation Profiles','Profile','dashicons-id','profiles','profiles'),
            'bsw_knowledge_event' => array('Knowledge Timeline Events','Timeline Event','dashicons-calendar-alt','timeline','knowledge-events'),
        );
        foreach ($types as $type=>$v) {
            if (!self::enabled($v[3])) continue;
            register_post_type($type, array(
                'labels'=>array('name'=>$v[0], 'singular_name'=>$v[1], 'add_new_item'=>'Add '.$v[1], 'edit_item'=>'Edit '.$v[1]),
                'public'=>true, 'show_in_rest'=>true, 'has_archive'=>true,
                'menu_icon'=>$v[2], 'supports'=>array('title','editor','excerpt','thumbnail','author','revisions'),
                'rewrite'=>array('slug'=>$v[4]), 'capability_type'=>'post', 'map_meta_cap'=>true,
            ));
        }
    }

    public static function meta_boxes() {
        if (self::enabled('knowledge_graph') || self::enabled('multilingual')) {
            $types=get_post_types(array('public'=>true),'names');
            foreach ($types as $type) {
                if (in_array($type,array('attachment','revision','nav_menu_item'),true)) continue;
                add_meta_box('bsw_p7_relationships','BIGSHOR Knowledge Connections',array(__CLASS__,'render_relationship_box'),$type,'normal','default');
            }
        }
        if (post_type_exists('bsw_profile')) add_meta_box('bsw_p7_profile','BIGSHOR Profile & Verification',array(__CLASS__,'render_profile_box'),'bsw_profile','side','default');
        if (post_type_exists('bsw_knowledge_event')) add_meta_box('bsw_p7_event','BIGSHOR Timeline Details',array(__CLASS__,'render_event_box'),'bsw_knowledge_event','normal','default');
    }

    public static function render_relationship_box($post) {
        wp_nonce_field('bsw_p7_relationships','bsw_p7_relationship_nonce');
        $related=(array)get_post_meta($post->ID,'_bsw_p7_related_ids',true);
        $language=get_post_meta($post->ID,'_bsw_p7_language',true);
        $group=get_post_meta($post->ID,'_bsw_p7_translation_group',true);
        echo '<p><label for="bsw_p7_related"><strong>Related content IDs</strong></label><br><input class="widefat" id="bsw_p7_related" name="bsw_p7_related" value="'.esc_attr(implode(',',array_map('absint',$related))).'" placeholder="e.g. 123, 456"><span class="description">Enter published WordPress content IDs separated by commas. Connections are displayed on the knowledge graph.</span></p>';
        if (self::enabled('multilingual')) {
            echo '<p><label for="bsw_p7_language"><strong>Content language (BCP 47)</strong></label><br><input class="widefat" id="bsw_p7_language" name="bsw_p7_language" value="'.esc_attr($language).'" placeholder="en, ar, hi-IN"></p>';
            echo '<p><label for="bsw_p7_translation_group"><strong>Translation group ID</strong></label><br><input class="widefat" id="bsw_p7_translation_group" name="bsw_p7_translation_group" value="'.esc_attr($group).'" placeholder="Shared ID for translated versions"><span class="description">Use the same group ID on each language version. This links translations; it does not machine-translate content.</span></p>';
        }
    }

    public static function render_profile_box($post) {
        wp_nonce_field('bsw_p7_profile','bsw_p7_profile_nonce');
        $verified=get_post_meta($post->ID,'_bsw_p7_verified',true);
        $verification_status=get_post_meta($post->ID,'_bsw_p7_verification_status',true);
        if (!$verification_status) $verification_status=($verified==='1'?'verified':'unverified');
        $expert=get_post_meta($post->ID,'_bsw_p7_expert',true);
        $field=get_post_meta($post->ID,'_bsw_p7_expertise',true);
        $organisation=get_post_meta($post->ID,'_bsw_p7_organisation',true);
        echo '<p><label><input type="checkbox" name="bsw_p7_expert" value="1" '.checked($expert,'1',false).'> List in Expert Network</label></p>';
        echo '<p><label for="bsw_p7_expertise"><strong>Expertise / subject areas</strong></label><input class="widefat" id="bsw_p7_expertise" name="bsw_p7_expertise" value="'.esc_attr($field).'"></p>';
        echo '<p><label for="bsw_p7_organisation"><strong>Organisation</strong></label><input class="widefat" id="bsw_p7_organisation" name="bsw_p7_organisation" value="'.esc_attr($organisation).'"></p>';
        if (current_user_can('manage_options')) { echo '<p><label for="bsw_p7_verification_status"><strong>Verification status</strong></label><select class="widefat" id="bsw_p7_verification_status" name="bsw_p7_verification_status">'; foreach (array('unverified'=>'Unverified','pending'=>'Pending review','verified'=>'Verified','rejected'=>'Rejected') as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($verification_status,$key,false).'>'.esc_html($label).'</option>'; echo '</select></p><p class="description">Only administrators can change verification status. Verification is an editorial decision, not an identity guarantee.</p>'; } else echo '<p><strong>Verification:</strong> '.esc_html(ucfirst($verification_status)).'</p>'; 
    }

    public static function render_event_box($post) {
        wp_nonce_field('bsw_p7_event','bsw_p7_event_nonce');
        $date=get_post_meta($post->ID,'_bsw_p7_event_date',true);
        $end=get_post_meta($post->ID,'_bsw_p7_event_end_date',true);
        $related=get_post_meta($post->ID,'_bsw_p7_event_related_id',true);
        echo '<p><label for="bsw_p7_event_date"><strong>Event date</strong></label><input class="regular-text" type="date" id="bsw_p7_event_date" name="bsw_p7_event_date" value="'.esc_attr($date).'" required></p>';
        echo '<p><label for="bsw_p7_event_end_date"><strong>End date (optional)</strong></label><input class="regular-text" type="date" id="bsw_p7_event_end_date" name="bsw_p7_event_end_date" value="'.esc_attr($end).'"></p>';
        echo '<p><label for="bsw_p7_event_related_id"><strong>Related content ID (optional)</strong></label><input class="regular-text" type="number" min="1" id="bsw_p7_event_related_id" name="bsw_p7_event_related_id" value="'.esc_attr($related).'"></p>';
    }

    public static function save_metadata($post_id,$post) {
        if (!$post || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
        if (!current_user_can('edit_post',$post_id)) return;
        if (isset($_POST['bsw_p7_relationship_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p7_relationship_nonce'])),'bsw_p7_relationships')) {
            if (isset($_POST['bsw_p7_related'])) {
                $ids=array_filter(array_unique(array_map('absint',explode(',',sanitize_text_field(wp_unslash($_POST['bsw_p7_related']))))));
                $ids=array_values(array_filter($ids,function($id) use ($post_id){return $id!==$post_id && get_post_status($id)==='publish';}));
                update_post_meta($post_id,'_bsw_p7_related_ids',$ids);
            }
            if (self::enabled('multilingual')) {
                if (isset($_POST['bsw_p7_language'])) update_post_meta($post_id,'_bsw_p7_language',sanitize_text_field(wp_unslash($_POST['bsw_p7_language'])));
                if (isset($_POST['bsw_p7_translation_group'])) update_post_meta($post_id,'_bsw_p7_translation_group',sanitize_title(wp_unslash($_POST['bsw_p7_translation_group'])));
            }
        }
        if ($post->post_type==='bsw_profile' && isset($_POST['bsw_p7_profile_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p7_profile_nonce'])),'bsw_p7_profile')) {
            update_post_meta($post_id,'_bsw_p7_expert',isset($_POST['bsw_p7_expert'])?'1':'0');
            update_post_meta($post_id,'_bsw_p7_expertise',isset($_POST['bsw_p7_expertise'])?sanitize_text_field(wp_unslash($_POST['bsw_p7_expertise'])):'');
            update_post_meta($post_id,'_bsw_p7_organisation',isset($_POST['bsw_p7_organisation'])?sanitize_text_field(wp_unslash($_POST['bsw_p7_organisation'])):'');
            if (current_user_can('manage_options')) { $status=isset($_POST['bsw_p7_verification_status'])?sanitize_key(wp_unslash($_POST['bsw_p7_verification_status'])):'unverified'; if (!in_array($status,array('unverified','pending','verified','rejected'),true)) $status='unverified'; update_post_meta($post_id,'_bsw_p7_verification_status',$status); update_post_meta($post_id,'_bsw_p7_verified',$status==='verified'?'1':'0'); }
        }
        if ($post->post_type==='bsw_knowledge_event' && isset($_POST['bsw_p7_event_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_p7_event_nonce'])),'bsw_p7_event')) {
            $date=isset($_POST['bsw_p7_event_date'])?sanitize_text_field(wp_unslash($_POST['bsw_p7_event_date'])):'';
            $end=isset($_POST['bsw_p7_event_end_date'])?sanitize_text_field(wp_unslash($_POST['bsw_p7_event_end_date'])):'';
            if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) $date='';
            if ($end && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)) $end='';
            update_post_meta($post_id,'_bsw_p7_event_date',$date);
            update_post_meta($post_id,'_bsw_p7_event_end_date',$end);
            $rid=isset($_POST['bsw_p7_event_related_id'])?absint($_POST['bsw_p7_event_related_id']):0;
            update_post_meta($post_id,'_bsw_p7_event_related_id',($rid && get_post_status($rid)==='publish')?$rid:0);
        }
    }

    public static function register_shortcodes() {
        add_shortcode('bigshor_knowledge_graph',array(__CLASS__,'shortcode_graph'));
        add_shortcode('bigshor_profiles',array(__CLASS__,'shortcode_profiles'));
        add_shortcode('bigshor_experts',array(__CLASS__,'shortcode_experts'));
        add_shortcode('bigshor_translations',array(__CLASS__,'shortcode_translations'));
        add_shortcode('bigshor_timeline',array(__CLASS__,'shortcode_timeline'));
    }

    public static function shortcode_graph($atts) {
        if (!self::enabled('knowledge_graph')) return '';
        $atts=shortcode_atts(array('id'=>0,'depth'=>1),$atts); $id=absint($atts['id']);
        if (!$id) $id=get_queried_object_id();
        if (!$id || get_post_status($id)!=='publish') return '<p>Knowledge item not found.</p>';
        $ids=(array)get_post_meta($id,'_bsw_p7_related_ids',true); $ids=array_slice(array_filter(array_map('absint',$ids)),0,30);
        ob_start(); echo '<section class="bsw-p7-graph"><div class="bsw-p7-node bsw-p7-root"><strong>'.esc_html(get_the_title($id)).'</strong><span>Current item</span></div>';
        if ($ids) { echo '<div class="bsw-p7-connections">'; foreach($ids as $rid) { if(get_post_status($rid)!=='publish')continue; echo '<article class="bsw-p7-node"><a href="'.esc_url(get_permalink($rid)).'">'.esc_html(get_the_title($rid)).'</a><span>'.esc_html(get_post_type_object(get_post_type($rid))->labels->singular_name ?? get_post_type($rid)).'</span></article>'; } echo '</div>'; }
        else echo '<p>No related knowledge connections have been added yet.</p>';
        echo '</section>'; return ob_get_clean();
    }

    private static function profile_listing($experts=false) {
        if (!self::enabled('profiles') || ($experts && !self::enabled('experts'))) return '';
        $meta=array(); if($experts) $meta[]=array('key'=>'_bsw_p7_expert','value'=>'1');
        $q=new WP_Query(array('post_type'=>'bsw_profile','post_status'=>'publish','posts_per_page'=>50,'orderby'=>'title','order'=>'ASC','meta_query'=>$meta));
        if(!$q->have_posts()) return '<p>No profiles have been published yet.</p>';
        ob_start(); echo '<div class="bsw-p7-grid">'; while($q->have_posts()){$q->the_post();$id=get_the_ID();$verified=(get_post_meta($id,'_bsw_p7_verification_status',true)==='verified' || get_post_meta($id,'_bsw_p7_verified',true)==='1');echo '<article class="bsw-p7-card">';if(has_post_thumbnail())echo get_the_post_thumbnail($id,'medium');echo '<h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a> '.($verified?'<span class="bsw-p7-verified">Editorially verified</span>':'').'</h3><p>'.esc_html(get_post_meta($id,'_bsw_p7_expertise',true)).'</p><small>'.esc_html(get_post_meta($id,'_bsw_p7_organisation',true)).'</small></article>'; } echo '</div>'; wp_reset_postdata(); return ob_get_clean();
    }
    public static function shortcode_profiles(){return self::profile_listing(false);}
    public static function shortcode_experts(){return self::enabled('experts')?self::profile_listing(true):'';}

    public static function shortcode_translations($atts) {
        if (!self::enabled('multilingual')) return '';
        $atts=shortcode_atts(array('id'=>0),$atts);$id=absint($atts['id']);if(!$id)$id=get_queried_object_id();
        $group=get_post_meta($id,'_bsw_p7_translation_group',true);if(!$group)return '<p>No translations have been linked to this item.</p>';
        $q=new WP_Query(array('post_type'=>'any','post_status'=>'publish','posts_per_page'=>30,'meta_key'=>'_bsw_p7_translation_group','meta_value'=>$group,'post__not_in'=>array($id)));
        if(!$q->have_posts())return '<p>No other language versions have been published yet.</p>';
        ob_start();echo '<ul class="bsw-p7-translations">';while($q->have_posts()){$q->the_post();$pid=get_the_ID();$lang=get_post_meta($pid,'_bsw_p7_language',true);echo '<li><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a>'.($lang?' <span>'.esc_html($lang).'</span>':'').'</li>'; }echo '</ul>';wp_reset_postdata();return ob_get_clean();
    }

    public static function shortcode_timeline($atts) {
        if (!self::enabled('timeline')) return '';
        $atts=shortcode_atts(array('limit'=>30),$atts);$limit=min(100,max(1,absint($atts['limit'])?:30));
        $q=new WP_Query(array('post_type'=>'bsw_knowledge_event','post_status'=>'publish','posts_per_page'=>$limit,'meta_key'=>'_bsw_p7_event_date','orderby'=>'meta_value','order'=>'ASC'));
        if(!$q->have_posts())return '<p>No timeline events have been published yet.</p>';
        ob_start();echo '<ol class="bsw-p7-timeline">';while($q->have_posts()){$q->the_post();$id=get_the_ID();$date=get_post_meta($id,'_bsw_p7_event_date',true);$related=absint(get_post_meta($id,'_bsw_p7_event_related_id',true));echo '<li><time datetime="'.esc_attr($date).'">'.esc_html($date).'</time><div><h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><p>'.esc_html(get_the_excerpt()).'</p>';if($related && get_post_status($related)==='publish')echo '<small>Related: <a href="'.esc_url(get_permalink($related)).'">'.esc_html(get_the_title($related)).'</a></small>';echo '</div></li>'; }echo '</ol>';wp_reset_postdata();return ob_get_clean();
    }

    public static function register_rest_routes() {
        register_rest_route('bigshor/v1', '/network/graph/(?P<id>\\d+)', array('methods'=>'GET','callback'=>array(__CLASS__,'rest_graph'),'permission_callback'=>'__return_true','args'=>array('id'=>array('validate_callback'=>function($v){return absint($v)>0;}))));
        register_rest_route('bigshor/v1', '/network/profiles', array('methods'=>'GET','callback'=>array(__CLASS__,'rest_profiles'),'permission_callback'=>'__return_true'));
        register_rest_route('bigshor/v1', '/network/experts', array('methods'=>'GET','callback'=>array(__CLASS__,'rest_experts'),'permission_callback'=>'__return_true'));
        register_rest_route('bigshor/v1', '/network/timeline', array('methods'=>'GET','callback'=>array(__CLASS__,'rest_timeline'),'permission_callback'=>'__return_true'));
    }
    private static function rest_items($query) {
        $items=array(); foreach($query->posts as $post) { $items[]=array('id'=>$post->ID,'type'=>$post->post_type,'title'=>get_the_title($post),'url'=>get_permalink($post),'excerpt'=>wp_strip_all_tags(get_the_excerpt($post))); }
        return rest_ensure_response(array('count'=>count($items),'items'=>$items));
    }
    public static function rest_graph($request) {
        if (!self::enabled('public_api') || !self::enabled('knowledge_graph')) return new WP_Error('bsw_network_disabled','Knowledge graph API is disabled.',array('status'=>404));
        $id=absint($request['id']); if(get_post_status($id)!=='publish') return new WP_Error('bsw_not_found','Published knowledge item not found.',array('status'=>404));
        $ids=(array)get_post_meta($id,'_bsw_p7_related_ids',true); $items=array(); foreach(array_slice(array_unique(array_map('absint',$ids)),0,50) as $rid) if(get_post_status($rid)==='publish') $items[]=array('id'=>$rid,'type'=>get_post_type($rid),'title'=>get_the_title($rid),'url'=>get_permalink($rid));
        return rest_ensure_response(array('root'=>array('id'=>$id,'type'=>get_post_type($id),'title'=>get_the_title($id),'url'=>get_permalink($id)),'connections'=>$items));
    }
    public static function rest_profiles($request) {
        if (!self::enabled('public_api') || !self::enabled('profiles')) return new WP_Error('bsw_network_disabled','Profiles API is disabled.',array('status'=>404));
        $limit=min(50,max(1,absint($request->get_param('per_page'))?:20)); $q=new WP_Query(array('post_type'=>'bsw_profile','post_status'=>'publish','posts_per_page'=>$limit,'orderby'=>'title','order'=>'ASC')); return self::rest_items($q);
    }
    public static function rest_experts($request) {
        if (!self::enabled('experts')) return new WP_Error('bsw_network_disabled','Expert network is disabled.',array('status'=>404));
        if (!self::enabled('public_api')) return new WP_Error('bsw_network_disabled','Public API is disabled.',array('status'=>404));
        $limit=min(50,max(1,absint($request->get_param('per_page'))?:20)); $q=new WP_Query(array('post_type'=>'bsw_profile','post_status'=>'publish','posts_per_page'=>$limit,'meta_query'=>array(array('key'=>'_bsw_p7_expert','value'=>'1')))); return self::rest_items($q);
    }
    public static function rest_timeline($request) {
        if (!self::enabled('public_api') || !self::enabled('timeline')) return new WP_Error('bsw_network_disabled','Timeline API is disabled.',array('status'=>404));
        $limit=min(100,max(1,absint($request->get_param('per_page'))?:30)); $q=new WP_Query(array('post_type'=>'bsw_knowledge_event','post_status'=>'publish','posts_per_page'=>$limit,'meta_key'=>'_bsw_p7_event_date','orderby'=>'meta_value','order'=>'ASC')); $items=array(); foreach($q->posts as $post) $items[]=array('id'=>$post->ID,'title'=>get_the_title($post),'url'=>get_permalink($post),'date'=>get_post_meta($post->ID,'_bsw_p7_event_date',true),'end_date'=>get_post_meta($post->ID,'_bsw_p7_event_end_date',true)); return rest_ensure_response(array('count'=>count($items),'items'=>$items));
    }
    public static function output_schema() {
        if (!self::enabled('structured_data') || !is_singular()) return;
        $id=get_queried_object_id(); if(!$id || get_post_status($id)!=='publish') return;
        $type=get_post_type($id); $schema=null;
        if($type==='bsw_profile') { $schema=array('@context'=>'https://schema.org','@type'=>'Person','name'=>get_the_title($id),'url'=>get_permalink($id),'description'=>wp_strip_all_tags(get_the_excerpt($id))); $org=get_post_meta($id,'_bsw_p7_organisation',true); if($org)$schema['affiliation']=array('@type'=>'Organization','name'=>$org); }
        elseif($type==='bsw_knowledge_event') { $date=get_post_meta($id,'_bsw_p7_event_date',true); $schema=array('@context'=>'https://schema.org','@type'=>'Event','name'=>get_the_title($id),'url'=>get_permalink($id),'description'=>wp_strip_all_tags(get_the_excerpt($id))); if($date)$schema['startDate']=$date; $end=get_post_meta($id,'_bsw_p7_event_end_date',true); if($end)$schema['endDate']=$end; }
        if($schema) echo "\n<script type=\"application/ld+json\">".wp_json_encode($schema,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."</script>\n";
    }

    public static function admin_menu(){add_submenu_page('options-general.php','BIGSHOR Global Network','BIGSHOR Global Network','manage_options','bsw-phase7',array(__CLASS__,'settings_page'));}
    public static function register_settings(){register_setting('bsw_phase7_group',self::OPTION,array('sanitize_callback'=>array(__CLASS__,'sanitize_settings')));}
    public static function sanitize_settings($input){$out=self::defaults();foreach(array_keys($out) as $key)$out[$key]=empty($input[$key])?0:1;return $out;}
    public static function settings_page(){
        if(!current_user_can('manage_options'))return;$s=self::settings();
        echo '<div class="wrap"><h1>BIGSHOR Global Knowledge Network — Phase 7</h1><p>Enable or disable modules. Disabling a module does not delete its content or metadata.</p><form method="post" action="options.php">';settings_fields('bsw_phase7_group');
        $labels=array('knowledge_graph'=>'Knowledge Graph & Connections','profiles'=>'People & Organisation Profiles','multilingual'=>'Multilingual Relationships','experts'=>'Expert Network','timeline'=>'Knowledge Timeline','public_api'=>'Read-only Public API','structured_data'=>'Structured Data (JSON-LD)');
        foreach($labels as $key=>$label)echo '<p><label><input type="checkbox" name="'.esc_attr(self::OPTION).'['.esc_attr($key).']" value="1" '.checked($s[$key],1,false).'> '.esc_html($label).'</label></p>';
        submit_button('Save Phase 7 settings');echo '</form><hr><h2>Available shortcodes</h2><ul><li><code>[bigshor_knowledge_graph]</code> or <code>[bigshor_knowledge_graph id="123"]</code></li><li><code>[bigshor_profiles]</code></li><li><code>[bigshor_experts]</code></li><li><code>[bigshor_translations]</code></li><li><code>[bigshor_timeline limit="30"]</code></li></ul><h2>Public API</h2><p><code>/wp-json/bigshor/v1/network/graph/POST_ID</code>, <code>/wp-json/bigshor/v1/network/profiles</code>, <code>/wp-json/bigshor/v1/network/experts</code>, <code>/wp-json/bigshor/v1/network/timeline</code></p><p>Profiles are editorially verified by administrators. Translation groups connect independently authored language versions; no automatic translation is performed.</p></div>';
    }
    public static function styles(){
        $css='.bsw-p7-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}.bsw-p7-card,.bsw-p7-node{border:1px solid #ddd;border-radius:10px;padding:16px;background:#fff}.bsw-p7-card img{width:100%;height:auto;border-radius:6px}.bsw-p7-verified{display:inline-block;font-size:11px;padding:3px 7px;border-radius:20px;background:#e8f4ec;color:#176b36}.bsw-p7-graph{display:grid;gap:16px}.bsw-p7-root{border-width:2px}.bsw-p7-node span{display:block;font-size:12px;opacity:.75;margin-top:5px}.bsw-p7-connections{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.bsw-p7-timeline{list-style:none;margin:0;padding:0 0 0 18px;border-left:2px solid #bbb}.bsw-p7-timeline li{position:relative;padding:0 0 22px 18px}.bsw-p7-timeline li:before{content:"";position:absolute;left:-25px;top:5px;width:10px;height:10px;border-radius:50%;background:currentColor}.bsw-p7-timeline time{font-weight:700}.bsw-p7-translations li{margin:6px 0}';
        wp_register_style('bsw-phase7-inline',false);wp_enqueue_style('bsw-phase7-inline');wp_add_inline_style('bsw-phase7-inline',$css);
    }
    public static function activation(){if(!get_option(self::OPTION))add_option(self::OPTION,self::defaults());self::register_types();flush_rewrite_rules();update_option('bsw_core_schema_version',self::VERSION,false);}
}
BSW_Phase7_Global_Network::init();
}
