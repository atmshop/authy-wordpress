(function($){
	var container = $('#authy_for_wp_user');

	container.html( '<tr><th><label>' + window.AuthyForWP.th_text + '</label></th><td><a class="button thickbox" href="' + window.AuthyForWP.ajax + '&KeepThis=true&TB_iframe=true&height=250&width=450">' + window.AuthyForWP.button_text + '</a></td></tr>' );

	$( '.button', container ).on( 'click', function( ev ) {
		ev.preventDefault();
	} );
})(jQuery);