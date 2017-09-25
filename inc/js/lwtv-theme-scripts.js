$("a.dropdown-item").click(function(){
  var selText = $(this).text();
  $(this).parents('.input-group-btn').find('.btn.btn-primary.dropdown-toggle').html(selText+' <span class="caret"></span>');
  
  //optional store val in hidden input
  $('#selVal').val(selText);
});