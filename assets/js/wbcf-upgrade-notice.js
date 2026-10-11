/**
 * Handles the dismiss control on the "Upgrade to Pro" admin banner.
 * Hides the banner immediately, then tells the server to remember the
 * dismissal permanently (via user meta) so the banner does not come
 * back on a later page load, a different screen, or a new session.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var banner = document.querySelector( '.wbcf-upgrade-banner' );
		if ( ! banner ) {
			return;
		}

		var dismissBtn = banner.querySelector( '.wbcf-upgrade-banner-dismiss' );
		if ( ! dismissBtn || 'undefined' === typeof wbcfUpgradeNotice ) {
			return;
		}

		dismissBtn.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			banner.style.display = 'none';

			var data = new FormData();
			data.append( 'action', wbcfUpgradeNotice.action );
			data.append( 'nonce', wbcfUpgradeNotice.nonce );

			fetch( wbcfUpgradeNotice.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} );
		} );
	} );
} )();
