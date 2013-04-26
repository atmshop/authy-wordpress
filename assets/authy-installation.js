jQuery(document).ready(function($) {
  $(".request-sms-link").on( 'click', function( ev ) {
    ev.preventDefault();

    var username = $(this).data('username');
    var signature = $(this).data('signature');

    $.ajax({
      url:  AuthyAjax.ajaxurl,
      data: ({action : 'request_sms_ajax', username: username, signature: signature}),
      success: function(msg) {
        alert(msg);
      }
    });
  });
});