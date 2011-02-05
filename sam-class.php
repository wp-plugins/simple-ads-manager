<?php
if ( !class_exists( 'SimpleAdsManager' ) ) {
  class SimpleAdsManager {
    private $defaultSettings = array(
      'adCycle' => 1000,
      'placesPerPage' => 10,
      'itemsPerPage' => 10,
			'deleteOptions' => 0,
			'deleteDB' => 0,
      'deleteFolder' => 0,
      'beforePost' => 0,
      'bpAdsId' => 0,
      'bpUseCodes' => 1,
      'afterPost' => 0,
      'apAdsId' => 0,
      'apUseCodes' => 1,
      'useDFP' => 0,
      'dfpPub' => '',
      'dfpBlocks' => array()
		);
		
		function __construct() {
      define('SAM_VERSION', '0.3.11');
			define('SAM_DB_VERSION', '0.3');
      define('SAM_PATH', dirname( __FILE__ ));
      define('SAM_URL', WP_PLUGIN_URL . '/' . str_replace( basename( __FILE__), "", plugin_basename( __FILE__ ) ));
      define('SAM_IMG_URL', SAM_URL.'images/');
      define('SAM_DOMAIN', 'simple-ads-manager');
			define('SAM_OPTIONS_NAME', 'samPluginOptions');
      define('SAM_AD_IMG', WP_PLUGIN_DIR.'/sam-images/');
      define('SAM_AD_URL', WP_PLUGIN_URL.'/sam-images/');
      
      define('SAM_IS_HOME', 1);
      define('SAM_IS_SINGULAR', 2);
      define('SAM_IS_SINGLE', 4);
      define('SAM_IS_PAGE', 8);
      define('SAM_IS_ATTACHMENT', 16);
      define('SAM_IS_SEARCH', 32);
      define('SAM_IS_404', 64);
      define('SAM_IS_ARCHIVE', 128);
      define('SAM_IS_TAX', 256);
      define('SAM_IS_CATEGORY', 512);
      define('SAM_IS_TAG', 1024);
      define('SAM_IS_AUTHOR', 2048);
      define('SAM_IS_DATE', 4096);
      
      add_action('wp_ajax_nopriv_sam_click', array(&$this, 'clickHandler'));
      add_action('wp_ajax_sam_click', array(&$this, 'clickHandler'));
      add_action('template_redirect', array(&$this, 'headerScripts'));
      add_action('wp_head', array(&$this, 'headerCodes'));
      
      add_shortcode('sam', array(&$this, 'doShortcode'));
      
      add_filter('the_content', array(&$this, 'addContentAds'), 8);
    }
		
		function getSettings() {
			$pluginOptions = get_option(SAM_OPTIONS_NAME, '');
			$options = $this->defaultSettings;
			if ($pluginOptions !== '') {
				foreach($pluginOptions as $key => $option) {
					$options[$key] = $option;
				}
			}
			return $options;
		}
		
		function updateDB() {
			global $wpdb;
			$pTable = $wpdb->prefix . "sam_places";					
			$aTable = $wpdb->prefix . "sam_ads";
			
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			
			$dbVersion = get_option( 'sam_db_version', '' );
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
					$pSql = 'ALTER TABLE '.$pTable.'
										 ADD COLUMN patch_dfp VARCHAR(255) DEFAULT NULL,
                     ADD COLUMN patch_adserver TINYINT(1) DEFAULT 0,
                     ADD COLUMN patch_hits INT(11) DEFAULT 0;';
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
                  ad_schedule TINYINT(1) DEFAULT 0,
                  ad_start_date DATE DEFAULT NULL,
                  ad_end_date DATE DEFAULT NULL,
                  ad_hits INT(11) DEFAULT 0,
                  ad_clicks INT(11) DEFAULT 0,
                  ad_weight INT(11) DEFAULT 10,
                  ad_weight_hits INT(11) DEFAULT 0,
								  trash TINYINT(1) NOT NULL DEFAULT 0,
								  PRIMARY KEY (id, pid)
								)";
					dbDelta($aSql);
				}
				elseif($dbVersion == '0.1') {
					$aSql = 'ALTER TABLE '.$aTable.'
					           ADD COLUMN ad_cats TINYINT(1) DEFAULT 0,
                     ADD COLUMN ad_authors TINYINT(1) DEFAULT 0,
                     ADD COLUMN view_authors VARCHAR(255) DEFAULT NULL;';
					$wpdb->query($aSql);
          $aSqlU = "UPDATE LOW_PRIORITY {$aTable}
                      SET {$aTable}.ad_cats = 1, 
                          {$aTable}.view_type = 0,
                          {$aTable}.view_pages = 4
                      WHERE {$aTable}.view_type = 3;";
          $wpdb->query($aSqlU);
				}
				update_option('sam_db_version', SAM_DB_VERSION);
      }
			update_option('sam_version', SAM_VERSION);
		}
    
    function checkViewPages( $value, $page ) {
      return ( ($value & $page) > 0 );
    }
    
    function headerScripts() {      
      wp_enqueue_script('jquery');
      wp_enqueue_script('samLayout', SAM_URL.'js/sam-layout.js', array('jquery'), SAM_VERSION);
      wp_localize_script('samLayout', 'samAjax', array(
          'ajaxurl' => admin_url( 'admin-ajax.php' ), 
          '_ajax_nonce' => wp_create_nonce('samNonce'))
      );
    }
    
    function headerCodes() {
      $options = $this->getSettings();
      $pub = $options['dfpPub'];
      
      if(($options['useDFP'] == 1) && !empty($options['dfpPub'])) {
        $output = "<!-- Start of SAM ".SAM_VERSION." scripts -->"."\n";
        $output .= "<script type='text/javascript' src='http://partner.googleadservices.com/gampad/google_service.js'></script>"."\n";
        $output .= "<script type='text/javascript'>"."\n";
        $output .= "  GS_googleAddAdSenseService('$pub');"."\n";
        $output .= "  GS_googleEnableAllServices();"."\n";
        $output .= "</script>"."\n";
        $output .= "<script type='text/javascript'>"."\n";
        foreach($options['dfpBlocks'] as $value)
          $output .= "  GA_googleAddSlot('$pub', '$value');"."\n";
        $output .= "</script>"."\n";
        $output .= "<script type='text/javascript'>"."\n";
        $output .= "  GA_googleFetchAds();"."\n";
        $output .= "</script>"."\n";
        $output .= "<!-- End of SAM ".SAM_VERSION." scripts -->"."\n";
      }
      else $output = '';
      
      echo $output;
    }
    
    function clickHandler() {
      if(isset($_POST['sam_ad_id'])) {
        $adId = $_POST['sam_ad_id'];
        $aId = explode('_', $adId);
        $id = $aId[1];
      }
      else $id = 0;
      if(isset($_POST['_ajax_nonce']))  $nonce = $_POST['_ajax_nonce'];
      else $nonce = 0;

      if(wp_verify_nonce($nonce, 'samNonce') && ($id > 0)) {
        global $wpdb;
        $aTable = $wpdb->prefix . "sam_ads";  
        
        $wpdb->query("UPDATE {$aTable} SET {$aTable}.ad_clicks = {$aTable}.ad_clicks+1 WHERE {$aTable}.id = {$id}");
        $error = 'id: '.$id;
      }
      else $error = 'error';
      
      if($error) exit($error);
    }
		
		function buildAd( $args = null, $useCodes = false ) {
      $ad = new SamAd($args, $useCodes);
      $output = $ad->ad;
      return $output;
    }
    
    function doShortcode( $atts ) {
      extract(shortcode_atts( array( 'id' => '', 'name' => '', 'codes' => ''), $atts ));
      $ad = new SamAd(array('id' => $id, 'name' => $name), ($codes == 'true'));
      return $ad->ad;
    }
    
    function addContentAds( $content ) {
      $options = $this->getSettings();
      $bpAd = '';
      $apAd = '';
      
      if(is_single() || is_page()) {
        if(!empty($options['beforePost']) && !empty($options['bpAdsId'])) 
          $bpAd = $this->buildAd(array('id' => $options['bpAdsId']), $options['bpUseCodes']);
        if(!empty($options['afterPost']) && !empty($options['apAdsId'])) 
          $apAd = $this->buildAd(array('id' => $options['apAdsId']), $options['apUseCodes']);
      }
      
      return $bpAd.$content.$apAd;
    }
  } // end of class definition
} // end of if not class SimpleAdsManager exists
?>