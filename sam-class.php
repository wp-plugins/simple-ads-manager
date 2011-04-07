<?php
if ( !class_exists( 'SimpleAdsManager' ) ) {
  class SimpleAdsManager {
    private $samOptions = array();
    private $samVersions = array('sam' => null, 'db' => null);
    private $crawler = false;
    
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
      'detectBots' => 0,
      'detectingMode' => 'inexact',
      'currency' => 'auto',
      'dfpPub' => '',
      'dfpBlocks' => array()
		);
		
		function __construct() {
      define('SAM_VERSION', '0.5.22');
			define('SAM_DB_VERSION', '0.5.1');
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
      
      add_shortcode('sam-ad', array(&$this, 'doAdShortcode'));
      add_shortcode('sam', array(&$this, 'doShortcode'));
      add_shortcode('sam-zone', array(&$this, 'doZoneShortcode'));      
      add_filter('the_content', array(&$this, 'addContentAds'), 8);
      
      $this->getSettings(true);
      $this->getVersions(true);
      $this->crawler = $this->isCrawler();
    }
		
		function getSettings($force = false) {
			if($force) {
        $pluginOptions = get_option(SAM_OPTIONS_NAME, '');
			  $options = $this->defaultSettings;
			  if ($pluginOptions !== '') {
				  foreach($pluginOptions as $key => $option) {
					  $options[$key] = $option;
				  }
			  }
			  $this->samOptions = $options;        
      }
      else $options = $this->samOptions;
      return $options; 
		}
    
    function getVersions($force = false) {
      $versions = array('sam' => null, 'db' => null);
      if($force) {
        $versions['sam'] = get_option( 'sam_version', '' );
        $versions['db'] = get_option( 'sam_db_version', '' );
        $this->samVersions = $versions;
      }
      else $versions = $this->samVersions;
      
      return $versions;
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
        $id = (integer) $aId[1];
      }
      else $id = 0;
      if(isset($_POST['_ajax_nonce']))  $nonce = $_POST['_ajax_nonce'];
      else $nonce = 0;

      if(wp_verify_nonce($nonce, 'samNonce') && ($id > 0)) {
        global $wpdb;
        $aTable = $wpdb->prefix . "sam_ads";  
        
        $result = $wpdb->query("UPDATE $aTable SET $aTable.ad_clicks = $aTable.ad_clicks+1 WHERE $aTable.id = $id;");
        if($result) $error = 'ok';
        else $error = 'error';
      }
      else $error = 'error';
      
      if($error) exit($error);
    }
    
    private function isCrawler() {
      $options = $this->getSettings();
      $crawler = false;
      
      if($options['detectBots'] == 1) {
        switch($options['detectingMode']) {
          case 'inexact':
            if($_SERVER["HTTP_USER_AGENT"] == '' ||
               $_SERVER['HTTP_ACCEPT'] == '' ||
               $_SERVER['HTTP_ACCEPT_ENCODING'] == '' ||
               $_SERVER['HTTP_ACCEPT_LANGUAGE'] == '' ||
               $_SERVER['HTTP_CONNECTION']=='') $crawler == true;
            break;
            
          case 'exact':
            include_once('browser.php');
            $browser = new Browser();
            $crawler = $browser->isRobot();
            break;
            
          case 'more':
            if(ini_get("browscap")) {
              $browser = get_browser(null, true);
              $crawler = $browser['crawler']; 
            }
            break;
        }
      }
      return $crawler;
    }
		
		/**
    * Outputs the Single Ad.
    *
    * Returns Single Ad content.
    *
    * @since 0.1.1
    *
    * @param array $args 'id' array element: id of ad, 'name' array elemnt: name of ad
    * @param bool|array $useCodes If bool codes 'before' and 'after' from Ads Place record are used. If array codes 'before' and 'after' from array are used
    * @return string value of Ad content
    */
    function buildSingleAd( $args = null, $useCodes = false ) {
      $ad = new SamAd($args, $useCodes, $this->crawler);
      $output = $ad->ad;
      return $output;
    }
    
    /**
    * Outputs Ads Place content.
    *
    * Returns Ads Place content.
    *
    * @since 0.1.1
    *
    * @param array $args 'id' array element: id of Ads Place, 'name' array elemnt: name of Ads Place
    * @param bool|array $useCodes If bool codes 'before' and 'after' from Ads Place record are used. If array codes 'before' and 'after' from array are used
    * @return string value of Ads Place content
    */
    function buildAd( $args = null, $useCodes = false ) {
      $ad = new SamAdPlace($args, $useCodes, $this->crawler);
      $output = $ad->ad;
      return $output;
    }
    
    /**
    * Outputs Ads Zone content.
    *
    * Returns Ads Zone content.
    *
    * @since 0.1.1
    *
    * @param array $args 'id' array element: id of Ads Zone, 'name' array elemnt: name of Ads Zone
    * @param bool|array $useCodes If bool codes 'before' and 'after' from Ads Place record are used. If array codes 'before' and 'after' from array are used
    * @return string value of Ads Zone content
    */
    function buildAdZone( $args = null, $useCodes = false ) {
      $ad = new SamAdPlaceZone($args, $useCodes, $this->crawler);
      $output = $ad->ad;
      return $output;
    }
    
    function doAdShortcode($atts) {
      extract(shortcode_atts( array( 'id' => '', 'name' => '', 'codes' => ''), $atts ));
      $ad = new SamAd(array('id' => $id, 'name' => $name), ($codes == 'true'), $this->crawler);
      return $ad->ad;
    }
    
    function doShortcode( $atts ) {
      extract(shortcode_atts( array( 'id' => '', 'name' => '', 'codes' => ''), $atts ));      
      $ad = new SamAdPlace(array('id' => $id, 'name' => $name), ($codes == 'true'), $this->crawler);
      return $ad->ad;
    }
    
    function doZoneShortcode($atts) {
      extract(shortcode_atts( array( 'id' => '', 'name' => '', 'codes' => ''), $atts ));
      $ad = new SamAdPlaceZone(array('id' => $id, 'name' => $name), ($codes == 'true'), $this->crawler);
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