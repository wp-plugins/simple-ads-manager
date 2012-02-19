<?php
if ( !class_exists( 'SimpleAdsManagerAdmin' && class_exists('SimpleAdsManager') ) ) {
  class SimpleAdsManagerAdmin extends SimpleAdsManager {
    private $editPage;
    private $settingsPage;
    private $listPage;
    private $editZone;
    private $listZone;
    private $editBlock;
    private $listBlock;
    
    public function __construct() {
      parent::__construct();

      global $wp_version;
      
			if ( function_exists( 'load_plugin_textdomain' ) )
				load_plugin_textdomain( SAM_DOMAIN, false, basename( SAM_PATH ) );
      
      if(!is_dir(SAM_AD_IMG)) mkdir(SAM_AD_IMG);
				
      register_activation_hook(SAM_MAIN_FILE, array(&$this, 'onActivate'));
      register_deactivation_hook(SAM_MAIN_FILE, array(&$this, 'onDeactivate'));
      
      add_action('wp_ajax_upload_ad_image', array(&$this, 'uploadHandler'));
      add_action('wp_ajax_get_strings', array(&$this, 'getStringsHandler'));
			add_action('admin_init', array(&$this, 'initSettings'));
			add_action('admin_menu', array(&$this, 'regAdminPage'));
      add_filter('tiny_mce_version', array(&$this, 'tinyMCEVersion'));
      add_action('init', array(&$this, 'addButtons'));
      if(version_compare($wp_version, '3.3', '<'))
        add_filter('contextual_help', array(&$this, 'help'), 10, 3);
      
      $versions = parent::getVersions(true);
      if(empty($versions) || 
         version_compare($versions['sam'], SAM_VERSION, '<') ||
         version_compare($versions['db'], SAM_DB_VERSION, '<')) self::updateDB();
    }
    
    public function onActivate() {
      $settings = parent::getSettings(true);
			update_option( SAM_OPTIONS_NAME, $settings );
			self::updateDB();
    }
    
    public function onDeactivate() {
      global $wpdb;
			$zTable = $wpdb->prefix . "sam_zones";
      $pTable = $wpdb->prefix . "sam_places";					
			$aTable = $wpdb->prefix . "sam_ads";
      $bTable = $wpdb->prefix . "sam_blocks";
			$settings = parent::getSettings();
			
			if($settings['deleteOptions'] == 1) {
				delete_option( SAM_OPTIONS_NAME );
				delete_option('sam_version');
				delete_option('sam_db_version');
			}
			if($settings['deleteDB'] == 1) {
				$sql = 'DROP TABLE IF EXISTS ';
        $wpdb->query($sql.$zTable);
				$wpdb->query($sql.$pTable);
				$wpdb->query($sql.$aTable);
        $wpdb->query($sql.$bTable);
				delete_option('sam_db_version');
			}
      if($settings['deleteFolder'] == 1) {
        if(is_dir(SAM_AD_IMG)) rmdir(SAM_AD_IMG);
      }
    }
    
    private function getVersionData($version) {
      $output = array();
      $vArray = explode('.', $version);
      
      $output['major'] = (integer)$vArray[0];
      $output['minor'] = (integer)$vArray[1];
      if(!is_null((integer)$vArray[2])) $output['revision'] = (integer)$vArray[2];
      else $output['revision'] = 0;
      
      return $output;
    }
    
    private function updateDB() {
      global $wpdb, $charset_collate;
      $pTable = $wpdb->prefix . "sam_places";          
      $aTable = $wpdb->prefix . "sam_ads";
      $zTable = $wpdb->prefix . "sam_zones";
      $bTable = $wpdb->prefix . "sam_blocks";
      
      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      
      $versions = $this->getVersions(true);
      $dbVersion = $versions['db'];
      $vData = $this->getVersionData($dbVersion);
      
      if( $dbVersion != SAM_DB_VERSION ) {
        if($wpdb->get_var("SHOW TABLES LIKE '$pTable'") != $pTable) {
          $pSql = "CREATE TABLE $pTable (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,                  
                    description VARCHAR(255) DEFAULT NULL,
                    code_before VARCHAR(255) DEFAULT NULL,
                    code_after VARCHAR(255) DEFAULT NULL,
                    place_size VARCHAR(25) DEFAULT NULL,
                    place_custom_width INT(11) DEFAULT NULL,
                    place_custom_height INT(11) DEFAULT NULL,
                    patch_img VARCHAR(255) DEFAULT NULL,
                    patch_link VARCHAR(255) DEFAULT NULL,
                    patch_code TEXT DEFAULT NULL,
                    patch_adserver TINYINT(1) DEFAULT 0,
                    patch_dfp VARCHAR(255) DEFAULT NULL,                  
                    patch_source TINYINT(1) DEFAULT 0,
                    patch_hits INT(11) DEFAULT 0,
                    trash TINYINT(1) DEFAULT 0,
                    PRIMARY KEY  (id)
                   ) $charset_collate;";
          dbDelta($pSql);
        }
        elseif($dbVersion == '0.1' || $dbVersion == '0.2') {
          $pSql = "ALTER TABLE $pTable 
                     CONVERT TO $charset_collate,
                     ADD COLUMN patch_dfp VARCHAR(255) DEFAULT NULL,
                     ADD COLUMN patch_adserver TINYINT(1) DEFAULT 0,
                     ADD COLUMN patch_hits INT(11) DEFAULT 0;";
          $wpdb->query($pSql);
        }
        elseif($vData['major'] < 2) {
          $pSql = "ALTER TABLE $pTable CONVERT TO $charset_collate;";
          $wpdb->query($pSql);
        }
        
        if($wpdb->get_var("SHOW TABLES LIKE '$aTable'") != $aTable) {
          $aSql = "CREATE TABLE $aTable (
                  id INT(11) NOT NULL AUTO_INCREMENT,
                  pid INT(11) NOT NULL,
                  name VARCHAR(255) DEFAULT NULL,
                  description VARCHAR(255) DEFAULT NULL,
                  code_type TINYINT(1) NOT NULL DEFAULT 0,
                  code_mode TINYINT(1) NOT NULL DEFAULT 1,
                  ad_code TEXT DEFAULT NULL,
                  ad_img TEXT DEFAULT NULL,
                  ad_alt TEXT DEFAULT NULL,
                  ad_no TINYINT(1) NOT NULL DEFAULT 0,
                  ad_target TEXT DEFAULT NULL,
                  count_clicks TINYINT(1) NOT NULL DEFAULT 0,
                  view_type INT(11) DEFAULT 1,
                  view_pages SET('isHome', 'isSingular', 'isSingle', 'isPage', 'isAttachment', 'isSearch', 'is404', 'isArchive', 'isTax', 'isCategory', 'isTag', 'isAuthor', 'isDate') DEFAULT NULL,
                  view_id VARCHAR(255) DEFAULT NULL,
                  ad_cats TINYINT(1) DEFAULT 0,
                  view_cats VARCHAR(255) DEFAULT NULL,
                  ad_authors TINYINT(1) DEFAULT 0,
                  view_authors VARCHAR(255) DEFAULT NULL,
                  ad_tags TINYINT(1) DEFAULT 0,
                  view_tags VARCHAR(255) DEFAULT NULL,
                  ad_custom TINYINT(1) DEFAULT 0,
                  view_custom VARCHAR(255) DEFAULT NULL,
                  x_id TINYINT(1) DEFAULT 0,
                  x_view_id VARCHAR(255) DEFAULT NULL,
                  x_cats TINYINT(1) DEFAULT 0,
                  x_view_cats VARCHAR(255) DEFAULT NULL,
                  x_authors TINYINT(1) DEFAULT 0,
                  x_view_authors VARCHAR(255) DEFAULT NULL,
                  x_tags TINYINT(1) DEFAULT 0,
                  x_view_tags VARCHAR(255) DEFAULT NULL,
                  x_custom TINYINT(1) DEFAULT 0,
                  x_view_custom VARCHAR(255) DEFAULT NULL,
                  ad_schedule TINYINT(1) DEFAULT 0,
                  ad_start_date DATE DEFAULT NULL,
                  ad_end_date DATE DEFAULT NULL,
                  limit_hits TINYINT(1) DEFAULT 0,
                  hits_limit INT(11) DEFAULT 0,
                  limit_clicks TINYINT(1) DEFAULT 0,
                  clicks_limit INT(11) DEFAULT 0,
                  ad_hits INT(11) DEFAULT 0,
                  ad_clicks INT(11) DEFAULT 0,
                  ad_weight INT(11) DEFAULT 10,
                  ad_weight_hits INT(11) DEFAULT 0,
                  cpm DECIMAL(10,2) UNSIGNED DEFAULT 0.00,
                  cpc DECIMAL(10,2) UNSIGNED DEFAULT 0.00,
                  per_month DECIMAL(10,2) UNSIGNED DEFAULT 0.00,
                  trash TINYINT(1) NOT NULL DEFAULT 0,
                  PRIMARY KEY  (id, pid)
                ) $charset_collate;";
          dbDelta($aSql);
        }
        elseif($dbVersion == '0.1') {
          $aSql = "ALTER TABLE $aTable 
                      CONVERT TO $charset_collate,
                      MODIFY view_pages set('isHome','isSingular','isSingle','isPage','isAttachment','isSearch','is404','isArchive','isTax','isCategory','isTag','isAuthor','isDate','isPostType','isPostTypeArchive') default NULL,
                      ADD COLUMN ad_alt TEXT DEFAULT NULL,
                      ADD COLUMN ad_no TINYINT(1) NOT NULL DEFAULT 0,
                      ADD COLUMN ad_cats TINYINT(1) DEFAULT 0,
                      ADD COLUMN ad_authors TINYINT(1) DEFAULT 0,
                      ADD COLUMN view_authors VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN ad_tags TINYINT(1) DEFAULT 0,
                      ADD COLUMN view_tags VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN ad_custom TINYINT(1) DEFAULT 0,
                      ADD COLUMN view_custom VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN limit_hits TINYINT(1) DEFAULT 0,
                      ADD COLUMN hits_limit INT(11) DEFAULT 0,
                      ADD COLUMN limit_clicks TINYINT(1) DEFAULT 0,
                      ADD COLUMN clicks_limit INT(11) DEFAULT 0,
                      ADD COLUMN cpm DECIMAL(10,2) UNSIGNED DEFAULT 0.00,
                      ADD COLUMN cpc DECIMAL(10,2) UNSIGNED DEFAULT 0.00,
                      ADD COLUMN per_month DECIMAL(10,2) UNSIGNED DEFAULT 0.00,
                      ADD COLUMN x_id TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_id VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_cats TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_cats VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_authors TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_authors VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_tags TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_tags VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_custom TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_custom VARCHAR(255) DEFAULT NULL;";
          $wpdb->query($aSql);
          $aSqlU = "UPDATE LOW_PRIORITY $aTable 
                      SET $aTable.ad_cats = 1, 
                          $aTable.view_type = 0,
                          $aTable.view_pages = 4
                      WHERE $aTable.view_type = 3;";
          $wpdb->query($aSqlU);
        }
        elseif($dbVersion == '0.2' || $dbVersion == '0.3' || $dbVersion == '0.3.1') {
          $aSql = "ALTER TABLE $aTable
                      CONVERT TO $charset_collate,
                      MODIFY view_pages set('isHome','isSingular','isSingle','isPage','isAttachment','isSearch','is404','isArchive','isTax','isCategory','isTag','isAuthor','isDate','isPostType','isPostTypeArchive') default NULL,
                      ADD COLUMN ad_alt TEXT DEFAULT NULL,
                      ADD COLUMN ad_no TINYINT(1) NOT NULL DEFAULT 0,
                      ADD COLUMN limit_hits TINYINT(1) DEFAULT 0,
                      ADD COLUMN hits_limit INT(11) DEFAULT 0,
                      ADD COLUMN limit_clicks TINYINT(1) DEFAULT 0,
                      ADD COLUMN clicks_limit INT(11) DEFAULT 0,
                      ADD COLUMN cpm DECIMAL(10,2) UNSIGNED DEFAULT 0.00,
                      ADD COLUMN cpc DECIMAL(10,2) UNSIGNED DEFAULT 0.00,
                      ADD COLUMN per_month DECIMAL(10,2) UNSIGNED DEFAULT 0.00,
                      ADD COLUMN ad_tags TINYINT(1) DEFAULT 0,
                      ADD COLUMN view_tags VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN ad_custom TINYINT(1) DEFAULT 0,
                      ADD COLUMN view_custom VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_id TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_id VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_cats TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_cats VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_authors TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_authors VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_tags TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_tags VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_custom TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_custom VARCHAR(255) DEFAULT NULL;";
          $wpdb->query($aSql);
        }
        elseif($dbVersion == '0.4' || $dbVersion == '0.5') {
          $aSql = "ALTER TABLE $aTable
                      CONVERT TO $charset_collate,
                      MODIFY view_pages set('isHome','isSingular','isSingle','isPage','isAttachment','isSearch','is404','isArchive','isTax','isCategory','isTag','isAuthor','isDate','isPostType','isPostTypeArchive') default NULL,
                      ADD COLUMN ad_alt TEXT DEFAULT NULL,
                      ADD COLUMN ad_no TINYINT(1) NOT NULL DEFAULT 0,
                      ADD COLUMN ad_tags TINYINT(1) DEFAULT 0,
                      ADD COLUMN view_tags VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN ad_custom TINYINT(1) DEFAULT 0,
                      ADD COLUMN view_custom VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_id TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_id VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_cats TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_cats VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_authors TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_authors VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_tags TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_tags VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_custom TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_custom VARCHAR(255) DEFAULT NULL;";
          $wpdb->query($aSql);
        }
        elseif($dbVersion == "0.5.1") {
          $aSql = "ALTER TABLE $aTable
                      CONVERT TO $charset_collate,
                      MODIFY view_pages set('isHome','isSingular','isSingle','isPage','isAttachment','isSearch','is404','isArchive','isTax','isCategory','isTag','isAuthor','isDate','isPostType','isPostTypeArchive') default NULL,
                      ADD COLUMN ad_alt TEXT DEFAULT NULL,
                      ADD COLUMN ad_no TINYINT(1) NOT NULL DEFAULT 0,
                      ADD COLUMN ad_tags TINYINT(1) DEFAULT 0,
                      ADD COLUMN view_tags VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN ad_custom TINYINT(1) DEFAULT 0,
                      ADD COLUMN view_custom VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_tags TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_tags VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_custom TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_custom VARCHAR(255) DEFAULT NULL;";
          $wpdb->query($aSql);
        }
        elseif($vData['major'] < 2) {
          $aSql = "ALTER TABLE $aTable CONVERT TO $charset_collate;";
          $wpdb->query($aSql);
        }
        
        if($wpdb->get_var("SHOW TABLES LIKE '$zTable'") != $zTable) {
          $zSql = "CREATE TABLE $zTable (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,                  
                    description VARCHAR(255) DEFAULT NULL,
                    z_default INT(11) DEFAULT 0,
                    z_home INT(11) DEFAULT 0,
                    z_singular INT(11) DEFAULT 0,
                    z_single INT(11) DEFAULT 0,
                    z_ct INT(11) DEFAULT 0,
                    z_single_ct LONGTEXT DEFAULT NULL,
                    z_page INT(11) DEFAULT 0,
                    z_attachment INT(11) DEFAULT 0,
                    z_search INT(11) DEFAULT 0,
                    z_404 INT(11) DEFAULT 0,
                    z_archive INT(11) DEFAULT 0,
                    z_tax INT(11) DEFAULT 0,
                    z_category INT(11) DEFAULT 0,
                    z_cats LONGTEXT DEFAULT NULL,
                    z_tag INT(11) DEFAULT 0,
                    z_author INT(11) DEFAULT 0,
                    z_authors LONGTEXT DEFAULT NULL,
                    z_date INT(11) DEFAULT 0,
                    z_cts INT(11) DEFAULT 0,
                    z_archive_ct LONGTEXT DEFAULT NULL,
                    trash TINYINT(1) DEFAULT 0,
                    PRIMARY KEY (id)
                  ) $charset_collate;";
          dbDelta($zSql);
        }
        elseif(in_array($dbVersion, array('0.1', '0.2', '0.3', '0.3.1', '0.4', '0.5', '0.5.1'))) {
          $zSql = "ALTER TABLE $zTable
                      CONVERT TO $charset_collate,
                      ADD COLUMN z_ct INT(11) DEFAULT 0,
                      ADD COLUMN z_cts INT(11) DEFAULT 0,
                      ADD COLUMN z_single_ct LONGTEXT DEFAULT NULL,
                      ADD COLUMN z_archive_ct LONGTEXT DEFAULT NULL;";
          $wpdb->query($zSql);
        }
        elseif($vData['major'] < 2) {
          $zSql = "ALTER TABLE $zTable CONVERT TO $charset_collate;";
          $wpdb->query($zSql);
        }
        
        if($wpdb->get_var("SHOW TABLES LIKE '$bTable'") != $bTable) {
          $bSql = "CREATE TABLE $bTable (
                      id INT(11) NOT NULL AUTO_INCREMENT,
                      name VARCHAR(255) NOT NULL,                  
                      description VARCHAR(255) DEFAULT NULL,
                      b_lines INT(11) DEFAULT 2,
                      b_cols INT(11) DEFAULT 2,
                      block_data LONGTEXT DEFAULT NULL,
                      b_margin VARCHAR(30) DEFAULT '5px 5px 5px 5px',
                      b_padding VARCHAR(30) DEFAULT '5px 5px 5px 5px',
                      b_background VARCHAR(30) DEFAULT '#FFFFFF',
                      b_border VARCHAR(30) DEFAULT '0px solid #333333',
                      i_margin VARCHAR(30) DEFAULT '5px 5px 5px 5px',
                      i_padding VARCHAR(30) DEFAULT '5px 5px 5px 5px',
                      i_background VARCHAR(30) DEFAULT '#FFFFFF',
                      i_border VARCHAR(30) DEFAULT '0px solid #333333',
                      trash TINYINT(1) DEFAULT 0,
                      PRIMARY KEY (id)
                  ) $charset_collate;";
          dbDelta($bSql);
        }
        elseif($vData['major'] < 2) {
          $bSql = "ALTER TABLE $bTable CONVERT TO $charset_collate;";
          $wpdb->query($bSql);
        }
        update_option('sam_db_version', SAM_DB_VERSION);
      }
      update_option('sam_version', SAM_VERSION);
      $this->getVersions(true);
    }
		
		public function initSettings() {
			register_setting('samOptions', SAM_OPTIONS_NAME);
      add_settings_section("sam_general_section", __("General Settings", SAM_DOMAIN), array(&$this, "drawGeneralSection"), 'sam-settings');
      add_settings_section("sam_single_section", __("Auto Inserting Settings", SAM_DOMAIN), array(&$this, "drawSingleSection"), 'sam-settings');
      add_settings_section("sam_dfp_section", __("Google DFP Settings", SAM_DOMAIN), array(&$this, "drawDFPSection"), 'sam-settings');
      add_settings_section("sam_statistic_section", __("Statistics Settings", SAM_DOMAIN), array(&$this, "drawStatisticsSection"), 'sam-settings');
      add_settings_section("sam_layout_section", __("Admin Layout", SAM_DOMAIN), array(&$this, "drawLayoutSection"), 'sam-settings');
			add_settings_section("sam_deactivate_section", __("Plugin Deactivating", SAM_DOMAIN), array(&$this, "drawDeactivateSection"), 'sam-settings');
			
      add_settings_field('adCycle', __("Views per Cycle", SAM_DOMAIN), array(&$this, 'drawTextOption'), 'sam-settings', 'sam_general_section', array('description' => __('Number of hits of one ad for a full cycle of rotation (maximal activity).', SAM_DOMAIN)));
      add_settings_field('adDisplay', __("Display Ad Source in", SAM_DOMAIN), array(&$this, 'drawRadioOption'), 'sam-settings', 'sam_general_section', array('description' => __('Target wintow (tab) for advetisement source.', SAM_DOMAIN), 'options' => array('blank' => __('New Window (Tab)', SAM_DOMAIN), 'self' => __('Current Window (Tab)', SAM_DOMAIN))));
      
      add_settings_field('bpAdsId', __("Ads Place before content", SAM_DOMAIN), array(&$this, 'drawSelectOptionX'), 'sam-settings', 'sam_single_section', array('description' => ''));
      add_settings_field('beforePost', __("Allow Ads Place auto inserting before post/page content", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'beforePost', 'checkbox' => true));
      add_settings_field('bpUseCodes', __("Allow using predefined Ads Place HTML codes (before and after codes)", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'bpUseCodes', 'checkbox' => true));
      add_settings_field('mpAdsId', __("Ads Place in the middle of content", SAM_DOMAIN), array(&$this, 'drawSelectOptionX'), 'sam-settings', 'sam_single_section', array('description' => ''));
      add_settings_field('middlePost', __("Allow Ads Place auto inserting into the middle of post/page content", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'afterPost', 'checkbox' => true));
      add_settings_field('mpUseCodes', __("Allow using predefined Ads Place HTML codes (before and after codes)", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'apUseCodes', 'checkbox' => true));
      add_settings_field('apAdsId', __("Ads Place after content", SAM_DOMAIN), array(&$this, 'drawSelectOptionX'), 'sam-settings', 'sam_single_section', array('description' => ''));
      add_settings_field('afterPost', __("Allow Ads Place auto inserting after post/page content", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'afterPost', 'checkbox' => true));
      add_settings_field('apUseCodes', __("Allow using predefined Ads Place HTML codes (before and after codes)", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'apUseCodes', 'checkbox' => true));
      
      add_settings_field('useDFP', __("Allow using Google DoubleClick for Publishers (DFP) rotator codes", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_dfp_section', array('label_for' => 'useDFP', 'checkbox' => true));
      add_settings_field('dfpPub', __("Google DFP Pub Code", SAM_DOMAIN), array(&$this, 'drawTextOption'), 'sam-settings', 'sam_dfp_section', array('description' => __('Your Google DFP Pub code. i.e:', SAM_DOMAIN).' ca-pub-0000000000000000.', 'width' => 200));
      
      add_settings_field('detectBots', __("Allow Bots and Crawlers detection", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_statistic_section', array('label_for' => 'detectBots', 'checkbox' => true));
      add_settings_field('detectingMode', __("Accuracy of Bots and Crawlers Detection", SAM_DOMAIN), array(&$this, 'drawRadioOption'), 'sam-settings', 'sam_statistic_section', array('description' => __("If bot is detected hits of ads won't be counted. Use with caution! More exact detection requires more server resources.", SAM_DOMAIN), 'options' => array( 'inexact' => __('Inexact detection', SAM_DOMAIN), 'exact' => __('Exact detection', SAM_DOMAIN), 'more' => __('More exact detection', SAM_DOMAIN))));
      add_settings_field('currency', __("Display of Currency", SAM_DOMAIN), array(&$this, 'drawRadioOption'), 'sam-settings', 'sam_statistic_section', array('description' => __("Define display of currency. Auto - auto detection of currency from blog settings. USD, EUR - Forcing the display of currency to U.S. dollars or Euro.", SAM_DOMAIN), 'options' => array( 'auto' => __('Auto', SAM_DOMAIN), 'usd' => __('USD', SAM_DOMAIN), 'euro' => __('EUR', SAM_DOMAIN))));

      add_settings_field('editorButtonMode', __("TinyMCE Editor Button Mode", SAM_DOMAIN), array(&$this, 'drawRadioOption'), 'sam-settings', 'sam_layout_section', array('description' => __('If you do not want to use the modern dropdown button in your TinyMCE editor, or use of this button causes a problem, you can use classic TinyMCE buttons. In this case select "Classic TinyMCE Buttons".', SAM_DOMAIN), 'options' => array('modern' => __('Modern TinyMCE Button', SAM_DOMAIN), 'classic' => __('Classic TinyMCE Buttons', SAM_DOMAIN))));
      add_settings_field('placesPerPage', __("Ads Places per Page", SAM_DOMAIN), array(&$this, 'drawTextOption'), 'sam-settings', 'sam_layout_section', array('description' => __('Ads Places Management grid pagination. How many Ads Places will be shown on one page of grid.', SAM_DOMAIN)));
			add_settings_field('itemsPerPage', __("Ads per Page", SAM_DOMAIN), array(&$this, 'drawTextOption'), 'sam-settings', 'sam_layout_section', array('description' => __('Ads of Ads Place Management grid pagination. How many Ads will be shown on one page of grid.', SAM_DOMAIN)));
      
      add_settings_field('deleteOptions', __("Delete plugin options during deactivating plugin", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_deactivate_section', array('label_for' => 'deleteOptions', 'checkbox' => true));
			add_settings_field('deleteDB', __("Delete database tables of plugin during deactivating plugin", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_deactivate_section', array('label_for' => 'deleteDB', 'checkbox' => true));
      add_settings_field('deleteFolder', __("Delete custom images folder of plugin during deactivating plugin", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_deactivate_section', array('label_for' => 'deleteFolder', 'checkbox' => true));
      
      register_setting('sam-settings', SAM_OPTIONS_NAME, array(&$this, 'sanitizeSettings'));
		}
    
    public function regAdminPage() {
			global $wp_version;

      $menuPage = add_object_page(__('Ads', SAM_DOMAIN), __('Ads', SAM_DOMAIN), 8, 'sam-list', array(&$this, 'samTablePage'), WP_PLUGIN_URL.'/simple-ads-manager/images/sam-icon.png');
			$this->listPage = add_submenu_page('sam-list', __('Ads List', SAM_DOMAIN), __('Ads Places', SAM_DOMAIN), 8, 'sam-list', array(&$this, 'samTablePage'));
			add_action('admin_print_styles-'.$this->listPage, array(&$this, 'adminListStyles'));
      $this->editPage = add_submenu_page('sam-list', __('Ad Editor', SAM_DOMAIN), __('New Place', SAM_DOMAIN), 8, 'sam-edit', array(&$this, 'samEditPage'));
      add_action('admin_print_styles-'.$this->editPage, array(&$this, 'adminEditStyles'));
      add_action('admin_print_scripts-'.$this->editPage, array(&$this, 'adminEditScripts'));
      $this->listZone = add_submenu_page('sam-list', __('Ads Zones List', SAM_DOMAIN), __('Ads Zones', SAM_DOMAIN), 8, 'sam-zone-list', array(&$this, 'samZoneListPage'));
      add_action('admin_print_styles-'.$this->listZone, array(&$this, 'adminListStyles'));
      $this->editZone = add_submenu_page('sam-list', __('Ads Zone Editor', SAM_DOMAIN), __('New Zone', SAM_DOMAIN), 8, 'sam-zone-edit', array(&$this, 'samZoneEditPage'));
      add_action('admin_print_styles-'.$this->editZone, array(&$this, 'adminEditStyles'));
      $this->listBlock = add_submenu_page('sam-list', __('Ads Blocks List', SAM_DOMAIN), __('Ads Blocks', SAM_DOMAIN), 8, 'sam-block-list', array(&$this, 'samBlockListPage'));
      add_action('admin_print_styles-'.$this->listBlock, array(&$this, 'adminListStyles'));
      $this->editBlock = add_submenu_page('sam-list', __('Ads Block Editor', SAM_DOMAIN), __('New Block', SAM_DOMAIN), 8, 'sam-block-edit', array(&$this, 'samBlockEditPage'));
      add_action('admin_print_styles-'.$this->editBlock, array(&$this, 'adminEditStyles'));
			$this->settingsPage = add_submenu_page('sam-list', __('Simple Ads Manager Settings', SAM_DOMAIN), __('Settings', SAM_DOMAIN), 8, 'sam-settings', array(&$this, 'samAdminPage'));
      add_action('admin_print_styles-'.$this->settingsPage, array(&$this, 'adminSettingsStyles'));
      if(version_compare($wp_version, '3.3', '>=')) {
        add_action('load-'.$this->listPage, array(&$this, 'samHelp'));
        add_action('load-'.$this->editPage, array(&$this, 'samHelp'));
        add_action('load-'.$this->settingsPage, array(&$this, 'samHelp'));
      }
		}

    public function samHelp() {
      $samScreens = array($this->listPage, $this->editPage, $this->listZone, $this->editZone, $this->listBlock, $this->editBlock, $this->settingsPage);
      $samPages = array(
        'listPage' => $this->listPage,
        'editPage' => $this->editPage,
        'listZone' => $this->listZone,
        'editZone' => $this->editZone,
        'listBlock' => $this->listBlock,
        'editBlock' => $this->editBlock,
        'settingsPage' => $this->settingsPage
      );

      include_once('help.class.php');
      $help = new SAMHelp33(array('screens' => $samScreens, 'pages' => $samPages));
      $help->help();

      /*$samScreens = array($this->listPage, $this->editPage, $this->listZone, $this->editZone, $this->listBlock, $this->editBlock, $this->settingsPage);
      $screen = get_current_screen();
      $context = '';

      if(!in_array($screen->id, $samScreens)) return;

      if($screen->id == $this->listPage) {
        if($_GET["action"] == 'places' || $_GET["action"] == '') {
          //$context = '<div class="sam-contextual-help">';
          $context .= '<p>'.__('This is list of Ads Places', SAM_DOMAIN).'</p>';
          $context .= '<p><a class="button-secondary" href="http://www.simplelib.com/?p=480" target="_blank">'.__('Manual', SAM_DOMAIN).'</a> ';
          $context .= '<a class="button-secondary" href="http://forum.simplelib.com/index.php?board=10.0" target="_blank">'.__('Support Forum', SAM_DOMAIN).'</a></p>';
          //$context .= '</div>';
          $title = __('Help', SAM_DOMAIN);
        }
        else {
          //$context = '<div class="sam-contextual-help">';
          $context .= '<p>'.__('This is list of Ads', SAM_DOMAIN).'</p>';
          $context .= '<p><a class="button-secondary" href="http://www.simplelib.com/?p=480" target="_blank">'.__('Manual', SAM_DOMAIN).'</a> ';
          $context .= '<a class="button-secondary" href="http://forum.simplelib.com/index.php?board=10.0" target="_blank">'.__('Support Forum', SAM_DOMAIN).'</a></p>';
          //$context .= '</div>';
          $title = __('Help', SAM_DOMAIN);
        }
        $screen->add_help_tab(array('id' => 'sam-help', 'title' => $title, 'content' => $context));
      }

      if($screen->id == $this->editPage) {
        if($_GET["mode"] == 'place' || $_GET['mode'] == '') {
          $context = '<p>'.__('The main object of the plugin is “Ads Place“. Each Ads Place is a container for the advertisements and provides the logic of the show and rotation. In addition, one of the parameters of advertising space is “patch ad code”, ie ad to be shown if and only if the logic of ads this Ads Place does not permit to show none of the advertisements contained in this Ads Place. One Ads Place can contain any number of objects “advertisement”.', SAM_DOMAIN).'</p>';
          $context .= '<p><a class="button-secondary" href="http://www.simplelib.com/?p=480" target="_blank">'.__('Manual', SAM_DOMAIN).'</a> ';
          $context .= '<a class="button-secondary" href="http://forum.simplelib.com/index.php?board=10.0" target="_blank">'.__('Support Forum', SAM_DOMAIN).'</a></p>';
          $title = __('Ads Place', SAM_DOMAIN);

          $screen->add_help_tab(array('id' => 'sam-help', 'title' => $title, 'content' => $context));

          $context2 = '<p>'.__('Enter a <strong>name</strong> and a <strong>description</strong> of the Ads Place. In principle, it is not mandatory parameters, because these parameters don’t influence anything, but experience suggests that after a while all IDs usually will be forgotten  and such information may be useful.', SAM_DOMAIN).'</p>';
          $context2 .= '<p>'.__('<strong>Ads Place Size</strong> – in this version is only for informational purposes only, but in future I plan to use this option. It is desirable to expose the real size.', SAM_DOMAIN).'</p>';
          $context2 .= '<p>'.__('<strong>Ads Place Patch</strong> - it’s an ad that will appear in the event that the logic of basic ads outputing of this Ads Place on the current page will not be able to choose a single basic ad for displaying. For example, if all basic announcements are set to displaying only on archives pages or single pages, in this case the patch ad of Ads Place will be shown on the Home page. Conveniently to use the patch ad of Ads Place where you sell the advertising place for a limited time – after the time expiration of ordered ad will appear patch ad. It may be a banner leading to your page of advertisement publication costs or a banner from AdSense.', SAM_DOMAIN).'</p>';
          $context2 .= '<p>'.__('Patch can be defined', SAM_DOMAIN);
          $context2 .= '<ul>';
          $context2 .= '<li>'.__('as combination of the image URL and target page URL', SAM_DOMAIN).'</li>';
          $context2 .= '<li>'.__('as HTML code or javascript code', SAM_DOMAIN).'</li>';
          $context2 .= '<li>'.__('as name of Google <a href="https://www.google.com/intl/en/dfp/info/welcome.html" target="_blank">DoubleClick for Publishers</a> (DFP) block', SAM_DOMAIN).'</li>';
          $context2 .= '</ul></p>';
          $context2 .= '<p>'.__('If you select the first option (image mode), tools to download/choosing of downloaded image banner become available for you.', SAM_DOMAIN).'</p>';
          $context2 .= '<p>'.__('<strong>Codes</strong> – as Ads Place can be inserted into the page code not only as widget, but as a short code or by using function, you can use code “before” and “after” for centering or alignment of Ads Place on the place of inserting or for something else you need. Use HTML tags.', SAM_DOMAIN);
          $context2 .= '<p><a class="button-secondary" href="http://www.simplelib.com/?p=480" target="_blank">'.__('Manual', SAM_DOMAIN).'</a> ';
          $context2 .= '<a class="button-secondary" href="http://forum.simplelib.com/index.php?board=10.0" target="_blank">'.__('Support Forum', SAM_DOMAIN).'</a></p>';
          $title2 = __('Parameters', SAM_DOMAIN);

          $screen->add_help_tab(array('id' => 'sam-help-place', 'title' => $title2, 'content' => $context2));

        }
        else {
          $context = '<p>'.__('Object “advertisement” rigidly attached to his container “Ads Place”. Its parameters determine frequency (weight) of displaying and limiting displaying from “show all pages” to “show the articles with ID … ” and show from date to date (the schedule).', SAM_DOMAIN).'</p>';
          $context .= '<p><a class="button-secondary" href="http://www.simplelib.com/?p=480" target="_blank">'.__('Manual', SAM_DOMAIN).'</a> ';
          $context .= '<a class="button-secondary" href="http://forum.simplelib.com/index.php?board=10.0" target="_blank">'.__('Support Forum', SAM_DOMAIN).'</a></p>';
          $title = __('Advertisement', SAM_DOMAIN);

          $screen->add_help_tab(array('id' => 'sam-help', 'title' => $title, 'content' => $context));

          $context2 = '<p>'.__('Enter a <strong>name</strong> and a <strong>description</strong> of the advertisement. These parameters are optional, because don’t influence anything, but help in the visual identification of the ad (do not forget which is which).', SAM_DOMAIN).'</p>';
          $context2 .= '<p>'.__('<strong>Ad Code</strong> – code can be defined as a combination of the image URL and target page URL, or as HTML code, javascript code, or PHP code (for PHP-code don’t forget to set the checkbox labeled "This code of ad contains PHP script"). If you select the first option (image mode) you can keep statistics of clicks and also tools for uploading/selecting the downloaded image banner becomes available to you.', SAM_DOMAIN).'</p>';
          $context2 .= '<p>'.__('<strong>Restrictions of advertisement Showing</strong>', SAM_DOMAIN).'</p>';
          $context2 .= '<p>'.__('<em>Ad Weight</em> – coefficient of frequency of show of the advertisement for one cycle of advertisements rotation. 0 – ad is inactive, 1 – minimal activity of this advertisement, 10 – maximal activity of this ad.', SAM_DOMAIN).'</p>';
          $context2 .= '<p>'.__('<em>Restrictions by the type of pages</em> – select restrictions:', SAM_DOMAIN);
          $context2 .= '<ul>';
          $context2 .= '<li>'.__('Show ad on all pages of blog', SAM_DOMAIN).'</li>';
          $context2 .= '<li>'.__('Show ad only on pages of this type – ad will appear only on the pages of selected types', SAM_DOMAIN).'</li>';
          $context2 .= '<li>'.__('Show ad only in certain posts – ad will be shown only on single posts pages with the given IDs (ID items separated by commas, no spaces)', SAM_DOMAIN).'</li>';
          $context2 .= '</ul></p>';
          $context2 .= '<p><a class="button-secondary" href="http://www.simplelib.com/?p=480" target="_blank">'.__('Manual', SAM_DOMAIN).'</a> ';
          $context2 .= '<a class="button-secondary" href="http://forum.simplelib.com/index.php?board=10.0" target="_blank">'.__('Support Forum', SAM_DOMAIN).'</a></p>';

          $title2 = __('Parameters', SAM_DOMAIN);

          $screen->add_help_tab(array('id' => 'sam-help-item', 'title' => $title2, 'content' => $context2));

          $context3 = '<p>'.__('<strong>Additional restrictions</strong>', SAM_DOMAIN);
          $context3 .= '<ul>';
          $context3 .= '<li>'.__('Show ad only in single posts or categories archives of certain categories – ad will be shown only on single posts pages or category archive pages of the specified categories', SAM_DOMAIN).'</li>';
          $context3 .= '<li>'.__('Show ad only in single posts or authors archives of certain authors – ad will be shown only on single posts pages or author archive pages of the specified authors', SAM_DOMAIN).'</li>';
          $context3 .= '</ul></p>';
          $context3 .= '<p>'.__('<em>Use the schedule for this ad</em> – if necessary, select checkbox labeled “Use the schedule for this ad” and set start and finish dates of ad campaign.', SAM_DOMAIN).'</p>';
          $context3 .= '<p>'.__('<em>Use limitation by hits</em> – if necessary, select checkbox labeled “Use limitation by hits” and set hits limit.', SAM_DOMAIN).'</p>';
          $context3 .= '<p>'.__('<em>Use limitation by clicks</em> – if necessary, select checkbox labeled “Use limitation by clicks” and set clicks limit.', SAM_DOMAIN).'</p>';
          $context3 .= '<p>'.'<strong>'.__('Prices', SAM_DOMAIN).'</strong>: '.__('Use these parameters to get the statistics of incomes from advertisements placed in your blog. "Price of ad placement per month" - parameter used only for calculating statistic of scheduled ads.', SAM_DOMAIN).'</p>';
          $context3 .= '<p><a class="button-secondary" href="http://www.simplelib.com/?p=480" target="_blank">'.__('Manual', SAM_DOMAIN).'</a> ';
          $context3 .= '<a class="button-secondary" href="http://forum.simplelib.com/index.php?board=10.0" target="_blank">'.__('Support Forum', SAM_DOMAIN).'</a></p>';

          $title3 = __('Additional Parameters', SAM_DOMAIN);

          $screen->add_help_tab(array('id' => 'sam-help-item-lmt', 'title' => $title3, 'content' => $context3));
        }
      }*/
    }
    
    public function help($contextualHelp, $screenId, $screen) {
      include_once('help.class.php');

      $help = new SAMHelp(array(
        'editPage' => $this->editPage,
        'listPage' => $this->listPage,
        'settingsPage' => $this->settingsPage
      ));

      return $help->help($contextualHelp, $screenId, $screen);

      /*if ($screenId == $this->editPage) {
        if($_GET['mode'] == 'item') {
          $contextualHelp = '<div class="sam-contextual-help">';
          $contextualHelp .= '<p>'.__('Enter a <strong>name</strong> and a <strong>description</strong> of the advertisement. These parameters are optional, because don’t influence anything, but help in the visual identification of the ad (do not forget which is which).', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p>'.__('<strong>Ad Code</strong> – code can be defined as a combination of the image URL and target page URL, or as HTML code, javascript code, or PHP code (for PHP-code don’t forget to set the checkbox labeled "This code of ad contains PHP script"). If you select the first option (image mode) you can keep statistics of clicks and also tools for uploading/selecting the downloaded image banner becomes available to you.', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p>'.__('<strong>Restrictions of advertisement Showing</strong>', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p>'.__('<em>Ad Weight</em> – coefficient of frequency of show of the advertisement for one cycle of advertisements rotation. 0 – ad is inactive, 1 – minimal activity of this advertisement, 10 – maximal activity of this ad.', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p>'.__('<em>Restrictions by the type of pages</em> – select restrictions:', SAM_DOMAIN);
          $contextualHelp .= '<ul>';
          $contextualHelp .= '<li>'.__('Show ad on all pages of blog', SAM_DOMAIN).'</li>';
          $contextualHelp .= '<li>'.__('Show ad only on pages of this type – ad will appear only on the pages of selected types', SAM_DOMAIN).'</li>';
          $contextualHelp .= '<li>'.__('Show ad only in certain posts – ad will be shown only on single posts pages with the given IDs (ID items separated by commas, no spaces)', SAM_DOMAIN).'</li>';
          $contextualHelp .= '</ul></p>';
          $contextualHelp .= '<p>'.__('<em>Additional restrictions</em>', SAM_DOMAIN);
          $contextualHelp .= '<ul>';
          $contextualHelp .= '<li>'.__('Show ad only in single posts or categories archives of certain categories – ad will be shown only on single posts pages or category archive pages of the specified categories', SAM_DOMAIN).'</li>';
          $contextualHelp .= '<li>'.__('Show ad only in single posts or authors archives of certain authors – ad will be shown only on single posts pages or author archive pages of the specified authors', SAM_DOMAIN).'</li>';
          $contextualHelp .= '</ul></p>';
          $contextualHelp .= '<p>'.__('<em>Use the schedule for this ad</em> – if necessary, select checkbox labeled “Use the schedule for this ad” and set start and finish dates of ad campaign.', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p>'.__('<em>Use limitation by hits</em> – if necessary, select checkbox labeled “Use limitation by hits” and set hits limit.', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p>'.__('<em>Use limitation by clicks</em> – if necessary, select checkbox labeled “Use limitation by clicks” and set clicks limit.', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p>'.'<strong>'.__('Prices', SAM_DOMAIN).'</strong>: '.__('Use these parameters to get the statistics of incomes from advertisements placed in your blog. "Price of ad placement per month" - parameter used only for calculating statistic of scheduled ads.', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p><a class="button-secondary" href="http://www.simplelib.com/?p=480" target="_blank">'.__('Manual', SAM_DOMAIN).'</a> ';
          $contextualHelp .= '<a class="button-secondary" href="http://forum.simplelib.com/index.php?board=10.0" target="_blank">'.__('Support Forum', SAM_DOMAIN).'</a></p>';
          $contextualHelp .= '</div>';
        }
        elseif($_GET['mode'] == 'place') {
          $contextualHelp = '<div class="sam-contextual-help">';
          $contextualHelp .= '<p>'.__('Enter a <strong>name</strong> and a <strong>description</strong> of the Ads Place. In principle, it is not mandatory parameters, because these parameters don’t influence anything, but experience suggests that after a while all IDs usually will be forgotten  and such information may be useful.', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p>'.__('<strong>Ads Place Size</strong> – in this version is only for informational purposes only, but in future I plan to use this option. It is desirable to expose the real size.', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p>'.__('<strong>Ads Place Patch</strong> - it’s an ad that will appear in the event that the logic of basic ads outputing of this Ads Place on the current page will not be able to choose a single basic ad for displaying. For example, if all basic announcements are set to displaying only on archives pages or single pages, in this case the patch ad of Ads Place will be shown on the Home page. Conveniently to use the patch ad of Ads Place where you sell the advertising place for a limited time – after the time expiration of ordered ad will appear patch ad. It may be a banner leading to your page of advertisement publication costs or a banner from AdSense.', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p>'.__('Patch can be defined', SAM_DOMAIN);
          $contextualHelp .= '<ul>';
          $contextualHelp .= '<li>'.__('as combination of the image URL and target page URL', SAM_DOMAIN).'</li>';
          $contextualHelp .= '<li>'.__('as HTML code or javascript code', SAM_DOMAIN).'</li>';
          $contextualHelp .= '<li>'.__('as name of Google <a href="https://www.google.com/intl/en/dfp/info/welcome.html" target="_blank">DoubleClick for Publishers</a> (DFP) block', SAM_DOMAIN).'</li>';
          $contextualHelp .= '</ul></p>';
          $contextualHelp .= '<p>'.__('If you select the first option (image mode), tools to download/choosing of downloaded image banner become available for you.', SAM_DOMAIN).'</p>';
          $contextualHelp .= '<p>'.__('<strong>Codes</strong> – as Ads Place can be inserted into the page code not only as widget, but as a short code or by using function, you can use code “before” and “after” for centering or alignment of Ads Place on the place of inserting or for something else you need. Use HTML tags.', SAM_DOMAIN);
          $contextualHelp .= '<p><a class="button-secondary" href="http://www.simplelib.com/?p=480" target="_blank">'.__('Manual', SAM_DOMAIN).'</a> ';
          $contextualHelp .= '<a class="button-secondary" href="http://forum.simplelib.com/index.php?board=10.0" target="_blank">'.__('Support Forum', SAM_DOMAIN).'</a></p>';
          $contextualHelp .= '</div>';
        }
      }
      elseif($screenId == $this->listPage) {
        $contextualHelp = '<div class="sam-contextual-help">';
        $contextualHelp .= '<p><a class="button-secondary" href="http://www.simplelib.com/?p=480" target="_blank">'.__('Manual', SAM_DOMAIN).'</a> ';
        $contextualHelp .= '<a class="button-secondary" href="http://forum.simplelib.com/index.php?board=10.0" target="_blank">'.__('Support Forum', SAM_DOMAIN).'</a></p>';
        $contextualHelp .= '</div>';
      }
      elseif($screenId == $this->settingsPage) {
        $contextualHelp = '<div class="sam-contextual-help">';
        $contextualHelp .= '<p>'.__('<strong>Views per Cycle</strong> – the number of impressions an ad for one cycle of rotation, provided that this ad has maximum weight (the activity). In other words, if the number of hits in the series is 1000, an ad with a weight of 10 will be shown in 1000, and the ad with a weight of 3 will be shown 300 times.', SAM_DOMAIN).'</p>';
        $contextualHelp .= '<p>'.__('Do not set this parameter to a value less than the maximum number of visitors which may simultaneously be on your site – it may violate the logic of rotation.', SAM_DOMAIN).'</p>';
        $contextualHelp .= '<p>'.__('Not worth it, though it has no special meaning, set this parameter to a value greater than the number of hits your web pages during a month. Optimal, perhaps, is the value to the daily shows website pages.', SAM_DOMAIN).'</p>';
        $contextualHelp .= '<p>'.__('<strong>Auto Inserting Settings</strong> - here you can select the Ads Places and allow the display of their ads before and after the  content of single post.', SAM_DOMAIN).'</p>';
        $contextualHelp .= '<p>'.__("<strong>Google DFP Settings</strong> - if you want to use codes of Google DFP rotator, you must allow it's using and define your pub-code.", SAM_DOMAIN).'</p>';
        $contextualHelp .= '<p>'.'<strong>'.__('Statistics Settings', SAM_DOMAIN).'</strong>'.'</p>';
        $contextualHelp .= '<p>'.'<em>'.__('Bots and Crawlers detection', SAM_DOMAIN).'</em>: '.__("For obtaining of more exact indexes of statistics and incomes it is preferable to exclude data about visits of bots and crawlers from the data about all visits of your blog. If enabled and bot or crawler is detected, hits of ads won't be counted. Select accuracy of detection but use with caution - more exact detection requires more server resources.", SAM_DOMAIN).'</p>';
        $contextualHelp .= '<p>'.'<em>'.__('Display of Currency', SAM_DOMAIN).'</em>: '.__("Define display of currency. Auto - auto detection of currency from blog settings. USD, EUR - Forcing the display of currency to U.S. dollars or Euro.", SAM_DOMAIN).'</p>';
        $contextualHelp .= '<p><a class="button-secondary" href="http://www.simplelib.com/?p=480" target="_blank">'.__('Manual', SAM_DOMAIN).'</a> ';
        $contextualHelp .= '<a class="button-secondary" href="http://forum.simplelib.com/index.php?board=10.0" target="_blank">'.__('Support Forum', SAM_DOMAIN).'</a></p>';
        $contextualHelp .= '</div>';
      }
      return $contextualHelp;*/
    }
    
    public function adminEditStyles() {
      wp_enqueue_style('adminEditLayout', SAM_URL.'css/sam-admin-edit.css', false, SAM_VERSION);
      wp_enqueue_style('jquery-ui-css', SAM_URL.'css/jquery-ui-1.8.9.custom.css', false, '1.8.9');
      wp_enqueue_style('ColorPickerCSS', SAM_URL.'css/colorpicker.css');
    }
    
    public function adminSettingsStyles() {
      wp_enqueue_style('adminSettingsLayout', SAM_URL.'css/sam-admin-edit.css', false, SAM_VERSION);
    }
    
    public function adminListStyles() {
      wp_enqueue_style('adminListLayout', SAM_URL.'css/sam-admin-list.css', false, SAM_VERSION);
    }
    
    public function adminEditScripts() {
      $loc = get_locale();
      if(in_array($loc, array('en_GB', 'fr_CH', 'pt_BR', 'sr_SR', 'zh_CN', 'zh_HK', 'zh_TW')))
        $lc = str_replace('_', '-', $loc);
      else $lc = substr($loc, 0, 2);
      wp_enqueue_script('jquery');
      wp_enqueue_script('jquery-ui', SAM_URL.'js/jquery-ui-1.8.9.custom.min.js', array('jquery'), '1.8.9');
      if(file_exists(SAM_PATH.'/js/i18n/jquery.ui.datepicker-'.$lc.'.js'))
        wp_enqueue_script('jquery-ui-locale', SAM_URL.'js/i18n/jquery.ui.datepicker-'.$lc.'.js', array('jquery'), '1.8.9');
      wp_enqueue_script('ColorPicker', SAM_URL.'js/colorpicker.js', array('jquery'));
      wp_enqueue_script('AjaxUpload', SAM_URL.'js/ajaxupload.js', array('jquery'), '3.9');
      wp_enqueue_script('adminEditScript', SAM_URL.'js/sam-admin-edit.js', array('jquery', 'jquery-ui', 'ColorPicker'), SAM_VERSION);
    }

    public function getCategories($valueType = 'array') {
      global $wpdb;
      $tTable = $wpdb->prefix . "terms";
      $ttTable = $wpdb->prefix . "term_taxonomy";
      
      $sql = "SELECT
                $tTable.term_id,
                $tTable.name,
                $ttTable.taxonomy
              FROM
                $tTable
              INNER JOIN $ttTable
                ON $tTable.term_id = $ttTable.term_id
              WHERE
                $ttTable.taxonomy = 'category'";
                
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
    
    public function uploadHandler() {
      $uploaddir = SAM_AD_IMG;  
      $file = $uploaddir . basename($_FILES['uploadfile']['name']);   

      if ( move_uploaded_file( $_FILES['uploadfile']['tmp_name'], $file )) {
        exit("success");  
      } else {
        exit("error");  
      }
    }
    
    public function getStringsHandler() {
      global $wpdb;
      $tTable = $wpdb->prefix . "terms";
      $ttTable = $wpdb->prefix . "term_taxonomy";
      $uTable = $wpdb->prefix . "users";
      $umTable = $wpdb->prefix . "usermeta";
      
      $sql = "SELECT $tTable.name
              FROM $tTable
              INNER JOIN $ttTable
                ON $tTable.term_id = $ttTable.term_id
              WHERE $ttTable.taxonomy = 'category';";
                
      $cats = $wpdb->get_results($sql, ARRAY_A);
      $terms = array();
      
      foreach($cats as $value) array_push($terms, $value['name']);
      
      $sql = "SELECT $tTable.name
              FROM $tTable
              INNER JOIN $ttTable
                ON $tTable.term_id = $ttTable.term_id
              WHERE $ttTable.taxonomy = 'post_tag';";
                
      $ttags = $wpdb->get_results($sql, ARRAY_A);
      $tags = array();
      
      foreach($ttags as $value) array_push($tags, $value['name']);
      
      $sql = "SELECT
                $uTable.user_nicename,
                $uTable.display_name
              FROM
                $uTable
              INNER JOIN $umTable
                ON $uTable.ID = $umTable.user_id
              WHERE
                $umTable.meta_key = 'wp_user_level' AND
                $umTable.meta_value > 1;";
                
      $auth = $wpdb->get_results($sql, ARRAY_A);
      $authors = array();
      
      foreach($auth as $value) array_push($authors, $value['display_name']);
      
      $args = array('public' => true, '_builtin' => false);
      $output = 'objects';
      $operator = 'and';
      $post_types = get_post_types($args, $output, $operator);
      $customs = array();
      
      foreach($post_types as $post_type) array_push($customs, $post_type->name);
      
      $output = array(
        'uploading' => __('Uploading', SAM_DOMAIN).' ...',
        'uploaded' => __('Uploaded.', SAM_DOMAIN),
        'status' => __('Only JPG, PNG or GIF files are allowed', SAM_DOMAIN),
        'file' => __('File', SAM_DOMAIN),
        'path' => SAM_AD_IMG,
        'url' => SAM_AD_URL,
        'cats' => $terms,
        'authors' => $authors,
        'tags' => $tags,
        'customs' => $customs
      );
      $charset = get_bloginfo('charset');
      
      header("Content-type: application/json; charset=$charset"); 
      exit(json_encode($output));
    }
		
		public function doSettingsSections($page) {
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
    
    public function doSettingsFields($page, $section) {
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
    
    public function sanitizeSettings($input) {
      global $wpdb;
      
      $pTable = $wpdb->prefix . "sam_places";
      $sql = "SELECT $pTable.patch_dfp FROM $pTable WHERE $pTable.patch_source = 2";
      $rows = $wpdb->get_results($sql, ARRAY_A);
      $blocks = array();      
      foreach($rows as $value) array_push($blocks, $value['patch_dfp']);
      
      $output = $input;
      $output['dfpBlocks'] = array_unique($blocks);
      return $output;
    }
    
    public function drawGeneralSection() {
      echo '<p>'.__('There are general options.', SAM_DOMAIN).'</p>';
    }
    
    public function drawSingleSection() {
      echo '<p>'.__('Single post/page auto inserting options. Use these parameters for allowing/defining Ads Places which will be automatically inserted before/after post/page content.', SAM_DOMAIN).'</p>';
    }
    
    public function drawDFPSection() {
      echo '<p>'.__('Adjust parameters of your Google DFP account.', SAM_DOMAIN).'</p>';
    }
    
    public function drawStatisticsSection() {
      echo '<p>'.__('Adjust parameters of plugin statistics.', SAM_DOMAIN).'</p>';
    }
		
		public function drawLayoutSection() {
			echo '<p>'.__('This options define layout for Ads Managin Pages.', SAM_DOMAIN).'</p>';
		}
    
    public function drawDeactivateSection() {
			echo '<p>'.__('Are you allow to perform these actions during deactivating plugin?', SAM_DOMAIN).'</p>';
		}
    
    public function drawTextOption( $id, $args ) {
      $settings = parent::getSettings();
      $width = $args['width'];
      ?>
        <input id="<?php echo $id; ?>"
					name="<?php echo SAM_OPTIONS_NAME.'['.$id.']'; ?>"
					type="text"
					value="<?php echo $settings[$id]; ?>"
          style="height: 22px; font-size: 11px; <?php if(!empty($width)) echo 'width: '.$width.'px;' ?>" />
      <?php
    }

    public function drawCheckboxOption( $id, $args ) {
			$settings = parent::getSettings();
			?>
				<input id="<?php echo $id; ?>"
					<?php checked('1', $settings[$id]); ?>
					name="<?php echo SAM_OPTIONS_NAME.'['.$id.']'; ?>"
					type="checkbox"
					value="1" />
			<?php
		}
    
    public function drawSelectOptionX( $id, $args ) {
      global $wpdb;
      $pTable = $wpdb->prefix . "sam_places";
      
      $ids = $wpdb->get_results("SELECT $pTable.id, $pTable.name FROM $pTable WHERE $pTable.trash IS FALSE", ARRAY_A);
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
    
    public function drawRadioOption( $id, $args ) {
      $options = $args['options'];
      $settings = parent::getSettings();
      
      foreach ($options as $key => $option) {
      ?>
        <input type="radio" 
          id="<?php echo $id.'_'.$key; ?>" 
          name="<?php echo SAM_OPTIONS_NAME.'['.$id.']'; ?>" 
          value="<?php echo $key; ?>" 
          <?php checked($key, $settings[$id]); ?> 
          <?php if($key == 'more') disabled('', ini_get("browscap")); ?> />
        <label for="<?php echo $id.'_'.$key; ?>"> 
          <?php echo $option;?>
        </label>&nbsp;&nbsp;&nbsp;&nbsp;        
      <?php
      }
    }
		
		public function samAdminPage() {
      global $wpdb, $wp_version;
      
      $row = $wpdb->get_row('SELECT VERSION()AS ver', ARRAY_A);
      $sqlVersion = $row['ver'];
      $mem = ini_get('memory_limit');
      
      if(!is_dir(SAM_AD_IMG)) mkdir(SAM_AD_IMG);
      ?>
			<div class="wrap">
				<?php screen_icon("options-general"); ?>
				<h2><?php  _e('Simple Ads Manager Settings', SAM_DOMAIN); ?></h2>
				<?php
				/*$shell = $this->checkShell();
        if(!empty($shell)) echo $shell;*/
        include_once('errors.class.php');
        $errors = new samErrors();
        if(!empty($errors->errorString)) echo $errors->errorString;
        if(isset($_GET['settings-updated'])) $updated = $_GET['settings-updated'];
        elseif(isset($_GET['updated'])) $updated = $_GET['updated'];
				if($updated === 'true') {
          parent::getSettings(true);
				  ?>
				  <div class="updated"><p><strong><?php _e("Simple Ads Manager Settings Updated.", SAM_DOMAIN); ?></strong></p></div>
				<?php } else { ?>
				  <div class="clear"></div>
				<?php } ?>
				<form action="options.php" method="post">
          <div id='poststuff' class='metabox-holder has-right-sidebar'>
            <div id="side-info-column" class="inner-sidebar">
              <div class='postbox opened'>
                <h3><?php _e('System Info', SAM_DOMAIN) ?></h3>
                <div class="inside">
                  <p>
                    <?php 
                      //$versions = $this->getVersions(false);
                      //$dbVersion = $versions['db'];
                      //$vData = $this->getVersionData($dbVersion);
                      echo __('Wordpress Version', SAM_DOMAIN).': <strong>'.$wp_version.'</strong><br/>';
                      echo __('SAM Version', SAM_DOMAIN).': <strong>'.SAM_VERSION.'</strong><br/>';
                      echo __('SAM DB Version', SAM_DOMAIN).': <strong>'.SAM_DB_VERSION.'</strong><br/>';
                      echo __('PHP Version', SAM_DOMAIN).': <strong>'.PHP_VERSION.'</strong><br/>';
                      echo __('MySQL Version', SAM_DOMAIN).': <strong>'.$sqlVersion.'</strong><br/>';
                      echo __('Memory Limit', SAM_DOMAIN).': <strong>'.$mem.'</strong>'; 
                    ?>
                  </p>
                  <p>
                    <?php _e('Note! If you have detected a bug, include this data to bug report.', SAM_DOMAIN); ?>
                  </p>
                </div>
              </div>
              <div class='postbox opened'>
                <h3><?php _e('Resources', SAM_DOMAIN) ?></h3>
                <div class="inside">
                  <ul>
                    <li><a target='_blank' href='http://wordpress.org/extend/plugins/simple-ads-manager/'><?php _e("Wordpress Plugin Page", SAM_DOMAIN); ?></a></li>
                    <li><a target='_blank' href='http://www.simplelib.com/?p=480'><?php _e("Author Plugin Page", SAM_DOMAIN); ?></a></li>
                    <li><a target='_blank' href='http://forum.simplelib.com/forumdisplay.php?13-Simple-Ads-Manager/'><?php _e("Support Forum", SAM_DOMAIN); ?></a></li>
                    <li><a target='_blank' href='http://www.simplelib.com/'><?php _e("Author's Blog", SAM_DOMAIN); ?></a></li>
                  </ul>                    
                </div>
              </div>  
              <div class='postbox opened'>
                <h3><?php _e('Donations', SAM_DOMAIN) ?></h3>
                <div class="inside">
                  <p>
                    <?php 
                      $format = __('If you have found this plugin useful, please consider making a %s to help support future development. Your support will be much appreciated. Thank you!', SAM_DOMAIN);
                      $str = '<a title="'.__('Donate Now!', SAM_DOMAIN).'" href="https://load.payoneer.com/LoadToPage.aspx?email=minimus@simplelib.com" target="_blank">'.__('donation', SAM_DOMAIN).'</a>';
                      printf($format, $str); 
                    ?>
                  </p>
                  <div style="text-align: center;">
                    <a title="Donate Now!" href="https://load.payoneer.com/LoadToPage.aspx?email=minimus@simplelib.com" target="_blank">
                      <img  title="<?php _e('Donate Now!', SAM_DOMAIN); ?>" src="<?php echo SAM_IMG_URL.'donate-now.png' ?>" alt="" width="100" height="34" style='margin-right: 5px;' />
                    </a>
                  </div>
                  <p style='margin: 3px; font-size: 0.8em'>
                    <?php 
                      $format = __("Warning! The default value of donation is %s. Don't worry! This is not my appetite, this is default value defined by Payoneer service.", SAM_DOMAIN).'<strong>'.__(' You can change it to any value you want!', SAM_DOMAIN).'</strong>';
                      $str = '<strong>$200</strong>';
                      printf($format, $str);
                    ?>
                  </p>                    
                </div>
              </div>
              <div class='postbox opened'>
                <h3><?php _e('Another Plugins', SAM_DOMAIN) ?></h3>
                <div class="inside">
                  <p>
                    <?php
                    $format = __('Another plugins from %s', SAM_DOMAIN).':';
                    $str = '<strong><a target="_blank" href="http://wordpress.org/extend/plugins/profile/minimus">minimus</a></strong>';
                    printf($format, $str);
                    ?>
                  </p>
                    <ul>
                      <li><a target='_blank' href='http://wordpress.org/extend/plugins/wp-special-textboxes/'><strong>Special Text Boxes</strong></a> - <?php _e("Highlights any portion of text as text in the colored boxes.", SAM_DOMAIN); ?></li>
                      <li><a target='_blank' href='http://wordpress.org/extend/plugins/simple-counters/'><strong>Simple Counters</strong></a> - <?php _e("Adds simple counters badge (FeedBurner subscribers and Twitter followers) to your blog.", SAM_DOMAIN); ?></li>
                      <li><a target='_blank' href='http://wordpress.org/extend/plugins/simple-view/'><strong>Simple View</strong></a> - <?php _e("This plugin is WordPress shell for FloatBox library by Byron McGregor.", SAM_DOMAIN); ?></li>
                      <li><a target='_blank' href='http://wordpress.org/extend/plugins/wp-copyrighted-post/'><strong>Copyrighted Post</strong></a> - <?php _e("Adds copyright notice in the end of each post of your blog. ", SAM_DOMAIN); ?></li>
                    </ul>
                </div>
              </div>
            </div>
            <div id="post-body">
              <div id="post-body-content">
                <?php settings_fields('samOptions'); ?>
                <?php $this->doSettingsSections('sam-settings'); ?>
                <p class="submit">
                  <input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
                </p>
                <p style='color: #777777; font-size: 12px; font-style: italic;'>Simple Ads Manager plugin for Wordpress. Copyright &copy; 2010 - 2011, <a href='http://www.simplelib.com/'>minimus</a>. All rights reserved.</p>
              </div>
            </div>
          </div>
				</form>
			</div>
			<?php
		}
    
    public function addButtons() {
      if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
        return;
      
      if ( get_user_option('rich_editing') == 'true') {
        add_filter("mce_external_plugins", array(&$this, "addTinyMCEPlugin"));
        add_filter('mce_buttons', array(&$this, 'registerButton'));
      }
    }
    
    public function registerButton( $buttons ) {
      $options = $this->getSettings();
      if($options['editorButtonMode'] === 'modern') array_push($buttons, "separator", "samb");
      else array_push($buttons, 'separator', 'sama', 'samp', 'samz', 'samb');
      return $buttons;
    }
    
    public function addTinyMCEPlugin( $plugin_array ) {
      $options = parent::getSettings();
      if($options['editorButtonMode'] === 'modern') $plugin_array['samb'] = SAM_URL.'js/editor_plugin.js';
      else $plugin_array['samb'] = SAM_URL.'js/ep_classic.js';
      return $plugin_array;
    }
    
    public function tinyMCEVersion( $version ) {
      return ++$version;
    }
		
	  public function samTablePage() {
	    include_once('list.admin.class.php');
        $settings = parent::getSettings();
        $list = new SamPlaceList($settings);
        $list->page();
	  }
    
    public function samZoneListPage() {
      include_once('zone.list.admin.class.php');
      $settings = parent::getSettings();
      $list = new SamZoneList($settings);
      $list->page();
    }
    
    public function samBlockListPage() {
      include_once('block.list.admin.class.php');
      $settings = parent::getSettings();
      $list = new SamBlockList($settings);
      $list->page();
    }
		
	  public function samEditPage() {
	    include_once('editor.admin.class.php');
        $settings = parent::getSettings();
        $editor = new SamPlaceEdit($settings);
        $editor->page();
	  }
      
    public function samZoneEditPage() {
      include_once('zone.editor.admin.class.php');
      $settings = parent::getSettings();
      $editor = new SamZoneEditor($settings);
      $editor->page();
    }
    
    public function samBlockEditPage() {
      include_once('block.editor.admin.class.php');
      $settings = parent::getSettings();
      $editor = new SamBlockEditor($settings);
      $editor->page();
    }
  } // end of class definition
} // end of if not class SimpleAdsManager exists
?>