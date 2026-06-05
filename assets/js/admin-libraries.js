/**
 * Site-wide graphic libraries admin.
 */
( function ( $ ) {
	'use strict';

	const config = window.wcGpdLibrariesAdmin || {};
	const hidden = document.getElementById( 'wc_gpd_libraries_json' );
	const listEl = document.getElementById( 'wc-gpd-libraries-admin-list' );
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

	function persist() {
		if ( hidden ) {
			hidden.value = JSON.stringify( libraries );
		}
		render();
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
			const card = document.createElement( 'div' );
			card.className = 'wc-gpd-library-admin-card';

			const header = document.createElement( 'div' );
			header.className = 'wc-gpd-library-admin-card__header';

			const nameInput = document.createElement( 'input' );
			nameInput.type = 'text';
			nameInput.className = 'regular-text wc-gpd-library-name-input';
			nameInput.value = lib.name || '';
			nameInput.placeholder = config.i18n?.libraryName || 'Library name';
			nameInput.addEventListener( 'input', () => {
				lib.name = nameInput.value;
				persist();
			} );

			const addBtn = document.createElement( 'button' );
			addBtn.type = 'button';
			addBtn.className = 'button button-small';
			addBtn.textContent = config.i18n?.addImages || 'Add images';
			addBtn.addEventListener( 'click', () => openMedia( lib ) );

			const removeBtn = document.createElement( 'button' );
			removeBtn.type = 'button';
			removeBtn.className = 'button button-link-delete';
			removeBtn.textContent = config.i18n?.removeLibrary || 'Remove';
			removeBtn.addEventListener( 'click', () => {
				libraries.splice( index, 1 );
				persist();
			} );

			header.appendChild( nameInput );
			header.appendChild( addBtn );
			header.appendChild( removeBtn );

			const preview = document.createElement( 'ul' );
			preview.className = 'wc-gpd-graphic-library-preview';
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
						persist();
					} );
					li.appendChild( img );
					li.appendChild( rm );
					preview.appendChild( li );
				} );
			} );

			card.appendChild( header );
			card.appendChild( preview );
			listEl.appendChild( card );
		} );
	}

	function openMedia( lib ) {
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		const frame = wp.media( {
			title: config.i18n?.addImages || 'Add images',
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
			ids: [],
		} );
		persist();
	} );

	load();
	render();
}( jQuery ) );
