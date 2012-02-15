/**
 * @author minimus
 * @copyright 2010
 */
(function($) {
  $(document).ready(function() {
    var options = $.parseJSON($.ajax({
      url: ajaxurl,
      data: {action: 'get_strings'},
      async: false,
      dataType: 'jsonp'
    }).responseText);
      
    var btnUpload = $("#upload-file-button");
    var status = $("#uploading");
    var srcHelp = $("#uploading-help");
    var loadImg = $('#load_img');
    var fileExt = '';
      
    var fu = new AjaxUpload(btnUpload, {  
      action: ajaxurl,  
      name: 'uploadfile',
      data: {
        action: 'upload_ad_image'
      },
      onSubmit: function(file, ext){  
        if (! (ext && /^(jpg|png|jpeg|gif)$/.test(ext))){  
          status.text(options.status);  
          return false;  
        }
        loadImg.show();
        status.text(options.uploading);  
      },  
      onComplete: function(file, response){
        status.text('');
        loadImg.hide();
        $('<div id="files"></div>').appendTo(srcHelp);
        if(response=="success"){  
          $("#files").text(options.file+' '+file+' '+options.uploaded)
            .addClass('updated')
            .delay(3000)
            .fadeOut(1000, function() {
              $(this).remove();
            });
          if($('#editor_mode').val() == 'item') $("#ad_img").val(options.url + file);
          if($('#editor_mode').val() == 'place') $("#patch_img").val(options.url + file);
        }
        else {          
          $('#files').text(file+' '+response)
            .addClass('error')
            .delay(3000)
            .fadeOut(1000, function() {
              $(this).remove();
            });
        }  
      }  
    });
    
    if($('#editor_mode').val() == 'item') {
      $( "#ad_start_date, #ad_end_date" ).datepicker({
        dateFormat: 'yy-mm-dd',
        showButtonPanel: true
      });
      
      var availableCats = options.cats;
      var availableAuthors = options.authors;
      var availableTags = options.tags;
      var availableCustoms = options.customs;
      
      function split( val ) {
        return val.split( /,\s*/ );
      }
      
      function extractLast( term ) {
        return split( term ).pop();
      }

      $( "#view_cats, #x_view_cats" ).autocomplete({
        minLength: 0,
        source: function( request, response ) {
          response( $.ui.autocomplete.filter(
            availableCats, extractLast( request.term ) ) );
        },
        focus: function() {
          return false;
        },
        select: function( event, ui ) {
          var terms = split( this.value );
          terms.pop();
          terms.push( ui.item.value );
          terms.push( "" );
          this.value = terms.join( "," );
          return false;
        }
      });
      
      $( "#view_authors, #x_view_authors" ).autocomplete({
        minLength: 0,
        source: function( request, response ) {
          response( $.ui.autocomplete.filter(
            availableAuthors, extractLast( request.term ) ) );
        },
        focus: function() {
          return false;
        },
        select: function( event, ui ) {
          var terms = split( this.value );
          terms.pop();
          terms.push( ui.item.value );
          terms.push( "" );
          this.value = terms.join( "," );
          return false;
        }
      });
      
      $( "#view_tags, #x_view_tags" ).autocomplete({
        minLength: 0,
        source: function( request, response ) {
          response( $.ui.autocomplete.filter(
            availableTags, extractLast( request.term ) ) );
        },
        focus: function() {
          return false;
        },
        select: function( event, ui ) {
          var terms = split( this.value );
          terms.pop();
          terms.push( ui.item.value );
          terms.push( "" );
          this.value = terms.join( "," );
          return false;
        }
      });
      
      $( "#view_custom, #x_view_custom" ).autocomplete({
        minLength: 0,
        source: function( request, response ) {
          response( $.ui.autocomplete.filter(
            availableCustoms, extractLast( request.term ) ) );
        },
        focus: function() {
          return false;
        },
        select: function( event, ui ) {
          var terms = split( this.value );
          terms.pop();
          terms.push( ui.item.value );
          terms.push( "" );
          this.value = terms.join( "," );
          return false;
        }
      });      
      
      $("#add-file-button").click(function(){
        var curFile = options.url + $("select#files_list option:selected").val();
        $("#ad_img").val(curFile);
        return false;
      });
    }
    
    if($('#editor_mode').val() == 'place') {
      $("#add-file-button").click(function(){
        var curFile = options.url + $("select#files_list option:selected").val();
        $("#patch_img").val(curFile);
        return false;
      });
    }
    
    $('#is_singular').click(function() {
      if($('#is_singular').is(':checked')) 
        $('#is_single, #is_page, #is_attachment, #is_posttype').attr('checked', true);
    });
    
    $('#is_single, #is_page, #is_attachment, #is_posttype').click(function() {
      if($('#is_singular').is(':checked') && 
         (!$('#is_single').is(':checked') || 
          !$('#is_page').is(':checked') || 
          !$('#is_attachment').is(':checked') ||
          !$('#is_posttype').is(':checked') )) {
        $('#is_singular').attr('checked', false);
      }
      else {
        if(!$('#is_singular').is(':checked') && 
            $('#is_single').is(':checked') &&
            $('#is_posttype').is(':checked') &&
            $('#is_page').is(':checked') && 
            $('#is_attachment').is(':checked'))
          $('#is_singular').attr('checked', true);
      }
    });
    
    $('#is_archive').click(function() {
      if($('#is_archive').is(':checked'))
        $('#is_tax, #is_category, #is_tag, #is_author, #is_date, #is_posttype_archive').attr('checked', true);
    });
    
    $('#is_tax, #is_category, #is_tag, #is_author, #is_date, #is_posttype_archive').click(function() {
      if($('#is_archive').is(':checked') &&
         (!$('#is_tax').is(':checked') ||
          !$('#is_category').is(':checked') ||
          !$('#is_posttype_archive').is(':checked') ||
          !$('#is_tag').is(':checked') ||
          !$('#is_author').is(':checked') ||
          !$('#is_date').is(':checked'))) {
        $('#is_archive').attr('checked', false);    
      }
      else {
        if(!$('#is_archive').is(':checked') &&
           $('#is_tax').is(':checked') &&
           $('#is_category').is(':checked') &&
           $('#is_posttype_archive').is(':checked') &&
           $('#is_tag').is(':checked') &&
           $('#is_author').is(':checked') &&
           $('#is_date').is(':checked')) {
          $('#is_archive').attr('checked', true);   
        }
      }
    });
    
    return false;
  });
})(jQuery)