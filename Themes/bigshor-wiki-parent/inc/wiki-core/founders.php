<?php
if (!defined('ABSPATH')) exit;

/*
 * Curated founder starter collection. This is a non-ranked selection, not a definitive global ranking.
 * Each record carries a primary-source starting point. Editors must review and expand each biography
 * against the cited source and independent reliable reporting before marking it fully reviewed.
 */
function bsw_founder_seed_data(){
 return array(
 array('Steve Jobs','Apple; Pixar','American entrepreneur and product executive who co-founded Apple Computer with Steve Wozniak and Ronald Wayne in 1976. He later led the development of influential Apple products and helped build Pixar into a major animation studio.','1976','https://www.apple.com/stevejobs/'),
 array('Steve Wozniak','Apple','American engineer and entrepreneur who co-founded Apple Computer with Steve Jobs and Ronald Wayne. He designed the Apple I and was the principal designer of the Apple II.','1976','https://www.woz.org/'),
 array('Bill Gates','Microsoft','American businessperson and philanthropist who co-founded Microsoft with Paul Allen in 1975. Microsoft became a major developer of personal-computer software.','1975','https://news.microsoft.com/exec/bill-gates/'),
 array('Paul Allen','Microsoft; Vulcan','American entrepreneur, investor and philanthropist who co-founded Microsoft with Bill Gates. He later founded Vulcan Inc. and supported scientific, cultural and civic projects.','1975','https://www.paulallen.com/'),
 array('Jeff Bezos','Amazon; Blue Origin','American entrepreneur who founded Amazon in 1994 as an online bookseller. He later founded aerospace company Blue Origin.','1994','https://ir.aboutamazon.com/officers-and-directors/default.aspx'),
 array('Larry Page','Google; Alphabet','American computer scientist and entrepreneur who co-founded Google with Sergey Brin. Their early search engine project began at Stanford University.','1998','https://about.google/company-info/our-story/'),
 array('Sergey Brin','Google; Alphabet','Computer scientist and entrepreneur who co-founded Google with Larry Page after the pair developed a research search engine at Stanford University.','1998','https://about.google/company-info/our-story/'),
 array('Mark Zuckerberg','Facebook; Meta','American technology entrepreneur who co-founded Facebook with fellow Harvard students in 2004. Facebook later became part of Meta Platforms.','2004','https://about.fb.com/company-info/'),
 array('Michael Dell','Dell Technologies','American entrepreneur who founded PC’s Limited, later Dell Computer Corporation, while studying at the University of Texas at Austin.','1984','https://www.dell.com/en-us/dt/corporate/about-us/who-we-are.htm'),
 array('Jensen Huang','NVIDIA','Taiwanese-American engineer and entrepreneur who co-founded NVIDIA with Chris Malachowsky and Curtis Priem in 1993.','1993','https://nvidianews.nvidia.com/bios/jensen-huang'),
 array('Jack Ma','Alibaba Group','Chinese entrepreneur and educator who led the group of 18 people that established Alibaba in Hangzhou in 1999.','1999','https://home.alibabagroup.com/en-US/about-alibaba'),
 array('Pony Ma','Tencent','Chinese entrepreneur who co-founded Tencent in 1998 with four other partners. Tencent developed internet and digital services including QQ and WeChat.','1998','https://www.tencent.com/en-us/about.html'),
 array('Zhang Yiming','ByteDance','Chinese technology entrepreneur who founded ByteDance in 2012. The company developed products including Toutiao and TikTok.','2012','https://www.bytedance.com/en/'),
 array('Lei Jun','Xiaomi','Chinese entrepreneur and investor who founded Xiaomi in 2010 with a group of partners. Xiaomi develops consumer electronics and smart devices.','2010','https://www.mi.com/global/about/'),
 array('Masayoshi Son','SoftBank Group','Japanese entrepreneur and investor who founded SoftBank in 1981, initially as a software distribution business.','1981','https://group.softbank/en/about/history'),
 array('Soichiro Honda','Honda Motor Co.','Japanese engineer and industrialist who co-founded Honda Motor Co. with Takeo Fujisawa. Honda grew from a motorised-bicycle business into a global mobility manufacturer.','1948','https://global.honda/en/about/history-digest/'),
 array('Akio Morita','Sony','Japanese entrepreneur who co-founded Tokyo Tsushin Kogyo, later Sony Group, with Masaru Ibuka in 1946.','1946','https://www.sony.com/en/SonyInfo/CorporateInfo/History/'),
 array('Ingvar Kamprad','IKEA','Swedish entrepreneur who founded IKEA in 1943. The business began as a mail-order company and later became known internationally for furniture and home furnishings.','1943','https://www.ikea.com/global/en/our-business/our-heritage/'),
 array('Richard Branson','Virgin Group','British entrepreneur who founded the Virgin business group. His early venture was a student magazine, followed by a mail-order record business and record shops.','1970','https://www.virgin.com/branson-family/richard-branson'),
 array('James Dyson','Dyson','British inventor and entrepreneur who founded Dyson Ltd. after developing a bagless vacuum cleaner based on cyclonic separation.','1991','https://www.dyson.com/inside-dyson'),
 array('Amancio Ortega','Inditex; Zara','Spanish entrepreneur who co-founded the clothing company that became Inditex and launched the first Zara store with Rosalía Mera in 1975.','1975','https://www.inditex.com/itxcomweb/en/group/history'),
 array('Coco Chanel','Chanel','French fashion designer and entrepreneur who established the Chanel fashion house. Her designs became influential in twentieth-century fashion.','1910','https://www.chanel.com/us/about-chanel/'),
 array('Estée Lauder','Estée Lauder Companies','American businesswoman who co-founded the Estée Lauder cosmetics company with her husband Joseph Lauder. The company began operations in 1946.','1946','https://www.elcompanies.com/en/who-we-are/our-history'),
 array('Walt Disney','The Walt Disney Company','American animator and entrepreneur who co-founded the Disney Brothers Cartoon Studio with Roy O. Disney in 1923.','1923','https://thewaltdisneycompany.com/about/'),
 array('Henry Ford','Ford Motor Company','American industrialist who founded Ford Motor Company in 1903. The company helped popularise large-scale automobile assembly-line production.','1903','https://corporate.ford.com/about/history.html'),
 array('Andrew Carnegie','Carnegie Steel; philanthropy','Scottish-American industrialist who built Carnegie Steel into a major steel producer and later became a prominent philanthropist.','1892','https://www.carnegie.org/about-us/founder/'),
 array('Madam C. J. Walker','Madam C. J. Walker Manufacturing Company','American entrepreneur and philanthropist who built a hair-care business serving Black women and became one of the best-known self-made businesswomen of her era.','1906','https://www.nps.gov/people/madam-c-j-walker.htm'),
 array('Oprah Winfrey','Harpo Productions; OWN','American media executive and philanthropist who founded Harpo Productions and built a major television and media business around her work as a broadcaster.','1986','https://www.oprah.com/app/own-tv.html'),
 array('Sara Blakely','Spanx','American entrepreneur who founded Spanx, an apparel company known for shapewear and related clothing products.','2000','https://spanx.com/pages/about-us'),
 array('Reed Hastings','Netflix','American entrepreneur who co-founded Netflix with Marc Randolph in 1997. The company began as a DVD rental service and later became a global streaming business.','1997','https://about.netflix.com/en'),
 array('Brian Chesky','Airbnb','American designer and entrepreneur who co-founded Airbnb with Joe Gebbia and Nathan Blecharczyk in 2008.','2008','https://news.airbnb.com/about-us/'),
 array('Joe Gebbia','Airbnb','American designer and entrepreneur who co-founded Airbnb with Brian Chesky and Nathan Blecharczyk.','2008','https://news.airbnb.com/about-us/'),
 array('Nathan Blecharczyk','Airbnb','American technology entrepreneur who co-founded Airbnb with Brian Chesky and Joe Gebbia and helped build its technology platform.','2008','https://news.airbnb.com/about-us/'),
 array('Melanie Perkins','Canva','Australian technology entrepreneur who co-founded Canva with Cliff Obrecht and Cameron Adams. Canva launched as an online design platform in 2013.','2013','https://www.canva.com/about/'),
 array('Daniel Ek','Spotify','Swedish entrepreneur who co-founded Spotify with Martin Lorentzon. The music streaming service launched publicly in 2008.','2006','https://newsroom.spotify.com/company-info/'),
 array('Tobias Lütke','Shopify','German-Canadian entrepreneur who co-founded Shopify with Daniel Weinand and Scott Lake after building software for an online snowboard store.','2006','https://www.shopify.com/about'),
 array('Pierre Omidyar','eBay','French-born Iranian-American entrepreneur who founded the online marketplace AuctionWeb, which became eBay, in 1995.','1995','https://www.ebayinc.com/company/'),
 array('Marc Benioff','Salesforce','American entrepreneur who co-founded Salesforce in 1999 with Parker Harris, Dave Moellenhoff and Frank Dominguez.','1999','https://www.salesforce.com/company/about-us/'),
 array('Larry Ellison','Oracle','American entrepreneur who co-founded Software Development Laboratories, later Oracle, with Bob Miner and Ed Oates in 1977.','1977','https://www.oracle.com/corporate/'),
 array('Michael Bloomberg','Bloomberg LP','American entrepreneur who founded the financial data and media company Bloomberg LP in 1981 after working in financial services.','1981','https://www.bloomberg.com/company/'),
 array('Phil Knight','Nike','American entrepreneur who co-founded Blue Ribbon Sports with Bill Bowerman; the business later became Nike.','1964','https://about.nike.com/en/company'),
 array('Kiran Mazumdar-Shaw','Biocon','Indian entrepreneur who founded Biocon in 1978 as an industrial enzymes company; it later expanded into biopharmaceuticals.','1978','https://www.biocon.com/about-us/our-story/'),
 array('Dhirubhai Ambani','Reliance Industries','Indian entrepreneur who founded the business that became Reliance Industries. The company traces its origins to his yarn-trading venture in 1957.','1957','https://www.ril.com/about/our-history'),
 array('N. R. Narayana Murthy','Infosys','Indian software entrepreneur who co-founded Infosys in 1981 with six other engineers.','1981','https://www.infosys.com/about/history.html'),
 array('Falguni Nayar','Nykaa','Indian entrepreneur who founded Nykaa, an omnichannel beauty and lifestyle retailer, in 2012.','2012','https://www.nykaa.com/'),
 array('Vijay Shekhar Sharma','One97 Communications; Paytm','Indian entrepreneur who founded One97 Communications, the company behind the Paytm digital payments and commerce brand.','2000','https://paytm.com/about-us/'),
 array('Bhavish Aggarwal','Ola; Ola Electric','Indian entrepreneur who co-founded Ola Cabs with Ankit Bhati in 2010 and later founded Ola Electric.','2010','https://www.olacabs.com/about'),
 array('Ritesh Agarwal','OYO','Indian entrepreneur who founded OYO, a hospitality company that began by aggregating and standardising budget hotel rooms.','2013','https://www.oyorooms.com/'),
 array('Gautam Adani','Adani Group','Indian industrialist who founded the Adani Group in 1988, beginning with commodity trading and expanding into infrastructure and energy businesses.','1988','https://www.adani.com/about-us'),
 array('Shiv Nadar','HCL','Indian entrepreneur who founded HCL in 1976 with a group of colleagues, initially focusing on microprocessors and calculators.','1976','https://www.hcltech.com/about-us')
 );
}
function bsw_import_founder_profiles(){
 $term=term_exists('Founders','wiki_topic');if(!$term)$term=wp_insert_term('Founders','wiki_topic');$term_id=is_array($term)?(int)$term['term_id']:(int)$term;
 foreach(bsw_founder_seed_data() as $row){list($name,$company,$bio,$year,$source)=$row;$slug=sanitize_title($name);$existing=get_page_by_path($slug,OBJECT,'wiki_article');
  $body='<p>'.esc_html($bio).'</p><div class="wiki-infobox"><div class="wiki-infobox-title">Founder profile</div><div class="wiki-infobox-body"><p><strong>Known for:</strong> '.esc_html($company).'</p><p><strong>Business founding milestone:</strong> '.esc_html($year).'</p></div></div><h2>Overview</h2><p>'.esc_html($name).' is included in this BIGSHOR Wiki collection as a notable business founder associated with '.esc_html($company).'. The collection is an editorial selection, not a ranking of wealth, influence or business performance.</p><h2>Sources and further reading</h2><ol><li><a href="'.esc_url($source).'" rel="nofollow noopener" target="_blank">Primary company or institutional source</a> — starting point for verifying the founder and company history.</li></ol><p><em>Editorial status: starter profile. Editors should corroborate key dates, co-founder roles and biographical claims with independent reliable sources before marking this entry as fully reviewed. Readers can use the “Suggest an edit” link to report corrections.</em></p>';
  $args=array('post_type'=>'wiki_article','post_status'=>'publish','post_title'=>$name,'post_name'=>$slug,'post_content'=>$body,'post_excerpt'=>$bio,'post_author'=>get_current_user_id()?:1);
  if($existing){continue;}else $id=wp_insert_post($args,true);
  if(!is_wp_error($id)){update_post_meta($id,'_bsw_founder_profile','1');update_post_meta($id,'_bsw_founder_company',$company);update_post_meta($id,'_bsw_founder_year',$year);update_post_meta($id,'_bsw_founder_source',$source);update_post_meta($id,'_bsw_review_status','needs_sources');if($term_id)wp_set_object_terms($id,array($term_id),'wiki_topic',false);}
 }
}
add_action('admin_post_bsw_import_founders',function(){if(!current_user_can('manage_options')||!check_admin_referer('bsw_import_founders'))wp_die('Not authorised.');bsw_import_founder_profiles();wp_safe_redirect(admin_url('admin.php?page=bigshor-founders&imported=1'));exit;});
add_shortcode('bigshor_founders_directory',function(){
 $s=bsw_get_settings();$title=$s['founder_collection_title']?:'50 Notable Founders';$q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','posts_per_page'=>100,'meta_key'=>'_bsw_founder_profile','meta_value'=>'1','orderby'=>'title','order'=>'ASC'));
 $out='<section class="bsw-founders"><h2>'.esc_html($title).'</h2><p>A curated, non-ranked selection of business founders from different countries and industries. Each entry includes a source starting point and an editorial review status.</p>';
 if(!$q->have_posts())return $out.'<p>Founder profiles have not yet been imported. An administrator can import them from BIGSHOR Wiki → Founder Collection.</p></section>';
 $out.='<div class="bsw-founder-grid">';while($q->have_posts()){$q->the_post();$company=get_post_meta(get_the_ID(),'_bsw_founder_company',true);$out.='<article class="bsw-founder-card"><h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><p>'.esc_html($company).'</p><a class="bsw-founder-read" href="'.esc_url(get_permalink()).'">Read profile →</a></article>';}$out.='</div>';wp_reset_postdata();return $out.'</section>';
});
add_action('add_meta_boxes',function(){add_meta_box('bsw_founder_review','Founder profile verification','bsw_founder_review_box','wiki_article','side','high');});
function bsw_founder_review_box($post){if(get_post_meta($post->ID,'_bsw_founder_profile',true)!=='1')return;$status=get_post_meta($post->ID,'_bsw_review_status',true)?:'needs_sources';$source=get_post_meta($post->ID,'_bsw_founder_source',true);wp_nonce_field('bsw_founder_review','bsw_founder_nonce');echo '<p><strong>Collection entry</strong></p><p>Review status: '.esc_html($status).'</p><p><a href="'.esc_url($source).'" target="_blank" rel="noopener">Open source link</a></p><label><input type="checkbox" name="bsw_founder_verified" value="1"> Mark as editorially reviewed</label><p class="description">Only check after corroborating facts against reliable sources.</p>';}
add_action('save_post_wiki_article',function($id){if(!isset($_POST['bsw_founder_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_founder_nonce'])),'bsw_founder_review')||!current_user_can('edit_post',$id)||get_post_meta($id,'_bsw_founder_profile',true)!=='1')return;if(!empty($_POST['bsw_founder_verified']))update_post_meta($id,'_bsw_review_status','reviewed');},30);



