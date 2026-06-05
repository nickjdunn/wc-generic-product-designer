/**
 * WC Generic Product Designer — Fabric.js canvas controller.
 */
( function () {
	'use strict';

	const config = window.wcGpdDesigner;
	if ( ! config || typeof fabric === 'undefined' ) {
		return;
	}

	const log = window.wcGpdDebug || { setEnabled() {}, debug() {}, info() {}, warn() {}, error() {} };
	log.setEnabled( !! config.debug );
	log.info( 'Designer initializing', { width: config.canvasWidth, height: config.canvasHeight } );

	const canvasEl = document.getElementById( 'wc-gpd-canvas' );
	const designerRoot = document.getElementById( 'wc-gpd-designer' );
	let svgInput = document.getElementById( 'wc-gpd-design-svg' );

	/**
	 * Find the add-to-cart form (theme-safe).
	 *
	 * @returns {HTMLFormElement|null}
	 */
	function resolveCartForm() {
		if ( designerRoot ) {
			const closest = designerRoot.closest( 'form' );
			if ( closest ) {
				return closest;
			}
		}
		return document.querySelector( 'form.cart, form.variations_form, .cart form' );
	}

	const form = resolveCartForm();

	if ( ! canvasEl || ! designerRoot || ! svgInput || ! form ) {
		if ( log.error ) {
			log.error( 'Designer markup missing required elements', {
				canvas: !! canvasEl,
				root: !! designerRoot,
				svgInput: !! svgInput,
				form: !! form,
			} );
		}
		return;
	}

	/**
	 * Ensure hidden SVG field and nonce live inside the cart form.
	 */
	function ensureFieldsInForm() {
		if ( form.contains( svgInput ) ) {
			return;
		}
		form.appendChild( svgInput );
		log.debug( 'Moved SVG input into cart form' );

		const nonce = designerRoot.querySelector( 'input[name="wc_gpd_add_to_cart_nonce"]' );
		if ( nonce && ! form.contains( nonce ) ) {
			form.appendChild( nonce );
			log.debug( 'Moved nonce into cart form' );
		}

		const editKey = document.getElementById( 'wc-gpd-edit-cart-key' );
		if ( editKey && ! form.contains( editKey ) ) {
			form.appendChild( editKey );
			log.debug( 'Moved edit cart key into cart form' );
		}
	}

	ensureFieldsInForm();

	const PROD_WIDTH = config.canvasWidth;
	const PROD_HEIGHT = config.canvasHeight;

	const ui = {
		addText: document.getElementById( 'wc-gpd-add-text' ),
		controls: document.getElementById( 'wc-gpd-controls' ),
		fontFamily: document.getElementById( 'wc-gpd-font-family' ),
		fontSize: document.getElementById( 'wc-gpd-font-size' ),
		bold: document.getElementById( 'wc-gpd-bold' ),
		italic: document.getElementById( 'wc-gpd-italic' ),
		alignButtons: designerRoot.querySelectorAll( '.wc-gpd-align' ),
	};

	const canvas = new fabric.Canvas( 'wc-gpd-canvas', {
		selection: true,
		preserveObjectStacking: true,
		width: PROD_WIDTH,
		height: PROD_HEIGHT,
	} );

	let activeText = null;
	let displayScale = 1;
	let submitApproved = false;

	/**
	 * Populate font dropdown.
	 */
	function initFontSelect() {
		if ( ! ui.fontFamily ) {
			return;
		}
		config.fonts.forEach( ( font ) => {
			const option = document.createElement( 'option' );
			option.value = font;
			option.textContent = font.split( ',' )[ 0 ].replace( /"/g, '' ).trim();
			ui.fontFamily.appendChild( option );
		} );
	}

	/**
	 * Scale canvas element for responsive layout (production pixels unchanged).
	 */
	function applyResponsiveScale() {
		const wrap = designerRoot.querySelector( '.wc-gpd-designer__canvas-wrap' );
		if ( ! wrap ) {
			return;
		}

		const maxWidth = wrap.clientWidth;
		displayScale = Math.min( 1, maxWidth / PROD_WIDTH );
		const displayW = Math.floor( PROD_WIDTH * displayScale );
		const displayH = Math.floor( PROD_HEIGHT * displayScale );

		canvasEl.width = PROD_WIDTH;
		canvasEl.height = PROD_HEIGHT;
		canvasEl.style.width = `${ displayW }px`;
		canvasEl.style.height = `${ displayH }px`;

		const lower = canvas.lowerCanvasEl;
		const upper = canvas.upperCanvasEl;
		if ( lower ) {
			lower.style.width = `${ displayW }px`;
			lower.style.height = `${ displayH }px`;
		}
		if ( upper ) {
			upper.style.width = `${ displayW }px`;
			upper.style.height = `${ displayH }px`;
		}

		canvas.setZoom( displayScale );
		canvas.setDimensions( { width: PROD_WIDTH, height: PROD_HEIGHT } );
		canvas.calcOffset();
		canvas.requestRenderAll();
	}

	/**
	 * Load non-interactive background template.
	 */
	function loadBackground() {
		if ( ! config.templateUrl ) {
			canvas.backgroundColor = '#f4f4f4';
			canvas.requestRenderAll();
			log.debug( 'Using blank canvas background (no template image)' );
			return;
		}

		fabric.Image.fromURL(
			config.templateUrl,
			( img ) => {
				if ( ! img ) {
					log.warn( 'Template image failed to load; using blank canvas' );
					canvas.backgroundColor = '#f4f4f4';
					canvas.requestRenderAll();
					return;
				}
				const scaleX = PROD_WIDTH / img.width;
				const scaleY = PROD_HEIGHT / img.height;
				const scale = Math.max( scaleX, scaleY );

				img.set( {
					originX: 'center',
					originY: 'center',
					left: PROD_WIDTH / 2,
					top: PROD_HEIGHT / 2,
					scaleX: scale,
					scaleY: scale,
					selectable: false,
					evented: false,
					hasControls: false,
					hasBorders: false,
					lockMovementX: true,
					lockMovementY: true,
					wcGpdBackground: true,
				} );

				canvas.backgroundImage = img;
				canvas.requestRenderAll();
			},
			{ crossOrigin: 'anonymous' }
		);
	}

	/**
	 * Keep object bounding box inside canvas.
	 *
	 * @param {fabric.Object} obj Fabric object.
	 */
	function constrainToCanvas( obj ) {
		if ( ! obj || obj.wcGpdBackground ) {
			return;
		}

		obj.setCoords();
		const rect = obj.getBoundingRect( true );
		const pad = 2;
		let deltaX = 0;
		let deltaY = 0;

		if ( rect.left < pad ) {
			deltaX = pad - rect.left;
		} else if ( rect.left + rect.width > PROD_WIDTH - pad ) {
			deltaX = PROD_WIDTH - pad - ( rect.left + rect.width );
		}

		if ( rect.top < pad ) {
			deltaY = pad - rect.top;
		} else if ( rect.top + rect.height > PROD_HEIGHT - pad ) {
			deltaY = PROD_HEIGHT - pad - ( rect.top + rect.height );
		}

		if ( deltaX !== 0 || deltaY !== 0 ) {
			obj.left += deltaX;
			obj.top += deltaY;
			obj.setCoords();
		}
	}

	/**
	 * Minimum scale so object cannot shrink off-canvas entirely.
	 *
	 * @param {fabric.Object} obj Fabric object.
	 */
	function constrainScale( obj ) {
		if ( ! obj || obj.wcGpdBackground ) {
			return;
		}

		obj.setCoords();
		const rect = obj.getBoundingRect( true );
		const maxW = PROD_WIDTH - 4;
		const maxH = PROD_HEIGHT - 4;

		if ( rect.width > maxW && obj.scaleX ) {
			const factor = maxW / rect.width;
			obj.scaleX *= factor;
			if ( obj.scaleY ) {
				obj.scaleY *= factor;
			}
		}
		if ( rect.height > maxH && obj.scaleY ) {
			const factor = maxH / rect.height;
			obj.scaleY *= factor;
			if ( obj.scaleX ) {
				obj.scaleX *= factor;
			}
		}

		obj.setCoords();
		constrainToCanvas( obj );
	}

	/**
	 * @param {fabric.Object} obj Fabric object.
	 * @returns {boolean}
	 */
	function isTextLayer( obj ) {
		if ( ! obj || obj.wcGpdBackground ) {
			return false;
		}
		const type = obj.type || '';
		return !! obj.wcGpdTextLayer || type === 'i-text' || type === 'text' || type === 'textbox';
	}

	/**
	 * @returns {boolean}
	 */
	function hasTextLayer() {
		return canvas.getObjects().some( ( o ) => isTextLayer( o ) );
	}

	/**
	 * Sync toolbar from active text object.
	 *
	 * @param {fabric.IText|null} obj Active text.
	 */
	function syncToolbar( obj ) {
		activeText = obj;
		const enabled = !! obj;

		if ( ui.controls ) {
			ui.controls.disabled = ! enabled;
		}

		if ( ! enabled ) {
			return;
		}

		if ( ui.fontFamily ) {
			ui.fontFamily.value = obj.fontFamily || config.fonts[ 0 ];
		}
		if ( ui.fontSize ) {
			ui.fontSize.value = String( Math.round( obj.fontSize || 32 ) );
		}
		if ( ui.bold ) {
			ui.bold.checked = obj.fontWeight === 'bold';
		}
		if ( ui.italic ) {
			ui.italic.checked = obj.fontStyle === 'italic';
		}

		const align = obj.textAlign || 'left';
		ui.alignButtons.forEach( ( btn ) => {
			const isActive = btn.dataset.align === align;
			btn.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
			btn.classList.toggle( 'is-active', isActive );
		} );
	}

	/**
	 * Add a new editable text layer.
	 */
	function addTextLayer() {
		const text = new fabric.IText( 'Your text', {
			left: PROD_WIDTH / 2,
			top: PROD_HEIGHT / 2,
			originX: 'center',
			originY: 'center',
			fontFamily: config.fonts[ 0 ],
			fontSize: 32,
			fill: '#000000',
			wcGpdTextLayer: true,
		} );

		canvas.add( text );
		canvas.setActiveObject( text );
		canvas.requestRenderAll();
		syncToolbar( text );
		log.debug( 'Text layer added' );
	}

	/**
	 * Production SVG at full canvas dimensions (text vectors only, no template background).
	 *
	 * @returns {string|null}
	 */
	function exportProductionSvg() {
		if ( ! hasTextLayer() ) {
			return null;
		}

		const prevBg = canvas.backgroundImage;
		const prevZoom = canvas.getZoom();
		const prevVpt = canvas.viewportTransform.slice();

		canvas.backgroundImage = null;
		canvas.setZoom( 1 );
		canvas.viewportTransform = [ 1, 0, 0, 1, 0, 0 ];

		canvas.getObjects().forEach( ( o ) => {
			o._wcGpdWasVisible = o.visible;
			o.visible = isTextLayer( o );
		} );

		canvas.renderAll();

		let svg = canvas.toSVG( {
			viewBox: {
				x: 0,
				y: 0,
				width: PROD_WIDTH,
				height: PROD_HEIGHT,
			},
			width: PROD_WIDTH,
			height: PROD_HEIGHT,
		} );

		canvas.getObjects().forEach( ( o ) => {
			if ( typeof o._wcGpdWasVisible !== 'undefined' ) {
				o.visible = o._wcGpdWasVisible;
				delete o._wcGpdWasVisible;
			}
		} );

		canvas.backgroundImage = prevBg;
		canvas.setZoom( prevZoom );
		canvas.viewportTransform = prevVpt;
		canvas.requestRenderAll();

		if ( svg && ! /width\s*=/.test( svg ) ) {
			svg = svg.replace( '<svg ', `<svg width="${ PROD_WIDTH }" height="${ PROD_HEIGHT }" ` );
		}

		log.info( 'Production SVG exported', { bytes: svg ? svg.length : 0 } );
		return svg;
	}

	/**
	 * Export SVG and write to hidden input.
	 *
	 * @returns {boolean}
	 */
	function prepareDesignForCart() {
		if ( ! hasTextLayer() ) {
			log.warn( 'Add to cart blocked: no text layers' );
			window.alert( config.i18n.layerRequired );
			return false;
		}

		const svg = exportProductionSvg();
		if ( ! svg ) {
			log.error( 'SVG export failed' );
			window.alert( config.i18n.exportError );
			return false;
		}

		ensureFieldsInForm();
		svgInput.value = svg;
		return true;
	}

	/**
	 * Restore text layers from saved SVG (cart edit mode).
	 *
	 * @param {string} svgString Saved design SVG.
	 * @returns {Promise<void>}
	 */
	function loadDesignFromSvg( svgString ) {
		return new Promise( ( resolve, reject ) => {
			fabric.loadSVGFromString( svgString, ( objects ) => {
				if ( ! objects || ! objects.length ) {
					reject( new Error( 'empty' ) );
					return;
				}

				canvas.getObjects().slice().forEach( ( obj ) => {
					if ( isTextLayer( obj ) ) {
						canvas.remove( obj );
					}
				} );

				objects.forEach( ( obj ) => {
					obj.wcGpdTextLayer = true;
					canvas.add( obj );
				} );

				canvas.requestRenderAll();
				const first = objects[ 0 ];
				if ( first ) {
					canvas.setActiveObject( first );
					syncToolbar( first );
				}

				log.info( 'Loaded design from cart', { layers: objects.length } );
				resolve();
			} );
		} );
	}

	/**
	 * @returns {HTMLElement|null}
	 */
	function getAddToCartButton() {
		return form.querySelector(
			'button.single_add_to_cart_button, button[name="add-to-cart"], input[name="add-to-cart"], .single_add_to_cart_button'
		);
	}

	/**
	 * POST the cart form after SVG is ready.
	 */
	function submitCartForm() {
		submitApproved = true;
		const btn = getAddToCartButton();

		if ( btn ) {
			btn.classList.add( 'wc-gpd-submitting' );
			btn.setAttribute( 'aria-busy', 'true' );
		}

		log.info( 'Submitting add to cart with design SVG' );

		if ( typeof form.requestSubmit === 'function' ) {
			form.requestSubmit( btn || undefined );
			return;
		}

		// Fallback: temporary submit control (fires submit event unlike form.submit()).
		const fallback = document.createElement( 'input' );
		fallback.type = 'submit';
		fallback.hidden = true;
		fallback.setAttribute( 'aria-hidden', 'true' );
		form.appendChild( fallback );
		fallback.click();
		fallback.remove();
	}

	/**
	 * Intercept add to cart (themes often use click/AJAX instead of form submit).
	 */
	function bindAddToCart() {
		const onIntent = ( event ) => {
			if ( submitApproved ) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();
			if ( typeof event.stopImmediatePropagation === 'function' ) {
				event.stopImmediatePropagation();
			}

			if ( ! prepareDesignForCart() ) {
				return;
			}

			submitCartForm();
		};

		form.addEventListener( 'submit', ( event ) => {
			if ( submitApproved ) {
				return;
			}
			onIntent( event );
		} );

		// Capture phase runs before theme/WooCommerce jQuery handlers.
		document.addEventListener(
			'click',
			( event ) => {
				if ( submitApproved ) {
					return;
				}
				const btn = event.target.closest(
					'button.single_add_to_cart_button, button[name="add-to-cart"], input[name="add-to-cart"]'
				);
				if ( ! btn || ! form.contains( btn ) ) {
					return;
				}
				onIntent( event );
			},
			true
		);
	}

	// Events
	canvas.on( 'selection:created', ( e ) => {
		const target = e.selected?.[ 0 ] || e.target;
		if ( target?.wcGpdTextLayer ) {
			syncToolbar( target );
		}
	} );

	canvas.on( 'selection:updated', ( e ) => {
		const target = e.selected?.[ 0 ] || e.target;
		if ( target?.wcGpdTextLayer ) {
			syncToolbar( target );
		}
	} );

	canvas.on( 'selection:cleared', () => syncToolbar( null ) );

	canvas.on( 'object:moving', ( e ) => constrainToCanvas( e.target ) );
	canvas.on( 'object:scaling', ( e ) => {
		constrainScale( e.target );
		constrainToCanvas( e.target );
	} );
	canvas.on( 'object:modified', ( e ) => constrainToCanvas( e.target ) );

	if ( ui.addText ) {
		ui.addText.addEventListener( 'click', addTextLayer );
	}

	if ( ui.fontFamily ) {
		ui.fontFamily.addEventListener( 'change', () => {
			if ( ! activeText ) {
				return;
			}
			activeText.set( 'fontFamily', ui.fontFamily.value );
			canvas.requestRenderAll();
		} );
	}

	if ( ui.fontSize ) {
		ui.fontSize.addEventListener( 'input', () => {
			if ( ! activeText ) {
				return;
			}
			const size = Math.min( 400, Math.max( 8, parseInt( ui.fontSize.value, 10 ) || 32 ) );
			activeText.set( 'fontSize', size );
			canvas.requestRenderAll();
			constrainToCanvas( activeText );
		} );
	}

	if ( ui.bold ) {
		ui.bold.addEventListener( 'change', () => {
			if ( ! activeText ) {
				return;
			}
			activeText.set( 'fontWeight', ui.bold.checked ? 'bold' : 'normal' );
			canvas.requestRenderAll();
		} );
	}

	if ( ui.italic ) {
		ui.italic.addEventListener( 'change', () => {
			if ( ! activeText ) {
				return;
			}
			activeText.set( 'fontStyle', ui.italic.checked ? 'italic' : 'normal' );
			canvas.requestRenderAll();
		} );
	}

	ui.alignButtons.forEach( ( btn ) => {
		btn.addEventListener( 'click', () => {
			if ( ! activeText ) {
				return;
			}
			const align = btn.dataset.align;
			activeText.set( 'textAlign', align );
			ui.alignButtons.forEach( ( b ) => {
				const on = b === btn;
				b.classList.toggle( 'is-active', on );
				b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
			canvas.requestRenderAll();
		} );
	} );

	let resizeTimer;
	window.addEventListener( 'resize', () => {
		clearTimeout( resizeTimer );
		resizeTimer = setTimeout( applyResponsiveScale, 150 );
	} );

	initFontSelect();
	loadBackground();
	applyResponsiveScale();
	bindAddToCart();

	if ( config.isEditing && config.i18n.updateCart ) {
		const addBtn = getAddToCartButton();
		if ( addBtn ) {
			addBtn.textContent = config.i18n.updateCart;
		}
	}

	if ( config.existingDesignSvg ) {
		loadDesignFromSvg( config.existingDesignSvg ).catch( () => {
			log.warn( 'Failed to load cart design; starting fresh' );
			window.alert( config.i18n.loadDesignError );
			addTextLayer();
		} );
	} else {
		addTextLayer();
	}

	log.info( 'Designer ready' );
} )();
