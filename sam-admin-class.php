<?php
if ( !class_exists( 'SimpleAdsManagerAdmin' && class_exists('SimpleAdsManager') ) ) {
  class SimpleAdsManagerAdmin extends SimpleAdsManager {
    function __construct() {
      parent::__construct();
      
			if ( function_exists( 'load_plugin_textdomain' ) )
				load_plugin_textdomain( SAM_DOMAIN, false, basename( SAM_PATH ) );
      
      if(!is_dir(SAM_AD_IMG)) mkdir(SAM_AD_IMG);
				
      add_action('activate_simple-ads-manager/simple-ads-manager.php',  array(&$this, 'onActivate'));
			add_action('deactivate_simple-ads-manager/simple-ads-manager.php',  array(&$this, 'onDeactivate'));
      add_action('wp_ajax_upload_ad_image', array(&$this, 'uploadHandler'));
      add_action('wp_ajax_get_strings', array(&$this, 'getStringsHandler'));
			add_action('admin_init', array(&$this, 'initSettings'));
			add_action('admin_menu', array(&$this, 'regAdminPage'));
      add_filter('tiny_mce_version', array(&$this, 'tinyMCEVersion') );
      add_action('init', array(&$this, 'addButtons'));
    }
    
    function onActivate() {
      $settings = parent::getSettings();
			update_option( SAM_OPTIONS_NAME, $settings );
			parent::updateDB();
    }
    
    function onDeactivate() {
      global $wpdb;
			$pTable = $wpdb->prefix . "sam_places";					
			$aTable = $wpdb->prefix . "sam_ads";
			$settings = parent::getSettings();
			
			if($settings['deleteOptions'] == 1) {
				delete_option( SAM_OPTIONS_NAME );
				delete_option('sam_version');
				delete_option('sam_db_version');
			}
			if($settings['deleteDB'] == 1) {
				$sql = 'DROP TABLE IF EXISTS '.$pTable;
				$wpdb->query($sql);
				$sql = 'DROP TABLE IF EXISTS '.$aTable;
				$wpdb->query($sql);
				delete_option('sam_db_version');
			}
      if($settings['deleteFolder'] == 1) {
        if(is_dir(SAM_AD_IMG)) rmdir(SAM_AD_IMG);
      }
    }
		
		function initSettings() {
			register_setting('samOptions', SAM_OPTIONS_NAME);
      add_settings_section("sam_general_section", __("General Settings", SAM_DOMAIN), array(&$this, "drawGeneralSection"), 'sam-settings');
      add_settings_section("sam_single_section", __("Auto Inserting Settings", SAM_DOMAIN), array(&$this, "drawSingleSection"), 'sam-settings');
      add_settings_section("sam_layout_section", __("Admin Layout", SAM_DOMAIN), array(&$this, "drawLayoutSection"), 'sam-settings');
			add_settings_section("sam_deactivate_section", __("Plugin Deactivating", SAM_DOMAIN), array(&$this, "drawDeactivateSection"), 'sam-settings');
			
      add_settings_field('adCycle', __("Views per Cycle", SAM_DOMAIN), array(&$this, 'drawTextOption'), 'sam-settings', 'sam_general_section', array('description' => __('Number of hits of one ad for a full cycle of rotation (maximal activity).', SAM_DOMAIN)));
      
      add_settings_field('bpAdsId', __("Ads Place before content", SAM_DOMAIN), array(&$this, 'drawSelectOptionX'), 'sam-settings', 'sam_single_section', array('description' => ''));
      add_settings_field('beforePost', __("Allow Ads Place auto inserting before post/page content", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'beforePost', 'checkbox' => true));
      add_settings_field('bpUseCodes', __("Allow using predefined Ads Place HTML codes (before and after codes)", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'bpUseCodes', 'checkbox' => true));
      add_settings_field('apAdsId', __("Ads Place after content", SAM_DOMAIN), array(&$this, 'drawSelectOptionX'), 'sam-settings', 'sam_single_section', array('description' => ''));
      add_settings_field('afterPost', __("Allow Ads Place auto inserting after post/page content", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'afterPost', 'checkbox' => true));
      add_settings_field('apUseCodes', __("Allow using predefined Ads Place HTML codes (before and after codes)", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'apUseCodes', 'checkbox' => true));
      
      add_settings_field('placesPerPage', __("Ads Places per Page", SAM_DOMAIN), array(&$this, 'drawTextOption'), 'sam-settings', 'sam_layout_section', array('description' => __('Ads Places Management grid pagination. How many Ads Places will be shown on one page of grid.', SAM_DOMAIN)));
			add_settings_field('itemsPerPage', __("Ads per Page", SAM_DOMAIN), array(&$this, 'drawTextOption'), 'sam-settings', 'sam_layout_section', array('description' => __('Ads of Ads Place Management grid pagination. How many Ads will be shown on one page of grid.', SAM_DOMAIN)));
      
      add_settings_field('deleteOptions', __("Delete plugin options during deactivating plugin", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_deactivate_section', array('label_for' => 'deleteOptions', 'checkbox' => true));
			add_settings_field('deleteDB', __("Delete database tables of plugin during deactivating plugin", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_deactivate_section', array('label_for' => 'deleteDB', 'checkbox' => true));
      add_settings_field('deleteFolder', __("Delete custom images folder of plugin during deactivating plugin", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_deactivate_section', array('label_for' => 'deleteFolder', 'checkbox' => true));
		}
    
    function regAdminPage() {
			$menuPage = add_object_page(__('Ads', SAM_DOMAIN), __('Ads', SAM_DOMAIN), 8, 'sam-list', array(&$this, 'samTablePage'), WP_PLUGIN_URL.'/simple-ads-manager/images/sam-icon.png');
			$samListPage = add_submenu_page('sam-list', __('Ads List', SAM_DOMAIN), __('Ads Places', SAM_DOMAIN), 8, 'sam-list', array(&$this, 'samTablePage'));
			add_action('admin_print_styles-'.$samListPage, array(&$this, 'adminListStyles'));
      $samEditPage = add_submenu_page('sam-list', __('Ads Editor', SAM_DOMAIN), __('New Place', SAM_DOMAIN), 8, 'sam-edit', array(&$this, 'samEditPage'));
      add_action('admin_print_styles-'.$samEditPage, array(&$this, 'adminEditStyles'));
      add_action('admin_print_scripts-'.$samEditPage, array(&$this, 'adminEditScripts'));
			$samSettingsPage = add_submenu_page('sam-list', __('Simple Ads Manager Settings', SAM_DOMAIN), __('Settings', SAM_DOMAIN), 8, 'sam-settings', array(&$this, 'samAdminPage'));
      add_action('admin_print_styles-'.$samSettingsPage, array(&$this, 'adminSettingsStyles'));
		}
    
    function adminEditStyles() {
      wp_enqueue_style('adminEditLayout', SAM_URL.'css/sam-admin-edit.css', false, SAM_VERSION);
      wp_enqueue_style('jquery-ui-css', SAM_URL.'css/jquery-ui-1.8.6.custom.css', false, '1.8.6');
      wp_enqueue_style('ColorPickerCSS', SAM_URL.'css/colorpicker.css');
    }
    
    function adminSettingsStyles() {
      wp_enqueue_style('adminSettingsLayout', SAM_URL.'css/sam-admin-edit.css', false, SAM_VERSION);
    }
    
    function adminListStyles() {
      wp_enqueue_style('adminListLayout', SAM_URL.'css/sam-admin-list.css', false, SAM_VERSION);
    }
    
    function adminEditScripts() {
      $loc = get_locale();
      if(in_array($loc, array('en_GB', 'fr_CH', 'pt_BR', 'sr_SR', 'zh_CN', 'zh_HK', 'zh_TW')))
        $lc = str_replace('_', '-', $loc);
      else $lc = substr($loc, 0, 2);
      wp_enqueue_script('jquery');
      wp_enqueue_script('jquery-ui', SAM_URL.'js/jquery-ui-1.8.6.custom.min.js', array('jquery'), '1.8.6');
      if(file_exists(SAM_PATH.'/js/i18n/jquery.ui.datepicker-'.$lc.'.js'))
        wp_enqueue_script('jquery-ui-locale', SAM_URL.'js/i18n/jquery.ui.datepicker-'.$lc.'.js', array('jquery'), '1.8.6');
      wp_enqueue_script('ColorPicker', SAM_URL.'js/colorpicker.js', array('jquery'));
      wp_enqueue_script('AjaxUpload', SAM_URL.'js/ajaxupload.js', array('jquery'), '3.9');
      wp_enqueue_script('adminEditScript', SAM_URL.'js/sam-admin-edit.js', array('jquery', 'jquery-ui', 'ColorPicker'), SAM_VERSION);
    }

    /**
    * Outputs the name of Ads Place.
    *
    * Returns full Ads Place Size name.
    *
    * @since 0.1.1
    *
    * @param string $size Short name of Ads Place size
    * @return string value of Ads Place Size Name
    */
    function getAdSize($value = '', $width = null, $height = null) {
      if($value == '') return null;

      if($value == 'custom') return array('name' => __('Custom sizes', SAM_DOMAIN), 'width' => $width, 'height' => $height);

      $aSizes = array(
        '800x90' => sprintf('%1$s x %2$s %3$s', 800, 90, __('Large Leaderboard', SAM_DOMAIN)),
			  '728x90' => sprintf('%1$s x %2$s %3$s', 728, 90, __('Leaderboard', SAM_DOMAIN)),
			  '600x90' => sprintf('%1$s x %2$s %3$s', 600, 90, __('Small Leaderboard', SAM_DOMAIN)),
			  '550x250' => sprintf('%1$s x %2$s %3$s', 550, 250, __('Mega Unit', SAM_DOMAIN)),
			  '550x120' => sprintf('%1$s x %2$s %3$s', 550, 120, __('Small Leaderboard', SAM_DOMAIN)),
			  '550x90' => sprintf('%1$s x %2$s %3$s', 550, 90, __('Small Leaderboard', SAM_DOMAIN)),
			  '468x180' => sprintf('%1$s x %2$s %3$s', 468, 180, __('Tall Banner', SAM_DOMAIN)),
			  '468x120' => sprintf('%1$s x %2$s %3$s', 468, 120, __('Tall Banner', SAM_DOMAIN)),
			  '468x90' => sprintf('%1$s x %2$s %3$s', 468, 90, __('Tall Banner', SAM_DOMAIN)),
			  '468x60' => sprintf('%1$s x %2$s %3$s', 468, 60, __('Banner', SAM_DOMAIN)),
			  '450x90' => sprintf('%1$s x %2$s %3$s', 450, 90, __('Tall Banner', SAM_DOMAIN)),
			  '430x90' => sprintf('%1$s x %2$s %3$s', 430, 90, __('Tall Banner', SAM_DOMAIN)),
			  '400x90' => sprintf('%1$s x %2$s %3$s', 400, 90, __('Tall Banner', SAM_DOMAIN)),
			  '234x60' => sprintf('%1$s x %2$s %3$s', 234, 60, __('Half Banner', SAM_DOMAIN)),
			  '200x90' => sprintf('%1$s x %2$s %3$s', 200, 90, __('Tall Half Banner', SAM_DOMAIN)),
			  '150x50' => sprintf('%1$s x %2$s %3$s', 150, 50, __('Half Banner', SAM_DOMAIN)),
			  '120x90' => sprintf('%1$s x %2$s %3$s', 120, 90, __('Button', SAM_DOMAIN)),
			  '120x60' => sprintf('%1$s x %2$s %3$s', 120, 60, __('Button', SAM_DOMAIN)),
			  '83x31' => sprintf('%1$s x %2$s %3$s', 83, 31, __('Micro Bar', SAM_DOMAIN)),
			  '728x15x4' => sprintf('%1$s x %2$s %3$s, %4$s', 728, 15, __('Thin Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
			  '728x15x5' => sprintf('%1$s x %2$s %3$s, %4$s', 728, 15, __('Thin Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5)),
			  '468x15x4' => sprintf('%1$s x %2$s %3$s, %4$s', 468, 15, __('Thin Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
			  '468x15x5' => sprintf('%1$s x %2$s %3$s, %4$s', 468, 15, __('Thin Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5)),
        '160x600' => sprintf('%1$s x %2$s %3$s', 160, 600, __('Wide Skyscraper', SAM_DOMAIN)),
			  '120x600' => sprintf('%1$s x %2$s %3$s', 120, 600, __('Skyscraper', SAM_DOMAIN)),
			  '200x360' => sprintf('%1$s x %2$s %3$s', 200, 360, __('Wide Half Banner', SAM_DOMAIN)),
			  '240x400' => sprintf('%1$s x %2$s %3$s', 240, 400, __('Vertical Rectangle', SAM_DOMAIN)),
			  '180x300' => sprintf('%1$s x %2$s %3$s', 180, 300, __('Tall Rectangle', SAM_DOMAIN)),
			  '200x270' => sprintf('%1$s x %2$s %3$s', 200, 270, __('Tall Rectangle', SAM_DOMAIN)),
			  '120x240' => sprintf('%1$s x %2$s %3$s', 120, 240, __('Vertical Banner', SAM_DOMAIN)),
        '336x280' => sprintf('%1$s x %2$s %3$s', 336, 280, __('Large Rectangle', SAM_DOMAIN)),
			  '336x160' => sprintf('%1$s x %2$s %3$s', 336, 160, __('Wide Rectangle', SAM_DOMAIN)),
			  '334x100' => sprintf('%1$s x %2$s %3$s', 334, 100, __('Wide Rectangle', SAM_DOMAIN)),
			  '300x250' => sprintf('%1$s x %2$s %3$s', 300, 250, __('Medium Rectangle', SAM_DOMAIN)),
			  '300x150' => sprintf('%1$s x %2$s %3$s', 300, 150, __('Small Wide Rectangle', SAM_DOMAIN)),
			  '300x125' => sprintf('%1$s x %2$s %3$s', 300, 125, __('Small Wide Rectangle', SAM_DOMAIN)),
			  '300x70' => sprintf('%1$s x %2$s %3$s', 300, 70, __('Mini Wide Rectangle', SAM_DOMAIN)),
			  '250x250' => sprintf('%1$s x %2$s %3$s', 250, 250, __('Square', SAM_DOMAIN)),
			  '200x200' => sprintf('%1$s x %2$s %3$s', 200, 200, __('Small Square', SAM_DOMAIN)),
			  '200x180' => sprintf('%1$s x %2$s %3$s', 200, 180, __('Small Rectangle', SAM_DOMAIN)),
			  '180x150' => sprintf('%1$s x %2$s %3$s', 180, 150, __('Small Rectangle', SAM_DOMAIN)),
			  '160x160' => sprintf('%1$s x %2$s %3$s', 160, 160, __('Small Square', SAM_DOMAIN)),
			  '125x125' => sprintf('%1$s x %2$s %3$s', 125, 125, __('Button', SAM_DOMAIN)),
			  '200x90x4' => sprintf('%1$s x %2$s %3$s, %4$s', 200, 90, __('Tall Half Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
			  '200x90x5' => sprintf('%1$s x %2$s %3$s, %4$s', 200, 90, __('Tall Half Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5)),
			  '180x90x4' => sprintf('%1$s x %2$s %3$s, %4$s', 180, 90, __('Half Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
			  '180x90x5' => sprintf('%1$s x %2$s %3$s, %4$s', 180, 90, __('Half Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5)),
			  '160x90x4' => sprintf('%1$s x %2$s %3$s, %4$s', 160, 90, __('Tall Button', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
			  '160x90x5' => sprintf('%1$s x %2$s %3$s, %4$s', 160, 90, __('Tall Button', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5)),
			  '120x90x4' => sprintf('%1$s x %2$s %3$s, %4$s', 120, 90, __('Button', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
        '120x90x5' => sprintf('%1$s x %2$s %3$s, %4$s', 120, 90, __('Button', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5))
      );

      $aSize = explode("x", $value);
      //$aSize = preg_split("[x]", $value, null, PREG_SPLIT_NO_EMPTY);
      return array('name' => $aSizes[$value], 'width' => $aSize[0], 'height' => $aSize[1]);
    }

    function adSizes($size = '468x60') {
      $sizes = array(
        'horizontal' => array(
			    '800x90' => sprintf('%1$s x %2$s %3$s', 800, 90, __('Large Leaderboard', SAM_DOMAIN)),
			    '728x90' => sprintf('%1$s x %2$s %3$s', 728, 90, __('Leaderboard', SAM_DOMAIN)),
			    '600x90' => sprintf('%1$s x %2$s %3$s', 600, 90, __('Small Leaderboard', SAM_DOMAIN)),
			    '550x250' => sprintf('%1$s x %2$s %3$s', 550, 250, __('Mega Unit', SAM_DOMAIN)),
			    '550x120' => sprintf('%1$s x %2$s %3$s', 550, 120, __('Small Leaderboard', SAM_DOMAIN)),
			    '550x90' => sprintf('%1$s x %2$s %3$s', 550, 90, __('Small Leaderboard', SAM_DOMAIN)),
			    '468x180' => sprintf('%1$s x %2$s %3$s', 468, 180, __('Tall Banner', SAM_DOMAIN)),
			    '468x120' => sprintf('%1$s x %2$s %3$s', 468, 120, __('Tall Banner', SAM_DOMAIN)),
			    '468x90' => sprintf('%1$s x %2$s %3$s', 468, 90, __('Tall Banner', SAM_DOMAIN)),
			    '468x60' => sprintf('%1$s x %2$s %3$s', 468, 60, __('Banner', SAM_DOMAIN)),
			    '450x90' => sprintf('%1$s x %2$s %3$s', 450, 90, __('Tall Banner', SAM_DOMAIN)),
			    '430x90' => sprintf('%1$s x %2$s %3$s', 430, 90, __('Tall Banner', SAM_DOMAIN)),
			    '400x90' => sprintf('%1$s x %2$s %3$s', 400, 90, __('Tall Banner', SAM_DOMAIN)),
			    '234x60' => sprintf('%1$s x %2$s %3$s', 234, 60, __('Half Banner', SAM_DOMAIN)),
			    '200x90' => sprintf('%1$s x %2$s %3$s', 200, 90, __('Tall Half Banner', SAM_DOMAIN)),
			    '150x50' => sprintf('%1$s x %2$s %3$s', 150, 50, __('Half Banner', SAM_DOMAIN)),
			    '120x90' => sprintf('%1$s x %2$s %3$s', 120, 90, __('Button', SAM_DOMAIN)),
			    '120x60' => sprintf('%1$s x %2$s %3$s', 120, 60, __('Button', SAM_DOMAIN)),
			    '83x31' => sprintf('%1$s x %2$s %3$s', 83, 31, __('Micro Bar', SAM_DOMAIN)),
			    '728x15x4' => sprintf('%1$s x %2$s %3$s, %4$s', 728, 15, __('Thin Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
			    '728x15x5' => sprintf('%1$s x %2$s %3$s, %4$s', 728, 15, __('Thin Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5)),
			    '468x15x4' => sprintf('%1$s x %2$s %3$s, %4$s', 468, 15, __('Thin Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
			    '468x15x5' => sprintf('%1$s x %2$s %3$s, %4$s', 468, 15, __('Thin Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5))
        ),
        'vertical' => array(
			    '160x600' => sprintf('%1$s x %2$s %3$s', 160, 600, __('Wide Skyscraper', SAM_DOMAIN)),
			    '120x600' => sprintf('%1$s x %2$s %3$s', 120, 600, __('Skyscraper', SAM_DOMAIN)),
			    '200x360' => sprintf('%1$s x %2$s %3$s', 200, 360, __('Wide Half Banner', SAM_DOMAIN)),
			    '240x400' => sprintf('%1$s x %2$s %3$s', 240, 400, __('Vertical Rectangle', SAM_DOMAIN)),
			    '180x300' => sprintf('%1$s x %2$s %3$s', 180, 300, __('Tall Rectangle', SAM_DOMAIN)),
			    '200x270' => sprintf('%1$s x %2$s %3$s', 200, 270, __('Tall Rectangle', SAM_DOMAIN)),
			    '120x240' => sprintf('%1$s x %2$s %3$s', 120, 240, __('Vertical Banner', SAM_DOMAIN))
		    ),
        'square' => array(
			    '336x280' => sprintf('%1$s x %2$s %3$s', 336, 280, __('Large Rectangle', SAM_DOMAIN)),
			    '336x160' => sprintf('%1$s x %2$s %3$s', 336, 160, __('Wide Rectangle', SAM_DOMAIN)),
			    '334x100' => sprintf('%1$s x %2$s %3$s', 334, 100, __('Wide Rectangle', SAM_DOMAIN)),
			    '300x250' => sprintf('%1$s x %2$s %3$s', 300, 250, __('Medium Rectangle', SAM_DOMAIN)),
			    '300x150' => sprintf('%1$s x %2$s %3$s', 300, 150, __('Small Wide Rectangle', SAM_DOMAIN)),
			    '300x125' => sprintf('%1$s x %2$s %3$s', 300, 125, __('Small Wide Rectangle', SAM_DOMAIN)),
			    '300x70' => sprintf('%1$s x %2$s %3$s', 300, 70, __('Mini Wide Rectangle', SAM_DOMAIN)),
			    '250x250' => sprintf('%1$s x %2$s %3$s', 250, 250, __('Square', SAM_DOMAIN)),
			    '200x200' => sprintf('%1$s x %2$s %3$s', 200, 200, __('Small Square', SAM_DOMAIN)),
			    '200x180' => sprintf('%1$s x %2$s %3$s', 200, 180, __('Small Rectangle', SAM_DOMAIN)),
			    '180x150' => sprintf('%1$s x %2$s %3$s', 180, 150, __('Small Rectangle', SAM_DOMAIN)),
			    '160x160' => sprintf('%1$s x %2$s %3$s', 160, 160, __('Small Square', SAM_DOMAIN)),
			    '125x125' => sprintf('%1$s x %2$s %3$s', 125, 125, __('Button', SAM_DOMAIN)),
			    '200x90x4' => sprintf('%1$s x %2$s %3$s, %4$s', 200, 90, __('Tall Half Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
			    '200x90x5' => sprintf('%1$s x %2$s %3$s, %4$s', 200, 90, __('Tall Half Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5)),
			    '180x90x4' => sprintf('%1$s x %2$s %3$s, %4$s', 180, 90, __('Half Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
			    '180x90x5' => sprintf('%1$s x %2$s %3$s, %4$s', 180, 90, __('Half Banner', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5)),
			    '160x90x4' => sprintf('%1$s x %2$s %3$s, %4$s', 160, 90, __('Tall Button', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
			    '160x90x5' => sprintf('%1$s x %2$s %3$s, %4$s', 160, 90, __('Tall Button', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5)),
			    '120x90x4' => sprintf('%1$s x %2$s %3$s, %4$s', 120, 90, __('Button', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 4, SAM_DOMAIN), 4)),
          '120x90x5' => sprintf('%1$s x %2$s %3$s, %4$s', 120, 90, __('Button', SAM_DOMAIN), sprintf(__ngettext('%d Link', '%d Links', 5, SAM_DOMAIN), 5))
		    ),
        'custom' => array( 'custom' => __('Custom sizes', SAM_DOMAIN) )
      );
      $sections = array(
			  'horizontal' => __('Horizontal', SAM_DOMAIN),
			  'vertical' => __('Vertical', SAM_DOMAIN),
			  'square' => __('Square', SAM_DOMAIN),
			  'custom' => __('Custom width and height', SAM_DOMAIN),
		  );

      ?>
      <select id="place_size" name="place_size">
      <?php
      foreach($sizes as $key => $value) {
        ?>
        <optgroup label="<?php echo $sections[$key]; ?>">
            <?php
          foreach($value as $skey => $svalue) {
            ?>
          <option value="<?php echo $skey; ?>" <?php selected($size, $skey); ?> ><?php echo $svalue; ?></option>
            <?php
          }
          ?>
        </optgroup>
        <?php
      }
      ?>
      </select>
      <?php

    }
		
		public function getCategories($valueType = 'array') {
      global $wpdb;
      $tTable = $wpdb->prefix . "terms";
      $ttTable = $wpdb->prefix . "term_taxonomy";
      
      $sql = "SELECT
                {$tTable}.term_id,
                {$tTable}.name,
                {$ttTable}.taxonomy
              FROM
                {$tTable}
              INNER JOIN {$ttTable}
                ON {$tTable}.term_id = {$ttTable}.term_id
              WHERE
                {$ttTable}.taxonomy = 'category'";
                
      $cats = $wpdb->get_results($sql, ARRAY_A);
      if($valueType == 'array') $output = $cats;
      else {
        $output = '';
        foreach($cats as $cat) {
          if(!empty($output)) $output .= ',';
          $output .= "'".$cat['name']."'";
        }
      }
      return $output;
    }
    
    function getFilesList($dir, $exclude = null) {
      $i = 1;
      
      if( is_null($exclude) ) $exclude = array();
      
      if ($handle = opendir($dir)) {
        while (false !== ($file = readdir($handle))) {
          if( $file != '.' && $file != '..' && !in_array( $file, $exclude ) ) {
            echo '<option value="'.$file.'"'.(($i == 1) ? '" selected="selected"' : '').'>'.$file.'</option>'."\n";
            $i++;
          }
        }
        closedir($handle);
      }
    }
    
    function uploadHandler() {
      $uploaddir = SAM_AD_IMG;  
      $file = $uploaddir . basename($_FILES['uploadfile']['name']);   

      if ( move_uploaded_file( $_FILES['uploadfile']['tmp_name'], $file )) {
        exit("success");  
      } else {
        exit("error");  
      }
    }
    
    function getStringsHandler() {
      global $wpdb;
      $tTable = $wpdb->prefix . "terms";
      $ttTable = $wpdb->prefix . "term_taxonomy";
      
      $sql = "SELECT
                {$tTable}.name
              FROM
                {$tTable}
              INNER JOIN {$ttTable}
                ON {$tTable}.term_id = {$ttTable}.term_id
              WHERE
                {$ttTable}.taxonomy = 'category'";
                
      $cats = $wpdb->get_results($sql, ARRAY_A);
      $terms = array();
      
      foreach($cats as $value) array_push($terms, $value['name']);
      
      $output = array(
        'uploading' => __('Uploading', SAM_DOMAIN).' ...',
        'uploaded' => __('Uploaded.', SAM_DOMAIN),
        'status' => __('Only JPG, PNG or GIF files are allowed', SAM_DOMAIN),
        'file' => __('File', SAM_DOMAIN),
        'path' => SAM_AD_IMG,
        'url' => SAM_AD_URL,
        'cats' => $terms
      );
      
      header("Content-type: application/json; charset=UTF-8"); 
      exit(json_encode($output));
    }
    
    function removeTrailingComma($value = null) {
      if(empty($value)) return '';
      
      return rtrim(trim($value), ',');
    }
    
    function buildViewPages($args) {
      $output = 0;
      foreach($args as $value) {
        if(!empty($value)) $output += $value;
      }
      return $output;
    }
		
		function doSettingsSections($page) {
      global $wp_settings_sections, $wp_settings_fields;

      if ( !isset($wp_settings_sections) || !isset($wp_settings_sections[$page]) )
        return;

      foreach ( (array) $wp_settings_sections[$page] as $section ) {
        echo "<div id='poststuff' class='ui-sortable'>\n";
        echo "<div class='postbox opened'>\n";
        echo "<h3>{$section['title']}</h3>\n";
        echo '<div class="inside">';
        call_user_func($section['callback'], $section);
        if ( !isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']]) )
          continue;
        $this->doSettingsFields($page, $section['id']);
        echo '</div>';
        echo '</div>';
        echo '</div>';
      }
    }
    
    function doSettingsFields($page, $section) {
			global $wp_settings_fields;

			if ( !isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section]) )
				return;

			foreach ( (array) $wp_settings_fields[$page][$section] as $field ) {
				echo '<p>';
				if ( !empty($field['args']['checkbox']) ) {
					call_user_func($field['callback'], $field['id'], $field['args']);
					echo '<label for="' . $field['args']['label_for'] . '">' . $field['title'] . '</label>';
          echo '</p>';
				}
				else {
					if ( !empty($field['args']['label_for']) )
						echo '<label for="' . $field['args']['label_for'] . '">' . $field['title'] . '</label>';
					else
						echo '<strong>' . $field['title'] . '</strong><br/>';
          echo '</p>';
          echo '<p>';
					call_user_func($field['callback'], $field['id'], $field['args']);
          echo '</p>';
				}
        if(!empty($field['args']['description'])) echo '<p>' . $field['args']['description'] . '</p>';
			}
		}
    
    function drawGeneralSection() {
      echo '<p>'.__('There are general options.', SAM_DOMAIN).'</p>';
    }
    
    function drawSingleSection() {
      echo '<p>'.__('Single post/page auto inserting options. Use these parameters for allowing/defining Ads Places which will be automatically inserted before/after post/page content.', SAM_DOMAIN).'</p>';
    }
		
		function drawLayoutSection() {
			echo '<p>'.__('This options define layout for Ads Managin Pages.', SAM_DOMAIN).'</p>';
		}
    
    function drawDeactivateSection() {
			echo '<p>'.__('Are you allow to perform these actions during deactivating plugin?', SAM_DOMAIN).'</p>';
		}
    
    function drawTextOption( $id, $args ) {
      $settings = parent::getSettings();
      ?>
        <input id="<?php echo $id; ?>"
					name="<?php echo SAM_OPTIONS_NAME.'['.$id.']'; ?>"
					type="text"
					value="<?php echo $settings[$id]; ?>" />
      <?php
    }

    function drawCheckboxOption( $id, $args ) {
			$settings = parent::getSettings();
			?>
				<input id="<?php echo $id; ?>"
					<?php checked('1', $settings[$id]); ?>
					name="<?php echo SAM_OPTIONS_NAME.'['.$id.']'; ?>"
					type="checkbox"
					value="1" />
			<?php
		}
    
    function drawSelectOptionX( $id, $args ) {
      global $wpdb;
      $pTable = $wpdb->prefix . "sam_places";
      
      $ids = $wpdb->get_results("SELECT {$pTable}.id, {$pTable}.name FROM {$pTable} WHERE {$pTable}.trash IS FALSE", ARRAY_A);
      $settings = parent::getSettings();
      ?>
        <select id="<?php echo $id; ?>" name="<?php echo SAM_OPTIONS_NAME.'['.$id.']'; ?>">
        <?php
          foreach($ids as $value) {
            echo "<option value='{$value['id']}' ".selected($value['id'], $settings[$id], false)." >{$value['name']}</option>";
          }
        ?>
        </select>
      <?php
    }
		
		function samAdminPage() {
      if(!is_dir(SAM_AD_IMG)) mkdir(SAM_AD_IMG);
      //echo $this->buildAd(array('id' => 1));
      ?>
			<div class="wrap">
				<?php screen_icon("options-general"); ?>
				<h2><?php echo __('Simple Ads Manager Settings', SAM_DOMAIN).' '.SAM_VERSION; ?></h2>
				<?php
				if(isset($_GET['settings-updated'])) $updated = $_GET['settings-updated'];
        elseif(isset($_GET['updated'])) $updated = $_GET['updated'];
				if($updated === 'true') {
				?>
				<div class="updated"><p><strong><?php _e("Simple Ads Manager Settings Updated.", SAM_DOMAIN); ?></strong></p></div>
				<?php } else { ?>
				<div class="clear"></div>
				<?php } ?>
				<form action="options.php" method="post">
					<?php settings_fields('samOptions'); ?>
          <?php $this->doSettingsSections('sam-settings'); ?>
					<p class="submit">
						<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
					</p>
				</form>
			</div>
			<?php
		}
    
    function addButtons() {
      if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
        return;
      
      if ( get_user_option('rich_editing') == 'true') {
        add_filter("mce_external_plugins", array(&$this, "addTinyMCEPlugin"));
        add_filter('mce_buttons', array(&$this, 'registerButton'));
      }
    }
    
    function registerButton( $buttons ) {
      array_push($buttons, "separator", "samb");
      return $buttons;
    }
    
    function addTinyMCEPlugin( $plugin_array ) {
      $plugin_array['samb'] = SAM_URL.'js/editor_plugin.js';
      return $plugin_array;
    }
    
    function tinyMCEVersion( $version ) {
      return ++$version;
    }
		
		function samTablePage() {
			global $wpdb;
			$pTable = $wpdb->prefix . "sam_places";
			$aTable = $wpdb->prefix . "sam_ads";

      if(isset($_GET['mode'])) $mode = $_GET['mode'];
			else $mode = 'active';
			if(isset($_GET["action"])) $action = $_GET['action'];
			else $action = 'places';
			if(isset($_GET['item'])) $item = $_GET['item'];
			else $item = null;
			if(isset($_GET['iaction'])) $iaction = $_GET['iaction'];
			else $iaction = null;
			if(isset($_GET['iitem'])) $iitem = $_GET['iitem'];
			else $iitem = null;
			if(isset($_GET['apage'])) $apage = abs( (int) $_GET['apage'] );
			else $apage = 1;

      $options = $this->getSettings();
      $places_per_page = $options['placesPerPage'];
			$items_per_page = $options['itemsPerPage'];

      switch($action) {
				case 'places':
					if(!is_null($item)) {
						if($iaction === 'delete') $wpdb->update( $pTable, array( 'trash' => true ), array( 'id' => $item ), array( '%d' ), array( '%d' ) );
						elseif($iaction === 'untrash') $wpdb->update( $pTable, array( 'trash' => false ), array( 'id' => $item ), array( '%d' ), array( '%d' ) );
            elseif($iaction === 'kill') $wpdb->query("DELETE FROM {$pTable} WHERE id={$item}");
					}
          if($iaction === 'kill-em-all') $wpdb->query("DELETE FROM {$pTable} WHERE trash=true");
					$trash_num = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$pTable.' WHERE trash = TRUE'));
					$active_num = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$pTable.' WHERE trash = FALSE'));
					if(is_null($active_num)) $active_num = 0;
					if(is_null($trash_num)) $trash_num = 0;
					$all_num = $trash_num + $active_num;
					$total = (($mode !== 'all') ? (($mode === 'trash') ? $trash_num : $active_num) : $all_num);
					$start = $offset = ( $apage - 1 ) * $places_per_page;

					$page_links = paginate_links( array(
						'base' => add_query_arg( 'apage', '%#%' ),
						'format' => '',
						'prev_text' => __('&laquo;'),
						'next_text' => __('&raquo;'),
						'total' => ceil($total / $places_per_page),
						'current' => $apage
					));
          ?>
<div class='wrap'>
	<div class="icon32" style="background: url('<?php echo SAM_IMG_URL.'sam-list.png' ?>') no-repeat transparent; "><br/></div>
	<h2><?php _e('Managing Ads Places', SAM_DOMAIN); ?></h2>
	<ul class="subsubsub">
		<li><a <?php if($mode === 'all') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=places&mode=all"><?php _e('All', SAM_DOMAIN); ?></a> (<?php echo $all_num; ?>) | </li>
		<li><a <?php if($mode === 'active') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=places&mode=active"><?php _e('Active', SAM_DOMAIN); ?></a> (<?php echo $active_num; ?>) | </li>
		<li><a <?php if($mode === 'trash') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=places&mode=trash"><?php _e('Trash', SAM_DOMAIN); ?></a> (<?php echo $trash_num; ?>)</li>
	</ul>
	<div class="tablenav">
		<div class="alignleft">
			<?php if($mode === 'trash') {?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=places&mode=trash&iaction=kill-em-all"><?php _e('Clear Trash', SAM_DOMAIN); ?></a>
      <?php } else { ?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-edit&action=new&mode=place"><?php _e('Add New Place', SAM_DOMAIN); ?></a>
      <?php } ?>
    </div>
		<div class="tablenav-pages">
			<?php $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s', SAM_DOMAIN ) . '</span>%s',
				number_format_i18n( $start + 1 ),
				number_format_i18n( min( $apage * $places_per_page, $total ) ),
				'<span class="total-type-count">' . number_format_i18n( $total ) . '</span>',
				$page_links
			); echo $page_links_text; ?>
		</div>
	</div>
	<div class="clear"></div>
	<table class="widefat fixed" cellpadding="0">
		<thead>
			<tr>
				<th id="t-idg" class="manage-column column-title" style="width:5%;" scope="col"><?php _e('ID', SAM_DOMAIN); ?></th>
				<th id="t-name" class="manage-column column-title" style="width:55%;" scope="col"><?php _e('Place Name', SAM_DOMAIN);?></th>
        <th id="t-size" class="manage-column column-title" style="width:20%;" scope="col"><?php _e('Size', SAM_DOMAIN); ?></th>
        <th id="tp-items" class="manage-column column-title" style="width:10%;" scope="col"><div class="vers"><?php _e('Total Ads', SAM_DOMAIN); ?></div></th>				
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th id="b-idg" class="manage-column column-title" style="width:5%;" scope="col"><?php _e('ID', SAM_DOMAIN); ?></th>
				<th id="b-name" class="manage-column column-title" style="width:55%;" scope="col"><?php _e('Place Name', SAM_DOMAIN);?></th>
				<th id="b-size" class="manage-column column-title" style="width:20%;" scope="col"><?php _e('Size', SAM_DOMAIN); ?></th>
				<th id="bp-items" class="manage-column column-title" style="width:10%;" scope="col"><div class="vers"><?php _e('Total Ads', SAM_DOMAIN); ?></div></th>
			</tr>
		</tfoot>
		<tbody>
				<?php
					$places = $wpdb->get_results("SELECT id, name, description, place_size, place_custom_width, place_custom_height, trash, (SELECT COUNT(*) FROM wp_sam_ads WHERE ".$aTable.".pid = ".$pTable.".id) AS items FROM ".$pTable.(($mode !== 'all') ? ' WHERE trash = '.(($mode === 'trash') ? 'TRUE' : 'FALSE') : '').' LIMIT '.$offset.', '.$places_per_page, ARRAY_A);          
					$i = 0;
					if(!is_array($places) || empty ($places)) {
				?>
			<tr id="g0" class="alternate author-self status-publish iedit" valign="top">
				<th class="post-title column-title">0</th>
        <th class="author column-author"><?php _e('There are no data ...', SAM_DOMAIN).$pTable; ?></th>
			</tr>
				<?php } else {
					foreach($places as $row) {
						//$aItems = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$aTable.' WHERE (trash = FALSE) AND (pid = '.$row['id'].')'));
            $apSize = $this->getAdSize($row['place_size'], $row['place_custom_width'], $row['place_custom_height']);
				?>
			<tr id="<?php echo $row['id'];?>" class="<?php echo (($i & 1) ? 'alternate' : ''); ?> author-self status-publish iedit" valign="top">
				<th class="post-title column-title"><?php echo $row['id']; ?></th>
				<td class="post-title column-title">
					<strong style='display: inline;'><a href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=active&item=<?php echo $row['id']; ?>"><?php echo $row['name'];?></a><?php echo ((($row['trash'] == true) && ($mode === 'all')) ? '<span class="post-state"> - '.__('in Trash', SAM_DOMAIN).'</span>' : ''); ?></strong><br/><?php echo $row['description'];?>
					<div class="row-actions">
						<span class="edit"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-edit&action=edit&mode=place&item=<?php echo $row['id'] ?>" title="<?php _e('Edit Place', SAM_DOMAIN) ?>"><?php _e('Edit', SAM_DOMAIN); ?></a> | </span>
						<?php 
            if($row['trash'] == true) { 
              ?>
              <span class="untrash"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=places&mode=<?php echo $mode ?>&iaction=untrash&item=<?php echo $row['id'] ?>" title="<?php _e('Restore this Place from the Trash', SAM_DOMAIN) ?>"><?php _e('Restore', SAM_DOMAIN); ?></a> | </span>
              <span class="delete"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=places&mode=<?php echo $mode ?>&iaction=kill&item=<?php echo $row['id'] ?>" title="<?php _e('Remove this Place permanently', SAM_DOMAIN) ?>"><?php _e('Remove permanently', SAM_DOMAIN); ?></a></span>
						<?php 
            } 
            else { 
              ?>
              <span class="delete"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=places&mode=<?php echo $mode ?>&iaction=delete&item=<?php echo $row['id'] ?>" title="<?php _e('Move this Place to the Trash', SAM_DOMAIN) ?>"><?php _e('Delete', SAM_DOMAIN); ?></a> | </span>
						  <span class="edit"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=active&item=<?php echo $row['id']; ?>" title="<?php _e('View List of Place Ads', SAM_DOMAIN) ?>"><?php _e('View Ads', SAM_DOMAIN); ?></a> | </span>
              <span class="edit"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-edit&action=new&mode=item&place=<?php echo $row['id']; ?>" title="<?php _e('Create New Ad', SAM_DOMAIN) ?>"><?php _e('New Ad', SAM_DOMAIN); ?></a></span>
            <?php } ?>
					</div>
				</td>
				<td class="post-title column-title"><?php echo $apSize['name']; ?></td>
				<td class="post-title column-title"><div class="post-com-count-wrapper" style="text-align: center;"><?php echo $row['items'];?></div></td>
			</tr>
				<?php $i++; }}?>
		</tbody>
	</table>
	<div class="tablenav">
		<div class="alignleft">
			<?php if($mode === 'trash') {?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=places&mode=trash&iaction=kill-em-all"><?php _e('Clear Trash', SAM_DOMAIN); ?></a>
      <?php } else { ?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-edit&action=new&mode=place"><?php _e('Add New Place', SAM_DOMAIN); ?></a>
      <?php } ?>
		</div>
		<div class="tablenav-pages">
			<?php $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s', SAM_DOMAIN ) . '</span>%s',
				number_format_i18n( $start + 1 ),
				number_format_i18n( min( $apage * $places_per_page, $total ) ),
				'<span class="total-type-count">' . number_format_i18n( $total ) . '</span>',
				$page_links
			); echo $page_links_text; ?>
		</div>
	</div>
</div>
          <?php
          break;

        case 'items':
          if(!is_null($item)) {
						if($iaction === 'delete') $wpdb->update( $aTable, array( 'trash' => true ), array( 'id' => $iitem ), array( '%d' ), array( '%d' ) );
						elseif($iaction === 'untrash') $wpdb->update( $aTable, array( 'trash' => false ), array( 'id' => $iitem ), array( '%d' ), array( '%d' ) );
            elseif($iaction === 'kill') $wpdb->query("DELETE FROM {$aTable} WHERE id={$iitem}");
					}
          if($iaction === 'kill-em-all') $wpdb->query("DELETE FROM {$aTable} WHERE trash=true");
					$trash_num = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$aTable.' WHERE (trash = TRUE) AND (pid = '.$item.')'));
					$active_num = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$aTable.' WHERE (trash = FALSE) AND (pid = '.$item.')'));
					if(is_null($active_num)) $active_num = 0;
					if(is_null($trash_num)) $trash_num = 0;
					$all_num = $trash_num + $active_num;
					$places = $wpdb->get_row("SELECT id, name, trash FROM ".$pTable." WHERE id = ".$item, ARRAY_A);

					$total = (($mode !== 'all') ? (($mode === 'trash') ? $trash_num : $active_num) : $all_num);
					$start = $offset = ( $apage - 1 ) * $items_per_page;

					$page_links = paginate_links( array(
						'base' => add_query_arg( 'apage', '%#%' ),
						'format' => '',
						'prev_text' => __('&laquo;'),
						'next_text' => __('&raquo;'),
						'total' => ceil($total / $items_per_page),
						'current' => $apage
					));
          ?>
<div class="wrap">
	<div class="icon32" style="background: url('<?php echo SAM_IMG_URL.'sam-list.png'; ?>') no-repeat transparent; "><br/></div>
	<h2><?php echo __('Managing Items of Ads Place', SAM_DOMAIN).' "'.$places['name'].'" ('.$item.') '; ?></h2>
	<ul class="subsubsub">
		<li><a <?php if($mode === 'all') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=all&item=<?php echo $item ?>"><?php _e('All', SAM_DOMAIN); ?></a> (<?php echo $all_num; ?>) | </li>
		<li><a <?php if($mode === 'active') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=active&item=<?php echo $item ?>"><?php _e('Active', SAM_DOMAIN); ?></a> (<?php echo $active_num; ?>) | </li>
		<li><a <?php if($mode === 'trash') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=trash&item=<?php echo $item ?>"><?php _e('Trash', SAM_DOMAIN); ?></a> (<?php echo $trash_num; ?>)</li>
	</ul>
	<div class="tablenav">
		<div class="alignleft">
      <?php 
      if($mode === 'trash') { ?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=trash&iaction=kill-em-all&item=<?php echo $item ?>"><?php _e('Clear Trash', SAM_DOMAIN); ?></a>
      <?php } else { ?>
			<a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-edit&action=new&mode=item&place=<?php echo $places['id']; ?>"><?php _e('Add New Ad', SAM_DOMAIN); ?></a>
      <?php } ?>
		</div>
		<div class="alignleft">
			<a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-list"><?php _e('Back to Ads Places Management', SAM_DOMAIN); ?></a>
		</div>
		<div class="tablenav-pages">
			<?php 
      $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s', SAM_DOMAIN ) . '</span>%s',
				number_format_i18n( $start + 1 ),
				number_format_i18n( min( $apage * $items_per_page, $total ) ),
				'<span class="total-type-count">' . number_format_i18n( $total ) . '</span>',
				$page_links
			); 
      echo $page_links_text; 
      ?>
		</div>
	</div>
	<div class="clear"></div>
	<table class="widefat fixed" cellpadding="0">
		<thead>
			<tr>
				<th id="t-id" class="manage-column column-title" style="width:5%;" scope="col"><?php _e('ID', SAM_DOMAIN); ?></th>
				<th id="t-ad" class='manage-column column-title' style="width:65%;" scope="col"><?php _e('Advertisment', SAM_DOMAIN); ?></th>
				<th id="t-act" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Activity', SAM_DOMAIN);?></th>
				<th id="t-hits" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Hits', SAM_DOMAIN);?></th>
				<th id="t-clicks" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Clicks', SAM_DOMAIN);?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th id="b-id" class="manage-column column-title" style="width:5%;" scope="col"><?php _e('ID', SAM_DOMAIN); ?></th>
				<th id="b-ad" class='manage-column column-title' style="width:65%;" scope="col"><?php _e('Advertisment', SAM_DOMAIN); ?></th>
				<th id="b-act" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Activity', SAM_DOMAIN);?></th>
				<th id="b-hits" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Hits', SAM_DOMAIN);?></th>
				<th id="b-clicks" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Clicks', SAM_DOMAIN);?></th>
			</tr>
		</tfoot>
		<tbody>
				<?php
					if($mode !== 'all')
						$items = $wpdb->get_results("SELECT id, pid, name, description, ad_hits, ad_clicks, ad_weight, trash FROM ".$aTable.' WHERE (pid = '.$item.') AND (trash = '.(($mode === 'trash') ? 'TRUE' : 'FALSE').')'.' LIMIT '.$offset.', '.$items_per_page, ARRAY_A);
					else
						$items = $wpdb->get_results("SELECT id, pid, name, description, ad_hits, ad_clicks, ad_weight, trash FROM ".$aTable." WHERE pid = ".$item.' LIMIT '.$offset.', '.$items_per_page, ARRAY_A);
					$i = 0;
					if(!is_array($items) || empty($items)) {
				?>
			<tr id="g0" class="alternate author-self status-publish iedit" valign="top">
				<th class="post-title column-title">0</th>
        <th class="author column-author"><?php _e('There are no data ...', SAM_DOMAIN); ?></th>
			</tr>
				<?php 
          } 
          else {
					  foreach($items as $row) {
						  if($row['ad_weight'] > 0) $activity = __('Yes', SAM_DOMAIN);
              else $activity = __('No', SAM_DOMAIN);
				?>
			<tr id="<?php echo $row['id'];?>" class="<?php echo (($i & 1) ? 'alternate' : ''); ?> author-self status-publish iedit" valign="top">
				<th class="post-title column-title"><?php echo $row['id']; ?></th>
				<td class="column-icon column-title">
					<strong><a href="<?php echo admin_url('admin.php'); ?>?page=sam-edit&action=edit&mode=item&item=<?php echo $row['id']; ?>"><?php echo $row['name'];?></a><?php echo ((($row['trash'] == true) && ($mode === 'all')) ? '<span class="post-state"> - '.__('in Trash', SAM_DOMAIN).'</span>' : ''); ?></strong><br/><?php echo $row['description'];?>
					<div class="row-actions">
						<span class="edit"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-edit&action=edit&mode=item&item=<?php echo $row['id'] ?>" title="<?php _e('Edit this Item of Ads Place', SAM_DOMAIN) ?>"><?php _e('Edit', SAM_DOMAIN); ?></a> | </span>
						<?php 
            if($row['trash'] == true) { 
              ?>
              <span class="untrash"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=<?php echo $mode ?>&iaction=untrash&item=<?php echo $row['pid'] ?>&iitem=<?php echo $row['id'] ?>" title="<?php _e('Restore this Ad from the Trash', SAM_DOMAIN) ?>"><?php _e('Restore', SAM_DOMAIN); ?></a> | </span>
              <span class="delete"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=<?php echo $mode ?>&iaction=kill&item=<?php echo $row['pid'] ?>&iitem=<?php echo $row['id'] ?>" title="<?php _e('Remove this Ad permanently', SAM_DOMAIN) ?>"><?php _e('Remove permanently', SAM_DOMAIN); ?></a> </span>
						<?php } else { ?><span class="delete"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=<?php echo $mode ?>&iaction=delete&item=<?php echo $row['pid'] ?>&iitem=<?php echo $row['id'] ?>" title="<?php _e('Move this item to the Trash', SAM_DOMAIN) ?>"><?php _e('Delete', SAM_DOMAIN); ?></a> </span><?php } ?>
					</div>
				</td>
        <td class="post-title column-title"><?php echo $activity; ?></td>
				<td class="post-title column-title"><?php echo $row['ad_hits'];?></td>
				<td class="post-title column-title"><?php echo $row['ad_clicks'];?></td>
			</tr>
				<?php $i++; }}?>
		</tbody>
	</table>
	<div class="tablenav">
		<div class="alignleft">
      <?php 
      if($mode === 'trash') { ?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=trash&iaction=kill-em-all&item=<?php echo $item ?>"><?php _e('Clear Trash', SAM_DOMAIN); ?></a>
      <?php } else { ?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-edit&action=new&mode=item&place=<?php echo $places['id']; ?>"><?php _e('Add New Ad', SAM_DOMAIN); ?></a>
      <?php } ?>
    </div>
		<div class="alignleft">
			<a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-list"><?php _e('Back to Ads Places Management', SAM_DOMAIN); ?></a>
		</div>
		<div class="tablenav-pages">
			<?php $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s', SAM_DOMAIN ) . '</span>%s',
				number_format_i18n( $start + 1 ),
				number_format_i18n( min( $apage * $items_per_page, $total ) ),
				'<span class="total-type-count">' . number_format_i18n( $total ) . '</span>',
				$page_links
			); echo $page_links_text; ?>
		</div>
	</div>
</div>
          <?php
          break;
      }
		}
		
		function samEditPage() {
			global $wpdb;
			$pTable = $wpdb->prefix . "sam_places";					
			$aTable = $wpdb->prefix . "sam_ads";
			
			$options = parent::getSettings();
			
			if(isset($_GET['action'])) $action = $_GET['action'];
			else $action = 'new';
			if(isset($_GET['mode'])) $mode = $_GET['mode'];
			else $mode = 'place';
			if(isset($_GET['item'])) $item = $_GET['item'];
			else $item = null;
			if(isset($_GET['place'])) $place = $_GET['place'];
			else $place = null;
			
			switch($mode) {
				case 'place':
					$updated = false;
					
					if(isset($_POST['update_place'])) {
						$placeId = $_POST['place_id'];
						$updateRow = array(
							'name' => $_POST['place_name'],
							'description' => $_POST['description'],
							'code_before' => $_POST['code_before'],
							'code_after' => $_POST['code_after'],
              'place_size' => $_POST['place_size'],
							'place_custom_width' => $_POST['place_custom_width'],
							'place_custom_height' => $_POST['place_custom_height'],
							'patch_img' => $_POST['patch_img'],
							'patch_link' => $_POST['patch_link'],
							'patch_code' => stripslashes($_POST['patch_code']),
							'patch_source' => $_POST['patch_source'],
							'trash' => ($_POST['trash'] === 'true')
						);
						$formatRow = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d');
						if($placeId === __('Undefined', SAM_DOMAIN)) {
							$wpdb->insert($pTable, $updateRow);
							$updated = true;
							$item = $wpdb->insert_id;
						}
						else {
							if(is_null($item)) $item = $placeId;
							$wpdb->update($pTable, $updateRow, array( 'id' => $item ), $formatRow, array( '%d' ));
							$updated = true;
						}
						?>
<div class="updated"><p><strong><?php _e("Ads Place Data Updated.", SAM_DOMAIN);?></strong></p></div>
						<?php
					}

          $aSize = array();
					
					if($action !== 'new') {
						$row = $wpdb->get_row("SELECT id, name, description, code_before, code_after, place_size, place_custom_width, place_custom_height, patch_img, patch_link, patch_code, patch_source, trash FROM ".$pTable." WHERE id = ".$item, ARRAY_A);
            if($row['place_size'] === 'custom') $aSize = $this->getAdSize($row['place_size'], $row['place_custom_width'], $row['place_custom_height']);
            else $aSize = $this->getAdSize ($row['place_size']);
					}
					else {
						if($updated) {
							$row = $wpdb->get_row("SELECT id, name, description, code_before, code_after, place_size, place_custom_width, place_custom_height, patch_img, patch_link, patch_code, patch_source, trash FROM ".$pTable." WHERE id = ".$item, ARRAY_A);
              if($row['place_size'] === 'custom') $aSize = $this->getAdSize($row['place_size'], $row['place_custom_width'], $row['place_custom_height']);
              else $aSize = $this->getAdSize($row['place_size']);
						}
						else {
              $row = array(
								'id' => __('Undefined', SAM_DOMAIN),
								'name' => '',
								'description' => '',
								'code_before' => '',
								'code_after' => '',
                'place_size' => '468x60',
								'place_custom_width' => '',
								'place_custom_height' => '',
								'patch_img' => '',
								'patch_link' => '',
								'patch_code' => '',
								'patch_source' => 0,
								'trash' => false
							);
              $aSize = array(
                'name' => __('Undefined', SAM_DOMAIN),
                'width' => __('Undefined', SAM_DOMAIN),
                'height' => __('Undefined', SAM_DOMAIN)
              );
            }
					}
					?>
<div class="wrap">
	<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
		<div class="icon32" style="background: url('<?php echo SAM_IMG_URL.'sam-editor.png'; ?>') no-repeat transparent; "><br/></div>
		<h2><?php echo ( ( ($action === 'new') && ( $row['id'] === __('Undefined', SAM_DOMAIN) ) ) ? __('New Ads Place', SAM_DOMAIN) : __('Edit Ads Place', SAM_DOMAIN).' ('.$item.')' ); ?></h2>
		<div class="metabox-holder has-right-sidebar" id="poststuff">
			<div id="side-info-column" class="inner-sidebar">
				<div class="meta-box-sortables ui-sortable">
					<div id="submitdiv" class="postbox ">
						<div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
						<h3 class="hndle"><span><?php _e('Status', SAM_DOMAIN);?></span></h3>
						<div class="inside">
							<div id="submitpost" class="submitbox">
								<div id="minor-publishing">
									<div id="minor-publishing-actions">
										<div id="save-action"> </div>
										<div id="preview-action">
											<a id="post-preview" class="preview button" href='<?php echo admin_url('admin.php'); ?>?page=sam-list'><?php _e('Back to Places List', SAM_DOMAIN) ?></a>
										</div>
										<div class="clear"></div>
									</div>
									<div id="misc-publishing-actions">
										<div class="misc-pub-section">
											<label for="place_id_stat"><?php echo __('Ads Place ID', SAM_DOMAIN).':'; ?></label>
											<span id="place_id_stat" class="post-status-display"><?php echo $row['id']; ?></span>
											<input type="hidden" id="place_id" name="place_id" value="<?php echo $row['id']; ?>" />
                      <input type='hidden' name='editor_mode' id='editor_mode' value='place'>
										</div>
                    <div class="misc-pub-section">
											<label for="place_size_info"><?php echo __('Size', SAM_DOMAIN).':'; ?></label>
                      <span id="place_size_info" class="post-status-display"><?php echo $aSize['name']; ?></span><br/>
                      <label for="place_width"><?php echo __('Width', SAM_DOMAIN).':'; ?></label>
                      <span id="place_width" class="post-status-display"><?php echo $aSize['width']; ?></span><br/>
                      <label for="place_height"><?php echo __('Height', SAM_DOMAIN).':'; ?></label>
											<span id="place_height" class="post-status-display"><?php echo $aSize['height']; ?></span>
										</div>
										<div class="misc-pub-section">
											<label for="trash_no"><input type="radio" id="trash_no" value="false" name="trash" <?php if (!$row['trash']) { echo 'checked="checked"'; }?> >  <?php _e('Is Active', SAM_DOMAIN); ?></label><br/>
											<label for="trash_yes"><input type="radio" id="trash_yes" value="true" name="trash" <?php if ($row['trash']) { echo 'checked="checked"'; }?> >  <?php _e('Is In Trash', SAM_DOMAIN); ?></label>
										</div>
									</div>
									<div class="clear"></div>
								</div>
								<div id="major-publishing-actions">
									<div id="delete-action">
										<a class="submitdelete deletion" href='<?php echo admin_url('admin.php'); ?>?page=sam-list'><?php _e('Cancel', SAM_DOMAIN) ?></a>
									</div>
									<div id="publishing-action">
										<input type="submit" class='button-primary' name="update_place" value="<?php _e('Save', SAM_DOMAIN) ?>" />
									</div>
									<div class="clear"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div id="post-body">
				<div id="post-body-content">
					<div id="titlediv">
						<div id="titlewrap">
							<label class="screen-reader-text" for="title"><?php _e('Name', SAM_DOMAIN); ?></label>
							<input id="title" type="text" autocomplete="off" tabindex="1" size="30" name="place_name" value="<?php echo $row['name']; ?>" />
						</div>
					</div>
					<div class="meta-box-sortables ui-sortable">
						<div id="descdiv" class="postbox ">
							<div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
							<h3 class="hndle"><span><?php _e('Description', SAM_DOMAIN);?></span></h3>
							<div class="inside">
								<p><?php _e('Enter description of this Ads Place.', SAM_DOMAIN);?></p>
								<p>
									<label for="description"><?php echo __('Description', SAM_DOMAIN).':'; ?></label>
									<textarea id="description" class="code" tabindex="2" name="description" style="width:100%" ><?php echo $row['description']; ?></textarea>
								</p>
								<p><?php _e('This description is not used anywhere and is added solely for the convenience of managing advertisements.', SAM_DOMAIN); ?></p>
							</div>
						</div>
					</div>
          <div class="meta-box-sortables ui-sortable">
						<div id="sizediv" class="postbox ">
							<div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
							<h3 class="hndle"><span><?php _e('Ads Place Size', SAM_DOMAIN);?></span></h3>
							<div class="inside">
								<p><?php _e('Select size of this Ads Place.', SAM_DOMAIN);?></p>
								<p>
									<?php $this->adSizes($row['place_size']); ?>
								</p>
								<p><?php _e('You can enter any HTML codes here for the further withdrawal of their before and after the code of Ads Place.', SAM_DOMAIN); ?></p>
                <p>
                  <label for="place_custom_width"><?php echo __('Custom Width', SAM_DOMAIN).':'; ?></label>
									<input id="place_custom_width" type="text" tabindex="3" name="place_custom_width" value="<?php echo $row['place_custom_width']; ?>" style="width:20%" />
                </p>
                <p>
                  <label for="place_custom_height"><?php echo __('Custom Height', SAM_DOMAIN).':'; ?></label>
									<input id="place_custom_height" type="text" tabindex="3" name="place_custom_height" value="<?php echo $row['place_custom_height']; ?>" style="width:20%" />
                </p>
                <p><?php _e('These values are not used and are added solely for the convenience of advertising management. Will be used in the future...', SAM_DOMAIN); ?></p>
							</div>
						</div>
					</div>
					<div class="meta-box-sortables ui-sortable">
						<div id="srcdiv" class="postbox ">
							<div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
							<h3 class="hndle"><span><?php _e('Ads Place Patch', SAM_DOMAIN);?></span></h3>
							<div class="inside">
								<p><?php _e('Select type of the code of a patch and fill data entry fields with the appropriate data.', SAM_DOMAIN);?></p>
								<p>
									<label for="patch_source_image"><input type="radio" id="patch_source_image" name="patch_source" value="0" <?php if($row['patch_source'] == '0') { echo 'checked="checked"'; } ?> />&nbsp;<?php _e('Image', SAM_DOMAIN); ?></label>&nbsp;&nbsp;&nbsp;&nbsp;
								</p>
                <div class='radio-content'>
								  <p>
									  <label for="patch_img"><?php echo __('Image', SAM_DOMAIN).':'; ?></label>
									  <input id="patch_img" class="code" type="text" tabindex="3" name="patch_img" value="<?php echo htmlspecialchars(stripslashes($row['patch_img'])); ?>" style="width:100%" />
								  </p>
								  <p>
									  <?php _e('This image is a patch for advertising space. This may be an image with the text "Place your ad here".', SAM_DOMAIN); ?>
								  </p>
								  <p>
									  <label for="patch_link"><?php echo __('Target', SAM_DOMAIN).':'; ?></label>
									  <input id="patch_link" class="code" type="text" tabindex="4" name="patch_link" value="<?php echo htmlspecialchars(stripslashes($row['patch_link'])); ?>" style="width:100%" />
								  </p>
								  <p>
									  <?php _e('This is a link to a page where are your suggestions for advertisers.', SAM_DOMAIN); ?>
								  </p>
                  <div id="source_tools" >
                    <p><strong><?php _e('Image Tools', SAM_DOMAIN); ?></strong></p>
                    <p>
                      <label for="files_list"><strong><?php echo (__('Select File', SAM_DOMAIN).':'); ?></strong></label>
                      <select id="files_list" name="files_list" size="1"  dir="ltr" style="width: auto;">
                        <?php $this->getFilesList(SAM_AD_IMG); ?>
                      </select>&nbsp;&nbsp;
                      <input id="add-file-button" type="button" class="button-secondary" value="<?php _e('Apply', SAM_DOMAIN);?>" />  <br/>  
                      <?php _e("Select file from your blog server.", SAM_DOMAIN); ?>                
                    </p>
                    <p>
                      <label for="upload-file-button"><strong><?php echo (__('Upload File', SAM_DOMAIN).':'); ?></strong></label>
                      <input id="upload-file-button" type="button" class="button-secondary" name="upload_media" value="<?php _e('Upload', SAM_DOMAIN);?>" />
                      <img id='load_img' src='<?php echo SAM_IMG_URL ?>loader.gif' style='display: none;'>
                      <span id="uploading"></span><br/>
                      <span id="uploading-help"><?php _e("Select and upload file from your local computer.", SAM_DOMAIN); ?></span>
                    </p>
                  </div>
                </div>
                <div class='clear-line'></div>
								<p>
									<label for="patch_source_code"><input type="radio" id="patch_source_code" name="patch_source" value="1" <?php if($row['patch_source'] == '1') { echo 'checked="checked"'; } ?> />&nbsp;<?php _e('HTML or Javascript Code', SAM_DOMAIN); ?></label>&nbsp;&nbsp;&nbsp;&nbsp;
								</p>
                <div class='radio-content'>
								  <p>
									  <label for="patch_code"><?php echo __('Patch Code', SAM_DOMAIN).':'; ?></label>
									  <textarea id="patch_code" class="code" rows='10' name="patch_code" style="width:100%" ><?php echo $row['patch_code']; ?></textarea>
								  </p>
								  <p>
									  <?php _e('This is a HTML-code patch of advertising space. For example: use the code to display AdSense advertisment.', SAM_DOMAIN); ?>
								  </p>
                </div>
								<p><?php _e('The patch shows that if the logic of the plugin can not show any advertisements on the current page of the document.', SAM_DOMAIN); ?></p>
							</div>
						</div>
					</div>
					<div class="meta-box-sortables ui-sortable">
						<div id="codediv" class="postbox ">
							<div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
							<h3 class="hndle"><span><?php _e('Codes', SAM_DOMAIN);?></span></h3>
							<div class="inside">
								<p><?php _e('Enter the code to output before and after the codes of Ads Place.', SAM_DOMAIN);?></p>
								<p>
									<label for="code_before"><?php echo __('Code Before', SAM_DOMAIN).':'; ?></label>
									<input id="code_before" class="code" type="text" tabindex="2" name="code_before" value="<?php echo htmlspecialchars(stripslashes($row['code_before'])); ?>" style="width:100%" />
								</p>
								<p>
									<label for="code_after"><?php echo __('Code After', SAM_DOMAIN).':'; ?></label>
									<input id="code_after" class="code" type="text" tabindex="3" name="code_after" value="<?php echo htmlspecialchars(stripslashes($row['code_after'])); ?>" style="width:100%" />
								</p>
								<p><?php _e('You can enter any HTML codes here for the further withdrawal of their before and after the code of Ads Place.', SAM_DOMAIN); ?></p>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>
					<?php
					break;
          
        case 'item':
          $aSize = array();
          
          if(isset($_POST['update_item'])) {
            $itemId = $_POST['item_id'];
            $placeId = $_POST['place_id'];
            $viewPages = $this->buildViewPages(array(
              $_POST['is_home'],
              $_POST['is_singular'],
              $_POST['is_single'],
              $_POST['is_page'],
              $_POST['is_attachment'],
              $_POST['is_search'],
              $_POST['is_404'],
              $_POST['is_archive'],
              $_POST['is_tax'],
              $_POST['is_category'],
              $_POST['is_tag'],
              $_POST['is_author'],
              $_POST['is_date']
            ));
            $updateRow = array(
              'pid' => $_POST['place_id'],
              'name' => $_POST['item_name'],
              'description' => $_POST['item_description'],
              'code_type' => $_POST['code_type'],
              'code_mode' => $_POST['code_mode'],
              'ad_code' => stripcslashes($_POST['ad_code']),
              'ad_img' => $_POST['ad_img'],
              'ad_target' => $_POST['ad_target'],
              'count_clicks' => $_POST['count_clicks'],
              'view_type' => $_POST['view_type'],
              'view_pages' => $viewPages,
              'view_id' => $_POST['view_id'],
              'view_cats' => $this->removeTrailingComma( $_POST['view_cats'] ),
              'ad_start_date' => $_POST['ad_start_date'],
              'ad_end_date' => $_POST['ad_end_date'],              
              'ad_schedule' => $_POST['ad_schedule'],
              'ad_weight' => $_POST['ad_weight'],
              'trash' => ($_POST['trash'] === 'true')
            );
            $formatRow = array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d');
            if($itemId === __('Undefined', SAM_DOMAIN)) {
              $wpdb->insert($aTable, $updateRow);
              $item = $wpdb->insert_id;
            }
            else {
              if(is_null($item)) $item = $itemId;
              $wpdb->update($aTable, $updateRow, array( 'id' => $item ), $formatRow, array( '%d' ));
            }
            $wpdb->query("UPDATE {$aTable} SET {$aTable}.ad_weight_hits = 0 WHERE {$aTable}.pid = {$placeId}");
            $action = 'edit';
            ?>
<div class="updated"><p><strong><?php _e("Ad Data Updated.", SAM_DOMAIN);?></strong></p></div>
            <?php
          }
          
          if($action !== 'new') {
            $row = $wpdb->get_row(
              "SELECT id, 
                      pid, 
                      name, 
                      description, 
                      code_type, 
                      code_mode, 
                      ad_code, 
                      ad_img, 
                      ad_target,
                      count_clicks, 
                      (SELECT place_size FROM ".$pTable." WHERE ".$pTable.".id = ".$aTable.".pid) AS ad_size,
                      (SELECT place_custom_width FROM ".$pTable." WHERE ".$pTable.".id = ".$aTable.".pid) AS ad_custom_width,
                      (SELECT place_custom_height FROM ".$pTable." WHERE ".$pTable.".id = ".$aTable.".pid) AS ad_custom_height, 
                      view_type, 
                      (view_pages+0) AS view_pages, 
                      view_id,
                      view_cats, 
                      ad_start_date, 
                      ad_end_date, 
                      ad_schedule, 
                      ad_hits, 
                      ad_clicks, 
                      ad_weight, 
                      ad_weight_hits, 
                      trash 
                  FROM ".$aTable." WHERE id = ".$item, 
              ARRAY_A);
              
            if($row['ad_size'] === 'custom') $aSize = $this->getAdSize($row['ad_size'], $row['ad_custom_width'], $row['ad_custom_height']);
            else $aSize = $this->getAdSize ($row['ad_size']);  
          }
          else {
            $row = array(
              'id' => __('Undefined', SAM_DOMAIN),
              'pid' => $place,
              'name' => '',
              'description' => '',
              'code_type' => 0,
              'code_mode' => 1,
              'ad_code' => '',
              'ad_img' => '',
              'ad_target' => '',
              'count_clicks' => 0,
              'view_type' => 1,
              'view_pages' => 0,
              'view_id' => '',
              'view_cats' => '',
              'ad_start_date' => '',
              'ad_end_date' => '',              
              'ad_schedule' => 0,
              'ad_hits' => 0,
              'ad_clicks' => 0,
              'ad_weight' => 10,
              'ad_weight_hits' => 0,
              'trash' => 0
            );
            $aSize = array(
                'name' => __('Undefined', SAM_DOMAIN),
                'width' => __('Undefined', SAM_DOMAIN),
                'height' => __('Undefined', SAM_DOMAIN)
              );
          }
          ?>
<div class="wrap">
  <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
    <div class="icon32" style="background: url('<?php echo SAM_IMG_URL.'sam-editor.png'; ?>') no-repeat transparent; "><br/></div>
    <h2><?php echo ( ( $action === 'new' ) ? __('New Advertisment', SAM_DOMAIN) : __('Edit Advertisment', SAM_DOMAIN).' ('.$item.')' ); ?></h2>
    <div class="metabox-holder has-right-sidebar" id="poststuff">
      <div id="side-info-column" class="inner-sidebar">
        <div class="meta-box-sortables ui-sortable">
          <div id="submitdiv" class="postbox ">
            <div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
            <h3 class="hndle"><span><?php _e('Status', SAM_DOMAIN);?></span></h3>
            <div class="inside">
              <div id="submitpost" class="submitbox">
                <div id="minor-publishing">
                  <div id="minor-publishing-actions">
                    <div id="save-action"> </div>
                    <div id="preview-action">
                      <a id="post-preview" class="preview button" href='<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=active&item=<?php echo $row['pid'] ?>'><?php _e('Back to Ads List', SAM_DOMAIN) ?></a>
                    </div>
                    <div class="clear"></div>
                  </div>
                  <div id="misc-publishing-actions">
                    <div class="misc-pub-section">
                      <label for="item_id_info"><?php echo __('Advertisment ID', SAM_DOMAIN).':'; ?></label>
                      <span id="item_id_info" style="font-weight: bold;"><?php echo $row['id']; ?></span>
                      <input type="hidden" id="item_id" name="item_id" value="<?php echo $row['id']; ?>" />
                      <input type="hidden" id="place_id" name="place_id" value="<?php echo $row['pid']; ?>" />
                      <input type='hidden' name='editor_mode' id='editor_mode' value='item'>
                    </div>
                    <div class="misc-pub-section">
                      <label for="ad_weight_info"><?php echo __('Activity', SAM_DOMAIN).':'; ?></label>
                      <span id="ad_weight_info" style="font-weight: bold;"><?php echo (($row['ad_weight'] > 0) && !$row['trash']) ? __('Ad is Active', SAM_DOMAIN) : __('Ad is Inactive', SAM_DOMAIN); ?></span><br/>
                      <label for="ad_hits_info"><?php echo __('Hits', SAM_DOMAIN).':'; ?></label>
                      <span id="ad_hits_info" style="font-weight: bold;"><?php echo $row['ad_hits']; ?></span><br/>
                      <label for="ad_clicks_info"><?php echo __('Clicks', SAM_DOMAIN).':'; ?></label>
                      <span id="ad_clicks_info" style="font-weight: bold;"><?php echo $row['ad_clicks']; ?></span>
                    </div>
                    <div class="misc-pub-section">
                      <label for="place_size_info"><?php echo __('Size', SAM_DOMAIN).':'; ?></label>
                      <span id="ad_size_info" class="post-status-display"><strong><?php echo $aSize['name']; ?></strong></span><br/>
                      <label for="place_width"><?php echo __('Width', SAM_DOMAIN).':'; ?></label>
                      <span id="ad_width" class="post-status-display"><strong><?php echo $aSize['width']; ?></strong></span><br/>
                      <label for="place_height"><?php echo __('Height', SAM_DOMAIN).':'; ?></label>
                      <span id="ad_height" class="post-status-display"><strong><?php echo $aSize['height']; ?></strong></span>
                    </div>
                    <div class="misc-pub-section">
                      <input type="radio" id="trash_no" value="false" name="trash" <?php checked(0, $row['trash'], true); ?> />
                      <label for="trash_no">  <?php _e('Is in Rotation', SAM_DOMAIN); ?></label><br/>
                      <input type="radio" id="trash_yes" value="true" name="trash" <?php checked(1, $row['trash'], true); ?> />
                      <label for="trash_yes">  <?php _e('Is In Trash', SAM_DOMAIN); ?></label>
                    </div>
                  </div>
                  <div class="clear"></div>
                </div>
                <div id="major-publishing-actions">
                  <div id="delete-action">
                    <a class="submitdelete deletion" href='<?php echo admin_url('admin.php'); ?>?page=sam-list&action=items&mode=active&item=<?php echo $row['pid'] ?>'><?php _e('Cancel', SAM_DOMAIN) ?></a>
                  </div>
                  <div id="publishing-action">
                    <input type="submit" class='button-primary' name="update_item" value="<?php _e('Save', SAM_DOMAIN) ?>" />
                  </div>
                  <div class="clear"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div id="post-body">
        <div id="post-body-content">
          <div id="titlediv">
            <div id="titlewrap">
              <label class="screen-reader-text" for="title"><?php _e('Title', SAM_DOMAIN); ?></label>
              <input id="title" type="text" autocomplete="off" tabindex="1" size="30" name="item_name" value="<?php echo $row['name']; ?>" />
            </div>
          </div>
          <div id="normal-sortables" class="meta-box-sortables ui-sortable">
            <div id="codediv" class="postbox ">
              <div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
              <h3 class="hndle"><span><?php _e('Advertisment Description', SAM_DOMAIN);?></span></h3>
              <div class="inside">
                <p>
                  <label for="item_description"><strong><?php echo __('Description', SAM_DOMAIN).':' ?></strong></label>
                  <textarea rows='3' id="item_description" class="code" tabindex="2" name="item_description" style="width:100%" ><?php echo $row['description']; ?></textarea>
                </p>
                <p>
                  <?php _e('This description is not used anywhere and is added solely for the convenience of managing advertisements.', SAM_DOMAIN); ?>
                </p>
              </div>
            </div>
          </div>
          <div id="sources" class="meta-box-sortables ui-sortable">
            <div id="codediv" class="postbox ">
              <div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
              <h3 class="hndle"><span><?php _e('Ad Code', SAM_DOMAIN);?></span></h3>
              <div class="inside">
                <p>
                  <input type='radio' name='code_mode' id='code_mode_false' value='0' <?php checked(0, $row['code_mode']) ?>>
                  <label for='code_mode_false'><strong><?php _e('Image Mode', SAM_DOMAIN); ?></strong></label>                  
                </p>
                <div class='radio-content'>
                  <p>
                    <label for="ad_img"><strong><?php echo __('Ad Image', SAM_DOMAIN).':' ?></strong></label>
                    <input id="ad_img" class="code" type="text" tabindex="3" name="ad_img" value="<?php echo $row['ad_img']; ?>" style="width:100%" />
                  </p>
                  <p>
                    <label for="ad_target"><strong><?php echo __('Ad Target', SAM_DOMAIN).':' ?></strong></label>
                    <input id="ad_target" class="code" type="text" tabindex="3" name="ad_target" value="<?php echo $row['ad_target']; ?>" style="width:100%" />
                  </p>
                  <p>
                    <input type='checkbox' name='count_clicks' id='count_clicks' value='1' <?php checked(1, $row['count_clicks']) ?>>
                    <label for='count_clicks'><?php _e('Count clicks for this advertisment', SAM_DOMAIN); ?></label>
                  </p>
                  <p><strong><?php _e('Use carefully!', SAM_DOMAIN) ?></strong> <?php _e("Do not use if the wp-admin folder is password protected. In this case the viewer will be prompted to enter a username and password during ajax request. It's not good.", SAM_DOMAIN) ?></p>
                  <div class="clear"></div>
                  <div id="source_tools" >
                    <p><strong><?php _e('Image Tools', SAM_DOMAIN); ?></strong></p>
                    <p>
                      <label for="files_list"><strong><?php echo (__('Select File', SAM_DOMAIN).':'); ?></strong></label>
                      <select id="files_list" name="files_list" size="1"  dir="ltr" style="width: auto;">
                        <?php $this->getFilesList(SAM_AD_IMG); ?>
                      </select>&nbsp;&nbsp;
                      <input id="add-file-button" type="button" class="button-secondary" value="<?php _e('Apply', SAM_DOMAIN);?>" />  <br/>  
                      <?php _e("Select file from your blog server.", SAM_DOMAIN); ?>                
                    </p>
                    <p>
                      <label for="upload-file-button"><strong><?php echo (__('Upload File', SAM_DOMAIN).':'); ?></strong></label>
                      <input id="upload-file-button" type="button" class="button-secondary" name="upload_media" value="<?php _e('Upload', SAM_DOMAIN);?>" />
                      <img id='load_img' src='<?php echo SAM_IMG_URL ?>loader.gif' style='display: none;'>
                      <span id="uploading"></span><br/>
                      <span id="uploading-help"><?php _e("Select and upload file from your local computer.", SAM_DOMAIN); ?></span>
                    </p>
                  </div>
                </div>                
                <div class='clear-line' ></div>
                <p>
                  <input type='radio' name='code_mode' id='code_mode_true' value='1' <?php checked(1, $row['code_mode']) ?>>
                  <label for='code_mode_true'><strong><?php _e('Code Mode', SAM_DOMAIN); ?></strong></label>
                </p>
                <div class='radio-content'>
                  <p>
                    <label for="ad_code"><strong><?php echo __('Ad Code', SAM_DOMAIN).':'; ?></strong></label>
                    <textarea name='ad_code' id='ad_code' rows='10' title='Ad Code' style='width: 100%;'><?php echo $row['ad_code'] ?></textarea>
                    <input type='checkbox' name='code_type' id='code_type' value='1' <?php checked(1, $row['code_type']); ?>><label for='code_type' style='vertical-align: middle;'> <?php _e('This code of ad contains PHP script', SAM_DOMAIN); ?></label>
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div id="contents" class="meta-box-sortables ui-sortable">
            <div id="codediv" class="postbox ">
              <div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
              <h3 class="hndle"><span><?php _e('Restrictions of advertisements showing', SAM_DOMAIN);?></span></h3>
              <div class="inside">
                <p>
                  <label for='ad_weight'><strong><?php echo __('Ad Weight', SAM_DOMAIN).':' ?></strong></label>
                  <select name='ad_weight' id='ad_weight'>
                    <?php
                    for($i=0; $i <= 10; $i++) {
                      ?>
                      <option value='<?php echo $i; ?>' <?php selected($i, $row['ad_weight']); ?>>
                        <?php 
                          if($i == 0) echo $i.' - '.__('Inactive', SAM_DOMAIN);
                          elseif($i == 1) echo $i.' - '.__('Minimal Activity', SAM_DOMAIN);
                          elseif($i == 10) echo $i.' - '.__('Maximal Activity', SAM_DOMAIN);
                          else echo $i; 
                        ?>
                      </option>
                      <?php
                    }
                    ?>
                  </select>
                </p>
                <p>
                  <?php _e('Ad weight - coefficient of frequency of show of the advertisement for one cycle of advertisements rotation.', SAM_DOMAIN); ?><br/>
                  <?php _e('0 - ad is inactive, 1 - minimal activity of this advertisment, 10 - maximal activity of this ad.', SAM_DOMAIN); ?>
                </p>
                <div class='clear-line'></div>
                <p>
                  <input type='radio' name='view_type' id='view_type_1' value='1' <?php checked(1, $row['view_type']); ?>>
                  <label for='view_type_1'><strong><?php _e('Show ad on all pages of blog', SAM_DOMAIN); ?></strong></label>
                </p>
                <p>
                  <input type='radio' name='view_type' id='view_type_0' value='0' <?php checked(0, $row['view_type']); ?>>
                  <label for='view_type_0'><strong><?php echo __('Show ad only on pages of this type', SAM_DOMAIN).':'; ?></strong></label>
                </p>
                <div class='radio-content'>
                  <input type='checkbox' name='is_home' id='is_home' value='<?php echo SAM_IS_HOME; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_HOME)); ?>>
                  <label for='is_home'><?php _e('Home Page (Home or Front Page)', SAM_DOMAIN); ?></label><br/>
                  <input type='checkbox' name='is_singular' id='is_singular' value='<?php echo SAM_IS_SINGULAR; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_SINGULAR)); ?>>
                  <label for='is_singular'><?php _e('Singular Pages', SAM_DOMAIN); ?></label><br/>
                  <div class='radio-content'>
                    <input type='checkbox' name='is_single' id='is_single' value='<?php echo SAM_IS_SINGLE; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_SINGLE)); ?>>
                    <label for='is_single'><?php _e('Single Post', SAM_DOMAIN); ?></label><br/>
                    <input type='checkbox' name='is_page' id='is_page' value='<?php echo SAM_IS_PAGE; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_PAGE)); ?>>
                    <label for='is_page'><?php _e('Page', SAM_DOMAIN); ?></label><br/>
                    <input type='checkbox' name='is_attachment' id='is_attachment' value='<?php echo SAM_IS_ATTACHMENT; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_ATTACHMENT)); ?>>
                    <label for='is_attachment'><?php _e('Attachment', SAM_DOMAIN); ?></label><br/>
                  </div>
                  <input type='checkbox' name='is_search' id='is_search' value='<?php echo SAM_IS_SEARCH; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_SEARCH)); ?>>
                  <label for='is_search'><?php _e('Search Page', SAM_DOMAIN); ?></label><br/>
                  <input type='checkbox' name='is_404' id='is_404' value='<?php echo SAM_IS_404; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_404)); ?>>
                  <label for='is_404'><?php _e('"Not found" Page (HTTP 404: Not Found)', SAM_DOMAIN); ?></label><br/>
                  <input type='checkbox' name='is_archive' id='is_archive' value='<?php echo SAM_IS_ARCHIVE; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_ARCHIVE)); ?>>
                  <label for='is_archive'><?php _e('Archive Pages', SAM_DOMAIN); ?></label><br/>
                  <div class='radio-content'>
                    <input type='checkbox' name='is_tax' id='is_tax' value='<?php echo SAM_IS_TAX; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_TAX)); ?>>
                    <label for='is_tax'><?php _e('Taxonomy Archive Pages', SAM_DOMAIN); ?></label><br/>                  
                    <input type='checkbox' name='is_category' id='is_category' value='<?php echo SAM_IS_CATEGORY; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_CATEGORY)); ?>>
                    <label for='is_category'><?php _e('Category Archive Pages', SAM_DOMAIN); ?></label><br/>                  
                    <input type='checkbox' name='is_tag' id='is_tag' value='<?php echo SAM_IS_TAG; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_TAG)); ?>>
                    <label for='is_tag'><?php _e('Tag Archive Pages', SAM_DOMAIN); ?></label><br/>                  
                    <input type='checkbox' name='is_author' id='is_author' value='<?php echo SAM_IS_AUTHOR; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_AUTHOR)); ?>>
                    <label for='is_author'><?php _e('Author Archive Pages', SAM_DOMAIN); ?></label><br/>                  
                    <input type='checkbox' name='is_date' id='is_date' value='<?php echo SAM_IS_DATE; ?>' <?php checked(1, $this->checkViewPages($row['view_pages'], SAM_IS_DATE)); ?>>
                    <label for='is_date'><?php _e('Date Archive Pages (any date-based archive pages, i.e. a monthly, yearly, daily or time-based archive)', SAM_DOMAIN); ?></label><br/>
                  </div>
                </div>
                <div class='clear-line'></div>
                <p>
                  <input type='radio' name='view_type' id='view_type_2' value='2' <?php checked(2, $row['view_type']); ?>>
                  <label for='view_type_2'><strong><?php echo __('Show ad only in certain posts', SAM_DOMAIN).':'; ?></strong></label>
                </p>
                <div class='radio-content'>
                  <label for='view_id'><strong><?php echo __('Posts IDs (comma separated)', SAM_DOMAIN).':'; ?></strong></label>
                  <input type='text' name='view_id' id='view_id' value='<?php echo $row['view_id']; ?>'>                  
                </div>
                <p>
                  <?php _e('Use this setting to display an ad only in certain posts. Enter the ID of posts, separated by commas.', SAM_DOMAIN); ?>
                </p>
                <div class='clear-line'></div>
                <p>
                  <input type='radio' name='view_type' id='view_type_3' value='3' <?php checked(3, $row['view_type']); ?>>
                  <label for='view_type_2'><strong><?php echo __('Show ad only in single posts of certain categories', SAM_DOMAIN).':'; ?></strong></label>
                </p>
                <div class='radio-content'>
                  <label for='view_cats'><strong><?php echo __('Categories (comma separated)', SAM_DOMAIN).':'; ?></strong></label>
                  <input type='text' name='view_cats' id='view_cats' autocomplete="off" value='<?php echo $row['view_cats']; ?>' style="width:100%">                  
                </div>
                <p>
                  <?php _e('Use this setting to display an ad only in single posts of certain categories. Enter the names of categories, separated by commas.', SAM_DOMAIN); ?>
                </p>
                <div class='clear-line'></div>
                <p>
                  <input type='checkbox' name='ad_schedule' id='ad_schedule' value='1' <?php checked(1, $row['ad_schedule']); ?>>
                  <label for='ad_schedule'><?php _e('Use the schedule for this ad', SAM_DOMAIN); ?></label>
                </p>
                <p>
                  <label for='ad_start_date'><strong><?php echo __('Campaign Start Date', SAM_DOMAIN).':' ?></strong></label>
                  <input type='text' name='ad_start_date' id='ad_start_date' value='<?php echo $row['ad_start_date']; ?>'>
                </p>
                <p>
                  <label for='ad_end_date'><strong><?php echo __('Campaign End Date', SAM_DOMAIN).':' ?></strong></label>
                  <input type='text' name='ad_end_date' id='ad_end_date' value='<?php echo $row['ad_end_date']; ?>'>
                </p>                
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>          
          <?php
          break;
          
			}
		}
  } // end of class definition
} // end of if not class SimpleAdsManager exists
?>