(function($){
	var container = $('#authy_for_wp_user');

	container.html( '<tr><th></th><td><a class="button thickbox" href="' + window.AuthyForWP.ajax + '&KeepThis=true&TB_iframe=true&height=250&width=450">Manage Authy Connection</a></td></tr>' );

	$( '.button', container ).on( 'click', function( ev ) {
		ev.preventDefault();

		// alert( 'Clicked!' );
	} );
})(jQuery);