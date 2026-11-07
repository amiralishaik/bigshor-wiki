<?php
/**
 * BIGSHOR Phase 2 template/parser expansion layer (module 54).
 * Supports balanced nested template calls, named/positional parameters,
 * reusable administrator-managed wiki templates, and a safe parser-function subset.
 * It never evaluates PHP, arbitrary code, or user-supplied expressions via eval().
 */
if (!defined('ABSPATH')) exit;
final class BSW_Phase54_Template_Engine {
    const POST_TYPE = 'wiki_template';
    const MAX_DEPTH = 10;
    const MAX_EXPANSIONS = 180;
    const MAX_SOURCE_BYTES = 250000;

    public static function boot() {
        add_action('init', array(__CLASS__, 'register_template_type'), 24);
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
    }

    public static function register_template_type() {
        if (post_type_exists(self::POST_TYPE)) return;
        register_post_type(self::POST_TYPE, array(
            'labels' => array('name'=>'Wiki Templates','singular_name'=>'Wiki Template','add_new_item'=>'Add Wiki Template','edit_item'=>'Edit Wiki Template'),
            'public'=>false, 'show_ui'=>true, 'show_in_menu'=>'edit.php?post_type=wiki_article',
            'show_in_rest'=>false, 'exclude_from_search'=>true, 'publicly_queryable'=>false,
            'supports'=>array('title','editor','revisions'), 'capability_type'=>'post', 'map_meta_cap'=>true,
            'capabilities'=>array(
                'edit_post'=>'manage_options','read_post'=>'manage_options','delete_post'=>'manage_options',
                'edit_posts'=>'manage_options','edit_others_posts'=>'manage_options','publish_posts'=>'manage_options',
                'read_private_posts'=>'manage_options','delete_posts'=>'manage_options','delete_private_posts'=>'manage_options',
                'delete_published_posts'=>'manage_options','delete_others_posts'=>'manage_options',
                'edit_private_posts'=>'manage_options','edit_published_posts'=>'manage_options'
            )
        ));
    }

    public static function admin_notice() {
        if (!current_user_can('manage_options') || !isset($_GET['post_type']) || sanitize_key(wp_unslash($_GET['post_type'])) !== self::POST_TYPE) return;
        echo '<div class="notice notice-info"><p><strong>BIGSHOR Wiki Templates:</strong> Use <code>{{{parameter|default}}}</code> for parameters. Templates are expanded as wikitext and are restricted to administrators. Parser expansion is bounded to prevent recursion and resource exhaustion.</p></div>';
    }

    public static function expand($source, $depth=0, &$budget=null, $parameters=array(), $resolve_parameters=false) {
        if ($budget === null) $budget = self::MAX_EXPANSIONS;
        $source = (string) $source;
        if (strlen($source) > self::MAX_SOURCE_BYTES || $depth > self::MAX_DEPTH || $budget <= 0) return $source;
        if ($resolve_parameters) $source = self::expand_parameters($source, $parameters);
        $out = ''; $length = strlen($source); $i = 0;
        while ($i < $length) {
            if ($i + 2 < $length && substr($source, $i, 3) === '{{{') {
                $end = strpos($source, '}}}', $i + 3);
                if ($end !== false) { $out .= substr($source, $i, $end + 3 - $i); $i = $end + 3; continue; }
            }
            if ($i + 1 < $length && substr($source, $i, 2) === '{{') {
                $end = self::balanced_end($source, $i);
                if ($end !== null) {
                    $budget--;
                    $inside = substr($source, $i + 2, $end - $i - 4);
                    $expanded = self::expand_expression($inside, $depth + 1, $budget, $parameters);
                    $out .= $expanded; $i = $end; continue;
                }
            }
            $out .= $source[$i]; $i++;
        }
        return $out;
    }

    private static function balanced_end($source, $start) {
        $level = 0; $len = strlen($source);
        for ($i=$start; $i<$len-1; $i++) {
            if ($i+2<$len && substr($source,$i,3)==='{{{') { $e=strpos($source,'}}}',$i+3); if($e===false)return null; $i=$e+2; continue; }
            if (substr($source,$i,2)==='{{') { $level++; $i++; continue; }
            if (substr($source,$i,2)==='}}') { $level--; $i++; if ($level===0) return $i+1; }
        }
        return null;
    }

