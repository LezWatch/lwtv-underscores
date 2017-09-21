//Make elements within row.equal-height div all equal heights
jQuery(document).ready(function($) {
    $(window).load(function(){
    //Define equalHeight function
    function equalHeight() {
      if ($(document).width() >= 768 ){

        $('.site-loop.equal-height').each(function() {
          var heightText = 0; //height counter for .panel-body.h4
          $(this).find('.card .card-body .card-text').each(function() {
            $(this).css('height', '');
            heightText = heightText > $(this).outerHeight() ? heightText : $(this).outerHeight();
          });
          $(this).find('.card .card-body .card-text').each(function() {
            $(this).outerHeight(heightText);
          });

          heightText = 0;
        });


        $('.site-loop.equal-height').each(function() {
          var heightTitle = 0; //height counter for .panel-body.h4
          $(this).find('.card .card-body .card-title').each(function() {
            $(this).css('height', '');
            heightTitle = heightTitle > $(this).outerHeight() ? heightTitle : $(this).outerHeight();
          });
          $(this).find('.card .card-body .card-title').each(function() {
            $(this).outerHeight(heightTitle);
          });

          heightTitle = 0;
        });

      }
      else {
        $('.row.site-loop.equal-height').each(function() {
          $(this).find('.card .card-body .card-text').each(function() {
            $(this).css('height', '');
          });
        });
      }
    }

    equalHeight();
    $(window).resize(equalHeight);
    
  });
});