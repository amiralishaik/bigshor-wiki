<?php
if (!defined('ABSPATH')) exit;
add_action('add_meta_boxes',function(){add_meta_box('bsw_media_meta','Media provenance and licence','bsw_media_meta_box','wiki_media','normal','high');});
function bsw_media_meta_box($post){
 wp_nonce_field('bsw_media_meta_save','bsw_media_meta_nonce');
 foreach(array('source'=>'Original source URL','creator'=>'Creator / rights holder','licence'=>'Licence (e.g. CC BY 4.0)','credit'=>'Required attribution / credit','date'=>'Creation date') as $key=>$label){
  $v=get_post_meta($post->ID,'_bsw_media_'.$key,true);
  echo '<p><label><strong>'.esc_html($label).'</strong><br><input style="width:100%" name="bsw_media_'.$key.'" value="'.esc_attr($v).'"></label></p>';
 }
 echo '<p class="description">Only upload media you own or are authorised to publish. Record the source and licence accurately.</p>';
}
add_action('save_post_wiki_media',function($id){
 if(!isset($_POST['bsw_media_meta_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_media_meta_nonce'])),'bsw_media_meta_save'))return;
 if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;if(!current_user_can('edit_post',$id))return;
 foreach(array('source','creator','licence','credit','date') as $key){
  $val=wp_unslash($_POST['bsw_media_'.$key]??'');
  if($key==='source')$val=esc_url_raw($val);else $val=sanitize_text_field($val);
  update_post_meta($id,'_bsw_media_'.$key,$val);
 }
});
add_shortcode('bigshor_wiki_media',function($atts){
 $a=shortcode_atts(array('limit'=>60),$atts);$q=new WP_Query(array('post_type'=>'wiki_media','post_status'=>'publish','posts_per_page'=>max(1,min(100,(int)$a['limit']))));
 if(!$q->have_posts())return '<p>The media repository is being built. No media records are published yet.</p>';
 $o='<div class="bsw-media-grid">';
 while($q->have_posts()){$q->the_post();$id=get_the_ID();$thumb=get_the_post_thumbnail($id,'medium');$lic=get_post_meta($id,'_bsw_media_licence',true);$creator=get_post_meta($id,'_bsw_media_creator',true);
 $o.='<article class="bsw-media-card"><a href="'.esc_url(get_permalink()).'">'.$thumb.'<strong>'.esc_html(get_the_title()).'</strong></a><p>'.esc_html($creator).'</p><p>'.esc_html($lic).'</p></article>';}
 wp_reset_postdata();return $o.'</div>';
});
add_shortcode('bigshor_wiki_media_submit',function(){
 if(!is_user_logged_in()||!current_user_can('upload_files'))return '<p>Sign in with an account permitted to upload media to contribute to the repository.</p>';
 $notice='';
 if(isset($_POST['bsw_media_submit'])){
  if(!isset($_POST['bsw_media_submit_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bsw_media_submit_nonce'])),'bsw_media_submit'))$notice='<p class="wiki-alert">Security validation failed.</p>';
  elseif(empty($_FILES['bsw_media_file']['name']))$notice='<p class="wiki-alert">Choose an image to upload.</p>';
  else{
   require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/image.php';
   $file=$_FILES['bsw_media_file'];$type=wp_check_filetype_and_ext($file['tmp_name'],$file['name']);
   $allowed=array('image/jpeg','image/png','image/gif','image/webp');
   if(!in_array($type['type'],$allowed,true))$notice='<p class="wiki-alert">Only JPEG, PNG, GIF and WebP images are accepted here.</p>';
   else{
    $uploaded=wp_handle_upload($file,array('test_form'=>false,'mimes'=>array('jpg|jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp')));
    if(isset($uploaded['error']))$notice='<p class="wiki-alert">'.esc_html($uploaded['error']).'</p>';
    else{
     $att=wp_insert_attachment(array('post_mime_type'=>$uploaded['type'],'post_title'=>sanitize_text_field(pathinfo($file['name'],PATHINFO_FILENAME)),'post_status'=>'inherit'),$uploaded['file']);
     if(!is_wp_error($att)){
      $meta=wp_generate_attachment_metadata($att,$uploaded['file']);wp_update_attachment_metadata($att,$meta);
      $title=sanitize_text_field(wp_unslash($_POST['bsw_media_title']??pathinfo($file['name'],PATHINFO_FILENAME)));
      $mid=wp_insert_post(array('post_type'=>'wiki_media','post_status'=>'pending','post_title'=>$title,'post_content'=>sanitize_textarea_field(wp_unslash($_POST['bsw_media_description']??'')),'post_author'=>get_current_user_id()));
      if($mid){set_post_thumbnail($mid,$att);foreach(array('source'=>'source','creator'=>'creator','licence'=>'licence','credit'=>'credit') as $key=>$field)update_post_meta($mid,'_bsw_media_'.$key,sanitize_text_field(wp_unslash($_POST['bsw_media_'.$field]??'')));$notice='<p class="bsw-success">Media submitted for editorial review. It will appear after approval.</p>';}
     }
    }
   }
  }
 }
 $o=$notice.'<form class="bsw-v3-form" method="post" enctype="multipart/form-data">'.wp_nonce_field('bsw_media_submit','bsw_media_submit_nonce',true,false);
 $o.='<p><label>Media file (JPEG, PNG, GIF, WebP)<input type="file" name="bsw_media_file" accept="image/jpeg,image/png,image/gif,image/webp" required></label></p>';
 $o.='<p><label>Title<input name="bsw_media_title" required></label></p><p><label>Description<textarea name="bsw_media_description"></textarea></label></p>';
 $o.='<p><label>Original source URL<input type="url" name="bsw_media_source"></label></p><p><label>Creator / rights holder<input name="bsw_media_creator"></label></p>';
 $o.='<p><label>Licence<input name="bsw_media_licence" placeholder="e.g. CC BY 4.0 or All rights reserved"></label></p><p><label>Attribution / credit<input name="bsw_media_credit"></label></p>';
 $o.='<p class="wiki-alert">Do not upload copyrighted material unless you own it or have permission. Editors will review every submission.</p><button class="wiki-button" name="bsw_media_submit" value="1">Submit media for review</button></form>';
 return $o;
});
