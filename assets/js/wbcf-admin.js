( function () {
	'use strict';

	function onReady( fn ) {
		if ( document.readyState !== 'loading' ) fn();
		else document.addEventListener( 'DOMContentLoaded', fn );
	}

	function ajax( action, params ) {
		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', window.wbcfAdmin.nonce );
		Object.keys( params ).forEach( function ( key ) { body.set( key, params[ key ] ); } );

		return fetch( window.wbcfAdmin.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			credentials: 'same-origin',
			body: body.toString()
		} ).then( function ( res ) { return res.json(); } );
	}

	function currentPostType() {
		var checked = document.querySelector( 'input[name="wbcf_post_type"]:checked' );
		return checked ? checked.value : '';
	}

	function updatePtCardStyles() {
		document.querySelectorAll( '.wbcf-pt-card' ).forEach( function ( card ) {
			var input = card.querySelector( 'input' );
			card.classList.toggle( 'is-selected', input.checked );
		} );
	}

	function updateSummary( taxLabel, termCount ) {
		var ptEl   = document.getElementById( 'wbcf-summary-pt' );
		var taxEl  = document.getElementById( 'wbcf-summary-taxonomy' );
		var cntEl  = document.getElementById( 'wbcf-summary-terms' );
		if ( ptEl ) ptEl.textContent = currentPostType() || '—';
		if ( taxEl ) taxEl.textContent = taxLabel || '—';
		if ( cntEl !== null && termCount !== undefined ) cntEl.textContent = termCount;
	}

	function rebuildDefaultTabOptions( checkedTerms ) {
		var select = document.getElementById( 'wbcf_default_tab' );
		if ( ! select ) return;

		var previous = select.getAttribute( 'data-current' ) || select.value || 'all';
		var showAll  = document.getElementById( 'wbcf_show_all_tab' );

		select.innerHTML = '';

		if ( ! showAll || showAll.checked ) {
			var allOpt = document.createElement( 'option' );
			allOpt.value = 'all';
			allOpt.textContent = window.wbcfAdmin.i18n.all;
			select.appendChild( allOpt );
		}

		checkedTerms.forEach( function ( term ) {
			var opt = document.createElement( 'option' );
			opt.value = String( term.id );
			opt.textContent = term.name;
			select.appendChild( opt );
		} );

		if ( [].some.call( select.options, function ( o ) { return o.value === String( previous ); } ) ) {
			select.value = String( previous );
		}
		select.removeAttribute( 'data-current' );
	}

	function readCheckedTerms( container ) {
		return Array.prototype.map.call(
			container.querySelectorAll( 'input[type="checkbox"]:checked' ),
			function ( el ) { return { id: el.value, name: el.getAttribute( 'data-name' ) || el.value }; }
		);
	}

	/* -------------------- Terms (step 4) -------------------- */

	function loadTerms( taxonomy, selectedIds, container ) {
		container.innerHTML = '<p class="description">' + window.wbcfAdmin.i18n.loadingTerms + '</p>';

		if ( ! taxonomy ) {
			container.innerHTML = '<p class="description">' + window.wbcfAdmin.i18n.noneTerms + '</p>';
			rebuildDefaultTabOptions( [] );
			updateSummary( '', 0 );
			return;
		}

		ajax( 'wbcf_get_terms', { taxonomy: taxonomy } ).then( function ( json ) {
			if ( ! json || ! json.success || ! json.data.terms.length ) {
				container.innerHTML = '<p class="description">' + window.wbcfAdmin.i18n.noneTerms + '</p>';
				rebuildDefaultTabOptions( [] );
				updateSummary( currentTaxonomyLabel(), 0 );
				return;
			}

			var html = '';
			json.data.terms.forEach( function ( term ) {
				var checked = selectedIds.indexOf( String( term.id ) ) !== -1 ? 'checked' : '';
				html += '<label class="wbcf-tax-checkbox">' +
					'<input type="checkbox" name="wbcf_terms[]" value="' + term.id + '" data-name="' + term.name + '" ' + checked + '> ' +
					term.name + '</label>';
			} );
			container.innerHTML = html;

			bindTermCheckboxes( container );

			var checked = readCheckedTerms( container );
			rebuildDefaultTabOptions( checked );
			updateSummary( currentTaxonomyLabel(), checked.length );
		} ).catch( function () {
			container.innerHTML = '<p class="description">' + window.wbcfAdmin.i18n.error + '</p>';
		} );
	}

	function bindTermCheckboxes( container ) {
		container.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( cb ) {
			cb.addEventListener( 'change', function () {
				var checked = readCheckedTerms( container );
				rebuildDefaultTabOptions( checked );
				updateSummary( currentTaxonomyLabel(), checked.length );
			} );
		} );
	}

	function currentTaxonomyLabel() {
		var select = document.getElementById( 'wbcf_taxonomy' );
		if ( ! select || ! select.selectedOptions.length ) return '';
		return select.selectedOptions[0].textContent;
	}

	/* -------------------- Taxonomy (step 3) -------------------- */

	function loadTaxonomies( postType, selectedTaxonomy, termsContainer, selectedTermIds ) {
		var select = document.getElementById( 'wbcf_taxonomy' );
		if ( ! select ) return;

		select.innerHTML = '<option value="">' + window.wbcfAdmin.i18n.loadingTax + '</option>';

		ajax( 'wbcf_get_taxonomies', { post_type: postType } ).then( function ( json ) {
			if ( ! json || ! json.success || ! json.data.taxonomies.length ) {
				select.innerHTML = '<option value="">' + window.wbcfAdmin.i18n.noneTax + '</option>';
				loadTerms( '', [], termsContainer );
				return;
			}

			select.innerHTML = '';
			json.data.taxonomies.forEach( function ( tax ) {
				var opt = document.createElement( 'option' );
				opt.value = tax.slug;
				opt.textContent = tax.label;
				select.appendChild( opt );
			} );

			var toSelect = selectedTaxonomy && [].some.call( select.options, function ( o ) { return o.value === selectedTaxonomy; } )
				? selectedTaxonomy
				: select.options[0].value;
			select.value = toSelect;

			loadTerms( toSelect, selectedTermIds, termsContainer );
		} ).catch( function () {
			select.innerHTML = '<option value="">' + window.wbcfAdmin.i18n.error + '</option>';
		} );
	}

	onReady( function () {
		var taxSelect     = document.getElementById( 'wbcf_taxonomy' );
		var termsContainer = document.getElementById( 'wbcf-terms-list' );
		var pptInput       = document.getElementById( 'wbcf_posts_per_page' );
		var showAll        = document.getElementById( 'wbcf_show_all_tab' );
		if ( ! taxSelect || ! termsContainer ) return;

		var initialTaxonomy = taxSelect.getAttribute( 'data-current' ) || '';
		var initialTermIds  = ( termsContainer.getAttribute( 'data-selected' ) || '' ).split( ',' ).filter( Boolean );

		var postType = currentPostType();
		if ( postType ) {
			loadTaxonomies( postType, initialTaxonomy, termsContainer, initialTermIds );
		}

		document.querySelectorAll( 'input[name="wbcf_post_type"]' ).forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				updatePtCardStyles();
				// Post type changed -- previous taxonomy/terms selection no longer applies.
				loadTaxonomies( input.value, '', termsContainer, [] );
			} );
		} );

		taxSelect.addEventListener( 'change', function () {
			taxSelect.removeAttribute( 'data-current' );
			loadTerms( taxSelect.value, [], termsContainer );
		} );

		if ( showAll ) {
			showAll.addEventListener( 'change', function () {
				rebuildDefaultTabOptions( readCheckedTerms( termsContainer ) );
			} );
		}

		if ( pptInput ) {
			pptInput.addEventListener( 'input', function () {
				var el = document.getElementById( 'wbcf-summary-ppp' );
				if ( el ) el.textContent = pptInput.value;
			} );
		}

		/* -------------------- Hide Columns for full-width-row templates -------------------- */

		var templateSelect = document.getElementById( 'wbcf_template' );
		var columnsRow      = document.getElementById( 'wbcf-style-columns-row' );

		function syncColumnsVisibility() {
			if ( ! templateSelect || ! columnsRow ) return;

			var selectedOption = templateSelect.options[ templateSelect.selectedIndex ];
			var noColumns = selectedOption && selectedOption.getAttribute( 'data-no-columns' ) === '1';

			var select = document.getElementById( 'wbcf_style_card_columns' );
			var note   = columnsRow.querySelector( '.wbcf-style-columns-na' );
			var normalDescription = columnsRow.querySelector( '.description:not(.wbcf-style-columns-na)' );

			if ( select ) select.style.display = noColumns ? 'none' : '';
			if ( normalDescription ) normalDescription.style.display = noColumns ? 'none' : '';
			if ( note ) note.style.display = noColumns ? '' : 'none';
		}

		if ( templateSelect ) {
			templateSelect.addEventListener( 'change', syncColumnsVisibility );
			syncColumnsVisibility(); // reflect whatever template is already selected on page load
		}

		// WP core color picker (jQuery-based, bundled with wp-admin).
		if ( window.jQuery && jQuery.fn.wpColorPicker ) {
			jQuery( '.wbcf-color-field' ).wpColorPicker();
		}
	} );
} )();
