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

	// —— Site color palettes ——
	const colorHidden = document.getElementById( 'wc_gpd_site_color_palettes_json' );
	const colorListEl = document.getElementById( 'wc-gpd-site-color-palettes-list' );
	let colorPalettesDoc = { palettes: [] };

	function loadColorPalettes() {
		if ( config.colorPalettes && typeof config.colorPalettes === 'object' ) {
			colorPalettesDoc = {
				palettes: Array.isArray( config.colorPalettes.palettes ) ? config.colorPalettes.palettes : [],
			};
		}
		if ( colorHidden && colorHidden.value ) {
			try {
				const parsed = JSON.parse( colorHidden.value );
				if ( parsed && Array.isArray( parsed.palettes ) ) {
					colorPalettesDoc.palettes = parsed.palettes;
				}
			} catch ( e ) {
				// Keep loaded value.
			}
		}
		if ( ! colorPalettesDoc.palettes.length ) {
			colorPalettesDoc.palettes = [ { id: 'pal_default', name: 'Default', colors: [ '#000000' ] } ];
		}
	}

	function syncColorPalettesHidden() {
		if ( colorHidden ) {
			colorHidden.value = JSON.stringify( colorPalettesDoc );
		}
	}

	function renderColorPalettes() {
		if ( ! colorListEl ) {
			return;
		}
		colorListEl.innerHTML = '';
		if ( ! colorPalettesDoc.palettes.length ) {
			colorListEl.innerHTML = '<p class="description">' + ( config.i18n?.emptyColorPalettes || 'No color palettes yet.' ) + '</p>';
			return;
		}
		colorPalettesDoc.palettes.forEach( ( palette, paletteIndex ) => {
			const card = document.createElement( 'div' );
			card.className = 'wc-gpd-palette-card';
			card.dataset.paletteId = palette.id;

			const header = document.createElement( 'div' );
			header.className = 'wc-gpd-palette-card__header';
			const nameInput = document.createElement( 'input' );
			nameInput.type = 'text';
			nameInput.className = 'wc-gpd-palette-name regular-text';
			nameInput.value = palette.name || palette.id;
			nameInput.addEventListener( 'input', () => {
				palette.name = nameInput.value;
				syncColorPalettesHidden();
			} );
			const removeBtn = document.createElement( 'button' );
			removeBtn.type = 'button';
			removeBtn.className = 'button button-link-delete';
			removeBtn.textContent = config.i18n?.removeLibrary || 'Remove';
			removeBtn.disabled = colorPalettesDoc.palettes.length <= 1;
			removeBtn.addEventListener( 'click', () => {
				if ( colorPalettesDoc.palettes.length <= 1 ) {
					return;
				}
				colorPalettesDoc.palettes.splice( paletteIndex, 1 );
				syncColorPalettesHidden();
				renderColorPalettes();
			} );
			header.appendChild( nameInput );
			header.appendChild( removeBtn );

			const swatches = document.createElement( 'div' );
			swatches.className = 'wc-gpd-palette-swatches';
			( palette.colors || [] ).forEach( ( color, colorIndex ) => {
				const row = document.createElement( 'label' );
				row.className = 'wc-gpd-palette-color-row';
				const picker = document.createElement( 'input' );
				picker.type = 'color';
				picker.value = color;
				picker.addEventListener( 'input', () => {
					palette.colors[ colorIndex ] = picker.value;
					syncColorPalettesHidden();
				} );
				const rm = document.createElement( 'button' );
				rm.type = 'button';
				rm.className = 'button-link-delete wc-gpd-palette-remove-color';
				rm.textContent = '×';
				rm.disabled = ( palette.colors || [] ).length <= 1;
				rm.addEventListener( 'click', () => {
					if ( ( palette.colors || [] ).length <= 1 ) {
						return;
					}
					palette.colors.splice( colorIndex, 1 );
					syncColorPalettesHidden();
					renderColorPalettes();
				} );
				row.appendChild( picker );
				row.appendChild( rm );
				swatches.appendChild( row );
			} );

			const addColorBtn = document.createElement( 'button' );
			addColorBtn.type = 'button';
			addColorBtn.className = 'button button-small';
			addColorBtn.textContent = config.i18n?.addColor || 'Add color';
			addColorBtn.addEventListener( 'click', () => {
				palette.colors = palette.colors || [];
				palette.colors.push( '#000000' );
				syncColorPalettesHidden();
				renderColorPalettes();
			} );

			card.appendChild( header );
			card.appendChild( swatches );
			card.appendChild( addColorBtn );
			colorListEl.appendChild( card );
		} );
	}

	document.getElementById( 'wc-gpd-add-site-color-palette' )?.addEventListener( 'click', () => {
		colorPalettesDoc.palettes.push( {
			id: 'pal_' + Date.now().toString( 36 ),
			name: 'Palette ' + ( colorPalettesDoc.palettes.length + 1 ),
			colors: [ '#000000' ],
		} );
		syncColorPalettesHidden();
		renderColorPalettes();
	} );

	// —— Site font libraries ——
	const fontHidden = document.getElementById( 'wc_gpd_site_font_libraries_json' );
	const fontListEl = document.getElementById( 'wc-gpd-font-libraries-list' );
	const fontOptions = Array.isArray( config.fontOptions ) ? config.fontOptions : [];
	let fontLibrariesDoc = { libraries: [] };

	function loadFontLibraries() {
		if ( config.fontLibraries && typeof config.fontLibraries === 'object' ) {
			const rows = config.fontLibraries.libraries || config.fontLibraries.palettes || [];
			fontLibrariesDoc.libraries = Array.isArray( rows ) ? rows : [];
		}
		if ( fontHidden && fontHidden.value ) {
			try {
				const parsed = JSON.parse( fontHidden.value );
				const rows = parsed.libraries || parsed.palettes || [];
				if ( Array.isArray( rows ) ) {
					fontLibrariesDoc.libraries = rows;
				}
			} catch ( e ) {
				// Keep loaded value.
			}
		}
		if ( ! fontLibrariesDoc.libraries.length ) {
			const defaultFonts = fontOptions.map( ( font ) => font.key ).filter( Boolean ).slice( 0, 8 );
			fontLibrariesDoc.libraries = [ { id: 'fp_default', name: 'Default', fonts: defaultFonts } ];
		}
	}

	function syncFontLibrariesHidden() {
		if ( fontHidden ) {
			fontHidden.value = JSON.stringify( { libraries: fontLibrariesDoc.libraries } );
		}
	}

	function renderFontLibraries() {
		if ( ! fontListEl ) {
			return;
		}
		fontListEl.innerHTML = '';
		if ( ! fontLibrariesDoc.libraries.length ) {
			fontListEl.innerHTML = '<p class="description">' + ( config.i18n?.emptyFontLibs || 'No font libraries yet.' ) + '</p>';
			return;
		}
		fontLibrariesDoc.libraries.forEach( ( library, libraryIndex ) => {
			const card = document.createElement( 'div' );
			card.className = 'wc-gpd-palette-card wc-gpd-font-palette-card';
			card.dataset.libraryId = library.id;

			const header = document.createElement( 'div' );
			header.className = 'wc-gpd-palette-card__header';
			const nameInput = document.createElement( 'input' );
			nameInput.type = 'text';
			nameInput.className = 'wc-gpd-palette-name regular-text';
			nameInput.value = library.name || library.id;
			nameInput.addEventListener( 'input', () => {
				library.name = nameInput.value;
				syncFontLibrariesHidden();
			} );
			const removeBtn = document.createElement( 'button' );
			removeBtn.type = 'button';
			removeBtn.className = 'button button-link-delete';
			removeBtn.textContent = config.i18n?.removeLibrary || 'Remove';
			removeBtn.disabled = fontLibrariesDoc.libraries.length <= 1;
			removeBtn.addEventListener( 'click', () => {
				if ( fontLibrariesDoc.libraries.length <= 1 ) {
					return;
				}
				fontLibrariesDoc.libraries.splice( libraryIndex, 1 );
				syncFontLibrariesHidden();
				renderFontLibraries();
			} );
			header.appendChild( nameInput );
			header.appendChild( removeBtn );

			const picks = document.createElement( 'div' );
			picks.className = 'wc-gpd-font-palette-picks';
			const selected = new Set( library.fonts || [] );
			fontOptions.forEach( ( font ) => {
				const label = document.createElement( 'label' );
				label.className = 'wc-gpd-font-palette-pick';
				label.style.fontFamily = font.css || font.family;
				const input = document.createElement( 'input' );
				input.type = 'checkbox';
				input.value = font.key;
				input.checked = selected.has( font.key );
				input.addEventListener( 'change', () => {
					library.fonts = library.fonts || [];
					if ( input.checked ) {
						if ( ! library.fonts.includes( font.key ) ) {
							library.fonts.push( font.key );
						}
					} else {
						library.fonts = library.fonts.filter( ( key ) => key !== font.key );
					}
					if ( ! library.fonts.length ) {
						library.fonts = [ font.key ];
						input.checked = true;
					}
					syncFontLibrariesHidden();
				} );
				label.appendChild( input );
				label.appendChild( document.createTextNode( ' ' + ( font.label || font.key ) ) );
				picks.appendChild( label );
			} );

			card.appendChild( header );
			card.appendChild( picks );
			fontListEl.appendChild( card );
		} );
	}

	document.getElementById( 'wc-gpd-add-font-library' )?.addEventListener( 'click', () => {
		const defaultFonts = fontOptions.map( ( font ) => font.key ).filter( Boolean ).slice( 0, 8 );
		fontLibrariesDoc.libraries.push( {
			id: 'fp_' + Date.now().toString( 36 ),
			name: 'Font library ' + ( fontLibrariesDoc.libraries.length + 1 ),
			fonts: defaultFonts,
		} );
		syncFontLibrariesHidden();
		renderFontLibraries();
	} );

	function initLibrariesAccordion() {
		const accordion = document.getElementById( 'wc-gpd-libraries-accordion' );
		if ( ! accordion ) {
			return;
		}
		accordion.querySelectorAll( '.wc-gpd-accordion-toggle' ).forEach( ( toggle ) => {
			toggle.addEventListener( 'click', () => {
				const section = toggle.closest( '.wc-gpd-accordion-section' );
				if ( ! section ) {
					return;
				}
				const body = section.querySelector( '.wc-gpd-accordion-body' );
				const sectionName = section.dataset.libSection || '';
				if ( section.classList.contains( 'is-open' ) ) {
					section.classList.remove( 'is-open' );
					toggle.setAttribute( 'aria-expanded', 'false' );
					if ( body ) {
						body.hidden = true;
					}
					return;
				}
				accordion.querySelectorAll( '.wc-gpd-accordion-section' ).forEach( ( row ) => {
					const isTarget = row.dataset.libSection === sectionName;
					const rowToggle = row.querySelector( '.wc-gpd-accordion-toggle' );
					const rowBody = row.querySelector( '.wc-gpd-accordion-body' );
					if ( ! isTarget ) {
						row.classList.remove( 'is-open' );
						if ( rowToggle ) {
							rowToggle.setAttribute( 'aria-expanded', 'false' );
						}
						if ( rowBody ) {
							rowBody.hidden = true;
						}
					}
				} );
				section.classList.add( 'is-open' );
				toggle.setAttribute( 'aria-expanded', 'true' );
				if ( body ) {
					body.hidden = false;
				}
			} );
		} );
	}

	const librariesForm = document.getElementById( 'wc-gpd-libraries-form' );
	if ( librariesForm ) {
		librariesForm.addEventListener( 'submit', () => {
			syncHidden();
			syncColorPalettesHidden();
			syncFontLibrariesHidden();
		} );
	}

	load();
	render();
	loadColorPalettes();
	renderColorPalettes();
	loadFontLibraries();
	renderFontLibraries();
	initLibrariesAccordion();
}( jQuery ) );
