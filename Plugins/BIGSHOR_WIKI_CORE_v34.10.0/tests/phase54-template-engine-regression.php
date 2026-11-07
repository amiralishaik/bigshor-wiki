<?php
define('ABSPATH', __DIR__);
define('OBJECT', 'OBJECT');
function add_action() {}
function post_type_exists($type) { return $type === 'wiki_template'; }
function register_post_type() {}
function current_user_can() { return true; }
function sanitize_key($v) { return strtolower(preg_replace('/[^a-z0-9_-]/','', (string)$v)); }
function sanitize_title($v) { return strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',(string)$v),'-')); }
function wp_unslash($v) { return $v; }
function esc_html($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function get_page_by_path($slug,$output,$type) {
    $templates=array(
        'greeting'=>'Hello {{{name|friend}}}! {{{role|No role}}}',
        'wrapper'=>'{{Greeting|name={{{name|Guest}}}|role={{{role|Contributor}}}}}',
        'positional'=>'{{Greeting|name={{{1|Visitor}}}|role={{{2|member}}}}}',
        'loop'=>'{{Loop}}'
    );
    if($type!=='wiki_template' || !isset($templates[$slug])) return null;
    return (object)array('post_status'=>'publish','post_content'=>$templates[$slug]);
}
require dirname(__DIR__).'/includes/phase54-template-engine.php';
$tests=array(
    'nested parser functions'=>array('{{#if:yes|{{#ifeq:a|a|PASS|FAIL}}|FAIL}}','PASS'),
    'safe arithmetic'=>array('{{#expr: (2 + 3) * 4 / 2 }}','10'),
    'length function'=>array('{{#len:BIGSHOR}}','7'),
    'upper function'=>array('{{#uc:wiki}}','WIKI'),
    'nested named templates'=>array('{{Wrapper|name=Amir|role=Editor}}','Hello Amir! Editor'),
    'default parameters'=>array('{{Greeting}}','Hello friend! No role'),
    'positional arguments'=>array('{{Positional|Sam|Editor}}','Hello Sam! Editor'),
    'substring function'=>array('{{#sub:encyclopedia|0|4}}','ency'),
    'replace function'=>array('{{#replace:BIGSHOR Wiki|Wiki|WIKI}}','BIGSHOR WIKI'),
    'switch function'=>array('{{#switch:blue|red=warm|blue=cool|default=unknown}}','cool'),
    'invalid expression rejected'=>array('{{#expr:system(1)}}','')
);
$failed=0;
foreach($tests as $label=>$case){$budget=180;$actual=BSW_Phase54_Template_Engine::expand($case[0],0,$budget);$ok=$actual===$case[1];echo ($ok?'PASS':'FAIL').' '.$label.' expected='.json_encode($case[1]).' actual='.json_encode($actual)."\n";if(!$ok)$failed++;}
$budget=180; $loop=BSW_Phase54_Template_Engine::expand('{{Loop}}',0,$budget); $loop_ok=(strlen($loop)<100 && strpos($loop,'{{Loop}}')!==false); echo ($loop_ok?'PASS':'FAIL')." recursive template guard\n"; if(!$loop_ok)$failed++;
exit($failed?1:0);
