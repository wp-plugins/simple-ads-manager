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
    
    function widget( $args, $instance ) {
      extract($args);
      $title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title']);
      $adp_id = $instance['adp_id'];
      $hide_style = $instance['hide_style'];
      $place_codes = $instance['place_codes'];
      
      $ad = new SamAd(array('id' => $adp_id), $place_codes);
      $content = $ad->ad;
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
