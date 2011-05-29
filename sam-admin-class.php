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
    
    function __construct() {
      parent::__construct();
      
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
      add_filter('contextual_help', array(&$this, 'help'), 10, 3);
      
      $versions = parent::getVersions(true);
      if(empty($version) || ($versions['sam'] !== SAM_VERSION)) self::updateDB();
    }
    
    function onActivate() {
      $settings = parent::getSettings(true);
			update_option( SAM_OPTIONS_NAME, $settings );
			self::updateDB();
    }
    
    function onDeactivate() {
      global $wpdb;
			$zTable = $wpdb->prefix . "sam_zones";
      $pTable = $wpdb->prefix . "sam_places";					
			$aTable = $wpdb->prefix . "sam_ads";
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
				delete_option('sam_db_version');
			}
      if($settings['deleteFolder'] == 1) {
        if(is_dir(SAM_AD_IMG)) rmdir(SAM_AD_IMG);
      }
    }
    
    function updateDB() {
      global $wpdb;
      $pTable = $wpdb->prefix . "sam_places";          
      $aTable = $wpdb->prefix . "sam_ads";
      $zTable = $wpdb->prefix . "sam_zones";
      $bTable = $wpdb->prefix . "sam_blocks";
      
      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      
      $versions = $this->getVersions(true);
      $dbVersion = $versions['db'];
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
                    PRIMARY KEY (id)
                   )";
          dbDelta($pSql);
        }
        elseif($dbVersion == '0.1' || $dbVersion == '0.2') {
          $pSql = "ALTER TABLE $pTable 
                     ADD COLUMN patch_dfp VARCHAR(255) DEFAULT NULL,
                     ADD COLUMN patch_adserver TINYINT(1) DEFAULT 0,
                     ADD COLUMN patch_hits INT(11) DEFAULT 0;";
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
                  ad_target TEXT DEFAULT NULL,
                  count_clicks TINYINT(1) NOT NULL DEFAULT 0,
                  view_type INT(11) DEFAULT 1,
                  view_pages SET('isHome', 'isSingular', 'isSingle', 'isPage', 'isAttachment', 'isSearch', 'is404', 'isArchive', 'isTax', 'isCategory', 'isTag', 'isAuthor', 'isDate') DEFAULT NULL,
                  view_id VARCHAR(255) DEFAULT NULL,
                  ad_cats TINYINT(1) DEFAULT 0,
                  view_cats VARCHAR(255) DEFAULT NULL,
                  ad_authors TINYINT(1) DEFAULT 0,
                  view_authors VARCHAR(255) DEFAULT NULL,
                  x_id TINYINT(1) DEFAULT 0,
                  x_view_id VARCHAR(255) DEFAULT NULL,
                  x_cats TINYINT(1) DEFAULT 0,
                  x_view_cats VARCHAR(255) DEFAULT NULL,
                  x_authors TINYINT(1) DEFAULT 0,
                  x_view_authors VARCHAR(255) DEFAULT NULL,
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
                  PRIMARY KEY (id, pid)
                )";
          dbDelta($aSql);
        }
        elseif($dbVersion == '0.1') {
          $aSql = "ALTER TABLE $aTable 
                      ADD COLUMN ad_cats TINYINT(1) DEFAULT 0,
                      ADD COLUMN ad_authors TINYINT(1) DEFAULT 0,
                      ADD COLUMN view_authors VARCHAR(255) DEFAULT NULL,
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
                      ADD COLUMN x_view_authors VARCHAR(255) DEFAULT NULL;";
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
                      ADD COLUMN x_view_authors VARCHAR(255) DEFAULT NULL;";
          $wpdb->query($aSql);
        }
        elseif($dbVersion == '0.4' || $dbVersion == '0.5') {
          $aSql = "ALTER TABLE $aTable
                      ADD COLUMN x_id TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_id VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_cats TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_cats VARCHAR(255) DEFAULT NULL,
                      ADD COLUMN x_authors TINYINT(1) DEFAULT 0,
                      ADD COLUMN x_view_authors VARCHAR(255) DEFAULT NULL;";
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
                    trash TINYINT(1) DEFAULT 0,
                    PRIMARY KEY (id)
                  );";
          dbDelta($zSql);
        }
        
        /*if($wpdb->get_var("SHOW TABLES LIKE '$bTable'") != $bTable) {
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
                  );";
          dbDelta($bSql);
        }*/
        update_option('sam_db_version', SAM_DB_VERSION);
      }
      update_option('sam_version', SAM_VERSION);
      $this->getVersions(true);
    }
		
		function initSettings() {
			register_setting('samOptions', SAM_OPTIONS_NAME);
      add_settings_section("sam_general_section", __("General Settings", SAM_DOMAIN), array(&$this, "drawGeneralSection"), 'sam-settings');
      add_settings_section("sam_single_section", __("Auto Inserting Settings", SAM_DOMAIN), array(&$this, "drawSingleSection"), 'sam-settings');
      add_settings_section("sam_dfp_section", __("Google DFP Settings", SAM_DOMAIN), array(&$this, "drawDFPSection"), 'sam-settings');
      add_settings_section("sam_statistic_section", __("Statistics Settings", SAM_DOMAIN), array(&$this, "drawStatisticsSection"), 'sam-settings');
      add_settings_section("sam_layout_section", __("Admin Layout", SAM_DOMAIN), array(&$this, "drawLayoutSection"), 'sam-settings');
			add_settings_section("sam_deactivate_section", __("Plugin Deactivating", SAM_DOMAIN), array(&$this, "drawDeactivateSection"), 'sam-settings');
			
      add_settings_field('adCycle', __("Views per Cycle", SAM_DOMAIN), array(&$this, 'drawTextOption'), 'sam-settings', 'sam_general_section', array('description' => __('Number of hits of one ad for a full cycle of rotation (maximal activity).', SAM_DOMAIN)));
      
      add_settings_field('bpAdsId', __("Ads Place before content", SAM_DOMAIN), array(&$this, 'drawSelectOptionX'), 'sam-settings', 'sam_single_section', array('description' => ''));
      add_settings_field('beforePost', __("Allow Ads Place auto inserting before post/page content", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'beforePost', 'checkbox' => true));
      add_settings_field('bpUseCodes', __("Allow using predefined Ads Place HTML codes (before and after codes)", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'bpUseCodes', 'checkbox' => true));
      add_settings_field('apAdsId', __("Ads Place after content", SAM_DOMAIN), array(&$this, 'drawSelectOptionX'), 'sam-settings', 'sam_single_section', array('description' => ''));
      add_settings_field('afterPost', __("Allow Ads Place auto inserting after post/page content", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'afterPost', 'checkbox' => true));
      add_settings_field('apUseCodes', __("Allow using predefined Ads Place HTML codes (before and after codes)", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_single_section', array('label_for' => 'apUseCodes', 'checkbox' => true));
      
      add_settings_field('useDFP', __("Allow using Google DoubleClick for Publishers (DFP) rotator codes", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_dfp_section', array('label_for' => 'useDFP', 'checkbox' => true));
      add_settings_field('dfpPub', __("Google DFP Pub Code", SAM_DOMAIN), array(&$this, 'drawTextOption'), 'sam-settings', 'sam_dfp_section', array('description' => __('Your Google DFP Pub code. i.e:', SAM_DOMAIN).' ca-pub-0000000000000000.', 'width' => 200));
      
      add_settings_field('detectBots', __("Allow Bots and Crawlers detection", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_statistic_section', array('label_for' => 'detectBots', 'checkbox' => true));
      add_settings_field('detectingMode', __("Accuracy of Bots and Crawlers Detection", SAM_DOMAIN), array(&$this, 'drawRadioOption'), 'sam-settings', 'sam_statistic_section', array('description' => __("If bot is detected hits of ads won't be counted. Use with caution! More exact detection requires more server resources.", SAM_DOMAIN), 'options' => array( 'inexact' => __('Inexact detection', SAM_DOMAIN), 'exact' => __('Exact detection', SAM_DOMAIN), 'more' => __('More exact detection', SAM_DOMAIN))));
      add_settings_field('currency', __("Display of Currency", SAM_DOMAIN), array(&$this, 'drawRadioOption'), 'sam-settings', 'sam_statistic_section', array('description' => __("Define display of currency. Auto - auto detection of currency from blog settings. USD, EUR - Forcing the display of currency to U.S. dollars or Euro.", SAM_DOMAIN), 'options' => array( 'auto' => __('Auto', SAM_DOMAIN), 'usd' => __('USD', SAM_DOMAIN), 'euro' => __('EUR', SAM_DOMAIN))));
      
      add_settings_field('placesPerPage', __("Ads Places per Page", SAM_DOMAIN), array(&$this, 'drawTextOption'), 'sam-settings', 'sam_layout_section', array('description' => __('Ads Places Management grid pagination. How many Ads Places will be shown on one page of grid.', SAM_DOMAIN)));
			add_settings_field('itemsPerPage', __("Ads per Page", SAM_DOMAIN), array(&$this, 'drawTextOption'), 'sam-settings', 'sam_layout_section', array('description' => __('Ads of Ads Place Management grid pagination. How many Ads will be shown on one page of grid.', SAM_DOMAIN)));
      
      add_settings_field('deleteOptions', __("Delete plugin options during deactivating plugin", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_deactivate_section', array('label_for' => 'deleteOptions', 'checkbox' => true));
			add_settings_field('deleteDB', __("Delete database tables of plugin during deactivating plugin", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_deactivate_section', array('label_for' => 'deleteDB', 'checkbox' => true));
      add_settings_field('deleteFolder', __("Delete custom images folder of plugin during deactivating plugin", SAM_DOMAIN), array(&$this, 'drawCheckboxOption'), 'sam-settings', 'sam_deactivate_section', array('label_for' => 'deleteFolder', 'checkbox' => true));
      
      register_setting('sam-settings', SAM_OPTIONS_NAME, array(&$this, 'sanitizeSettings'));
		}
    
    function regAdminPage() {
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
      //$this->listBlock = add_submenu_page('sam-list', __('Ads Blocks List', SAM_DOMAIN), __('Ads Blocks', SAM_DOMAIN), 8, 'sam-block-list', array(&$this, 'samBlockListPage'));
      //add_action('admin_print_styles-'.$this->listBlock, array(&$this, 'adminListStyles'));
      //$this->editBlock = add_submenu_page('sam-list', __('Ads Block Editor', SAM_DOMAIN), __('New Block', SAM_DOMAIN), 8, 'sam-block-edit', array(&$this, 'samBlockEditPage'));
      //add_action('admin_print_styles-'.$this->editBlock, array(&$this, 'adminEditStyles'));
			$this->settingsPage = add_submenu_page('sam-list', __('Simple Ads Manager Settings', SAM_DOMAIN), __('Settings', SAM_DOMAIN), 8, 'sam-settings', array(&$this, 'samAdminPage'));
      add_action('admin_print_styles-'.$this->settingsPage, array(&$this, 'adminSettingsStyles'));
		}
    
    function help($contextualHelp, $screenId, $screen) {
      if ($screenId == $this->editPage) {
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
      return $contextualHelp;
    }
    
    function adminEditStyles() {
      wp_enqueue_style('adminEditLayout', SAM_URL.'css/sam-admin-edit.css', false, SAM_VERSION);
      wp_enqueue_style('jquery-ui-css', SAM_URL.'css/jquery-ui-1.8.9.custom.css', false, '1.8.9');
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
      wp_enqueue_script('jquery-ui', SAM_URL.'js/jquery-ui-1.8.9.custom.min.js', array('jquery'), '1.8.9');
      if(file_exists(SAM_PATH.'/js/i18n/jquery.ui.datepicker-'.$lc.'.js'))
        wp_enqueue_script('jquery-ui-locale', SAM_URL.'js/i18n/jquery.ui.datepicker-'.$lc.'.js', array('jquery'), '1.8.9');
      wp_enqueue_script('ColorPicker', SAM_URL.'js/colorpicker.js', array('jquery'));
      wp_enqueue_script('AjaxUpload', SAM_URL.'js/ajaxupload.js', array('jquery'), '3.9');
      wp_enqueue_script('adminEditScript', SAM_URL.'js/sam-admin-edit.js', array('jquery', 'jquery-ui', 'ColorPicker'), SAM_VERSION);
    }

    /**
    * Outputs the name of Ads Place Size.
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
    
    function drawPlacesSelector($places = null, $current = -1, $default = false) {
      if(!is_null($places) && is_array($places)) {      
        if(is_null($current) && !$default) $current = -1;
        if(!$default) {
          ?>
            <option value="-1" <?php selected(-1, $current); ?> ><?php echo ' - '.__('Default', SAM_DOMAIN).' - '; ?></option>
            <option value="0" <?php selected(0, $current); ?> ><?php echo ' - '.__('None', SAM_DOMAIN).' - '; ?></option>
          <?php
        }
        foreach($places as $value) {
          ?>
            <option value="<?php echo $value['id']; ?>" <?php selected($value['id'], $current); ?> ><?php echo $value['name']; ?></option>
          <?php
        }
      }
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
    
    function getTax($type = 'category') {
      if(empty($type)) return;
      
      global $wpdb;
      $tTable = $wpdb->prefix . "terms";
      $ttTable = $wpdb->prefix . "term_taxonomy";
      
      $sql = "SELECT
                $tTable.term_id,
                $tTable.name,
                $tTable.slug,
                $ttTable.taxonomy
              FROM
                $tTable
              INNER JOIN $ttTable
                ON $tTable.term_id = $ttTable.term_id
              WHERE
                $ttTable.taxonomy = '$type' AND $tTable.term_id <> 1;";
      
      $taxonomies = $wpdb->get_results($sql, ARRAY_A);
      
      $output = array();
      foreach($taxonomies as $tax) {
        array_push($output, array('name' => $tax['name'], 'slug' => $tax['slug']));
      }
      return $output;
    }
    
    function getAuthors() {
      global $wpdb;
      $uTable = $wpdb->prefix . "users";
      $umTable = $wpdb->prefix . "usermeta";
      
      $sql = "SELECT
                $uTable.id,
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
      foreach($auth as $value) $authors[$value['display_name']] = $value['id'];
      
      return $authors;
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
      
      $output = array(
        'uploading' => __('Uploading', SAM_DOMAIN).' ...',
        'uploaded' => __('Uploaded.', SAM_DOMAIN),
        'status' => __('Only JPG, PNG or GIF files are allowed', SAM_DOMAIN),
        'file' => __('File', SAM_DOMAIN),
        'path' => SAM_AD_IMG,
        'url' => SAM_AD_URL,
        'cats' => $terms,
        'authors' => $authors
      );
      $charset = get_bloginfo('charset');
      
      header("Content-type: application/json; charset=$charset"); 
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
    
    function sanitizeSettings($input) {
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
    
    function drawGeneralSection() {
      echo '<p>'.__('There are general options.', SAM_DOMAIN).'</p>';
    }
    
    function drawSingleSection() {
      echo '<p>'.__('Single post/page auto inserting options. Use these parameters for allowing/defining Ads Places which will be automatically inserted before/after post/page content.', SAM_DOMAIN).'</p>';
    }
    
    function drawDFPSection() {
      echo '<p>'.__('Adjust parameters of your Google DFP account.', SAM_DOMAIN).'</p>';
    }
    
    function drawStatisticsSection() {
      echo '<p>'.__('Adjust parameters of plugin statistics.', SAM_DOMAIN).'</p>';
    }
		
		function drawLayoutSection() {
			echo '<p>'.__('This options define layout for Ads Managin Pages.', SAM_DOMAIN).'</p>';
		}
    
    function drawDeactivateSection() {
			echo '<p>'.__('Are you allow to perform these actions during deactivating plugin?', SAM_DOMAIN).'</p>';
		}
    
    function drawTextOption( $id, $args ) {
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
    
    function drawRadioOption( $id, $args ) {
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
		
		function samAdminPage() {
      if(!is_dir(SAM_AD_IMG)) mkdir(SAM_AD_IMG);
      ?>
			<div class="wrap">
				<?php screen_icon("options-general"); ?>
				<h2><?php echo __('Simple Ads Manager Settings', SAM_DOMAIN).' ('.SAM_VERSION.')'; ?></h2>
				<?php
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
          if($iaction === 'clear-stats') {
            $wpdb->query("UPDATE $pTable SET $pTable.patch_hits = 0;");
            $wpdb->query("UPDATE $aTable SET $aTable.ad_hits = 0, $aTable.ad_clicks = 0;");
          }
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
    <div class='alignleft'>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=places&mode=<?php echo $mode; ?>&iaction=clear-stats"><?php _e('Reset Statistics', SAM_DOMAIN); ?></a>
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
				<th id="t-name" class="manage-column column-title" style="width:31%;" scope="col"><?php _e('Place Name', SAM_DOMAIN);?></th>
        <th id="t-size" class="manage-column column-title" style="width:15%;" scope="col"><?php _e('Size', SAM_DOMAIN); ?></th>
        <th id="t-size" class="manage-column column-title" style="width:7%;" scope="col"><?php _e('Hits', SAM_DOMAIN); ?></th>
        <th id="t-size" class="manage-column column-title" style="width:7%;" scope="col"><?php _e('Total Hits', SAM_DOMAIN); ?></th>
        <th id="tp-items" class="manage-column column-title" style="width:10%;" scope="col"><div class="vers"><?php _e('Total Ads', SAM_DOMAIN); ?></div></th>
        <th id="tp-earnings" class="manage-column column-title" style="width:15%;" scope="col"><?php _e('Earnings', SAM_DOMAIN); ?></th>				
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th id="b-idg" class="manage-column column-title" style="width:5%;" scope="col"><?php _e('ID', SAM_DOMAIN); ?></th>
				<th id="b-name" class="manage-column column-title" style="width:31%;" scope="col"><?php _e('Place Name', SAM_DOMAIN);?></th>
				<th id="b-size" class="manage-column column-title" style="width:15%;" scope="col"><?php _e('Size', SAM_DOMAIN); ?></th>
        <th id="t-size" class="manage-column column-title" style="width:7%;" scope="col"><?php _e('Hits', SAM_DOMAIN); ?></th>
        <th id="t-size" class="manage-column column-title" style="width:7%;" scope="col"><?php _e('Total Hits', SAM_DOMAIN); ?></th>
				<th id="bp-items" class="manage-column column-title" style="width:10%;" scope="col"><div class="vers"><?php _e('Total Ads', SAM_DOMAIN); ?></div></th>
        <th id="bp-earnings" class="manage-column column-title" style="width:15%;" scope="col"><?php _e('Earnings', SAM_DOMAIN); ?></th>
			</tr>
		</tfoot>
		<tbody>
				<?php
					$pSql = "SELECT 
                      $pTable.id, 
                      $pTable.name, 
                      $pTable.description, 
                      $pTable.place_size, 
                      $pTable.place_custom_width, 
                      $pTable.place_custom_height,
                      $pTable.patch_hits,
                      (IFNULL((SELECT sum($aTable.ad_hits) FROM $aTable WHERE $aTable.pid = $pTable.id), 0) + $pTable.patch_hits) as total_ad_hits,
                      (IFNULL((SELECT SUM(IF(cpm > 0, ad_hits*cpm/1000, 0)) FROM $aTable WHERE $aTable.pid = $pTable.id), 0)) AS e_cpm,
                      (IFNULL((SELECT SUM(IF(cpc > 0, ad_clicks*cpc, 0)) FROM $aTable WHERE $aTable.pid = $pTable.id), 0)) AS e_cpc,
                      (IFNULL((SELECT SUM(IF(ad_schedule AND per_month > 0, DATEDIFF(CURDATE(), ad_start_date)*per_month/30, 0)) FROM $aTable WHERE $aTable.pid = $pTable.id), 0)) AS e_month, 
                      $pTable.trash, 
                      (SELECT COUNT(*) FROM $aTable WHERE $aTable.pid = $pTable.id) AS items 
                    FROM $pTable".
                    (($mode !== 'all') ? " WHERE $pTable.trash = ".(($mode === 'trash') ? 'TRUE' : 'FALSE') : '').
                    " LIMIT $offset, $places_per_page";
          $places = $wpdb->get_results($pSql, ARRAY_A);          
					$i = 0;
					if(!is_array($places) || empty ($places)) {
				?>
			<tr id="g0" class="alternate author-self status-publish iedit" valign="top">
				<th class="post-title column-title">0</th>
        <th class="author column-author"><?php _e('There are no data ...', SAM_DOMAIN).$pTable; ?></th>
			</tr>
				<?php } else {
					switch($options['currency']) {
            case 'auto': $lang = str_replace('-', '_', get_bloginfo('language')); break;
            case 'usd' : $lang = 'en_US'; break;
            case 'euro': $lang = 'de_DE'; break;
            default: $lang = str_replace('-', '_', get_bloginfo('language'));
          }          
          $codeset = get_bloginfo('charset');
          setlocale(LC_MONETARY, $lang.'.'.$codeset);
          foreach($places as $row) {
						$apSize = $this->getAdSize($row['place_size'], $row['place_custom_width'], $row['place_custom_height']);
            $eMonth = round(floatval($row['e_month']), 2);
            $eCPM = round(floatval($row['e_cpm']), 2);
            $eCPC = round(floatval($row['e_cpc']), 2);
            $eTotal = $eMonth + $eCPC + $eCPM;
            $earnings = $eMonth ? __('Placement', SAM_DOMAIN).": ".money_format('%.2n', $eMonth)." <br/>" : '';
            $earnings .= $eCPM ? __('Hits', SAM_DOMAIN).": ".money_format('%.2n', $eCPM)." <br/>" : '';
            $earnings .= $eCPC ? __('Clicks', SAM_DOMAIN).": ".money_format('%.2n', $eCPC)." <br/>" : '';
            $earnings .= $eTotal ? "<strong>".__('Total', SAM_DOMAIN).": ".money_format('%.2n', $eTotal)." </strong>" : __('N/A', SAM_DOMAIN);
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
        <td class="post-title column-title"><div class="post-com-count-wrapper" style="text-align: center;"><?php echo $row['patch_hits'];?></div></td>
        <td class="post-title column-title"><div class="post-com-count-wrapper" style="text-align: center;"><?php echo $row['total_ad_hits'];?></div></td>
				<td class="post-title column-title"><div class="post-com-count-wrapper" style="text-align: center;"><?php echo $row['items'];?></div></td>
        <td class="post-title column-title"><div class='earnings'><?php echo $earnings;?></div></td>
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
    <div class='alignleft'>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-list&action=places&mode=<?php echo $mode; ?>&iaction=clear-stats"><?php _e('Reset Statistics', SAM_DOMAIN); ?></a>
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
            elseif($iaction === 'kill') $wpdb->query("DELETE FROM $aTable WHERE id = $iitem");
					}
          if($iaction === 'kill-em-all') $wpdb->query("DELETE FROM $aTable WHERE trash=true");
					$trash_num = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$aTable.' WHERE (trash = TRUE) AND (pid = '.$item.')'));
					$active_num = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.$aTable.' WHERE (trash = FALSE) AND (pid = '.$item.')'));
					if(is_null($active_num)) $active_num = 0;
					if(is_null($trash_num)) $trash_num = 0;
					$all_num = $trash_num + $active_num;
					$places = $wpdb->get_row("SELECT id, name, trash FROM $pTable WHERE id = $item", ARRAY_A);

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
				<th id="t-ad" class='manage-column column-title' style="width:55%;" scope="col"><?php _e('Advertisement', SAM_DOMAIN); ?></th>
				<th id="t-act" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Activity', SAM_DOMAIN);?></th>
				<th id="t-hits" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Hits', SAM_DOMAIN);?></th>
				<th id="t-clicks" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Clicks', SAM_DOMAIN);?></th>
        <th id="t-earnings" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Earnings', SAM_DOMAIN);?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th id="b-id" class="manage-column column-title" style="width:5%;" scope="col"><?php _e('ID', SAM_DOMAIN); ?></th>
				<th id="b-ad" class='manage-column column-title' style="width:55%;" scope="col"><?php _e('Advertisement', SAM_DOMAIN); ?></th>
				<th id="b-act" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Activity', SAM_DOMAIN);?></th>
				<th id="b-hits" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Hits', SAM_DOMAIN);?></th>
				<th id="b-clicks" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Clicks', SAM_DOMAIN);?></th>
        <th id="b-earnings" class="manage-column column-title" style="width:10%;" scope="col"><?php _e('Earnings', SAM_DOMAIN);?></th>
			</tr>
		</tfoot>
		<tbody>
				<?php
					if($mode !== 'all')
            $aSql = "SELECT 
                      id, 
                      pid, 
                      name, 
                      description, 
                      ad_hits, 
                      ad_clicks, 
                      ad_weight,
                      (IF(ad_schedule AND per_month > 0, DATEDIFF(CURDATE(), ad_start_date)*per_month/30, 0)) AS e_month,
                      (cpm * ad_hits / 1000) AS e_cpm,
                      (cpc * ad_clicks) AS e_cpc, 
                      trash 
                     FROM $aTable 
                     WHERE (pid = $item) AND (trash = ".(($mode === 'trash') ? 'TRUE' : 'FALSE').")
                     LIMIT $offset, $items_per_page";
					else
						$aSql = "SELECT 
                      id, 
                      pid, 
                      name, 
                      description, 
                      ad_hits, 
                      ad_clicks, 
                      ad_weight,
                      (IF(ad_schedule AND per_month > 0, DATEDIFF(CURDATE(), ad_start_date)*per_month/30, 0)) AS e_month,
                      (cpm * ad_hits / 1000) AS e_cpm,
                      (cpc * ad_clicks) AS e_cpc, 
                      trash 
                     FROM $aTable 
                     WHERE pid = $item 
                     LIMIT $offset, $items_per_page";
          $items = $wpdb->get_results($aSql, ARRAY_A);
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
					  switch($options['currency']) {
              case 'auto': $lang = str_replace('-', '_', get_bloginfo('language')); break;
              case 'usd' : $lang = 'en_US'; break;
              case 'euro': $lang = 'de_DE'; break;
              default: $lang = str_replace('-', '_', get_bloginfo('language'));
            }          
            $codeset = get_bloginfo('charset');
            setlocale(LC_MONETARY, $lang.'.'.$codeset);
            foreach($items as $row) {
						  if($row['ad_weight'] > 0) $activity = __('Yes', SAM_DOMAIN);
              else $activity = __('No', SAM_DOMAIN);
              $eMonth = round(floatval($row['e_month']), 2);
              $eCPM = round(floatval($row['e_cpm']), 2);
              $eCPC = round(floatval($row['e_cpc']), 2);
              $eTotal = $eMonth + $eCPC + $eCPM;
              $earnings = $eMonth ? __('Placement', SAM_DOMAIN).": ".money_format('%.2n', $eMonth)." <br/>" : '';
              $earnings .= $eCPM ? __('Hits', SAM_DOMAIN).": ".money_format('%.2n', $eCPM)." <br/>" : '';
              $earnings .= $eCPC ? __('Clicks', SAM_DOMAIN).": ".money_format('%.2n', $eCPC)." <br/>" : '';
              $earnings .= $eTotal ? "<strong>".__('Total', SAM_DOMAIN).": ".money_format('%.2n', $eTotal)." </strong>" : __('N/A', SAM_DOMAIN);
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
        <td class="post-title column-title"><div class='earnings'><?php echo $earnings;?></div></td>
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
    
    function samZoneListPage() {
      global $wpdb;
      $zTable = $wpdb->prefix . "sam_zones";
      
      if(isset($_GET['mode'])) $mode = $_GET['mode'];
      else $mode = 'active';
      if(isset($_GET["action"])) $action = $_GET['action'];
      else $action = 'zones';
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
      
      if(!is_null($item)) {
        if($iaction === 'delete') $wpdb->update( $zTable, array( 'trash' => true ), array( 'id' => $item ), array( '%d' ), array( '%d' ) );
        elseif($iaction === 'untrash') $wpdb->update( $zTable, array( 'trash' => false ), array( 'id' => $item ), array( '%d' ), array( '%d' ) );
        elseif($iaction === 'kill') $wpdb->query("DELETE FROM $zTable WHERE id=$item");
      }
      if($iaction === 'kill-em-all') $wpdb->query("DELETE FROM $zTable WHERE trash=true");
      $trash_num = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $zTable WHERE trash = TRUE"));
      $active_num = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $zTable WHERE trash = FALSE"));
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
  <h2><?php _e('Managing Ads Zones', SAM_DOMAIN); ?></h2>
  <ul class="subsubsub">
    <li><a <?php if($mode === 'all') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-list&action=zones&mode=all"><?php _e('All', SAM_DOMAIN); ?></a> (<?php echo $all_num; ?>) | </li>
    <li><a <?php if($mode === 'active') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-list&action=zones&mode=active"><?php _e('Active', SAM_DOMAIN); ?></a> (<?php echo $active_num; ?>) | </li>
    <li><a <?php if($mode === 'trash') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-list&action=zones&mode=trash"><?php _e('Trash', SAM_DOMAIN); ?></a> (<?php echo $trash_num; ?>)</li>
  </ul>
  <div class="tablenav">
    <div class="alignleft">
      <?php if($mode === 'trash') {?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-list&action=zones&mode=trash&iaction=kill-em-all"><?php _e('Clear Trash', SAM_DOMAIN); ?></a>
      <?php } else { ?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-edit&action=new&mode=zone"><?php _e('Add New Zone', SAM_DOMAIN); ?></a>
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
        <th id="t-name" class="manage-column column-title" style="width:95%;" scope="col"><?php _e('Zone Name', SAM_DOMAIN);?></th>        
      </tr>
    </thead>
    <tfoot>
      <tr>
        <th id="b-idg" class="manage-column column-title" style="width:5%;" scope="col"><?php _e('ID', SAM_DOMAIN); ?></th>
        <th id="b-name" class="manage-column column-title" style="width:95%;" scope="col"><?php _e('Zone Name', SAM_DOMAIN);?></th>
      </tr>
    </tfoot>
    <tbody>
      <?php
      $zSql = "SELECT 
                  $zTable.id, 
                  $zTable.name, 
                  $zTable.description,
                  $zTable.trash 
                FROM $zTable".
                (($mode !== 'all') ? " WHERE $zTable.trash = ".(($mode === 'trash') ? 'TRUE' : 'FALSE') : '').
                " LIMIT $offset, $places_per_page";
      $zones = $wpdb->get_results($zSql, ARRAY_A);          
      $i = 0;
      if(!is_array($zones) || empty ($zones)) {
      ?>
      <tr id="g0" class="alternate author-self status-publish iedit" valign="top">
        <th class="post-title column-title">0</th>
        <th class="author column-author"><?php _e('There are no data ...', SAM_DOMAIN).$pTable; ?></th>
      </tr>
        <?php } else {
          foreach($zones as $row) {            
        ?>
      <tr id="<?php echo $row['id'];?>" class="<?php echo (($i & 1) ? 'alternate' : ''); ?> author-self status-publish iedit" valign="top">
        <th class="post-title column-title"><?php echo $row['id']; ?></th>
        <td class="post-title column-title">
          <strong style='display: inline;'><a href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-edit&action=edit&mode=zone&item=<?php echo $row['id']; ?>"><?php echo $row['name'];?></a><?php echo ((($row['trash'] == true) && ($mode === 'all')) ? '<span class="post-state"> - '.__('in Trash', SAM_DOMAIN).'</span>' : ''); ?></strong><br/><?php echo $row['description'];?>
          <div class="row-actions">
            <span class="edit"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-edit&action=edit&mode=zone&item=<?php echo $row['id']; ?>" title="<?php _e('Edit Zone', SAM_DOMAIN) ?>"><?php _e('Edit', SAM_DOMAIN); ?></a> | </span>
            <?php 
            if($row['trash'] == true) { 
              ?>
              <span class="untrash"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-list&action=zones&mode=<?php echo $mode ?>&iaction=untrash&item=<?php echo $row['id'] ?>" title="<?php _e('Restore this Zone from the Trash', SAM_DOMAIN) ?>"><?php _e('Restore', SAM_DOMAIN); ?></a> | </span>
              <span class="delete"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-list&action=zones&mode=<?php echo $mode ?>&iaction=kill&item=<?php echo $row['id'] ?>" title="<?php _e('Remove this Zone permanently', SAM_DOMAIN) ?>"><?php _e('Remove permanently', SAM_DOMAIN); ?></a></span>
            <?php 
            } 
            else { 
              ?>
              <span class="delete"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-list&action=zones&mode=<?php echo $mode ?>&iaction=delete&item=<?php echo $row['id'] ?>" title="<?php _e('Move this Zone to the Trash', SAM_DOMAIN) ?>"><?php _e('Delete', SAM_DOMAIN); ?></a></span>
            <?php } ?>
          </div>
        </td>
      </tr>
        <?php $i++; }}?>
    </tbody>
  </table>
  <div class="tablenav">
    <div class="alignleft">
      <?php if($mode === 'trash') {?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-list&action=zones&mode=trash&iaction=kill-em-all"><?php _e('Clear Trash', SAM_DOMAIN); ?></a>
      <?php } else { ?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-zone-edit&action=new&mode=zone"><?php _e('Add New Zone', SAM_DOMAIN); ?></a>      
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
    }
    
    /*function samBlockListPage() {
      global $wpdb;
      $bTable = $wpdb->prefix . "sam_blocks";
      
      if(isset($_GET['mode'])) $mode = $_GET['mode'];
      else $mode = 'active';
      if(isset($_GET["action"])) $action = $_GET['action'];
      else $action = 'blocks';
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
      
      if(!is_null($item)) {
        if($iaction === 'delete') $wpdb->update( $bTable, array( 'trash' => true ), array( 'id' => $item ), array( '%d' ), array( '%d' ) );
        elseif($iaction === 'untrash') $wpdb->update( $bTable, array( 'trash' => false ), array( 'id' => $item ), array( '%d' ), array( '%d' ) );
        elseif($iaction === 'kill') $wpdb->query("DELETE FROM $bTable WHERE id=$item");
      }
      if($iaction === 'kill-em-all') $wpdb->query("DELETE FROM $bTable WHERE trash=true");
      $trash_num = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bTable WHERE trash = TRUE"));
      $active_num = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $bTable WHERE trash = FALSE"));
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
  <h2><?php _e('Managing Ads Blocks', SAM_DOMAIN); ?></h2>
  <ul class="subsubsub">
    <li><a <?php if($mode === 'all') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-block-list&action=blocks&mode=all"><?php _e('All', SAM_DOMAIN); ?></a> (<?php echo $all_num; ?>) | </li>
    <li><a <?php if($mode === 'active') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-block-list&action=blocks&mode=active"><?php _e('Active', SAM_DOMAIN); ?></a> (<?php echo $active_num; ?>) | </li>
    <li><a <?php if($mode === 'trash') echo 'class="current"';?> href="<?php echo admin_url('admin.php'); ?>?page=sam-block-list&action=blocks&mode=trash"><?php _e('Trash', SAM_DOMAIN); ?></a> (<?php echo $trash_num; ?>)</li>
  </ul>
  <div class="tablenav">
    <div class="alignleft">
      <?php if($mode === 'trash') {?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-block-list&action=blocks&mode=trash&iaction=kill-em-all"><?php _e('Clear Trash', SAM_DOMAIN); ?></a>
      <?php } else { ?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-block-edit&action=new&mode=block"><?php _e('Add New Block', SAM_DOMAIN); ?></a>
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
        <th id="t-name" class="manage-column column-title" style="width:95%;" scope="col"><?php _e('Block Name', SAM_DOMAIN);?></th>        
      </tr>
    </thead>
    <tfoot>
      <tr>
        <th id="b-idg" class="manage-column column-title" style="width:5%;" scope="col"><?php _e('ID', SAM_DOMAIN); ?></th>
        <th id="b-name" class="manage-column column-title" style="width:95%;" scope="col"><?php _e('Block Name', SAM_DOMAIN);?></th>
      </tr>
    </tfoot>
    <tbody>
      <?php
      $bSql = "SELECT 
                  $bTable.id, 
                  $bTable.name, 
                  $bTable.description,
                  $bTable.trash 
                FROM $bTable".
                (($mode !== 'all') ? " WHERE $bTable.trash = ".(($mode === 'trash') ? 'TRUE' : 'FALSE') : '').
                " LIMIT $offset, $places_per_page";
      $blocks = $wpdb->get_results($bSql, ARRAY_A);          
      $i = 0;
      if(!is_array($blocks) || empty ($blocks)) {
      ?>
      <tr id="g0" class="alternate author-self status-publish iedit" valign="top">
        <th class="post-title column-title">0</th>
        <th class="author column-author"><?php _e('There are no data ...', SAM_DOMAIN).$pTable; ?></th>
      </tr>
        <?php } else {
          foreach($blocks as $row) {            
        ?>
      <tr id="<?php echo $row['id'];?>" class="<?php echo (($i & 1) ? 'alternate' : ''); ?> author-self status-publish iedit" valign="top">
        <th class="post-title column-title"><?php echo $row['id']; ?></th>
        <td class="post-title column-title">
          <strong style='display: inline;'><a href="<?php echo admin_url('admin.php'); ?>?page=sam-block-edit&action=edit&mode=block&item=<?php echo $row['id']; ?>"><?php echo $row['name'];?></a><?php echo ((($row['trash'] == true) && ($mode === 'all')) ? '<span class="post-state"> - '.__('in Trash', SAM_DOMAIN).'</span>' : ''); ?></strong><br/><?php echo $row['description'];?>
          <div class="row-actions">
            <span class="edit"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-block-edit&action=edit&mode=block&item=<?php echo $row['id']; ?>" title="<?php _e('Edit Zone', SAM_DOMAIN) ?>"><?php _e('Edit', SAM_DOMAIN); ?></a> | </span>
            <?php 
            if($row['trash'] == true) { 
              ?>
              <span class="untrash"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-block-list&action=blocks&mode=<?php echo $mode ?>&iaction=untrash&item=<?php echo $row['id'] ?>" title="<?php _e('Restore this Block from the Trash', SAM_DOMAIN) ?>"><?php _e('Restore', SAM_DOMAIN); ?></a> | </span>
              <span class="delete"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-block-list&action=blocks&mode=<?php echo $mode ?>&iaction=kill&item=<?php echo $row['id'] ?>" title="<?php _e('Remove this Block permanently', SAM_DOMAIN) ?>"><?php _e('Remove permanently', SAM_DOMAIN); ?></a></span>
            <?php 
            } 
            else { 
              ?>
              <span class="delete"><a href="<?php echo admin_url('admin.php'); ?>?page=sam-block-list&action=blocks&mode=<?php echo $mode ?>&iaction=delete&item=<?php echo $row['id'] ?>" title="<?php _e('Move this Block to the Trash', SAM_DOMAIN) ?>"><?php _e('Delete', SAM_DOMAIN); ?></a></span>
            <?php } ?>
          </div>
        </td>
      </tr>
        <?php $i++; }}?>
    </tbody>
  </table>
  <div class="tablenav">
    <div class="alignleft">
      <?php if($mode === 'trash') {?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-block-list&action=blocks&mode=trash&iaction=kill-em-all"><?php _e('Clear Trash', SAM_DOMAIN); ?></a>
      <?php } else { ?>
      <a class="button-secondary" href="<?php echo admin_url('admin.php'); ?>?page=sam-block-edit&action=new&mode=block"><?php _e('Add New Block', SAM_DOMAIN); ?></a>      
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
    }*/
		
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
              'patch_adserver' => $_POST['patch_adserver'],
              'patch_dfp' => $_POST['patch_dfp'],
							'patch_source' => $_POST['patch_source'],
							'trash' => ($_POST['trash'] === 'true')
						);
						$formatRow = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d');
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
            $newOptions = $this->sanitizeSettings($options);
            update_option( SAM_OPTIONS_NAME, $newOptions );
						?>
<div class="updated"><p><strong><?php _e("Ads Place Data Updated.", SAM_DOMAIN);?></strong></p></div>
						<?php
					}

          $aSize = array();
					
					if($action !== 'new') {
						$row = $wpdb->get_row("SELECT id, name, description, code_before, code_after, place_size, place_custom_width, place_custom_height, patch_img, patch_link, patch_code, patch_adserver, patch_dfp, patch_source, trash FROM ".$pTable." WHERE id = ".$item, ARRAY_A);
            if($row['place_size'] === 'custom') $aSize = $this->getAdSize($row['place_size'], $row['place_custom_width'], $row['place_custom_height']);
            else $aSize = $this->getAdSize ($row['place_size']);
					}
					else {
						if($updated) {
							$row = $wpdb->get_row("SELECT id, name, description, code_before, code_after, place_size, place_custom_width, place_custom_height, patch_img, patch_link, patch_code, patch_adserver, patch_dfp, patch_source, trash FROM ".$pTable." WHERE id = ".$item, ARRAY_A);
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
                'patch_adserver' => 0,
                'patch_dfp' => '',
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
                    <input type='checkbox' name='patch_adserver' id='patch_adserver' value='1' <?php checked(1, $row['patch_adserver']); ?> >
                    <label for='patch_adserver'><?php _e('This is one-block code of third-party AdServer rotator. Selecting this checkbox prevents displaying contained ads.', SAM_DOMAIN); ?></label>
                  </p>
								  <p>
									  <?php _e('This is a HTML-code patch of advertising space. For example: use the code to display AdSense advertisement. ', SAM_DOMAIN); ?>
								  </p>
                </div>
                <div class='clear-line'></div>
                <p>
                  <label for="patch_source_dfp"><input type="radio" id="patch_source_dfp" name="patch_source" value="2" <?php if($row['patch_source'] == '2') { echo 'checked="checked"'; } ?> />&nbsp;<?php _e('Google DFP', SAM_DOMAIN); ?></label>&nbsp;&nbsp;&nbsp;&nbsp;
                </p>
                <div class='radio-content'>
                  <p>
                    <label for="patch_dfp"><?php echo __('DFP Block Name', SAM_DOMAIN).':'; ?></label>
                    <input type='text' name='patch_dfp' id='patch_dfp' value='<?php echo $row['patch_dfp']; ?>'>
                  </p>
                  <p>
                    <?php _e('This is name of Google DFP block!', SAM_DOMAIN); ?>
                  </p>
                </div>              
                <p><?php _e('The patch (default advertisement) will be shown that if the logic of the plugin can not show any contained advertisement on the current page of the document.', SAM_DOMAIN); ?></p>
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
              'ad_cats' => $_POST['ad_cats'],
              'view_cats' => $this->removeTrailingComma( $_POST['view_cats'] ),
              'ad_authors' => $_POST['ad_authors'],
              'view_authors' => $this->removeTrailingComma( $_POST['view_authors'] ),
              'x_id' => $_POST['x_id'],
              'x_view_id' => $_POST['x_view_id'],
              'x_cats' => $_POST['x_cats'],
              'x_view_cats' => $this->removeTrailingComma($_POST['x_view_cats']),
              'x_authors' => $_POST['x_authors'],
              'x_view_authors' => $this->removeTrailingComma($_POST['x_view_authors']),
              'ad_start_date' => $_POST['ad_start_date'],
              'ad_end_date' => $_POST['ad_end_date'],              
              'ad_schedule' => $_POST['ad_schedule'],
              'ad_weight' => $_POST['ad_weight'],
              'limit_hits' => $_POST['limit_hits'],
              'hits_limit' => $_POST['hits_limit'],
              'limit_clicks' => $_POST['limit_clicks'],
              'clicks_limit' => $_POST['clicks_limit'],
              'cpm' => $_POST['cpm'],
              'cpc' => $_POST['cpc'],
              'per_month' => $_POST['per_month'],
              'trash' => ($_POST['trash'] === 'true')
            );
            $formatRow = array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d');
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
                      (SELECT place_size FROM $pTable WHERE $pTable.id = $aTable.pid) AS ad_size,
                      (SELECT place_custom_width FROM $pTable WHERE $pTable.id = $aTable.pid) AS ad_custom_width,
                      (SELECT place_custom_height FROM $pTable WHERE $pTable.id = $aTable.pid) AS ad_custom_height, 
                      view_type, 
                      (view_pages+0) AS view_pages, 
                      view_id,
                      ad_cats,
                      view_cats,
                      ad_authors,
                      view_authors,
                      x_id,
                      x_view_id,
                      x_cats,
                      x_view_cats,
                      x_authors,
                      x_view_authors, 
                      ad_start_date, 
                      ad_end_date, 
                      ad_schedule,
                      limit_hits,
                      hits_limit,
                      limit_clicks,
                      clicks_limit, 
                      ad_hits, 
                      ad_clicks, 
                      ad_weight, 
                      ad_weight_hits,
                      cpm,
                      cpc,
                      per_month, 
                      trash 
                  FROM $aTable WHERE id = $item", 
              ARRAY_A);
              
            if($row['ad_size'] === 'custom') $aSize = $this->getAdSize($row['ad_size'], $row['ad_custom_width'], $row['ad_custom_height']);
            else $aSize = $this->getAdSize($row['ad_size']);  
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
              'ad_cats' => 0,
              'view_cats' => '',
              'ad_authors' => 0,
              'view_authors' => '',
              'x_id' => 0,
              'x_view_id' => '',
              'x_cats' => 0,
              'x_view_cats' => '',
              'x_authors' => 0,
              'x_view_authors' => '',
              'ad_start_date' => '',
              'ad_end_date' => '',              
              'ad_schedule' => 0,
              'limit_hits' => 0,
              'hits_limit' => 0,
              'limit_clicks' => 0,
              'clicks_limit' => 0,
              'ad_hits' => 0,
              'ad_clicks' => 0,
              'ad_weight' => 10,
              'ad_weight_hits' => 0,
              'cpm' => 0.0,
              'cpc' => 0.0,
              'per_month' => 0.0,
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
    <h2><?php echo ( ( $action === 'new' ) ? __('New advertisement', SAM_DOMAIN) : __('Edit advertisement', SAM_DOMAIN).' ('.$item.')' ); ?></h2>
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
                      <label for="item_id_info"><?php echo __('Advertisement ID', SAM_DOMAIN).':'; ?></label>
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
              <h3 class="hndle"><span><?php _e('Advertisement Description', SAM_DOMAIN);?></span></h3>
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
                    <label for='count_clicks'><?php _e('Count clicks for this advertisement', SAM_DOMAIN); ?></label>
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
                  <?php _e('0 - ad is inactive, 1 - minimal activity of this advertisement, 10 - maximal activity of this ad.', SAM_DOMAIN); ?>
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
                <p>
                  <input type='radio' name='view_type' id='view_type_2' value='2' <?php checked(2, $row['view_type']); ?>>
                  <label for='view_type_2'><strong><?php echo __('Show ad only in certain posts/pages', SAM_DOMAIN).':'; ?></strong></label>
                </p>
                <div class='radio-content'>
                  <p>
                    <label for='view_id'><strong><?php echo __('Posts/Pages IDs (comma separated)', SAM_DOMAIN).':'; ?></strong></label>
                    <input type='text' name='view_id' id='view_id' value='<?php echo $row['view_id']; ?>' style='width: 100%;'>
                  </p>                  
                </div>
                <p>
                  <?php _e('Use this setting to display an ad only in certain posts/pages. Enter the IDs of posts/pages, separated by commas.', SAM_DOMAIN); ?>
                </p>
              </div>
            </div>
          </div>
          <div id="contents" class="meta-box-sortables ui-sortable">
            <div id="codediv" class="postbox ">
              <div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
              <h3 class="hndle"><span><?php _e('Extended restrictions of advertisements showing', SAM_DOMAIN);?></span></h3>
              <div class="inside"> 
                <p>
                  <input type='checkbox' name='x_id' id='x_id' value='1' <?php checked(1, $row['x_id']); ?>>
                  <label for='x_id'><strong><?php echo __('Do not show ad on certain posts/pages', SAM_DOMAIN).':'; ?></strong></label>
                </p>
                <div class='radio-content'>
                  <p>
                    <label for='x_view_id'><strong><?php echo __('Posts/Pages IDs (comma separated)', SAM_DOMAIN).':'; ?></strong></label>
                    <input type='text' name='x_view_id' id='x_view_id' value='<?php echo $row['x_view_id']; ?>' style='width: 100%;'>
                  </p>                  
                </div>
                <p>
                  <?php _e('Use this setting to not display an ad on certain posts/pages. Enter the IDs of posts/pages, separated by commas.', SAM_DOMAIN); ?>
                </p>
                <div class='clear-line'></div>
                <p>
                  <input type='checkbox' name='ad_cats' id='ad_cats' value='1' <?php checked(1, $row['ad_cats']); ?>>
                  <label for='ad_cats'><strong><?php echo __('Show ad only in single posts or categories archives of certain categories', SAM_DOMAIN).':'; ?></strong></label>
                </p>
                <div class='radio-content'>
                  <label for='view_cats'><strong><?php echo __('Categories (comma separated)', SAM_DOMAIN).':'; ?></strong></label>
                  <input type='text' name='view_cats' id='view_cats' autocomplete="off" value='<?php echo $row['view_cats']; ?>' style="width:100%">                  
                </div>
                <p>
                  <?php _e('Use this setting to display an ad only in single posts or categories archives of certain categories. Enter the names of categories, separated by commas.', SAM_DOMAIN); ?>
                </p>
                <div class='sam-warning'>
                  <p>
                    <?php _e('This display logic parameter will be applied only when you use the "Show ad on all pages of blog" and "Show your ad only on the pages of this type" modes. Otherwise, it will be ignored.', SAM_DOMAIN); ?>
                  </p>
                </div>
                <div class='clear-line'></div>
                <p>
                  <input type='checkbox' name='x_cats' id='x_cats' value='1' <?php checked(1, $row['x_cats']); ?>>
                  <label for='ad_cats'><strong><?php echo __('Do not show ad in single posts or categories archives of certain categories', SAM_DOMAIN).':'; ?></strong></label>
                </p>
                <div class='radio-content'>
                  <label for='x_view_cats'><strong><?php echo __('Categories (comma separated)', SAM_DOMAIN).':'; ?></strong></label>
                  <input type='text' name='x_view_cats' id='x_view_cats' autocomplete="off" value='<?php echo $row['x_view_cats']; ?>' style="width:100%">                  
                </div>
                <p>
                  <?php _e('Use this setting to not display an ad in single posts or categories archives of certain categories. Enter the names of categories, separated by commas.', SAM_DOMAIN); ?>
                </p>
                <div class='clear-line'></div>
                <p>
                  <input type='checkbox' name='ad_authors' id='ad_authors' value='1' <?php checked(1, $row['ad_authors']); ?>>
                  <label for='ad_authors'><strong><?php echo __('Show ad only in single posts or authors archives of certain authors', SAM_DOMAIN).':'; ?></strong></label>
                </p>
                <div class='radio-content'>
                  <label for='view_authors'><strong><?php echo __('Authors (comma separated)', SAM_DOMAIN).':'; ?></strong></label>
                  <input type='text' name='view_authors' id='view_authors' autocomplete="off" value='<?php echo $row['view_authors']; ?>' style="width:100%">                  
                </div>
                <p>
                  <?php _e('Use this setting to display an ad only in single posts or authors archives of certain authors. Enter the names of authors, separated by commas.', SAM_DOMAIN); ?>
                </p>
                <div class='sam-warning'>
                  <p>
                    <?php _e('This display logic parameter will be applied only when you use the "Show ad on all pages of blog" and "Show your ad only on the pages of this type" modes. Otherwise, it will be ignored.', SAM_DOMAIN); ?>
                  </p>
                </div>
                <div class='clear-line'></div>
                <p>
                  <input type='checkbox' name='x_authors' id='x_authors' value='1' <?php checked(1, $row['x_authors']); ?>>
                  <label for='x_authors'><strong><?php echo __('Do not show ad in single posts or authors archives of certain authors', SAM_DOMAIN).':'; ?></strong></label>
                </p>
                <div class='radio-content'>
                  <label for='x_view_authors'><strong><?php echo __('Authors (comma separated)', SAM_DOMAIN).':'; ?></strong></label>
                  <input type='text' name='x_view_authors' id='x_view_authors' autocomplete="off" value='<?php echo $row['x_view_authors']; ?>' style="width:100%">                  
                </div>
                <p>
                  <?php _e('Use this setting to not display an ad in single posts or authors archives of certain authors. Enter the names of authors, separated by commas.', SAM_DOMAIN); ?>
                </p>
                <div class='clear-line'></div>
                <p>
                  <input type='checkbox' name='ad_schedule' id='ad_schedule' value='1' <?php checked(1, $row['ad_schedule']); ?>>
                  <label for='ad_schedule'><strong><?php _e('Use the schedule for this ad', SAM_DOMAIN); ?></strong></label>
                </p>
                <p>
                  <label for='ad_start_date'><?php echo __('Campaign Start Date', SAM_DOMAIN).':' ?></label>
                  <input type='text' name='ad_start_date' id='ad_start_date' value='<?php echo $row['ad_start_date']; ?>'>
                </p>
                <p>
                  <label for='ad_end_date'><?php echo __('Campaign End Date', SAM_DOMAIN).':' ?></label>
                  <input type='text' name='ad_end_date' id='ad_end_date' value='<?php echo $row['ad_end_date']; ?>'>
                </p>
                <p>
                  <?php _e('Use these parameters for displaying ad during the certain period of time.', SAM_DOMAIN); ?>
                </p>
                <div class='clear-line'></div>
                <p>
                  <input type='checkbox' name='limit_hits' id='limit_hits' value='1' <?php checked(1, $row['limit_hits']); ?>>
                  <label for='limit_hits'><strong><?php _e('Use limitation by hits', SAM_DOMAIN); ?></strong></label>
                </p>
                <p>
                  <label for='hits_limit'><?php echo __('Hits Limit', SAM_DOMAIN).':' ?></label>
                  <input type='text' name='hits_limit' id='hits_limit' value='<?php echo $row['hits_limit']; ?>'>
                </p>
                <p>
                  <?php _e('Use this parameter for limiting displaying of ad by hits.', SAM_DOMAIN); ?>
                </p><div class='clear-line'></div>
                <p>
                  <input type='checkbox' name='limit_clicks' id='limit_clicks' value='1' <?php checked(1, $row['limit_clicks']); ?>>
                  <label for='limit_clicks'><strong><?php _e('Use limitation by clicks', SAM_DOMAIN); ?></strong></label>
                </p>
                <p>
                  <label for='clicks_limit'><?php echo __('Clicks Limit', SAM_DOMAIN).':' ?></label>
                  <input type='text' name='clicks_limit' id='clicks_limit' value='<?php echo $row['clicks_limit']; ?>'>
                </p>
                <p>
                  <?php _e('Use this parameter for limiting displaying of ad by clicks.', SAM_DOMAIN); ?>
                </p>                
              </div>
            </div>
          </div>
          <div id="contents" class="meta-box-sortables ui-sortable">
            <div id="codediv" class="postbox ">
              <div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
              <h3 class="hndle"><span><?php _e('Prices', SAM_DOMAIN);?></span></h3>
              <div class="inside">
                <p>
                  <label for='per_month'><strong><?php echo __('Price of ad placement per month', SAM_DOMAIN).':' ?></strong></label>
                  <input type='text' name='per_month' id='per_month' value='<?php echo $row['per_month']; ?>'>
                </p>
                <p>
                  <?php _e('Tthis parameter used only for scheduled ads.', SAM_DOMAIN) ?>
                </p>
                <p>
                  <label for='cpm'><strong><?php echo __('Price per Thousand Hits', SAM_DOMAIN).':' ?></strong></label>
                  <input type='text' name='cpm' id='cpm' value='<?php echo $row['cpm']; ?>'>
                </p>
                <p>
                  <?php _e('Not only humans visit your blog, bots and crawlers too. In order not to deceive an advertiser, you must enable the detection of bots and crawlers.', SAM_DOMAIN); ?>
                </p>
                <p>
                  <label for='cpc'><strong><?php echo __('Price per Click', SAM_DOMAIN).':' ?></strong></label>
                  <input type='text' name='cpc' id='cpc' value='<?php echo $row['cpc']; ?>'>
                </p>
                <p>
                  <?php _e('To calculate the earnings on clicks, you must enable counting of clicks for that ad.', SAM_DOMAIN); ?>
                </p>
              </div>
            </div>
          </div>
          <?php if($action !== 'new') { ?>
          <div id="sources" class="meta-box-sortables ui-sortable">
            <div id="codediv" class="postbox ">
              <div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
              <h3 class="hndle"><span><?php _e('Ad Preview', SAM_DOMAIN);?></span></h3>
              <div class="inside">
                <div class='ad-example'>
                  <?php echo $this->buildSingleAd(array('id' => (integer) $row['id']), true); ?>
                </div>
              </div>
            </div>
          </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </form>
</div>          
          <?php
          break;
          
			}
		}
      
    function samZoneEditPage() {
      global $wpdb;
      $zTable = $wpdb->prefix . "sam_zones";
      $pTable = $wpdb->prefix . "sam_places";
      
      $options = parent::getSettings();
      $cats = $this->getTax();
      $authors = $this->getAuthors();
      $uCats = array();
      $uAuthors = array();
      
      if(isset($_GET['action'])) $action = $_GET['action'];
      else $action = 'new';
      if(isset($_GET['mode'])) $mode = $_GET['mode'];
      else $mode = 'zone';
      if(isset($_GET['item'])) $item = $_GET['item'];
      else $item = null;
      if(isset($_GET['zone'])) $zone = $_GET['zone'];
      else $zone = null;
      
      $updated = false;
          
      if(isset($_POST['update_zone'])) {
        $zoneId = $_POST['zone_id'];
        foreach($cats as $cat) {
          if(isset($_POST['z_cats_'.$cat['slug']])) {
            $value = (integer) $_POST['z_cats_'.$cat['slug']];
            $uCats[$cat['slug']] = $value;
          }          
        }
        foreach($authors as $key => $author) {
          if(isset($_POST['z_authors_'.$author])) $uAuthors[$author] = $_POST['z_authors_'.$author];
        }
        $updateRow = array(
          'name' => $_POST['zone_name'],
          'description' => $_POST['description'],
          'z_default' => $_POST['z_default'],
          'z_home' => $_POST['z_home'],
          'z_singular' => $_POST['z_singular'],
          'z_single' => $_POST['z_single'],
          'z_page' => $_POST['z_page'],
          'z_attachment' => $_POST['z_attachment'],
          'z_search' => $_POST['z_search'],
          'z_404' => $_POST['z_404'],
          'z_archive' => $_POST['z_archive'],
          'z_tax' => $_POST['z_tax'],
          'z_category' => $_POST['z_category'],
          'z_cats' => serialize($uCats),
          'z_tag' => $_POST['z_tag'],
          'z_author' => $_POST['z_author'],
          'z_authors' => serialize($uAuthors),
          'z_date' => $_POST['z_date'],
          'trash' => ($_POST['trash'] === 'true')
        );
        $formatRow = array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%d');
        if($zoneId === __('Undefined', SAM_DOMAIN)) {
          $wpdb->insert($zTable, $updateRow);
          $updated = true;
          $item = $wpdb->insert_id;
        }
        else {
          if(is_null($item)) $item = $zoneId;
          $wpdb->update($zTable, $updateRow, array( 'id' => $item ), $formatRow, array( '%d' ));
          $updated = true;
        }
        ?>
<div class="updated"><p><strong><?php _e("Ads Zone Data Updated.", SAM_DOMAIN);?></strong></p></div>
        <?php
      }
      
      $zSql = "SELECT 
                  id, 
                  name, 
                  description, 
                  z_default, 
                  z_home, 
                  z_singular, 
                  z_single, 
                  z_page, 
                  z_attachment, 
                  z_search, 
                  z_404, 
                  z_archive, 
                  z_tax, 
                  z_category,
                  z_cats,
                  z_tag,
                  z_author,
                  z_authors,
                  z_date, 
                  trash 
                FROM $zTable 
                WHERE id = $item;";      
      
      $pSql = "SELECT id, name FROM $pTable WHERE $pTable.trash IS FALSE;";
      $places = $wpdb->get_results($pSql, ARRAY_A);
      $sCats = array();
      $sAuthors = array();
      
      if($action !== 'new') {
        $row = $wpdb->get_row($zSql, ARRAY_A);
        $zCats = unserialize($row['z_cats']);
        $zAuthors = unserialize($row['z_authors']);
        foreach($cats as $cat) {
          $val = (!is_null($zCats[$cat['slug']])) ? $zCats[$cat['slug']] : -1;
          array_push($sCats, array('name' => $cat['name'], 'slug' => $cat['slug'], 'val' => $val));
        }
        foreach($authors as $key => $author) {
          $val = (!is_null($zAuthors[$author])) ? $zAuthors[$author] : -1;
          array_push($sAuthors, array('id' => $author, 'name' => $key, 'val' => $val));
        }
      }
      else {
        if($updated) {
          $row = $wpdb->get_row($zSql, ARRAY_A);
          $zCats = unserialize($row['z_cats']);          
          $zAuthors = unserialize($row['z_authors']);
          foreach($cats as $cat) {
            $val = (!is_null($zCats[$cat['slug']])) ? $zCats[$cat['slug']] : -1;
            array_push($sCats, array('name' => $cat['name'], 'slug' => $cat['slug'], 'val' => $val));
          }
          foreach($authors as $key => $author) {
            $val = (!is_null($zAuthors[$author])) ? $zAuthors[$author] : -1;
            array_push($sAuthors, array('id' => $author, 'name' => $key, 'val' => $val));
          }
        }
        else {
          $row = array(
            'id' => __('Undefined', SAM_DOMAIN),
            'name' => '',
            'description' => '',
            'z_default' => 0,
            'z_home' => -1,
            'z_singular' => -1,
            'z_single' => -1,
            'z_page' => -1,
            'z_attachment' => -1,
            'z_search' => -1,
            'z_404' => -1,
            'z_archive' => -1,
            'z_tax' => -1,
            'z_category' => -1,
            'z_tag' => -1,
            'z_author' => -1,
            'z_date' => -1,
            'trash' => false
          );
          foreach($cats as $cat) array_push($sCats, array('name' => $cat['name'], 'slug' => $cat['slug'], 'val' => -1));
          foreach($authors as $key => $author) array_push($sAuthors, array('id' => $author, 'name' => $key, 'val' => -1));
        }
      }
      ?>
<div class="wrap">
  <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
    <div class="icon32" style="background: url('<?php echo SAM_IMG_URL.'sam-editor.png'; ?>') no-repeat transparent; "><br/></div>
    <h2><?php echo ( ( ($action === 'new') && ( $row['id'] === __('Undefined', SAM_DOMAIN) ) ) ? __('New Ads Zone', SAM_DOMAIN) : __('Edit Ads Zone', SAM_DOMAIN).' ('.$item.')' ); ?></h2>
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
                      <a id="post-preview" class="preview button" href='<?php echo admin_url('admin.php'); ?>?page=sam-zone-list'><?php _e('Back to Zones List', SAM_DOMAIN) ?></a>
                    </div>
                    <div class="clear"></div>
                  </div>
                  <div id="misc-publishing-actions">
                    <div class="misc-pub-section">
                      <label for="place_id_stat"><?php echo __('Ads Zone ID', SAM_DOMAIN).':'; ?></label>
                      <span id="place_id_stat" class="post-status-display"><?php echo $row['id']; ?></span>
                      <input type="hidden" id="zone_id" name="zone_id" value="<?php echo $row['id']; ?>" />
                      <input type='hidden' name='editor_mode' id='editor_mode' value='zone'>
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
                    <input type="submit" class='button-primary' name="update_zone" value="<?php _e('Save', SAM_DOMAIN) ?>" />
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
              <input id="title" type="text" autocomplete="off" tabindex="1" size="30" name="zone_name" value="<?php echo $row['name']; ?>" />
            </div>
          </div>
          <div class="meta-box-sortables ui-sortable">
            <div id="descdiv" class="postbox ">
              <div class="handlediv" title="<?php _e('Click to toggle', SAM_DOMAIN); ?>"><br/></div>
              <h3 class="hndle"><span><?php _e('Description', SAM_DOMAIN);?></span></h3>
              <div class="inside">
                <p><?php _e('Enter description of this Ads Zone.', SAM_DOMAIN);?></p>
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
              <h3 class="hndle"><span><?php _e('Ads Zone Settings', SAM_DOMAIN);?></span></h3>
              <div class="inside">
                <p>
                  <label for='z_default'><?php echo __('Default Ads Place', SAM_DOMAIN).': '; ?></label>
                  <select id='z_default' name='z_default'>
                    <?php $this->drawPlacesSelector($places, $row['z_default'], true); ?>
                  </select>
                </p>
                <p>
                  <?php _e('Select the Ads Place by default. This Ads Place will be displayed in the event that for the page of a given type the Ads Place value is set to "Default".', SAM_DOMAIN); ?>
                </p>
                <div class='clear-line'></div>
                <p>
                  <label for='z_home'><?php echo __('Home Page Ads Place', SAM_DOMAIN).': '; ?></label>
                  <select id='z_home' name='z_home'>
                    <?php $this->drawPlacesSelector($places, $row['z_home'], false); ?>
                  </select>
                </p>
                <p>
                  <label for='z_singular'><?php echo __('Default Ads Place for Singular Pages', SAM_DOMAIN).': '; ?></label>
                  <select id='z_singular' name='z_singular'>
                    <?php $this->drawPlacesSelector($places, $row['z_singular'], false); ?>
                  </select>
                </p>
                <div class='sub-content'>
                  <p>
                    <label for='z_single'><?php echo __('Single Post Ads Place', SAM_DOMAIN).': '; ?></label>
                    <select id='z_single' name='z_single'>
                      <?php $this->drawPlacesSelector($places, $row['z_single'], false); ?>
                    </select>
                  </p>
                  <p>
                    <label for='z_page'><?php echo __('Page Ads Place', SAM_DOMAIN).': '; ?></label>
                    <select id='z_page' name='z_page'>
                      <?php $this->drawPlacesSelector($places, $row['z_page'], false); ?>
                    </select>
                  </p>
                  <p>
                    <label for='z_attachment'><?php echo __('Attachment Ads Place', SAM_DOMAIN).': '; ?></label>
                    <select id='z_attachment' name='z_attachment'>
                      <?php $this->drawPlacesSelector($places, $row['z_attachment'], false); ?>
                    </select>
                  </p>
                </div>
                <p>
                  <label for='z_search'><?php echo __('Search Pages Ads Place', SAM_DOMAIN).': '; ?></label>
                  <select id='z_search' name='z_search'>
                    <?php $this->drawPlacesSelector($places, $row['z_search'], false); ?>
                  </select>
                </p>
                <p>
                  <label for='z_404'><?php echo __('404 Page Ads Place', SAM_DOMAIN).': '; ?></label>
                  <select id='z_404' name='z_404'>
                    <?php $this->drawPlacesSelector($places, $row['z_404'], false); ?>
                  </select>
                </p>
                <p>
                  <label for='z_archive'><?php echo __('Default Ads Place for Archive Pages', SAM_DOMAIN).': '; ?></label>
                  <select id='z_archive' name='z_archive'>
                    <?php $this->drawPlacesSelector($places, $row['z_archive'], false); ?>
                  </select>
                </p>
                <div class='sub-content'>
                  <p>
                    <label for='z_tax'><?php echo __('Default Ads Place for Taxonomies Pages', SAM_DOMAIN).': '; ?></label>
                    <select id='z_tax' name='z_tax'>
                      <?php $this->drawPlacesSelector($places, $row['z_tax'], false); ?>
                    </select>
                  </p>
                  <div class='sub-content-level-2'>
                    <p>
                      <label for='z_category'><?php echo __('Default Ads Place for Category Archive Pages', SAM_DOMAIN).': '; ?></label>
                      <select id='z_category' name='z_category'>
                        <?php $this->drawPlacesSelector($places, $row['z_category'], false); ?>
                      </select>
                    </p>
                    <?php 
                    if(count($sCats) > 1) {
                      ?>
                    <div class='sub-content'>  
                      <?php
                      foreach($sCats as $cat) {
                        ?>
                      <p>
                        <label for='<?php echo 'z_cats_'.$cat['slug']; ?>'><?php echo __('Ads Place for Category', SAM_DOMAIN).' "<strong>'.$cat['name'].'</strong>": '; ?></label>
                        <select id='<?php echo 'z_cats_'.$cat['slug']; ?>' name='<?php echo 'z_cats_'.$cat['slug']; ?>'>
                          <?php $this->drawPlacesSelector($places, $cat['val'], false); ?>
                        </select>
                      </p>      
                        <?php
                      }
                      ?>
                    </div>
                    <?php  
                    }                    
                    ?>
                    <p>
                      <label for='z_tag'><?php echo __('Tags Archive Pages Ads Place', SAM_DOMAIN).': '; ?></label>
                      <select id='z_tag' name='z_tag'>
                        <?php $this->drawPlacesSelector($places, $row['z_tag'], false); ?>
                      </select>
                    </p>
                  </div>
                  <p>
                    <label for='z_author'><?php echo __('Default Ads Place for Author Archive Pages', SAM_DOMAIN).': '; ?></label>
                    <select id='z_author' name='z_author'>
                      <?php $this->drawPlacesSelector($places, $row['z_author'], false); ?>
                    </select>
                  </p>
                  <?php if(count($sAuthors) > 1) { ?>
                  <div class='sub-content-level-2'>
                    <?php foreach($sAuthors as $author) { ?>
                    <p>
                      <label for='<?php echo 'z_authors_'.$author['id']; ?>'><?php echo __('Ads Place for author', SAM_DOMAIN).' <strong>'.$author['name'].'</strong>: '; ?></label>
                      <select id='<?php echo 'z_authors_'.$author['id']; ?>' name='<?php echo 'z_authors_'.$author['id']; ?>'>
                        <?php $this->drawPlacesSelector($places, $author['val'], false); ?>
                      </select>
                    </p>
                    <?php } ?>
                  </div>
                  <?php } ?>
                  <p>
                    <label for='z_date'><?php echo __('Date Archive Pages Ads Place', SAM_DOMAIN).': '; ?></label>
                    <select id='z_date' name='z_date'>
                      <?php $this->drawPlacesSelector($places, $row['z_date'], false); ?>
                    </select>
                  </p>
                </div>
                <p>
                  <?php _e('Ads Places for Singular pages, for Pages of Taxonomies and for Archive pages are Ads Places by default for the low level pages of relevant pages.', SAM_DOMAIN); ?>
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
    }
    
    /*function samBlockEditPage() {
      
    }*/
  } // end of class definition
} // end of if not class SimpleAdsManager exists
?>