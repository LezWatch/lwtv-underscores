// Get the dropdown search to stick

jQuery("a.dropdown-item").click(function(){
  var selText = $(this).text();
  jQuery(this).parents('.input-group-btn').find('.btn.btn-primary.dropdown-toggle').html(selText+' <span class="caret"></span>');
  
  //optional store val in hidden input
  jQuery('#selVal').val(selText);
});


// Search Box toggle
jQuery(document).ready(function($) {
	$(function () {
	    $('a[href="#search"]').on('click', function(event) {
	        event.preventDefault();
	        $('#search').addClass('open');
	        $('#search > form > input[type="search"]').focus();
	    });
	    
	    $('#search, #search button.close').on('click keyup', function(event) {
	        if (event.target == this || event.target.className == 'close' || event.keyCode == 27) {
	            $(this).removeClass('open');
	        }
	    });
	});
});