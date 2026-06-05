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
	const strokeColorInput = document.getElementById( 'wc_gpd_template_stroke_color' );
	const strokeWidthInput = document.getElementById( 'wc_gpd_template_stroke_width' );
	const shapePropsFields = document.getElementById( 'wc-gpd-shape-props-fields' );
	const shapePropsHint = document.getElementById( 'wc-gpd-shape-props-hint' );
	const imagePropsPanel = document.getElementById( 'wc-gpd-image-props' );
	const textEditorPanel = document.getElementById( 'wc-gpd-text-editor' );
	const textEditorHint = document.getElementById( 'wc-gpd-text-editor-hint' );
	const layerColorsPanel = document.getElementById( 'wc-gpd-layer-colors-panel' );
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
		'wcGpdPlaceholderLabel', 'wcGpdPlaceholderKey', 'wcGpdShrinkToFit', 'wcGpdFitMode', 'wcGpdPaletteId',
		'wcGpdLockFont', 'wcGpdLockSize', 'wcGpdLockColor', 'wcGpdLockBold', 'wcGpdLockItalic', 'wcGpdLockAlign',
		'wcGpdLockUnderline', 'wcGpdLockLineHeight', 'wcGpdLockLetterSpacing',
		'wcGpdLockMove', 'wcGpdLockScale', 'strokeDashArray',
	];

	const CUSTOMER_ALLOW_MAP = {
		font: 'wcGpdLockFont',
		size: 'wcGpdLockSize',
		bold: 'wcGpdLockBold',
		italic: 'wcGpdLockItalic',
		underline: 'wcGpdLockUnderline',
		align: 'wcGpdLockAlign',
		line_height: 'wcGpdLockLineHeight',
		letter_spacing: 'wcGpdLockLetterSpacing',
		move: 'wcGpdLockMove',
	};

	if ( ! canvasEl || typeof fabric === 'undefined' ) {
		return;
	}

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

	const MOCKUP_CHECKBOX_MAP = {
		free_text: 'wc_gpd_ps_allow_free_text',
		font: 'wc_gpd_ps_allow_font_family',
		size: 'wc_gpd_ps_allow_font_size',
		color: 'wc_gpd_ps_allow_text_color',
		bold: 'wc_gpd_ps_allow_bold',
		italic: 'wc_gpd_ps_allow_italic',
		underline: 'wc_gpd_ps_allow_underline',
		line_height: 'wc_gpd_ps_allow_line_height',
		letter_spacing: 'wc_gpd_ps_allow_letter_spacing',
		align: 'wc_gpd_ps_allow_text_align',
		details: 'wc_gpd_ps_allow_details_panel',
		graphics: 'wc_gpd_ps_allow_customer_graphics',
		layers: 'wc_gpd_ps_allow_layers_panel',
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

	function isShape( obj ) {
		if ( ! obj || obj.wcGpdLayerType === 'graphic_slot' || obj.wcGpdGraphicSlot ) {
			return false;
		}
		const shapeTypes = [ 'rect', 'circle', 'ellipse', 'polygon', 'path', 'polyline', 'group' ];
		return shapeTypes.indexOf( obj.type ) >= 0 && obj.wcGpdTemplateLayer;
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
		if ( ! obj || ! isShape( obj ) ) {
			return;
		}
		obj.set( {
			stroke: isBbox ? defaultBboxColor() : ( strokeColorInput ? strokeColorInput.value : defaultOutlineColor() ),
			strokeWidth: isBbox ? defaultBboxWidth() : ( strokeWidthInput ? parseFloat( strokeWidthInput.value ) : defaultOutlineWidth() ),
			fill: 'transparent',
			strokeDashArray: isBbox ? [ 6, 4 ] : null,
		} );
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
		if ( ! obj || ! isShape( obj ) ) {
			return;
		}
		obj.wcGpdTemplateLayer = true;
		if ( ! obj.wcGpdUid ) {
			obj.wcGpdUid = uid();
		}
		if ( outlineToggle ) {
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
		[ shapePropsFields, imagePropsPanel, textEditorPanel, graphicSlotPropsPanel ].forEach( ( el ) => {
			if ( el ) {
				el.hidden = true;
			}
		} );
		if ( textEditorHint ) {
			textEditorHint.hidden = false;
		}
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
		const blocks = contextPane ? contextPane.querySelectorAll( '.wc-gpd-context-block' ) : [];
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

	function colorsForLayer( obj ) {
		if ( palettesData.use_global_colors ) {
			return palettesData.global_colors || [ '#000000' ];
		}
		const paletteId = obj && obj.wcGpdPaletteId ? obj.wcGpdPaletteId : 'pal_default';
		const palette = getPaletteById( paletteId ) || getPaletteById( 'pal_default' );
		return palette ? palette.colors : [ '#000000' ];
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

	function populateLayerPaletteSelect() {
		const select = document.getElementById( 'wc_gpd_layer_palette_id' );
		if ( ! select ) {
			return;
		}
		const current = select.value;
		select.innerHTML = '';
		( palettesData.palettes || [] ).forEach( ( palette ) => {
			const opt = document.createElement( 'option' );
			opt.value = palette.id;
			opt.textContent = palette.name || palette.id;
			select.appendChild( opt );
		} );
		if ( current ) {
			select.value = current;
		}
	}

	function syncColorsPanel( obj ) {
		if ( ! objectHasColor( obj ) ) {
			return;
		}

		const paletteSelect = document.getElementById( 'wc_gpd_layer_palette_id' );
		const swatchesEl = document.getElementById( 'wc-gpd-layer-color-swatches' );
		const useGlobal = palettesData.use_global_colors;

		if ( paletteSelect ) {
			paletteSelect.closest( 'p' ).hidden = useGlobal;
			if ( ! useGlobal ) {
				populateLayerPaletteSelect();
				paletteSelect.value = obj.wcGpdPaletteId || 'pal_default';
			}
		}

		if ( ! swatchesEl ) {
			return;
		}
		swatchesEl.innerHTML = '';
		const colors = colorsForLayer( obj );
		const currentFill = obj.fill || '#000000';
		colors.forEach( ( color ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wc-gpd-color-swatch';
			btn.style.backgroundColor = color;
			btn.title = color;
			btn.classList.toggle( 'is-active', color.toLowerCase() === String( currentFill ).toLowerCase() );
			btn.addEventListener( 'click', () => {
				if ( isShape( obj ) && obj.wcGpdOutlineLayer && obj.fill === 'transparent' ) {
					obj.set( 'stroke', color );
				} else {
					obj.set( 'fill', color );
				}
				canvas.requestRenderAll();
				syncColorsPanel( obj );
			} );
			swatchesEl.appendChild( btn );
		} );
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

	function syncCustomerMockup() {
		Object.keys( MOCKUP_CHECKBOX_MAP ).forEach( ( key ) => {
			const checkbox = document.querySelector( `input[name="${ MOCKUP_CHECKBOX_MAP[ key ] }"]` );
			const chip = document.querySelector( `.wc-gpd-mockup-chip[data-mockup="${ key }"]` );
			if ( chip ) {
				chip.hidden = checkbox ? ! checkbox.checked : true;
			}
		} );
		const fieldsEl = document.getElementById( 'wc-gpd-mockup-fields' );
		if ( fieldsEl ) {
			const hasSidebarFields = canvas.getObjects().some( ( obj ) => isPlaceholder( obj ) );
			const detailsCheckbox = document.querySelector( 'input[name="wc_gpd_ps_allow_details_panel"]' );
			const detailsOn = ! detailsCheckbox || detailsCheckbox.checked;
			fieldsEl.hidden = ! hasSidebarFields || ! detailsOn;
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
		const lockBox = document.getElementById( 'wc_gpd_text_lock_box' );

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
		if ( lockBox ) {
			obj.wcGpdLockMove = lockBox.checked;
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

		if ( shapePropsHint ) {
			shapePropsHint.hidden = shapeSelected || imageSelected || textSelected || slotSelected;
		}

		if ( shapeSelected && shapePropsFields ) {
			shapePropsFields.hidden = false;
		}
		if ( imageSelected && imagePropsPanel ) {
			imagePropsPanel.hidden = false;
			syncImagePropsPanel( active );
		}
		if ( isTextLayer( active ) && textEditorPanel ) {
			textEditorPanel.hidden = false;
			if ( textEditorHint ) {
				textEditorHint.hidden = true;
			}
			syncTextEditorPanel( active );
		}
		if ( slotSelected && graphicSlotPropsPanel ) {
			graphicSlotPropsPanel.hidden = false;
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
		if ( shapeSelected && strokeColorInput ) {
			strokeColorInput.value = active.stroke || defaultOutlineColor();
		}
		if ( shapeSelected && strokeWidthInput ) {
			strokeWidthInput.value = String( active.strokeWidth || defaultOutlineWidth() );
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
		const lockBox = document.getElementById( 'wc_gpd_text_lock_box' );
		const customerFills = document.getElementById( 'wc_gpd_text_customer_fills' );
		const boxW = document.getElementById( 'wc_gpd_placeholder_width' );
		const boldBtn = document.getElementById( 'wc_gpd_tpl_bold' );
		const italicBtn = document.getElementById( 'wc_gpd_tpl_italic' );
		const underlineBtn = document.getElementById( 'wc_gpd_tpl_underline' );

		normalizeTextLayer( obj );

		if ( layerLabelInput ) {
			layerLabelInput.value = obj.wcGpdLayerLabel || obj.wcGpdPlaceholderLabel || '';
		}
		if ( lockBox ) {
			lockBox.checked = !! obj.wcGpdLockMove;
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
		} else {
			obj.wcGpdMockupImage = false;
			obj.wcGpdGraphicLayer = true;
			obj.wcGpdLayerType = 'graphic';
			obj.wcGpdExportGraphic = obj.wcGpdExportGraphic !== false;
			obj.wcGpdLockMove = false;
			obj.wcGpdLockScale = false;
			obj.wcGpdCustomerMovable = true;
			obj.wcGpdCustomerResizable = true;
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

	function loadView( viewId ) {
		persistCanvasToActiveView();
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
				enlivened.forEach( ( obj ) => {
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
			wcGpdUid: uid(),
			wcGpdTemplateLayer: true,
			wcGpdLayerType: 'text',
			wcGpdLayerLabel: '',
			wcGpdFitMode: 'none',
			wcGpdPaletteId: 'pal_default',
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
			loadView( documentData.views[ 0 ].id );
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
		loadView( documentData.views[ 0 ].id );
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
		const lockBox = document.getElementById( 'wc_gpd_text_lock_box' );
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
		if ( lockBox ) {
			lockBox.addEventListener( 'change', () => {
				const obj = activeTextObject();
				if ( obj ) {
					obj.wcGpdLockMove = lockBox.checked;
					const allowMove = document.getElementById( 'wc_gpd_allow_move' );
					if ( allowMove ) {
						allowMove.checked = ! lockBox.checked;
					}
					renderLayersList();
				}
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
					const obj = activeTextObject();
					if ( obj ) {
						applyAllowCheckboxes( obj );
					}
				} );
			}
		} );
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
				}
			} );
		}
		if ( allowResize ) {
			allowResize.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicImage( obj ) ) {
					obj.wcGpdLockScale = ! allowResize.checked;
					obj.wcGpdCustomerResizable = allowResize.checked;
				}
			} );
		}
		if ( lockAspect ) {
			lockAspect.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicImage( obj ) ) {
					obj.wcGpdLockAspect = lockAspect.checked;
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

	if ( strokeColorInput ) {
		strokeColorInput.addEventListener( 'input', () => {
			const active = canvas.getActiveObject();
			if ( ! active || ! isShape( active ) || active.wcGpdBoundingBox ) {
				return;
			}
			active.set( 'stroke', strokeColorInput.value );
			canvas.requestRenderAll();
		} );
	}

	if ( strokeWidthInput ) {
		strokeWidthInput.addEventListener( 'input', () => {
			const active = canvas.getActiveObject();
			if ( ! active || ! isShape( active ) || active.wcGpdBoundingBox ) {
				return;
			}
			active.set( 'strokeWidth', parseFloat( strokeWidthInput.value ) || 1 );
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
		showRulerToggle.addEventListener( 'change', updateRulers );
	}
	if ( showMeasurementsToggle ) {
		showMeasurementsToggle.addEventListener( 'change', updateRulers );
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
		const obj = canvas.getActiveObject();
		if ( obj && objectHasColor( obj ) ) {
			obj.wcGpdPaletteId = event.target.value;
			syncColorsPanel( obj );
		}
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
	Object.values( MOCKUP_CHECKBOX_MAP ).forEach( ( name ) => {
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
	initAdminStudioNav();
	initAccordion();
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
	bindImagePropInputs();
	loadGraphicLibraries();
	loadJson();
	updateRulers();
	syncCustomerMockup();
	openAccordionSection( 'add' );
	applyResponsiveScale();
}( jQuery ) );
