/**
 * Bootstrap Icons browser for template shapes panel.
 *
 * @see https://icons.getbootstrap.com/ (MIT)
 */
( function () {
	'use strict';

	const config = window.wcGpdBootstrapIcons || {};
	const searchInput = document.getElementById( 'wc-gpd-bootstrap-icon-search' );
	const limitSelect = document.getElementById( 'wc-gpd-bootstrap-icon-limit' );
	const resultsEl = document.getElementById( 'wc-gpd-bootstrap-icon-results' );
	const featuredEl = document.getElementById( 'wc-gpd-bootstrap-icon-featured' );
	const statusEl = document.getElementById( 'wc-gpd-bootstrap-icon-status' );
	const loadMoreWrap = document.getElementById( 'wc-gpd-bootstrap-icon-load-more-wrap' );
	const loadMoreBtn = document.getElementById( 'wc-gpd-bootstrap-icon-load-more' );

	const state = {
		query: '',
		limit: 60,
		offset: 0,
		total: 0,
		icons: [],
		loading: false,
		iconBaseUrl: config.iconBaseUrl || '',
	};

	function iconPreviewUrl( slug ) {
		if ( state.iconBaseUrl ) {
			return state.iconBaseUrl + slug + '.svg';
		}
		return '';
	}

	function bindIconButton( btn, slug ) {
		btn.addEventListener( 'click', () => {
			if ( typeof window.wcGpdAddBootstrapIcon === 'function' ) {
				window.wcGpdAddBootstrapIcon( slug );
			}
		} );
	}

	function renderIconButton( slug ) {
		const btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'wc-gpd-shape-library-btn wc-gpd-bootstrap-icon-btn';
		btn.title = slug;
		const url = iconPreviewUrl( slug );
		if ( url ) {
			btn.innerHTML = `<img src="${ url }" alt="" width="28" height="28" loading="lazy" /><span>${ slug.replace( /-/g, ' ' ) }</span>`;
		} else {
			btn.innerHTML = `<span class="wc-gpd-bootstrap-icon-fallback">${ slug.charAt( 0 ).toUpperCase() }</span><span>${ slug }</span>`;
		}
		bindIconButton( btn, slug );
		return btn;
	}

	function renderFeatured( slugs ) {
		if ( ! featuredEl || ! slugs || ! slugs.length ) {
			return;
		}
		featuredEl.innerHTML = '';
		slugs.forEach( ( slug ) => {
			featuredEl.appendChild( renderIconButton( slug ) );
		} );
	}

	function updateStatus() {
		if ( ! statusEl ) {
			return;
		}
		const count = state.icons.length;
		if ( ! count && ! state.loading ) {
			statusEl.hidden = true;
			return;
		}
		const template = config.i18n?.showing || 'Showing %1$s–%2$s of %3$s';
		statusEl.textContent = template
			.replace( '%1$s', count ? '1' : '0' )
			.replace( '%2$s', String( count ) )
			.replace( '%3$s', String( state.total || count ) );
		statusEl.hidden = false;
	}

	function updateLoadMore() {
		if ( ! loadMoreWrap ) {
			return;
		}
		loadMoreWrap.hidden = state.loading || state.icons.length >= state.total;
	}

	function renderResults() {
		if ( ! resultsEl ) {
			return;
		}
		resultsEl.innerHTML = '';
		if ( ! state.icons.length ) {
			resultsEl.innerHTML = '<p class="description">' + ( config.i18n?.noResults || 'No icons found.' ) + '</p>';
			updateStatus();
			updateLoadMore();
			return;
		}
		state.icons.forEach( ( slug ) => {
			resultsEl.appendChild( renderIconButton( slug ) );
		} );
		updateStatus();
		updateLoadMore();
	}

	function fetchIcons( append ) {
		if ( state.loading ) {
			return;
		}
		const query = searchInput ? searchInput.value.trim() : '';
		const limit = limitSelect ? parseInt( limitSelect.value, 10 ) || 60 : 60;

		if ( ! append ) {
			state.offset = 0;
			state.icons = [];
			state.query = query;
			state.limit = limit;
			if ( resultsEl ) {
				resultsEl.innerHTML = '<p class="description">' + ( config.i18n?.searching || 'Loading…' ) + '</p>';
			}
		} else {
			state.offset = state.icons.length;
		}

		state.loading = true;
		updateLoadMore();

		const url = new URL( config.ajaxUrl || '/wp-admin/admin-ajax.php', window.location.origin );
		url.searchParams.set( 'action', 'wc_gpd_search_bootstrap_icons' );
		url.searchParams.set( 'nonce', config.nonce || '' );
		url.searchParams.set( 'q', query );
		url.searchParams.set( 'limit', String( limit ) );
		url.searchParams.set( 'offset', String( state.offset ) );

		fetch( url.toString(), { credentials: 'same-origin' } )
			.then( ( response ) => response.json() )
			.then( ( payload ) => {
				if ( ! payload || ! payload.success || ! payload.data ) {
					throw new Error( 'search failed' );
				}
				state.total = payload.data.total || 0;
				if ( ! append && payload.data.featured ) {
					renderFeatured( payload.data.featured );
				}
				const page = payload.data.icons || [];
				state.icons = append ? state.icons.concat( page ) : page;
				renderResults();
			} )
			.catch( () => {
				if ( ! append && resultsEl ) {
					resultsEl.innerHTML = '<p class="description">' + ( config.i18n?.noResults || 'Could not load icons.' ) + '</p>';
				}
			} )
			.finally( () => {
				state.loading = false;
				updateLoadMore();
			} );
	}

	document.getElementById( 'wc-gpd-bootstrap-icon-search-btn' )?.addEventListener( 'click', () => {
		fetchIcons( false );
	} );

	searchInput?.addEventListener( 'keydown', ( event ) => {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			fetchIcons( false );
		}
	} );

	limitSelect?.addEventListener( 'change', () => {
		fetchIcons( false );
	} );

	loadMoreBtn?.addEventListener( 'click', () => {
		fetchIcons( true );
	} );

	if ( resultsEl ) {
		fetchIcons( false );
	}
}() );
