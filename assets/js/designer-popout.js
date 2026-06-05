/**
 * Fullscreen pop-out mode for designer canvases (admin + storefront).
 */
( function () {
	'use strict';

	function closePopout( rootEl, onResize ) {
		if ( ! rootEl ) {
			return;
		}
		rootEl.classList.remove( 'wc-gpd-is-popout' );
		const closeBtn = rootEl.querySelector( '.wc-gpd-popout-close' );
		if ( closeBtn ) {
			closeBtn.hidden = true;
		}
		const backdrop = document.querySelector( '.wc-gpd-popout-backdrop' );
		if ( backdrop ) {
			backdrop.remove();
		}
		document.body.classList.remove( 'wc-gpd-popout-open' );
		rootEl.dispatchEvent( new CustomEvent( 'wc-gpd-popout-closed' ) );
		if ( typeof onResize === 'function' ) {
			setTimeout( onResize, 60 );
		}
	}

	window.WcGpdPopout = {
		/**
		 * @param {HTMLElement} rootEl Container to expand.
		 * @param {Function}    onResize Called after open/close to rescale canvas.
		 */
		toggle( rootEl, onResize ) {
			if ( ! rootEl ) {
				return;
			}

			const isOpen = rootEl.classList.contains( 'wc-gpd-is-popout' );

			if ( isOpen ) {
				closePopout( rootEl, onResize );
				return;
			}

			let backdrop = document.querySelector( '.wc-gpd-popout-backdrop' );
			if ( ! backdrop ) {
				backdrop = document.createElement( 'div' );
				backdrop.className = 'wc-gpd-popout-backdrop';
				backdrop.addEventListener( 'click', () => {
					if ( rootEl.classList.contains( 'wc-gpd-is-popout' ) ) {
						closePopout( rootEl, onResize );
					}
				} );
				document.body.appendChild( backdrop );
			}

			let closeBtn = rootEl.querySelector( '.wc-gpd-popout-close' );
			if ( ! closeBtn ) {
				closeBtn = document.createElement( 'button' );
				closeBtn.type = 'button';
				closeBtn.className = 'button wc-gpd-popout-close';
				closeBtn.textContent = 'Close expanded designer';
				closeBtn.hidden = true;
				closeBtn.addEventListener( 'click', () => {
					closePopout( rootEl, onResize );
				} );
				rootEl.prepend( closeBtn );
			}
			closeBtn.hidden = false;

			rootEl.classList.add( 'wc-gpd-is-popout' );
			document.body.classList.add( 'wc-gpd-popout-open' );

			if ( typeof onResize === 'function' ) {
				setTimeout( onResize, 60 );
			}
		},
	};

	document.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' !== event.key ) {
			return;
		}
		const openEl = document.querySelector( '.wc-gpd-is-popout' );
		if ( openEl ) {
			closePopout( openEl );
		}
	} );
}() );
