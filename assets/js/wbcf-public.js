( function () {
	'use strict';

	function onReady( fn ) {
		if ( document.readyState !== 'loading' ) fn();
		else document.addEventListener( 'DOMContentLoaded', fn );
	}

	function ajaxPost( params ) {
		var body = new URLSearchParams();
		Object.keys( params ).forEach( function ( key ) {
			if ( params[ key ] !== null && params[ key ] !== undefined && params[ key ] !== '' ) {
				body.set( key, params[ key ] );
			}
		} );
		return fetch( window.wbcfPublic.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			credentials: 'same-origin',
			body: body.toString()
		} ).then( function ( res ) { return res.json(); } );
	}

	function overridesFor( widget ) {
		return {
			override_ppp: widget.getAttribute( 'data-override-ppp' ),
			override_template: widget.getAttribute( 'data-override-template' )
		};
	}

	/* -------------------- Skeleton loading -------------------- */

	function buildSkeletonHtml( count ) {
		count = Math.max( 1, Math.min( 12, count || 6 ) );
		var card = '<div class="wbcf-skeleton-card">' +
			'<div class="wbcf-skeleton-thumb"></div>' +
			'<div class="wbcf-skeleton-body">' +
			'<div class="wbcf-skeleton-line wbcf-skeleton-line--meta"></div>' +
			'<div class="wbcf-skeleton-line wbcf-skeleton-line--title"></div>' +
			'<div class="wbcf-skeleton-line wbcf-skeleton-line--short"></div>' +
			'</div></div>';
		return '<div class="wbcf-grid wbcf-skeleton-grid">' + card.repeat( count ) + '</div>';
	}

	/* -------------------- Tab switching -------------------- */

	function fetchTab( widget, tab ) {
		var panel    = widget.querySelector( '.wbcf-panel' );
		var filterId = widget.getAttribute( 'data-filter-id' );
		var ppp      = parseInt( widget.getAttribute( 'data-ppp' ), 10 ) || 6;

		panel.setAttribute( 'aria-busy', 'true' );
		panel.innerHTML = buildSkeletonHtml( ppp );

		var params = Object.assign(
			{ action: 'wbcf_filter_posts', nonce: window.wbcfPublic.nonce, filter_id: filterId, tab: tab },
			overridesFor( widget )
		);

		ajaxPost( params )
			.then( function ( json ) {
				if ( json && json.success ) {
					panel.innerHTML = json.data.html;
				} else {
					panel.innerHTML = '<p class="wbcf-error">' + window.wbcfPublic.i18n.error + '</p>';
				}
			} )
			.catch( function () {
				panel.innerHTML = '<p class="wbcf-error">' + window.wbcfPublic.i18n.error + '</p>';
			} )
			.finally( function () {
				panel.setAttribute( 'aria-busy', 'false' );
				initPagination( widget );
			} );
	}

	function rememberTab( filterId, term ) {
		try {
			window.localStorage.setItem( 'wbcf_last_tab_' + filterId, term );
		} catch ( e ) { /* private-mode / storage disabled -- silently skip */ }
	}

	function activateTab( widget, tabButton ) {
		var tabs = widget.querySelectorAll( '.wbcf-tab' );
		tabs.forEach( function ( t ) {
			var isActive = t === tabButton;
			t.classList.toggle( 'is-active', isActive );
			t.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			t.setAttribute( 'tabindex', isActive ? '0' : '-1' );
		} );

		var filterId = widget.getAttribute( 'data-filter-id' );
		var term = tabButton.getAttribute( 'data-term' );

		widget.setAttribute( 'data-active', term );
		fetchTab( widget, term );
		rememberTab( filterId, term );

		// Reflect the active tab in the URL so it's shareable/bookmarkable
		// AND so the server renders the right tab on the next load --
		// see WBCF_Render::tab_from_request() on the PHP side.
		if ( window.history && window.history.replaceState ) {
			var url = new URL( window.location.href );
			url.searchParams.set( 'wbcf_tab_' + filterId, term );
			window.history.replaceState( null, '', url );
		}
	}

	/* -------------------- Load More / Infinite Scroll -------------------- */

	function initPagination( widget ) {
		var panel = widget.querySelector( '.wbcf-panel' );
		var grid  = panel.querySelector( '.wbcf-grid' );
		if ( ! grid ) return;

		var filterId = widget.getAttribute( 'data-filter-id' );
		var tab      = widget.getAttribute( 'data-active' );

		bindLoadMore( widget, panel, grid, filterId, tab );
		bindInfiniteScroll( widget, panel, grid, filterId, tab );
	}

	function fetchMore( widget, filterId, tab, page ) {
		var params = Object.assign(
			{ action: 'wbcf_load_more', nonce: window.wbcfPublic.nonce, filter_id: filterId, tab: tab, page: page },
			overridesFor( widget )
		);
		return ajaxPost( params );
	}

	function bindLoadMore( widget, panel, grid, filterId, tab ) {
		var button = panel.querySelector( '.wbcf-load-more' );
		if ( ! button ) return;

		button.addEventListener( 'click', function () {
			var page = parseInt( button.getAttribute( 'data-next-page' ), 10 ) || 2;
			var originalText = button.textContent;

			button.disabled = true;
			button.textContent = window.wbcfPublic.i18n.loading;

			fetchMore( widget, filterId, tab, page )
				.then( function ( json ) {
					if ( json && json.success ) {
						grid.insertAdjacentHTML( 'beforeend', json.data.html );
						if ( json.data.has_more ) {
							button.setAttribute( 'data-next-page', String( page + 1 ) );
						} else if ( button.parentElement ) {
							button.parentElement.remove();
							return;
						}
						button.disabled = false;
						button.textContent = originalText;
					} else {
						button.disabled = false;
						button.textContent = originalText;
					}
				} )
				.catch( function () {
					button.disabled = false;
					button.textContent = originalText;
				} );
		} );
	}

	function bindInfiniteScroll( widget, panel, grid, filterId, tab ) {
		var sentinel = panel.querySelector( '.wbcf-infinite-sentinel' );
		if ( ! sentinel || ! window.IntersectionObserver ) return;

		var loading = false;

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting || loading || ! sentinel.isConnected ) return;

				var page = parseInt( sentinel.getAttribute( 'data-next-page' ), 10 ) || 2;
				loading = true;
				sentinel.classList.add( 'is-loading' );

				fetchMore( widget, filterId, tab, page )
					.then( function ( json ) {
						if ( json && json.success ) {
							grid.insertAdjacentHTML( 'beforeend', json.data.html );
							if ( json.data.has_more ) {
								sentinel.setAttribute( 'data-next-page', String( page + 1 ) );
							} else {
								observer.disconnect();
								sentinel.remove();
							}
						}
					} )
					.catch( function () {} )
					.finally( function () {
						loading = false;
						sentinel.classList.remove( 'is-loading' );
					} );
			} );
		}, { rootMargin: '200px 0px' } );

		observer.observe( sentinel );
	}

	/* -------------------- Tabs setup + keyboard nav -------------------- */

	function initTabs( widget ) {
		var tabs = widget.querySelectorAll( '.wbcf-tab' );

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				activateTab( widget, tab );
			} );

			// Arrow-key navigation per WAI-ARIA tabs pattern.
			tab.addEventListener( 'keydown', function ( e ) {
				var newIndex = null;
				if ( e.key === 'ArrowRight' ) newIndex = ( index + 1 ) % tabs.length;
				if ( e.key === 'ArrowLeft' ) newIndex = ( index - 1 + tabs.length ) % tabs.length;
				if ( newIndex !== null ) {
					e.preventDefault();
					tabs[ newIndex ].focus();
					activateTab( widget, tabs[ newIndex ] );
				}
			} );
		} );
	}

	/* -------------------- Remember last tab -------------------- */

	function restoreRememberedTab( widget ) {
		var filterId = widget.getAttribute( 'data-filter-id' );
		var urlParam = 'wbcf_tab_' + filterId + '=';

		// If the URL already names a tab, the server already rendered it
		// (see WBCF_Render::tab_from_request()) -- don't override that
		// with a possibly-stale localStorage value.
		if ( window.location.search.indexOf( urlParam ) !== -1 ) return;

		var remembered = null;
		try {
			remembered = window.localStorage.getItem( 'wbcf_last_tab_' + filterId );
		} catch ( e ) { return; }

		if ( ! remembered || remembered === widget.getAttribute( 'data-active' ) ) return;

		var target = null;
		widget.querySelectorAll( '.wbcf-tab' ).forEach( function ( t ) {
			if ( t.getAttribute( 'data-term' ) === remembered ) target = t;
		} );
		if ( target ) activateTab( widget, target );
	}

	function initWidget( widget ) {
		initTabs( widget );
		initPagination( widget );
		restoreRememberedTab( widget );
	}

	onReady( function () {
		document.querySelectorAll( '.wbcf-widget' ).forEach( initWidget );
	} );
} )();