    private static function split_top_level($raw, $delimiter='|') {
        $parts=array(); $buffer=''; $double=0; $triple=0; $len=strlen($raw);
        for($i=0;$i<$len;$i++) {
            if($i+2<$len && substr($raw,$i,3)==='{{{'){$triple++;$buffer.='{{{';$i+=2;continue;}
            if($i+2<$len && substr($raw,$i,3)==='}}}' && $triple>0){$triple--;$buffer.='}}}';$i+=2;continue;}
            if($i+1<$len && substr($raw,$i,2)==='{{'){$double++;$buffer.='{{';$i++;continue;}
            if($i+1<$len && substr($raw,$i,2)==='}}' && $double>0){$double--;$buffer.='}}';$i++;continue;}
            if($raw[$i]===$delimiter && $double===0 && $triple===0){$parts[]=$buffer;$buffer='';continue;}
            $buffer.=$raw[$i];
        }
        $parts[]=$buffer; return $parts;
    }

    private static function expand_parameters($source, $parameters) {
        if (!is_array($parameters)) return $source;
        return preg_replace_callback('/\{\{\{\s*([a-zA-Z0-9 _-]+)\s*(?:\|((?:(?!\}\}\}).)*))?\}\}\}/s', function($m) use ($parameters) {
            $key = sanitize_key(trim($m[1]));
            if (array_key_exists($key,$parameters)) return (string)$parameters[$key];
            if (isset($m[2])) return $m[2];
            return '';
        }, $source);
    }

    private static function expand_expression($inside, $depth, &$budget, $parameters) {
        $parts=self::split_top_level($inside); $name=trim(array_shift($parts));
        $args=array(); $position=1;
        foreach($parts as $part) {
            $pair=self::split_first_equals($part);
            if($pair!==null && preg_match('/^[a-zA-Z0-9 _-]+$/',trim($pair[0]))) $args[sanitize_key(trim($pair[0]))]=self::expand($pair[1],$depth,$budget,$parameters);
            else $args[(string)$position++]=self::expand(trim($part),$depth,$budget,$parameters);
        }
        $lower=strtolower($name); $expandedName=self::expand($name,$depth,$budget,$parameters);
        if (strpos($lower,'#if:')===0) { $test=trim(substr($expandedName,4)); return $test!=='' ? ($args['1']??'') : ($args['2']??''); }
        if (strpos($lower,'#ifeq:')===0) { $test=trim(substr($expandedName,6)); return (string)$test===(string)($args['1']??'') ? ($args['2']??'') : ($args['3']??''); }
        if (strpos($lower,'#ifexist:')===0) { $target=trim(substr($expandedName,9)); $exists=self::article_exists($target); return $exists?($args['1']??''):($args['2']??''); }
        if (strpos($lower,'#switch:')===0) {
            $test=trim(substr($expandedName,8)); $default=$args['default']??'';
            foreach($args as $key=>$value){if($key==='default')continue;if((string)$key===(string)$test)return $value;}
            return $default;
        }
        if (strpos($lower,'#expr:')===0) { $value=self::safe_expression(trim(substr($expandedName,6))); return $value===null?'':(string)$value; }
        if (strpos($lower,'#len:')===0) return (string)self::unicode_length(trim(substr($expandedName,5)));
        if (strpos($lower,'#uc:')===0) return esc_html(strtoupper(trim(substr($expandedName,4))));
        if (strpos($lower,'#lc:')===0) return esc_html(strtolower(trim(substr($expandedName,4))));
        if (strpos($lower,'#sub:')===0) { $text=trim(substr($expandedName,5));$start=(int)($args['1']??0);$len=isset($args['2'])?(int)$args['2']:null;return esc_html($len===null?substr($text,$start):substr($text,$start,$len)); }
        if (strpos($lower,'#replace:')===0) { $text=trim(substr($expandedName,9));return isset($args['2'])?str_replace((string)($args['1']??''),(string)$args['2'],$text):$text; }
        if ($name==='!') return '|'; if ($name==='=') return '=';
        $magic=self::magic_word($name); if($magic!==null)return $magic;
        $template=self::get_template($name);
        if ($template !== null) return self::expand($template,$depth,$budget,$args,true);
        $slug=sanitize_key(str_replace(' ','_',strtolower($name)));
        if (class_exists('BSW_Phase5_Template_Registry')) {
            $fields=$args; unset($fields['1'],$fields['2'],$fields['3'],$fields['default']);
            $html=BSW_Phase5_Template_Registry::render($slug,$fields); if($html!=='')return $html;
        }
        return '{{'.$inside.'}}';
    }

