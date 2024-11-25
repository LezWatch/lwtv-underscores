// Smooths scrolling for TOC
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
