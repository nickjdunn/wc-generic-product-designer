/**
 * Pop-out mode for designer canvases (admin + storefront).
 */
( function () {
	'use strict';

	function getCloseButton( rootEl ) {
		return rootEl.querySelector( '#wc-gpd-popout-close' )
			|| rootEl.querySelector( '.wc-gpd-popout-chrome__close' )
			|| rootEl.querySelector( '.wc-gpd-popout-close' );
	}

	function getChrome( rootEl ) {
		return rootEl.querySelector( '#wc-gpd-popout-chrome' )
			|| rootEl.querySelector( '.wc-gpd-popout-chrome' );
	}

	function isStorefrontDesigner( rootEl ) {
		return rootEl && rootEl.id === 'wc-gpd-designer';
	}

	function closePopout( rootEl, onResize ) {
		if ( ! rootEl ) {
			return;
		}
		rootEl.classList.remove( 'wc-gpd-is-popout', 'wc-gpd-popout--modal' );
		rootEl.removeAttribute( 'aria-modal' );
		rootEl.removeAttribute( 'role' );

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

			const chrome = getChrome( rootEl );
			if ( chrome ) {
				chrome.hidden = false;
				bindClose( rootEl, chrome.querySelector( '.wc-gpd-popout-chrome__close' ), onResize );
			}

			let closeBtn = getCloseButton( rootEl );
			if ( ! closeBtn ) {
				closeBtn = document.createElement( 'button' );
				closeBtn.type = 'button';
				closeBtn.className = 'button wc-gpd-popout-close';
				closeBtn.textContent = 'Close expanded designer';
				closeBtn.hidden = true;
				rootEl.prepend( closeBtn );
			}
			if ( closeBtn.classList.contains( 'wc-gpd-popout-close' ) ) {
				closeBtn.hidden = false;
			}
			bindClose( rootEl, closeBtn, onResize );

			rootEl.classList.add( 'wc-gpd-is-popout' );
			if ( isStorefrontDesigner( rootEl ) && window.matchMedia( '(min-width: 768px)' ).matches ) {
				rootEl.classList.add( 'wc-gpd-popout--modal' );
			}
			rootEl.setAttribute( 'role', 'dialog' );
			rootEl.setAttribute( 'aria-modal', 'true' );
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
