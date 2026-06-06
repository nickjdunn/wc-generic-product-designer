/**
 * Admin template editor — mockups, text, placeholders, graphics, shapes.
 */
( function ( $ ) {
	'use strict';

	const canvasEl = document.getElementById( 'wc-gpd-template-canvas' );
	const jsonInput = document.getElementById( 'wc_gpd_template_json' );
	const widthInput = document.getElementById( 'wc_gpd_template_canvas_width' );
	const heightInput = document.getElementById( 'wc_gpd_template_canvas_height' );
	const maxViewsHidden = document.getElementById( 'wc_gpd_max_design_views' );
	const viewTabsEl = document.getElementById( 'wc-gpd-template-view-tabs' );
	const editorConfig = window.wcGpdTemplateEditor || { maxViews: 6, fonts: [ '"Times New Roman", Times, serif' ], defaultFont: '"Times New Roman", Times, serif' };
	const MAX_VIEWS = editorConfig.maxViews || 6;
	const DEFAULT_FONT = editorConfig.defaultFont || '"Times New Roman", Times, serif';
	const layersListEl = document.getElementById( 'wc-gpd-template-layers-list' );
	const layersEmptyHint = document.getElementById( 'wc-gpd-layers-empty-hint' );
	const outlineToggle = document.getElementById( 'wc_gpd_template_is_outline' );
	const bboxToggle = document.getElementById( 'wc_gpd_template_is_bbox' );
	const strokeWidthInput = document.getElementById( 'wc_gpd_template_stroke_width' );
	const shapePropsFields = document.getElementById( 'wc-gpd-shape-appearance-panel' );
	const shapeStrokeWidthRow = document.getElementById( 'wc-gpd-shape-stroke-width-row' );
	const shapeUseFillToggle = document.getElementById( 'wc_gpd_shape_use_fill' );
	const shapeUseStrokeToggle = document.getElementById( 'wc_gpd_shape_use_stroke' );
	const imagePropsPanel = document.getElementById( 'wc-gpd-image-props' );
	const layerColorsPanel = document.getElementById( 'wc-gpd-fill-colors-panel' );
	const contextNavBtn = document.getElementById( 'wc-gpd-nav-context' );
	const contextEmpty = document.getElementById( 'wc-gpd-context-empty' );
	const contextPane = document.getElementById( 'wc-gpd-context-pane' );
	const contextLayerName = document.getElementById( 'wc-gpd-context-layer-name' );
	const palettesInput = document.getElementById( 'wc_gpd_template_palettes' );
	const graphicSlotPropsPanel = document.getElementById( 'wc-gpd-graphic-slot-props' );
	const mockupVisibleToggle = document.getElementById( 'wc_gpd_template_mockup_visible' );
	const deleteImageBtn = document.getElementById( 'wc-gpd-template-delete-image' );
	const unitsSelect = document.getElementById( 'wc_gpd_tpl_units' );
	const freeformHint = document.getElementById( 'wc-gpd-freeform-hint' );
	const editorRoot = document.getElementById( 'wc-gpd-template-editor-root' );
	const popoutBtn = document.getElementById( 'wc-gpd-template-popout' );
	const templateFontsInput = document.getElementById( 'wc_gpd_template_fonts' );
	const accordionEl = document.getElementById( 'wc-gpd-tpl-accordion' );
	const showRulerToggle = document.getElementById( 'wc_gpd_tpl_show_ruler' );
	const showMeasurementsToggle = document.getElementById( 'wc_gpd_tpl_show_measurements' );
	const rulerTopEl = document.getElementById( 'wc-gpd-tpl-ruler-top' );
	const rulerLeftEl = document.getElementById( 'wc-gpd-tpl-ruler-left' );
	const measureBottomEl = document.getElementById( 'wc-gpd-tpl-measure-bottom' );
	const measureRightEl = document.getElementById( 'wc-gpd-tpl-measure-right' );

	const SERIALIZE_PROPS = [
		'wcGpdUid', 'wcGpdLayerType', 'wcGpdLayerLabel', 'wcGpdTemplateLayer', 'wcGpdOutlineLayer', 'wcGpdBoundingBox',
		'wcGpdMockupImage', 'wcGpdMockupVisible', 'wcGpdAttachmentId', 'wcGpdGraphicLayer', 'wcGpdGraphicSlot',
		'wcGpdGraphicLibraryId', 'wcGpdExportGraphic', 'wcGpdCustomerMovable', 'wcGpdCustomerResizable', 'wcGpdLockAspect',
		'wcGpdPlaceholderLabel', 'wcGpdPlaceholderKey', 'wcGpdShrinkToFit', 'wcGpdFitMode', 'wcGpdPaletteId', 'wcGpdLayerColors',
		'wcGpdStrokePaletteId', 'wcGpdStrokeLayerColors', 'wcGpdShapeUseFill', 'wcGpdShapeUseStroke',
		'wcGpdLockFont', 'wcGpdLockSize', 'wcGpdLockColor', 'wcGpdLockBold', 'wcGpdLockItalic', 'wcGpdLockAlign',
		'wcGpdLockUnderline', 'wcGpdLockLineHeight', 'wcGpdLockLetterSpacing',
		'wcGpdLockMove', 'wcGpdLockScale', 'wcGpdLockText', 'wcGpdCustomerEditable', 'wcGpdHideFromCustomerLayers',
		'wcGpdCustomerPaletteOnly',
		'strokeDashArray',
	];

	const CUSTOMER_ALLOW_MAP = {
		text_edit: 'wcGpdLockText',
		font: 'wcGpdLockFont',
		size: 'wcGpdLockSize',
		color: 'wcGpdLockColor',
		bold: 'wcGpdLockBold',
		italic: 'wcGpdLockItalic',
		underline: 'wcGpdLockUnderline',
		align: 'wcGpdLockAlign',
		line_height: 'wcGpdLockLineHeight',
		letter_spacing: 'wcGpdLockLetterSpacing',
		move: 'wcGpdLockMove',
		resize: 'wcGpdLockScale',
	};

	if ( ! canvasEl || typeof fabric === 'undefined' ) {
		return;
	}

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
			SERIALIZE_PROPS.forEach( ( prop ) => {
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
		SERIALIZE_PROPS.forEach( ( key ) => {
			if ( source[ key ] !== undefined ) {
				obj[ key ] = source[ key ];
			}
		} );
	}

	registerFabricCustomProperties();

	function readCanvasDimensions() {
		const wField = document.getElementById( 'wc_gpd_canvas_width' );
		const hField = document.getElementById( 'wc_gpd_canvas_height' );
		const w = parseInt( wField ? wField.value : ( widthInput ? widthInput.value : '800' ), 10 ) || 800;
		const h = parseInt( hField ? hField.value : ( heightInput ? heightInput.value : '600' ), 10 ) || 600;
		return { width: w, height: h };
	}

	let dims = readCanvasDimensions();
	let width = dims.width;
	let height = dims.height;
	let graphicLibraries = [];
	let lockAspectRatio = false;
	let displayUnit = 'px';
	let freeformMode = false;
	let freeformPoints = [];
	let freeformPreview = null;
	let palettesData = {
		palettes: [ { id: 'pal_default', name: 'Default', colors: [ '#000000' ] } ],
		use_global_colors: false,
		global_colors: [ '#000000' ],
	};

	const PAL_CUSTOM = 'pal_custom';

	const STEPPER_CONFIG = {
		line_height: {
			inputId: 'wc_gpd_tpl_line_height',
			displayId: 'wc_gpd_tpl_line_height_display',
			step: 0.05,
			min: 0.5,
			max: 3,
			decimals: 2,
			prop: 'lineHeight',
			parse: parseFloat,
		},
		letter_spacing: {
			inputId: 'wc_gpd_tpl_letter_spacing',
			displayId: 'wc_gpd_tpl_letter_spacing_display',
			step: 10,
			min: -200,
			max: 800,
			decimals: 0,
			prop: 'charSpacing',
			parse: ( value ) => parseInt( value, 10 ),
		},
	};

	const MOCKUP_LAYER_TOOL_MAP = {
		font: 'wcGpdLockFont',
		size: 'wcGpdLockSize',
		color: 'wcGpdLockColor',
		bold: 'wcGpdLockBold',
		italic: 'wcGpdLockItalic',
		underline: 'wcGpdLockUnderline',
		line_height: 'wcGpdLockLineHeight',
		letter_spacing: 'wcGpdLockLetterSpacing',
		align: 'wcGpdLockAlign',
	};

	const UNIT_FACTORS = {
		px: 1,
		in: 96,
		mm: 96 / 25.4,
		cm: 96 / 2.54,
	};

	function pxToDisplay( px ) {
		const factor = UNIT_FACTORS[ displayUnit ] || 1;
		const value = px / factor;
		return displayUnit === 'px' ? Math.round( value ) : Math.round( value * 100 ) / 100;
	}

	function displayToPx( value ) {
		const factor = UNIT_FACTORS[ displayUnit ] || 1;
		return Math.round( parseFloat( value ) * factor ) || 0;
	}

	function updateUnitSuffixes() {
		const suffix = displayUnit;
		[ 'wc_gpd_unit_suffix_w', 'wc_gpd_unit_suffix_h', 'wc_gpd_unit_suffix_x', 'wc_gpd_unit_suffix_y' ].forEach( ( id ) => {
			const el = document.getElementById( id );
			if ( el ) {
				el.textContent = suffix;
			}
		} );
	}

	function initFontSelect() {
		const fontSelect = document.getElementById( 'wc_gpd_tpl_font_family' );
		if ( ! fontSelect ) {
			return;
		}
		fontSelect.innerHTML = '';
		const options = editorConfig.fontOptions || ( editorConfig.fonts || [] ).map( ( family ) => ( {
			family,
			label: family.split( ',' )[ 0 ].replace( /"/g, '' ).trim(),
			css: family,
		} ) );
		options.forEach( ( font ) => {
			const option = document.createElement( 'option' );
			option.value = font.family || font.css;
			option.textContent = font.label || option.value;
			option.style.fontFamily = font.css || font.family;
			if ( font.admin_label && font.admin_label !== font.label ) {
				option.title = font.admin_label;
			}
			fontSelect.appendChild( option );
		} );
	}

	function readDefault( id, fallback ) {
		const el = document.getElementById( id );
		return el && el.value ? el.value : fallback;
	}

	function canvasBgColor() {
		const settingsField = document.querySelector( '[name="wc_gpd_ps_canvas_bg_color"]' );
		if ( settingsField && settingsField.value ) {
			return settingsField.value;
		}
		return readDefault( 'wc_gpd_tpl_canvas_bg', '#f0f0f0' );
	}

	function defaultOutlineColor() {
		const el = document.getElementById( 'wc_gpd_ps_outline_color' );
		return el && el.value ? el.value : readDefault( 'wc_gpd_tpl_default_outline_color', '#ff0000' );
	}

	function defaultOutlineWidth() {
		const el = document.getElementById( 'wc_gpd_ps_outline_stroke_width' );
		return el && el.value ? parseFloat( el.value ) : parseFloat( readDefault( 'wc_gpd_tpl_default_outline_width', '1' ) ) || 1;
	}

	function defaultBboxColor() {
		const el = document.getElementById( 'wc_gpd_ps_bbox_stroke_color' );
		return el && el.value ? el.value : readDefault( 'wc_gpd_tpl_default_bbox_color', '#ff0000' );
	}

	function defaultBboxWidth() {
		const el = document.getElementById( 'wc_gpd_ps_bbox_stroke_width' );
		return el && el.value ? parseFloat( el.value ) : parseFloat( readDefault( 'wc_gpd_tpl_default_bbox_width', '1' ) ) || 1;
	}

	const canvas = new fabric.Canvas( 'wc-gpd-template-canvas', {
		selection: true,
		preserveObjectStacking: true,
		width,
		height,
	} );

	let documentData = { version: 2, views: [] };
	let activeViewId = '';

	function uid() {
		return `gpd-${ Date.now().toString( 36 ) }-${ Math.random().toString( 36 ).slice( 2, 8 ) }`;
	}

	function getMaxViews() {
		return MAX_VIEWS;
	}

	function syncMaxViewsField() {
		if ( ! maxViewsHidden ) {
			return;
		}
		const count = documentData.views ? documentData.views.length : 1;
		maxViewsHidden.value = String( Math.max( 1, count ) );
	}

	function getActiveView() {
		return documentData.views.find( ( view ) => view.id === activeViewId ) || documentData.views[ 0 ];
	}

	function isShapeType( obj ) {
		if ( ! obj || obj.wcGpdLayerType === 'graphic_slot' || obj.wcGpdGraphicSlot ) {
			return false;
		}
		const shapeTypes = [ 'rect', 'circle', 'ellipse', 'polygon', 'path', 'polyline', 'group', 'line' ];
		return shapeTypes.indexOf( obj.type ) >= 0;
	}

	function isShape( obj ) {
		return isShapeType( obj ) && !! obj.wcGpdTemplateLayer;
	}

	function isIconLayer( obj ) {
		return isShape( obj ) && obj.type === 'group';
	}

	function shapeIsFillBased( obj ) {
		if ( ! obj ) {
			return false;
		}
		if ( obj.type === 'group' ) {
			return true;
		}
		const hasFill = obj.fill && obj.fill !== 'transparent';
		const hasStroke = obj.stroke && obj.stroke !== 'transparent' && ( obj.strokeWidth || 0 ) > 0;
		return hasFill && ! hasStroke;
	}

	function shapeUsesFill( obj ) {
		return !! obj && obj.wcGpdShapeUseFill !== false;
	}

	function shapeUsesStroke( obj ) {
		return !! obj && obj.wcGpdShapeUseStroke !== false;
	}

	function inferShapeStyleFlags( obj ) {
		if ( ! isShape( obj ) ) {
			return;
		}
		if ( typeof obj.wcGpdShapeUseFill === 'undefined' ) {
			obj.wcGpdShapeUseFill = isIconLayer( obj ) || shapeIsFillBased( obj );
		}
		if ( typeof obj.wcGpdShapeUseStroke === 'undefined' ) {
			obj.wcGpdShapeUseStroke = ! isIconLayer( obj ) && ! shapeIsFillBased( obj );
		}
		if ( typeof obj.wcGpdCustomerPaletteOnly === 'undefined' ) {
			obj.wcGpdCustomerPaletteOnly = true;
		}
	}

	function initShapeStyleDefaults( obj, kind ) {
		if ( ! isShapeType( obj ) ) {
			return;
		}
		if ( kind === 'icon' ) {
			obj.wcGpdShapeUseFill = true;
			obj.wcGpdShapeUseStroke = false;
		} else {
			obj.wcGpdShapeUseFill = false;
			obj.wcGpdShapeUseStroke = true;
		}
		obj.wcGpdCustomerPaletteOnly = true;
		obj.wcGpdPaletteId = obj.wcGpdPaletteId || 'pal_default';
		obj.wcGpdStrokePaletteId = obj.wcGpdStrokePaletteId || 'pal_default';
	}

	function getShapeFillColor( obj ) {
		if ( ! obj ) {
			return defaultOutlineColor();
		}
		if ( obj.type === 'group' && obj.getObjects ) {
			const children = obj.getObjects();
			for ( let i = 0; i < children.length; i++ ) {
				const childColor = getShapeFillColor( children[ i ] );
				if ( childColor && childColor !== 'transparent' ) {
					return childColor;
				}
			}
		}
		if ( obj.fill && obj.fill !== 'transparent' ) {
			return obj.fill;
		}
		return defaultOutlineColor();
	}

	function getShapeStrokeColor( obj ) {
		if ( ! obj ) {
			return defaultOutlineColor();
		}
		if ( obj.type === 'group' && obj.getObjects ) {
			const children = obj.getObjects();
			for ( let i = 0; i < children.length; i++ ) {
				const childColor = getShapeStrokeColor( children[ i ] );
				if ( childColor && childColor !== 'transparent' ) {
					return childColor;
				}
			}
		}
		if ( obj.stroke && obj.stroke !== 'transparent' ) {
			return obj.stroke;
		}
		return defaultOutlineColor();
	}

	function applyShapeStyleFromFlags( obj ) {
		if ( ! isShape( obj ) || obj.wcGpdBoundingBox ) {
			return;
		}
		inferShapeStyleFlags( obj );
		const useFill = shapeUsesFill( obj );
		const useStroke = shapeUsesStroke( obj );
		const fillColor = getShapeFillColor( obj );
		const strokeColor = getShapeStrokeColor( obj );
		const strokeWidth = useStroke ? getShapeStrokeWidth( obj ) : 0;
		function applyToTarget( target ) {
			if ( ! target ) {
				return;
			}
			if ( target.type === 'group' && target.getObjects ) {
				target.getObjects().forEach( applyToTarget );
				return;
			}
			target.set( {
				fill: useFill ? fillColor : 'transparent',
				stroke: useStroke ? strokeColor : null,
				strokeWidth: useStroke ? strokeWidth : 0,
				strokeLineJoin: 'round',
				strokeLineCap: 'round',
			} );
		}
		applyToTarget( obj );
	}

	function getShapeDisplayColor( obj ) {
		if ( ! obj ) {
			return defaultOutlineColor();
		}
		if ( shapeUsesFill( obj ) ) {
			return getShapeFillColor( obj );
		}
		if ( shapeUsesStroke( obj ) ) {
			return getShapeStrokeColor( obj );
		}
		return defaultOutlineColor();
	}

	function applyShapeFillColor( obj, color ) {
		if ( ! obj || ! isShape( obj ) || obj.wcGpdBoundingBox ) {
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
		if ( ! obj || ! isShape( obj ) || obj.wcGpdBoundingBox ) {
			return;
		}
		const strokeWidth = getShapeStrokeWidth( obj );
		function applyToTarget( target ) {
			if ( ! target ) {
				return;
			}
			if ( target.type === 'group' && target.getObjects ) {
				target.getObjects().forEach( applyToTarget );
				return;
			}
			target.set( {
				stroke: color,
				strokeWidth,
				strokeLineJoin: 'round',
				strokeLineCap: 'round',
			} );
		}
		applyToTarget( obj );
	}

	function applyShapeColor( obj, color, role ) {
		if ( role === 'stroke' ) {
			applyShapeStrokeColor( obj, color );
			return;
		}
		if ( role === 'fill' ) {
			applyShapeFillColor( obj, color );
			return;
		}
		if ( isShape( obj ) && shapeUsesFill( obj ) && ! shapeUsesStroke( obj ) ) {
			applyShapeFillColor( obj, color );
			return;
		}
		if ( isShape( obj ) && shapeUsesStroke( obj ) ) {
			applyShapeStrokeColor( obj, color );
			return;
		}
		applyShapeFillColor( obj, color );
	}

	function getShapeStrokeWidth( obj ) {
		if ( ! obj ) {
			return defaultOutlineWidth();
		}
		if ( obj.type === 'group' && obj.getObjects ) {
			const children = obj.getObjects();
			for ( let i = 0; i < children.length; i++ ) {
				const width = getShapeStrokeWidth( children[ i ] );
				if ( width > 0 ) {
					return width;
				}
			}
			return defaultOutlineWidth();
		}
		return obj.strokeWidth || defaultOutlineWidth();
	}

	function applyShapeStrokeWidth( obj, width ) {
		if ( ! obj || ! isShape( obj ) ) {
			return;
		}
		const strokeWidth = parseFloat( width ) || defaultOutlineWidth();
		const color = getShapeStrokeColor( obj );
		function applyToTarget( target ) {
			if ( ! target ) {
				return;
			}
			if ( target.type === 'group' && target.getObjects ) {
				target.getObjects().forEach( applyToTarget );
				return;
			}
			target.set( {
				stroke: color,
				strokeWidth,
				strokeLineJoin: 'round',
				strokeLineCap: 'round',
			} );
		}
		applyToTarget( obj );
	}

	function isTemplateImage( obj ) {
		return isMockupImage( obj ) || isGraphicImage( obj );
	}

	function isMockupImage( obj ) {
		return obj && obj.type === 'image' && obj.wcGpdMockupImage;
	}

	function isGraphicImage( obj ) {
		return obj && obj.type === 'image' && obj.wcGpdGraphicLayer && ! obj.wcGpdGraphicSlot;
	}

	function isGraphicSlot( obj ) {
		return obj && ( obj.wcGpdLayerType === 'graphic_slot' || ( obj.type === 'rect' && obj.wcGpdGraphicSlot ) );
	}

	function isTextLayer( obj ) {
		return obj && ( obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox' )
			&& ( obj.wcGpdLayerType === 'text' || obj.wcGpdLayerType === 'placeholder' );
	}

	function isTemplateText( obj ) {
		return isTextLayer( obj ) && obj.wcGpdLayerType === 'text';
	}

	function isPlaceholder( obj ) {
		return isTextLayer( obj ) && obj.wcGpdLayerType === 'placeholder';
	}

	function getFitMode( obj ) {
		if ( ! obj ) {
			return 'none';
		}
		if ( obj.wcGpdFitMode ) {
			return obj.wcGpdFitMode;
		}
		if ( obj.wcGpdShrinkToFit ) {
			return 'horizontal';
		}
		return 'none';
	}

	function normalizeTextLayer( obj ) {
		if ( ! isTextLayer( obj ) ) {
			return;
		}
		if ( ! obj.wcGpdFitMode && obj.wcGpdShrinkToFit ) {
			obj.wcGpdFitMode = 'horizontal';
		}
		if ( ! obj.wcGpdFitMode ) {
			obj.wcGpdFitMode = 'none';
		}
	}

	function layerLabel( obj ) {
		if ( ! obj ) {
			return 'Layer';
		}
		if ( obj.wcGpdLayerLabel ) {
			return obj.wcGpdLayerLabel;
		}
		if ( isMockupImage( obj ) ) {
			return 'Mockup background';
		}
		if ( isGraphicImage( obj ) ) {
			if ( obj.wcGpdLockMove && obj.wcGpdLockScale ) {
				return 'Fixed graphic';
			}
			return 'Repositionable graphic';
		}
		if ( isGraphicSlot( obj ) ) {
			return 'Graphic pick area';
		}
		if ( isTextLayer( obj ) ) {
			if ( obj.wcGpdLayerLabel ) {
				return obj.wcGpdLayerLabel;
			}
			if ( isPlaceholder( obj ) ) {
				return obj.wcGpdPlaceholderLabel || 'Text field';
			}
			const text = ( obj.text || '' ).trim();
			return text ? text.slice( 0, 32 ) : 'Text field';
		}
		if ( obj.wcGpdBoundingBox ) {
			return 'Bounding box';
		}
		if ( obj.wcGpdOutlineLayer ) {
			return 'Outline';
		}
		return 'Shape';
	}

	function ensureDocument() {
		if ( ! documentData.views.length ) {
			documentData = {
				version: 2,
				views: [ { id: 'view_front', label: 'Front', template_image_id: 0, bounding_box_uid: '', objects: [] } ],
			};
		}
		if ( ! activeViewId ) {
			activeViewId = documentData.views[ 0 ].id;
		}
	}

	function applyStrokeToObject( obj, isBbox ) {
		if ( ! obj || ! isShapeType( obj ) ) {
			return;
		}
		obj.set( {
			stroke: isBbox ? defaultBboxColor() : getShapeStrokeColor( obj ),
			strokeWidth: isBbox ? defaultBboxWidth() : ( strokeWidthInput ? parseFloat( strokeWidthInput.value ) : defaultOutlineWidth() ),
			fill: 'transparent',
			strokeDashArray: isBbox ? [ 6, 4 ] : null,
		} );
	}

	function syncShapeAppearancePanel( obj ) {
		if ( ! obj || ! isShape( obj ) ) {
			return;
		}
		inferShapeStyleFlags( obj );
		if ( shapeUseFillToggle ) {
			shapeUseFillToggle.checked = shapeUsesFill( obj );
		}
		if ( shapeUseStrokeToggle ) {
			shapeUseStrokeToggle.checked = shapeUsesStroke( obj );
		}
		if ( strokeWidthInput ) {
			strokeWidthInput.value = String( getShapeStrokeWidth( obj ) );
		}
		setPropRowDisabled( shapeStrokeWidthRow, ! shapeUsesStroke( obj ) );
	}

	function persistCanvasToActiveView() {
		const view = getActiveView();
		if ( ! view ) {
			return;
		}
		view.objects = canvas.getObjects().map( ( obj ) => obj.toObject( SERIALIZE_PROPS ) );
		view.bounding_box_uid = '';
		view.template_image_id = 0;
		view.objects.forEach( ( obj ) => {
			if ( obj.wcGpdBoundingBox && obj.wcGpdUid ) {
				view.bounding_box_uid = obj.wcGpdUid;
			}
		} );
	}

	function syncShapeFlags( obj ) {
		if ( ! isShapeType( obj ) ) {
			return;
		}
		obj.wcGpdTemplateLayer = true;
		if ( ! obj.wcGpdUid ) {
			obj.wcGpdUid = uid();
		}
		if ( typeof obj.wcGpdOutlineLayer === 'undefined' ) {
			obj.wcGpdOutlineLayer = true;
			obj.wcGpdLayerType = 'outline';
		}
		const active = canvas.getActiveObject();
		if ( outlineToggle && active === obj ) {
			obj.wcGpdOutlineLayer = outlineToggle.checked;
			obj.wcGpdLayerType = outlineToggle.checked ? 'outline' : 'shape';
		}
		if ( bboxToggle && bboxToggle.checked ) {
			obj.wcGpdOutlineLayer = true;
			obj.wcGpdLayerType = 'outline';
			if ( outlineToggle ) {
				outlineToggle.checked = true;
			}
			canvas.getObjects().forEach( ( other ) => {
				if ( other !== obj && isShape( other ) ) {
					other.wcGpdBoundingBox = false;
					if ( other.wcGpdOutlineLayer ) {
						applyStrokeToObject( other, false );
					}
				}
			} );
			obj.wcGpdBoundingBox = true;
			applyStrokeToObject( obj, true );
			const view = getActiveView();
			if ( view ) {
				view.bounding_box_uid = obj.wcGpdUid;
			}
		} else if ( bboxToggle ) {
			obj.wcGpdBoundingBox = false;
			applyStrokeToObject( obj, false );
		}
		updateSelectionPanels();
	}

	function hideAllPropPanels() {
		// Layer-type panels are shown via showContextBlocks(); no inner toggles needed.
	}

	function initContextAccordions() {
		if ( ! contextPane ) {
			return;
		}
		contextPane.querySelectorAll( '.wc-gpd-context-accordion__toggle' ).forEach( ( toggle ) => {
			toggle.addEventListener( 'click', () => {
				const accordion = toggle.closest( '.wc-gpd-context-accordion' );
				if ( ! accordion ) {
					return;
				}
				const body = accordion.querySelector( '.wc-gpd-context-accordion__body' );
				const open = ! accordion.classList.contains( 'is-open' );
				accordion.classList.toggle( 'is-open', open );
				toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				if ( body ) {
					body.hidden = ! open;
				}
			} );
		} );
	}

	const adminStudioNav = document.getElementById( 'wc-gpd-admin-studio-nav' );
	const adminDrawerTitle = document.getElementById( 'wc-gpd-admin-drawer-title' );
	const adminSectionTitles = {
		add: 'Add',
		layers: 'Layers',
		context: 'Properties',
		template: 'Template',
	};

	function syncAdminStudioNav( sectionName ) {
		if ( adminStudioNav ) {
			adminStudioNav.querySelectorAll( '.wc-gpd-studio-nav__btn' ).forEach( ( btn ) => {
				btn.classList.toggle( 'is-active', btn.dataset.section === sectionName );
			} );
		}
		if ( adminDrawerTitle && adminSectionTitles[ sectionName ] ) {
			adminDrawerTitle.textContent = adminSectionTitles[ sectionName ];
		}
	}

	function openAccordionSection( sectionName, exclusive ) {
		if ( ! accordionEl || ! sectionName ) {
			return;
		}
		syncAdminStudioNav( sectionName );
		const onlyOne = exclusive !== false;
		accordionEl.querySelectorAll( '.wc-gpd-accordion-section' ).forEach( ( section ) => {
			const isTarget = section.dataset.section === sectionName;
			const toggle = section.querySelector( '.wc-gpd-accordion-toggle' );
			const body = section.querySelector( '.wc-gpd-accordion-body' );
			if ( onlyOne && ! isTarget ) {
				section.classList.remove( 'is-open' );
				if ( toggle ) {
					toggle.setAttribute( 'aria-expanded', 'false' );
				}
				if ( body ) {
					body.hidden = true;
				}
			}
			if ( isTarget ) {
				section.classList.add( 'is-open' );
				if ( toggle ) {
					toggle.setAttribute( 'aria-expanded', 'true' );
				}
				if ( body ) {
					body.hidden = false;
				}
			}
		} );
	}

	function contextTypeForObject( obj ) {
		if ( ! obj ) {
			return null;
		}
		if ( isTextLayer( obj ) ) {
			return 'text';
		}
		if ( isGraphicSlot( obj ) ) {
			return 'slot';
		}
		if ( isShape( obj ) ) {
			return 'shape';
		}
		if ( isTemplateImage( obj ) ) {
			return 'image';
		}
		return null;
	}

	function showContextBlocks( active ) {
		const type = contextTypeForObject( active );
		const blocks = contextPane ? contextPane.querySelectorAll( '.wc-gpd-context-accordion, .wc-gpd-context-block' ) : [];
		blocks.forEach( ( block ) => {
			const forTypes = ( block.dataset.contextFor || '' ).split( ',' ).map( ( part ) => part.trim() );
			if ( forTypes.includes( 'all' ) ) {
				block.hidden = false;
				return;
			}
			if ( ! type ) {
				block.hidden = true;
				return;
			}
			let show = forTypes.includes( type );
			if ( block.id === 'wc-gpd-context-block-colors' ) {
				show = show && objectHasColor( active );
			}
			block.hidden = ! show;
		} );
	}

	function syncContextNav( active ) {
		if ( contextNavBtn ) {
			contextNavBtn.hidden = ! active;
		}
	}

	function openContextPane() {
		openAccordionSection( 'context' );
	}

	function openAccordionForSelection( active ) {
		if ( ! active ) {
			return;
		}
		openContextPane();
	}

	function updateAccordionTitles() {
		if ( ! accordionEl ) {
			return;
		}
		const active = canvas.getActiveObject();
		const name = active ? layerLabel( active ) : '';
		accordionEl.querySelectorAll( '.wc-gpd-accordion-section[data-layer-title="1"]' ).forEach( ( section ) => {
			const toggle = section.querySelector( '.wc-gpd-accordion-toggle' );
			if ( ! toggle ) {
				return;
			}
			const base = toggle.dataset.baseTitle || toggle.textContent.trim();
			toggle.textContent = active && name ? `${ base }: ${ name }` : base;
		} );
	}

	function objectHasColor( obj ) {
		return !! obj && ( isTextLayer( obj ) || isShape( obj ) );
	}

	function loadPalettesFromInput() {
		if ( ! palettesInput || ! palettesInput.value ) {
			return;
		}
		try {
			const parsed = JSON.parse( palettesInput.value );
			if ( parsed && typeof parsed === 'object' ) {
				palettesData = {
					palettes: Array.isArray( parsed.palettes ) && parsed.palettes.length ? parsed.palettes : palettesData.palettes,
					use_global_colors: !! parsed.use_global_colors,
					global_colors: Array.isArray( parsed.global_colors ) && parsed.global_colors.length ? parsed.global_colors : [ '#000000' ],
				};
			}
		} catch ( e ) {
			// Keep defaults.
		}
	}

	function savePalettesToInput() {
		if ( ! palettesInput ) {
			return;
		}
		if ( ! palettesData.global_colors || ! palettesData.global_colors.length ) {
			palettesData.global_colors = [ '#000000' ];
		}
		palettesInput.value = JSON.stringify( palettesData );
	}

	function getPaletteById( paletteId ) {
		return ( palettesData.palettes || [] ).find( ( palette ) => palette.id === paletteId );
	}

	function isCustomPaletteId( paletteId ) {
		return paletteId === PAL_CUSTOM;
	}

	function getPaletteIdForRole( obj, role ) {
		if ( role === 'stroke' ) {
			return obj && obj.wcGpdStrokePaletteId ? obj.wcGpdStrokePaletteId : 'pal_default';
		}
		return obj && obj.wcGpdPaletteId ? obj.wcGpdPaletteId : 'pal_default';
	}

	function getCustomColorsForRole( obj, role ) {
		if ( role === 'stroke' ) {
			return obj.wcGpdStrokeLayerColors;
		}
		return obj.wcGpdLayerColors;
	}

	function setCustomColorsForRole( obj, role, colors ) {
		if ( role === 'stroke' ) {
			obj.wcGpdStrokeLayerColors = colors;
			return;
		}
		obj.wcGpdLayerColors = colors;
	}

	function setPaletteIdForRole( obj, role, paletteId ) {
		if ( role === 'stroke' ) {
			obj.wcGpdStrokePaletteId = paletteId;
			return;
		}
		obj.wcGpdPaletteId = paletteId;
	}

	function normalizeHexColor( value ) {
		let hex = String( value || '' ).trim();
		if ( ! hex ) {
			return '';
		}
		if ( hex.charAt( 0 ) !== '#' ) {
			hex = '#' + hex;
		}
		if ( /^#[0-9a-fA-F]{3}$/.test( hex ) ) {
			hex = '#' + hex.charAt( 1 ) + hex.charAt( 1 ) + hex.charAt( 2 ) + hex.charAt( 2 ) + hex.charAt( 3 ) + hex.charAt( 3 );
		}
		return /^#[0-9a-fA-F]{6}$/.test( hex ) ? hex.toLowerCase() : '';
	}

	function getLayerColorSource( obj, role ) {
		if ( palettesData.use_global_colors ) {
			return {
				type: 'global',
				colors: palettesData.global_colors || [ '#000000' ],
				role,
				persist() {
					savePalettesToInput();
				},
			};
		}
		const paletteId = getPaletteIdForRole( obj, role );
		if ( isCustomPaletteId( paletteId ) ) {
			let colors = getCustomColorsForRole( obj, role );
			if ( ! Array.isArray( colors ) ) {
				colors = [];
				setCustomColorsForRole( obj, role, colors );
			}
			return {
				type: 'custom',
				colors,
				role,
				persist() {},
			};
		}
		const palette = getPaletteById( paletteId ) || getPaletteById( 'pal_default' );
		const colors = palette ? palette.colors : [ '#000000' ];
		return {
			type: 'palette',
			colors,
			palette,
			role,
			persist() {
				savePalettesToInput();
			},
		};
	}

	function colorsForLayer( obj, role ) {
		return getLayerColorSource( obj, role || 'fill' ).colors;
	}

	function openNativeColorPicker( initialColor, onPick ) {
		const picker = document.createElement( 'input' );
		picker.type = 'color';
		picker.value = normalizeHexColor( initialColor ) || '#000000';
		picker.style.position = 'fixed';
		picker.style.left = '-9999px';
		document.body.appendChild( picker );
		picker.addEventListener( 'change', () => {
			const color = picker.value;
			picker.remove();
			if ( color ) {
				onPick( color );
			}
		}, { once: true } );
		picker.click();
	}

	function setPropRowDisabled( row, disabled ) {
		if ( ! row ) {
			return;
		}
		row.classList.toggle( 'is-disabled', !! disabled );
		row.querySelectorAll( 'input, select, button, textarea' ).forEach( ( el ) => {
			el.disabled = !! disabled;
		} );
	}

	function getActiveColorForRole( obj, role ) {
		if ( isShape( obj ) ) {
			return role === 'stroke' ? getShapeStrokeColor( obj ) : getShapeFillColor( obj );
		}
		return obj.fill || '#000000';
	}

	function renderColorRoleUI( obj, role, config ) {
		const select = document.getElementById( config.selectId );
		const swatchesEl = document.getElementById( config.listId );
		const addColorBtn = document.getElementById( config.addBtnId );
		const labelEl = document.getElementById( config.labelId );
		const paletteRow = document.getElementById( config.paletteRowId );
		const useGlobal = palettesData.use_global_colors;
		const paletteId = getPaletteIdForRole( obj, role );
		const isCustom = isCustomPaletteId( paletteId );
		const isEditable = isCustom && ! useGlobal;

		if ( paletteRow ) {
			paletteRow.hidden = useGlobal;
		}
		if ( select && ! useGlobal ) {
			populateLayerPaletteSelect( paletteId, select );
			select.value = paletteId;
		}

		if ( labelEl ) {
			if ( useGlobal ) {
				labelEl.textContent = 'Template colors';
			} else if ( isCustom ) {
				labelEl.textContent = role === 'stroke' ? 'Custom outline colors' : 'Custom colors (this layer)';
			} else {
				labelEl.textContent = role === 'stroke' ? 'Outline palette colors' : 'Palette colors';
			}
		}

		if ( addColorBtn ) {
			addColorBtn.hidden = ! isEditable;
		}

		if ( ! swatchesEl ) {
			return;
		}
		swatchesEl.innerHTML = '';
		swatchesEl.classList.add( 'wc-gpd-layer-color-list' );

		const source = getLayerColorSource( obj, role );
		const colors = source.colors;
		const currentColor = String( getActiveColorForRole( obj, role ) ).toLowerCase();

		if ( isEditable && ! colors.length ) {
			const empty = document.createElement( 'p' );
			empty.className = 'description';
			empty.textContent = 'No colors yet. Click Add color to pick the first one.';
			swatchesEl.appendChild( empty );
			return;
		}

		colors.forEach( ( color, index ) => {
			const row = document.createElement( 'div' );
			row.className = 'wc-gpd-layer-color-row';

			if ( isEditable ) {
				const swatchBtn = document.createElement( 'button' );
				swatchBtn.type = 'button';
				swatchBtn.className = 'wc-gpd-color-swatch';
				swatchBtn.style.backgroundColor = color;
				swatchBtn.title = 'Change ' + color;
				swatchBtn.addEventListener( 'click', () => {
					openNativeColorPicker( color, ( nextColor ) => {
						colors[ index ] = nextColor;
						source.persist();
						syncColorsPanel( obj );
					} );
				} );

				const hexInput = document.createElement( 'input' );
				hexInput.type = 'text';
				hexInput.className = 'wc-gpd-layer-color-hex wc-gpd-prop-control';
				hexInput.value = color;
				hexInput.maxLength = 7;
				hexInput.spellcheck = false;
				hexInput.addEventListener( 'change', () => {
					const next = normalizeHexColor( hexInput.value );
					if ( ! next ) {
						hexInput.value = colors[ index ];
						return;
					}
					colors[ index ] = next;
					source.persist();
					syncColorsPanel( obj );
				} );

				const removeBtn = document.createElement( 'button' );
				removeBtn.type = 'button';
				removeBtn.className = 'button-link-delete wc-gpd-palette-remove-color';
				removeBtn.textContent = '×';
				removeBtn.addEventListener( 'click', () => {
					colors.splice( index, 1 );
					source.persist();
					syncColorsPanel( obj );
				} );

				row.appendChild( swatchBtn );
				row.appendChild( hexInput );
				row.appendChild( removeBtn );
			} else {
				const applyBtn = document.createElement( 'button' );
				applyBtn.type = 'button';
				applyBtn.className = 'wc-gpd-color-swatch';
				applyBtn.style.backgroundColor = color;
				applyBtn.title = 'Apply ' + color;
				applyBtn.classList.toggle( 'is-active', color.toLowerCase() === currentColor );
				applyBtn.addEventListener( 'click', () => {
					if ( isShape( obj ) ) {
						applyShapeColor( obj, color, role );
						applyShapeStyleFromFlags( obj );
					} else {
						obj.set( 'fill', color );
					}
					canvas.requestRenderAll();
					syncColorsPanel( obj );
				} );
				row.appendChild( applyBtn );
			}

			swatchesEl.appendChild( row );
		} );
	}

	function renderPalettesAdmin() {
		const list = document.getElementById( 'wc-gpd-palettes-list' );
		if ( ! list ) {
			return;
		}
		list.innerHTML = '';
		( palettesData.palettes || [] ).forEach( ( palette ) => {
			const card = document.createElement( 'div' );
			card.className = 'wc-gpd-palette-card';
			card.dataset.paletteId = palette.id;

			const header = document.createElement( 'div' );
			header.className = 'wc-gpd-palette-card__header';
			const nameInput = document.createElement( 'input' );
			nameInput.type = 'text';
			nameInput.className = 'wc-gpd-palette-name';
			nameInput.value = palette.name || palette.id;
			nameInput.addEventListener( 'input', () => {
				palette.name = nameInput.value;
				savePalettesToInput();
				populateLayerPaletteSelect();
			} );
			header.appendChild( nameInput );

			const swatches = document.createElement( 'div' );
			swatches.className = 'wc-gpd-palette-swatches';
			( palette.colors || [] ).forEach( ( color, index ) => {
				const row = document.createElement( 'label' );
				row.className = 'wc-gpd-palette-color-row';
				const picker = document.createElement( 'input' );
				picker.type = 'color';
				picker.value = color;
				picker.addEventListener( 'input', () => {
					palette.colors[ index ] = picker.value;
					savePalettesToInput();
					syncColorsPanel( canvas.getActiveObject() );
				} );
				const removeBtn = document.createElement( 'button' );
				removeBtn.type = 'button';
				removeBtn.className = 'button-link-delete wc-gpd-palette-remove-color';
				removeBtn.textContent = '×';
				removeBtn.disabled = ( palette.colors || [] ).length <= 1;
				removeBtn.addEventListener( 'click', () => {
					if ( ( palette.colors || [] ).length <= 1 ) {
						return;
					}
					palette.colors.splice( index, 1 );
					savePalettesToInput();
					renderPalettesAdmin();
					syncColorsPanel( canvas.getActiveObject() );
				} );
				row.appendChild( picker );
				row.appendChild( removeBtn );
				swatches.appendChild( row );
			} );

			const addColorBtn = document.createElement( 'button' );
			addColorBtn.type = 'button';
			addColorBtn.className = 'button button-small';
			addColorBtn.textContent = 'Add color';
			addColorBtn.addEventListener( 'click', () => {
				palette.colors = palette.colors || [];
				palette.colors.push( '#000000' );
				savePalettesToInput();
				renderPalettesAdmin();
			} );

			card.appendChild( header );
			card.appendChild( swatches );
			card.appendChild( addColorBtn );
			list.appendChild( card );
		} );
	}

	function populateLayerPaletteSelect( selectedId, selectEl ) {
		const select = selectEl || document.getElementById( 'wc_gpd_layer_palette_id' );
		if ( ! select ) {
			return;
		}
		const current = selectedId || select.value;
		select.innerHTML = '';
		( palettesData.palettes || [] ).forEach( ( palette ) => {
			const opt = document.createElement( 'option' );
			opt.value = palette.id;
			opt.textContent = palette.name || palette.id;
			select.appendChild( opt );
		} );
		const customOpt = document.createElement( 'option' );
		customOpt.value = PAL_CUSTOM;
		customOpt.textContent = 'Custom colors (this layer)';
		select.appendChild( customOpt );
		if ( current ) {
			select.value = current;
		}
	}

	function syncColorsPanel( obj ) {
		if ( ! objectHasColor( obj ) ) {
			return;
		}

		const useGlobal = palettesData.use_global_colors;
		const isShapeLayer = isShape( obj );
		inferShapeStyleFlags( obj );
		const useFill = ! isShapeLayer || shapeUsesFill( obj );
		const useStroke = isShapeLayer && shapeUsesStroke( obj );

		const shapeAppearancePanel = document.getElementById( 'wc-gpd-shape-appearance-panel' );
		if ( shapeAppearancePanel ) {
			shapeAppearancePanel.hidden = ! isShapeLayer;
		}
		const colorsToggle = document.getElementById( 'wc-gpd-context-colors-toggle' );
		if ( colorsToggle ) {
			colorsToggle.textContent = isShapeLayer ? 'Shape, icon & color' : 'Color';
		}

		const fillPaletteLabel = document.getElementById( 'wc-gpd-fill-palette-label' );
		if ( fillPaletteLabel ) {
			fillPaletteLabel.textContent = isShapeLayer ? 'Fill color palette' : 'Color palette for this layer';
		}

		const fillPanel = document.getElementById( 'wc-gpd-fill-colors-panel' );
		const fillListRow = document.getElementById( 'wc-gpd-layer-colors-list-row' );
		const strokePanel = document.getElementById( 'wc-gpd-stroke-colors-panel' );
		const strokeListRow = document.getElementById( 'wc-gpd-stroke-colors-list-row' );

		setPropRowDisabled( fillPanel, isShapeLayer && ! useFill );
		setPropRowDisabled( fillListRow, isShapeLayer && ! useFill );

		if ( strokePanel ) {
			strokePanel.hidden = ! isShapeLayer || useGlobal;
		}
		if ( strokeListRow ) {
			strokeListRow.hidden = ! isShapeLayer || useGlobal;
		}
		setPropRowDisabled( strokePanel, ! useStroke );
		setPropRowDisabled( strokeListRow, ! useStroke );

		if ( useFill || ! isShapeLayer ) {
			renderColorRoleUI( obj, 'fill', {
				selectId: 'wc_gpd_layer_palette_id',
				listId: 'wc-gpd-layer-color-swatches',
				labelId: 'wc-gpd-layer-colors-list-label',
				addBtnId: 'wc-gpd-layer-add-color',
				paletteRowId: 'wc-gpd-fill-colors-panel',
			} );
		}

		if ( isShapeLayer && ! useGlobal ) {
			renderColorRoleUI( obj, 'stroke', {
				selectId: 'wc_gpd_stroke_layer_palette_id',
				listId: 'wc-gpd-stroke-layer-color-swatches',
				labelId: 'wc-gpd-stroke-colors-list-label',
				addBtnId: 'wc-gpd-stroke-layer-add-color',
				paletteRowId: 'wc-gpd-stroke-colors-panel',
			} );
		}
	}

	function renderGlobalColorsList() {
		const list = document.getElementById( 'wc-gpd-global-colors-list' );
		if ( ! list ) {
			return;
		}
		list.innerHTML = '';
		( palettesData.global_colors || [] ).forEach( ( color, index ) => {
			const row = document.createElement( 'label' );
			row.className = 'wc-gpd-global-color-row';
			const picker = document.createElement( 'input' );
			picker.type = 'color';
			picker.value = color;
			picker.addEventListener( 'input', () => {
				palettesData.global_colors[ index ] = picker.value;
				savePalettesToInput();
				syncColorsPanel( canvas.getActiveObject() );
			} );
			const removeBtn = document.createElement( 'button' );
			removeBtn.type = 'button';
			removeBtn.className = 'button-link-delete';
			removeBtn.textContent = '×';
			removeBtn.disabled = ( palettesData.global_colors || [] ).length <= 1;
			removeBtn.addEventListener( 'click', () => {
				if ( ( palettesData.global_colors || [] ).length <= 1 ) {
					return;
				}
				palettesData.global_colors.splice( index, 1 );
				savePalettesToInput();
				renderGlobalColorsList();
				syncColorsPanel( canvas.getActiveObject() );
			} );
			row.appendChild( picker );
			row.appendChild( removeBtn );
			list.appendChild( row );
		} );
	}

	const MOCKUP_PANEL_TITLES = {
		add: 'Add',
		layers: 'Layers',
		details: 'Your details',
		context: 'Edit',
	};

	function initAddMenuCollapsible( root ) {
		const scope = root || document;
		scope.querySelectorAll( '.wc-gpd-add-menu--collapsible .wc-gpd-add-menu__toggle' ).forEach( ( toggle ) => {
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

	function expandAddMenuGroup( match ) {
		const needle = String( match || '' ).toLowerCase();
		document.querySelectorAll( '.wc-gpd-add-menu--collapsible .wc-gpd-add-menu__group' ).forEach( ( group ) => {
			const toggle = group.querySelector( '.wc-gpd-add-menu__toggle' );
			const body = group.querySelector( '.wc-gpd-add-menu__body' );
			if ( ! toggle || ! body ) {
				return;
			}
			if ( toggle.textContent.trim().toLowerCase().includes( needle ) ) {
				group.classList.add( 'is-open' );
				toggle.setAttribute( 'aria-expanded', 'true' );
				body.hidden = false;
			}
		} );
	}

	function openMockupPanel( panelName ) {
		const mockup = document.getElementById( 'wc-gpd-customer-mockup' );
		if ( ! mockup ) {
			return;
		}
		mockup.querySelectorAll( '[data-mockup-panel]' ).forEach( ( panel ) => {
			const isTarget = panel.dataset.mockupPanel === panelName;
			panel.hidden = ! isTarget;
			panel.classList.toggle( 'is-active', isTarget );
		} );
		mockup.querySelectorAll( '[data-mockup-nav]' ).forEach( ( btn ) => {
			btn.classList.toggle( 'is-active', btn.dataset.mockupNav === panelName );
		} );
		const titleEl = document.getElementById( 'wc-gpd-mockup-drawer-title' );
		if ( titleEl && MOCKUP_PANEL_TITLES[ panelName ] ) {
			titleEl.textContent = MOCKUP_PANEL_TITLES[ panelName ];
		}
	}

	function initMockupStudioNav() {
		const nav = document.getElementById( 'wc-gpd-mockup-nav' );
		if ( ! nav || nav.dataset.gpdBound ) {
			return;
		}
		nav.dataset.gpdBound = '1';
		nav.querySelectorAll( '[data-mockup-nav]' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				if ( btn.hidden ) {
					return;
				}
				openMockupPanel( btn.dataset.mockupNav || 'add' );
			} );
		} );
	}

	function layerCustomerEditable( obj ) {
		return !! obj && obj.wcGpdCustomerEditable !== false;
	}

	function syncCustomerMockup() {
		const mockupRoot = '#wc-gpd-customer-mockup';
		const active = canvas.getActiveObject();
		const editable = layerCustomerEditable( active );

		Object.keys( MOCKUP_LAYER_TOOL_MAP ).forEach( ( key ) => {
			const lockProp = MOCKUP_LAYER_TOOL_MAP[ key ];
			const allowed = editable && active && ! active[ lockProp ];
			document.querySelectorAll( `${ mockupRoot } [data-mockup="${ key }"]` ).forEach( ( el ) => {
				el.hidden = ! allowed;
			} );
		} );

		const anyEdit = editable && active && isTextLayer( active ) && Object.keys( MOCKUP_LAYER_TOOL_MAP ).some( ( key ) => ! active[ MOCKUP_LAYER_TOOL_MAP[ key ] ] );
		document.querySelectorAll( `${ mockupRoot } [data-mockup-edit-nav]` ).forEach( ( el ) => {
			el.hidden = ! anyEdit;
		} );

		const freeTextCheckbox = document.querySelector( 'input[name="wc_gpd_ps_allow_free_text"]' );
		const freeTextOn = ! freeTextCheckbox || freeTextCheckbox.checked;
		document.querySelectorAll( `${ mockupRoot } [data-mockup="free_text"]` ).forEach( ( el ) => {
			el.hidden = ! freeTextOn;
		} );

		const layersCheckbox = document.querySelector( 'input[name="wc_gpd_ps_allow_layers_panel"]' );
		const layersOn = ! layersCheckbox || layersCheckbox.checked;
		document.querySelectorAll( `${ mockupRoot } [data-mockup="layers"]` ).forEach( ( el ) => {
			el.hidden = ! layersOn;
		} );

		const hasPlaceholders = canvas.getObjects().some( ( obj ) => isPlaceholder( obj ) && layerCustomerEditable( obj ) );
		const detailsCheckbox = document.querySelector( 'input[name="wc_gpd_ps_allow_details_panel"]' );
		const graphicsCheckbox = document.querySelector( 'input[name="wc_gpd_ps_allow_customer_graphics"]' );
		const detailsOn = detailsCheckbox && detailsCheckbox.checked;
		const graphicsOn = graphicsCheckbox && graphicsCheckbox.checked;
		const showDetailsNav = detailsOn && ( hasPlaceholders || graphicsOn );

		const detailsNavBtn = document.querySelector( '#wc-gpd-mockup-nav [data-mockup-nav="details"]' );
		if ( detailsNavBtn ) {
			detailsNavBtn.hidden = ! showDetailsNav;
		}

		const fieldsEl = document.getElementById( 'wc-gpd-mockup-fields' );
		if ( fieldsEl ) {
			fieldsEl.hidden = ! hasPlaceholders || ! detailsOn;
		}

		const graphicsEl = document.querySelector( `${ mockupRoot } .wc-gpd-mockup-graphics` );
		if ( graphicsEl ) {
			graphicsEl.hidden = ! graphicsOn || ! detailsOn;
		}

		const mockupLayers = document.querySelector( `${ mockupRoot } .wc-gpd-mockup-layers` );
		if ( mockupLayers ) {
			const visibleLayers = canvas.getObjects().filter( ( obj ) => ! obj.wcGpdHideFromCustomerLayers && layerCustomerEditable( obj ) && ( isTextLayer( obj ) || isPlaceholder( obj ) ) );
			mockupLayers.innerHTML = '';
			if ( ! visibleLayers.length ) {
				const empty = document.createElement( 'li' );
				empty.textContent = 'No editable layers';
				mockupLayers.appendChild( empty );
			} else {
				visibleLayers.reverse().forEach( ( obj ) => {
					const li = document.createElement( 'li' );
					li.textContent = layerLabel( obj );
					if ( active === obj ) {
						li.classList.add( 'is-active' );
					}
					mockupLayers.appendChild( li );
				} );
			}
		}
	}

	function syncCustomerAccessPanel( obj ) {
		const editableEl = document.getElementById( 'wc_gpd_customer_editable' );
		const visibleEl = document.getElementById( 'wc_gpd_show_in_customer_layers' );
		if ( ! obj ) {
			return;
		}

		const type = contextTypeForObject( obj );
		const editable = obj.wcGpdCustomerEditable !== false;

		if ( contextPane ) {
			contextPane.querySelectorAll( '[data-customer-for]' ).forEach( ( panel ) => {
				const types = ( panel.dataset.customerFor || '' ).split( ',' ).map( ( part ) => part.trim() );
				panel.hidden = ! type || ! types.includes( type );
			} );
		}

		const shapeColorRow = document.getElementById( 'wc-gpd-prop-shape-color-row' );
		if ( shapeColorRow ) {
			shapeColorRow.hidden = true;
		}

		const paletteOnlyRow = document.getElementById( 'wc-gpd-customer-palette-only-row' );
		if ( paletteOnlyRow ) {
			paletteOnlyRow.hidden = type !== 'text' && type !== 'shape';
		}

		if ( editableEl ) {
			editableEl.checked = editable;
		}
		if ( visibleEl ) {
			visibleEl.checked = ! obj.wcGpdHideFromCustomerLayers;
		}

		const customerInputs = contextPane
			? contextPane.querySelectorAll( '.wc-gpd-prop-customer-group input, .wc-gpd-prop-row--check input, #wc_gpd_customer_color_palette_only' )
			: [];
		customerInputs.forEach( ( input ) => {
			if ( input.id === 'wc_gpd_customer_editable' || input.id === 'wc_gpd_show_in_customer_layers' ) {
				return;
			}
			input.disabled = ! editable;
		} );

		if ( isTextLayer( obj ) ) {
			const customerFills = document.getElementById( 'wc_gpd_text_customer_fills' );
			if ( customerFills ) {
				customerFills.checked = isPlaceholder( obj );
			}
			setAllowCheckboxes( obj );
		}

		if ( isTemplateImage( obj ) ) {
			const allowMove = document.getElementById( 'wc_gpd_graphic_allow_move' );
			const allowResize = document.getElementById( 'wc_gpd_graphic_allow_resize' );
			const lockAspect = document.getElementById( 'wc_gpd_graphic_lock_aspect' );
			if ( allowMove ) {
				allowMove.checked = ! obj.wcGpdLockMove;
			}
			if ( allowResize ) {
				allowResize.checked = ! obj.wcGpdLockScale;
			}
			if ( lockAspect ) {
				lockAspect.checked = !! obj.wcGpdLockAspect;
			}
		}

		if ( isShape( obj ) ) {
			const allowColor = document.getElementById( 'wc_gpd_shape_allow_color' );
			const allowMove = document.getElementById( 'wc_gpd_shape_allow_move' );
			const allowResize = document.getElementById( 'wc_gpd_shape_allow_resize' );
			if ( allowColor ) {
				allowColor.checked = ! obj.wcGpdLockColor;
			}
			if ( allowMove ) {
				allowMove.checked = ! obj.wcGpdLockMove;
			}
			if ( allowResize ) {
				allowResize.checked = ! obj.wcGpdLockScale;
			}
		}

		const paletteOnly = document.getElementById( 'wc_gpd_customer_color_palette_only' );
		if ( paletteOnly ) {
			paletteOnly.checked = obj.wcGpdCustomerPaletteOnly !== false;
		}
	}

	function openLayerSettings( obj ) {
		if ( ! obj ) {
			return;
		}
		canvas.setActiveObject( obj );
		canvas.requestRenderAll();
		updateSelectionPanels();
		openContextPane();
		const block = document.getElementById( 'wc-gpd-context-block-layer' );
		if ( block ) {
			block.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}
	}

	function setStepperDisplay( key, value ) {
		const config = STEPPER_CONFIG[ key ];
		if ( ! config ) {
			return;
		}
		const input = document.getElementById( config.inputId );
		const display = document.getElementById( config.displayId );
		const rounded = config.decimals > 0
			? Math.round( value * Math.pow( 10, config.decimals ) ) / Math.pow( 10, config.decimals )
			: Math.round( value );
		if ( input ) {
			input.value = String( rounded );
		}
		if ( display ) {
			display.textContent = String( rounded );
		}
	}

	function applyTextLayerFlags( obj ) {
		if ( ! isTextLayer( obj ) ) {
			return;
		}
		const customerFills = document.getElementById( 'wc_gpd_text_customer_fills' );
		const layerLabelInput = document.getElementById( 'wc_gpd_text_layer_label' );

		if ( customerFills && customerFills.checked ) {
			obj.wcGpdLayerType = 'placeholder';
			if ( ! obj.wcGpdPlaceholderKey ) {
				obj.wcGpdPlaceholderKey = `field_${ Date.now().toString( 36 ) }`;
			}
			if ( layerLabelInput ) {
				obj.wcGpdPlaceholderLabel = layerLabelInput.value || 'Field';
				obj.wcGpdLayerLabel = layerLabelInput.value;
			}
		} else {
			obj.wcGpdLayerType = 'text';
			if ( layerLabelInput ) {
				obj.wcGpdLayerLabel = layerLabelInput.value;
			}
		}
	}

	function initAdminStudioNav() {
		if ( ! adminStudioNav ) {
			return;
		}
		adminStudioNav.querySelectorAll( '.wc-gpd-studio-nav__btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				openAccordionSection( btn.dataset.section || 'add' );
			} );
		} );
	}

	function initAccordion() {
		if ( ! accordionEl ) {
			return;
		}
		accordionEl.querySelectorAll( '.wc-gpd-accordion-toggle' ).forEach( ( toggle ) => {
			toggle.addEventListener( 'click', () => {
				const section = toggle.closest( '.wc-gpd-accordion-section' );
				if ( ! section ) {
					return;
				}
				const body = section.querySelector( '.wc-gpd-accordion-body' );
				if ( section.classList.contains( 'is-open' ) ) {
					section.classList.remove( 'is-open' );
					toggle.setAttribute( 'aria-expanded', 'false' );
					if ( body ) {
						body.hidden = true;
					}
				} else {
					openAccordionSection( section.dataset.section || '' );
				}
			} );
		} );
	}

	function objectDimensions( obj ) {
		if ( ! obj ) {
			return { width: 0, height: 0, left: 0, top: 0 };
		}
		const bounds = obj.getBoundingRect( true, true );
		return {
			width: Math.round( bounds.width ),
			height: Math.round( bounds.height ),
			left: Math.round( bounds.left ),
			top: Math.round( bounds.top ),
		};
	}

	function syncSelectionDimsPanel( obj ) {
		if ( ! obj ) {
			return;
		}
		const dims = objectDimensions( obj );
		const wEl = document.getElementById( 'wc_gpd_sel_width' );
		const hEl = document.getElementById( 'wc_gpd_sel_height' );
		const lEl = document.getElementById( 'wc_gpd_sel_left' );
		const tEl = document.getElementById( 'wc_gpd_sel_top' );
		const lockEl = document.getElementById( 'wc_gpd_lock_aspect' );
		if ( wEl ) {
			wEl.value = String( pxToDisplay( dims.width ) );
		}
		if ( hEl ) {
			hEl.value = String( pxToDisplay( dims.height ) );
		}
		if ( lEl ) {
			lEl.value = String( pxToDisplay( dims.left ) );
		}
		if ( tEl ) {
			tEl.value = String( pxToDisplay( dims.top ) );
		}
		if ( lockEl ) {
			lockEl.checked = lockAspectRatio;
		}
	}

	function applySelectionDimsFromInputs() {
		const obj = canvas.getActiveObject();
		if ( ! obj ) {
			return;
		}
		const wEl = document.getElementById( 'wc_gpd_sel_width' );
		const hEl = document.getElementById( 'wc_gpd_sel_height' );
		const lEl = document.getElementById( 'wc_gpd_sel_left' );
		const tEl = document.getElementById( 'wc_gpd_sel_top' );
		const newW = displayToPx( wEl ? wEl.value : '0' );
		const newH = displayToPx( hEl ? hEl.value : '0' );
		const newL = displayToPx( lEl ? lEl.value : '0' );
		const newT = displayToPx( tEl ? tEl.value : '0' );
		if ( ! newW || ! newH ) {
			return;
		}
		const current = objectDimensions( obj );
		if ( lockAspectRatio && current.width > 0 ) {
			const ratio = current.height / current.width;
			if ( obj.scaleX !== undefined ) {
				const baseW = obj.width * ( obj.scaleX || 1 );
				const scale = newW / ( obj.width || 1 );
				obj.set( { scaleX: scale, scaleY: scale } );
			} else {
				obj.set( { height: Math.round( newW * ratio ) } );
			}
		} else if ( obj.scaleX !== undefined && obj.width ) {
			obj.set( { scaleX: newW / obj.width, scaleY: newH / ( obj.height || 1 ) } );
		} else if ( obj.width !== undefined ) {
			obj.set( { width: newW, height: newH } );
		}
		obj.set( { left: newL, top: newT } );
		obj.setCoords();
		canvas.requestRenderAll();
		renderLayersList();
	}

	function activeTextObject() {
		const active = canvas.getActiveObject();
		if ( isTextLayer( active ) ) {
			return active;
		}
		return null;
	}

	function updateSelectionPanels() {
		const active = canvas.getActiveObject();
		const shapeSelected = isShape( active );
		const imageSelected = isTemplateImage( active );
		const textSelected = isTextLayer( active );
		const slotSelected = isGraphicSlot( active );

		hideAllPropPanels();

		if ( shapeSelected ) {
			syncShapeAppearancePanel( active );
		}
		if ( imageSelected && imagePropsPanel ) {
			syncImagePropsPanel( active );
		}
		if ( textSelected ) {
			syncTextEditorPanel( active );
		}
		if ( slotSelected && graphicSlotPropsPanel ) {
			syncGraphicSlotPropsPanel( active );
		}

		if ( bboxToggle ) {
			bboxToggle.disabled = ! shapeSelected;
			if ( shapeSelected ) {
				bboxToggle.checked = !! active.wcGpdBoundingBox;
			}
		}
		if ( outlineToggle && shapeSelected ) {
			outlineToggle.checked = !! active.wcGpdOutlineLayer;
		}
		if ( imageSelected && mockupVisibleToggle ) {
			mockupVisibleToggle.checked = active.wcGpdMockupVisible !== false;
		}
		if ( active ) {
			if ( contextEmpty ) {
				contextEmpty.hidden = true;
			}
			if ( contextPane ) {
				contextPane.hidden = false;
			}
			if ( contextLayerName ) {
				contextLayerName.textContent = layerLabel( active );
			}
			showContextBlocks( active );
			syncCustomerAccessPanel( active );
			syncSelectionDimsPanel( active );
			syncColorsPanel( active );
			syncContextNav( active );
			openAccordionForSelection( active );
		} else {
			syncColorsPanel( null );
			if ( contextEmpty ) {
				contextEmpty.hidden = false;
			}
			if ( contextPane ) {
				contextPane.hidden = true;
			}
			syncContextNav( null );
		}

		renderLayersList();
		updateAccordionTitles();
		updateRulers();
		syncCustomerMockup();
	}

	function syncTextEditorPanel( obj ) {
		const content = document.getElementById( 'wc_gpd_tpl_text_content' );
		const font = document.getElementById( 'wc_gpd_tpl_font_family' );
		const size = document.getElementById( 'wc_gpd_tpl_font_size' );
		const fitMode = document.getElementById( 'wc_gpd_tpl_fit_mode' );
		const layerLabelInput = document.getElementById( 'wc_gpd_text_layer_label' );
		const customerFills = document.getElementById( 'wc_gpd_text_customer_fills' );
		const boxW = document.getElementById( 'wc_gpd_placeholder_width' );
		const boldBtn = document.getElementById( 'wc_gpd_tpl_bold' );
		const italicBtn = document.getElementById( 'wc_gpd_tpl_italic' );
		const underlineBtn = document.getElementById( 'wc_gpd_tpl_underline' );

		normalizeTextLayer( obj );

		if ( layerLabelInput ) {
			layerLabelInput.value = obj.wcGpdLayerLabel || obj.wcGpdPlaceholderLabel || '';
		}
		const allowMove = document.getElementById( 'wc_gpd_allow_move' );
		if ( allowMove ) {
			allowMove.checked = ! obj.wcGpdLockMove;
		}
		if ( customerFills ) {
			customerFills.checked = isPlaceholder( obj );
		}
		if ( boxW ) {
			boxW.value = String( Math.round( obj.width || 240 ) );
		}
		if ( content ) {
			content.value = obj.text || '';
		}
		if ( font ) {
			font.value = obj.fontFamily || DEFAULT_FONT;
		}
		if ( size ) {
			size.value = String( Math.round( obj.fontSize || 32 ) );
		}
		if ( fitMode ) {
			fitMode.value = getFitMode( obj );
		}
		setStepperDisplay( 'line_height', obj.lineHeight || 1.16 );
		setStepperDisplay( 'letter_spacing', obj.charSpacing || 0 );
		if ( boldBtn ) {
			boldBtn.classList.toggle( 'is-active', obj.fontWeight === 'bold' );
		}
		if ( italicBtn ) {
			italicBtn.classList.toggle( 'is-active', obj.fontStyle === 'italic' );
		}
		if ( underlineBtn ) {
			underlineBtn.classList.toggle( 'is-active', !! obj.underline );
		}
		document.querySelectorAll( '.wc-gpd-tpl-align' ).forEach( ( btn ) => {
			btn.classList.toggle( 'is-active', btn.dataset.align === ( obj.textAlign || 'left' ) );
		} );
		setAllowCheckboxes( obj );
	}

	function setAllowCheckboxes( obj ) {
		Object.keys( CUSTOMER_ALLOW_MAP ).forEach( ( key ) => {
			const el = document.getElementById( 'wc_gpd_allow_' + key );
			if ( el ) {
				el.checked = ! obj[ CUSTOMER_ALLOW_MAP[ key ] ];
			}
		} );
	}

	function applyAllowCheckboxes( obj ) {
		Object.keys( CUSTOMER_ALLOW_MAP ).forEach( ( key ) => {
			const el = document.getElementById( 'wc_gpd_allow_' + key );
			if ( el ) {
				obj[ CUSTOMER_ALLOW_MAP[ key ] ] = ! el.checked;
			}
		} );
	}

	function imageRoleForObject( obj ) {
		if ( isMockupImage( obj ) ) {
			return 'mockup';
		}
		if ( obj.wcGpdLockMove && obj.wcGpdLockScale ) {
			return 'fixed';
		}
		return 'repositionable';
	}

	function applyImageRole( obj, role ) {
		if ( ! obj || obj.type !== 'image' ) {
			return;
		}
		if ( role === 'mockup' ) {
			obj.wcGpdMockupImage = true;
			obj.wcGpdMockupVisible = obj.wcGpdMockupVisible !== false;
			obj.wcGpdGraphicLayer = false;
			obj.wcGpdLayerType = 'mockup';
			obj.wcGpdExportGraphic = false;
			obj.wcGpdLockMove = true;
			obj.wcGpdLockScale = true;
			obj.wcGpdLockAspect = false;
			obj.wcGpdCustomerEditable = false;
			obj.wcGpdHideFromCustomerLayers = true;
		} else if ( role === 'fixed' ) {
			obj.wcGpdMockupImage = false;
			obj.wcGpdGraphicLayer = true;
			obj.wcGpdLayerType = 'graphic';
			obj.wcGpdExportGraphic = obj.wcGpdExportGraphic !== false;
			obj.wcGpdLockMove = true;
			obj.wcGpdLockScale = true;
			obj.wcGpdCustomerMovable = false;
			obj.wcGpdCustomerResizable = false;
			obj.wcGpdLockAspect = false;
			obj.wcGpdCustomerEditable = false;
			obj.wcGpdHideFromCustomerLayers = true;
		} else {
			obj.wcGpdMockupImage = false;
			obj.wcGpdGraphicLayer = true;
			obj.wcGpdLayerType = 'graphic';
			obj.wcGpdExportGraphic = obj.wcGpdExportGraphic !== false;
			obj.wcGpdLockMove = false;
			obj.wcGpdLockScale = false;
			obj.wcGpdCustomerMovable = true;
			obj.wcGpdCustomerResizable = true;
			obj.wcGpdCustomerEditable = true;
			obj.wcGpdHideFromCustomerLayers = false;
		}
		if ( canvas.getObjects().indexOf( obj ) >= 0 ) {
			sortLayers();
			canvas.requestRenderAll();
			renderLayersList();
		}
	}

	function syncImagePropsPanel( obj ) {
		const role = imageRoleForObject( obj );
		document.querySelectorAll( 'input[name="wc_gpd_image_role"]' ).forEach( ( input ) => {
			input.checked = input.value === role;
		} );
		const mockupOpts = document.getElementById( 'wc-gpd-image-mockup-options' );
		const graphicOpts = document.getElementById( 'wc-gpd-image-graphic-options' );
		const customerOpts = document.getElementById( 'wc-gpd-image-customer-options' );
		if ( mockupOpts ) {
			mockupOpts.hidden = role !== 'mockup';
		}
		if ( graphicOpts ) {
			graphicOpts.hidden = role === 'mockup';
		}
		if ( customerOpts ) {
			customerOpts.hidden = role !== 'repositionable';
		}
		const exp = document.getElementById( 'wc_gpd_graphic_export' );
		const allowMove = document.getElementById( 'wc_gpd_graphic_allow_move' );
		const allowResize = document.getElementById( 'wc_gpd_graphic_allow_resize' );
		const lockAspect = document.getElementById( 'wc_gpd_graphic_lock_aspect' );
		if ( exp ) {
			exp.checked = obj.wcGpdExportGraphic !== false;
		}
		if ( mockupVisibleToggle ) {
			mockupVisibleToggle.checked = obj.wcGpdMockupVisible !== false;
		}
		if ( allowMove ) {
			allowMove.checked = ! obj.wcGpdLockMove;
		}
		if ( allowResize ) {
			allowResize.checked = ! obj.wcGpdLockScale;
		}
		if ( lockAspect ) {
			lockAspect.checked = !! obj.wcGpdLockAspect;
		}
	}

	function syncGraphicSlotPropsPanel( obj ) {
		const librarySelect = document.getElementById( 'wc_gpd_slot_library_id' );
		if ( librarySelect ) {
			librarySelect.innerHTML = '<option value="">— Select library —</option>';
			graphicLibraries.forEach( ( lib ) => {
				const opt = document.createElement( 'option' );
				opt.value = lib.id;
				opt.textContent = lib.name || lib.id;
				librarySelect.appendChild( opt );
			} );
			librarySelect.value = obj.wcGpdGraphicLibraryId || '';
		}
	}

	function layerActionBtn( title, glyph, handler ) {
		const btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'wc-gpd-tpl-layer-action';
		btn.title = title;
		btn.setAttribute( 'aria-label', title );
		btn.textContent = glyph;
		btn.addEventListener( 'click', ( event ) => {
			event.stopPropagation();
			handler();
		} );
		return btn;
	}

	function moveLayer( obj, direction ) {
		const objects = canvas.getObjects();
		const index = objects.indexOf( obj );
		if ( index < 0 ) {
			return;
		}
		if ( direction === 'up' && index < objects.length - 1 ) {
			canvas.bringForward( obj );
		} else if ( direction === 'down' && index > 0 ) {
			canvas.sendBackwards( obj );
		}
		sortLayers();
		canvas.requestRenderAll();
		renderLayersList();
	}

	function duplicateLayer( obj ) {
		if ( ! obj ) {
			return;
		}
		obj.clone( ( cloned ) => {
			cloned.set( {
				left: ( obj.left || 0 ) + 16,
				top: ( obj.top || 0 ) + 16,
				wcGpdUid: uid(),
			} );
			prepareLoadedObject( cloned );
			canvas.add( cloned );
			canvas.setActiveObject( cloned );
			sortLayers();
			canvas.requestRenderAll();
			updateSelectionPanels();
		}, SERIALIZE_PROPS );
	}

	function renameLayer( obj ) {
		if ( ! obj ) {
			return;
		}
		const next = window.prompt( 'Layer name', layerLabel( obj ) );
		if ( next && next.trim() ) {
			obj.wcGpdLayerLabel = next.trim();
			renderLayersList();
		}
	}

	function renderLayersList() {
		if ( ! layersListEl ) {
			return;
		}
		const objects = canvas.getObjects().slice().reverse();
		layersListEl.innerHTML = '';
		if ( layersEmptyHint ) {
			layersEmptyHint.hidden = objects.length > 0;
		}
		objects.forEach( ( obj ) => {
			const li = document.createElement( 'li' );
			li.className = 'wc-gpd-tpl-layer-row' + ( canvas.getActiveObject() === obj ? ' is-active' : '' );
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wc-gpd-tpl-layer-item';
			btn.textContent = layerLabel( obj );
			btn.addEventListener( 'click', () => {
				canvas.setActiveObject( obj );
				canvas.requestRenderAll();
				updateSelectionPanels();
			} );
			const actions = document.createElement( 'span' );
			actions.className = 'wc-gpd-tpl-layer-actions';
			const settingsBtn = layerActionBtn( 'Layer settings', '⚙', () => openLayerSettings( obj ) );
			settingsBtn.classList.add( 'wc-gpd-tpl-layer-action--settings' );
			actions.appendChild( settingsBtn );
			actions.appendChild( layerActionBtn( 'Move up', '↑', () => moveLayer( obj, 'up' ) ) );
			actions.appendChild( layerActionBtn( 'Move down', '↓', () => moveLayer( obj, 'down' ) ) );
			actions.appendChild( layerActionBtn( 'Rename', '✎', () => renameLayer( obj ) ) );
			actions.appendChild( layerActionBtn( 'Duplicate', '⧉', () => duplicateLayer( obj ) ) );
			actions.appendChild( layerActionBtn( 'Delete', '✕', () => {
				canvas.remove( obj );
				canvas.discardActiveObject();
				canvas.requestRenderAll();
				updateSelectionPanels();
			} ) );
			li.appendChild( btn );
			li.appendChild( actions );
			layersListEl.appendChild( li );
		} );
	}

	function clearCanvas() {
		canvas.getObjects().slice().forEach( ( obj ) => canvas.remove( obj ) );
		canvas.discardActiveObject();
		canvas.backgroundImage = null;
		canvas.backgroundColor = canvasBgColor();
		canvas.requestRenderAll();
	}

	function sortLayers() {
		canvas.getObjects().forEach( ( obj ) => {
			if ( isMockupImage( obj ) ) {
				canvas.sendToBack( obj );
			}
		} );
		canvas.getObjects().forEach( ( obj ) => {
			if ( isShape( obj ) && ! obj.wcGpdBoundingBox ) {
				canvas.bringToFront( obj );
			}
		} );
		canvas.getObjects().forEach( ( obj ) => {
			if ( obj.wcGpdBoundingBox ) {
				canvas.bringToFront( obj );
			}
		} );
	}

	function prepareLoadedObject( obj ) {
		obj.wcGpdTemplateLayer = true;
		obj.set( { selectable: true, evented: true, hasControls: true, hasBorders: true } );
		if ( isTextLayer( obj ) ) {
			obj.set( { editable: true } );
			normalizeTextLayer( obj );
			if ( typeof obj.wcGpdCustomerPaletteOnly === 'undefined' ) {
				obj.wcGpdCustomerPaletteOnly = true;
			}
		}
		if ( isShape( obj ) ) {
			inferShapeStyleFlags( obj );
		}
	}

	function migrateLegacyImage( view ) {
		if ( ! view.template_image_id || ! window.wp || ! wp.media ) {
			return Promise.resolve();
		}
		const hasImage = ( view.objects || [] ).some( ( obj ) => obj.type === 'image' );
		if ( hasImage ) {
			return Promise.resolve();
		}
		return wp.media.attachment( view.template_image_id ).fetch().then( () => {
			const attachment = wp.media.attachment( view.template_image_id );
			const url = attachment.get( 'url' );
			if ( ! url ) {
				return;
			}
			return new Promise( ( resolve ) => {
				fabric.Image.fromURL( url, ( img ) => {
					if ( ! img ) {
						resolve();
						return;
					}
					const scale = Math.min( width / img.width, height / img.height ) * 0.85;
					img.set( {
						left: width / 2,
						top: height / 2,
						originX: 'center',
						originY: 'center',
						scaleX: scale,
						scaleY: scale,
						wcGpdUid: uid(),
						wcGpdTemplateLayer: true,
						wcGpdMockupImage: true,
						wcGpdMockupVisible: true,
						wcGpdLayerType: 'mockup',
						wcGpdAttachmentId: view.template_image_id,
					} );
					view.objects = view.objects || [];
					view.objects.unshift( img.toObject( SERIALIZE_PROPS ) );
					view.template_image_id = 0;
					resolve();
				}, { crossOrigin: 'anonymous' } );
			} );
		} );
	}

	function loadView( viewId, options ) {
		const skipPersist = options && options.skipPersist;
		if ( ! skipPersist ) {
			persistCanvasToActiveView();
		}
		activeViewId = viewId;
		const view = getActiveView();
		clearCanvas();
		if ( ! view ) {
			renderTabs();
			return;
		}

		migrateLegacyImage( view ).then( () => {
			const objects = view.objects || [];
			if ( ! objects.length ) {
				renderTabs();
				updateSelectionPanels();
				return;
			}
			fabric.util.enlivenObjects( objects, ( enlivened ) => {
				enlivened.forEach( ( obj, index ) => {
					applyTemplateMetadata( obj, objects[ index ] );
					prepareLoadedObject( obj );
					canvas.add( obj );
				} );
				sortLayers();
				canvas.requestRenderAll();
				renderTabs();
				updateSelectionPanels();
			}, 'fabric' );
		} );
	}

	function renderTabs() {
		if ( ! viewTabsEl ) {
			return;
		}
		viewTabsEl.innerHTML = '';
		documentData.views.forEach( ( view ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wc-gpd-template-view-tab' + ( view.id === activeViewId ? ' is-active' : '' );
			btn.textContent = view.label || view.id;
			btn.addEventListener( 'click', () => loadView( view.id ) );
			viewTabsEl.appendChild( btn );
		} );
	}

	function addRect( square ) {
		const size = square ? 120 : 180;
		const rect = new fabric.Rect( {
			left: width / 2, top: height / 2, originX: 'center', originY: 'center',
			width: size, height: square ? size : 90,
			fill: 'transparent', stroke: defaultOutlineColor(), strokeWidth: defaultOutlineWidth(),
			wcGpdUid: uid(),
		} );
		syncShapeFlags( rect );
		initShapeStyleDefaults( rect, 'shape' );
		applyShapeStyleFromFlags( rect );
		canvas.add( rect );
		canvas.setActiveObject( rect );
		sortLayers();
		canvas.requestRenderAll();
		updateSelectionPanels();
	}

	function addCircle() {
		const circle = new fabric.Circle( {
			left: width / 2, top: height / 2, originX: 'center', originY: 'center',
			radius: 60, fill: 'transparent', stroke: defaultOutlineColor(), strokeWidth: defaultOutlineWidth(),
			wcGpdUid: uid(),
		} );
		syncShapeFlags( circle );
		initShapeStyleDefaults( circle, 'shape' );
		applyShapeStyleFromFlags( circle );
		canvas.add( circle );
		canvas.setActiveObject( circle );
		sortLayers();
		canvas.requestRenderAll();
		updateSelectionPanels();
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

	function addPolygonShape( sides ) {
		const radius = 70;
		const points = regularPolygonPoints( sides, radius );
		const poly = new fabric.Polygon( points, {
			left: width / 2,
			top: height / 2,
			originX: 'center',
			originY: 'center',
			fill: 'transparent',
			stroke: defaultOutlineColor(),
			strokeWidth: defaultOutlineWidth(),
			wcGpdUid: uid(),
		} );
		syncShapeFlags( poly );
		initShapeStyleDefaults( poly, 'shape' );
		applyShapeStyleFromFlags( poly );
		canvas.add( poly );
		canvas.setActiveObject( poly );
		sortLayers();
		canvas.requestRenderAll();
		updateSelectionPanels();
	}

	function addHeartShape() {
		const path = new fabric.Path(
			'M 12 21.35 L 10.55 20.03 C 5.4 15.36 2 12.28 2 8.5 C 2 5.42 4.42 3 7.5 3 C 9.24 3 10.91 3.81 12 5.09 C 13.09 3.81 14.76 3 16.5 3 C 19.58 3 22 5.42 22 8.5 C 22 12.28 18.6 15.36 13.45 20.03 L 12 21.35 Z',
			{
				left: width / 2,
				top: height / 2,
				originX: 'center',
				originY: 'center',
				scaleX: 4,
				scaleY: 4,
				fill: 'transparent',
				stroke: defaultOutlineColor(),
				strokeWidth: defaultOutlineWidth(),
				wcGpdUid: uid(),
			}
		);
		syncShapeFlags( path );
		initShapeStyleDefaults( path, 'shape' );
		applyShapeStyleFromFlags( path );
		canvas.add( path );
		canvas.setActiveObject( path );
		sortLayers();
		canvas.requestRenderAll();
		updateSelectionPanels();
	}

	function styleShapeForEngraving( target, color ) {
		if ( ! target ) {
			return;
		}
		if ( target.type === 'group' && target.getObjects ) {
			target.getObjects().forEach( ( child ) => styleShapeForEngraving( child, color ) );
			return;
		}
		target.set( {
			fill: color,
			stroke: null,
			strokeWidth: 0,
		} );
	}

	function addBootstrapIconFromSvg( svgString, slug ) {
		if ( ! svgString ) {
			return;
		}
		cancelFreeformMode();
		fabric.loadSVGFromString( svgString, ( objects, options ) => {
			if ( ! objects || ! objects.length ) {
				return;
			}
			let obj = objects.length === 1 ? objects[ 0 ] : fabric.util.groupSVGElements( objects, options );
			const color = defaultOutlineColor();
			styleShapeForEngraving( obj, color );
			const targetSize = 96;
			const bounds = obj.getBoundingRect( true, true );
			const base = Math.max( bounds.width || 16, bounds.height || 16, 1 );
			const scale = targetSize / base;
			obj.set( {
				left: width / 2,
				top: height / 2,
				originX: 'center',
				originY: 'center',
				scaleX: scale,
				scaleY: scale,
				wcGpdUid: uid(),
				wcGpdTemplateLayer: true,
				wcGpdLayerLabel: ( slug || 'icon' ).replace( /-/g, ' ' ),
			} );
			syncShapeFlags( obj );
			initShapeStyleDefaults( obj, 'icon' );
			applyShapeStyleFromFlags( obj );
			canvas.add( obj );
			canvas.setActiveObject( obj );
			sortLayers();
			canvas.requestRenderAll();
			updateSelectionPanels();
			openContextPane();
		} );
	}

	window.wcGpdAddBootstrapIcon = function ( slug ) {
		const iconsConfig = editorConfig.bootstrapIcons || {};
		const url = new URL( iconsConfig.ajaxUrl || '/wp-admin/admin-ajax.php', window.location.origin );
		url.searchParams.set( 'action', 'wc_gpd_get_bootstrap_icon' );
		url.searchParams.set( 'nonce', iconsConfig.nonce || '' );
		url.searchParams.set( 'icon', slug );
		fetch( url.toString(), { credentials: 'same-origin' } )
			.then( ( response ) => response.json() )
			.then( ( payload ) => {
				if ( payload && payload.success && payload.data && payload.data.svg ) {
					addBootstrapIconFromSvg( payload.data.svg, slug );
				}
			} );
	};

	function clearFreeformPreview() {
		if ( freeformPreview ) {
			canvas.remove( freeformPreview );
			freeformPreview = null;
		}
	}

	function updateFreeformPreview( closeLoop ) {
		clearFreeformPreview();
		if ( freeformPoints.length < 2 ) {
			canvas.requestRenderAll();
			return;
		}
		const points = freeformPoints.slice();
		if ( closeLoop && points.length >= 3 ) {
			freeformPreview = new fabric.Polygon( points, {
				fill: 'rgba(34,113,177,0.08)',
				stroke: '#2271b1',
				strokeWidth: 1,
				strokeDashArray: [ 4, 4 ],
				selectable: false,
				evented: false,
			} );
		} else {
			freeformPreview = new fabric.Polyline( points, {
				fill: 'transparent',
				stroke: '#2271b1',
				strokeWidth: 1,
				strokeDashArray: [ 4, 4 ],
				selectable: false,
				evented: false,
			} );
		}
		canvas.add( freeformPreview );
		canvas.requestRenderAll();
	}

	function finishFreeformShape() {
		if ( freeformPoints.length < 3 ) {
			return;
		}
		const poly = new fabric.Polygon( freeformPoints.slice(), {
			fill: 'transparent',
			stroke: defaultOutlineColor(),
			strokeWidth: defaultOutlineWidth(),
			wcGpdUid: uid(),
			wcGpdLayerLabel: 'Freeform',
		} );
		syncShapeFlags( poly );
		initShapeStyleDefaults( poly, 'shape' );
		applyShapeStyleFromFlags( poly );
		clearFreeformPreview();
		freeformMode = false;
		freeformPoints = [];
		if ( freeformHint ) {
			freeformHint.hidden = true;
		}
		canvas.selection = true;
		canvas.defaultCursor = 'default';
		canvas.add( poly );
		canvas.setActiveObject( poly );
		sortLayers();
		canvas.requestRenderAll();
		updateSelectionPanels();
	}

	function startFreeformMode() {
		cancelFreeformMode();
		freeformMode = true;
		freeformPoints = [];
		canvas.discardActiveObject();
		canvas.selection = false;
		canvas.defaultCursor = 'crosshair';
		if ( freeformHint ) {
			freeformHint.hidden = false;
		}
		openAccordionSection( 'add' );
		expandAddMenuGroup( 'shape' );
	}

	function cancelFreeformMode() {
		freeformMode = false;
		freeformPoints = [];
		clearFreeformPreview();
		canvas.selection = true;
		canvas.defaultCursor = 'default';
		if ( freeformHint ) {
			freeformHint.hidden = true;
		}
	}

	function addTextField() {
		const box = new fabric.Textbox( 'Your text here', {
			left: width / 2,
			top: height / 2,
			originX: 'center',
			originY: 'center',
			width: 240,
			fontFamily: DEFAULT_FONT,
			fontSize: 32,
			fill: '#000000',
			textAlign: 'center',
			editable: true,
			wcGpdUid: uid(),
			wcGpdTemplateLayer: true,
			wcGpdLayerType: 'text',
			wcGpdLayerLabel: '',
			wcGpdFitMode: 'none',
			wcGpdPaletteId: 'pal_default',
			wcGpdCustomerPaletteOnly: true,
			wcGpdCustomerEditable: true,
			wcGpdHideFromCustomerLayers: false,
			wcGpdLockMove: false,
		} );
		canvas.add( box );
		canvas.setActiveObject( box );
		sortLayers();
		canvas.requestRenderAll();
		updateSelectionPanels();
		openContextPane();
	}

	function addImageFromLibrary( attachment ) {
		const url = attachment.url;
		if ( ! url ) {
			return;
		}
		fabric.Image.fromURL( url, ( img ) => {
			if ( ! img ) {
				return;
			}
			const scale = Math.min( 160 / img.width, 160 / img.height, 1 );
			img.set( {
				left: width / 2,
				top: height / 2,
				originX: 'center',
				originY: 'center',
				scaleX: scale,
				scaleY: scale,
				wcGpdUid: uid(),
				wcGpdTemplateLayer: true,
				wcGpdAttachmentId: attachment.id,
			} );
			canvas.add( img );
			applyImageRole( img, 'fixed' );
			canvas.setActiveObject( img );
			sortLayers();
			canvas.requestRenderAll();
			updateSelectionPanels();
			openContextPane();
		}, { crossOrigin: 'anonymous' } );
	}

	function addGraphicSlot() {
		const rect = new fabric.Rect( {
			left: width / 2,
			top: height / 2,
			originX: 'center',
			originY: 'center',
			width: 140,
			height: 140,
			fill: 'rgba(34,113,177,0.08)',
			stroke: '#2271b1',
			strokeWidth: 1,
			strokeDashArray: [ 6, 4 ],
			wcGpdUid: uid(),
			wcGpdTemplateLayer: true,
			wcGpdLayerType: 'graphic_slot',
			wcGpdGraphicSlot: true,
			wcGpdCustomerMovable: false,
			wcGpdCustomerResizable: false,
		} );
		canvas.add( rect );
		canvas.setActiveObject( rect );
		sortLayers();
		canvas.requestRenderAll();
		updateSelectionPanels();
	}

	function loadGraphicLibraries() {
		const site = editorConfig.siteLibraries;
		graphicLibraries = Array.isArray( site ) ? site : [];
	}

	function saveTemplateFonts() {
		if ( ! templateFontsInput ) {
			return;
		}
		const keys = [];
		document.querySelectorAll( '.wc-gpd-template-font-pick:checked' ).forEach( ( el ) => {
			if ( el.value ) {
				keys.push( el.value );
			}
		} );
		templateFontsInput.value = JSON.stringify( keys );
	}

	function setMockupBackground() {
		const active = canvas.getActiveObject();
		if ( ! active || ! isMockupImage( active ) ) {
			return;
		}
		canvas.getObjects().forEach( ( obj ) => {
			if ( isMockupImage( obj ) && obj !== active ) {
				obj.wcGpdMockupImage = false;
				obj.wcGpdLayerType = 'graphic';
				obj.wcGpdGraphicLayer = true;
			}
		} );
		active.wcGpdMockupImage = true;
		active.wcGpdLayerType = 'mockup';
		active.wcGpdGraphicLayer = false;
		sortLayers();
		canvas.requestRenderAll();
		renderLayersList();
	}

	function formatRulerValue( px ) {
		if ( displayUnit === 'px' ) {
			return String( Math.round( px ) );
		}
		return String( pxToDisplay( px ) );
	}

	function updateRulers() {
		const showRuler = showRulerToggle && showRulerToggle.checked;
		const showMeasure = showMeasurementsToggle && showMeasurementsToggle.checked;
		const unitLabel = displayUnit;
		if ( rulerTopEl ) {
			rulerTopEl.hidden = ! showRuler;
		}
		if ( rulerLeftEl ) {
			rulerLeftEl.hidden = ! showRuler;
		}
		if ( measureBottomEl ) {
			measureBottomEl.hidden = ! showMeasure;
			measureBottomEl.textContent = showMeasure ? `${ formatRulerValue( width ) } ${ unitLabel }` : '';
		}
		if ( measureRightEl ) {
			measureRightEl.hidden = ! showMeasure;
			measureRightEl.textContent = showMeasure ? `${ formatRulerValue( height ) } ${ unitLabel }` : '';
		}
		if ( ! showRuler || ! rulerTopEl || ! rulerLeftEl ) {
			return;
		}
		const stepPx = width > 1200 ? 100 : 50;
		let topMarks = '';
		let leftMarks = '';
		for ( let x = 0; x <= width; x += stepPx ) {
			topMarks += `<span class="wc-gpd-tpl-ruler-mark" style="left:${ ( x / width ) * 100 }%">${ formatRulerValue( x ) }</span>`;
		}
		for ( let y = 0; y <= height; y += stepPx ) {
			leftMarks += `<span class="wc-gpd-tpl-ruler-mark wc-gpd-tpl-ruler-mark--v" style="top:${ ( y / height ) * 100 }%">${ formatRulerValue( y ) }</span>`;
		}
		rulerTopEl.innerHTML = topMarks;
		rulerLeftEl.innerHTML = leftMarks;
	}

	function addView() {
		if ( documentData.views.length >= getMaxViews() ) {
			window.alert( `Maximum of ${ getMaxViews() } design areas allowed.` );
			return;
		}
		persistCanvasToActiveView();
		const index = documentData.views.length + 1;
		const presets = [ 'Front', 'Back', 'Left sleeve', 'Right sleeve', 'Inside', 'Custom' ];
		documentData.views.push( {
			id: `view_${ index }`,
			label: presets[ index - 1 ] || `Area ${ index }`,
			template_image_id: 0, bounding_box_uid: '', objects: [],
		} );
		loadView( `view_${ index }` );
		syncMaxViewsField();
	}

	function renameView() {
		const view = getActiveView();
		if ( ! view ) {
			return;
		}
		const next = window.prompt( 'Design area name', view.label || view.id );
		if ( next ) {
			view.label = next.trim();
			renderTabs();
		}
	}

	function deleteView() {
		if ( ! documentData.views || documentData.views.length <= 1 ) {
			window.alert( 'At least one design area is required.' );
			return;
		}
		const view = getActiveView();
		if ( ! view ) {
			return;
		}
		const label = view.label || view.id;
		if ( ! window.confirm( `Delete design area "${ label }"? This cannot be undone.` ) ) {
			return;
		}
		persistCanvasToActiveView();
		const idx = documentData.views.findIndex( ( row ) => row.id === activeViewId );
		if ( idx < 0 ) {
			return;
		}
		documentData.views.splice( idx, 1 );
		const nextIdx = Math.min( idx, documentData.views.length - 1 );
		loadView( documentData.views[ nextIdx ].id );
		syncMaxViewsField();
	}

	function deleteActiveLayer() {
		const active = canvas.getActiveObject();
		if ( ! active ) {
			return;
		}
		canvas.remove( active );
		canvas.discardActiveObject();
		updateSelectionPanels();
		canvas.requestRenderAll();
	}

	function saveJson() {
		persistCanvasToActiveView();
		syncMaxViewsField();
		if ( jsonInput ) {
			jsonInput.value = JSON.stringify( documentData );
		}
		saveTemplateFonts();
	}

	function loadJson() {
		if ( ! jsonInput || ! jsonInput.value ) {
			ensureDocument();
			loadView( documentData.views[ 0 ].id, { skipPersist: true } );
			return;
		}
		try {
			documentData = JSON.parse( jsonInput.value );
		} catch ( e ) {
			ensureDocument();
		}
		if ( ! documentData.views || ! documentData.views.length ) {
			ensureDocument();
		}
		if ( documentData.views.length > MAX_VIEWS ) {
			documentData.views = documentData.views.slice( 0, MAX_VIEWS );
		}
		syncMaxViewsField();
		loadView( documentData.views[ 0 ].id, { skipPersist: true } );
	}

	function syncDimensionFields() {
		dims = readCanvasDimensions();
		width = dims.width;
		height = dims.height;
		if ( widthInput ) {
			widthInput.value = String( width );
		}
		if ( heightInput ) {
			heightInput.value = String( height );
		}
	}

	function resizeCanvasDimensions() {
		syncDimensionFields();
		canvas.setWidth( width );
		canvas.setHeight( height );
		canvas.backgroundColor = canvasBgColor();
		canvas.calcOffset();
		canvas.requestRenderAll();
		updateRulers();
		applyResponsiveScale();
	}

	function roundLineHeightInput() {
		const lineHeight = document.getElementById( 'wc_gpd_tpl_line_height' );
		if ( ! lineHeight || lineHeight.value === '' ) {
			return;
		}
		const parsed = parseFloat( lineHeight.value );
		if ( Number.isNaN( parsed ) ) {
			setStepperDisplay( 'line_height', 1.16 );
			return;
		}
		const clamped = Math.min( 3, Math.max( 0.5, parsed ) );
		setStepperDisplay( 'line_height', clamped );
	}

	function sanitizeFormNumbersBeforeSave() {
		roundLineHeightInput();
		const strokeWidth = document.getElementById( 'wc_gpd_template_stroke_width' );
		if ( strokeWidth && strokeWidth.value !== '' ) {
			const parsed = parseFloat( strokeWidth.value );
			if ( ! Number.isNaN( parsed ) ) {
				strokeWidth.value = String( Math.round( parsed * 10 ) / 10 );
			}
		}
	}

	function syncStudioLayoutHeight( displayH ) {
		if ( ! editorRoot ) {
			return;
		}
		const showRuler = showRulerToggle && showRulerToggle.checked;
		const rulerPad = showRuler ? 20 : 0;
		const vertPad = 16;
		const workH = Math.max( 320, displayH + ( vertPad * 2 ) + rulerPad );
		editorRoot.style.setProperty( '--wc-gpd-studio-work-h', `${ workH }px` );
		editorRoot.style.setProperty( '--wc-gpd-studio-canvas-display-h', `${ displayH }px` );
	}

	function applyResponsiveScale() {
		const col = editorRoot ? editorRoot.querySelector( '.wc-gpd-tpl-canvas-col' ) : null;
		if ( ! col ) {
			return;
		}
		const isPopout = editorRoot && editorRoot.classList.contains( 'wc-gpd-is-popout' );
		const maxW = isPopout ? Math.min( window.innerWidth - 320, 1100 ) : Math.max( 1, col.clientWidth - 16 );
		const scale = Math.min( 1, maxW / width );
		const displayW = Math.max( 1, Math.floor( width * scale ) );
		const displayH = Math.max( 1, Math.floor( height * scale ) );
		canvas.setDimensions( { width, height } );
		canvas.setDimensions( { width: displayW, height: displayH }, { cssOnly: true } );
		if ( canvasEl ) {
			canvasEl.style.width = `${ displayW }px`;
			canvasEl.style.height = `${ displayH }px`;
		}
		if ( canvas.wrapperEl ) {
			canvas.wrapperEl.style.width = `${ displayW }px`;
			canvas.wrapperEl.style.height = `${ displayH }px`;
		}
		syncStudioLayoutHeight( displayH );
		canvas.calcOffset();
		canvas.requestRenderAll();
	}

	function bindSteppers() {
		document.querySelectorAll( '.wc-gpd-stepper-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const key = btn.dataset.stepper;
				const dir = parseInt( btn.dataset.dir, 10 ) || 0;
				const config = STEPPER_CONFIG[ key ];
				if ( ! config || ! dir ) {
					return;
				}
				const input = document.getElementById( config.inputId );
				const current = config.parse( input ? input.value : '0', 10 ) || 0;
				const next = Math.min( config.max, Math.max( config.min, current + ( config.step * dir ) ) );
				setStepperDisplay( key, next );
				const obj = activeTextObject();
				if ( obj ) {
					obj.set( config.prop, next );
					canvas.requestRenderAll();
				}
			} );
		} );
	}

	function bindTextEditor() {
		const content = document.getElementById( 'wc_gpd_tpl_text_content' );
		const font = document.getElementById( 'wc_gpd_tpl_font_family' );
		const size = document.getElementById( 'wc_gpd_tpl_font_size' );
		const fitMode = document.getElementById( 'wc_gpd_tpl_fit_mode' );
		const layerLabelInput = document.getElementById( 'wc_gpd_text_layer_label' );
		const customerFills = document.getElementById( 'wc_gpd_text_customer_fills' );
		const boxW = document.getElementById( 'wc_gpd_placeholder_width' );

		function applyAndRender( obj ) {
			if ( ! obj ) {
				return;
			}
			canvas.requestRenderAll();
			renderLayersList();
		}

		if ( content ) {
			content.addEventListener( 'input', () => {
				const obj = activeTextObject();
				if ( obj ) {
					obj.set( 'text', content.value );
					applyAndRender( obj );
				}
			} );
		}
		if ( font ) {
			font.addEventListener( 'change', () => {
				const obj = activeTextObject();
				if ( obj ) {
					obj.set( 'fontFamily', font.value );
					applyAndRender( obj );
				}
			} );
		}
		if ( size ) {
			size.addEventListener( 'input', () => {
				const obj = activeTextObject();
				if ( obj ) {
					obj.set( 'fontSize', parseInt( size.value, 10 ) || 32 );
					applyAndRender( obj );
				}
			} );
		}
		if ( fitMode ) {
			fitMode.addEventListener( 'change', () => {
				const obj = activeTextObject();
				if ( obj ) {
					obj.wcGpdFitMode = fitMode.value || 'none';
					obj.wcGpdShrinkToFit = obj.wcGpdFitMode !== 'none';
				}
			} );
		}
		if ( layerLabelInput ) {
			layerLabelInput.addEventListener( 'input', () => {
				const obj = activeTextObject();
				if ( ! obj ) {
					return;
				}
				applyTextLayerFlags( obj );
				renderLayersList();
				updateAccordionTitles();
			} );
		}
		if ( customerFills ) {
			customerFills.addEventListener( 'change', () => {
				const obj = activeTextObject();
				if ( obj ) {
					applyTextLayerFlags( obj );
					syncTextEditorPanel( obj );
					renderLayersList();
					updateAccordionTitles();
					syncCustomerMockup();
				}
			} );
		}
		if ( boxW ) {
			boxW.addEventListener( 'input', () => {
				const obj = activeTextObject();
				if ( obj && isTextLayer( obj ) ) {
					obj.set( 'width', parseInt( boxW.value, 10 ) || 240 );
					applyAndRender( obj );
				}
			} );
		}

		document.getElementById( 'wc_gpd_tpl_bold' )?.addEventListener( 'click', () => {
			const obj = activeTextObject();
			if ( ! obj ) {
				return;
			}
			const on = obj.fontWeight !== 'bold';
			obj.set( 'fontWeight', on ? 'bold' : 'normal' );
			document.getElementById( 'wc_gpd_tpl_bold' )?.classList.toggle( 'is-active', on );
			applyAndRender( obj );
		} );
		document.getElementById( 'wc_gpd_tpl_italic' )?.addEventListener( 'click', () => {
			const obj = activeTextObject();
			if ( ! obj ) {
				return;
			}
			const on = obj.fontStyle !== 'italic';
			obj.set( 'fontStyle', on ? 'italic' : 'normal' );
			document.getElementById( 'wc_gpd_tpl_italic' )?.classList.toggle( 'is-active', on );
			applyAndRender( obj );
		} );
		document.getElementById( 'wc_gpd_tpl_underline' )?.addEventListener( 'click', () => {
			const obj = activeTextObject();
			if ( ! obj ) {
				return;
			}
			const on = ! obj.underline;
			obj.set( 'underline', on );
			document.getElementById( 'wc_gpd_tpl_underline' )?.classList.toggle( 'is-active', on );
			applyAndRender( obj );
		} );
		document.querySelectorAll( '.wc-gpd-tpl-align' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const obj = activeTextObject();
				if ( ! obj ) {
					return;
				}
				obj.set( 'textAlign', btn.dataset.align );
				document.querySelectorAll( '.wc-gpd-tpl-align' ).forEach( ( b ) => {
					b.classList.toggle( 'is-active', b === btn );
				} );
				applyAndRender( obj );
			} );
		} );

		Object.keys( CUSTOMER_ALLOW_MAP ).forEach( ( key ) => {
			const el = document.getElementById( 'wc_gpd_allow_' + key );
			if ( el ) {
				el.addEventListener( 'change', () => {
					const obj = canvas.getActiveObject();
					if ( obj && isTextLayer( obj ) ) {
						applyAllowCheckboxes( obj );
						syncCustomerMockup();
					}
				} );
			}
		} );
	}

	function bindCustomerAccessPanel() {
		const editableEl = document.getElementById( 'wc_gpd_customer_editable' );
		const visibleEl = document.getElementById( 'wc_gpd_show_in_customer_layers' );

		function refreshCustomerAccess() {
			const obj = canvas.getActiveObject();
			if ( obj ) {
				syncCustomerAccessPanel( obj );
				syncCustomerMockup();
				renderLayersList();
			}
		}

		if ( editableEl ) {
			editableEl.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( ! obj ) {
					return;
				}
				obj.wcGpdCustomerEditable = editableEl.checked;
				if ( ! editableEl.checked ) {
					obj.wcGpdHideFromCustomerLayers = true;
					if ( visibleEl ) {
						visibleEl.checked = false;
					}
				}
				refreshCustomerAccess();
			} );
		}
		if ( visibleEl ) {
			visibleEl.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( ! obj ) {
					return;
				}
				obj.wcGpdHideFromCustomerLayers = ! visibleEl.checked;
				refreshCustomerAccess();
			} );
		}

		const shapeColor = document.getElementById( 'wc_gpd_shape_allow_color' );
		const shapeMove = document.getElementById( 'wc_gpd_shape_allow_move' );
		const shapeResize = document.getElementById( 'wc_gpd_shape_allow_resize' );
		const paletteOnly = document.getElementById( 'wc_gpd_customer_color_palette_only' );

		if ( paletteOnly ) {
			paletteOnly.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( ! obj || ( ! isTextLayer( obj ) && ! isShape( obj ) ) ) {
					return;
				}
				obj.wcGpdCustomerPaletteOnly = paletteOnly.checked;
				syncCustomerMockup();
			} );
		}

		if ( shapeColor ) {
			shapeColor.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isShape( obj ) ) {
					obj.wcGpdLockColor = ! shapeColor.checked;
					syncCustomerMockup();
				}
			} );
		}
		if ( shapeMove ) {
			shapeMove.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isShape( obj ) ) {
					obj.wcGpdLockMove = ! shapeMove.checked;
					syncCustomerMockup();
				}
			} );
		}
		if ( shapeResize ) {
			shapeResize.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isShape( obj ) ) {
					obj.wcGpdLockScale = ! shapeResize.checked;
					syncCustomerMockup();
				}
			} );
		}

		const textColorAllow = document.getElementById( 'wc_gpd_allow_color' );
		if ( textColorAllow ) {
			textColorAllow.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isTextLayer( obj ) ) {
					obj.wcGpdLockColor = ! textColorAllow.checked;
					syncCustomerMockup();
				}
			} );
		}
	}

	function bindImagePropInputs() {
		document.querySelectorAll( 'input[name="wc_gpd_image_role"]' ).forEach( ( input ) => {
			input.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( ! isTemplateImage( obj ) || ! input.checked ) {
					return;
				}
				applyImageRole( obj, input.value );
				syncImagePropsPanel( obj );
			} );
		} );

		const exp = document.getElementById( 'wc_gpd_graphic_export' );
		const allowMove = document.getElementById( 'wc_gpd_graphic_allow_move' );
		const allowResize = document.getElementById( 'wc_gpd_graphic_allow_resize' );
		const lockAspect = document.getElementById( 'wc_gpd_graphic_lock_aspect' );

		if ( exp ) {
			exp.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicImage( obj ) ) {
					obj.wcGpdExportGraphic = exp.checked;
				}
			} );
		}
		if ( allowMove ) {
			allowMove.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicImage( obj ) ) {
					obj.wcGpdLockMove = ! allowMove.checked;
					obj.wcGpdCustomerMovable = allowMove.checked;
					syncCustomerMockup();
				}
			} );
		}
		if ( allowResize ) {
			allowResize.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicImage( obj ) ) {
					obj.wcGpdLockScale = ! allowResize.checked;
					obj.wcGpdCustomerResizable = allowResize.checked;
					syncCustomerMockup();
				}
			} );
		}
		if ( lockAspect ) {
			lockAspect.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicImage( obj ) ) {
					obj.wcGpdLockAspect = lockAspect.checked;
					syncCustomerMockup();
				}
			} );
		}

		const slotLibrary = document.getElementById( 'wc_gpd_slot_library_id' );
		if ( slotLibrary ) {
			slotLibrary.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicSlot( obj ) ) {
					obj.wcGpdGraphicLibraryId = slotLibrary.value;
				}
			} );
		}
	}

	canvas.on( 'selection:created', updateSelectionPanels );
	canvas.on( 'selection:updated', updateSelectionPanels );
	canvas.on( 'selection:cleared', updateSelectionPanels );
	canvas.on( 'text:changed', ( event ) => {
		const obj = event.target;
		const content = document.getElementById( 'wc_gpd_tpl_text_content' );
		if ( ! isTextLayer( obj ) || ! content || canvas.getActiveObject() !== obj ) {
			return;
		}
		content.value = obj.text || '';
		renderLayersList();
		updateAccordionTitles();
	} );
	canvas.on( 'text:editing:exited', ( event ) => {
		const obj = event.target;
		if ( isTextLayer( obj ) && canvas.getActiveObject() === obj ) {
			syncTextEditorPanel( obj );
		}
	} );
	canvas.on( 'object:modified', () => {
		sortLayers();
		const active = canvas.getActiveObject();
		if ( active ) {
			syncSelectionDimsPanel( active );
		}
		renderLayersList();
	} );
	canvas.on( 'object:added', renderLayersList );
	canvas.on( 'object:removed', renderLayersList );

	if ( outlineToggle ) {
		outlineToggle.addEventListener( 'change', () => {
			const active = canvas.getActiveObject();
			if ( ! active || ! isShape( active ) ) {
				return;
			}
			active.wcGpdOutlineLayer = outlineToggle.checked;
			active.wcGpdLayerType = outlineToggle.checked ? 'outline' : 'shape';
			if ( ! outlineToggle.checked ) {
				active.wcGpdBoundingBox = false;
				if ( bboxToggle ) {
					bboxToggle.checked = false;
				}
			}
			applyStrokeToObject( active, active.wcGpdBoundingBox );
			canvas.requestRenderAll();
		} );
	}

	if ( bboxToggle ) {
		bboxToggle.addEventListener( 'change', () => {
			const active = canvas.getActiveObject();
			if ( ! active || ! isShape( active ) ) {
				return;
			}
			if ( bboxToggle.checked ) {
				syncShapeFlags( active );
			} else {
				active.wcGpdBoundingBox = false;
				applyStrokeToObject( active, false );
			}
			canvas.requestRenderAll();
		} );
	}

	if ( shapeUseFillToggle ) {
		shapeUseFillToggle.addEventListener( 'change', () => {
			const active = canvas.getActiveObject();
			if ( ! active || ! isShape( active ) || active.wcGpdBoundingBox ) {
				return;
			}
			active.wcGpdShapeUseFill = shapeUseFillToggle.checked;
			if ( ! active.wcGpdShapeUseFill && ! active.wcGpdShapeUseStroke ) {
				active.wcGpdShapeUseStroke = true;
				if ( shapeUseStrokeToggle ) {
					shapeUseStrokeToggle.checked = true;
				}
			}
			applyShapeStyleFromFlags( active );
			syncShapeAppearancePanel( active );
			syncColorsPanel( active );
			canvas.requestRenderAll();
		} );
	}

	if ( shapeUseStrokeToggle ) {
		shapeUseStrokeToggle.addEventListener( 'change', () => {
			const active = canvas.getActiveObject();
			if ( ! active || ! isShape( active ) || active.wcGpdBoundingBox ) {
				return;
			}
			active.wcGpdShapeUseStroke = shapeUseStrokeToggle.checked;
			if ( ! active.wcGpdShapeUseFill && ! active.wcGpdShapeUseStroke ) {
				active.wcGpdShapeUseFill = true;
				if ( shapeUseFillToggle ) {
					shapeUseFillToggle.checked = true;
				}
			}
			applyShapeStyleFromFlags( active );
			syncShapeAppearancePanel( active );
			syncColorsPanel( active );
			canvas.requestRenderAll();
		} );
	}

	if ( strokeWidthInput ) {
		strokeWidthInput.addEventListener( 'input', () => {
			const active = canvas.getActiveObject();
			if ( ! active || ! isShape( active ) || active.wcGpdBoundingBox ) {
				return;
			}
			applyShapeStrokeWidth( active, parseFloat( strokeWidthInput.value ) || defaultOutlineWidth() );
			canvas.requestRenderAll();
		} );
	}

	if ( mockupVisibleToggle ) {
		mockupVisibleToggle.addEventListener( 'change', () => {
			const active = canvas.getActiveObject();
			if ( ! active || ! isMockupImage( active ) ) {
				return;
			}
			active.wcGpdMockupVisible = mockupVisibleToggle.checked;
			active.set( 'opacity', mockupVisibleToggle.checked ? 1 : 0.35 );
			canvas.requestRenderAll();
		} );
	}

	if ( deleteImageBtn ) {
		deleteImageBtn.addEventListener( 'click', deleteActiveLayer );
	}
	document.getElementById( 'wc-gpd-template-delete-slot' )?.addEventListener( 'click', deleteActiveLayer );

	const addImageBtn = document.getElementById( 'wc-gpd-template-add-image' );
	if ( addImageBtn && window.wp && wp.media ) {
		addImageBtn.addEventListener( 'click', () => {
			const frame = wp.media( {
				title: 'Add image',
				button: { text: 'Add to template' },
				multiple: false,
				library: { type: [ 'image' ] },
			} );
			frame.on( 'select', () => {
				const attachment = frame.state().get( 'selection' ).first().toJSON();
				addImageFromLibrary( attachment );
			} );
			frame.open();
		} );
	}

	document.getElementById( 'wc-gpd-set-mockup-background' )?.addEventListener( 'click', setMockupBackground );
	document.querySelectorAll( '.wc-gpd-template-font-pick' ).forEach( ( el ) => {
		el.addEventListener( 'change', saveTemplateFonts );
	} );

	[ 'wc_gpd_sel_width', 'wc_gpd_sel_height', 'wc_gpd_sel_left', 'wc_gpd_sel_top' ].forEach( ( id ) => {
		document.getElementById( id )?.addEventListener( 'change', applySelectionDimsFromInputs );
	} );
	document.getElementById( 'wc_gpd_lock_aspect' )?.addEventListener( 'change', ( event ) => {
		lockAspectRatio = !! event.target.checked;
	} );

	if ( showRulerToggle ) {
		showRulerToggle.addEventListener( 'change', () => {
			updateRulers();
			applyResponsiveScale();
		} );
	}
	if ( showMeasurementsToggle ) {
		showMeasurementsToggle.addEventListener( 'change', updateRulers );
	}

	function handlePaletteSelectChange( role, nextId ) {
		const obj = canvas.getActiveObject();
		if ( ! obj || ! objectHasColor( obj ) ) {
			return;
		}
		const prevId = getPaletteIdForRole( obj, role );
		if ( isCustomPaletteId( nextId ) && ! isCustomPaletteId( prevId ) ) {
			setCustomColorsForRole( obj, role, [] );
		}
		setPaletteIdForRole( obj, role, nextId );
		syncColorsPanel( obj );
	}

	function handleAddCustomColor( role ) {
		const obj = canvas.getActiveObject();
		if ( ! obj || ! objectHasColor( obj ) ) {
			return;
		}
		const source = getLayerColorSource( obj, role );
		if ( source.type !== 'custom' ) {
			return;
		}
		openNativeColorPicker( '#000000', ( color ) => {
			source.colors.push( color );
			source.persist();
			syncColorsPanel( obj );
		} );
	}

	document.getElementById( 'wc-gpd-template-add-text' )?.addEventListener( 'click', addTextField );
	document.getElementById( 'wc-gpd-add-palette' )?.addEventListener( 'click', () => {
		const id = `pal_${ Date.now().toString( 36 ) }`;
		palettesData.palettes = palettesData.palettes || [];
		palettesData.palettes.push( { id, name: 'New palette', colors: [ '#000000' ] } );
		savePalettesToInput();
		renderPalettesAdmin();
		populateLayerPaletteSelect();
	} );
	document.getElementById( 'wc_gpd_layer_palette_id' )?.addEventListener( 'change', ( event ) => {
		handlePaletteSelectChange( 'fill', event.target.value );
	} );
	document.getElementById( 'wc_gpd_stroke_layer_palette_id' )?.addEventListener( 'change', ( event ) => {
		handlePaletteSelectChange( 'stroke', event.target.value );
	} );
	document.getElementById( 'wc-gpd-layer-add-color' )?.addEventListener( 'click', () => {
		handleAddCustomColor( 'fill' );
	} );
	document.getElementById( 'wc-gpd-stroke-layer-add-color' )?.addEventListener( 'click', () => {
		handleAddCustomColor( 'stroke' );
	} );
	document.getElementById( 'wc-gpd-add-global-color' )?.addEventListener( 'click', () => {
		palettesData.global_colors = palettesData.global_colors || [];
		palettesData.global_colors.push( '#000000' );
		savePalettesToInput();
		renderGlobalColorsList();
		syncColorsPanel( canvas.getActiveObject() );
	} );
	const useSameColorsCheckbox = document.getElementById( 'wc_gpd_ps_use_same_colors' );
	const globalColorsPanel = document.getElementById( 'wc-gpd-global-colors-panel' );
	if ( useSameColorsCheckbox ) {
		useSameColorsCheckbox.addEventListener( 'change', () => {
			palettesData.use_global_colors = useSameColorsCheckbox.checked;
			savePalettesToInput();
			if ( globalColorsPanel ) {
				globalColorsPanel.hidden = ! useSameColorsCheckbox.checked;
			}
			syncColorsPanel( canvas.getActiveObject() );
		} );
	}
	[
		'wc_gpd_ps_allow_free_text',
		'wc_gpd_ps_allow_layers_panel',
		'wc_gpd_ps_allow_details_panel',
		'wc_gpd_ps_allow_customer_graphics',
	].forEach( ( name ) => {
		document.querySelector( `input[name="${ name }"]` )?.addEventListener( 'change', syncCustomerMockup );
	} );
	document.getElementById( 'wc-gpd-template-add-graphic-slot' )?.addEventListener( 'click', () => {
		cancelFreeformMode();
		addGraphicSlot();
	} );

	$( '.wc-gpd-add-template-rect' ).on( 'click', () => { cancelFreeformMode(); addRect( false ); } );
	$( '.wc-gpd-add-template-square' ).on( 'click', () => { cancelFreeformMode(); addRect( true ); } );
	$( '.wc-gpd-add-template-circle' ).on( 'click', () => { cancelFreeformMode(); addCircle(); } );
	$( '.wc-gpd-add-template-hexagon' ).on( 'click', () => { cancelFreeformMode(); addPolygonShape( 6 ); } );
	$( '.wc-gpd-add-template-octagon' ).on( 'click', () => { cancelFreeformMode(); addPolygonShape( 8 ); } );
	$( '.wc-gpd-add-template-heart' ).on( 'click', () => { cancelFreeformMode(); addHeartShape(); } );
	document.getElementById( 'wc-gpd-add-template-freeform' )?.addEventListener( 'click', startFreeformMode );

	if ( unitsSelect ) {
		unitsSelect.addEventListener( 'change', () => {
			displayUnit = unitsSelect.value || 'px';
			updateUnitSuffixes();
			const active = canvas.getActiveObject();
			if ( active ) {
				syncSelectionDimsPanel( active );
			}
			updateRulers();
		} );
	}

	canvas.on( 'mouse:down', ( event ) => {
		if ( ! freeformMode ) {
			return;
		}
		const pointer = canvas.getPointer( event.e );
		if ( freeformPoints.length >= 3 ) {
			const first = freeformPoints[ 0 ];
			const dist = Math.hypot( pointer.x - first.x, pointer.y - first.y );
			if ( dist < 12 ) {
				finishFreeformShape();
				return;
			}
		}
		freeformPoints.push( { x: pointer.x, y: pointer.y } );
		updateFreeformPreview( false );
	} );

	canvas.on( 'mouse:dblclick', () => {
		if ( freeformMode ) {
			finishFreeformShape();
		}
	} );
	$( '#wc-gpd-template-add-view' ).on( 'click', addView );
	$( '#wc-gpd-template-rename-view' ).on( 'click', renameView );
	$( '#wc-gpd-template-delete-view' ).on( 'click', deleteView );
	$( '#wc_gpd_canvas_width, #wc_gpd_canvas_height' ).on( 'change input', resizeCanvasDimensions );

	if ( popoutBtn && editorRoot && window.WcGpdPopout ) {
		popoutBtn.addEventListener( 'click', () => window.WcGpdPopout.toggle( editorRoot, applyResponsiveScale ) );
		editorRoot.addEventListener( 'wc-gpd-popout-closed', applyResponsiveScale );
	}

	function handleFormSave( event ) {
		sanitizeFormNumbersBeforeSave();
		if ( useSameColorsCheckbox ) {
			palettesData.use_global_colors = useSameColorsCheckbox.checked;
		}
		savePalettesToInput();
		saveJson();
	}

	const templateForm = document.getElementById( 'wc-gpd-template-form' );
	if ( templateForm ) {
		templateForm.addEventListener( 'submit', handleFormSave );
	}
	$( '#post' ).on( 'submit', handleFormSave );
	window.addEventListener( 'resize', applyResponsiveScale );

	initFontSelect();
	initAddMenuCollapsible();
	initMockupStudioNav();
	initAdminStudioNav();
	initAccordion();
	initContextAccordions();
	updateUnitSuffixes();
	loadPalettesFromInput();
	if ( useSameColorsCheckbox ) {
		palettesData.use_global_colors = useSameColorsCheckbox.checked;
	}
	renderPalettesAdmin();
	renderGlobalColorsList();
	populateLayerPaletteSelect();
	bindSteppers();
	bindTextEditor();
	bindCustomerAccessPanel();
	bindImagePropInputs();
	loadGraphicLibraries();
	loadJson();
	updateRulers();
	syncCustomerMockup();
	openAccordionSection( 'add' );
	applyResponsiveScale();
}( jQuery ) );
