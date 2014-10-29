<?php
add_filter('the_posts',  'putStickyOnTop' );
function putStickyOnTop( $posts ) {
  if(is_home() || !is_main_query() || !is_archive())
    return $posts;
    
  global $wp_query;

  // 如果这个分类没有开启显示置顶的话
  if(is_category() || is_tag()) {
    if(is_category())$term_id = $wp_query->query_vars['cat'];
    else $term_id = $wp_query->query_vars['tag_id'];
    $show_sticky = get_term_meta($term_id,'show_sticky');
    if(!$show_sticky)return $posts;
  }
  // 不是分类或标签就不要置顶了
  else {
    return $posts;
  }

  // 获取所有置顶文章
  $sticky_posts = get_option('sticky_posts');
  
  if ( $wp_query->query_vars['paged'] <= 1 && !empty($sticky_posts) && is_array($sticky_posts) && !get_query_var('ignore_sticky_posts') ) {
    $stickies1 = get_posts( array( 'post__in' => $sticky_posts ) );
    foreach ( $stickies1 as $sticky_post1 ) {
      // 判断当前是否分类页 
      if($wp_query->is_category == 1 && !has_category($wp_query->query_vars['cat'], $sticky_post1->ID)) {
        // 去除不属于本分类的置顶文章
        $offset1 = array_search($sticky_post1->ID, $sticky_posts);
        unset( $sticky_posts[$offset1] );
      }
      if($wp_query->is_tag == 1 && !has_tag($wp_query->query_vars['tag'], $sticky_post1->ID)) {
        // 去除不属于本标签的文章
        $offset1 = array_search($sticky_post1->ID, $sticky_posts);
        unset( $sticky_posts[$offset1] );
      }
      if($wp_query->is_year == 1 && date_i18n('Y', strtotime($sticky_post1->post_date))!=$wp_query->query['m']) {
        // 去除不属于本年份的文章
        $offset1 = array_search($sticky_post1->ID, $sticky_posts);
        unset( $sticky_posts[$offset1] );
      }
      if($wp_query->is_month == 1 && date_i18n('Ym', strtotime($sticky_post1->post_date))!=$wp_query->query['m']) {
        // 去除不属于本月份的文章
        $offset1 = array_search($sticky_post1->ID, $sticky_posts);
        unset( $sticky_posts[$offset1] );
      }
      if($wp_query->is_day == 1 && date_i18n('Ymd', strtotime($sticky_post1->post_date))!=$wp_query->query['m']) {
        // 去除不属于本日期的文章
        $offset1 = array_search($sticky_post1->ID, $sticky_posts);
        unset( $sticky_posts[$offset1] );
      }
      if($wp_query->is_author == 1 && $sticky_post1->post_author != $wp_query->query_vars['author']) {
        // 去除不属于本作者的文章
        $offset1 = array_search($sticky_post1->ID, $sticky_posts);
        unset( $sticky_posts[$offset1] );
      }
    }
  
    $num_posts = count($posts);
    $sticky_offset = 0;
    // Loop over posts and relocate stickies to the front.
    for ( $i = 0; $i < $num_posts; $i++ ) {
      if ( in_array($posts[$i]->ID, $sticky_posts) ) {
        $sticky_post = $posts[$i];
        // Remove sticky from current position
        array_splice($posts, $i, 1);
        // Move to front, after other stickies
        array_splice($posts, $sticky_offset, 0, array($sticky_post));
        // Increment the sticky offset. The next sticky will be placed at this offset.
        $sticky_offset++;
        // Remove post from sticky posts array
        $offset = array_search($sticky_post->ID, $sticky_posts);
        unset( $sticky_posts[$offset] );
      }
    }

    // If any posts have been excluded specifically, Ignore those that are sticky.
    if ( !empty($sticky_posts) && !empty($wp_query->query_vars['post__not_in'] ) )
      $sticky_posts = array_diff($sticky_posts, $wp_query->query_vars['post__not_in']);

    // Fetch sticky posts that weren't in the query results
    if ( !empty($sticky_posts) ) {
      $stickies = get_posts( array(
        'post__in' => $sticky_posts,
        'post_type' => $wp_query->query_vars['post_type'],
        'post_status' => 'publish',
        'nopaging' => true
      ) );

      foreach ( $stickies as $sticky_post ) {
        array_splice( $posts, $sticky_offset, 0, array( $sticky_post ) );
        $sticky_offset++;
      }
    }
  }
  
  return $posts;
}

/**
下方的代码用以实现term_meta
**/

add_action('category_add_form_fields','extra_term_show_sticky__fields');
add_action('edit_category_form_fields','extra_term_show_sticky__fields');
add_action('add_tag_form_fields','extra_term_show_sticky__fields');
add_action('edit_tag_form_fields','extra_term_show_sticky__fields');
function extra_term_show_sticky__fields($term){
  $metas = array(
    array('meta_name' => '置顶文章','meta_key' => 'show_sticky'),
  );
  $term_id = $term->term_id;
  foreach($metas as $meta) {
    $meta_name = $meta['meta_name'];
    $meta_key = $meta['meta_key'];
    $meta_value = get_option("term_{$term_id}_meta_{$meta_key}");
    ?>
<tr class="form-field">
  <th scope="row" valign="top"><label for="term_<?php echo $meta_key; ?>"><?php echo $meta_name; ?></label></th>
  <td><input type="text" name="term_meta_<?php echo $meta_key; ?>" id="term_<?php echo $meta_key; ?>" class="regular-text" value="<?php echo $meta_value; ?>"></td>
</tr>
    <?php
  }
}

add_action('created_category','save_extra_term_show_sticky__fileds');
add_action('edited_category','save_extra_term_show_sticky__fileds');
add_action('created_post_tag','save_extra_term_show_sticky__fileds');
add_action('edited_post_tag','save_extra_term_show_sticky__fileds');
function save_extra_term_show_sticky__fileds($term_id){
  if(!empty($_POST))foreach($_POST as $key => $value){
    echo $key;
    if(strpos($key,'term_meta_') === 0 && trim($value) != '') {
      $meta_key = str_replace('term_meta_','',$key);
      $meta_value = trim($value);
      update_option("term_{$term_id}_meta_{$meta_key}",$meta_value) OR add_option("term_{$term_id}_meta_{$meta_key}",$meta_value);
    }
  }
}

if(!function_exists('get_term_meta')) :
function get_term_meta($term_id,$meta_key){
  if(is_object($term_id))$term_id = $term_id->term_id;
  $term_meta = get_option("term_{$term_id}_meta_{$meta_key}");
  if($term_meta){
    return $term_meta;
  }else{
    return null;
  }
}
endif;