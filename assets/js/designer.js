/**
 * WC Generic Product Designer — Fabric.js canvas controller.
 */
( function () {
	'use strict';

	const config = window.wcGpdDesigner;
	if ( ! config || typeof fabric === 'undefined' ) {
		return;
	}

	const productSettings = config.productSettings || {};

	const log = window.wcGpdDebug || { setEnabled() {}, debug() {}, info() {}, warn() {}, error() {} };
	log.setEnabled( !! config.debug );
	log.info( 'Designer initializing', { width: config.canvasWidth, height: config.canvasHeight } );

	const canvasEl = document.getElementById( 'wc-gpd-canvas' );
	const designerRoot = document.getElementById( 'wc-gpd-designer' );
	let svgInput = document.getElementById( 'wc-gpd-design-svg' );
	let jsonInput = document.getElementById( 'wc-gpd-design-json' );
	let previewInput = document.getElementById( 'wc-gpd-preview-image' );

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

		if ( jsonInput && ! form.contains( jsonInput ) ) {
			form.appendChild( jsonInput );
			log.debug( 'Moved design JSON into cart form' );
		}

		if ( previewInput && ! form.contains( previewInput ) ) {
			form.appendChild( previewInput );
			log.debug( 'Moved preview image into cart form' );
		}
	}

	ensureFieldsInForm();

	const PROD_WIDTH = config.canvasWidth;
	const PROD_HEIGHT = config.canvasHeight;

	const ui = {
		addText: document.getElementById( 'wc-gpd-add-text' ),
		dock: document.getElementById( 'wc-gpd-dock' ),
		fontFamily: document.getElementById( 'wc-gpd-font-family' ),
		fontSize: document.getElementById( 'wc-gpd-font-size' ),
		bold: document.getElementById( 'wc-gpd-bold' ),
		italic: document.getElementById( 'wc-gpd-italic' ),
		boldBtn: document.getElementById( 'wc-gpd-bold-btn' ),
		italicBtn: document.getElementById( 'wc-gpd-italic-btn' ),
		underlineBtn: document.getElementById( 'wc-gpd-underline-btn' ),
		textColor: document.getElementById( 'wc-gpd-text-color' ),
		underline: document.getElementById( 'wc-gpd-underline' ),
		lineHeight: document.getElementById( 'wc-gpd-line-height' ),
		letterSpacing: document.getElementById( 'wc-gpd-letter-spacing' ),
		alignRow: designerRoot.querySelector( '.wc-gpd-control-align' ),
		alignButtons: designerRoot.querySelectorAll( '.wc-gpd-align' ),
		layersList: document.getElementById( 'wc-gpd-layers-list' ),
		layerForward: document.getElementById( 'wc-gpd-layer-forward' ),
		layerBackward: document.getElementById( 'wc-gpd-layer-backward' ),
		layerDelete: document.getElementById( 'wc-gpd-layer-delete' ),
		popoutBtn: document.getElementById( 'wc-gpd-popout-btn' ),
		viewPhotosBtn: document.getElementById( 'wc-gpd-view-photos-btn' ),
		galleryModal: document.getElementById( 'wc-gpd-gallery-modal' ),
		galleryScroll: document.getElementById( 'wc-gpd-gallery-scroll' ),
		galleryClose: document.getElementById( 'wc-gpd-gallery-close' ),
		galleryBackdrop: document.getElementById( 'wc-gpd-gallery-backdrop' ),
	};

	function defaultTextColor() {
		return productSettings.forced_text_color || '#000000';
	}

	function enforceSingleColor( obj ) {
		if ( ! obj || ! productSettings.single_color_only ) {
			return;
		}
		obj.set( 'fill', defaultTextColor() );
	}

	function textColorAllowed() {
		return productSettings.allow_text_color !== false && ! productSettings.single_color_only;
	}

	/**
	 * Show/hide customer tools based on per-product settings.
	 */
	function setToolVisible( el, visible ) {
		if ( el ) {
			el.hidden = ! visible;
		}
	}

	function applyProductToolSettings() {
		const showColor = textColorAllowed();
		setToolVisible( ui.textColor, showColor );
		setToolVisible( ui.fontFamily, productSettings.allow_font_family !== false );
		setToolVisible( ui.fontSize, productSettings.allow_font_size !== false );
		setToolVisible( ui.boldBtn, productSettings.allow_bold !== false );
		setToolVisible( ui.italicBtn, productSettings.allow_italic !== false );
		setToolVisible( ui.underlineBtn, productSettings.allow_underline !== false );
		setToolVisible( ui.lineHeight, productSettings.allow_line_height !== false );
		setToolVisible( ui.letterSpacing, productSettings.allow_letter_spacing !== false );
		setToolVisible( ui.alignRow, productSettings.allow_text_align !== false );
		setToolVisible( ui.popoutBtn, productSettings.enable_popout !== false );
		if ( ui.textColor ) {
			ui.textColor.value = defaultTextColor();
		}
	}

	const canvas = new fabric.Canvas( 'wc-gpd-canvas', {
		selection: true,
		preserveObjectStacking: true,
		width: PROD_WIDTH,
		height: PROD_HEIGHT,
		backgroundColor: productSettings.canvas_bg_color || '#f0f0f0',
	} );

	const templateViews = Array.isArray( config.templateViews ) && config.templateViews.length
		? config.templateViews
		: [
			{
				id: 'view_front',
				label: config.i18n.designArea || 'Front',
				templateUrl: config.templateUrl || '',
				boundingBoxUid: '',
				objects: [],
			},
		];

	const viewDesigns = {};
	const viewSwitcherEl = document.getElementById( 'wc-gpd-view-switcher' );
	let activeViewId = templateViews[ 0 ].id;
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
	 * Scale canvas via CSS only (keeps coordinates accurate for edit/export).
	 */
	function applyResponsiveScale() {
		const wrap = designerRoot.querySelector( '.wc-gpd-designer__canvas-wrap' );
		if ( ! wrap ) {
			return;
		}

		const isPopout = designerRoot.classList.contains( 'wc-gpd-is-popout' );
		const maxWidth = isPopout
			? Math.min( window.innerWidth - 16, 1200 )
			: Math.max( 1, wrap.clientWidth );
		displayScale = Math.min( 1, maxWidth / PROD_WIDTH );
		const displayW = Math.max( 1, Math.floor( PROD_WIDTH * displayScale ) );
		const displayH = Math.max( 1, Math.floor( PROD_HEIGHT * displayScale ) );

		canvas.setZoom( 1 );
		canvas.viewportTransform = [ 1, 0, 0, 1, 0, 0 ];
		canvas.setDimensions( { width: PROD_WIDTH, height: PROD_HEIGHT } );

		if ( typeof canvas.setDimensions === 'function' ) {
			canvas.setDimensions( { width: displayW, height: displayH }, { cssOnly: true } );
		}

		canvasEl.style.maxWidth = '100%';
		canvasEl.style.width = `${ displayW }px`;
		canvasEl.style.height = `${ displayH }px`;

		if ( canvas.wrapperEl ) {
			canvas.wrapperEl.style.maxWidth = '100%';
			canvas.wrapperEl.style.width = `${ displayW }px`;
			canvas.wrapperEl.style.height = `${ displayH }px`;
			canvas.wrapperEl.style.margin = '0 auto';
		}

		refreshObjectCoords();
		canvas.calcOffset();
		canvas.requestRenderAll();
	}

	/**
	 * Recalculate object coordinates after layout changes.
	 */
	function refreshObjectCoords() {
		canvas.getObjects().forEach( ( obj ) => {
			obj.setCoords();
		} );
	}

	/**
	 * @returns {object|null}
	 */
	function getActiveViewConfig() {
		return templateViews.find( ( view ) => view.id === activeViewId ) || templateViews[ 0 ] || null;
	}

	/**
	 * @returns {{left:number,top:number,width:number,height:number}|null}
	 */
	function getBoundingBoxRect() {
		const bboxObj = canvas.getObjects().find( ( obj ) => obj.wcGpdBoundingBox );
		if ( ! bboxObj ) {
			return null;
		}
		bboxObj.setCoords();
		return bboxObj.getBoundingRect( true );
	}

	/**
	 * @returns {{left:number,top:number,width:number,height:number}}
	 */
	function getConstraintRect() {
		const bbox = getBoundingBoxRect();
		if ( bbox ) {
			return bbox;
		}
		return {
			left: 2,
			top: 2,
			width: PROD_WIDTH - 4,
			height: PROD_HEIGHT - 4,
		};
	}

	function clearTemplateLayers() {
		canvas.getObjects().slice().forEach( ( obj ) => {
			if ( isTemplateLayer( obj ) ) {
				canvas.remove( obj );
			}
		} );
	}

	function persistCurrentViewDesign() {
		viewDesigns[ activeViewId ] = canvas.getObjects()
			.filter( ( obj ) => isDesignLayer( obj ) )
			.map( ( obj ) => {
				const data = obj.toObject( [ 'wcGpdTextLayer', 'wcGpdLayerType' ] );
				if ( isUsableTextLayer( obj ) ) {
					data.wcGpdLayerType = 'text';
					data.wcGpdTextLayer = true;
				}
				return data;
			} );
	}

	function renderViewSwitcher() {
		if ( ! viewSwitcherEl ) {
			return;
		}

		viewSwitcherEl.innerHTML = '';
		if ( templateViews.length <= 1 ) {
			viewSwitcherEl.hidden = true;
			return;
		}

		viewSwitcherEl.hidden = false;
		templateViews.forEach( ( view ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wc-gpd-view-tab' + ( view.id === activeViewId ? ' is-active' : '' );
			btn.textContent = view.label || view.id;
			btn.setAttribute( 'role', 'tab' );
			btn.setAttribute( 'aria-selected', view.id === activeViewId ? 'true' : 'false' );
			if ( viewDesigns[ view.id ] && viewDesigns[ view.id ].length ) {
				btn.classList.add( 'has-design' );
			}
			btn.addEventListener( 'click', () => {
				switchDesignView( view.id );
			} );
			viewSwitcherEl.appendChild( btn );
		} );
	}

	function setCanvasBackground() {
		canvas.backgroundImage = null;
		canvas.backgroundColor = productSettings.canvas_bg_color || '#f0f0f0';
		canvas.requestRenderAll();
	}

	function isMockupImage( obj ) {
		return !! obj && obj.type === 'image' && ( obj.wcGpdMockupImage || obj.wcGpdLayerType === 'mockup' );
	}

	/**
	 * Load template outlines and customer layers for a design area.
	 *
	 * @param {string} viewId View ID.
	 * @returns {Promise<void>}
	 */
	function loadDesignView( viewId ) {
		persistCurrentViewDesign();
		activeViewId = viewId;
		const view = getActiveViewConfig();
		clearTextLayers();
		clearTemplateLayers();
		discardSelection();

		if ( ! view ) {
			renderViewSwitcher();
			return Promise.resolve();
		}

		setCanvasBackground();
		const templateObjects = Array.isArray( view.objects ) ? view.objects : [];
		const designObjects = viewDesigns[ viewId ] || [];

		return new Promise( ( resolve ) => {
			const afterTemplate = () => {
					if ( ! designObjects.length ) {
						applyResponsiveScale();
						renderViewSwitcher();
						resolve();
						return;
					}

					fabric.util.enlivenObjects(
						designObjects,
						( objects ) => {
							objects.forEach( ( obj ) => {
								obj.wcGpdTextLayer = obj.wcGpdTextLayer || isTextLayer( obj );
								enforceSingleColor( obj );
								canvas.add( obj );
								obj.setCoords();
							} );
							purgePhantomLayers();
							applyResponsiveScale();
							renderViewSwitcher();
							resolve();
						},
						'fabric'
					);
				};

				if ( ! templateObjects.length ) {
					afterTemplate();
					return;
				}

				fabric.util.enlivenObjects(
					templateObjects,
					( objects ) => {
						objects.forEach( ( obj ) => {
							obj.wcGpdTemplateLayer = true;
							if ( isMockupImage( obj ) ) {
								if ( obj.wcGpdMockupVisible === false ) {
									return;
								}
								obj.set( {
									selectable: false,
									evented: false,
									hasControls: false,
									hasBorders: false,
								} );
								canvas.add( obj );
								canvas.sendToBack( obj );
								return;
							}
							obj.wcGpdOutlineLayer = !! obj.wcGpdOutlineLayer || obj.wcGpdLayerType === 'outline';
							obj.wcGpdBoundingBox = view.boundingBoxUid && obj.wcGpdUid === view.boundingBoxUid;
							if ( obj.wcGpdBoundingBox ) {
								obj.stroke = obj.stroke || '#2b6cb0';
								obj.strokeDashArray = [ 8, 6 ];
							}
							obj.set( {
								selectable: false,
								evented: false,
								hasControls: false,
								hasBorders: false,
							} );
							canvas.add( obj );
						} );
						canvas.getObjects().forEach( ( obj ) => {
							if ( isMockupImage( obj ) ) {
								canvas.sendToBack( obj );
							}
						} );
						canvas.requestRenderAll();
						afterTemplate();
					},
					'fabric'
				);
			} );
	}

	function switchDesignView( viewId ) {
		if ( viewId === activeViewId ) {
			return;
		}
		loadDesignView( viewId );
	}

	/**
	 * Keep object bounding box inside canvas.
	 *
	 * @param {fabric.Object} obj Fabric object.
	 */
	function constrainToCanvas( obj ) {
		if ( ! obj || obj.wcGpdBackground || isTemplateLayer( obj ) ) {
			return;
		}

		const region = getConstraintRect();
		obj.setCoords();
		const rect = obj.getBoundingRect( true );
		let deltaX = 0;
		let deltaY = 0;

		if ( rect.left < region.left ) {
			deltaX = region.left - rect.left;
		} else if ( rect.left + rect.width > region.left + region.width ) {
			deltaX = region.left + region.width - ( rect.left + rect.width );
		}

		if ( rect.top < region.top ) {
			deltaY = region.top - rect.top;
		} else if ( rect.top + rect.height > region.top + region.height ) {
			deltaY = region.top + region.height - ( rect.top + rect.height );
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
		if ( ! obj || obj.wcGpdBackground || isTemplateLayer( obj ) ) {
			return;
		}

		const region = getConstraintRect();
		obj.setCoords();
		const rect = obj.getBoundingRect( true );
		const maxW = region.width;
		const maxH = region.height;

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
	function isTemplateLayer( obj ) {
		return !! obj && ( obj.wcGpdTemplateLayer || obj.wcGpdOutlineLayer || obj.wcGpdBoundingBox || isMockupImage( obj ) );
	}

	function isTextLayer( obj ) {
		if ( ! obj || obj.wcGpdBackground || isTemplateLayer( obj ) ) {
			return false;
		}
		const type = obj.type || '';
		return !! obj.wcGpdTextLayer || type === 'i-text' || type === 'text' || type === 'textbox';
	}

	function isDesignLayer( obj ) {
		if ( ! obj || obj.wcGpdBackground || isTemplateLayer( obj ) ) {
			return false;
		}
		if ( isUsableTextLayer( obj ) ) {
			return true;
		}
		const type = obj.type || '';
		return ( type === 'rect' || type === 'circle' || type === 'ellipse' ) && obj.wcGpdLayerType === 'shape';
	}

	/**
	 * Real, visible text layers only (excludes phantom SVG/group artifacts).
	 *
	 * @param {fabric.Object} obj Fabric object.
	 * @returns {boolean}
	 */
	function isUsableTextLayer( obj ) {
		if ( ! isTextLayer( obj ) || obj.type === 'group' ) {
			return false;
		}

		const text = typeof obj.text === 'string' ? obj.text.trim() : '';
		if ( ( obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox' ) && ! text ) {
			return false;
		}

		obj.setCoords();
		const rect = obj.getBoundingRect( true );
		return rect.width >= 2 && rect.height >= 2;
	}

	/**
	 * Remove empty or broken layers left from SVG import.
	 */
	function purgePhantomLayers() {
		canvas.getObjects().slice().forEach( ( obj ) => {
			if ( isTextLayer( obj ) && ! isUsableTextLayer( obj ) ) {
				canvas.remove( obj );
			}
		} );
	}

	/**
	 * Clear selection and refresh UI.
	 */
	function discardSelection() {
		canvas.discardActiveObject();
		activeText = null;
		syncToolbar( null );
		syncLayersList();
		canvas.requestRenderAll();
	}

	/**
	 * @returns {fabric.Object[]}
	 */
	function getUsableTextLayers() {
		return canvas.getObjects().filter( ( o ) => isUsableTextLayer( o ) );
	}

	/**
	 * @returns {boolean}
	 */
	function hasTextLayer() {
		persistCurrentViewDesign();
		const current = getUsableTextLayers().length > 0;
		if ( current ) {
			return true;
		}
		return templateViews.some( ( view ) => {
			const objects = viewDesigns[ view.id ] || [];
			return objects.some( ( obj ) => {
				const text = typeof obj.text === 'string' ? obj.text.trim() : '';
				return text.length > 0;
			} );
		} );
	}

	/**
	 * Render clickable layer list (top layer first).
	 */
	function syncLayersList() {
		if ( ! ui.layersList ) {
			return;
		}

		const layers = getUsableTextLayers();
		ui.layersList.innerHTML = '';

		if ( ! layers.length ) {
			const empty = document.createElement( 'li' );
			empty.className = 'wc-gpd-layers-list__empty';
			empty.textContent = config.i18n.noLayers || 'No layers yet.';
			ui.layersList.appendChild( empty );
			return;
		}

		const ordered = layers.slice().reverse();
		ordered.forEach( ( obj, index ) => {
			const label = ( obj.text && String( obj.text ).trim() )
				? String( obj.text ).trim().slice( 0, 40 )
				: ( config.i18n.layerText || 'Text layer' ) + ' ' + ( index + 1 );

			const li = document.createElement( 'li' );
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wc-gpd-layer-item';
			btn.textContent = label;
			btn.setAttribute( 'data-layer-index', String( canvas.getObjects().indexOf( obj ) ) );

			if ( canvas.getActiveObject() === obj ) {
				btn.classList.add( 'is-active' );
				btn.setAttribute( 'aria-pressed', 'true' );
			} else {
				btn.setAttribute( 'aria-pressed', 'false' );
			}

			btn.addEventListener( 'click', () => {
				canvas.setActiveObject( obj );
				syncToolbar( obj );
				syncLayersList();
				canvas.requestRenderAll();
			} );

			li.appendChild( btn );
			ui.layersList.appendChild( li );
		} );
	}

	/**
	 * Remove editable text layers from the canvas.
	 */
	function clearTextLayers() {
		canvas.getObjects().slice().forEach( ( obj ) => {
			if ( isDesignLayer( obj ) || isTextLayer( obj ) ) {
				canvas.remove( obj );
			}
		} );
		discardSelection();
	}

	/**
	 * Sync toolbar from active text object.
	 *
	 * @param {fabric.IText|null} obj Active text.
	 */
	function syncToolbar( obj ) {
		activeText = obj;
		const enabled = !! obj;

		if ( ui.dock ) {
			ui.dock.classList.toggle( 'is-disabled', ! enabled );
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
		const isBold = obj.fontWeight === 'bold';
		const isItalic = obj.fontStyle === 'italic';
		const isUnderline = !! obj.underline;
		if ( ui.bold ) {
			ui.bold.checked = isBold;
		}
		if ( ui.italic ) {
			ui.italic.checked = isItalic;
		}
		if ( ui.boldBtn ) {
			ui.boldBtn.classList.toggle( 'is-active', isBold );
		}
		if ( ui.italicBtn ) {
			ui.italicBtn.classList.toggle( 'is-active', isItalic );
		}
		if ( ui.underlineBtn ) {
			ui.underlineBtn.classList.toggle( 'is-active', isUnderline );
		}
		if ( ui.textColor ) {
			ui.textColor.value = obj.fill || defaultTextColor();
		}
		if ( ui.underline ) {
			ui.underline.checked = !! obj.underline;
		}
		if ( ui.lineHeight ) {
			ui.lineHeight.value = String( obj.lineHeight || 1.16 );
		}
		if ( ui.letterSpacing ) {
			ui.letterSpacing.value = String( obj.charSpacing || 0 );
		}

		const align = obj.textAlign || 'left';
		ui.alignButtons.forEach( ( btn ) => {
			const isActive = btn.dataset.align === align;
			btn.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
			btn.classList.toggle( 'is-active', isActive );
		} );

		enforceSingleColor( obj );
	}

	/**
	 * Add a new editable text layer.
	 */
	function addTextLayer() {
		const region = getConstraintRect();
		const text = new fabric.IText( 'Your text', {
			left: region.left + region.width / 2,
			top: region.top + region.height / 2,
			originX: 'center',
			originY: 'center',
			fontFamily: config.fonts[ 0 ],
			fontSize: 32,
			fill: defaultTextColor(),
			lineHeight: 1.16,
			charSpacing: 0,
			wcGpdTextLayer: true,
			wcGpdLayerType: 'text',
		} );

		canvas.add( text );
		canvas.setActiveObject( text );
		canvas.requestRenderAll();
		syncToolbar( text );
		syncLayersList();
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
		canvas.setDimensions( { width: PROD_WIDTH, height: PROD_HEIGHT } );

		canvas.getObjects().forEach( ( o ) => {
			o._wcGpdWasVisible = o.visible;
			o.visible = isDesignLayer( o );
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
		canvas.setZoom( 1 );
		canvas.viewportTransform = [ 1, 0, 0, 1, 0, 0 ];
		applyResponsiveScale();

		if ( svg && ! /width\s*=/.test( svg ) ) {
			svg = svg.replace( '<svg ', `<svg width="${ PROD_WIDTH }" height="${ PROD_HEIGHT }" ` );
		}

		log.info( 'Production SVG exported', { bytes: svg ? svg.length : 0 } );
		return svg;
	}

	/**
	 * Serialize text layers for accurate cart edit round-trip.
	 *
	 * @returns {string}
	 */
	function exportDesignJson() {
		persistCurrentViewDesign();
		const views = {};

		templateViews.forEach( ( view ) => {
			const objects = viewDesigns[ view.id ] || [];
			if ( objects.length ) {
				views[ view.id ] = { objects };
			}
		} );

		return JSON.stringify( {
			version: 2,
			views,
		} );
	}

	/**
	 * PNG preview for cart thumbnails (saved server-side).
	 *
	 * @returns {string}
	 */
	function exportPreviewPng() {
		const multiplier = Math.min( 0.45, 360 / PROD_WIDTH );
		canvas.setZoom( 1 );
		canvas.viewportTransform = [ 1, 0, 0, 1, 0, 0 ];
		canvas.setDimensions( { width: PROD_WIDTH, height: PROD_HEIGHT } );
		canvas.renderAll();

		const dataUrl = canvas.toDataURL( {
			format: 'png',
			multiplier,
			quality: 0.85,
		} );

		applyResponsiveScale();
		return dataUrl;
	}

	/**
	 * Export SVG and JSON, write to hidden inputs.
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

		if ( jsonInput ) {
			jsonInput.value = exportDesignJson();
		}

		if ( previewInput ) {
			try {
				previewInput.value = exportPreviewPng();
			} catch ( error ) {
				log.warn( 'Preview PNG export failed', error );
				previewInput.value = '';
			}
		}

		return true;
	}

	/**
	 * Restore text layers from Fabric JSON (preferred for edit mode).
	 *
	 * @param {string} jsonString Saved canvas JSON.
	 * @returns {Promise<void>}
	 */
	function loadDesignFromJson( jsonString ) {
		return new Promise( ( resolve, reject ) => {
			let data;
			try {
				data = JSON.parse( jsonString );
			} catch ( error ) {
				reject( error );
				return;
			}

			if ( ! data.objects || ! data.objects.length ) {
				reject( new Error( 'empty' ) );
				return;
			}

			fabric.util.enlivenObjects(
				data.objects,
				( objects ) => {
					if ( ! objects || ! objects.length ) {
						reject( new Error( 'empty' ) );
						return;
					}

					clearTextLayers();

					objects.forEach( ( obj ) => {
						obj.wcGpdTextLayer = true;
						canvas.add( obj );
						obj.setCoords();
					} );

					purgePhantomLayers();
					discardSelection();
					applyResponsiveScale();

					const usable = getUsableTextLayers();
					if ( usable.length ) {
						canvas.setActiveObject( usable[ 0 ] );
						syncToolbar( usable[ 0 ] );
					}

					syncLayersList();
					log.info( 'Loaded design from JSON', { layers: usable.length } );
					resolve();
				},
				'fabric'
			);
		} );
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

				clearTextLayers();

				objects.forEach( ( obj ) => {
					obj.wcGpdTextLayer = true;
					canvas.add( obj );
					obj.setCoords();
				} );

				purgePhantomLayers();
				discardSelection();
				applyResponsiveScale();

				const usable = getUsableTextLayers();
				if ( ! usable.length ) {
					reject( new Error( 'empty' ) );
					return;
				}

				canvas.setActiveObject( usable[ 0 ] );
				syncToolbar( usable[ 0 ] );
				syncLayersList();
				log.info( 'Loaded design from SVG', { layers: usable.length } );
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
		if ( isUsableTextLayer( target ) ) {
			syncToolbar( target );
			syncLayersList();
		} else {
			discardSelection();
		}
	} );

	canvas.on( 'selection:updated', ( e ) => {
		const target = e.selected?.[ 0 ] || e.target;
		if ( isUsableTextLayer( target ) ) {
			syncToolbar( target );
			syncLayersList();
		} else {
			discardSelection();
		}
	} );

	canvas.on( 'selection:cleared', () => discardSelection() );

	canvas.on( 'object:added', syncLayersList );
	canvas.on( 'object:removed', syncLayersList );

	canvas.on( 'object:moving', ( e ) => constrainToCanvas( e.target ) );
	canvas.on( 'object:scaling', ( e ) => {
		constrainScale( e.target );
		constrainToCanvas( e.target );
	} );
	canvas.on( 'object:modified', ( e ) => {
		constrainToCanvas( e.target );
		syncLayersList();
	} );

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

	function bindStyleToggle( btn, checkbox, apply ) {
		if ( ! btn || ! checkbox ) {
			return;
		}
		btn.addEventListener( 'click', () => {
			if ( ! activeText || ui.dock?.classList.contains( 'is-disabled' ) ) {
				return;
			}
			checkbox.checked = ! checkbox.checked;
			apply( checkbox.checked );
			btn.classList.toggle( 'is-active', checkbox.checked );
			canvas.requestRenderAll();
		} );
	}

	bindStyleToggle( ui.boldBtn, ui.bold, ( on ) => {
		if ( activeText ) {
			activeText.set( 'fontWeight', on ? 'bold' : 'normal' );
		}
	} );
	bindStyleToggle( ui.italicBtn, ui.italic, ( on ) => {
		if ( activeText ) {
			activeText.set( 'fontStyle', on ? 'italic' : 'normal' );
		}
	} );
	bindStyleToggle( ui.underlineBtn, ui.underline, ( on ) => {
		if ( activeText ) {
			activeText.set( 'underline', on );
		}
	} );

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

	if ( ui.textColor ) {
		ui.textColor.addEventListener( 'input', () => {
			if ( ! activeText || ! textColorAllowed() ) {
				return;
			}
			activeText.set( 'fill', ui.textColor.value );
			canvas.requestRenderAll();
		} );
	}

	if ( ui.lineHeight ) {
		ui.lineHeight.addEventListener( 'input', () => {
			if ( ! activeText ) {
				return;
			}
			const value = Math.min( 3, Math.max( 0.5, parseFloat( ui.lineHeight.value ) || 1.16 ) );
			activeText.set( 'lineHeight', value );
			canvas.requestRenderAll();
		} );
	}

	if ( ui.letterSpacing ) {
		ui.letterSpacing.addEventListener( 'input', () => {
			if ( ! activeText ) {
				return;
			}
			const value = Math.min( 200, Math.max( -50, parseInt( ui.letterSpacing.value, 10 ) || 0 ) );
			activeText.set( 'charSpacing', value );
			canvas.requestRenderAll();
		} );
	}

	if ( ui.popoutBtn && window.WcGpdPopout && productSettings.enable_popout !== false ) {
		ui.popoutBtn.addEventListener( 'click', () => {
			window.WcGpdPopout.toggle( designerRoot, applyResponsiveScale );
		} );
		designerRoot.addEventListener( 'wc-gpd-popout-closed', applyResponsiveScale );
	} else if ( ui.popoutBtn ) {
		ui.popoutBtn.hidden = true;
	}

	if ( ui.layerForward ) {
		ui.layerForward.addEventListener( 'click', () => {
			const obj = canvas.getActiveObject();
			if ( ! isUsableTextLayer( obj ) ) {
				return;
			}
			canvas.bringForward( obj );
			syncLayersList();
			canvas.requestRenderAll();
		} );
	}

	if ( ui.layerBackward ) {
		ui.layerBackward.addEventListener( 'click', () => {
			const obj = canvas.getActiveObject();
			if ( ! isUsableTextLayer( obj ) ) {
				return;
			}
			canvas.sendBackwards( obj );
			syncLayersList();
			canvas.requestRenderAll();
		} );
	}

	if ( ui.layerDelete ) {
		ui.layerDelete.addEventListener( 'click', () => {
			const obj = canvas.getActiveObject();
			if ( ! isUsableTextLayer( obj ) ) {
				return;
			}
			canvas.remove( obj );
			discardSelection();
		} );
	}

	let resizeTimer;
	const canvasWrap = designerRoot.querySelector( '.wc-gpd-designer__canvas-wrap' );
	if ( canvasWrap && typeof ResizeObserver !== 'undefined' ) {
		const resizeObserver = new ResizeObserver( () => {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( applyResponsiveScale, 100 );
		} );
		resizeObserver.observe( canvasWrap );
	} else {
		window.addEventListener( 'resize', () => {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( applyResponsiveScale, 150 );
		} );
	}

	function startWithNewTextLayer() {
		addTextLayer();
		applyResponsiveScale();
	}

	function loadExistingDesign() {
		if ( config.existingDesignJson ) {
			try {
				const data = JSON.parse( config.existingDesignJson );
				if ( data.views && typeof data.views === 'object' ) {
					Object.keys( data.views ).forEach( ( viewId ) => {
						const entry = data.views[ viewId ];
						if ( entry && Array.isArray( entry.objects ) ) {
							viewDesigns[ viewId ] = entry.objects;
						}
					} );
					const firstWithDesign = templateViews.find( ( view ) => viewDesigns[ view.id ] && viewDesigns[ view.id ].length );
					if ( firstWithDesign ) {
						activeViewId = firstWithDesign.id;
					}
					return loadDesignView( activeViewId );
				}
			} catch ( error ) {
				log.warn( 'Failed to parse multi-view design JSON', error );
			}
			return loadDesignFromJson( config.existingDesignJson );
		}

		if ( config.isEditing ) {
			return Promise.reject( new Error( 'no-json' ) );
		}

		if ( config.existingDesignSvg ) {
			return loadDesignFromSvg( config.existingDesignSvg );
		}

		return Promise.reject( new Error( 'no-design' ) );
	}

	function bindGalleryModal() {
		if ( ! ui.galleryModal || ! Array.isArray( config.galleryImages ) ) {
			return;
		}

		const images = config.galleryImages;
		if ( ui.galleryScroll && images.length ) {
			ui.galleryScroll.innerHTML = '';
			images.forEach( ( image ) => {
				const img = document.createElement( 'img' );
				img.src = image.src;
				img.alt = image.alt || '';
				img.loading = 'lazy';
				ui.galleryScroll.appendChild( img );
			} );
		}

		const open = () => {
			ui.galleryModal.hidden = false;
			document.body.classList.add( 'wc-gpd-gallery-open' );
		};
		const close = () => {
			ui.galleryModal.hidden = true;
			document.body.classList.remove( 'wc-gpd-gallery-open' );
		};

		if ( ui.viewPhotosBtn && images.length ) {
			ui.viewPhotosBtn.addEventListener( 'click', open );
		} else if ( ui.viewPhotosBtn ) {
			ui.viewPhotosBtn.hidden = true;
		}
		if ( ui.galleryClose ) {
			ui.galleryClose.addEventListener( 'click', close );
		}
		if ( ui.galleryBackdrop ) {
			ui.galleryBackdrop.addEventListener( 'click', close );
		}
	}

	initFontSelect();
	applyProductToolSettings();
	bindGalleryModal();
	bindAddToCart();

	if ( config.isEditing && config.i18n.updateCart ) {
		const addBtn = getAddToCartButton();
		if ( addBtn ) {
			addBtn.textContent = config.i18n.updateCart;
		}
	}

	function bindOrderSave() {
		const btn = document.getElementById( 'wc-gpd-save-order-design' );
		if ( ! btn || ! config.orderEdit ) {
			return;
		}

		btn.addEventListener( 'click', () => {
			if ( ! prepareDesignForCart() ) {
				return;
			}

			const saveForm = document.createElement( 'form' );
			saveForm.method = 'POST';
			saveForm.action = config.adminPostUrl;
			saveForm.style.display = 'none';

			const fields = {
				action: config.orderSaveAction,
				order_id: String( config.orderId ),
				item_id: String( config.orderItemId ),
				_wc_gpd_save_order_nonce: config.orderSaveNonce,
				wc_gpd_design_svg: svgInput.value,
				wc_gpd_design_json: jsonInput ? jsonInput.value : '',
			};

			Object.keys( fields ).forEach( ( name ) => {
				const input = document.createElement( 'input' );
				input.type = 'hidden';
				input.name = name;
				input.value = fields[ name ];
				saveForm.appendChild( input );
			} );

			document.body.appendChild( saveForm );
			saveForm.submit();
		} );
	}

	const bootDesigner = () => {
		requestAnimationFrame( () => {
			applyResponsiveScale();

			loadDesignView( activeViewId ).then( () => {
				if ( config.isEditing || config.existingDesignJson || config.existingDesignSvg ) {
					loadExistingDesign().catch( () => {
						log.warn( 'Failed to load saved design; starting fresh' );
						if ( config.isEditing && ! config.orderEdit ) {
							window.alert( config.i18n.loadDesignError );
						}
						if ( ! config.orderEdit ) {
							startWithNewTextLayer();
						}
					} );
				} else if ( ! config.orderEdit ) {
					startWithNewTextLayer();
				}

				bindOrderSave();
				log.info( 'Designer ready' );
			} );
		} );
	};

	if ( document.readyState === 'complete' ) {
		bootDesigner();
	} else {
		window.addEventListener( 'load', bootDesigner, { once: true } );
	}
} )();
