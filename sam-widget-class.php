<?php
if(!class_exists('simple_ads_manager_widget') && class_exists('WP_Widget')) {
  class simple_ads_manager_widget extends WP_Widget {
    function simple_ads_manager_widget() {
      $widget_ops = array( 'classname' => 'simple_ads_manager_widget', 'description' => __('Ads serviced by Simple Ads Manager.', SAM_DOMAIN));
      $control_ops = array( 'id_base' => 'simple_ads_manager_widget' );
      $this->WP_Widget( 'simple_ads_manager_widget', __('Advertisment', SAM_DOMAIN), $widget_ops, $control_ops );
    }
    
    function getSettings() {
      $options = get_option(SAM_OPTIONS_NAME, '');      
      return $options;
    }
    
    function buildAd( $args = null, $useCodes = false ) {
      if(is_null($args)) return;
      if(empty($args['id']) && empty($args['name'])) return;
      
      $settings = $this->getSettings();
      if($settings['adCycle'] == 0) $cycle = 1000;
      else $cycle = $settings['adCycle'];
      
      global $wpdb;
      $pTable = $wpdb->prefix . "sam_places";          
      $aTable = $wpdb->prefix . "sam_ads";
      
      $viewPages = 0;
      $cats = array();
      $wcc = '';
      $wci = '';
      if(is_home() || is_front_page()) $viewPages += SAM_IS_HOME;
      if(is_singular()) {
        $viewPages += SAM_IS_SINGULAR;
        if(is_single()) {
          global $post;
          
          $viewPages += SAM_IS_SINGLE;
          $categories = get_the_category($post->ID);
          foreach($categories as $category) 
            $wcc .= " OR ({$aTable}.view_type = 3 AND FIND_IN_SET('{$category->cat_name}', {$aTable}.view_cats))";
          $wci = " OR ({$aTable}.view_type = 2 AND FIND_IN_SET({$post->ID}, {$aTable}.view_id))";
        }
        if(is_page()) $viewPages += SAM_IS_PAGE;
        if(is_attachment()) $viewPages += SAM_IS_ATTACHMENT;
      }
      if(is_search()) $viewPages += SAM_IS_SEARCH;
      if(is_404()) $viewPages += SAM_IS_404;
      if(is_archive()) {
        $viewPages += SAM_IS_ARCHIVE;
        if(is_tax()) $viewPages += SAM_IS_TAX;
        if(is_category()) $viewPages += SAM_IS_CATEGORY;
        if(is_tag()) $viewPages += SAM_IS_TAG;
        if(is_author()) $viewPages += SAM_IS_AUTHOR;
        if(is_date()) $viewPages += SAM_IS_DATE;
      }
      
      $whereClause  = "({$aTable}.view_type = 1)";
      $whereClause .= " OR ({$aTable}.view_type = 0 AND ({$aTable}.view_pages+0 & {$viewPages}))";
      $whereClause .= $wcc.$wci;
      $whereClauseT = " AND (({$aTable}.ad_schedule IS FALSE) OR ({$aTable}.ad_schedule IS TRUE AND (CURDATE() BETWEEN {$aTable}.ad_start_date AND {$aTable}.ad_end_date)))";
      
      $whereClauseW = " AND (({$aTable}.ad_weight > 0) AND (({$aTable}.ad_weight_hits*10/({$aTable}.ad_weight*{$cycle})) < 1))";
      $whereClause2W = "AND ({$aTable}.ad_weight > 0)";
      
      if(!empty($args['id'])) $pId = "{$pTable}.id = {$args['id']}";
      else $pId = "{$pTable}.name = {$args['name']}";
      
      $pSql = "SELECT
                  {$pTable}.id,
                  {$pTable}.name,                  
                  {$pTable}.description,
                  {$pTable}.code_before,
                  {$pTable}.code_after,
                  {$pTable}.place_size,
                  {$pTable}.place_custom_width,
                  {$pTable}.place_custom_height,
                  {$pTable}.patch_img,
                  {$pTable}.patch_link,
                  {$pTable}.patch_code,                  
                  {$pTable}.patch_source,
                  {$pTable}.trash,
                  (SELECT COUNT(*) FROM {$aTable} WHERE {$aTable}.pid = {$pTable}.id AND {$aTable}.trash IS FALSE) AS ad_count,
                  (SELECT COUNT(*) FROM {$aTable} WHERE {$aTable}.pid = {$pTable}.id AND {$aTable}.trash IS FALSE AND ({$whereClause}){$whereClauseT}{$whereClause2W}) AS ad_logic_count,
                  (SELECT COUNT(*) FROM {$aTable} WHERE {$aTable}.pid = {$pTable}.id AND {$aTable}.trash IS FALSE AND ({$whereClause}){$whereClauseT}{$whereClauseW}) AS ad_full_count
                FROM {$pTable}
                WHERE {$pId} AND {$pTable}.trash IS FALSE";
      
      $place = $wpdb->get_row($pSql, ARRAY_A);
      $output = $pSql;
      if((abs($place['ad_count']) == 0) || (abs($place['ad_logic_count']) == 0)) {
        if($place['patch_source'] == 0) 
          $output = "<a href='{$place['patch_link']}'><img src='{$place['patch_img']}' /></a>";
        else $output = $place['patch_code'];
      }
      
      if((abs($place['ad_logic_count']) > 0) && (abs($place['ad_full_count']) == 0)) {
        $wpdb->update($aTable, array('ad_weight_hits' => 0), array('pid' => $place['id']), array("%d"), array("%d"));
      }
      
      $aSql = "SELECT
                  {$aTable}.id,
                  {$aTable}.pid,
                  {$aTable}.code_mode,
                  {$aTable}.ad_code,
                  {$aTable}.ad_img,
                  {$aTable}.ad_target,
                  {$aTable}.count_clicks,
                  {$aTable}.code_type,
                  {$aTable}.ad_hits,
                  {$aTable}.ad_weight_hits,
                  IF({$aTable}.ad_weight, ({$aTable}.ad_weight_hits*10/({$aTable}.ad_weight*{$cycle})), 0) AS ad_cycle
                FROM {$aTable}
                WHERE {$aTable}.pid = {$place['id']} AND {$aTable}.trash IS FALSE AND ({$whereClause}){$whereClauseT}{$whereClauseW}
                ORDER BY ad_cycle
                LIMIT 1";
      
      if(abs($place['ad_logic_count']) > 0) {
        $ad = $wpdb->get_row($aSql, ARRAY_A);
        if($ad['code_mode'] == 0) {
          $outId = ((int) $ad['count_clicks'] == 1) ? " id='a".rand(10, 99)."_".$ad['id']."' class='sam_ad'" : '';
          $output = "<a href='{$ad['ad_target']}' target='_blank'><img{$outId} src='{$ad['ad_img']}' /></a>";
        }
        else {
          if($ad['code_type'] == 1) {
            ob_start();
            eval('?>'.$ad['ad_code'].'<?');
            $output = ob_get_contents();
            ob_end_clean();
          }
          else $output = $ad['ad_code'];
        }
        $wpdb->query("UPDATE {$aTable} SET {$aTable}.ad_hits = {$aTable}.ad_hits+1, {$aTable}.ad_weight_hits = {$aTable}.ad_weight_hits+1 WHERE {$aTable}.id = {$ad['id']}");
      }
      
      if($useCodes) $output = $place['code_before'].$output.$place['code_after'];
      return $output;
    }
    
    function widget( $args, $instance ) {
      extract($args);
      $title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title']);
      $adp_id = $instance['adp_id'];
      $hide_style = $instance['hide_style'];
      $place_codes = $instance['place_codes'];
      
      $content = $this->buildAd(array('id' => $adp_id), $place_codes);
      if(!empty($content)) {
        if ( !$hide_style ) {
          echo $before_widget;
          if ( !empty( $title ) ) echo $before_title . $title . $after_title;
          echo $content;
          echo $after_widget;
        }
        else echo $content;
      }
    }
    
    function update( $new_instance, $old_instance ) {
      $instance = $old_instance;
      $instance['title'] = strip_tags($new_instance['title']);
      $instance['adp_id'] = $new_instance['adp_id'];
      $instance['hide_style'] = isset($new_instance['hide_style']);
      $instance['place_codes'] = isset($new_instance['place_codes']);
      return $instance;
    }
    
    function form( $instance ) {
      global $wpdb;
      $pTable = $wpdb->prefix . "sam_places";
      
      $ids = $wpdb->get_results("SELECT {$pTable}.id, {$pTable}.name FROM {$pTable} WHERE {$pTable}.trash IS FALSE", ARRAY_A);
      
      $instance = wp_parse_args((array) $instance, 
        array(
          'title'       => '', 
          'adp_id'      => '', 
          'parse'       => false
        )
      );
      $title = strip_tags($instance['title']);
      $adp_id = $instance['adp_id'];
      $hide_style = $instance['hide_style'];
      $place_codes = $instance['place_codes'];
      ?>
      <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', SAM_DOMAIN); ?></label>
        <input class="widefat" 
          id="<?php echo $this->get_field_id('title'); ?>" 
          name="<?php echo $this->get_field_name('title'); ?>" 
          type="text" value="<?php echo esc_attr($title); ?>" />
      </p>
      <p>
        <label for="<?php echo $this->get_field_id('adp_id'); ?>"><?php _e('Ads Place:', SAM_DOMAIN) ?></label>
        <select class="widefat" 
          id="<?php echo $this->get_field_id('adp_id'); ?>" 
          name="<?php echo $this->get_field_name('adp_id'); ?>" >
        <?php 
          foreach ($ids as $option) 
            echo '<option value='.$option['id'].(($instance['adp_id'] === $option['id']) ? ' selected' : '' ).'>'.$option['name'].'</option>';
        ?> 
        </select>
      </p>    
      <p>
        <input 
          id="<?php echo $this->get_field_id('hide_style'); ?>" 
          name="<?php echo $this->get_field_name('hide_style'); ?>" 
          type="checkbox" <?php checked($instance['hide_style']); ?> />&nbsp;
        <label for="<?php echo $this->get_field_id('hide_style'); ?>">
          <?php _e('Hide widget style.', SAM_DOMAIN); ?>
        </label>
      </p>
      <p>
        <input 
          id="<?php echo $this->get_field_id('place_codes'); ?>" 
          name="<?php echo $this->get_field_name('place_codes'); ?>" 
          type="checkbox" <?php checked($instance['place_codes']); ?> />&nbsp;
        <label for="<?php echo $this->get_field_id('place_codes'); ?>">
          <?php _e('Allow using previously defined "before" and "after" codes of Ads Place..', SAM_DOMAIN); ?>
        </label>
      </p>
      <?php
    }
  }
}
?>
