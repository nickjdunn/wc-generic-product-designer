/**
 * Site-wide graphic, photo, and icon libraries admin.
 */
( function ( $ ) {
	'use strict';

	const config = window.wcGpdLibrariesAdmin || {};
	const hidden = document.getElementById( 'wc_gpd_libraries_json' );
	const listEl = document.getElementById( 'wc-gpd-libraries-admin-list' );
	const TYPES = {
		graphic: config.i18n?.typeGraphic || 'Graphics library',
		photo: config.i18n?.typePhoto || 'Photo library',
		icon: config.i18n?.typeIcon || 'Icon library',
	};
	let libraries = [];

	function load() {
		if ( ! hidden || ! hidden.value ) {
			libraries = [];
			return;
		}
		try {
			libraries = JSON.parse( hidden.value ) || [];
		} catch ( e ) {
			libraries = [];
		}
	}

	function syncHidden() {
		if ( hidden ) {
			hidden.value = JSON.stringify( libraries );
		}
	}

	function persist( options ) {
		const opts = options || {};
		syncHidden();
		if ( opts.render !== false ) {
			render();
		}
	}

	function isProtectedLibrary( lib ) {
		return lib && lib.id === 'bootstrap_all';
	}

	function findLibrary( libId ) {
		return libraries.find( ( row ) => row.id === libId );
	}

	function renderIconSlugList( lib, container ) {
		container.innerHTML = '';
		const toolbar = document.createElement( 'div' );
		toolbar.className = 'wc-gpd-library-icon-toolbar';

		const searchInput = document.createElement( 'input' );
		searchInput.type = 'search';
		searchInput.className = 'regular-text';
		searchInput.placeholder = config.i18n?.searchIcons || 'Search icons to add…';

		const searchBtn = document.createElement( 'button' );
		searchBtn.type = 'button';
		searchBtn.className = 'button button-small';
		searchBtn.textContent = config.i18n?.search || 'Search';

		const loadAllBtn = document.createElement( 'button' );
		loadAllBtn.type = 'button';
		loadAllBtn.className = 'button button-small';
		loadAllBtn.textContent = config.i18n?.loadAllIcons || 'Browse all icons';

		const results = document.createElement( 'div' );
		results.className = 'wc-gpd-library-icon-results';

		const loadMoreWrap = document.createElement( 'p' );
		loadMoreWrap.className = 'wc-gpd-library-icon-load-more';
		loadMoreWrap.hidden = true;
		const loadMoreBtn = document.createElement( 'button' );
		loadMoreBtn.type = 'button';
		loadMoreBtn.className = 'button button-small';
		loadMoreBtn.textContent = config.i18n?.loadMoreIcons || 'Load more icons';
		loadMoreWrap.appendChild( loadMoreBtn );

		const slugList = document.createElement( 'ul' );
		slugList.className = 'wc-gpd-library-icon-slugs';

		const browseState = {
			query: '',
			offset: 0,
			total: 0,
			loading: false,
		};

		function renderSlugs() {
			slugList.innerHTML = '';
			( lib.icon_slugs || [] ).forEach( ( slug ) => {
				const li = document.createElement( 'li' );
				li.className = 'wc-gpd-library-icon-slug';
				const baseUrl = config.iconBaseUrl || '';
				if ( baseUrl ) {
					const img = document.createElement( 'img' );
					img.src = baseUrl + slug + '.svg';
					img.alt = '';
					img.width = 20;
					img.height = 20;
					li.appendChild( img );
				}
				const label = document.createElement( 'span' );
				label.textContent = slug;
				li.appendChild( label );
				const rm = document.createElement( 'button' );
				rm.type = 'button';
				rm.className = 'button-link-delete';
				rm.textContent = '×';
				rm.addEventListener( 'click', () => {
					lib.icon_slugs = ( lib.icon_slugs || [] ).filter( ( row ) => row !== slug );
					syncHidden();
					renderSlugs();
				} );
				li.appendChild( rm );
				slugList.appendChild( li );
			} );
		}

		function appendIconButton( slug ) {
			if ( ( lib.icon_slugs || [] ).includes( slug ) ) {
				return;
			}
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wc-gpd-library-icon-pick';
			btn.dataset.slug = slug;
			const baseUrl = config.iconBaseUrl || '';
			if ( baseUrl ) {
				btn.innerHTML = '<img src="' + baseUrl + slug + '.svg" alt="" width="24" height="24" /><span>' + slug + '</span>';
			} else {
				btn.textContent = slug;
			}
			btn.addEventListener( 'click', () => {
				if ( ! lib.icon_slugs ) {
					lib.icon_slugs = [];
				}
				if ( ! lib.icon_slugs.includes( slug ) ) {
					lib.icon_slugs.push( slug );
					syncHidden();
					renderSlugs();
					btn.remove();
				}
			} );
			results.appendChild( btn );
		}

		function fetchIcons( append ) {
			if ( browseState.loading || ! config.ajaxUrl ) {
				return;
			}
			if ( ! append ) {
				browseState.offset = 0;
				results.innerHTML = '<p class="description">' + ( config.i18n?.searching || 'Loading…' ) + '</p>';
			}
			browseState.loading = true;
			loadMoreWrap.hidden = true;

			const url = new URL( config.ajaxUrl, window.location.origin );
			url.searchParams.set( 'action', config.ajaxAction || 'wc_gpd_search_bootstrap_icons' );
			url.searchParams.set( 'nonce', config.nonce || '' );
			url.searchParams.set( 'q', browseState.query );
			url.searchParams.set( 'limit', '60' );
			url.searchParams.set( 'offset', String( browseState.offset ) );

			fetch( url.toString(), { credentials: 'same-origin' } )
				.then( ( response ) => response.json() )
				.then( ( payload ) => {
					if ( ! payload || ! payload.success || ! payload.data || ! payload.data.icons ) {
						results.textContent = config.i18n?.noResults || 'No icons found.';
						return;
					}
					if ( ! append ) {
						results.innerHTML = '';
					}
					browseState.total = payload.data.total || 0;
					payload.data.icons.forEach( appendIconButton );
					browseState.offset = results.querySelectorAll( '.wc-gpd-library-icon-pick' ).length;
					loadMoreWrap.hidden = browseState.offset >= browseState.total;
				} )
				.catch( () => {
					if ( ! append ) {
						results.textContent = config.i18n?.noResults || 'No icons found.';
					}
				} )
				.finally( () => {
					browseState.loading = false;
				} );
		}

		searchBtn.addEventListener( 'click', () => {
			browseState.query = searchInput.value.trim();
			fetchIcons( false );
		} );
		loadAllBtn.addEventListener( 'click', () => {
			searchInput.value = '';
			browseState.query = '';
			fetchIcons( false );
		} );
		loadMoreBtn.addEventListener( 'click', () => {
			browseState.offset = results.querySelectorAll( '.wc-gpd-library-icon-pick' ).length;
			fetchIcons( true );
		} );
		searchInput.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				browseState.query = searchInput.value.trim();
				fetchIcons( false );
			}
		} );

		toolbar.appendChild( searchInput );
		toolbar.appendChild( searchBtn );
		toolbar.appendChild( loadAllBtn );
		container.appendChild( toolbar );
		container.appendChild( results );
		container.appendChild( loadMoreWrap );
		container.appendChild( slugList );
		renderSlugs();
	}

	function renderMediaPreview( lib, preview ) {
		preview.innerHTML = '';
		( lib.ids || [] ).forEach( ( id ) => {
			if ( ! window.wp || ! wp.media ) {
				return;
			}
			wp.media.attachment( id ).fetch().then( () => {
				const att = wp.media.attachment( id );
				const url = att.get( 'url' );
				if ( ! url ) {
					return;
				}
				const li = document.createElement( 'li' );
				li.className = 'wc-gpd-library-thumb-wrap';
				const img = document.createElement( 'img' );
				img.src = url;
				img.alt = att.get( 'title' ) || '';
				const rm = document.createElement( 'button' );
				rm.type = 'button';
				rm.className = 'wc-gpd-library-thumb-remove';
				rm.textContent = '×';
				rm.title = 'Remove';
				rm.addEventListener( 'click', () => {
					lib.ids = ( lib.ids || [] ).filter( ( rowId ) => rowId !== id );
					syncHidden();
					li.remove();
				} );
				li.appendChild( img );
				li.appendChild( rm );
				preview.appendChild( li );
			} );
		} );
	}

	function render() {
		if ( ! listEl ) {
			return;
		}
		listEl.innerHTML = '';
		if ( ! libraries.length ) {
			listEl.innerHTML = '<p class="description">' + ( config.i18n?.emptyLibrary || 'No libraries yet.' ) + '</p>';
			return;
		}

		libraries.forEach( ( lib, index ) => {
			const type = lib.type || 'graphic';
			const protectedLib = isProtectedLibrary( lib );
			const card = document.createElement( 'div' );
			card.className = 'wc-gpd-library-admin-card wc-gpd-library-admin-card--' + type;
			card.dataset.libraryId = lib.id;

			const header = document.createElement( 'div' );
			header.className = 'wc-gpd-library-admin-card__header';

			const typeSelect = document.createElement( 'select' );
			typeSelect.className = 'wc-gpd-library-type-select';
			Object.keys( TYPES ).forEach( ( key ) => {
				const opt = document.createElement( 'option' );
				opt.value = key;
				opt.textContent = TYPES[ key ];
				opt.selected = type === key;
				typeSelect.appendChild( opt );
			} );
			if ( protectedLib ) {
				typeSelect.disabled = true;
			}
			typeSelect.addEventListener( 'change', () => {
				lib.type = typeSelect.value;
				if ( lib.type === 'icon' ) {
					lib.ids = [];
				} else {
					lib.icon_slugs = [];
					lib.all_icons = false;
				}
				persist();
			} );

			const nameInput = document.createElement( 'input' );
			nameInput.type = 'text';
			nameInput.className = 'regular-text wc-gpd-library-name-input';
			nameInput.value = lib.name || '';
			nameInput.placeholder = config.i18n?.libraryName || 'Library name';
			if ( protectedLib ) {
				nameInput.readOnly = true;
			}
			nameInput.addEventListener( 'input', () => {
				lib.name = nameInput.value;
				syncHidden();
			} );

			const removeBtn = document.createElement( 'button' );
			removeBtn.type = 'button';
			removeBtn.className = 'button button-link-delete wc-gpd-library-remove-btn';
			removeBtn.textContent = config.i18n?.removeLibrary || 'Remove';
			if ( protectedLib ) {
				removeBtn.hidden = true;
				removeBtn.disabled = true;
			}
			removeBtn.addEventListener( 'click', () => {
				libraries.splice( index, 1 );
				persist();
			} );

			header.appendChild( typeSelect );
			header.appendChild( nameInput );
			if ( ! protectedLib ) {
				header.appendChild( removeBtn );
			}

			card.appendChild( header );

			if ( type === 'icon' ) {
				const iconBody = document.createElement( 'div' );
				iconBody.className = 'wc-gpd-library-icon-body';

				if ( protectedLib ) {
					const note = document.createElement( 'p' );
					note.className = 'description';
					note.textContent = config.i18n?.allIconsNote || 'This library includes every bundled Bootstrap icon.';
					iconBody.appendChild( note );
					lib.all_icons = true;
				} else {
					const allLabel = document.createElement( 'label' );
					allLabel.className = 'wc-gpd-library-all-icons';
					const allCheck = document.createElement( 'input' );
					allCheck.type = 'checkbox';
					allCheck.checked = !! lib.all_icons;
					allCheck.addEventListener( 'change', () => {
						lib.all_icons = allCheck.checked;
						if ( lib.all_icons ) {
							lib.icon_slugs = [];
						}
						persist();
					} );
					allLabel.appendChild( allCheck );
					allLabel.appendChild( document.createTextNode( ' ' + ( config.i18n?.allIcons || 'Include all Bootstrap icons' ) ) );
					iconBody.appendChild( allLabel );

					if ( ! lib.all_icons ) {
						renderIconSlugList( lib, iconBody );
					}
				}

				card.appendChild( iconBody );
			} else {
				const actions = document.createElement( 'div' );
				actions.className = 'wc-gpd-library-admin-card__actions';

				const addBtn = document.createElement( 'button' );
				addBtn.type = 'button';
				addBtn.className = 'button button-small';
				addBtn.textContent = type === 'photo'
					? ( config.i18n?.addPhotos || 'Add photos' )
					: ( config.i18n?.addImages || 'Add images' );
				addBtn.addEventListener( 'click', () => openMedia( lib ) );
				actions.appendChild( addBtn );
				card.appendChild( actions );

				const preview = document.createElement( 'ul' );
				preview.className = 'wc-gpd-graphic-library-preview';
				renderMediaPreview( lib, preview );
				card.appendChild( preview );
			}

			listEl.appendChild( card );
		} );
	}

	function openMedia( lib ) {
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		const frame = wp.media( {
			title: lib.type === 'photo'
				? ( config.i18n?.addPhotos || 'Add photos' )
				: ( config.i18n?.addImages || 'Add images' ),
			button: { text: 'Add to library' },
			multiple: true,
			library: { type: [ 'image' ] },
		} );
		frame.on( 'open', () => {
			const selection = frame.state().get( 'selection' );
			( lib.ids || [] ).forEach( ( id ) => {
				const attachment = wp.media.attachment( id );
				attachment.fetch();
				selection.add( attachment );
			} );
		} );
		frame.on( 'select', () => {
			lib.ids = frame.state().get( 'selection' ).map( ( att ) => att.id );
			persist();
		} );
		frame.open();
	}

	document.getElementById( 'wc-gpd-libraries-add' )?.addEventListener( 'click', () => {
		libraries.push( {
			id: 'lib_' + Date.now().toString( 36 ),
			name: 'Library ' + ( libraries.length + 1 ),
			type: 'graphic',
			ids: [],
			icon_slugs: [],
			all_icons: false,
		} );
		persist();
	} );

	load();
	render();
}( jQuery ) );
