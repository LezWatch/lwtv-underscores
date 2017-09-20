jQuery(document).ready(function($) {
    $(window).load(function(){
      $(document).scroll(function () {
        //Show element after user scrolls 100px
      var y = $(this).scrollTop();
          if (y > 100) {
          $('.header-smalllogo img').fadeIn();

          } else {
            
          $('.header-smalllogo img').fadeOut(100);
        }
    }); 
  });
});