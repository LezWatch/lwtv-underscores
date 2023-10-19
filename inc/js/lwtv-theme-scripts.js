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

// Smoothscrolling for  TOC
jQuery(document).ready(function($) {
  $('a.smoothscroll.breadcrumb-item[href*="#"]:not([href="#"])').click(function() {
    if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
      var target = $(this.hash);
      target = target.length ? target : $('[name=' + this.hash.slice(1) + ']');
      if (target.length) {
        $('html, body').animate({
          scrollTop: target.offset().top -55
        }, 1000);
        return false;
      }
    }
  });
});

// Tooltips
jQuery(document).ready(function($) {
	$(function () {
		$('[data-bs-target="tooltip"]').tooltip();
	});
});
