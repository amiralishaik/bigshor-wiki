<?php
/**
 * BIGSHOR WIKI Tools Hub — curated external tools directory.
 * Integrated into BIGSHOR Wiki Core; no separate plugin or external API dependency.
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'bsw_register_tools_hub_menu', 30);
function bsw_register_tools_hub_menu() {
    add_submenu_page(
        'bsw-control-centre',
        'BIGSHOR Tools Hub',
        'Tools Hub',
        'manage_options',
        'bsw-tools-hub',
        'bsw_render_tools_hub'
    );
}

function bsw_tools_hub_catalog() {
    return array(
        array('Google Search Console','SEO & Performance','Free','Monitor indexing, search queries, clicks, impressions and technical search issues.','https://search.google.com/search-console/'),
        array('Google Analytics 4','SEO & Performance','Free','Understand audience behaviour, acquisition and content engagement.','https://analytics.google.com/'),
        array('PageSpeed Insights','SEO & Performance','Free','Measure page performance and Core Web Vitals on mobile and desktop.','https://pagespeed.web.dev/'),
        array('Google Trends','Research & Fact-checking','Free','Explore topic interest, regional demand and search trend patterns.','https://trends.google.com/'),
        array('Google Alerts','Research & Fact-checking','Free','Track mentions of people, organisations, topics and developing stories.','https://www.google.com/alerts'),
        array('Google Fact Check Explorer','Research & Fact-checking','Free','Discover fact-check articles related to claims and public statements.','https://toolbox.google.com/factcheck/explorer'),
        array('Wikidata Query Service','Research & Fact-checking','Free','Explore structured open knowledge and entity relationships with SPARQL.','https://query.wikidata.org/'),
        array('Internet Archive','Research & Fact-checking','Free','Consult archived web pages and historical versions of public websites.','https://web.archive.org/'),
        array('Grammarly','Writing & Editing','Freemium','Review grammar, spelling, clarity and tone in editorial drafts.','https://www.grammarly.com/'),
        array('LanguageTool','Writing & Editing','Freemium','Check spelling, grammar and style across multiple languages.','https://languagetool.org/'),
        array('Hemingway Editor','Writing & Editing','Freemium','Improve readability and identify overly complex sentences.','https://hemingwayapp.com/'),
        array('DeepL Translator','Writing & Editing','Freemium','Translate and compare language drafts; verify names and factual details manually.','https://www.deepl.com/translator'),
        array('Google Translate','Writing & Editing','Free','Translate working text across supported languages.','https://translate.google.com/'),
        array('Canva','Images & Media','Freemium','Create article graphics, social visuals and simple editorial layouts.','https://www.canva.com/'),
        array('Unsplash','Images & Media','Free','Find photography; review each image’s current licence and attribution requirements.','https://unsplash.com/'),
        array('Pexels','Images & Media','Free','Find stock photos and videos; check applicable licence terms before use.','https://www.pexels.com/'),
        array('Google Fonts','Images & Media','Free','Use open-source typefaces in the BIGSHOR WIKI interface and graphics.','https://fonts.google.com/'),
        array('TinyPNG','Images & Media','Freemium','Compress images to reduce page weight before upload.','https://tinypng.com/'),
        array('Cloudflare','Security & Reliability','Freemium','Review DNS, CDN, caching and available website security protections.','https://www.cloudflare.com/'),
        array('Mozilla Observatory','Security & Reliability','Free','Assess web security headers and related configuration signals.','https://observatory.mozilla.org/'),
        array('VirusTotal','Security & Reliability','Freemium','Check suspicious URLs and files using multiple security engines.','https://www.virustotal.com/'),
        array('W3C Markup Validator','Accessibility & Standards','Free','Validate HTML markup and identify structural errors.','https://validator.w3.org/'),
        array('WAVE','Accessibility & Standards','Free','Review accessibility issues and visual accessibility indicators.','https://wave.webaim.org/'),
        array('WebAIM Contrast Checker','Accessibility & Standards','Free','Check foreground and background colour contrast ratios.','https://webaim.org/resources/contrastchecker/'),
        array('GitHub','Development & Operations','Freemium','Manage source code, change history, issues and automated workflows.','https://github.com/'),
        array('Google Looker Studio','Analytics & Reporting','Free','Build shareable dashboards from supported analytics and reporting sources.','https://lookerstudio.google.com/')
    );
}

function bsw_render_tools_hub() {
    if (!current_user_can('manage_options')) return;
    $tools = bsw_tools_hub_catalog();
    $categories = array();
    foreach ($tools as $tool) $categories[$tool[1]] = true;
    ?>
    <div class="wrap bsw-tools-hub">
        <h1>BIGSHOR Tools Hub</h1>
        <p class="description">A curated directory of external tools for research, editorial production, site quality and platform operations. Availability and pricing can change; confirm terms on each provider’s website.</p>
        <div class="bsw-tools-controls">
            <label class="screen-reader-text" for="bsw-tools-search">Search tools</label>
            <input type="search" id="bsw-tools-search" placeholder="Search tools or purposes…" />
            <label class="screen-reader-text" for="bsw-tools-category">Filter by category</label>
            <select id="bsw-tools-category">
                <option value="">All categories</option>
                <?php foreach (array_keys($categories) as $category) : ?>
                    <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
                <?php endforeach; ?>
            </select>
            <label class="screen-reader-text" for="bsw-tools-access">Filter by access</label>
            <select id="bsw-tools-access">
                <option value="">Free and freemium</option>
                <option value="Free">Free</option>
                <option value="Freemium">Freemium</option>
            </select>
            <span id="bsw-tools-count" aria-live="polite"><?php echo esc_html(count($tools)); ?> tools</span>
        </div>
        <div class="bsw-tools-grid" id="bsw-tools-grid">
            <?php foreach ($tools as $tool) :
                $search_text = strtolower($tool[0] . ' ' . $tool[1] . ' ' . $tool[3]);
            ?>
                <article class="bsw-tool-card" data-category="<?php echo esc_attr($tool[1]); ?>" data-access="<?php echo esc_attr($tool[2]); ?>" data-search="<?php echo esc_attr($search_text); ?>">
                    <div class="bsw-tool-card-top">
                        <span class="bsw-tool-category"><?php echo esc_html($tool[1]); ?></span>
                        <span class="bsw-tool-access"><?php echo esc_html($tool[2]); ?></span>
                    </div>
                    <h2><?php echo esc_html($tool[0]); ?></h2>
                    <p><?php echo esc_html($tool[3]); ?></p>
                    <a href="<?php echo esc_url($tool[4]); ?>" target="_blank" rel="noopener noreferrer">Visit official website <span aria-hidden="true">↗</span></a>
                </article>
            <?php endforeach; ?>
        </div>
        <p id="bsw-tools-empty" hidden>No tools match these filters. Try a different search or category.</p>
        <p class="bsw-tools-footnote">BIGSHOR WIKI does not operate or endorse these third-party services. Check each provider’s privacy policy, terms, licensing and current pricing before sharing data or publishing assets.</p>
    </div>
    <style>
        .bsw-tools-hub{max-width:1440px}.bsw-tools-controls{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:22px 0}.bsw-tools-controls input[type=search]{min-width:260px;max-width:420px;flex:1}.bsw-tools-controls select,.bsw-tools-controls input{min-height:38px}.bsw-tools-controls #bsw-tools-count{color:#646970;margin-left:auto}.bsw-tools-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}.bsw-tool-card{background:#fff;border:1px solid #dcdcde;border-radius:9px;padding:18px;display:flex;flex-direction:column;min-height:205px}.bsw-tool-card-top{display:flex;justify-content:space-between;gap:8px;align-items:center}.bsw-tool-category{font-size:11px;color:#50575e}.bsw-tool-access{font-size:11px;background:#f0f0f1;border-radius:20px;padding:4px 9px;color:#3c434a}.bsw-tool-card h2{font-size:16px;margin:15px 0 7px}.bsw-tool-card p{font-size:13px;color:#50575e;line-height:1.55;margin:0 0 16px}.bsw-tool-card a{font-weight:600;text-decoration:none;margin-top:auto}.bsw-tool-card a:focus,.bsw-tool-card a:hover{text-decoration:underline}.bsw-tools-footnote{margin-top:22px;padding:12px 15px;background:#f6f7f7;border-left:4px solid #8c8f94;color:#50575e}.bsw-tools-grid[aria-busy=true]{opacity:.65}@media(max-width:600px){.bsw-tools-controls{align-items:stretch}.bsw-tools-controls input[type=search],.bsw-tools-controls select{width:100%;max-width:none}.bsw-tools-controls #bsw-tools-count{width:100%;margin-left:0}}
    </style>
    <script>
    (function(){
        var search=document.getElementById('bsw-tools-search');
        var category=document.getElementById('bsw-tools-category');
        var access=document.getElementById('bsw-tools-access');
        var cards=Array.prototype.slice.call(document.querySelectorAll('.bsw-tool-card'));
        var count=document.getElementById('bsw-tools-count');
        var empty=document.getElementById('bsw-tools-empty');
        function filterTools(){
            var q=(search.value||'').toLowerCase().trim(), c=category.value, a=access.value, visible=0;
            cards.forEach(function(card){
                var match=(!q||card.getAttribute('data-search').indexOf(q)!==-1)&&(!c||card.getAttribute('data-category')===c)&&(!a||card.getAttribute('data-access')===a);
                card.hidden=!match;
                if(match) visible++;
            });
            count.textContent=visible+' of '+cards.length+' tools';
            empty.hidden=visible!==0;
        }
        search.addEventListener('input',filterTools);
        category.addEventListener('change',filterTools);
        access.addEventListener('change',filterTools);
    }());
    </script>
    <?php
}
