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
		global_palette_id: 'pal_custom',
		global_colors: [ '#000000' ],
	};

	const log = window.wcGpdDebug || { setEnabled() {}, debug() {}, info() {}, warn() {}, error() {} };
	log.setEnabled( !! config.debug );
	log.info( 'Designer initializing', { width: config.canvasWidth, height: config.canvasHeight } );

	const TEMPLATE_METADATA_PROPS = [
		'wcGpdUid', 'wcGpdLayerType', 'wcGpdLayerLabel', 'wcGpdTemplateLayer', 'wcGpdOutlineLayer', 'wcGpdBoundingBox', 'wcGpdBboxRole',
		'wcGpdMockupImage', 'wcGpdMockupVisible', 'wcGpdGraphicLayer', 'wcGpdGraphicSlot', 'wcGpdGraphicLibraryId',
		'wcGpdExportGraphic', 'wcGpdCustomerMovable', 'wcGpdCustomerResizable', 'wcGpdPlaceholderLabel', 'wcGpdPlaceholderKey',
		'wcGpdShrinkToFit', 'wcGpdFitMode', 'wcGpdPaletteId', 'wcGpdLayerColors', 'wcGpdStrokePaletteId', 'wcGpdStrokeLayerColors',
		'wcGpdShapeUseFill', 'wcGpdShapeUseStroke', 'wcGpdLockFont', 'wcGpdLockSize', 'wcGpdLockColor', 'wcGpdLockBold',
		'wcGpdLockItalic', 'wcGpdLockAlign', 'wcGpdLockUnderline', 'wcGpdLockLineHeight', 'wcGpdLockLetterSpacing', 'wcGpdLockText',
		'wcGpdLockMove', 'wcGpdLockScale', 'wcGpdLockAspect', 'wcGpdCustomerEditable', 'wcGpdHideFromCustomerLayers',
		'wcGpdCustomerPaletteOnly', 'wcGpdTextLayer', 'wcGpdAttachmentId', 'wcGpdGraphicSlotUid', 'wcGpdGraphicLayer',
		'wcGpdReplaceable', 'wcGpdReplaceableKind', 'wcGpdReplaceableUid',
		'wcGpdCustomerUpload', 'wcGpdGraphicVector', 'wcGpdGraphicColorSlots', 'wcGpdGraphicColors',
		'wcGpdGraphicFillSlot', 'wcGpdGraphicStrokeSlot',
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
		addShapes: document.getElementById( 'wc-gpd-add-shapes' ),
		addImage: document.getElementById( 'wc-gpd-add-image' ),
		addImageFile: document.getElementById( 'wc-gpd-add-image-file' ),
		addGraphicLibrary: document.getElementById( 'wc-gpd-add-graphic-library' ),
		addPhotoLibrary: document.getElementById( 'wc-gpd-add-photo-library' ),
		iconSearch: document.getElementById( 'wc-gpd-customer-icon-search' ),
		iconSearchBtn: document.getElementById( 'wc-gpd-customer-icon-search-btn' ),
		iconResults: document.getElementById( 'wc-gpd-customer-icon-results' ),
		iconStatus: document.getElementById( 'wc-gpd-customer-icon-status' ),
		iconLoadMoreWrap: document.getElementById( 'wc-gpd-customer-icon-load-more-wrap' ),
		iconLoadMoreBtn: document.getElementById( 'wc-gpd-customer-icon-load-more' ),
		layersEmptyHint: document.getElementById( 'wc-gpd-layers-empty-hint' ),
		addMenu: document.getElementById( 'wc-gpd-add-menu' ),
		addEmpty: document.getElementById( 'wc-gpd-add-empty' ),
		navAdd: document.getElementById( 'wc-gpd-nav-add' ),
		toolsPanel: document.getElementById( 'wc-gpd-tools-panel' ),
		contextEmpty: document.getElementById( 'wc-gpd-context-empty' ),
		contextPane: document.getElementById( 'wc-gpd-context-pane' ),
		contextLayerName: document.getElementById( 'wc-gpd-context-layer-name' ),
		contextGraphicHint: document.getElementById( 'wc-gpd-context-graphic-hint' ),
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
		placeholderEditRow: document.getElementById( 'wc-gpd-placeholder-edit-row' ),
		placeholderEditLabel: document.getElementById( 'wc-gpd-placeholder-edit-label' ),
		placeholderEditInput: document.getElementById( 'wc-gpd-placeholder-edit-input' ),
		replaceableModal: document.getElementById( 'wc-gpd-replaceable-modal' ),
		replaceableModalClose: document.getElementById( 'wc-gpd-replaceable-modal-close' ),
		replaceableModalGrid: document.getElementById( 'wc-gpd-replaceable-modal-grid' ),
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
		const isShapeLayer = isCustomerEditableShape( obj );
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
			productSettingsNote: 'Merged WooCommerce product settings over template defaults. Template layer locks still apply per layer.',
			demoContentSeeded: ( config.templateViews || [] ).some( ( view ) =>
				( view.objects || [] ).some( ( obj ) => obj.wcGpdUid === 'gpd-demo-text-all' )
			),
			templateViews: summarizeTemplateViews(),
			canvasLayers: canvas.getObjects().map( ( obj ) => buildLayerPermissionReport( obj ) ),
			activeLayer: buildLayerPermissionReport( activeText ),
			addMenu: {
				allowText: productAllowsAdd( 'text' ),
				allowShape: productAllowsAdd( 'shape' ),
				allowGraphic: productAllowsAdd( 'graphic' ),
				allowImage: productAllowsAdd( 'image' ),
				allowIcon: productAllowsAdd( 'icon' ),
				graphicItems: resolveAddGraphicItems().length,
				iconSlugs: Array.isArray( config.iconSlugs ) ? config.iconSlugs.length : 0,
				iconBaseUrl: bootstrapIcons.iconBaseUrl || null,
				graphicReady: isAddGroupReady( 'graphic' ),
				iconReady: isAddGroupReady( 'icon' ),
			},
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
	const photoLibrary = Array.isArray( config.photoLibrary ) ? config.photoLibrary : [];
	const graphicLibraries = Array.isArray( config.graphicLibraries ) ? config.graphicLibraries : [];
	const bootstrapIcons = config.bootstrapIcons || {};
	const demoGraphics = Array.isArray( config.demoGraphics ) ? config.demoGraphics : [];

	function resolveAddPhotoItems() {
		const items = [];
		const seen = new Set();
		photoLibrary.forEach( ( item ) => {
			if ( ! item || ! item.url || seen.has( item.url ) ) {
				return;
			}
			seen.add( item.url );
			items.push( item );
		} );
		return items;
	}

	function resolveAddGraphicItems() {
		const items = [];
		const seen = new Set();
		function pushItem( item ) {
			if ( ! item || ! item.url || seen.has( item.url ) ) {
				return;
			}
			seen.add( item.url );
			items.push( item );
		}
		graphicLibrary.forEach( pushItem );
		demoGraphics.forEach( pushItem );
		return items;
	}

	function iconSearchLibraryParam() {
		if ( ! bootstrapIcons.restrictIcons || ! Array.isArray( bootstrapIcons.iconLibraryIds ) || ! bootstrapIcons.iconLibraryIds.length ) {
			return '';
		}
		return bootstrapIcons.iconLibraryIds.join( ',' );
	}

	function isAddGroupEnabled( key ) {
		return productAllowsAdd( key );
	}

	function isAddGroupReady( key ) {
		if ( key === 'graphic' ) {
			return isAddGroupEnabled( 'graphic' ) && resolveAddGraphicItems().length > 0;
		}
		if ( key === 'image' ) {
			return isAddGroupEnabled( 'image' );
		}
		if ( key === 'icon' ) {
			return isAddGroupEnabled( 'icon' ) && !! bootstrapIcons.iconBaseUrl;
		}
		return isAddGroupEnabled( key );
	}

	const DEFAULT_FONT = config.defaultFont || ( config.fonts && config.fonts[ 0 ] ) || '"Times New Roman", Times, serif';
	const DESIGN_SERIALIZE_PROPS = [
		'wcGpdTextLayer', 'wcGpdLayerType', 'wcGpdPlaceholderKey', 'wcGpdPlaceholderLabel', 'wcGpdShrinkToFit', 'wcGpdFitMode', 'wcGpdPaletteId', 'wcGpdLayerColors',
		'wcGpdStrokePaletteId', 'wcGpdStrokeLayerColors', 'wcGpdShapeUseFill', 'wcGpdShapeUseStroke',
		'wcGpdGraphicLayer', 'wcGpdGraphicSlotUid', 'wcGpdAttachmentId', 'wcGpdCustomerUpload', 'wcGpdLayerLabel', 'wcGpdBboxRole',
		'wcGpdReplaceable', 'wcGpdReplaceableKind', 'wcGpdReplaceableUid',
		'wcGpdLockFont', 'wcGpdLockSize', 'wcGpdLockColor', 'wcGpdLockBold', 'wcGpdLockItalic', 'wcGpdLockAlign',
		'wcGpdLockUnderline', 'wcGpdLockLineHeight', 'wcGpdLockLetterSpacing', 'wcGpdLockText',
		'wcGpdLockMove', 'wcGpdLockScale', 'wcGpdLockAspect', 'wcGpdCustomerEditable', 'wcGpdHideFromCustomerLayers',
		'wcGpdCustomerPaletteOnly', 'wcGpdGraphicVector', 'wcGpdGraphicColorSlots', 'wcGpdGraphicColors',
		'wcGpdGraphicFillSlot', 'wcGpdGraphicStrokeSlot',
	];

	function paletteColorsById( paletteId ) {
		if ( ! paletteId || paletteId === 'pal_custom' ) {
			return [ '#000000' ];
		}
		const palette = ( templatePalettes.palettes || [] ).find( ( item ) => item.id === paletteId );
		return palette && palette.colors && palette.colors.length ? palette.colors : [ '#000000' ];
	}

	function resolveTemplateGlobalColors() {
		if ( ! templatePalettes.use_global_colors && ! productSettings.use_same_colors_entire_template ) {
			return null;
		}
		const globalPaletteId = templatePalettes.global_palette_id || 'pal_custom';
		if ( globalPaletteId && globalPaletteId !== 'pal_custom' ) {
			return paletteColorsById( globalPaletteId );
		}
		return templatePalettes.global_colors && templatePalettes.global_colors.length
			? templatePalettes.global_colors
			: [ '#000000' ];
	}

	function customerAddType( obj ) {
		if ( isCustomerAddedShape( obj ) ) {
			return 'shape';
		}
		if ( isCustomerAddedIcon( obj ) ) {
			return 'icon';
		}
		if ( isRecolorableCustomerGraphic( obj ) ) {
			return 'graphic';
		}
		return null;
	}

	function customerAddPaletteId( addType ) {
		if ( ! addType ) {
			return 'pal_default';
		}
		const key = 'customer_add_' + addType + '_palette_id';
		const configured = productSettings[ key ];
		return configured || 'pal_default';
	}

	function customerAddPaletteOnly( addType ) {
		if ( ! addType ) {
			return false;
		}
		if ( resolveTemplateGlobalColors() ) {
			return true;
		}
		return !! productSettings[ 'customer_add_' + addType + '_palette_only' ];
	}

	function customerAddedPaletteRestricted( obj ) {
		const addType = customerAddType( obj );
		return addType ? customerAddPaletteOnly( addType ) : false;
	}

	function paletteColorsForCustomerAdd( obj ) {
		const globalColors = resolveTemplateGlobalColors();
		if ( globalColors ) {
			return globalColors;
		}
		const addType = customerAddType( obj );
		return paletteColorsById( customerAddPaletteId( addType ) );
	}

	function paletteColorsForObject( obj, role ) {
		const addType = customerAddType( obj );
		if ( addType ) {
			return paletteColorsForCustomerAdd( obj );
		}
		const colorRole = role || 'fill';
		const globalColors = resolveTemplateGlobalColors();
		if ( globalColors ) {
			return globalColors;
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
		return paletteColorsById( paletteId );
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
		if ( isCustomerAddedShape( obj ) ) {
			return productSettingsAllow( 'allow_shape_color' );
		}
		if ( isCustomerAddedIcon( obj ) ) {
			return productSettingsAllow( 'allow_icon_color' );
		}
		return layerAllowsTool( obj, 'wcGpdLockColor', 'allow_text_color' );
	}

	function shapeColorAllowed( obj ) {
		if ( isCustomerEditableTemplateShape( obj ) ) {
			return !! obj && ! obj.wcGpdLockColor;
		}
		if ( isCustomerAddedShape( obj ) ) {
			return productSettingsAllow( 'allow_shape_color' );
		}
		return false;
	}

	function iconColorAllowed( obj ) {
		if ( isCustomerAddedIcon( obj ) ) {
			return productSettingsAllow( 'allow_icon_color' );
		}
		return false;
	}

	function customerAddedColorAllowed( obj ) {
		return shapeColorAllowed( obj ) || iconColorAllowed( obj ) || ( textColorAllowed( obj ) && ! isCustomerAddedShape( obj ) && ! isCustomerAddedIcon( obj ) );
	}

	function textColorUsesPalette( obj ) {
		return !! obj && !! obj.wcGpdCustomerPaletteOnly;
	}

	function layerColorPaletteRestricted( obj ) {
		if ( ! obj ) {
			return false;
		}
		if ( customerAddType( obj ) ) {
			return customerAddedPaletteRestricted( obj );
		}
		return !! obj.wcGpdCustomerPaletteOnly;
	}

	function templateLayerHasPalette( obj ) {
		if ( ! obj || customerAddType( obj ) ) {
			return false;
		}
		if ( resolveTemplateGlobalColors() ) {
			return true;
		}
		return !!( obj.wcGpdPaletteId || obj.wcGpdStrokePaletteId );
	}

	function shouldShowInlinePaletteSwatches( obj ) {
		if ( ! obj ) {
			return false;
		}
		const addType = customerAddType( obj );
		if ( addType ) {
			if ( addType === 'shape' && ! shapeColorAllowed( obj ) ) {
				return false;
			}
			if ( addType === 'icon' && ! iconColorAllowed( obj ) ) {
				return false;
			}
			if ( addType === 'graphic' && ! graphicColorAllowed( obj ) ) {
				return false;
			}
			return true;
		}
		if ( layerColorPaletteRestricted( obj ) ) {
			return textColorAllowed( obj ) || shapeColorAllowed( obj );
		}
		return isTextLayer( obj ) && textColorAllowed( obj ) && templateLayerHasPalette( obj );
	}

	function shouldShowDropdownColorPicker( obj ) {
		if ( ! obj ) {
			return false;
		}
		if ( customerAddType( obj ) ) {
			return false;
		}
		if ( isCustomerAddedShape( obj ) ) {
			return shapeColorAllowed( obj ) && ! customerAddedPaletteRestricted( obj );
		}
		if ( isCustomerAddedIcon( obj ) ) {
			return iconColorAllowed( obj ) && ! customerAddedPaletteRestricted( obj );
		}
		if ( isCustomerEditableTemplateShape( obj ) && shapeColorAllowed( obj ) ) {
			return ! layerColorPaletteRestricted( obj );
		}
		return false;
	}

	function shouldShowInlineCustomColorPicker( obj ) {
		if ( ! obj || customerAddedPaletteRestricted( obj ) ) {
			return false;
		}
		const addType = customerAddType( obj );
		if ( ! addType || addType === 'graphic' ) {
			return false;
		}
		if ( addType === 'shape' ) {
			return shapeColorAllowed( obj );
		}
		if ( addType === 'icon' ) {
			return iconColorAllowed( obj );
		}
		return false;
	}

	const MAX_GRAPHIC_COLOR_SLOTS = 4;

	function isMeaningfulSvgColor( value ) {
		if ( ! value || typeof value !== 'string' ) {
			return false;
		}
		const normalized = value.trim().toLowerCase();
		return normalized && normalized !== 'none' && normalized !== 'transparent'
			&& ! normalized.startsWith( 'url(' ) && normalized !== 'currentcolor';
	}

	function normalizeHexColor( color ) {
		if ( ! isMeaningfulSvgColor( color ) ) {
			return '';
		}
		try {
			const fabricColor = new fabric.Color( String( color ).trim() );
			return '#' + fabricColor.toHex().slice( 0, 7 ).toLowerCase();
		} catch ( error ) {
			const value = String( color ).trim().toLowerCase();
			if ( value.startsWith( '#' ) && value.length === 4 ) {
				return '#' + value[ 1 ] + value[ 1 ] + value[ 2 ] + value[ 2 ] + value[ 3 ] + value[ 3 ];
			}
			return value;
		}
	}

	function isSvgResourceUrl( url, item ) {
		if ( item && item.mime && String( item.mime ).toLowerCase().indexOf( 'svg' ) >= 0 ) {
			return true;
		}
		if ( ! url ) {
			return false;
		}
		const clean = String( url ).split( '?' )[ 0 ].split( '#' )[ 0 ].toLowerCase();
		return clean.endsWith( '.svg' );
	}

	function extractSvgColorSlots( svgText, maxSlots ) {
		const limit = maxSlots || MAX_GRAPHIC_COLOR_SLOTS;
		const seen = new Set();
		const slots = [];
		function pushColor( raw ) {
			const normalized = normalizeHexColor( raw );
			if ( ! normalized || seen.has( normalized ) ) {
				return;
			}
			seen.add( normalized );
			slots.push( normalized );
		}
		try {
			const doc = new DOMParser().parseFromString( svgText, 'image/svg+xml' );
			doc.querySelectorAll( '[fill],[stroke]' ).forEach( ( el ) => {
				[ 'fill', 'stroke' ].forEach( ( attr ) => {
					const value = el.getAttribute( attr );
					if ( isMeaningfulSvgColor( value ) ) {
						pushColor( value );
					}
				} );
				const style = el.getAttribute( 'style' );
				if ( style ) {
					style.split( ';' ).forEach( ( rule ) => {
						const match = rule.match( /^\s*(fill|stroke)\s*:\s*(.+)$/i );
						if ( match && isMeaningfulSvgColor( match[ 2 ] ) ) {
							pushColor( match[ 2 ].trim() );
						}
					} );
				}
			} );
		} catch ( error ) {
			return [];
		}
		return slots.slice( 0, limit );
	}

	function isRecolorableCustomerGraphic( obj ) {
		return graphicColorAllowed( obj )
			&& Array.isArray( obj.wcGpdGraphicColorSlots )
			&& obj.wcGpdGraphicColorSlots.length > 0;
	}

	function graphicColorAllowed( obj ) {
		return isCustomerGraphic( obj )
			&& !! obj.wcGpdGraphicVector
			&& productSettingsAllow( 'allow_graphic_color' );
	}

	function graphicColorValues( obj ) {
		if ( ! obj || ! Array.isArray( obj.wcGpdGraphicColorSlots ) ) {
			return [];
		}
		if ( Array.isArray( obj.wcGpdGraphicColors ) && obj.wcGpdGraphicColors.length === obj.wcGpdGraphicColorSlots.length ) {
			return obj.wcGpdGraphicColors.slice();
		}
		return obj.wcGpdGraphicColorSlots.slice();
	}

	function graphicSlotIndexForColor( slots, color ) {
		const normalized = normalizeHexColor( color );
		if ( ! normalized || ! Array.isArray( slots ) ) {
			return -1;
		}
		return slots.findIndex( ( slotColor ) => normalizeHexColor( slotColor ) === normalized );
	}

	function assignGraphicPathSlots( obj, slots ) {
		if ( ! obj || ! Array.isArray( slots ) || ! slots.length ) {
			return;
		}
		function walk( node ) {
			if ( ! node ) {
				return;
			}
			if ( node.type === 'group' && node.getObjects ) {
				node.getObjects().forEach( walk );
				return;
			}
			const fillSlot = graphicSlotIndexForColor( slots, node.fill );
			const strokeSlot = graphicSlotIndexForColor( slots, node.stroke );
			if ( fillSlot >= 0 ) {
				node.wcGpdGraphicFillSlot = fillSlot;
			}
			if ( strokeSlot >= 0 ) {
				node.wcGpdGraphicStrokeSlot = strokeSlot;
			}
		}
		walk( obj );
	}

	function graphicPathHasSlotTags( obj ) {
		let tagged = false;
		function walk( node ) {
			if ( tagged || ! node ) {
				return;
			}
			if ( node.wcGpdGraphicFillSlot !== undefined || node.wcGpdGraphicStrokeSlot !== undefined ) {
				tagged = true;
				return;
			}
			if ( node.type === 'group' && node.getObjects ) {
				node.getObjects().forEach( walk );
			}
		}
		walk( obj );
		return tagged;
	}

	function ensureGraphicPathSlots( obj ) {
		if ( ! obj || ! obj.wcGpdGraphicVector || ! Array.isArray( obj.wcGpdGraphicColorSlots ) ) {
			return;
		}
		if ( graphicPathHasSlotTags( obj ) ) {
			return;
		}
		assignGraphicPathSlots( obj, obj.wcGpdGraphicColorSlots );
	}

	function applyGraphicSlotColor( obj, slotIndex, color ) {
		if ( ! obj || ! Array.isArray( obj.wcGpdGraphicColorSlots ) ) {
			return;
		}
		ensureGraphicPathSlots( obj );
		const colors = graphicColorValues( obj );
		if ( slotIndex < 0 || slotIndex >= colors.length ) {
			return;
		}
		const nextColor = isNoColor( color ) ? colors[ slotIndex ] : color;
		function walk( node ) {
			if ( ! node ) {
				return;
			}
			if ( node.type === 'group' && node.getObjects ) {
				node.getObjects().forEach( walk );
				return;
			}
			if ( node.wcGpdGraphicFillSlot === slotIndex ) {
				node.set( 'fill', nextColor );
			}
			if ( node.wcGpdGraphicStrokeSlot === slotIndex ) {
				node.set( 'stroke', nextColor );
			}
		}
		walk( obj );
		colors[ slotIndex ] = nextColor;
		obj.wcGpdGraphicColors = colors;
	}

	function finalizeCustomerGraphic( obj, onAdded, options ) {
		const opts = options || {};
		const isReplaceable = opts.replaceable || isReplaceableContent( obj );

		if ( isReplaceable ) {
			if ( opts.frameObj ) {
				fitObjectToReplaceableFrame( obj, opts.frameObj );
			}
			applyReplaceableContentInteractivity( obj );
		} else {
			applyGraphicInteractivity( obj );
		}

		canvas.add( obj );
		canvas.requestRenderAll();
		syncLayersList();

		if ( ! opts.skipToolbar && ! isReplaceable ) {
			canvas.setActiveObject( obj );
			syncToolbar( obj );
			openCustomerSection( 'context' );
		}

		if ( typeof onAdded === 'function' ) {
			onAdded( obj );
		}
	}

	function loadCustomerGraphic( item, placement, onAdded ) {
		if ( ! item || ! item.url ) {
			return;
		}
		const isReplaceable = !!( placement && placement.replaceable );
		const frameObj = placement && placement.frameObj;
		const region = ( placement && placement.region ) || getConstraintRect();
		const maxW = ( placement && placement.maxW ) || Math.min( region.width * 0.45, 240 );
		const left = placement && placement.left !== undefined ? placement.left : region.left + region.width / 2;
		const top = placement && placement.top !== undefined ? placement.top : region.top + region.height / 2;
		const extra = ( placement && placement.extra ) || {};
		const finalizeOpts = isReplaceable ? { replaceable: true, frameObj, skipToolbar: true } : {};
		const baseMeta = {
			left,
			top,
			originX: 'center',
			originY: 'center',
			wcGpdLayerType: 'graphic',
			wcGpdGraphicLayer: true,
			wcGpdAttachmentId: item.id || 0,
			wcGpdLayerLabel: item.title || ( config.i18n.layerGraphic || 'Graphic' ),
			...extra,
		};

		function addRasterGraphic() {
			fabric.Image.fromURL(
				item.url,
				( img ) => {
					if ( ! img ) {
						return;
					}
					const scale = maxW / Math.max( img.width || 1, 1 );
					img.set( {
						...baseMeta,
						scaleX: isReplaceable ? 1 : scale,
						scaleY: isReplaceable ? 1 : scale,
						wcGpdGraphicVector: false,
					} );
					finalizeCustomerGraphic( img, onAdded, finalizeOpts );
				},
				{ crossOrigin: 'anonymous' }
			);
		}

		if ( ! isSvgResourceUrl( item.url, item ) ) {
			addRasterGraphic();
			return;
		}

		fetch( item.url, { credentials: 'same-origin' } )
			.then( ( response ) => response.text() )
			.then( ( svg ) => {
				if ( ! svg ) {
					addRasterGraphic();
					return;
				}
				const slots = extractSvgColorSlots( svg, MAX_GRAPHIC_COLOR_SLOTS );
				fabric.loadSVGFromString( svg, ( objects, options ) => {
					if ( ! objects || ! objects.length ) {
						addRasterGraphic();
						return;
					}
					let obj = objects.length === 1 ? objects[ 0 ] : fabric.util.groupSVGElements( objects, options );
					const bounds = obj.getBoundingRect( true, true );
					const base = Math.max( bounds.width || 1, bounds.height || 1, 1 );
					const scale = maxW / base;
					obj.set( {
						...baseMeta,
						scaleX: isReplaceable ? 1 : scale,
						scaleY: isReplaceable ? 1 : scale,
						wcGpdGraphicVector: true,
						wcGpdGraphicColorSlots: slots,
						wcGpdGraphicColors: slots.slice(),
					} );
					assignGraphicPathSlots( obj, slots );
					finalizeCustomerGraphic( obj, onAdded, finalizeOpts );
				} );
			} )
			.catch( () => {
				addRasterGraphic();
			} );
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

	function setGraphicContextVisible( visible ) {
		if ( ui.contextGraphicHint ) {
			ui.contextGraphicHint.hidden = ! visible;
		}
		designerRoot.querySelectorAll( '[data-customer-context="graphic"]' ).forEach( ( row ) => {
			row.hidden = ! visible;
		} );
	}

	function layerLabelForCustomer( obj ) {
		if ( ! obj ) {
			return config.i18n.layerText || 'Layer';
		}
		if ( obj.wcGpdLayerLabel ) {
			return obj.wcGpdLayerLabel;
		}
		const text = typeof obj.text === 'string' ? obj.text.trim() : '';
		if ( text ) {
			return text.slice( 0, 40 );
		}
		if ( isCustomerGraphic( obj ) ) {
			return obj.wcGpdCustomerUpload
				? ( config.i18n.layerImage || 'Uploaded image' )
				: ( config.i18n.layerGraphic || 'Graphic' );
		}
		if ( isCustomerAddedIcon( obj ) ) {
			return config.i18n.layerIcon || 'Icon';
		}
		if ( isCustomerEditableShape( obj ) ) {
			return config.i18n.layerShape || 'Shape';
		}
		if ( isCustomerEditableTemplateText( obj ) || ( isTextLayer( obj ) && ! obj.wcGpdTemplateLayer ) ) {
			return config.i18n.layerText || 'Text layer';
		}
		return config.i18n.layerText || 'Layer';
	}

	function layerTypeBadgeForCustomer( obj ) {
		if ( isCustomerGraphic( obj ) ) {
			return obj.wcGpdCustomerUpload ? 'IMG' : 'GFX';
		}
		if ( isCustomerAddedIcon( obj ) ) {
			return 'ICO';
		}
		if ( isCustomerEditableShape( obj ) ) {
			return 'SHP';
		}
		if ( isTextLayer( obj ) ) {
			return 'TXT';
		}
		return 'LYR';
	}

	function customerLayerActionBtn( title, label, onClick ) {
		const btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'wc-gpd-tpl-layer-action';
		btn.title = title;
		btn.setAttribute( 'aria-label', title );
		btn.textContent = label;
		btn.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			onClick();
		} );
		return btn;
	}

	function moveCustomerLayer( obj, direction ) {
		if ( ! obj || ! isCustomerSelectableLayer( obj ) ) {
			return;
		}
		if ( direction === 'up' ) {
			canvas.bringForward( obj );
		} else {
			canvas.sendBackwards( obj );
		}
		canvas.setActiveObject( obj );
		syncToolbar( obj );
		syncLayersList();
		canvas.requestRenderAll();
	}

	function deleteCustomerLayer( obj ) {
		if ( ! canCustomerDeleteLayer( obj ) ) {
			return;
		}
		canvas.remove( obj );
		discardSelection();
		syncLayersList();
		canvas.requestRenderAll();
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
		const allowText = isAddGroupReady( 'text' );
		const allowShape = isAddGroupReady( 'shape' );
		const allowGraphic = isAddGroupReady( 'graphic' );
		const allowImage = isAddGroupReady( 'image' );
		const allowIcon = isAddGroupReady( 'icon' );
		const anyAdd = allowText || allowShape || allowGraphic || allowImage || allowIcon
			|| isAddGroupEnabled( 'graphic' ) || isAddGroupEnabled( 'icon' );

		setToolVisible( ui.addText, allowText );
		setToolVisible( ui.addImage, allowImage );

		designerRoot.querySelectorAll( '[data-add-group]' ).forEach( ( group ) => {
			const key = group.getAttribute( 'data-add-group' );
			const enabled = isAddGroupEnabled( key );
			group.hidden = ! enabled;
			if ( enabled ) {
				const toggle = group.querySelector( '.wc-gpd-add-menu__toggle' );
				const body = group.querySelector( '.wc-gpd-add-menu__body' );
				if ( toggle && body && ! group.classList.contains( 'is-open' ) ) {
					group.classList.add( 'is-open' );
					toggle.setAttribute( 'aria-expanded', 'true' );
					body.hidden = false;
				}
			}
		} );

		if ( ui.addEmpty ) {
			ui.addEmpty.hidden = anyAdd;
		}
		if ( ui.addMenu ) {
			ui.addMenu.hidden = ! anyAdd;
		}
		if ( ui.navAdd ) {
			ui.navAdd.hidden = ! anyAdd;
		}

		if ( isAddGroupEnabled( 'graphic' ) ) {
			renderAddGraphicLibrary();
		} else if ( ui.addGraphicLibrary ) {
			ui.addGraphicLibrary.innerHTML = '';
		}

		if ( isAddGroupEnabled( 'image' ) ) {
			renderAddPhotoLibrary();
		} else if ( ui.addPhotoLibrary ) {
			ui.addPhotoLibrary.innerHTML = '';
		}

		if ( isAddGroupEnabled( 'icon' ) ) {
			initCustomerIconBrowser();
		}

		if ( ui.navLayers ) {
			ui.navLayers.hidden = productSettings.allow_layers_panel === false;
		}
		if ( activeText ) {
			applyLayerToolSettings( activeText );
		}
	}

	function productAllowsAdd( key ) {
		const settingKey = 'allow_add_' + key;
		if ( productSettings[ settingKey ] === false ) {
			return false;
		}
		if ( key === 'text' && productSettings.allow_free_text === false ) {
			return false;
		}
		if ( key === 'graphic' || key === 'image' ) {
			return productSettings[ settingKey ] !== false;
		}
		return productSettings[ settingKey ] !== false;
	}

	function renderAddPhotoLibrary() {
		if ( ! ui.addPhotoLibrary ) {
			return;
		}
		const items = resolveAddPhotoItems();
		ui.addPhotoLibrary.innerHTML = '';
		if ( ! items.length ) {
			return;
		}
		const row = document.createElement( 'div' );
		row.className = 'wc-gpd-graphic-thumb-row';
		items.forEach( ( item ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wc-gpd-graphic-thumb';
			btn.title = item.title || '';
			btn.innerHTML = `<img src="${ item.url }" alt="" loading="lazy" />`;
			btn.addEventListener( 'click', () => addPhotoFromLibrary( item ) );
			row.appendChild( btn );
		} );
		ui.addPhotoLibrary.appendChild( row );
	}

	function addPhotoFromLibrary( item ) {
		if ( ! item || ! item.url ) {
			return;
		}
		const region = getConstraintRect();
		fabric.Image.fromURL(
			item.url,
			( img ) => {
				if ( ! img ) {
					return;
				}
				const maxW = Math.min( region.width * 0.55, 320 );
				const scale = maxW / Math.max( img.width || 1, 1 );
				img.set( {
					left: region.left + region.width / 2,
					top: region.top + region.height / 2,
					originX: 'center',
					originY: 'center',
					scaleX: scale,
					scaleY: scale,
					wcGpdGraphicLayer: true,
					wcGpdLayerType: 'graphic',
					wcGpdCustomerUpload: false,
					wcGpdAttachmentId: item.id || 0,
					wcGpdLayerLabel: item.title || ( config.i18n.layerImage || 'Photo' ),
				} );
				applyGraphicInteractivity( img );
				canvas.add( img );
				canvas.setActiveObject( img );
				canvas.requestRenderAll();
				syncToolbar( img );
				syncLayersList();
				openCustomerSection( 'context' );
			},
			{ crossOrigin: 'anonymous' }
		);
	}

	function renderAddGraphicLibrary() {
		if ( ! ui.addGraphicLibrary ) {
			return;
		}
		const items = resolveAddGraphicItems();
		ui.addGraphicLibrary.innerHTML = '';
		if ( ! items.length ) {
			const empty = document.createElement( 'p' );
			empty.className = 'wc-gpd-add-menu__empty';
			empty.textContent = config.i18n.noGraphicsAvailable || 'No graphics are available yet. Add graphics in the store admin under Graphic Libraries.';
			ui.addGraphicLibrary.appendChild( empty );
			return;
		}
		const row = document.createElement( 'div' );
		row.className = 'wc-gpd-graphic-thumb-row';
		items.forEach( ( item ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wc-gpd-graphic-thumb';
			btn.title = item.title || '';
			btn.innerHTML = `<img src="${ item.url }" alt="" loading="lazy" />`;
			btn.addEventListener( 'click', () => addGraphicFromLibrary( item ) );
			row.appendChild( btn );
		} );
		ui.addGraphicLibrary.appendChild( row );
	}

	function renderCustomerIconButton( slug ) {
		const baseUrl = bootstrapIcons.iconBaseUrl || '';
		const btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'wc-gpd-shape-library-btn wc-gpd-bootstrap-icon-btn';
		btn.title = slug;
		const label = slug.replace( /-/g, ' ' );
		if ( baseUrl ) {
			btn.innerHTML = `<img src="${ baseUrl }${ slug }.svg" alt="" width="28" height="28" loading="lazy" /><span>${ label }</span>`;
		} else {
			btn.innerHTML = `<span class="wc-gpd-bootstrap-icon-fallback">${ slug.charAt( 0 ).toUpperCase() }</span><span>${ label }</span>`;
		}
		btn.addEventListener( 'click', () => addCustomerIcon( slug ) );
		return btn;
	}

	const iconBrowserState = {
		initialized: false,
		query: '',
		limit: 60,
		offset: 0,
		total: 0,
		icons: [],
		loading: false,
	};

	function updateCustomerIconStatus() {
		if ( ! ui.iconStatus ) {
			return;
		}
		const count = iconBrowserState.icons.length;
		if ( ! count && ! iconBrowserState.loading ) {
			ui.iconStatus.hidden = true;
			return;
		}
		ui.iconStatus.textContent = `${ count } / ${ iconBrowserState.total || count } icons`;
		ui.iconStatus.hidden = false;
	}

	function updateCustomerIconLoadMore() {
		if ( ! ui.iconLoadMoreWrap ) {
			return;
		}
		ui.iconLoadMoreWrap.hidden = iconBrowserState.loading || iconBrowserState.icons.length >= iconBrowserState.total;
	}

	function renderCustomerIconResults() {
		if ( ! ui.iconResults ) {
			return;
		}
		ui.iconResults.innerHTML = '';
		if ( ! iconBrowserState.icons.length ) {
			const empty = document.createElement( 'p' );
			empty.className = 'wc-gpd-add-menu__empty';
			empty.textContent = config.i18n.iconsNoResults || 'No icons found.';
			ui.iconResults.appendChild( empty );
			updateCustomerIconStatus();
			updateCustomerIconLoadMore();
			return;
		}
		iconBrowserState.icons.forEach( ( slug ) => {
			ui.iconResults.appendChild( renderCustomerIconButton( slug ) );
		} );
		updateCustomerIconStatus();
		updateCustomerIconLoadMore();
	}

	function fetchCustomerIcons( append ) {
		if ( iconBrowserState.loading || ! bootstrapIcons.ajaxUrl ) {
			return;
		}
		const query = ui.iconSearch ? ui.iconSearch.value.trim() : '';
		if ( ! append ) {
			iconBrowserState.offset = 0;
			iconBrowserState.icons = [];
			iconBrowserState.query = query;
			if ( ui.iconResults ) {
				ui.iconResults.innerHTML = '<p class="wc-gpd-add-menu__status">' + ( config.i18n.iconsSearching || 'Loading icons…' ) + '</p>';
			}
		} else {
			iconBrowserState.offset = iconBrowserState.icons.length;
		}
		iconBrowserState.loading = true;
		updateCustomerIconLoadMore();

		const url = new URL( bootstrapIcons.ajaxUrl, window.location.origin );
		url.searchParams.set( 'action', bootstrapIcons.ajaxAction || 'wc_gpd_search_bootstrap_icons' );
		url.searchParams.set( 'nonce', bootstrapIcons.nonce || config.nonce || '' );
		url.searchParams.set( 'q', query );
		url.searchParams.set( 'limit', String( iconBrowserState.limit ) );
		url.searchParams.set( 'offset', String( iconBrowserState.offset ) );
		const libraries = iconSearchLibraryParam();
		if ( libraries ) {
			url.searchParams.set( 'libraries', libraries );
		}

		fetch( url.toString(), { credentials: 'same-origin' } )
			.then( ( response ) => response.json() )
			.then( ( payload ) => {
				if ( ! payload || ! payload.success || ! payload.data ) {
					throw new Error( 'search failed' );
				}
				iconBrowserState.total = payload.data.total || 0;
				const page = payload.data.icons || [];
				iconBrowserState.icons = append ? iconBrowserState.icons.concat( page ) : page;
				renderCustomerIconResults();
			} )
			.catch( () => {
				if ( ! append && ui.iconResults ) {
					ui.iconResults.innerHTML = '<p class="wc-gpd-add-menu__empty">' + ( config.i18n.noIconsAvailable || 'Icons are not available.' ) + '</p>';
				}
			} )
			.finally( () => {
				iconBrowserState.loading = false;
				updateCustomerIconLoadMore();
			} );
	}

	function initCustomerIconBrowser() {
		if ( iconBrowserState.initialized ) {
			return;
		}
		iconBrowserState.initialized = true;
		if ( ui.iconSearchBtn ) {
			ui.iconSearchBtn.addEventListener( 'click', () => fetchCustomerIcons( false ) );
		}
		if ( ui.iconSearch ) {
			ui.iconSearch.addEventListener( 'keydown', ( event ) => {
				if ( 'Enter' === event.key ) {
					event.preventDefault();
					fetchCustomerIcons( false );
				}
			} );
		}
		if ( ui.iconLoadMoreBtn ) {
			ui.iconLoadMoreBtn.addEventListener( 'click', () => fetchCustomerIcons( true ) );
		}
		if ( ui.iconResults ) {
			fetchCustomerIcons( false );
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
		const isShapeLayer = isCustomerEditableShape( obj );
		const isAddedShape = isCustomerAddedShape( obj );
		const isAddedIcon = isCustomerAddedIcon( obj );
		const isGraphicLayer = isCustomerGraphic( obj );
		const isText = !! obj && ( isPlaceholderLayer( obj ) || isCustomerEditableTemplateText( obj ) || ( isTextLayer( obj ) && ! obj.wcGpdTemplateLayer ) ) && isUsableTextLayer( obj );
		const textColorOk = isText && layerAllowsTool( obj, 'wcGpdLockColor', 'allow_text_color' );
		const shapeColorOk = shapeColorAllowed( obj );
		const iconColorOk = iconColorAllowed( obj );
		const graphicColorOk = graphicColorAllowed( obj );
		const paletteRestricted = layerColorPaletteRestricted( obj );
		const showInlineSwatches = shouldShowInlinePaletteSwatches( obj );
		const showDropdownSwatches = shouldShowDropdownColorPicker( obj );
		const pickerAllowed = textColorOk && ! paletteRestricted;
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
			const showColor = textColorOk || shapeColorOk || iconColorOk || graphicColorOk;
			colorRow.hidden = ! showColor;
			colorRow.classList.remove( 'is-disabled' );
			const label = colorRow.querySelector( '.wc-gpd-prop-label' );
			if ( label ) {
				if ( graphicColorOk ) {
					label.textContent = config.i18n.graphicColors || 'Graphic colors';
				} else if ( isAddedIcon || ( isShapeLayer && ! isAddedShape && ! isText ) ) {
					label.textContent = isAddedIcon
						? ( config.i18n.iconColor || 'Icon color' )
						: ( config.i18n.fillColor || 'Color' );
				} else if ( isAddedShape || ( isShapeLayer && ! isText ) ) {
					label.textContent = config.i18n.fillColor || 'Color';
				} else {
					label.textContent = config.i18n.textColor || 'Text color';
				}
			}
		}
		if ( ui.colorSwatches ) {
			ui.colorSwatches.hidden = ! ( showInlineSwatches || showDropdownSwatches || graphicColorOk );
		}
		if ( ui.textColor ) {
			ui.textColor.hidden = ! pickerAllowed;
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

	let openColorMenu = null;

	function closeColorMenus() {
		if ( openColorMenu ) {
			openColorMenu.hidden = true;
			openColorMenu = null;
		}
	}

	function uniqueColors( colors ) {
		const seen = new Set();
		const list = [];
		( colors || [] ).forEach( ( color ) => {
			const normalized = String( color || '' ).toLowerCase();
			if ( ! normalized || seen.has( normalized ) ) {
				return;
			}
			seen.add( normalized );
			list.push( color );
		} );
		return list;
	}

	function allTemplatePaletteColors() {
		const globalColors = resolveTemplateGlobalColors();
		if ( globalColors ) {
			return globalColors;
		}
		const colors = [];
		( templatePalettes.palettes || [] ).forEach( ( palette ) => {
			if ( palette && Array.isArray( palette.colors ) ) {
				colors.push( ...palette.colors );
			}
		} );
		return colors.length ? colors : [ '#000000' ];
	}

	function shapeRoleColor( obj, role ) {
		if ( ! obj ) {
			return 'transparent';
		}
		if ( role === 'stroke' ) {
			return obj.stroke || 'transparent';
		}
		return obj.fill || 'transparent';
	}

	function iconRoleColor( obj ) {
		if ( ! obj ) {
			return 'transparent';
		}
		let color = 'transparent';
		function walk( target ) {
			if ( ! target ) {
				return;
			}
			if ( target.type === 'group' && target.getObjects ) {
				target.getObjects().forEach( walk );
				return;
			}
			if ( target.fill && ! isNoColor( target.fill ) ) {
				color = target.fill;
			}
		}
		walk( obj );
		return color;
	}

	function isNoColor( color ) {
		const value = String( color || '' ).toLowerCase();
		return ! value || value === 'transparent' || value === 'none';
	}

	function normalizePickerColor( color ) {
		if ( isNoColor( color ) ) {
			return '#ffffff';
		}
		if ( String( color ).startsWith( '#' ) && String( color ).length >= 7 ) {
			return color;
		}
		return '#000000';
	}

	function applyCustomerIconColor( obj, color ) {
		if ( ! obj ) {
			return;
		}
		const value = isNoColor( color ) ? 'transparent' : color;
		styleCustomerIconPaths( obj, value );
	}

	function applyLayerColor( obj, role, color ) {
		if ( isCustomerAddedIcon( obj ) ) {
			applyCustomerIconColor( obj, color );
			return;
		}
		if ( isCustomerEditableShape( obj ) ) {
			applyShapeRoleColor( obj, role, color );
			return;
		}
		if ( obj ) {
			obj.set( 'fill', color );
			if ( ui.textColor && isTextLayer( obj ) ) {
				ui.textColor.value = isNoColor( color ) ? normalizePickerColor( color ) : color;
			}
		}
	}

	function applyShapeRoleColor( obj, role, color ) {
		if ( ! obj ) {
			return;
		}
		const value = isNoColor( color ) ? 'transparent' : color;
		if ( role === 'stroke' ) {
			applyShapeStrokeColor( obj, value );
			if ( isNoColor( value ) ) {
				obj.set( 'strokeWidth', 0 );
			} else if ( ! obj.strokeWidth ) {
				obj.set( 'strokeWidth', productSettings.outline_stroke_width || 2 );
			}
		} else {
			applyShapeFillColor( obj, value );
		}
	}

	function roleColorForObject( obj, role ) {
		if ( isCustomerAddedIcon( obj ) ) {
			return iconRoleColor( obj );
		}
		if ( isCustomerEditableShape( obj ) ) {
			return shapeRoleColor( obj, role );
		}
		return ( obj && obj.fill ) || 'transparent';
	}

	function renderGraphicSlotSwatches( obj ) {
		if ( ! ui.colorSwatches ) {
			return;
		}
		ui.colorSwatches.innerHTML = '';
		closeColorMenus();

		const slots = obj.wcGpdGraphicColorSlots || [];
		const colors = graphicColorValues( obj );
		const palette = uniqueColors( paletteColorsForCustomerAdd( obj ) );
		const labelTemplate = config.i18n.graphicColorSlot || 'Color %d';
		const showCustomPicker = ! customerAddedPaletteRestricted( obj );

		slots.forEach( ( slotColor, slotIndex ) => {
			const group = document.createElement( 'div' );
			group.className = 'wc-gpd-color-role-group wc-gpd-color-role-group--stacked';

			const roleLabel = document.createElement( 'span' );
			roleLabel.className = 'wc-gpd-color-role-label';
			roleLabel.textContent = labelTemplate.replace( '%d', String( slotIndex + 1 ) );
			group.appendChild( roleLabel );

			const currentColor = colors[ slotIndex ] || slotColor;
			const swatchRow = document.createElement( 'div' );
			swatchRow.className = 'wc-gpd-color-role-swatches';
			palette.forEach( ( color ) => {
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'wc-gpd-color-swatch';
				btn.style.backgroundColor = color;
				btn.title = color;
				btn.setAttribute( 'aria-label', color );
				const activeColor = normalizeHexColor( currentColor );
				btn.classList.toggle( 'is-active', activeColor && color.toLowerCase() === activeColor );
				btn.addEventListener( 'click', () => {
					if ( ! activeText ) {
						return;
					}
					applyGraphicSlotColor( activeText, slotIndex, color );
					canvas.requestRenderAll();
					renderColorSwatches( activeText );
				} );
				swatchRow.appendChild( btn );
			} );
			group.appendChild( swatchRow );

			if ( showCustomPicker ) {
				const customWrap = document.createElement( 'label' );
				customWrap.className = 'wc-gpd-color-custom-wrap';
				const customInput = document.createElement( 'input' );
				customInput.type = 'color';
				customInput.className = 'wc-gpd-color-custom-input';
				customInput.value = normalizePickerColor( currentColor );
				customInput.addEventListener( 'change', () => {
					if ( ! activeText ) {
						return;
					}
					applyGraphicSlotColor( activeText, slotIndex, customInput.value );
					canvas.requestRenderAll();
					renderColorSwatches( activeText );
				} );
				customWrap.appendChild( customInput );
				customWrap.appendChild( document.createTextNode( ' ' + ( config.i18n.customColor || 'Custom color' ) ) );
				group.appendChild( customWrap );
			}

			ui.colorSwatches.appendChild( group );
		} );
	}

	function appendInlineCustomColorInput( group, obj, role, currentColor ) {
		const customWrap = document.createElement( 'label' );
		customWrap.className = 'wc-gpd-color-custom-wrap';
		const customInput = document.createElement( 'input' );
		customInput.type = 'color';
		customInput.className = 'wc-gpd-color-custom-input';
		customInput.value = normalizePickerColor( currentColor );
		customInput.addEventListener( 'change', () => {
			if ( ! activeText ) {
				return;
			}
			applyLayerColor( activeText, role, customInput.value );
			canvas.requestRenderAll();
			renderColorSwatches( activeText );
		} );
		customWrap.appendChild( customInput );
		customWrap.appendChild( document.createTextNode( ' ' + ( config.i18n.customColor || 'Custom color' ) ) );
		group.appendChild( customWrap );
	}

	function renderColorSwatches( obj ) {
		if ( ! ui.colorSwatches ) {
			return;
		}
		ui.colorSwatches.innerHTML = '';
		closeColorMenus();

		if ( isRecolorableCustomerGraphic( obj ) ) {
			renderGraphicSlotSwatches( obj );
			return;
		}

		const showInline = shouldShowInlinePaletteSwatches( obj );
		const showDropdown = shouldShowDropdownColorPicker( obj );
		if ( ! showInline && ! showDropdown ) {
			return;
		}

		const isShapeLayer = isCustomerEditableShape( obj );
		const isIconLayer = isCustomerAddedIcon( obj );
		const roles = isIconLayer
			? [ 'fill' ]
			: isShapeLayer
				? [ shapeUsesFill( obj ) ? 'fill' : null, shapeUsesStroke( obj ) ? 'stroke' : null ].filter( Boolean )
				: [ 'fill' ];

		roles.forEach( ( role ) => {
			const group = document.createElement( 'div' );
			group.className = 'wc-gpd-color-role-group wc-gpd-color-role-group--stacked';

			const roleLabel = document.createElement( 'span' );
			roleLabel.className = 'wc-gpd-color-role-label';
			roleLabel.textContent = isIconLayer
				? ( config.i18n.iconColor || 'Icon color' )
				: isShapeLayer
					? ( role === 'stroke'
						? ( config.i18n.outlineColor || 'Outline' )
						: ( config.i18n.fillColor || 'Fill' ) )
					: ( config.i18n.textColor || 'Color' );
			group.appendChild( roleLabel );

			const currentColor = roleColorForObject( obj, role );
			const paletteColors = uniqueColors( paletteColorsForObject( obj, role ) );

			if ( showInline ) {
				const swatchRow = document.createElement( 'div' );
				swatchRow.className = 'wc-gpd-color-role-swatches';
				paletteColors.forEach( ( color ) => {
					const btn = document.createElement( 'button' );
					btn.type = 'button';
					btn.className = 'wc-gpd-color-swatch';
					btn.style.backgroundColor = color;
					btn.title = color;
					btn.setAttribute( 'aria-label', color );
					const activeColor = String( currentColor || '' ).toLowerCase();
					btn.classList.toggle( 'is-active', ! isNoColor( activeColor ) && color.toLowerCase() === activeColor );
					btn.addEventListener( 'click', () => {
						if ( ! activeText ) {
							return;
						}
						applyLayerColor( activeText, role, color );
						canvas.requestRenderAll();
						renderColorSwatches( activeText );
					} );
					swatchRow.appendChild( btn );
				} );
				group.appendChild( swatchRow );
				if ( shouldShowInlineCustomColorPicker( obj ) ) {
					appendInlineCustomColorInput( group, obj, role, currentColor );
				}
				ui.colorSwatches.appendChild( group );
				return;
			}

			const pickerRow = document.createElement( 'div' );
			pickerRow.className = 'wc-gpd-color-picker-row';
			const menuColors = uniqueColors( paletteColors.concat( allTemplatePaletteColors() ) );

			const trigger = document.createElement( 'button' );
			trigger.type = 'button';
			trigger.className = 'wc-gpd-color-trigger';
			trigger.setAttribute( 'aria-haspopup', 'true' );
			trigger.title = config.i18n.chooseColor || 'Choose color';

			const triggerSwatch = document.createElement( 'span' );
			triggerSwatch.className = 'wc-gpd-color-trigger__swatch';
			if ( isNoColor( currentColor ) ) {
				triggerSwatch.classList.add( 'is-none' );
			} else {
				triggerSwatch.style.backgroundColor = currentColor;
			}
			trigger.appendChild( triggerSwatch );

			const menu = document.createElement( 'div' );
			menu.className = 'wc-gpd-color-menu';
			menu.hidden = true;
			menu.addEventListener( 'mousedown', ( event ) => event.stopPropagation() );
			menu.addEventListener( 'click', ( event ) => event.stopPropagation() );

			const swatchRow = document.createElement( 'div' );
			swatchRow.className = 'wc-gpd-color-role-swatches';
			menuColors.forEach( ( color ) => {
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'wc-gpd-color-swatch';
				btn.style.backgroundColor = color;
				btn.title = color;
				btn.setAttribute( 'aria-label', color );
				const activeColor = String( currentColor || '' ).toLowerCase();
				btn.classList.toggle( 'is-active', ! isNoColor( activeColor ) && color.toLowerCase() === activeColor );
				btn.addEventListener( 'click', ( event ) => {
					event.stopPropagation();
					if ( ! activeText ) {
						return;
					}
					applyLayerColor( activeText, role, color );
					if ( isNoColor( color ) ) {
						triggerSwatch.classList.add( 'is-none' );
						triggerSwatch.style.backgroundColor = '';
					} else {
						triggerSwatch.classList.remove( 'is-none' );
						triggerSwatch.style.backgroundColor = color;
					}
					closeColorMenus();
					canvas.requestRenderAll();
				} );
				swatchRow.appendChild( btn );
			} );
			menu.appendChild( swatchRow );

			const noneBtn = document.createElement( 'button' );
			noneBtn.type = 'button';
			noneBtn.className = 'wc-gpd-color-none-btn';
			noneBtn.textContent = config.i18n.noColor || 'No color';
			noneBtn.classList.toggle( 'is-active', isNoColor( currentColor ) );
			noneBtn.addEventListener( 'click', ( event ) => {
				event.stopPropagation();
				if ( ! activeText ) {
					return;
				}
				applyLayerColor( activeText, role, 'transparent' );
				triggerSwatch.classList.add( 'is-none' );
				triggerSwatch.style.backgroundColor = '';
				closeColorMenus();
				canvas.requestRenderAll();
			} );
			menu.appendChild( noneBtn );

			const customWrap = document.createElement( 'label' );
			customWrap.className = 'wc-gpd-color-custom-wrap';
			const customInput = document.createElement( 'input' );
			customInput.type = 'color';
			customInput.className = 'wc-gpd-color-custom-input';
			customInput.value = normalizePickerColor( currentColor );
			customInput.addEventListener( 'change', ( event ) => {
				event.stopPropagation();
				if ( ! activeText ) {
					return;
				}
				const color = customInput.value;
				applyLayerColor( activeText, role, color );
				triggerSwatch.classList.remove( 'is-none' );
				triggerSwatch.style.backgroundColor = color;
				canvas.requestRenderAll();
			} );
			customWrap.appendChild( customInput );
			customWrap.appendChild( document.createTextNode( ' ' + ( config.i18n.customColor || 'Custom color' ) ) );
			menu.appendChild( customWrap );

			trigger.addEventListener( 'click', ( event ) => {
				event.stopPropagation();
				const willOpen = menu.hidden;
				closeColorMenus();
				if ( willOpen ) {
					menu.hidden = false;
					openColorMenu = menu;
				}
			} );

			pickerRow.appendChild( trigger );
			pickerRow.appendChild( menu );
			group.appendChild( pickerRow );
			ui.colorSwatches.appendChild( group );
		} );
	}

	document.addEventListener( 'mousedown', ( event ) => {
		if ( event.target.closest( '.wc-gpd-color-picker-row' ) ) {
			return;
		}
		closeColorMenus();
	} );

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
	let replaceablePickerSlotUid = '';
	let suppressReplaceablePicker = false;
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

	function isReplaceableFrame( obj ) {
		return !! obj && (
			!! obj.wcGpdReplaceable
			|| obj.wcGpdLayerType === 'graphic_slot'
			|| obj.wcGpdLayerType === 'replaceable'
			|| !! obj.wcGpdGraphicSlot
		);
	}

	function isReplaceableContent( obj ) {
		if ( ! isCustomerGraphic( obj ) ) {
			return false;
		}
		return !! obj.wcGpdReplaceableUid || !! obj.wcGpdGraphicSlotUid;
	}

	function applyPlaceholderInteractivity( obj ) {
		if ( ! obj ) {
			return;
		}
		obj.set( {
			selectable: true,
			evented: true,
			hasControls: false,
			hasBorders: false,
			editable: false,
			lockMovementX: true,
			lockMovementY: true,
			lockScalingX: true,
			lockScalingY: true,
			hoverCursor: 'text',
		} );
	}

	function applyReplaceableFrameInteractivity( obj ) {
		if ( ! obj ) {
			return;
		}
		obj.set( {
			selectable: true,
			evented: true,
			hasControls: false,
			hasBorders: false,
			lockMovementX: true,
			lockMovementY: true,
			lockScalingX: true,
			lockScalingY: true,
			hoverCursor: 'pointer',
		} );
	}

	function applyReplaceableContentInteractivity( obj ) {
		if ( ! obj ) {
			return;
		}
		obj.set( {
			selectable: true,
			evented: true,
			hasControls: false,
			hasBorders: false,
			lockMovementX: true,
			lockMovementY: true,
			lockScalingX: true,
			lockScalingY: true,
			lockUniScaling: true,
			hoverCursor: 'pointer',
		} );
	}

	function applyReplaceableClipPath( obj, frameObj ) {
		if ( ! obj || ! frameObj ) {
			return;
		}
		frameObj.setCoords();
		const frame = frameObj.getBoundingRect( true );
		if ( frame.width <= 0 || frame.height <= 0 ) {
			obj.clipPath = null;
			return;
		}
		obj.clipPath = new fabric.Rect( {
			left: frame.left + frame.width / 2,
			top: frame.top + frame.height / 2,
			width: frame.width,
			height: frame.height,
			originX: 'center',
			originY: 'center',
			absolutePositioned: true,
		} );
	}

	function fitObjectToReplaceableFrame( obj, frameObj ) {
		if ( ! obj || ! frameObj ) {
			return;
		}
		frameObj.setCoords();
		const frame = frameObj.getBoundingRect( true );
		if ( frame.width <= 0 || frame.height <= 0 ) {
			return;
		}

		const savedAngle = obj.angle || 0;
		obj.set( { angle: 0 } );
		obj.setCoords();
		const bounds = obj.getBoundingRect( true );
		if ( bounds.width <= 0 || bounds.height <= 0 ) {
			obj.set( { angle: savedAngle } );
			return;
		}

		const uniform = Math.min( frame.width / bounds.width, frame.height / bounds.height );
		const centerX = frame.left + frame.width / 2;
		const centerY = frame.top + frame.height / 2;
		obj.set( {
			left: centerX,
			top: centerY,
			originX: 'center',
			originY: 'center',
			angle: frameObj.angle || 0,
			scaleX: ( obj.scaleX || 1 ) * uniform,
			scaleY: ( obj.scaleY || 1 ) * uniform,
		} );
		obj.setCoords();
		applyReplaceableClipPath( obj, frameObj );
	}

	function getReplaceableFrameForObject( obj ) {
		if ( ! obj ) {
			return null;
		}
		if ( isReplaceableFrame( obj ) ) {
			return obj;
		}
		const slotUid = obj.wcGpdReplaceableUid || obj.wcGpdGraphicSlotUid;
		if ( ! slotUid ) {
			return null;
		}
		return canvas.getObjects().find( ( candidate ) => isReplaceableFrame( candidate ) && candidate.wcGpdUid === slotUid ) || null;
	}

	function closeReplaceablePicker() {
		replaceablePickerSlotUid = '';
		if ( ui.replaceableModal ) {
			ui.replaceableModal.hidden = true;
		}
		if ( ui.replaceableModalGrid ) {
			ui.replaceableModalGrid.innerHTML = '';
		}
	}

	function openReplaceablePicker( frameObj ) {
		if ( productSettings.allow_customer_graphics === false || ! frameObj || ! ui.replaceableModal || ! ui.replaceableModalGrid ) {
			return;
		}
		const slotUid = frameObj.wcGpdUid || '';
		if ( ! slotUid ) {
			return;
		}
		replaceablePickerSlotUid = slotUid;
		const items = graphicItemsForSlot( frameObj );
		ui.replaceableModalGrid.innerHTML = '';
		items.forEach( ( item ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wc-gpd-replaceable-item';
			btn.dataset.attachmentId = String( item.id );
			const img = document.createElement( 'img' );
			img.src = item.url;
			img.alt = item.title || '';
			btn.appendChild( img );
			btn.addEventListener( 'click', () => {
				replaceReplaceableContent( frameObj, item );
			} );
			ui.replaceableModalGrid.appendChild( btn );
		} );
		ui.replaceableModal.hidden = false;
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
	function getBboxRect( role ) {
		const normalizedRole = role || 'product_outline';
		const bboxObj = canvas.getObjects().find( ( obj ) => {
			if ( ! obj ) {
				return false;
			}
			if ( obj.wcGpdBboxRole === normalizedRole ) {
				return true;
			}
			if ( normalizedRole === 'product_outline' && obj.wcGpdBoundingBox ) {
				return true;
			}
			return false;
		} );
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
		const imprintRect = getBboxRect( 'imprint_area' );
		if ( imprintRect ) {
			return imprintRect;
		}
		const outlineRect = getBboxRect( 'product_outline' );
		if ( outlineRect ) {
			return outlineRect;
		}
		return {
			left: 2,
			top: 2,
			width: PROD_WIDTH - 4,
			height: PROD_HEIGHT - 4,
		};
	}

	function getProductOutlineRect() {
		return getBboxRect( 'product_outline' ) || getConstraintRect();
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
		if ( ui.navDetails ) {
			ui.navDetails.hidden = true;
		}
		if ( ui.placeholderFields ) {
			ui.placeholderFields.innerHTML = '';
		}
		if ( ui.graphicPickers ) {
			ui.graphicPickers.innerHTML = '';
		}
	}

	function setCustomerGraphic( slotDef, libraryItem ) {
		replaceReplaceableContent( slotDef, libraryItem );
	}

	function replaceReplaceableContent( frameObj, libraryItem ) {
		if ( ! frameObj || ! libraryItem || ! libraryItem.url ) {
			return;
		}
		closeReplaceablePicker();
		suppressReplaceablePicker = true;
		const slotUid = frameObj.wcGpdUid;
		canvas.getObjects().slice().forEach( ( obj ) => {
			if ( isCustomerGraphic( obj ) && ( obj.wcGpdReplaceableUid === slotUid || obj.wcGpdGraphicSlotUid === slotUid ) ) {
				canvas.remove( obj );
			}
		} );
		const frameWidth = ( frameObj.width || 0 ) * ( frameObj.scaleX || 1 );
		const frameHeight = ( frameObj.height || 0 ) * ( frameObj.scaleY || 1 );
		loadCustomerGraphic( libraryItem, {
			replaceable: true,
			frameObj,
			left: frameObj.left,
			top: frameObj.top,
			maxW: Math.max( frameWidth, frameHeight ),
			extra: {
				wcGpdReplaceableUid: slotUid,
				wcGpdGraphicSlotUid: slotUid,
			},
		}, () => {
			canvas.discardActiveObject();
			canvas.requestRenderAll();
			syncLayersList();
			setTimeout( () => {
				suppressReplaceablePicker = false;
			}, 0 );
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

	function isCustomerAddedShape( obj ) {
		return !! obj && ! obj.wcGpdTemplateLayer && obj.wcGpdLayerType === 'shape';
	}

	function isCustomerAddedIcon( obj ) {
		return !! obj && ! obj.wcGpdTemplateLayer && obj.wcGpdLayerType === 'icon';
	}

	function isCustomerEditableShape( obj ) {
		return isCustomerEditableTemplateShape( obj ) || isCustomerAddedShape( obj ) || isCustomerAddedIcon( obj );
	}

	function isTemplateTextFullyLocked( obj ) {
		if ( ! isCustomerEditableTemplateText( obj ) ) {
			return false;
		}
		return !!(
			obj.wcGpdLockFont &&
			obj.wcGpdLockSize &&
			obj.wcGpdLockColor &&
			obj.wcGpdLockBold &&
			obj.wcGpdLockItalic &&
			obj.wcGpdLockUnderline &&
			obj.wcGpdLockAlign &&
			obj.wcGpdLockLineHeight &&
			obj.wcGpdLockLetterSpacing &&
			obj.wcGpdLockText &&
			obj.wcGpdLockMove &&
			obj.wcGpdLockScale
		);
	}

	function isTemplateShapeFullyLocked( obj ) {
		if ( ! isCustomerEditableTemplateShape( obj ) ) {
			return false;
		}
		return !!( obj.wcGpdLockColor && obj.wcGpdLockMove && obj.wcGpdLockScale );
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
		return isReplaceableFrame( obj );
	}

	function isCustomerGraphic( obj ) {
		return !! obj && obj.wcGpdLayerType === 'graphic' && ! obj.wcGpdTemplateLayer;
	}

	function isTemplateShape( obj ) {
		if ( ! obj || ! obj.wcGpdTemplateLayer || obj.wcGpdBoundingBox ) {
			return false;
		}
		if ( obj.wcGpdOutlineLayer || obj.wcGpdLayerType === 'outline' ) {
			return false;
		}
		if ( obj.wcGpdLayerType && obj.wcGpdLayerType !== 'shape' ) {
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
		const fullyLocked = isTemplateShapeFullyLocked( obj );
		const visible = layerVisibleToCustomer( obj ) && ! fullyLocked;
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

	function applyCustomerAddedShapeInteractivity( obj ) {
		if ( ! obj ) {
			return;
		}
		const isIcon = isCustomerAddedIcon( obj );
		const moveAllowed = productSettingsAllow( isIcon ? 'allow_icon_move' : 'allow_shape_move' );
		const resizeAllowed = productSettingsAllow( isIcon ? 'allow_icon_resize' : 'allow_shape_resize' );
		obj.set( {
			selectable: true,
			evented: true,
			hasControls: resizeAllowed,
			hasBorders: resizeAllowed,
			lockMovementX: ! moveAllowed,
			lockMovementY: ! moveAllowed,
			lockScalingX: ! resizeAllowed,
			lockScalingY: ! resizeAllowed,
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
		return ( view.objects || [] ).filter( ( obj ) => (
			obj.wcGpdLayerType === 'graphic_slot'
			|| obj.wcGpdLayerType === 'replaceable'
			|| obj.wcGpdReplaceable
			|| obj.wcGpdGraphicSlot
		) );
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
		closeReplaceablePicker();

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
								if ( isCustomerGraphic( obj ) ) {
									obj.wcGpdLayerType = 'graphic';
									obj.wcGpdGraphicLayer = true;
									ensureGraphicPathSlots( obj );
									if ( isReplaceableContent( obj ) ) {
										const frame = getReplaceableFrameForObject( obj );
										if ( frame ) {
											fitObjectToReplaceableFrame( obj, frame );
											applyReplaceableContentInteractivity( obj );
										} else {
											applyGraphicInteractivity( obj );
										}
									} else {
										applyGraphicInteractivity( obj );
									}
									canvas.add( obj );
									obj.setCoords();
									return;
								}
								if ( isCustomerAddedShape( obj ) || isCustomerAddedIcon( obj ) ) {
									applyCustomerAddedShapeInteractivity( obj );
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
						const productOutlineUid = view.productOutlineUid || view.boundingBoxUid || '';
						const imprintAreaUid = view.imprintAreaUid || '';
						objects.forEach( ( obj, index ) => {
							applyTemplateMetadata( obj, templateObjects[ index ] );
							obj.wcGpdTemplateLayer = true;
							if ( ! obj.wcGpdBboxRole && obj.wcGpdUid ) {
								if ( productOutlineUid && obj.wcGpdUid === productOutlineUid ) {
									obj.wcGpdBboxRole = 'product_outline';
								} else if ( imprintAreaUid && obj.wcGpdUid === imprintAreaUid ) {
									obj.wcGpdBboxRole = 'imprint_area';
								}
							}
							if ( obj.wcGpdBoundingBox && ! obj.wcGpdBboxRole ) {
								obj.wcGpdBboxRole = 'product_outline';
							}
							obj.wcGpdBoundingBox = obj.wcGpdBboxRole === 'product_outline';
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
								applyReplaceableFrameInteractivity( obj );
								obj.set( { opacity: 0.35 } );
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
								applyPlaceholderInteractivity( obj );
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
							if ( obj.wcGpdBboxRole === 'imprint_area' ) {
								obj.stroke = '#dd6b20';
								obj.strokeDashArray = [ 8, 6 ];
							} else if ( obj.wcGpdBboxRole === 'product_outline' ) {
								obj.stroke = '#2b6cb0';
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
		return obj.wcGpdTemplateLayer || obj.wcGpdOutlineLayer || obj.wcGpdBoundingBox || !! obj.wcGpdBboxRole || isMockupImage( obj );
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
		if ( isCustomerAddedShape( obj ) || isCustomerAddedIcon( obj ) ) {
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
		const fullyLocked = isTemplateTextFullyLocked( obj );
		const visible = layerVisibleToCustomer( obj ) && ! fullyLocked;
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
		if ( isCustomerGraphic( obj ) && ! obj.wcGpdGraphicSlotUid && ! obj.wcGpdReplaceableUid ) {
			const moveAllowed = productSettingsAllow( 'allow_graphic_move' );
			const resizeAllowed = productSettingsAllow( 'allow_graphic_resize' );
			obj.set( {
				selectable: true,
				evented: true,
				hasControls: resizeAllowed,
				hasBorders: resizeAllowed,
				lockMovementX: ! moveAllowed,
				lockMovementY: ! moveAllowed,
				lockScalingX: ! resizeAllowed,
				lockScalingY: ! resizeAllowed,
				lockUniScaling: false,
			} );
			return;
		}
		if ( isReplaceableContent( obj ) ) {
			applyReplaceableContentInteractivity( obj );
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
		if ( ui.placeholderEditRow ) {
			ui.placeholderEditRow.hidden = true;
		}
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
			if ( isPlaceholderLayer( obj ) || isReplaceableFrame( obj ) ) {
				return true;
			}
			if ( isCustomerEditableTemplateText( obj ) && isUsableTextLayer( obj ) ) {
				return ! isTemplateTextFullyLocked( obj );
			}
			if ( isCustomerEditableTemplateShape( obj ) ) {
				return ! isTemplateShapeFullyLocked( obj );
			}
			if ( isCustomerAddedShape( obj ) || isCustomerAddedIcon( obj ) ) {
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
			return true;
		}
		if ( isReplaceableFrame( obj ) || isReplaceableContent( obj ) ) {
			return true;
		}
		if ( isCustomerEditableTemplateText( obj ) && isUsableTextLayer( obj ) ) {
			return ! isTemplateTextFullyLocked( obj );
		}
		if ( isCustomerEditableTemplateShape( obj ) ) {
			return ! isTemplateShapeFullyLocked( obj );
		}
		if ( isCustomerAddedShape( obj ) || isCustomerAddedIcon( obj ) ) {
			return true;
		}
		if ( isTextLayer( obj ) && ! obj.wcGpdTemplateLayer && isUsableTextLayer( obj ) ) {
			return true;
		}
		if ( isCustomerGraphic( obj ) ) {
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
		if ( canvas.getObjects().some( ( obj ) => isDesignLayer( obj ) && ! isPlaceholderLayer( obj ) ) ) {
			return true;
		}
		return templateViews.some( ( view ) => {
			const objects = viewDesigns[ view.id ] || [];
			return objects.some( ( obj ) => {
				if ( obj.wcGpdLayerType === 'graphic' || obj.wcGpdLayerType === 'shape' || obj.wcGpdLayerType === 'icon' ) {
					return true;
				}
				const text = typeof obj.text === 'string' ? obj.text.trim() : '';
				return text.length > 0 && obj.wcGpdLayerType !== 'placeholder';
			} );
		} );
	}

	function canCustomerDeleteLayer( obj ) {
		if ( ! obj ) {
			return false;
		}
		if ( isCustomerGraphic( obj ) || isCustomerAddedShape( obj ) || isCustomerAddedIcon( obj ) ) {
			return true;
		}
		return isTextLayer( obj ) && ! obj.wcGpdTemplateLayer && isUsableTextLayer( obj );
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

		if ( ui.layersEmptyHint ) {
			ui.layersEmptyHint.hidden = layers.length > 0;
		}

		if ( ! layers.length ) {
			return;
		}

		const ordered = layers.slice().reverse();
		ordered.forEach( ( obj ) => {
			const li = document.createElement( 'li' );
			li.className = 'wc-gpd-tpl-layer-row' + ( canvas.getActiveObject() === obj ? ' is-active' : '' );

			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wc-gpd-tpl-layer-item';
			const badge = document.createElement( 'span' );
			badge.className = 'wc-gpd-layer-type-badge';
			badge.textContent = layerTypeBadgeForCustomer( obj );
			const name = document.createElement( 'span' );
			name.className = 'wc-gpd-layer-name';
			const labelText = layerLabelForCustomer( obj );
			name.textContent = labelText;
			name.title = labelText;
			btn.appendChild( badge );
			btn.appendChild( name );
			btn.setAttribute( 'data-layer-index', String( canvas.getObjects().indexOf( obj ) ) );

			btn.addEventListener( 'click', () => {
				canvas.setActiveObject( obj );
				syncToolbar( obj );
				syncLayersList();
				canvas.requestRenderAll();
				openCustomerSection( 'context' );
			} );

			const actions = document.createElement( 'span' );
			actions.className = 'wc-gpd-tpl-layer-actions';
			actions.appendChild( customerLayerActionBtn(
				config.i18n.bringForward || 'Bring forward',
				'↑',
				() => moveCustomerLayer( obj, 'up' )
			) );
			actions.appendChild( customerLayerActionBtn(
				config.i18n.sendBackward || 'Send backward',
				'↓',
				() => moveCustomerLayer( obj, 'down' )
			) );
			if ( canCustomerDeleteLayer( obj ) ) {
				actions.appendChild( customerLayerActionBtn(
					config.i18n.deleteLayer || 'Delete layer',
					'✕',
					() => deleteCustomerLayer( obj )
				) );
			}

			li.appendChild( btn );
			li.appendChild( actions );
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
		const isPlaceholder = !! obj && isPlaceholderLayer( obj );
		const isText = !! obj && (
			isPlaceholder
			|| (
				( isCustomerEditableTemplateText( obj ) || ( isTextLayer( obj ) && ! obj.wcGpdTemplateLayer ) )
				&& isUsableTextLayer( obj )
			)
		);
		const isShapeLayer = !! obj && isCustomerEditableShape( obj );
		const isAddedShape = isCustomerAddedShape( obj );
		const isGraphicLayer = !! obj && isCustomerGraphic( obj );
		const isReplaceableLayer = !! obj && ( isReplaceableFrame( obj ) || isReplaceableContent( obj ) );
		const enabled = isText || isShapeLayer || isGraphicLayer || isReplaceableLayer;

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
		setGraphicContextVisible( isGraphicLayer );

		if ( isPlaceholder ) {
			applyPlaceholderInteractivity( obj );
		} else if ( isAddedShape || isCustomerAddedIcon( obj ) ) {
			applyCustomerAddedShapeInteractivity( obj );
		} else if ( isGraphicLayer ) {
			applyGraphicInteractivity( obj );
		}
		if ( isReplaceableLayer ) {
			const frameObj = getReplaceableFrameForObject( obj );
			if ( frameObj ) {
				applyReplaceableFrameInteractivity( frameObj );
			}
		}

		if ( ! enabled ) {
			renderColorSwatches( null );
			resetToolRowStates();
			if ( ui.placeholderEditRow ) {
				ui.placeholderEditRow.hidden = true;
			}
			return;
		}

		if ( ui.contextLayerName ) {
			ui.contextLayerName.textContent = layerLabelForCustomer( obj );
		}

		syncContextNav( obj );
		openCustomerSection( 'context' );
		if ( ui.placeholderEditRow ) {
			ui.placeholderEditRow.hidden = ! isPlaceholder;
		}
		if ( isPlaceholder ) {
			if ( ui.placeholderEditLabel ) {
				ui.placeholderEditLabel.textContent = obj.wcGpdPlaceholderLabel || config.i18n.placeholderLabel || 'Field';
			}
			if ( ui.placeholderEditInput ) {
				ui.placeholderEditInput.value = obj.text || '';
				ui.placeholderEditInput.disabled = !! obj.wcGpdLockText;
				setTimeout( () => {
					if ( canvas.getActiveObject() === obj ) {
						ui.placeholderEditInput.focus();
						ui.placeholderEditInput.select();
					}
				}, 0 );
			}
		}

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

	function regularPolygonPoints( sides, radius ) {
		const points = [];
		for ( let i = 0; i < sides; i++ ) {
			const angle = ( 2 * Math.PI * i ) / sides - Math.PI / 2;
			points.push( {
				x: radius * Math.cos( angle ),
				y: radius * Math.sin( angle ),
			} );
		}
		return points;
	}

	function finishCustomerShape( obj, label ) {
		if ( label ) {
			obj.wcGpdLayerLabel = label;
		}
		applyCustomerAddedShapeInteractivity( obj );
		canvas.add( obj );
		canvas.setActiveObject( obj );
		canvas.requestRenderAll();
		syncToolbar( obj );
		syncLayersList();
		openCustomerSection( 'context' );
	}

	function defaultCustomerShapeColors() {
		const fill = defaultTextColor( null );
		return { fill, stroke: fill };
	}

	function addCustomerShape( kind ) {
		const region = getConstraintRect();
		const center = {
			left: region.left + region.width / 2,
			top: region.top + region.height / 2,
		};
		const colors = defaultCustomerShapeColors();
		const common = {
			originX: 'center',
			originY: 'center',
			fill: colors.fill,
			stroke: colors.stroke,
			strokeWidth: 2,
			wcGpdLayerType: 'shape',
			wcGpdShapeUseFill: true,
			wcGpdShapeUseStroke: true,
		};

		let obj = null;
		let label = config.i18n.layerShape || 'Shape';

		switch ( kind ) {
			case 'square':
				obj = new fabric.Rect( {
					...center,
					width: 120,
					height: 120,
					...common,
				} );
				label = config.i18n.shapeSquare || 'Square';
				break;
			case 'circle':
				obj = new fabric.Circle( {
					...center,
					radius: 60,
					...common,
				} );
				label = config.i18n.shapeCircle || 'Circle';
				break;
			case 'hexagon':
				obj = new fabric.Polygon( regularPolygonPoints( 6, 70 ), {
					...center,
					...common,
				} );
				label = config.i18n.shapeHexagon || 'Hexagon';
				break;
			case 'octagon':
				obj = new fabric.Polygon( regularPolygonPoints( 8, 70 ), {
					...center,
					...common,
				} );
				label = config.i18n.shapeOctagon || 'Octagon';
				break;
			case 'heart':
				obj = new fabric.Path(
					'M 12 21.35 L 10.55 20.03 C 5.4 15.36 2 12.28 2 8.5 C 2 5.42 4.42 3 7.5 3 C 9.24 3 10.91 3.81 12 5.09 C 13.09 3.81 14.76 3 16.5 3 C 19.58 3 22 5.42 22 8.5 C 22 12.28 18.6 15.36 13.45 20.03 L 12 21.35 Z',
					{
						...center,
						scaleX: 4,
						scaleY: 4,
						...common,
					}
				);
				label = config.i18n.shapeHeart || 'Heart';
				break;
			case 'rect':
			default:
				obj = new fabric.Rect( {
					...center,
					width: 140,
					height: 90,
					...common,
				} );
				label = config.i18n.shapeRectangle || 'Rectangle';
				break;
		}

		if ( obj ) {
			finishCustomerShape( obj, label );
		}
	}

	function addGraphicFromLibrary( item ) {
		if ( ! item || ! item.url ) {
			return;
		}
		const region = getConstraintRect();
		loadCustomerGraphic( item, {
			region,
			maxW: Math.min( region.width * 0.45, 240 ),
		} );
	}

	function addUploadedImage( file ) {
		if ( ! file ) {
			return;
		}
		const region = getConstraintRect();
		const objectUrl = URL.createObjectURL( file );
		const fileLabel = file.name ? file.name.replace( /\.[^.]+$/, '' ) : ( config.i18n.layerImage || 'Uploaded image' );
		fabric.Image.fromURL(
			objectUrl,
			( img ) => {
				URL.revokeObjectURL( objectUrl );
				if ( ! img ) {
					return;
				}
				const maxW = Math.min( region.width * 0.55, 320 );
				const scale = maxW / Math.max( img.width || 1, 1 );
				img.set( {
					left: region.left + region.width / 2,
					top: region.top + region.height / 2,
					originX: 'center',
					originY: 'center',
					scaleX: scale,
					scaleY: scale,
					wcGpdGraphicLayer: true,
					wcGpdLayerType: 'graphic',
					wcGpdCustomerUpload: true,
					wcGpdLayerLabel: fileLabel,
				} );
				applyGraphicInteractivity( img );
				canvas.add( img );
				canvas.setActiveObject( img );
				canvas.requestRenderAll();
				syncToolbar( img );
				syncLayersList();
				openCustomerSection( 'context' );
			},
			{ crossOrigin: 'anonymous' }
		);
	}

	function styleCustomerIconPaths( obj, color ) {
		function walk( target ) {
			if ( ! target ) {
				return;
			}
			if ( target.type === 'group' && target.getObjects ) {
				target.getObjects().forEach( walk );
				return;
			}
			target.set( { fill: color, stroke: null, strokeWidth: 0 } );
		}
		walk( obj );
	}

	function addCustomerIcon( slug ) {
		const baseUrl = bootstrapIcons.iconBaseUrl || '';
		if ( ! baseUrl || ! slug ) {
			return;
		}
		const region = getConstraintRect();
		fetch( baseUrl + slug + '.svg' )
			.then( ( response ) => response.text() )
			.then( ( svg ) => {
				if ( ! svg ) {
					return;
				}
				fabric.loadSVGFromString( svg, ( objects, options ) => {
					if ( ! objects || ! objects.length ) {
						return;
					}
					let obj = objects.length === 1 ? objects[ 0 ] : fabric.util.groupSVGElements( objects, options );
					const color = defaultTextColor( null );
					styleCustomerIconPaths( obj, color );
					const targetSize = Math.min( region.width * 0.25, 96 );
					const bounds = obj.getBoundingRect( true, true );
					const base = Math.max( bounds.width || 16, bounds.height || 16, 1 );
					const scale = targetSize / base;
					obj.set( {
						left: region.left + region.width / 2,
						top: region.top + region.height / 2,
						originX: 'center',
						originY: 'center',
						scaleX: scale,
						scaleY: scale,
						wcGpdLayerType: 'icon',
						wcGpdShapeUseFill: true,
						wcGpdLayerLabel: slug.replace( /-/g, ' ' ),
					} );
					applyCustomerAddedShapeInteractivity( obj );
					canvas.add( obj );
					canvas.setActiveObject( obj );
					canvas.requestRenderAll();
					syncToolbar( obj );
					syncLayersList();
				} );
			} )
			.catch( () => {} );
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
			if ( ! suppressReplaceablePicker && ( isReplaceableFrame( target ) || isReplaceableContent( target ) ) ) {
				const frameObj = getReplaceableFrameForObject( target );
				if ( frameObj ) {
					openReplaceablePicker( frameObj );
				}
			}
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

	if ( ui.addShapes ) {
		ui.addShapes.addEventListener( 'click', ( event ) => {
			const btn = event.target.closest( '[data-customer-shape]' );
			if ( btn ) {
				addCustomerShape( btn.getAttribute( 'data-customer-shape' ) );
			}
		} );
	}

	if ( ui.addImage && ui.addImageFile ) {
		ui.addImage.addEventListener( 'click', () => ui.addImageFile.click() );
		ui.addImageFile.addEventListener( 'change', () => {
			const file = ui.addImageFile.files && ui.addImageFile.files[ 0 ];
			if ( file ) {
				addUploadedImage( file );
			}
			ui.addImageFile.value = '';
		} );
	}

	if ( ui.placeholderEditInput ) {
		ui.placeholderEditInput.addEventListener( 'input', () => {
			const active = canvas.getActiveObject();
			if ( ! isPlaceholderLayer( active ) ) {
				return;
			}
			active.set( 'text', ui.placeholderEditInput.value || '' );
			shrinkTextToFit( active );
			canvas.requestRenderAll();
		} );
	}

	if ( ui.replaceableModalClose ) {
		ui.replaceableModalClose.addEventListener( 'click', closeReplaceablePicker );
	}
	if ( ui.replaceableModal ) {
		ui.replaceableModal.addEventListener( 'click', ( event ) => {
			if ( event.target === ui.replaceableModal ) {
				closeReplaceablePicker();
			}
		} );
	}
	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' && ui.replaceableModal && ! ui.replaceableModal.hidden ) {
			closeReplaceablePicker();
		}
	} );

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
		ui.textColor.addEventListener( 'change', () => {
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
