<?php
if(!class_exists('SamAd')) {
  class SamAd {
    private $args = array();
    private $useCodes = false;
    private $crawler = false;
    public $ad = '';
    
    public function __construct($args = null, $useCodes = false, $crawler = false) {
      $this->args = $args;
      $this->useCodes = $useCodes;
      $this->crawler = $crawler;
      $this->ad = $this->buildAd($this->args, $this->useCodes);
    }
    
    private function buildAd( $args = null, $useCodes = false ) {
      if(is_null($args)) return '';
      if(empty($args['id']) && empty($args['name'])) return '';
      
      global $wpdb;          
      $pTable = $wpdb->prefix . "sam_places";
      $aTable = $wpdb->prefix . "sam_ads";
      
      if(!empty($args['id'])) $wid = "$aTable.id = {$args['id']}";
      else $wid = "$aTable.name = '{$args['name']}'";
      
      $output = '';
      
      $aSql = "SELECT
                  $aTable.id,
                  $aTable.pid,
                  $aTable.code_mode,
                  $aTable.ad_code,
                  $aTable.ad_img,
                  $aTable.ad_target,
                  $aTable.count_clicks,
                  $aTable.code_type,
                  $pTable.code_before,
                  $pTable.code_after
                FROM $aTable
                  INNER JOIN $pTable
                    ON $aTable.pid = $pTable.id
                WHERE $wid;";
      $ad = $wpdb->get_row($aSql, ARRAY_A);
      if($ad['code_mode'] == 0) {
        $outId = ((int) $ad['count_clicks'] == 1) ? " id='a".rand(10, 99)."_".$ad['id']."' class='sam_ad'" : '';
        $aStart ='';
        $aEnd ='';
        $iTag = '';
        if(!empty($ad['ad_target'])) {
          $aStart = "<a href='{$ad['ad_target']}' target='_blank'>";
          $aEnd = "</a>";
        }
        if(!empty($ad['ad_img'])) $iTag = "<img $outId src='{$ad['ad_img']}' />";
        $output = $aStart.$iTag.$aEnd;
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
      if(!$this->crawler)
        $wpdb->query("UPDATE $aTable SET $aTable.ad_hits = $aTable.ad_hits+1 WHERE $aTable.id = {$ad['id']};");
      
      if(is_array($useCodes)) $output = $useCodes['before'].$output.$useCodes['after'];
      elseif($useCodes) $output = $ad['code_before'].$output.$ad['code_after'];
      return $output;
    }
  }
}

if(!class_exists('SamAdPlace')) {
  class SamAdPlace {
    private $args = array();
    private $useCodes = false;
    private $crawler = false;
    public $ad = '';
    
    public function __construct($args = null, $useCodes = false, $crawler = false) {
      $this->args = $args;
      $this->useCodes = $useCodes;
      $this->crawler = $crawler;
      $this->ad = $this->buildAd($this->args, $this->useCodes);
    }
    
    private function getSettings() {
      $options = get_option(SAM_OPTIONS_NAME, '');      
      return $options;
    }
    
    private function buildAd( $args = null, $useCodes = false ) {
      if(is_null($args)) return '';
      if(empty($args['id']) && empty($args['name'])) return '';
      
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
      $wca = '';
      $wcx = '';
      $wcxc = '';
      $wcxa = '';
      if(is_home() || is_front_page()) $viewPages += SAM_IS_HOME;
      if(is_singular()) {
        $viewPages += SAM_IS_SINGULAR;
        if(is_single()) {
          global $post;
          
          $viewPages += SAM_IS_SINGLE;
          $categories = get_the_category($post->ID);
          $wcc_0 = '';
          $wcxc_0 = '';
          $wcc = " AND IF($aTable.view_type < 2 AND $aTable.ad_cats AND IF($aTable.view_type = 0, $aTable.view_pages+0 & $viewPages, TRUE),";
          $wcxc = " AND IF($aTable.view_type < 2 AND $aTable.x_cats AND IF($aTable.view_type = 0, $aTable.view_pages+0 & $viewPages, TRUE),";
          foreach($categories as $category) {
            if(empty($wcc_0)) $wcc_0 = " FIND_IN_SET('{$category->cat_name}', $aTable.view_cats)";
            else $wcc_0 .= " OR FIND_IN_SET('{$category->cat_name}', $aTable.view_cats)";
            if(empty($wcxc_0)) $wcxc_0 = " (NOT FIND_IN_SET('{$category->cat_name}', $aTable.x_view_cats))";
            else $wcxc_0 .= " AND (NOT FIND_IN_SET('{$category->cat_name}', $aTable.x_view_cats))";
          }
          $wcc .= $wcc_0.", TRUE)";
          $wcxc .= $wcxc_0.", TRUE)";
          $wci = " OR ($aTable.view_type = 2 AND FIND_IN_SET({$post->ID}, $aTable.view_id))";
          $wcx = " AND IF($aTable.x_id, NOT FIND_IN_SET({$post->ID}, $aTable.x_view_id), TRUE)";
          $author = get_userdata($post->post_author);
          $wca = " AND IF($aTable.view_type < 2 AND $aTable.ad_authors AND IF($aTable.view_type = 0, $aTable.view_pages+0 & $viewPages, TRUE), FIND_IN_SET('{$author->display_name}', $aTable.view_authors), TRUE)";
          $wcxa = " AND IF($aTable.view_type < 2 AND $aTable.x_authors AND IF($aTable.view_type = 0, $aTable.view_pages+0 & $viewPages, TRUE), NOT FIND_IN_SET('{$author->display_name}', $aTable.x_view_authors), TRUE)";
        }
        if(is_page()) {
          global $post;
          
          $viewPages += SAM_IS_PAGE;
          $wci = " OR ($aTable.view_type = 2 AND FIND_IN_SET({$post->ID}, $aTable.view_id))";
          $wcx = " AND IF($aTable.x_id, NOT FIND_IN_SET({$post->ID}, $aTable.x_view_id), TRUE)";
        }
        if(is_attachment()) $viewPages += SAM_IS_ATTACHMENT;
      }
      if(is_search()) $viewPages += SAM_IS_SEARCH;
      if(is_404()) $viewPages += SAM_IS_404;
      if(is_archive()) {
        $viewPages += SAM_IS_ARCHIVE;
        if(is_tax()) $viewPages += SAM_IS_TAX;
        if(is_category()) {
          $viewPages += SAM_IS_CATEGORY;
          $cat = get_category(get_query_var('cat'), false);
          $wcc = " AND IF($aTable.view_type < 2 AND $aTable.ad_cats AND IF($aTable.view_type = 0, $aTable.view_pages+0 & $viewPages, TRUE), FIND_IN_SET('{$cat->cat_name}', $aTable.view_cats), TRUE)";
          $wcxc = " AND IF($aTable.view_type < 2 AND $aTable.x_cats AND IF($aTable.view_type = 0, $aTable.view_pages+0 & $viewPages, TRUE), NOT FIND_IN_SET('{$cat->cat_name}', $aTable.x_view_cats), TRUE)";
        }
        if(is_tag()) $viewPages += SAM_IS_TAG;
        if(is_author()) {
          global $wp_query;
          
          $viewPages += SAM_IS_AUTHOR;
          $author = $wp_query->get_queried_object();
          $wca = " AND IF($aTable.view_type < 2 AND $aTable.ad_authors = 1 AND IF($aTable.view_type = 0, $aTable.view_pages+0 & $viewPages, TRUE), FIND_IN_SET('{$author->display_name}', $aTable.view_authors), TRUE)";
          $wcxa = " AND IF($aTable.view_type < 2 AND $aTable.x_authors AND IF($aTable.view_type = 0, $aTable.view_pages+0 & $viewPages, TRUE), NOT FIND_IN_SET('{$author->display_name}', $aTable.x_view_authors), TRUE)";
        }
        if(is_date()) $viewPages += SAM_IS_DATE;
      }
      
      if(empty($wcc)) $wcc = " AND ($aTable.ad_cats = 0)";
      if(empty($wca)) $wca = " AND ($aTable.ad_authors = 0)";
      
      $whereClause  = "(($aTable.view_type = 1)";
      $whereClause .= " OR ($aTable.view_type = 0 AND ($aTable.view_pages+0 & $viewPages))";
      $whereClause .= "$wci)";
      $whereClause .= "$wcc $wca $wcx $wcxc $wcxa";
      $whereClauseT = " AND IF($aTable.ad_schedule, CURDATE() BETWEEN $aTable.ad_start_date AND $aTable.ad_end_date, TRUE)";
      $whereClauseT .= " AND IF($aTable.limit_hits, $aTable.hits_limit > $aTable.ad_hits, TRUE)";
      $whereClauseT .= " AND IF($aTable.limit_clicks, $aTable.clicks_limit > $aTable.ad_clicks, TRUE)";
      
      $whereClauseW = " AND IF($aTable.ad_weight > 0, ($aTable.ad_weight_hits*10/($aTable.ad_weight*$cycle)) < 1, FALSE)";
      $whereClause2W = "AND ($aTable.ad_weight > 0)";
      
      if(!empty($args['id'])) $pId = "$pTable.id = {$args['id']}";
      else $pId = "$pTable.name = '{$args['name']}'";
      
      $pSql = "SELECT
                  $pTable.id,
                  $pTable.name,                  
                  $pTable.description,
                  $pTable.code_before,
                  $pTable.code_after,
                  $pTable.place_size,
                  $pTable.place_custom_width,
                  $pTable.place_custom_height,
                  $pTable.patch_img,
                  $pTable.patch_link,
                  $pTable.patch_code,
                  $pTable.patch_adserver,
                  $pTable.patch_dfp,                  
                  $pTable.patch_source,
                  $pTable.trash,
                  (SELECT COUNT(*) FROM $aTable WHERE $aTable.pid = $pTable.id AND $aTable.trash IS FALSE) AS ad_count,
                  (SELECT COUNT(*) FROM $aTable WHERE $aTable.pid = $pTable.id AND $aTable.trash IS FALSE AND $whereClause $whereClauseT $whereClause2W) AS ad_logic_count,
                  (SELECT COUNT(*) FROM $aTable WHERE $aTable.pid = $pTable.id AND $aTable.trash IS FALSE AND $whereClause $whereClauseT $whereClauseW) AS ad_full_count
                FROM $pTable
                WHERE $pId AND $pTable.trash IS FALSE;";
      
      $place = $wpdb->get_row($pSql, ARRAY_A);
      
      if($place['patch_source'] == 2) {
        if(($settings['useDFP'] == 1) && !empty($settings['dfpPub'])) {
          $output = "<!-- {$place['patch_dfp']} -->"."\n";
          $output .= "<script type='text/javascript'>"."\n";
          $output .= "  GA_googleFillSlot('{$place['patch_dfp']}');"."\n";
          $output .= "</script>"."\n";
          if($useCodes) $output = $place['code_before'].$output.$place['code_after'];
        }
        else $output = '';
        if(!$this->crawler)
          $wpdb->query("UPDATE {$pTable} SET {$pTable}.patch_hits = {$pTable}.patch_hits+1 WHERE {$pTable}.id = {$place['id']}");
        return $output;
      }
      
      if(($place['patch_source'] == 1) && (abs($place['patch_adserver']) == 1)) {
        $output = $place['patch_code'];
        if($useCodes) $output = $place['code_before'].$output.$place['code_after'];
        if(!$this->crawler)
          $wpdb->query("UPDATE $pTable SET $pTable.patch_hits = $pTable.patch_hits+1 WHERE $pTable.id = {$place['id']}");
        return $output;
      }
                                     
      if((abs($place['ad_count']) == 0) || (abs($place['ad_logic_count']) == 0)) {
        if($place['patch_source'] == 0) {
         $aStart ='';
          $aEnd ='';
          $iTag = '';  
          if(!empty($place['patch_link'])) {
            $aStart = "<a href='{$place['patch_link']}'>";
            $aEnd = "</a>";
          }
          if(!empty($place['patch_img'])) $iTag = "<img src='{$place['patch_img']}' />";
          $output = $aStart.$iTag.$aEnd;
        }
        else $output = $place['patch_code'];
        if(!$this->crawler)
          $wpdb->query("UPDATE $pTable SET $pTable.patch_hits = $pTable.patch_hits+1 WHERE $pTable.id = {$place['id']}");
      }
      
      if((abs($place['ad_logic_count']) > 0) && (abs($place['ad_full_count']) == 0)) {
        $wpdb->update($aTable, array('ad_weight_hits' => 0), array('pid' => $place['id']), array("%d"), array("%d"));
      }
      
      $aSql = "SELECT
                  $aTable.id,
                  $aTable.pid,
                  $aTable.code_mode,
                  $aTable.ad_code,
                  $aTable.ad_img,
                  $aTable.ad_target,
                  $aTable.count_clicks,
                  $aTable.code_type,
                  $aTable.ad_hits,
                  $aTable.ad_weight_hits,
                  IF($aTable.ad_weight, ($aTable.ad_weight_hits*10/($aTable.ad_weight*$cycle)), 0) AS ad_cycle
                FROM $aTable
                WHERE $aTable.pid = {$place['id']} AND $aTable.trash IS FALSE AND $whereClause $whereClauseT $whereClauseW
                ORDER BY ad_cycle
                LIMIT 1;";
      
      if(abs($place['ad_logic_count']) > 0) {
        $ad = $wpdb->get_row($aSql, ARRAY_A);
        if($ad['code_mode'] == 0) {
          $outId = ((int) $ad['count_clicks'] == 1) ? " id='a".rand(10, 99)."_".$ad['id']."' class='sam_ad'" : '';
          $aStart ='';
          $aEnd ='';
          $iTag = '';
          if(!empty($ad['ad_target'])) {
            $aStart = "<a href='{$ad['ad_target']}' target='_blank'>";
            $aEnd = "</a>";
          }
          if(!empty($ad['ad_img'])) $iTag = "<img $outId src='{$ad['ad_img']}' />";
          $output = $aStart.$iTag.$aEnd;
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
        if(!$this->crawler)
          $wpdb->query("UPDATE $aTable SET $aTable.ad_hits = $aTable.ad_hits+1, $aTable.ad_weight_hits = $aTable.ad_weight_hits+1 WHERE $aTable.id = {$ad['id']}");
      }
      
      if(is_array($useCodes)) $output = $useCodes['before'].$output.$useCodes['after'];
      elseif($useCodes) $output = $place['code_before'].$output.$place['code_after'];
      return $output;
    }
  }
}

if(!class_exists('SamAdPlaceZone')) {
  class SamAdPlaceZone {
    private $args = array();
    private $useCodes = false;
    private $crawler = false;
    public $ad = '';
    
    public function __construct($args = null, $useCodes = false, $crawler = false) {
      $this->args = $args;
      $this->useCodes = $useCodes;
      $this->crawler = $crawler;
      $this->ad = self::buildZone($this->args, $this->useCodes, $this->crawler);
    }
    
    private function buildZone($args = null, $useCodes = false, $crawler = false) {
      if(is_null($args)) return '';
      if(empty($args['id']) && empty($args['name'])) return '';
      
      global $wpdb;
      $zTable = $wpdb->prefix . "sam_zones";
      
      $id = 0; // None
      $output = '';
      
      if(!empty($args['id'])) $zId = "$zTable.id = {$args['id']}";
      else $zId = "$zTable.name = '{$args['name']}'";
      
      $zSql = "SELECT
                  $zTable.id,
                  $zTable.name,
                  $zTable.z_default,
                  $zTable.z_home,
                  $zTable.z_singular,
                  $zTable.z_single,
                  $zTable.z_page,
                  $zTable.z_attachment,
                  $zTable.z_search,
                  $zTable.z_404,
                  $zTable.z_archive,
                  $zTable.z_tax,
                  $zTable.z_category,
                  $zTable.z_cats,
                  $zTable.z_tag,
                  $zTable.z_author,
                  $zTable.z_authors,
                  $zTable.z_date
                FROM $zTable
                WHERE $zId AND $zTable.trash IS FALSE;";
      $zone = $wpdb->get_row($zSql, ARRAY_A);
      if(!empty($zone)) {
        $cats = unserialize($zone['z_cats']);
        $authors = unserialize($zone['z_authors']);
        
        if((integer)$zone['z_home'] < 0) $zone['z_home'] = $zone['z_default'];
        if((integer)$zone['z_singular'] < 0) $zone['z_singular'] = $zone['z_default'];
        if((integer)$zone['z_single'] < 0) $zone['z_single'] = $zone['z_singular'];
        if((integer)$zone['z_page'] < 0) $zone['z_page'] = $zone['z_singular'];
        if((integer)$zone['z_attachment'] < 0) $zone['z_attachment'] = $zone['z_singular'];
        if((integer)$zone['z_search'] < 0) $zone['z_search'] = $zone['z_default'];
        if((integer)$zone['z_404'] < 0) $zone['z_404'] = $zone['z_default'];
        if((integer)$zone['z_archive'] < 0) $zone['z_archive'] = $zone['z_default'];
        if((integer)$zone['z_tax'] < 0) $zone['z_tax'] = $zone['z_archive'];
        if((integer)$zone['z_category'] < 0) $zone['z_category'] = $zone['z_tax'];
        foreach($cats as $key => $value) {
          if($value < 0) $cats[$key] = $zone['z_category'];
        }
        if((integer)$zone['z_tag'] < 0) $zone['z_tag'] = $zone['z_tax'];
        if((integer)$zone['z_author'] < 0) $zone['z_author'] = $zone['z_archive'];
        foreach($authors as $key => $value) {
          if($value < 0) $authors[$key] = $zone['z_author'];
        }
        if((integer)$zone['z_date'] < 0) $zone['z_date'] = $zone['z_archive'];
        
        if(is_home() || is_front_page()) $id = $zone['z_home'];
        if(is_singular()) {
          $id = $zone['z_singular'];
          if(is_single()) $id = $zone['z_single'];
          if(is_page()) $id = $zone['z_page'];
          if(is_attachment()) $id = $zone['z_attachment'];
        }
        if(is_search()) $id = $zone['z_search'];
        if(is_404()) $id = $zone['z_404'];
        if(is_archive()) {
          $id = $zone['z_archive'];
          if(is_tax()) $id = $zone['z_tax'];
          if(is_category()) {
            $id = $zone['z_category'];
            foreach($cats as $key => $value) {
              if(is_category($key)) $id = $value;
            }                
          }
          if(is_tag()) $id = $zone['z_tag'];
          if(is_author()) {
            $id = $zone['z_author'];
            foreach($authors as $key => $value) {
              if(is_author($key)) $id = $value;
            }
          }
          if(is_date()) $id = $zone['z_date'];
        }
      }
      
      if($id > 0) {
        $ad = new SamAdPlace(array('id' => $id), $useCodes, $crawler);
        $output = $ad->ad;
      }
      return $output;
    }
  }
}
?>
