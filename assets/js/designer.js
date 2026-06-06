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
	const templatePalettes = config.templatePalettes || {
		palettes: [ { id: 'pal_default', name: 'Default', colors: [ '#000000' ] } ],
		use_global_colors: false,
		global_colors: [ '#000000' ],
	};

	const log = window.wcGpdDebug || { setEnabled() {}, debug() {}, info() {}, warn() {}, error() {} };
	log.setEnabled( !! config.debug );
	log.info( 'Designer initializing', { width: config.canvasWidth, height: config.canvasHeight } );

	const TEMPLATE_METADATA_PROPS = [
		'wcGpdUid', 'wcGpdLayerType', 'wcGpdLayerLabel', 'wcGpdTemplateLayer', 'wcGpdOutlineLayer', 'wcGpdBoundingBox',
		'wcGpdMockupImage', 'wcGpdMockupVisible', 'wcGpdGraphicLayer', 'wcGpdGraphicSlot', 'wcGpdGraphicLibraryId',
		'wcGpdExportGraphic', 'wcGpdCustomerMovable', 'wcGpdCustomerResizable', 'wcGpdPlaceholderLabel', 'wcGpdPlaceholderKey',
		'wcGpdShrinkToFit', 'wcGpdFitMode', 'wcGpdPaletteId', 'wcGpdLayerColors', 'wcGpdStrokePaletteId', 'wcGpdStrokeLayerColors',
		'wcGpdShapeUseFill', 'wcGpdShapeUseStroke', 'wcGpdLockFont', 'wcGpdLockSize', 'wcGpdLockColor', 'wcGpdLockBold',
		'wcGpdLockItalic', 'wcGpdLockAlign', 'wcGpdLockUnderline', 'wcGpdLockLineHeight', 'wcGpdLockLetterSpacing', 'wcGpdLockText',
		'wcGpdLockMove', 'wcGpdLockScale', 'wcGpdLockAspect', 'wcGpdCustomerEditable', 'wcGpdHideFromCustomerLayers',
		'wcGpdCustomerPaletteOnly', 'wcGpdTextLayer', 'wcGpdAttachmentId', 'wcGpdGraphicSlotUid', 'wcGpdGraphicLayer',
	];

	function registerFabricCustomProperties() {
		const classes = [
			fabric.Object, fabric.Text, fabric.Textbox, fabric.IText, fabric.Rect, fabric.Circle,
			fabric.Ellipse, fabric.Polygon, fabric.Path, fabric.Polyline, fabric.Line, fabric.Group, fabric.Image,
		];
		classes.forEach( ( klass ) => {
			if ( ! klass ) {
				return;
			}
			if ( ! klass.customProperties ) {
				klass.customProperties = [];
			}
			TEMPLATE_METADATA_PROPS.forEach( ( prop ) => {
				if ( klass.customProperties.indexOf( prop ) < 0 ) {
					klass.customProperties.push( prop );
				}
			} );
		} );
	}

	function applyTemplateMetadata( obj, source ) {
		if ( ! obj || ! source || typeof source !== 'object' ) {
			return;
		}
		TEMPLATE_METADATA_PROPS.forEach( ( key ) => {
			if ( source[ key ] !== undefined ) {
				obj[ key ] = source[ key ];
			}
		} );
		normalizeCustomerLockProps( obj );
	}

	function normalizeCustomerLockProps( obj ) {
		if ( ! obj ) {
			return;
		}
		const lockProps = [
			'wcGpdLockFont', 'wcGpdLockSize', 'wcGpdLockColor', 'wcGpdLockBold', 'wcGpdLockItalic',
			'wcGpdLockAlign', 'wcGpdLockUnderline', 'wcGpdLockLineHeight', 'wcGpdLockLetterSpacing',
			'wcGpdLockText', 'wcGpdLockMove', 'wcGpdLockScale', 'wcGpdLockAspect',
		];
		lockProps.forEach( ( key ) => {
			const val = obj[ key ];
			if ( val === 'true' || val === '1' || val === 1 ) {
				obj[ key ] = true;
			} else if ( val === 'false' || val === '0' || val === 0 || val === null || typeof val === 'undefined' ) {
				obj[ key ] = false;
			}
		} );
		if ( obj.wcGpdCustomerEditable === 'false' || obj.wcGpdCustomerEditable === '0' || obj.wcGpdCustomerEditable === 0 ) {
			obj.wcGpdCustomerEditable = false;
		} else if ( typeof obj.wcGpdCustomerEditable === 'undefined' || obj.wcGpdCustomerEditable === 'true' || obj.wcGpdCustomerEditable === '1' || obj.wcGpdCustomerEditable === 1 ) {
			if ( obj.wcGpdCustomerEditable !== false ) {
				obj.wcGpdCustomerEditable = true;
			}
		}
	}

	registerFabricCustomProperties();

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

	let form = resolveCartForm();

	if ( ! canvasEl || ! designerRoot || ! svgInput ) {
		if ( log.error ) {
			log.error( 'Designer markup missing required elements', {
				canvas: !! canvasEl,
				root: !! designerRoot,
				svgInput: !! svgInput,
			} );
		}
		return;
	}

	function getCartForm() {
		if ( form && document.body.contains( form ) ) {
			return form;
		}
		form = resolveCartForm();
		return form;
	}

	/**
	 * Ensure hidden SVG field and nonce live inside the cart form.
	 */
	function ensureFieldsInForm() {
		const cartForm = getCartForm();
		if ( ! cartForm ) {
			return;
		}
		if ( cartForm.contains( svgInput ) ) {
			return;
		}
		cartForm.appendChild( svgInput );
		log.debug( 'Moved SVG input into cart form' );

		const nonce = designerRoot.querySelector( 'input[name="wc_gpd_add_to_cart_nonce"]' );
		if ( nonce && ! cartForm.contains( nonce ) ) {
			cartForm.appendChild( nonce );
			log.debug( 'Moved nonce into cart form' );
		}

		const editKey = document.getElementById( 'wc-gpd-edit-cart-key' );
		if ( editKey && ! cartForm.contains( editKey ) ) {
			cartForm.appendChild( editKey );
			log.debug( 'Moved edit cart key into cart form' );
		}

		if ( jsonInput && ! cartForm.contains( jsonInput ) ) {
			cartForm.appendChild( jsonInput );
			log.debug( 'Moved design JSON into cart form' );
		}

		if ( previewInput && ! cartForm.contains( previewInput ) ) {
			cartForm.appendChild( previewInput );
			log.debug( 'Moved preview image into cart form' );
		}
	}

	ensureFieldsInForm();

	const PROD_WIDTH = config.canvasWidth;
	const PROD_HEIGHT = config.canvasHeight;

	const ui = {
		addText: document.getElementById( 'wc-gpd-add-text' ),
		toolsPanel: document.getElementById( 'wc-gpd-tools-panel' ),
		contextEmpty: document.getElementById( 'wc-gpd-context-empty' ),
		contextPane: document.getElementById( 'wc-gpd-context-pane' ),
		contextLayerName: document.getElementById( 'wc-gpd-context-layer-name' ),
		fontFamily: document.getElementById( 'wc-gpd-font-family' ),
		fontSize: document.getElementById( 'wc-gpd-font-size' ),
		bold: document.getElementById( 'wc-gpd-bold' ),
		italic: document.getElementById( 'wc-gpd-italic' ),
		boldBtn: document.getElementById( 'wc-gpd-bold-btn' ),
		italicBtn: document.getElementById( 'wc-gpd-italic-btn' ),
		underlineBtn: document.getElementById( 'wc-gpd-underline-btn' ),
		textColor: document.getElementById( 'wc-gpd-text-color' ),
		colorSwatches: document.getElementById( 'wc-gpd-color-swatches' ),
		underline: document.getElementById( 'wc-gpd-underline' ),
		lineHeight: document.getElementById( 'wc-gpd-line-height' ),
		letterSpacing: document.getElementById( 'wc-gpd-letter-spacing' ),
		lineHeightLabel: designerRoot.querySelector( '.wc-gpd-control-line-height' ),
		letterSpacingLabel: designerRoot.querySelector( '.wc-gpd-control-letter-spacing' ),
		alignRow: designerRoot.querySelector( '.wc-gpd-control-align' ),
		alignButtons: designerRoot.querySelectorAll( '.wc-gpd-align' ),
		layersList: document.getElementById( 'wc-gpd-layers-list' ),
		layerForward: document.getElementById( 'wc-gpd-layer-forward' ),
		layerBackward: document.getElementById( 'wc-gpd-layer-backward' ),
		layerDelete: document.getElementById( 'wc-gpd-layer-delete' ),
		studio: document.getElementById( 'wc-gpd-studio' ),
		studioPanel: document.getElementById( 'wc-gpd-studio-panel' ),
		studioNav: document.getElementById( 'wc-gpd-studio-nav' ),
		drawerTitle: document.getElementById( 'wc-gpd-studio-drawer-title' ),
		sectionAdd: document.getElementById( 'wc-gpd-section-add' ),
		sectionContext: document.getElementById( 'wc-gpd-section-context' ),
		sectionDetails: document.getElementById( 'wc-gpd-section-details' ),
		navAdd: document.getElementById( 'wc-gpd-nav-add' ),
		navContext: document.getElementById( 'wc-gpd-nav-context' ),
		navDetails: document.getElementById( 'wc-gpd-nav-details' ),
		navLayers: document.getElementById( 'wc-gpd-nav-layers' ),
		designerAtc: document.getElementById( 'wc-gpd-designer-atc' ),
		copyDiagnostics: document.getElementById( 'wc-gpd-copy-diagnostics' ),
		placeholderFields: document.getElementById( 'wc-gpd-placeholder-fields' ),
		graphicPickers: document.getElementById( 'wc-gpd-graphic-pickers' ),
	};

	const sectionTitles = {
		add: config.i18n?.panelAdd || 'Add',
		details: config.i18n?.panelDetails || 'Your details',
		context: config.i18n?.panelContext || 'Edit',
		layers: config.i18n?.panelLayers || 'Layers',
	};

	let designerOpen = false;
	const isStartDesigningMode = config.launchMode === 'start_designing';
	const diagnosticsEnabled = !! config.diagnosticsEnabled;
	const diagnosticsLog = [];

	function logDiagnostic( type, data ) {
		if ( ! diagnosticsEnabled ) {
			return;
		}
		const entry = {
			at: new Date().toISOString(),
			type,
			data,
		};
		diagnosticsLog.push( entry );
		if ( diagnosticsLog.length > 80 ) {
			diagnosticsLog.shift();
		}
		log.debug( '[GPD diagnostics]', type, data );
	}

	function pickLockProps( obj ) {
		if ( ! obj ) {
			return {};
		}
		return {
			font: obj.wcGpdLockFont,
			size: obj.wcGpdLockSize,
			color: obj.wcGpdLockColor,
			bold: obj.wcGpdLockBold,
			italic: obj.wcGpdLockItalic,
			underline: obj.wcGpdLockUnderline,
			align: obj.wcGpdLockAlign,
			lineHeight: obj.wcGpdLockLineHeight,
			letterSpacing: obj.wcGpdLockLetterSpacing,
			text: obj.wcGpdLockText,
			move: obj.wcGpdLockMove,
			scale: obj.wcGpdLockScale,
		};
	}

	function summarizeTemplateViews() {
		return ( config.templateViews || [] ).map( ( view ) => ( {
			id: view.id,
			label: view.label,
			objectCount: ( view.objects || [] ).length,
			objects: ( view.objects || [] ).map( ( obj ) => ( {
				uid: obj.wcGpdUid,
				type: obj.type,
				layerType: obj.wcGpdLayerType,
				layerLabel: obj.wcGpdLayerLabel,
				customerEditable: obj.wcGpdCustomerEditable,
				locks: pickLockProps( obj ),
			} ) ),
		} ) );
	}

	function buildLayerPermissionReport( obj ) {
		if ( ! obj ) {
			return null;
		}
		const isText = isCustomerEditableTemplateText( obj ) || ( isTextLayer( obj ) && ! obj.wcGpdTemplateLayer );
		const isShapeLayer = isCustomerEditableTemplateShape( obj );
		return {
			uid: obj.wcGpdUid,
			type: obj.type,
			layerType: obj.wcGpdLayerType,
			layerLabel: obj.wcGpdLayerLabel,
			templateLayer: obj.wcGpdTemplateLayer,
			customerEditable: obj.wcGpdCustomerEditable,
			locks: pickLockProps( obj ),
			allows: {
				font: layerAllowsTool( obj, 'wcGpdLockFont', 'allow_font_family' ),
				size: layerAllowsTool( obj, 'wcGpdLockSize', 'allow_font_size' ),
				bold: layerAllowsTool( obj, 'wcGpdLockBold', 'allow_bold' ),
				italic: layerAllowsTool( obj, 'wcGpdLockItalic', 'allow_italic' ),
				underline: layerAllowsTool( obj, 'wcGpdLockUnderline', 'allow_underline' ),
				align: layerAllowsTool( obj, 'wcGpdLockAlign', 'allow_text_align' ),
				lineHeight: layerAllowsTool( obj, 'wcGpdLockLineHeight', 'allow_line_height' ),
				letterSpacing: layerAllowsTool( obj, 'wcGpdLockLetterSpacing', 'allow_letter_spacing' ),
				color: textColorAllowed( obj ),
			},
			uiVisible: {
				fontRow: ! getPropRow( ui.fontFamily )?.hidden,
				sizeRow: ! getPropRow( ui.fontSize )?.hidden,
				styleRow: ! getPropRow( ui.boldBtn )?.hidden,
				alignRow: ! getPropRow( ui.alignRow )?.hidden,
				colorRow: ! designerRoot.querySelector( '.wc-gpd-control-text-color' )?.hidden,
				lineHeightRow: ! getPropRow( ui.lineHeight )?.hidden,
				letterSpacingRow: ! getPropRow( ui.letterSpacing )?.hidden,
			},
			canvas: {
				selectable: obj.selectable,
				evented: obj.evented,
				editable: obj.editable,
				lockMovementX: obj.lockMovementX,
				lockMovementY: obj.lockMovementY,
				lockScalingX: obj.lockScalingX,
				lockScalingY: obj.lockScalingY,
			},
			isText,
			isShapeLayer,
		};
	}

	function buildDiagnosticsReport() {
		return {
			pluginVersion: config.pluginVersion || null,
			generatedAt: new Date().toISOString(),
			pageUrl: window.location.href,
			productId: config.productId || null,
			templateRef: config.templateRef || null,
			isSampleProduct: !! config.isSampleProduct,
			userAgent: navigator.userAgent,
			productSettings: config.productSettings || {},
			templateViews: summarizeTemplateViews(),
			canvasLayers: canvas.getObjects().map( ( obj ) => buildLayerPermissionReport( obj ) ),
			activeLayer: buildLayerPermissionReport( activeText ),
			recentEvents: diagnosticsLog.slice(),
		};
	}

	function copyDiagnosticsReport() {
		const report = JSON.stringify( buildDiagnosticsReport(), null, 2 );
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( report );
		}
		const textarea = document.createElement( 'textarea' );
		textarea.value = report;
		textarea.setAttribute( 'readonly', '' );
		textarea.style.position = 'absolute';
		textarea.style.left = '-9999px';
		document.body.appendChild( textarea );
		textarea.select();
		let copied = false;
		try {
			copied = document.execCommand( 'copy' );
		} catch ( error ) {
			copied = false;
		}
		document.body.removeChild( textarea );
		return copied ? Promise.resolve() : Promise.reject( new Error( 'copy failed' ) );
	}

	const graphicLibrary = Array.isArray( config.graphicLibrary ) ? config.graphicLibrary : [];
	const graphicLibraries = Array.isArray( config.graphicLibraries ) ? config.graphicLibraries : [];
	const DEFAULT_FONT = config.defaultFont || ( config.fonts && config.fonts[ 0 ] ) || '"Times New Roman", Times, serif';
	const DESIGN_SERIALIZE_PROPS = [
		'wcGpdTextLayer', 'wcGpdLayerType', 'wcGpdPlaceholderKey', 'wcGpdPlaceholderLabel', 'wcGpdShrinkToFit', 'wcGpdFitMode', 'wcGpdPaletteId', 'wcGpdLayerColors',
		'wcGpdStrokePaletteId', 'wcGpdStrokeLayerColors', 'wcGpdShapeUseFill', 'wcGpdShapeUseStroke',
		'wcGpdGraphicLayer', 'wcGpdGraphicSlotUid', 'wcGpdAttachmentId',
		'wcGpdLockFont', 'wcGpdLockSize', 'wcGpdLockColor', 'wcGpdLockBold', 'wcGpdLockItalic', 'wcGpdLockAlign',
		'wcGpdLockUnderline', 'wcGpdLockLineHeight', 'wcGpdLockLetterSpacing', 'wcGpdLockText',
		'wcGpdLockMove', 'wcGpdLockScale', 'wcGpdLockAspect', 'wcGpdCustomerEditable', 'wcGpdHideFromCustomerLayers',
		'wcGpdCustomerPaletteOnly',
	];

	function paletteColorsForObject( obj, role ) {
		const colorRole = role || 'fill';
		if ( templatePalettes.use_global_colors || productSettings.use_same_colors_entire_template ) {
			return templatePalettes.global_colors && templatePalettes.global_colors.length
				? templatePalettes.global_colors
				: [ '#000000' ];
		}
		const paletteId = colorRole === 'stroke'
			? ( obj && obj.wcGpdStrokePaletteId ? obj.wcGpdStrokePaletteId : 'pal_default' )
			: ( obj && obj.wcGpdPaletteId ? obj.wcGpdPaletteId : 'pal_default' );
		if ( paletteId === 'pal_custom' ) {
			const layerColors = colorRole === 'stroke' ? obj.wcGpdStrokeLayerColors : obj.wcGpdLayerColors;
			return Array.isArray( layerColors ) && layerColors.length
				? layerColors
				: [ '#000000' ];
		}
		const palette = ( templatePalettes.palettes || [] ).find( ( item ) => item.id === paletteId );
		return palette && palette.colors && palette.colors.length ? palette.colors : [ '#000000' ];
	}

	function defaultTextColor( obj ) {
		const colors = paletteColorsForObject( obj );
		return colors[ 0 ] || '#000000';
	}

	function enforcePaletteColor( obj ) {
		if ( ! obj ) {
			return;
		}
		const colors = paletteColorsForObject( obj );
		if ( colors.length === 1 ) {
			obj.set( 'fill', colors[ 0 ] );
		}
	}

	function textColorAllowed( obj ) {
		if ( isCustomerEditableTemplateShape( obj ) ) {
			return !! obj && ! obj.wcGpdLockColor;
		}
		return layerAllowsTool( obj, 'wcGpdLockColor', 'allow_text_color' );
	}

	function textColorUsesPalette( obj ) {
		return !! obj && !! obj.wcGpdCustomerPaletteOnly;
	}

	/**
	 * Show/hide customer tools based on per-product settings.
	 */
	function setToolVisible( el, visible ) {
		if ( el ) {
			el.hidden = ! visible;
		}
	}

	function isCustomerPropLocked( obj, lockProp ) {
		if ( ! obj || ! lockProp ) {
			return true;
		}
		const val = obj[ lockProp ];
		return val === true || val === 1 || val === '1' || val === 'true';
	}

	function isTemplateCustomerLayer( obj ) {
		return isCustomerEditableTemplateText( obj ) || isCustomerEditableTemplateShape( obj );
	}

	function productSettingsAllow( productKey ) {
		if ( ! productKey ) {
			return true;
		}
		const val = productSettings[ productKey ];
		return val !== false && val !== 0 && val !== '0' && val !== 'false';
	}

	function layerAllowsTool( obj, lockProp, productKey ) {
		if ( ! obj || isCustomerPropLocked( obj, lockProp ) ) {
			return false;
		}
		if ( isTemplateCustomerLayer( obj ) ) {
			return true;
		}
		return productSettingsAllow( productKey );
	}

	function getPropRow( controlEl ) {
		if ( ! controlEl ) {
			return null;
		}
		if ( controlEl.classList && controlEl.classList.contains( 'wc-gpd-prop-row' ) ) {
			return controlEl;
		}
		return controlEl.closest ? controlEl.closest( '.wc-gpd-prop-row' ) : null;
	}

	function setPropRowVisible( controlEl, visible ) {
		const row = getPropRow( controlEl );
		if ( ! row ) {
			setToolVisible( controlEl, visible );
			return;
		}
		row.hidden = ! visible;
		row.classList.remove( 'is-disabled' );
		row.querySelectorAll( 'input, select, button, textarea' ).forEach( ( input ) => {
			input.disabled = false;
		} );
	}

	function setControlVisible( controlEl, visible ) {
		if ( controlEl ) {
			controlEl.hidden = ! visible;
			controlEl.disabled = false;
		}
	}

	function setTextContextVisible( visible ) {
		designerRoot.querySelectorAll( '[data-customer-context="text"]' ).forEach( ( row ) => {
			row.hidden = ! visible;
		} );
	}

	function isDesignerOpen() {
		return designerOpen || designerRoot.classList.contains( 'wc-gpd-is-popout' );
	}

	function openCustomerSection( sectionName ) {
		if ( ! sectionName || ! ui.studioPanel ) {
			return;
		}
		ui.studioPanel.querySelectorAll( '.wc-gpd-studio-panel-section' ).forEach( ( panel ) => {
			const isTarget = panel.dataset.section === sectionName;
			panel.hidden = ! isTarget;
			panel.classList.toggle( 'is-active', isTarget );
		} );
		if ( ui.studioNav ) {
			ui.studioNav.querySelectorAll( '.wc-gpd-studio-nav__btn' ).forEach( ( btn ) => {
				btn.classList.toggle( 'is-active', btn.dataset.section === sectionName );
			} );
		}
		if ( ui.drawerTitle && sectionTitles[ sectionName ] ) {
			ui.drawerTitle.textContent = sectionTitles[ sectionName ];
		}
	}

	function initAddMenuCollapsible() {
		document.querySelectorAll( '.wc-gpd-add-menu--collapsible .wc-gpd-add-menu__toggle' ).forEach( ( toggle ) => {
			if ( toggle.dataset.gpdBound ) {
				return;
			}
			toggle.dataset.gpdBound = '1';
			toggle.addEventListener( 'click', () => {
				const group = toggle.closest( '.wc-gpd-add-menu__group' );
				const body = group ? group.querySelector( '.wc-gpd-add-menu__body' ) : null;
				if ( ! group || ! body ) {
					return;
				}
				const open = ! group.classList.contains( 'is-open' );
				group.classList.toggle( 'is-open', open );
				toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				body.hidden = ! open;
			} );
		} );
	}

	function initStudioNav() {
		if ( ! ui.studioNav ) {
			return;
		}
		ui.studioNav.querySelectorAll( '.wc-gpd-studio-nav__btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				if ( btn.hidden ) {
					return;
				}
				openCustomerSection( btn.dataset.section || 'add' );
			} );
		} );
	}

	function openDesigner() {
		if ( ! window.WcGpdPopout || designerOpen ) {
			return;
		}
		window.WcGpdPopout.open( designerRoot, applyResponsiveScale, { fullscreen: true } );
		designerOpen = true;
		applyResponsiveScale();
	}

	function closeDesigner() {
		if ( ! window.WcGpdPopout ) {
			return;
		}
		window.WcGpdPopout.close( designerRoot, applyResponsiveScale );
		designerOpen = false;
	}

	function applyProductToolSettings() {
		setToolVisible( ui.addText, productSettings.allow_free_text !== false );
		if ( ui.navLayers ) {
			ui.navLayers.hidden = productSettings.allow_layers_panel === false;
		}
		if ( activeText ) {
			applyLayerToolSettings( activeText );
		}
	}

	function resetToolRowStates() {
		if ( ! ui.toolsPanel ) {
			return;
		}
		ui.toolsPanel.classList.remove( 'is-disabled' );
		ui.toolsPanel.querySelectorAll( '.wc-gpd-prop-row' ).forEach( ( row ) => {
			row.hidden = false;
			row.classList.remove( 'is-disabled' );
			row.querySelectorAll( 'input, select, button, textarea' ).forEach( ( input ) => {
				input.hidden = false;
				input.disabled = false;
			} );
		} );
	}

	function applyLayerToolSettings( obj ) {
		const isShapeLayer = isCustomerEditableTemplateShape( obj );
		const isText = !! obj && ( isCustomerEditableTemplateText( obj ) || ( isTextLayer( obj ) && ! obj.wcGpdTemplateLayer ) ) && isUsableTextLayer( obj );
		const colorAllowed = textColorAllowed( obj );
		const paletteOnly = colorAllowed && textColorUsesPalette( obj );
		const pickerAllowed = colorAllowed && ! paletteOnly;
		const colorRow = designerRoot.querySelector( '.wc-gpd-control-text-color' );

		const fontAllowed = isText && layerAllowsTool( obj, 'wcGpdLockFont', 'allow_font_family' );
		const sizeAllowed = isText && layerAllowsTool( obj, 'wcGpdLockSize', 'allow_font_size' );
		const boldAllowed = isText && layerAllowsTool( obj, 'wcGpdLockBold', 'allow_bold' );
		const italicAllowed = isText && layerAllowsTool( obj, 'wcGpdLockItalic', 'allow_italic' );
		const underlineAllowed = isText && layerAllowsTool( obj, 'wcGpdLockUnderline', 'allow_underline' );
		const alignAllowed = isText && layerAllowsTool( obj, 'wcGpdLockAlign', 'allow_text_align' );
		const lineHeightAllowed = isText && layerAllowsTool( obj, 'wcGpdLockLineHeight', 'allow_line_height' );
		const letterSpacingAllowed = isText && layerAllowsTool( obj, 'wcGpdLockLetterSpacing', 'allow_letter_spacing' );
		const styleAllowed = boldAllowed || italicAllowed || underlineAllowed;

		setPropRowVisible( ui.fontFamily, fontAllowed );
		setPropRowVisible( ui.fontSize, sizeAllowed );
		setPropRowVisible( ui.lineHeightLabel || ui.lineHeight, lineHeightAllowed );
		setPropRowVisible( ui.letterSpacingLabel || ui.letterSpacing, letterSpacingAllowed );
		setPropRowVisible( ui.alignRow, alignAllowed );

		setControlVisible( ui.boldBtn, boldAllowed );
		setControlVisible( ui.italicBtn, italicAllowed );
		setControlVisible( ui.underlineBtn, underlineAllowed );
		setPropRowVisible( ui.boldBtn, styleAllowed );

		if ( colorRow ) {
			const showColor = colorAllowed && ( isShapeLayer || isText );
			colorRow.hidden = ! showColor;
			colorRow.classList.remove( 'is-disabled' );
		}
		if ( ui.colorSwatches ) {
			ui.colorSwatches.hidden = ! colorAllowed || ( ! paletteOnly && ! isShapeLayer );
		}
		if ( ui.textColor ) {
			ui.textColor.hidden = ! pickerAllowed || ! isText;
			ui.textColor.disabled = false;
		}
		if ( ui.textColor && obj && isText ) {
			ui.textColor.value = obj.fill || defaultTextColor( obj );
		}
	}

	function syncContextNav( active ) {
		if ( ui.navContext ) {
			ui.navContext.hidden = ! active;
		}
	}

	function renderColorSwatches( obj ) {
		if ( ! ui.colorSwatches ) {
			return;
		}
		ui.colorSwatches.innerHTML = '';
		if ( ! textColorAllowed( obj ) ) {
			return;
		}

		const paletteOnly = textColorUsesPalette( obj );
		const isShapeLayer = isCustomerEditableTemplateShape( obj );
		if ( ! paletteOnly && ! isShapeLayer ) {
			return;
		}

		const roles = isShapeLayer
			? [ shapeUsesFill( obj ) ? 'fill' : null, shapeUsesStroke( obj ) ? 'stroke' : null ].filter( Boolean )
			: [ 'fill' ];
		const currentFill = ( obj && obj.fill ) ? String( obj.fill ).toLowerCase() : defaultTextColor( obj ).toLowerCase();

		roles.forEach( ( role ) => {
			const colors = paletteColorsForObject( obj, role );
			colors.forEach( ( color ) => {
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'wc-gpd-color-swatch';
				btn.style.backgroundColor = color;
				btn.title = color;
				btn.setAttribute( 'aria-label', color );
				btn.classList.toggle( 'is-active', ! isShapeLayer && color.toLowerCase() === currentFill );
				btn.addEventListener( 'click', () => {
					if ( ! activeText ) {
						return;
					}
					if ( isShapeLayer ) {
						if ( role === 'stroke' ) {
							applyShapeStrokeColor( activeText, color );
						} else {
							applyShapeFillColor( activeText, color );
						}
					} else {
						activeText.set( 'fill', color );
						if ( ui.textColor ) {
							ui.textColor.value = color;
						}
					}
					renderColorSwatches( activeText );
					canvas.requestRenderAll();
				} );
				ui.colorSwatches.appendChild( btn );
			} );
		} );
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
		const options = config.fontOptions || ( config.fonts || [] ).map( ( family ) => ( {
			family,
			label: family.split( ',' )[ 0 ].replace( /"/g, '' ).trim(),
			css: family,
		} ) );
		options.forEach( ( font ) => {
			const option = document.createElement( 'option' );
			option.value = font.family || font.css;
			option.textContent = font.label || option.value;
			option.style.fontFamily = font.css || font.family;
			ui.fontFamily.appendChild( option );
		} );
	}

	function graphicItemsForSlot( slot ) {
		const libId = slot && slot.wcGpdGraphicLibraryId ? slot.wcGpdGraphicLibraryId : '';
		if ( ! libId || ! graphicLibraries.length ) {
			return graphicLibrary;
		}
		const library = graphicLibraries.find( ( row ) => row.id === libId );
		if ( ! library || ! library.ids || ! library.ids.length ) {
			return graphicLibrary;
		}
		const allowed = new Set( library.ids.map( ( id ) => Number( id ) ) );
		const filtered = graphicLibrary.filter( ( item ) => allowed.has( Number( item.id ) ) );
		return filtered.length ? filtered : graphicLibrary;
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
		const isFullscreen = designerRoot.classList.contains( 'wc-gpd-popout--fullscreen' );
		const canvasArea = designerRoot.querySelector( '.wc-gpd-studio-canvas-area' );
		let maxWidth = Math.max( 1, wrap.clientWidth );
		let maxHeight = PROD_HEIGHT;

		if ( isFullscreen && canvasArea ) {
			maxWidth = Math.max( 1, canvasArea.clientWidth - 32 );
			maxHeight = Math.max( 1, canvasArea.clientHeight - 32 );
		} else if ( isPopout ) {
			const isModal = designerRoot.classList.contains( 'wc-gpd-popout--modal' );
			const popoutWidth = isModal ? designerRoot.clientWidth : window.innerWidth;
			maxWidth = Math.max( 1, Math.min( popoutWidth - 24, 1200 ) );
		}

		displayScale = Math.min( 1, maxWidth / PROD_WIDTH, maxHeight / PROD_HEIGHT );
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
			.filter( ( obj ) => isDesignLayer( obj ) || isPlaceholderLayer( obj ) )
			.map( ( obj ) => serializeDesignObject( obj ) );
	}

	function buildCustomerFields() {
		const view = getActiveViewConfig();
		const placeholders = view ? getPlaceholderObjectsFromView( view ) : [];
		const slots = view ? getGraphicSlotsFromView( view ) : [];
		const showFields = placeholders.length > 0 || ( slots.length > 0 && graphicLibrary.length && productSettings.allow_customer_graphics !== false );
		const detailsEnabled = showFields && productSettings.allow_details_panel !== false;

		if ( ui.navDetails ) {
			ui.navDetails.hidden = ! detailsEnabled;
		}

		if ( ui.placeholderFields ) {
			ui.placeholderFields.innerHTML = '';
			placeholders.forEach( ( def ) => {
				const key = def.wcGpdPlaceholderKey || def.wcGpdUid;
				const label = document.createElement( 'label' );
				label.textContent = def.wcGpdPlaceholderLabel || key;
				label.setAttribute( 'for', `wc-gpd-ph-${ key }` );
				const input = document.createElement( 'input' );
				input.type = 'text';
				input.id = `wc-gpd-ph-${ key }`;
				input.className = 'wc-gpd-placeholder-input';
				input.dataset.placeholderKey = key;
				const canvasObj = findCanvasPlaceholder( key );
				input.value = canvasObj ? ( canvasObj.text || '' ) : ( def.text || '' );
				input.addEventListener( 'input', () => {
					const target = findCanvasPlaceholder( key );
					if ( ! target ) {
						return;
					}
					target.set( 'text', input.value );
					shrinkTextToFit( target );
					canvas.requestRenderAll();
				} );
				ui.placeholderFields.appendChild( label );
				ui.placeholderFields.appendChild( input );
			} );
		}

		if ( ui.graphicPickers ) {
			ui.graphicPickers.innerHTML = '';
			if ( ! slots.length || ! graphicLibrary.length || productSettings.allow_customer_graphics === false ) {
				return;
			}
			slots.forEach( ( slot ) => {
				const uid = slot.wcGpdUid;
				const wrap = document.createElement( 'div' );
				wrap.className = 'wc-gpd-graphic-picker';
				wrap.dataset.slotUid = uid;
				const label = document.createElement( 'label' );
				label.textContent = config.i18n.chooseGraphic || 'Choose graphic';
				wrap.appendChild( label );
				const row = document.createElement( 'div' );
				row.className = 'wc-gpd-graphic-thumb-row';
				graphicItemsForSlot( slot ).forEach( ( item ) => {
					const btn = document.createElement( 'button' );
					btn.type = 'button';
					btn.className = 'wc-gpd-graphic-thumb';
					btn.dataset.attachmentId = String( item.id );
					btn.dataset.slotUid = uid;
					const img = document.createElement( 'img' );
					img.src = item.url;
					img.alt = item.title || '';
					btn.appendChild( img );
					btn.addEventListener( 'click', () => {
						setCustomerGraphic( slot, item );
						row.querySelectorAll( '.wc-gpd-graphic-thumb' ).forEach( ( el ) => {
							el.classList.toggle( 'is-selected', el === btn );
						} );
					} );
					row.appendChild( btn );
				} );
				wrap.appendChild( row );
				ui.graphicPickers.appendChild( wrap );
			} );
		}
	}

	function setCustomerGraphic( slotDef, libraryItem ) {
		if ( ! slotDef || ! libraryItem || ! libraryItem.url ) {
			return;
		}
		const slotUid = slotDef.wcGpdUid;
		canvas.getObjects().slice().forEach( ( obj ) => {
			if ( isCustomerGraphic( obj ) && obj.wcGpdGraphicSlotUid === slotUid ) {
				canvas.remove( obj );
			}
		} );

		fabric.Image.fromURL( libraryItem.url, ( img ) => {
			if ( ! img ) {
				return;
			}
			const slotObj = canvas.getObjects().find( ( o ) => o.wcGpdUid === slotUid );
			const left = slotObj ? slotObj.left : PROD_WIDTH / 2;
			const top = slotObj ? slotObj.top : PROD_HEIGHT / 2;
			const targetW = slotObj ? ( slotObj.width * ( slotObj.scaleX || 1 ) ) : 120;
			const scale = Math.min( targetW / img.width, targetW / img.height );
			img.set( {
				left,
				top,
				originX: 'center',
				originY: 'center',
				scaleX: scale,
				scaleY: scale,
				wcGpdLayerType: 'graphic',
				wcGpdGraphicLayer: true,
				wcGpdGraphicSlotUid: slotUid,
				wcGpdAttachmentId: libraryItem.id,
			} );
			applyGraphicInteractivity( img );
			canvas.add( img );
			canvas.requestRenderAll();
		}, { crossOrigin: 'anonymous' } );
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

	function isPlaceholderLayer( obj ) {
		return !! obj && obj.wcGpdLayerType === 'placeholder';
	}

	function isCustomerEditableTemplateText( obj ) {
		if ( ! obj || obj.wcGpdCustomerEditable === false || ! obj.wcGpdTemplateLayer ) {
			return false;
		}
		if ( obj.wcGpdLayerType === 'placeholder' ) {
			return false;
		}
		if ( obj.wcGpdLayerType === 'text' ) {
			return true;
		}
		const textTypes = [ 'textbox', 'i-text', 'text' ];
		return ! obj.wcGpdLayerType && textTypes.indexOf( obj.type ) >= 0;
	}

	function isFixedTemplateText( obj ) {
		return !! obj && obj.wcGpdLayerType === 'text' && obj.wcGpdTemplateLayer && obj.wcGpdCustomerEditable === false;
	}

	function layerVisibleToCustomer( obj ) {
		return !! obj && ! obj.wcGpdHideFromCustomerLayers && obj.wcGpdCustomerEditable !== false;
	}

	function isTemplateGraphic( obj ) {
		return !! obj && obj.type === 'image' && obj.wcGpdGraphicLayer && obj.wcGpdLayerType === 'graphic';
	}

	function isGraphicSlotMarker( obj ) {
		return !! obj && obj.wcGpdLayerType === 'graphic_slot';
	}

	function isCustomerGraphic( obj ) {
		return !! obj && obj.type === 'image' && obj.wcGpdLayerType === 'graphic' && ! obj.wcGpdTemplateLayer;
	}

	function isTemplateShape( obj ) {
		if ( ! obj || ! obj.wcGpdTemplateLayer || obj.wcGpdBoundingBox ) {
			return false;
		}
		if ( isMockupImage( obj ) || isTemplateGraphic( obj ) || isGraphicSlotMarker( obj ) ) {
			return false;
		}
		const shapeTypes = [ 'rect', 'circle', 'ellipse', 'polygon', 'path', 'polyline', 'group', 'line' ];
		return shapeTypes.indexOf( obj.type ) >= 0;
	}

	function isCustomerEditableTemplateShape( obj ) {
		return isTemplateShape( obj ) && layerVisibleToCustomer( obj );
	}

	function shapeUsesFill( obj ) {
		return !! obj && obj.wcGpdShapeUseFill !== false;
	}

	function shapeUsesStroke( obj ) {
		return !! obj && obj.wcGpdShapeUseStroke !== false;
	}

	function applyShapeFillColor( obj, color ) {
		if ( ! obj ) {
			return;
		}
		function applyToTarget( target ) {
			if ( ! target ) {
				return;
			}
			if ( target.type === 'group' && target.getObjects ) {
				target.getObjects().forEach( applyToTarget );
				return;
			}
			target.set( { fill: color } );
		}
		applyToTarget( obj );
	}

	function applyShapeStrokeColor( obj, color ) {
		if ( ! obj ) {
			return;
		}
		function applyToTarget( target ) {
			if ( ! target ) {
				return;
			}
			if ( target.type === 'group' && target.getObjects ) {
				target.getObjects().forEach( applyToTarget );
				return;
			}
			target.set( { stroke: color } );
		}
		applyToTarget( obj );
	}

	function applyShapeCustomerInteractivity( obj ) {
		if ( ! obj ) {
			return;
		}
		const lockMove = !! obj.wcGpdLockMove;
		const lockScale = !! obj.wcGpdLockScale;
		const visible = layerVisibleToCustomer( obj );
		obj.set( {
			selectable: visible,
			evented: visible,
			hasControls: visible && ! lockScale,
			hasBorders: visible && ! lockScale,
			lockMovementX: lockMove,
			lockMovementY: lockMove,
			lockScalingX: lockScale,
			lockScalingY: lockScale,
		} );
	}

	function isExportTemplateGraphic( obj ) {
		return isTemplateGraphic( obj ) && obj.wcGpdExportGraphic !== false;
	}

	function getTextFitMode( textObj ) {
		if ( ! textObj ) {
			return 'none';
		}
		if ( textObj.wcGpdFitMode ) {
			return textObj.wcGpdFitMode;
		}
		if ( textObj.wcGpdShrinkToFit ) {
			return 'horizontal';
		}
		return 'none';
	}

	function shrinkTextToFit( textObj ) {
		const mode = getTextFitMode( textObj );
		if ( ! textObj || mode === 'none' ) {
			return;
		}
		const baseSize = textObj.wcGpdBaseFontSize || textObj.fontSize || 32;
		if ( ! textObj.wcGpdBaseFontSize ) {
			textObj.wcGpdBaseFontSize = baseSize;
		}
		const maxWidth = textObj.width || 200;
		const maxHeight = textObj.height || 200;
		const minSize = 8;
		let size = baseSize;

		while ( size >= minSize ) {
			textObj.set( 'fontSize', size );
			textObj.setCoords();
			const textWidth = typeof textObj.calcTextWidth === 'function' ? textObj.calcTextWidth() : 0;
			const textHeight = typeof textObj.calcTextHeight === 'function' ? textObj.calcTextHeight() : 0;
			let fits = true;
			if ( ( mode === 'horizontal' || mode === 'both' ) && textWidth > maxWidth ) {
				fits = false;
			}
			if ( ( mode === 'vertical' || mode === 'both' ) && textHeight > maxHeight ) {
				fits = false;
			}
			if ( fits ) {
				break;
			}
			size -= 1;
		}
		textObj.set( 'fontSize', Math.max( minSize, size ) );
		textObj.setCoords();
	}

	function getPlaceholderObjectsFromView( view ) {
		return ( view.objects || [] ).filter( ( obj ) => obj.wcGpdLayerType === 'placeholder' );
	}

	function getGraphicSlotsFromView( view ) {
		return ( view.objects || [] ).filter( ( obj ) => obj.wcGpdLayerType === 'graphic_slot' );
	}

	function templateHasPlaceholders() {
		return templateViews.some( ( view ) => getPlaceholderObjectsFromView( view ).length > 0 );
	}

	function templateHasGraphicSlots() {
		return templateViews.some( ( view ) => getGraphicSlotsFromView( view ).length > 0 );
	}

	function findCanvasPlaceholder( key ) {
		return canvas.getObjects().find( ( obj ) => isPlaceholderLayer( obj ) && obj.wcGpdPlaceholderKey === key );
	}

	function serializeDesignObject( obj ) {
		const data = obj.toObject( DESIGN_SERIALIZE_PROPS );
		if ( isUsableTextLayer( obj ) && ! isPlaceholderLayer( obj ) ) {
			data.wcGpdLayerType = 'text';
			data.wcGpdTextLayer = true;
		}
		if ( isPlaceholderLayer( obj ) ) {
			data.wcGpdLayerType = 'placeholder';
			data.wcGpdTextLayer = true;
		}
		if ( isCustomerGraphic( obj ) ) {
			data.wcGpdLayerType = 'graphic';
			data.wcGpdGraphicLayer = true;
		}
		return data;
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
							objects.forEach( ( obj, index ) => {
								applyTemplateMetadata( obj, designObjects[ index ] );
								if ( isPlaceholderLayer( obj ) ) {
									const existing = findCanvasPlaceholder( obj.wcGpdPlaceholderKey );
									if ( existing && obj.text ) {
										existing.set( 'text', obj.text );
										shrinkTextToFit( existing );
									}
									return;
								}
								if ( isCustomerGraphic( obj ) || ( obj.type === 'image' && obj.wcGpdGraphicLayer ) ) {
									obj.wcGpdLayerType = 'graphic';
									obj.wcGpdGraphicLayer = true;
									applyGraphicInteractivity( obj );
									canvas.add( obj );
									obj.setCoords();
									return;
								}
								obj.wcGpdTextLayer = obj.wcGpdTextLayer || isTextLayer( obj );
								if ( isCustomerEditableTemplateText( obj ) ) {
									applyTextCustomerInteractivity( obj );
								} else if ( isCustomerEditableTemplateShape( obj ) ) {
									applyShapeCustomerInteractivity( obj );
								}
								enforcePaletteColor( obj );
								canvas.add( obj );
								obj.setCoords();
							} );
							purgePhantomLayers();
							buildCustomerFields();
							applyResponsiveScale();
							renderViewSwitcher();
							resolve();
						},
						'fabric'
					);
				};

				if ( ! templateObjects.length ) {
					buildCustomerFields();
					afterTemplate();
					return;
				}

				fabric.util.enlivenObjects(
					templateObjects,
					( objects ) => {
						const savedPlaceholders = ( viewDesigns[ viewId ] || [] ).filter( ( o ) => o.wcGpdLayerType === 'placeholder' );
						objects.forEach( ( obj, index ) => {
							applyTemplateMetadata( obj, templateObjects[ index ] );
							obj.wcGpdTemplateLayer = true;
							const source = templateObjects[ index ] || {};
							logDiagnostic( 'template_object_loaded', {
								index,
								uid: obj.wcGpdUid,
								type: obj.type,
								sourceLocks: pickLockProps( source ),
								canvasLocks: pickLockProps( obj ),
								customerEditable: {
									source: source.wcGpdCustomerEditable,
									canvas: obj.wcGpdCustomerEditable,
								},
								recognizedAs: {
									editableText: isCustomerEditableTemplateText( obj ),
									editableShape: isCustomerEditableTemplateShape( obj ),
									fixedText: isFixedTemplateText( obj ),
								},
							} );
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
							if ( isGraphicSlotMarker( obj ) ) {
								obj.set( {
									selectable: false,
									evented: false,
									hasControls: false,
									hasBorders: false,
									opacity: 0.35,
								} );
								canvas.add( obj );
								return;
							}
							if ( isPlaceholderLayer( obj ) ) {
								const saved = savedPlaceholders.find( ( o ) => o.wcGpdPlaceholderKey === obj.wcGpdPlaceholderKey );
								if ( saved && saved.text ) {
									obj.set( 'text', saved.text );
								}
								if ( ! obj.wcGpdBaseFontSize ) {
									obj.wcGpdBaseFontSize = obj.fontSize || 32;
								}
								shrinkTextToFit( obj );
								obj.set( {
									selectable: false,
									evented: false,
									hasControls: false,
									hasBorders: false,
									editable: false,
								} );
								canvas.add( obj );
								return;
							}
							if ( isCustomerEditableTemplateText( obj ) ) {
								if ( ! obj.wcGpdBaseFontSize ) {
									obj.wcGpdBaseFontSize = obj.fontSize || 32;
								}
								shrinkTextToFit( obj );
								applyTextCustomerInteractivity( obj );
								canvas.add( obj );
								return;
							}
							if ( isCustomerEditableTemplateShape( obj ) ) {
								applyShapeCustomerInteractivity( obj );
								canvas.add( obj );
								return;
							}
							if ( isTemplateGraphic( obj ) && layerVisibleToCustomer( obj ) && ( ! obj.wcGpdLockMove || ! obj.wcGpdLockScale ) ) {
								applyGraphicInteractivity( obj );
								canvas.add( obj );
								return;
							}
							if ( isFixedTemplateText( obj ) || isTemplateGraphic( obj ) ) {
								obj.set( {
									selectable: false,
									evented: false,
									hasControls: false,
									hasBorders: false,
									editable: false,
								} );
								canvas.add( obj );
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
						buildCustomerFields();
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
		if ( ! obj ) {
			return false;
		}
		if ( isPlaceholderLayer( obj ) || isFixedTemplateText( obj ) || isTemplateGraphic( obj ) || isGraphicSlotMarker( obj ) ) {
			return true;
		}
		return obj.wcGpdTemplateLayer || obj.wcGpdOutlineLayer || obj.wcGpdBoundingBox || isMockupImage( obj );
	}

	function isTextLayer( obj ) {
		if ( ! obj || obj.wcGpdBackground ) {
			return false;
		}
		if ( isPlaceholderLayer( obj ) || isFixedTemplateText( obj ) || isCustomerEditableTemplateText( obj ) ) {
			return true;
		}
		if ( isTemplateLayer( obj ) ) {
			return false;
		}
		const type = obj.type || '';
		return !! obj.wcGpdTextLayer || type === 'i-text' || type === 'text' || type === 'textbox';
	}

	function isDesignLayer( obj ) {
		if ( ! obj || obj.wcGpdBackground ) {
			return false;
		}
		if ( isPlaceholderLayer( obj ) || isCustomerGraphic( obj ) ) {
			return true;
		}
		if ( isTemplateLayer( obj ) ) {
			return false;
		}
		if ( isUsableTextLayer( obj ) ) {
			return true;
		}
		const type = obj.type || '';
		return ( type === 'rect' || type === 'circle' || type === 'ellipse' ) && obj.wcGpdLayerType === 'shape';
	}

	function applyTextCustomerInteractivity( obj ) {
		if ( ! obj || isPlaceholderLayer( obj ) ) {
			return;
		}
		const lockMove = !! obj.wcGpdLockMove;
		const lockScale = !! obj.wcGpdLockScale;
		const lockText = !! obj.wcGpdLockText;
		const visible = layerVisibleToCustomer( obj );
		obj.set( {
			selectable: visible,
			evented: visible,
			hasControls: visible && ! lockScale,
			hasBorders: visible && ! lockScale,
			lockMovementX: lockMove,
			lockMovementY: lockMove,
			lockScalingX: lockScale,
			lockScalingY: lockScale,
			editable: visible && ! lockText,
		} );
	}

	function applyGraphicInteractivity( obj ) {
		if ( obj.wcGpdGraphicSlotUid ) {
			obj.set( {
				selectable: true,
				evented: true,
				hasControls: true,
				hasBorders: true,
				lockMovementX: false,
				lockMovementY: false,
				lockScalingX: false,
				lockScalingY: false,
				lockUniScaling: !! obj.wcGpdLockAspect,
			} );
			return;
		}
		const lockMove = !! obj.wcGpdLockMove;
		const lockScale = !! obj.wcGpdLockScale;
		obj.set( {
			selectable: ! lockMove,
			evented: ! lockMove,
			hasControls: ! lockScale,
			hasBorders: ! lockScale,
			lockMovementX: lockMove,
			lockMovementY: lockMove,
			lockScalingX: lockScale,
			lockScalingY: lockScale,
			lockUniScaling: ! lockScale && !! obj.wcGpdLockAspect,
		} );
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
		syncContextNav( null );
		if ( ui.contextEmpty ) {
			ui.contextEmpty.hidden = false;
		}
		if ( ui.contextPane ) {
			ui.contextPane.hidden = true;
		}
		syncLayersList();
		canvas.requestRenderAll();
	}

	/**
	 * @returns {fabric.Object[]}
	 */
	function getUsableTextLayers() {
		return canvas.getObjects().filter( ( o ) => isUsableTextLayer( o ) );
	}

	function getCustomerLayers() {
		return canvas.getObjects().filter( ( obj ) => {
			if ( ! layerVisibleToCustomer( obj ) ) {
				return false;
			}
			if ( isPlaceholderLayer( obj ) ) {
				return false;
			}
			if ( isCustomerEditableTemplateText( obj ) && isUsableTextLayer( obj ) ) {
				return true;
			}
			if ( isCustomerEditableTemplateShape( obj ) ) {
				return true;
			}
			if ( isTextLayer( obj ) && ! obj.wcGpdTemplateLayer && isUsableTextLayer( obj ) ) {
				return true;
			}
			if ( isCustomerGraphic( obj ) ) {
				return true;
			}
			return false;
		} );
	}

	function isCustomerSelectableLayer( obj ) {
		if ( ! layerVisibleToCustomer( obj ) ) {
			return false;
		}
		if ( isPlaceholderLayer( obj ) ) {
			return false;
		}
		if ( isCustomerEditableTemplateText( obj ) && isUsableTextLayer( obj ) ) {
			return true;
		}
		if ( isCustomerEditableTemplateShape( obj ) ) {
			return true;
		}
		if ( isTextLayer( obj ) && ! obj.wcGpdTemplateLayer && isUsableTextLayer( obj ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @returns {boolean}
	 */
	function templateIsPredesignedOnly() {
		const hasContent = templateViews.some( ( view ) => {
			const objs = view.objects || [];
			return objs.some( ( o ) => o.wcGpdLayerType === 'text' || o.wcGpdLayerType === 'graphic' );
		} );
		if ( ! hasContent ) {
			return false;
		}
		return ! templateHasPlaceholders() && ! templateHasGraphicSlots();
	}

	function hasTextLayer() {
		persistCurrentViewDesign();
		if ( templateIsPredesignedOnly() ) {
			return true;
		}
		if ( templateHasPlaceholders() ) {
			return templateViews.some( ( view ) => {
				const saved = viewDesigns[ view.id ] || [];
				const placeholderSaved = saved.filter( ( obj ) => obj.wcGpdLayerType === 'placeholder' );
				if ( placeholderSaved.some( ( obj ) => ( obj.text || '' ).trim().length > 0 ) ) {
					return true;
				}
				return canvas.getObjects().some( ( obj ) => isPlaceholderLayer( obj ) && ( obj.text || '' ).trim().length > 0 );
			} );
		}
		const current = getUsableTextLayers().filter( ( obj ) => ! isPlaceholderLayer( obj ) ).length > 0;
		if ( current ) {
			return true;
		}
		return templateViews.some( ( view ) => {
			const objects = viewDesigns[ view.id ] || [];
			return objects.some( ( obj ) => {
				if ( obj.wcGpdLayerType === 'graphic' ) {
					return true;
				}
				const text = typeof obj.text === 'string' ? obj.text.trim() : '';
				return text.length > 0 && obj.wcGpdLayerType !== 'placeholder';
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

		const layers = getCustomerLayers();
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
			let label = ( obj.text && String( obj.text ).trim() )
				? String( obj.text ).trim().slice( 0, 40 )
				: '';
			if ( ! label && obj.wcGpdLayerLabel ) {
				label = obj.wcGpdLayerLabel;
			}
			if ( ! label && isCustomerEditableTemplateShape( obj ) ) {
				label = obj.type === 'group' ? ( config.i18n.layerIcon || 'Icon' ) : ( config.i18n.layerShape || 'Shape' );
			}
			if ( ! label ) {
				label = ( config.i18n.layerText || 'Text layer' ) + ' ' + ( index + 1 );
			}

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
				openCustomerSection( 'context' );
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
		const isText = !! obj && ( isCustomerEditableTemplateText( obj ) || ( isTextLayer( obj ) && ! obj.wcGpdTemplateLayer ) ) && isUsableTextLayer( obj );
		const isShapeLayer = !! obj && isCustomerEditableTemplateShape( obj );
		const enabled = isText || isShapeLayer;

		if ( ui.toolsPanel ) {
			ui.toolsPanel.classList.remove( 'is-disabled' );
		}
		if ( ui.contextEmpty ) {
			ui.contextEmpty.hidden = enabled;
		}
		if ( ui.contextPane ) {
			ui.contextPane.hidden = ! enabled;
		}
		setTextContextVisible( isText );

		if ( ! enabled ) {
			renderColorSwatches( null );
			resetToolRowStates();
			return;
		}

		if ( ui.contextLayerName ) {
			let label = config.i18n.layerText || 'Layer';
			if ( isText && obj.text && String( obj.text ).trim() ) {
				label = String( obj.text ).trim().slice( 0, 48 );
			} else if ( obj.wcGpdLayerLabel ) {
				label = obj.wcGpdLayerLabel;
			} else if ( isShapeLayer ) {
				label = obj.type === 'group' ? ( config.i18n.layerIcon || 'Icon' ) : ( config.i18n.layerShape || 'Shape' );
			}
			ui.contextLayerName.textContent = label;
		}

		syncContextNav( obj );
		openCustomerSection( 'context' );

		if ( isText ) {
			if ( ui.fontFamily ) {
				ui.fontFamily.value = obj.fontFamily || DEFAULT_FONT;
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
			enforcePaletteColor( obj );
		}

		if ( ui.textColor && isText ) {
			ui.textColor.value = obj.fill || defaultTextColor( obj );
		}

		renderColorSwatches( obj );
		applyLayerToolSettings( obj );
		logDiagnostic( 'toolbar_synced', buildLayerPermissionReport( obj ) );
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
			fontFamily: DEFAULT_FONT,
			fontSize: 32,
			fill: defaultTextColor( null ),
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
			o.visible = isDesignLayer( o ) || isFixedTemplateText( o ) || isExportTemplateGraphic( o );
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
			log.warn( 'Add to cart blocked: no design content' );
			const message = templateHasPlaceholders()
				? ( config.i18n.placeholderRequired || config.i18n.layerRequired )
				: config.i18n.layerRequired;
			window.alert( message );
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

					objects.forEach( ( obj, index ) => {
						applyTemplateMetadata( obj, data.objects[ index ] );
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
		const cartForm = getCartForm();
		if ( cartForm ) {
			const inForm = cartForm.querySelector(
				'button.single_add_to_cart_button, button[name="add-to-cart"], input[name="add-to-cart"], .single_add_to_cart_button'
			);
			if ( inForm ) {
				return inForm;
			}
		}
		return document.querySelector(
			'.single-product form.cart button.single_add_to_cart_button, .single-product form.cart .single_add_to_cart_button'
		);
	}

	/**
	 * POST the cart form after SVG is ready.
	 */
	function submitCartForm() {
		const cartForm = getCartForm();
		if ( ! cartForm ) {
			window.alert( config.i18n?.exportError || 'Could not add to cart.' );
			return;
		}
		submitApproved = true;
		const btn = getAddToCartButton();

		[ btn, ui.designerAtc ].forEach( ( el ) => {
			if ( el ) {
				el.classList.add( 'wc-gpd-submitting' );
				el.setAttribute( 'aria-busy', 'true' );
			}
		} );

		log.info( 'Submitting add to cart with design SVG' );

		if ( typeof cartForm.requestSubmit === 'function' ) {
			cartForm.requestSubmit( btn || undefined );
			return;
		}

		// Fallback: temporary submit control (fires submit event unlike form.submit()).
		const fallback = document.createElement( 'input' );
		fallback.type = 'submit';
		fallback.hidden = true;
		fallback.setAttribute( 'aria-hidden', 'true' );
		cartForm.appendChild( fallback );
		fallback.click();
		fallback.remove();
	}

	/**
	 * Intercept add to cart (themes often use click/AJAX instead of form submit).
	 */
	function bindAddToCart() {
		const onCartIntent = ( event ) => {
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

		if ( ui.designerAtc ) {
			ui.designerAtc.addEventListener( 'click', onCartIntent );
		}

		const cartForm = getCartForm();
		if ( cartForm ) {
			cartForm.addEventListener( 'submit', ( event ) => {
				if ( submitApproved ) {
					return;
				}
				if ( isStartDesigningMode && ! isDesignerOpen() ) {
					event.preventDefault();
					openDesigner();
					return;
				}
				onCartIntent( event );
			} );
		}

		document.addEventListener(
			'click',
			( event ) => {
				if ( submitApproved ) {
					return;
				}
				const btn = event.target.closest(
					'button.single_add_to_cart_button, button[name="add-to-cart"], input[name="add-to-cart"], .wc-gpd-fallback-start'
				);
				if ( ! btn || btn.id === 'wc-gpd-designer-atc' ) {
					return;
				}
				const activeForm = getCartForm();
				const inCartForm = activeForm && activeForm.contains( btn );
				const isFallback = btn.classList.contains( 'wc-gpd-fallback-start' );
				if ( ! inCartForm && ! isFallback ) {
					return;
				}
				if ( isStartDesigningMode && ! isDesignerOpen() ) {
					event.preventDefault();
					event.stopPropagation();
					if ( typeof event.stopImmediatePropagation === 'function' ) {
						event.stopImmediatePropagation();
					}
					openDesigner();
					return;
				}
				onCartIntent( event );
			},
			true
		);
	}

	function applyCtaLabel() {
		const label = config.startDesigningLabel || config.i18n?.startDesigning || 'Start designing';
		const btn = getAddToCartButton();
		if ( btn && isStartDesigningMode && ! config.isEditing && ! config.orderEdit ) {
			btn.textContent = label;
		}
	}

	function initFallbackCta() {
		const wrap = document.getElementById( 'wc-gpd-fallback-cta' );
		const fallback = document.getElementById( 'wc-gpd-fallback-start' );
		if ( ! wrap || ! fallback ) {
			return;
		}
		if ( ! getAddToCartButton() ) {
			wrap.hidden = false;
		}
	}

	// Events
	canvas.on( 'selection:created', ( e ) => {
		const target = e.selected?.[ 0 ] || e.target;
		if ( isCustomerSelectableLayer( target ) ) {
			syncToolbar( target );
			syncLayersList();
		} else {
			discardSelection();
		}
	} );

	canvas.on( 'selection:updated', ( e ) => {
		const target = e.selected?.[ 0 ] || e.target;
		if ( isCustomerSelectableLayer( target ) ) {
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
			if ( ! activeText || ui.toolsPanel?.classList.contains( 'is-disabled' ) ) {
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
			if ( ! activeText || ! textColorAllowed( activeText ) ) {
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

	designerRoot.addEventListener( 'wc-gpd-popout-closed', () => {
		designerOpen = false;
		applyResponsiveScale();
	} );

	window.addEventListener( 'resize', () => {
		applyResponsiveScale();
	} );

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

	initFontSelect();
	initAddMenuCollapsible();
	initStudioNav();
	applyProductToolSettings();
	bindAddToCart();
	openCustomerSection( 'add' );

	if ( ui.copyDiagnostics ) {
		ui.copyDiagnostics.addEventListener( 'click', () => {
			copyDiagnosticsReport()
				.then( () => {
					window.alert( config.i18n?.diagnosticsCopied || 'Diagnostics copied to clipboard.' );
				} )
				.catch( () => {
					window.alert( config.i18n?.diagnosticsCopyFailed || 'Could not copy diagnostics.' );
					log.info( 'Diagnostics report', buildDiagnosticsReport() );
				} );
		} );
	}

	if ( diagnosticsEnabled ) {
		window.wcGpdGetDiagnostics = buildDiagnosticsReport;
		window.wcGpdCopyDiagnostics = copyDiagnosticsReport;
		logDiagnostic( 'designer_boot', {
			pluginVersion: config.pluginVersion,
			productId: config.productId,
			isSampleProduct: config.isSampleProduct,
			templateViews: summarizeTemplateViews(),
		} );
	}

	if ( config.isEditing || config.orderEdit || config.autoOpenDesigner ) {
		setTimeout( () => openDesigner(), 120 );
	}

	applyCtaLabel();
	initFallbackCta();

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
						if ( ! config.orderEdit && ( templateHasPlaceholders() || templateHasGraphicSlots() ) ) {
							buildCustomerFields();
						}
					} );
				} else if ( ! config.orderEdit && ( templateHasPlaceholders() || templateHasGraphicSlots() ) ) {
					buildCustomerFields();
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
