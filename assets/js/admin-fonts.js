/**
 * Fonts admin — Google Fonts browser and display label editor.
 */
( function ( $ ) {
	'use strict';

	const config = window.wcGpdFontsAdmin || {};
	const stateInput = document.getElementById( 'wc_gpd_fonts_state' );
	const installedList = document.getElementById( 'wc-gpd-installed-fonts-list' );
	const resultsEl = document.getElementById( 'wc-gpd-google-font-results' );
	const searchInput = document.getElementById( 'wc-gpd-google-font-search' );
	const defaultSelect = document.getElementById( 'wc-gpd-default-font-select' );

	let state = { enabled: [], google_fonts: [], custom: [], display_labels: {}, catalog: {} };

	function loadState() {
		if ( ! stateInput || ! stateInput.value ) {
			return;
		}
		try {
			state = JSON.parse( stateInput.value );
		} catch ( e ) {
			state = { enabled: [], google_fonts: [], custom: [], catalog: {} };
		}
		state.google_fonts = state.google_fonts || [];
		state.custom = state.custom || [];
		state.enabled = state.enabled || [];
		state.display_labels = state.display_labels || {};
		state.catalog = state.catalog || {};
	}

	function persistState() {
		if ( stateInput ) {
			stateInput.value = JSON.stringify( {
				enabled: state.enabled,
				google_fonts: state.google_fonts,
				custom: state.custom,
				display_labels: state.display_labels,
			} );
		}
		rebuildCatalog();
		renderInstalled();
		renderDefaultSelect();
	}

	function rebuildCatalog() {
		const catalog = { ...( state.catalog || {} ) };
		state.google_fonts.forEach( ( font ) => {
			if ( font && font.key ) {
				catalog[ font.key ] = font;
			}
		} );
		state.custom.forEach( ( font ) => {
			if ( font && font.id ) {
				catalog[ 'custom:' + font.id ] = {
					key: 'custom:' + font.id,
					family: font.family || ( 'wc-gpd-custom-' + font.id ),
					label: font.label || font.id,
					display_label: font.display_label || font.label || font.id,
					url: font.url || '',
				};
			}
		} );
		Object.keys( state.display_labels || {} ).forEach( ( key ) => {
			if ( catalog[ key ] ) {
				catalog[ key ].display_label = state.display_labels[ key ];
			}
		} );
		state.catalog = catalog;
	}

	function fontRow( key ) {
		if ( ! key ) {
			return null;
		}
		if ( state.catalog && state.catalog[ key ] ) {
			return state.catalog[ key ];
		}
		const customId = key.indexOf( 'custom:' ) === 0 ? key.slice( 7 ) : '';
		if ( customId ) {
			const custom = state.custom.find( ( row ) => row.id === customId );
			if ( custom ) {
				return {
					key,
					family: custom.family || ( 'wc-gpd-custom-' + custom.id ),
					label: custom.label || custom.id,
					display_label: custom.display_label || custom.label || custom.id,
				};
			}
		}
		return null;
	}

	function renderInstalled() {
		if ( ! installedList ) {
			return;
		}
		installedList.innerHTML = '';
		if ( ! state.enabled.length ) {
			const tr = document.createElement( 'tr' );
			tr.innerHTML = '<td colspan="4">' + ( config.i18n?.noResults || 'No fonts installed.' ) + '</td>';
			installedList.appendChild( tr );
			return;
		}

		state.enabled.forEach( ( key ) => {
			const row = fontRow( key );
			if ( ! row ) {
				return;
			}
			const tr = document.createElement( 'tr' );
			const css = row.family || 'inherit';
			const original = row.label || key;
			const display = row.display_label || original;

			tr.innerHTML = ''
				+ '<td class="wc-gpd-font-preview" style="font-family:' + css + '">' + display + '</td>'
				+ '<td class="wc-gpd-font-original">' + original + '</td>'
				+ '<td><input type="text" class="wc-gpd-font-display-input regular-text" value="' + display.replace( /"/g, '&quot;' ) + '" /></td>'
				+ '<td><button type="button" class="button-link-delete wc-gpd-remove-font">' + ( config.i18n?.remove || 'Remove' ) + '</button></td>';

			const displayInput = tr.querySelector( '.wc-gpd-font-display-input' );
			displayInput.addEventListener( 'input', () => {
				updateDisplayLabel( key, displayInput.value );
			} );

			tr.querySelector( '.wc-gpd-remove-font' ).addEventListener( 'click', () => {
				removeFont( key );
			} );

			installedList.appendChild( tr );
		} );
	}

	function renderDefaultSelect() {
		if ( ! defaultSelect ) {
			return;
		}
		const current = defaultSelect.value;
		defaultSelect.innerHTML = '';
		state.enabled.forEach( ( key ) => {
			const row = fontRow( key );
			if ( ! row ) {
				return;
			}
			const option = document.createElement( 'option' );
			option.value = key;
			option.textContent = row.display_label || row.label || key;
			option.style.fontFamily = row.family || 'inherit';
			defaultSelect.appendChild( option );
		} );
		if ( current ) {
			defaultSelect.value = current;
		}
	}

	function updateDisplayLabel( key, value ) {
		if ( key.indexOf( 'custom:' ) === 0 ) {
			const id = key.slice( 7 );
			const custom = state.custom.find( ( row ) => row.id === id );
			if ( custom ) {
				custom.display_label = value;
			}
		} else {
			let google = state.google_fonts.find( ( row ) => row.key === key );
			if ( google ) {
				google.display_label = value;
			} else {
				state.display_labels[ key ] = value;
			}
			if ( state.catalog[ key ] ) {
				state.catalog[ key ].display_label = value;
			}
		}
		persistState();
	}

	function addGoogleFont( family ) {
		const key = family.toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_|_$/g, '' );
		if ( state.enabled.includes( key ) ) {
			return;
		}
		const needsQuotes = family.indexOf( ' ' ) >= 0;
		const cssFamily = ( needsQuotes ? '"' + family + '"' : family ) + ', sans-serif';
		const row = {
			key,
			family: cssFamily,
			label: family,
			display_label: family,
			weights: '400,700',
			google: family.replace( / /g, '+' ),
		};
		const existing = state.google_fonts.findIndex( ( f ) => f.key === key );
		if ( existing >= 0 ) {
			state.google_fonts[ existing ] = row;
		} else {
			state.google_fonts.push( row );
		}
		state.catalog[ key ] = row;
		state.enabled.push( key );
		persistState();
	}

	function removeFont( key ) {
		state.enabled = state.enabled.filter( ( k ) => k !== key );
		if ( key.indexOf( 'custom:' ) === 0 ) {
			const id = key.slice( 7 );
			state.custom = state.custom.filter( ( row ) => row.id !== id );
		}
		persistState();
	}

	function searchGoogleFonts( query ) {
		if ( ! resultsEl ) {
			return;
		}
		resultsEl.innerHTML = '<li class="wc-gpd-fonts-loading">' + ( config.i18n?.searching || 'Searching…' ) + '</li>';

		const url = new URL( config.ajaxUrl || '/wp-admin/admin-ajax.php', window.location.origin );
		url.searchParams.set( 'action', 'wc_gpd_search_google_fonts' );
		url.searchParams.set( 'nonce', config.nonce || '' );
		url.searchParams.set( 'q', query || '' );
		url.searchParams.set( 'limit', '50' );

		fetch( url.toString(), { credentials: 'same-origin' } )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				const contentType = response.headers.get( 'content-type' ) || '';
				if ( contentType.indexOf( 'application/json' ) === -1 ) {
					throw new Error( 'Invalid response from server.' );
				}
				return response.json();
			} )
			.then( ( payload ) => {
				if ( ! payload || ! payload.success || ! payload.data || ! payload.data.fonts ) {
					throw new Error( 'No results' );
				}
				renderResults( payload.data.fonts );
			} )
			.catch( () => {
				resultsEl.innerHTML = '<li class="wc-gpd-fonts-error">' + ( config.i18n?.noResults || 'Could not load fonts.' ) + '</li>';
			} );
	}

	function renderResults( fonts ) {
		if ( ! resultsEl ) {
			return;
		}
		resultsEl.innerHTML = '';
		if ( ! fonts.length ) {
			resultsEl.innerHTML = '<li>' + ( config.i18n?.noResults || 'No fonts found.' ) + '</li>';
			return;
		}
		fonts.forEach( ( font ) => {
			const li = document.createElement( 'li' );
			li.className = 'wc-gpd-google-font-result';
			const key = font.family.toLowerCase().replace( /[^a-z0-9]+/g, '_' ).replace( /^_|_$/g, '' );
			const installed = state.enabled.includes( key );
			li.innerHTML = ''
				+ '<span class="wc-gpd-google-font-name" style="font-family:\'' + font.family + '\', sans-serif">' + font.family + '</span>'
				+ '<span class="wc-gpd-google-font-meta">' + ( font.category || '' ) + '</span>'
				+ '<button type="button" class="button button-small" ' + ( installed ? 'disabled' : '' ) + '>'
				+ ( installed ? ( config.i18n?.added || 'Added' ) : ( config.i18n?.addFont || 'Add' ) )
				+ '</button>';
			if ( ! installed ) {
				li.querySelector( 'button' ).addEventListener( 'click', () => {
					addGoogleFont( font.family );
					renderResults( fonts );
				} );
			}
			resultsEl.appendChild( li );
		} );
	}

	document.getElementById( 'wc-gpd-google-font-search-btn' )?.addEventListener( 'click', () => {
		searchGoogleFonts( searchInput ? searchInput.value : '' );
	} );

	searchInput?.addEventListener( 'keydown', ( event ) => {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			searchGoogleFonts( searchInput.value );
		}
	} );

	document.getElementById( 'wc-gpd-add-custom-font' )?.addEventListener( 'click', () => {
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		const frame = wp.media( { title: 'Upload font', button: { text: 'Use font' }, library: { type: [] }, multiple: false } );
		frame.on( 'select', () => {
			const att = frame.state().get( 'selection' ).first().toJSON();
			const id = 'font_' + Date.now().toString( 36 );
			const row = {
				id,
				label: att.title || 'Custom font',
				display_label: att.title || 'Custom font',
				attachment_id: att.id,
				family: 'wc-gpd-custom-' + id,
				url: att.url || '',
			};
			state.custom.push( row );
			state.enabled.push( 'custom:' + id );
			persistState();
		} );
		frame.open();
	} );

	loadState();
	rebuildCatalog();
	renderInstalled();
	searchGoogleFonts( '' );
}( jQuery ) );