    private static function split_first_equals($part) { $pieces=self::split_top_level($part,'='); if(count($pieces)<2)return null; $first=array_shift($pieces);return array($first,implode('=',$pieces)); }
    private static function get_template($name) {
        $name=trim(preg_replace('/^(Template|Шаблон):/i','',$name)); if($name==='' || !post_type_exists(self::POST_TYPE))return null;
        $slug=sanitize_title($name); $post=get_page_by_path($slug,OBJECT,self::POST_TYPE);
        if(!$post || $post->post_status!=='publish')return null;
        return (string)$post->post_content;
    }
    private static function article_exists($title) {
        $title=trim($title); if($title==='')return false;
        $post=get_page_by_path(sanitize_title($title),OBJECT,'wiki_article');
        if($post && $post->post_status==='publish')return true;
        $q=new WP_Query(array('post_type'=>'wiki_article','post_status'=>'publish','title'=>$title,'posts_per_page'=>1,'fields'=>'ids','no_found_rows'=>true));
        return !empty($q->posts);
    }
    private static function magic_word($name) {
        $key=strtoupper(trim($name));
        $allowed=array('SITENAME','CURRENTYEAR','CURRENTMONTH','CURRENTDAY','CURRENTMONTHNAME','PAGENAME','FULLPAGENAME');
        if(!in_array($key,$allowed,true)) return null;
        $post=($key==='PAGENAME'||$key==='FULLPAGENAME')?get_post():null;
        $map=array('SITENAME'=>get_bloginfo('name'),'CURRENTYEAR'=>current_time('Y'),'CURRENTMONTH'=>current_time('m'),'CURRENTDAY'=>current_time('d'),'CURRENTMONTHNAME'=>date_i18n('F'),'PAGENAME'=>$post?get_the_title($post):'','FULLPAGENAME'=>$post?get_the_title($post):'');
        return esc_html((string)$map[$key]);
    }
    private static function unicode_length($value) { return function_exists('mb_strlen')?mb_strlen($value,'UTF-8'):strlen($value); }

    /** Small arithmetic parser; supports numeric literals, parentheses and + - * / % ^. */
    private static function safe_expression($expression) {
        if(strlen($expression)>160 || !preg_match('/^[0-9eE+\-*\/%().\s^]+$/',$expression))return null;
        $tokens=array(); if(!preg_match_all('/(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?|[()+\-*\/%^]/',$expression,$m))return null; $tokens=$m[0];
        if(implode('', $tokens)!==preg_replace('/\s+/','',$expression))return null;
        $index=0; $parseExpr=null; $parsePrimary=null;
        $parsePrimary=function() use (&$tokens,&$index,&$parseExpr,&$parsePrimary) {
            if(!isset($tokens[$index]))return null; $t=$tokens[$index++];
            if($t==='+' || $t==='-'){ $v=$parsePrimary();return $v===null?null:($t==='-'?-$v:$v); }
            if($t==='('){$v=$parseExpr(0);if(($tokens[$index]??null)!==')')return null;$index++;return $v;}
            return is_numeric($t)?(float)$t:null;
        };
        $precedence=array('+' => 1,'-'=>1,'*'=>2,'/'=>2,'%'=>2,'^'=>3);
        $parseExpr=function($min) use (&$tokens,&$index,&$parseExpr,&$parsePrimary,$precedence) {
            $left=$parsePrimary();if($left===null)return null;
            while(isset($tokens[$index]) && isset($precedence[$tokens[$index]]) && $precedence[$tokens[$index]] >= $min){$op=$tokens[$index++];$p=$precedence[$op];$right=$parseExpr($p+($op==='^'?0:1));if($right===null)return null;
                if($op==='+')$left+=$right;elseif($op==='-')$left-=$right;elseif($op==='*')$left*=$right;elseif($op==='/'){if($right==0)return null;$left/=$right;}elseif($op==='%'){if($right==0)return null;$left=fmod($left,$right);}else $left=pow($left,$right);
                if(!is_finite($left) || abs($left)>1.0e15)return null;
            } return $left;
        };
        $result=$parseExpr(0); if($result===null || $index!==count($tokens))return null; return floor($result)===$result?(string)(int)$result:rtrim(rtrim(sprintf('%.10F',$result),'0'),'.');
    }
}
BSW_Phase54_Template_Engine::boot();
