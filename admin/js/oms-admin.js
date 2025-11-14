/**
 * Admin JavaScript for Obfuscated Malware Scanner.
 *
 * @package ObfuscatedMalwareScanner
 */

(function ( $ ) {
	'use strict';

	$(
		function () {
			// Handle review button clicks.
			$( '.oms-issues .button' ).on(
				'click',
				function (e) {
					e.preventDefault();
					var file = $( this ).data( 'file' );
					// Add your review functionality here.
					console.log( 'Reviewing file:', file );
				}
			);
		}
	);

})( jQuery );
