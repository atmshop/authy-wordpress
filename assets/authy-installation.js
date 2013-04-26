jQuery(document).ready(function($) {
  $("#request-sms-link").on( 'click', function( ev ) {
    ev.preventDefault();
    $.ajax({
      url:  AuthyAjax.ajaxurl,
      data: ({action : 'request_sms_ajax', username: 'other'}),
      success: function(msg) {
        alert(msg);
      }
    });
  });
});