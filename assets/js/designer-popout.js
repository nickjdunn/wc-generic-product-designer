/**
 * Full-screen studio mode for designer canvases (admin + storefront).
 */
( function () {
	'use strict';

	function getCloseButton( rootEl ) {
		return rootEl.querySelector( '#wc-gpd-popout-close' )
			|| rootEl.querySelector( '.wc-gpd-popout-chrome__close' )
			|| rootEl.querySelector( '.wc-gpd-studio-chrome__close' )
			|| rootEl.querySelector( '.wc-gpd-popout-close' );
	}

	function getChrome( rootEl ) {
		return rootEl.querySelector( '#wc-gpd-popout-chrome' )
			|| rootEl.querySelector( '.wc-gpd-popout-chrome' )
			|| rootEl.querySelector( '.wc-gpd-studio-chrome' );
	}

	function isStorefrontDesigner( rootEl ) {
		return rootEl && rootEl.id === 'wc-gpd-designer';
	}

	function closePopout( rootEl, onResize ) {
		if ( ! rootEl ) {
			return;
		}
		rootEl.classList.remove( 'wc-gpd-is-popout', 'wc-gpd-popout--modal', 'wc-gpd-popout--fullscreen' );
		rootEl.removeAttribute( 'aria-modal' );
		rootEl.removeAttribute( 'role' );

		if ( isStorefrontDesigner( rootEl ) ) {
			rootEl.hidden = true;
			rootEl.setAttribute( 'aria-hidden', 'true' );
		}

		const chrome = getChrome( rootEl );
		if ( chrome ) {
			chrome.hidden = true;
		}

		const closeBtn = getCloseButton( rootEl );
		if ( closeBtn && closeBtn.classList.contains( 'wc-gpd-popout-close' ) ) {
			closeBtn.hidden = true;
		}

		const backdrop = document.querySelector( '.wc-gpd-popout-backdrop' );
		if ( backdrop ) {
			backdrop.remove();
		}
		document.body.classList.remove( 'wc-gpd-popout-open' );
		document.body.classList.remove( 'wc-gpd-studio-open' );
		rootEl.dispatchEvent( new CustomEvent( 'wc-gpd-popout-closed' ) );
		if ( typeof onResize === 'function' ) {
			setTimeout( onResize, 60 );
		}
	}

	function bindClose( rootEl, closeBtn, onResize ) {
		if ( ! closeBtn || closeBtn.dataset.wcGpdPopoutBound ) {
			return;
		}
		closeBtn.dataset.wcGpdPopoutBound = '1';
		closeBtn.addEventListener( 'click', () => {
			closePopout( rootEl, onResize );
		} );
	}

	function openPopout( rootEl, onResize, options ) {
		if ( ! rootEl ) {
			return;
		}

		const opts = options || {};
		const fullscreen = opts.fullscreen !== false && isStorefrontDesigner( rootEl );

		if ( isStorefrontDesigner( rootEl ) ) {
			rootEl.hidden = false;
			rootEl.removeAttribute( 'aria-hidden' );
		}

		const chrome = getChrome( rootEl );
		if ( chrome ) {
			chrome.hidden = false;
			bindClose( rootEl, chrome.querySelector( '.wc-gpd-popout-chrome__close' ) || chrome.querySelector( '.wc-gpd-studio-chrome__close' ), onResize );
		}

		let closeBtn = getCloseButton( rootEl );
		if ( ! closeBtn ) {
			closeBtn = document.createElement( 'button' );
			closeBtn.type = 'button';
			closeBtn.className = 'button wc-gpd-popout-close';
			closeBtn.textContent = 'Close designer';
			closeBtn.hidden = true;
			rootEl.prepend( closeBtn );
		}
		if ( closeBtn.classList.contains( 'wc-gpd-popout-close' ) ) {
			closeBtn.hidden = false;
		}
		bindClose( rootEl, closeBtn, onResize );

		rootEl.classList.add( 'wc-gpd-is-popout' );

		if ( fullscreen ) {
			rootEl.classList.add( 'wc-gpd-popout--fullscreen' );
		} else if ( ! isStorefrontDesigner( rootEl ) && window.matchMedia( '(min-width: 768px)' ).matches ) {
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
			rootEl.classList.add( 'wc-gpd-popout--modal' );
		}

		rootEl.setAttribute( 'role', 'dialog' );
		rootEl.setAttribute( 'aria-modal', 'true' );
		document.body.classList.add( 'wc-gpd-popout-open' );
		if ( fullscreen ) {
			document.body.classList.add( 'wc-gpd-studio-open' );
		}

		if ( typeof onResize === 'function' ) {
			setTimeout( onResize, 60 );
		}
	}

	window.WcGpdPopout = {
		/**
		 * @param {HTMLElement} rootEl Container to expand.
		 * @param {Function}    onResize Called after open/close to rescale canvas.
		 * @param {Object}      options  { fullscreen?: boolean }
		 */
		open( rootEl, onResize, options ) {
			openPopout( rootEl, onResize, options );
		},

		close( rootEl, onResize ) {
			closePopout( rootEl, onResize );
		},

		/**
		 * @param {HTMLElement} rootEl Container to expand.
		 * @param {Function}    onResize Called after open/close to rescale canvas.
		 * @param {Object}      options  { fullscreen?: boolean }
		 */
		toggle( rootEl, onResize, options ) {
			if ( ! rootEl ) {
				return;
			}
			if ( rootEl.classList.contains( 'wc-gpd-is-popout' ) ) {
				closePopout( rootEl, onResize );
				return;
			}
			openPopout( rootEl, onResize, options );
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
