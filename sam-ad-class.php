<?php
if(!class_exists('SamAd')) {
  class SamAd {
    private $args = array();
    private $useCodes = false;
    public $ad = '';
    
    public function __construct($args = null, $useCodes = false) {
      $this->args = $args;
      $this->useCodes = $useCodes;
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
      if(is_home() || is_front_page()) $viewPages += SAM_IS_HOME;
      if(is_singular()) {
        $viewPages += SAM_IS_SINGULAR;
        if(is_single()) {
          global $post;
          
          $viewPages += SAM_IS_SINGLE;
          $categories = get_the_category($post->ID);
          foreach($categories as $category) 
            $wcc .= " OR ({$aTable}.ad_cats = 1 AND FIND_IN_SET('{$category->cat_name}', {$aTable}.view_cats) AND ({$aTable}.view_pages+0 & {$viewPages}))";
          $wci = " OR ({$aTable}.view_type = 2 AND FIND_IN_SET({$post->ID}, {$aTable}.view_id))";
          $author = get_userdata($post->post_author);
          $wca = " OR ({$aTable}.ad_authors = 1 AND FIND_IN_SET('{$author->display_name}', {$aTable}.view_authors) AND ({$aTable}.view_pages+0 & {$viewPages}))";
        }
        if(is_page()) $viewPages += SAM_IS_PAGE;
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
          $wcc = " OR ({$aTable}.ad_cats = 1 AND FIND_IN_SET('{$cat->cat_name}', {$aTable}.view_cats) AND ({$aTable}.view_pages+0 & {$viewPages}))";
        }
        if(is_tag()) $viewPages += SAM_IS_TAG;
        if(is_author()) {
          global $wp_query;
          
          $viewPages += SAM_IS_AUTHOR;
          $author = $wp_query->get_queried_object();
          $wca = " OR ({$aTable}.ad_authors = 1 AND FIND_IN_SET('{$author->display_name}', {$aTable}.view_authors) AND ({$aTable}.view_pages+0 & {$viewPages}))";
        }
        if(is_date()) $viewPages += SAM_IS_DATE;
      }
      
      $whereClause  = "({$aTable}.view_type = 1)";
      $whereClause .= " OR ({$aTable}.view_type = 0 AND ({$aTable}.view_pages+0 & {$viewPages}))";
      $whereClause .= $wcc.$wci.$wca;
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
                  {$pTable}.patch_adserver,
                  {$pTable}.patch_dfp,                  
                  {$pTable}.patch_source,
                  {$pTable}.trash,
                  (SELECT COUNT(*) FROM {$aTable} WHERE {$aTable}.pid = {$pTable}.id AND {$aTable}.trash IS FALSE) AS ad_count,
                  (SELECT COUNT(*) FROM {$aTable} WHERE {$aTable}.pid = {$pTable}.id AND {$aTable}.trash IS FALSE AND ({$whereClause}){$whereClauseT}{$whereClause2W}) AS ad_logic_count,
                  (SELECT COUNT(*) FROM {$aTable} WHERE {$aTable}.pid = {$pTable}.id AND {$aTable}.trash IS FALSE AND ({$whereClause}){$whereClauseT}{$whereClauseW}) AS ad_full_count
                FROM {$pTable}
                WHERE {$pId} AND {$pTable}.trash IS FALSE";
      
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
        $wpdb->query("UPDATE {$pTable} SET {$pTable}.patch_hits = {$pTable}.patch_hits+1 WHERE {$pTable}.id = {$place['id']}");
        return $output;
      }
      
      if(($place['patch_source'] == 1) && (abs($place['patch_adserver']) == 1)) {
        $output = $place['patch_code'];
        if($useCodes) $output = $place['code_before'].$output.$place['code_after'];
        $wpdb->query("UPDATE {$pTable} SET {$pTable}.patch_hits = {$pTable}.patch_hits+1 WHERE {$pTable}.id = {$place['id']}");
        return $output;
      }
      
      if((abs($place['ad_count']) == 0) || (abs($place['ad_logic_count']) == 0)) {
        if($place['patch_source'] == 0) {
          if(!empty($place['patch_link']) && !empty($place['patch_img'])) 
            $output = "<a href='{$place['patch_link']}'><img src='{$place['patch_img']}' /></a>";
          else $output = '';
        }
        else $output = $place['patch_code'];
        $wpdb->query("UPDATE {$pTable} SET {$pTable}.patch_hits = {$pTable}.patch_hits+1 WHERE {$pTable}.id = {$place['id']}");
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
          if(!empty($ad['ad_target']) && !empty($ad['ad_img']))
            $output = "<a href='{$ad['ad_target']}' target='_blank'><img{$outId} src='{$ad['ad_img']}' /></a>";
          else $output = '';
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
  }
}
?>
