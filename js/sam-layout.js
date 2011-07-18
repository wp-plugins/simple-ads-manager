(function($) {
  $(document).ready(function() {
    $(".sam_ad").click(function() {
      var adId = $(this).attr('id');
      $.ajax({
        type: "POST",
        url: samAjax.ajaxurl,
        data: {
          action: "sam_click", 
          sam_ad_id: adId,
          _ajax_nonce: samAjax._ajax_nonce
        },
        async: false
      });
    });
  });
})(jQuery)