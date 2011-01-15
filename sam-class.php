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
      'apUseCodes' => 1
		);
		
		function __construct() {
      define('SAM_VERSION', '0.1.3');
			define('SAM_DB_VERSION', '0.1');
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
			
			if( get_option( 'sam_db_version', '' ) != SAM_DB_VERSION ) {
				if($wpdb->get_var("SHOW TABLES LIKE '$pTable'") != $pTable) {
					$pSql = "CREATE TABLE ".$pTable."(
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
								  patch_source TINYINT(1) DEFAULT 0,
								  trash TINYINT(1) DEFAULT 0,
								  PRIMARY KEY (id)
									)";
					dbDelta($pSql);
				}
				else {
					//$pSql = 'ALTER TABLE '.$pTable.'
					//					ADD COLUMN patch_source TINYINT(1) DEFAULT 0;';
					//$wpdb->query($pSql);
				}
				
				if($wpdb->get_var("SHOW TABLES LIKE '$aTable'") != $aTable) {
					$aSql = "CREATE TABLE ".$aTable."(
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
                  view_cats VARCHAR(255) DEFAULT NULL,
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
				else {
					//$aSql = 'ALTER TABLE '.$aTable.'
					//					ADD COLUMN ad_source TINYINT(1) DEFAULT 0;';
					//$wpdb->query($aSql);
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
      else $pId = "{$pTable}.name = '{$args['name']}'";
      
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
    
    function doShortcode( $atts ) {
      extract(shortcode_atts( array( 'id' => '', 'name' => '', 'codes' => ''), $atts ));
      return $this->buildAd(array('id' => $id, 'name' => $name), ($codes == 'true'));
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