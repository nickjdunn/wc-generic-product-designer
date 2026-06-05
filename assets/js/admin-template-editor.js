/**
 * Admin template editor — mockups, text, placeholders, graphics, outlines.
 */
( function ( $ ) {
	'use strict';

	const canvasEl = document.getElementById( 'wc-gpd-template-canvas' );
	const jsonInput = document.getElementById( 'wc_gpd_template_json' );
	const widthInput = document.getElementById( 'wc_gpd_template_canvas_width' );
	const heightInput = document.getElementById( 'wc_gpd_template_canvas_height' );
	const maxViewsInput = document.getElementById( 'wc_gpd_template_max_views' );
	const viewTabsEl = document.getElementById( 'wc-gpd-template-view-tabs' );
	const layersListEl = document.getElementById( 'wc-gpd-template-layers-list' );
	const layersEmptyHint = document.getElementById( 'wc-gpd-layers-empty-hint' );
	const outlineToggle = document.getElementById( 'wc_gpd_template_is_outline' );
	const bboxToggle = document.getElementById( 'wc_gpd_template_is_bbox' );
	const strokeColorInput = document.getElementById( 'wc_gpd_template_stroke_color' );
	const strokeWidthInput = document.getElementById( 'wc_gpd_template_stroke_width' );
	const shapePropsFields = document.getElementById( 'wc-gpd-shape-props-fields' );
	const shapePropsHint = document.getElementById( 'wc-gpd-shape-props-hint' );
	const imagePropsPanel = document.getElementById( 'wc-gpd-image-props' );
	const textPropsPanel = document.getElementById( 'wc-gpd-text-props' );
	const placeholderPropsPanel = document.getElementById( 'wc-gpd-placeholder-props' );
	const graphicPropsPanel = document.getElementById( 'wc-gpd-graphic-props' );
	const graphicSlotPropsPanel = document.getElementById( 'wc-gpd-graphic-slot-props' );
	const mockupVisibleToggle = document.getElementById( 'wc_gpd_template_mockup_visible' );
	const deleteImageBtn = document.getElementById( 'wc-gpd-template-delete-image' );
	const addImageBtn = document.getElementById( 'wc-gpd-template-add-image' );
	const editorRoot = document.getElementById( 'wc-gpd-template-editor-root' );
	const popoutBtn = document.getElementById( 'wc-gpd-template-popout' );
	const graphicLibraryInput = document.getElementById( 'wc_gpd_graphic_library' );
	const graphicLibraryPreview = document.getElementById( 'wc-gpd-graphic-library-preview' );

	const SERIALIZE_PROPS = [
		'wcGpdUid', 'wcGpdLayerType', 'wcGpdTemplateLayer', 'wcGpdOutlineLayer', 'wcGpdBoundingBox',
		'wcGpdMockupImage', 'wcGpdMockupVisible', 'wcGpdAttachmentId', 'wcGpdGraphicLayer', 'wcGpdGraphicSlot',
		'wcGpdExportGraphic', 'wcGpdCustomerMovable', 'wcGpdCustomerResizable',
		'wcGpdPlaceholderLabel', 'wcGpdPlaceholderKey', 'wcGpdShrinkToFit',
		'wcGpdLockFont', 'wcGpdLockSize', 'wcGpdLockColor', 'wcGpdLockBold', 'wcGpdLockItalic', 'wcGpdLockAlign',
		'wcGpdLockMove', 'wcGpdLockScale', 'strokeDashArray',
	];

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
	const maxViews = parseInt( maxViewsInput ? maxViewsInput.value : '1', 10 ) || 1;
	let graphicLibraryIds = [];

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
		const field = document.getElementById( 'wc_gpd_max_design_views' );
		const value = field ? parseInt( field.value, 10 ) : maxViews;
		return Math.min( 6, Math.max( 1, value || 1 ) );
	}

	function getActiveView() {
		return documentData.views.find( ( view ) => view.id === activeViewId ) || documentData.views[ 0 ];
	}

	function isShape( obj ) {
		return obj && ( obj.type === 'rect' || obj.type === 'circle' || obj.type === 'ellipse' ) && obj.wcGpdLayerType !== 'graphic_slot';
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

	function isTemplateText( obj ) {
		return obj && ( obj.type === 'i-text' || obj.type === 'text' || obj.type === 'textbox' ) && obj.wcGpdLayerType === 'text';
	}

	function isPlaceholder( obj ) {
		return obj && ( obj.type === 'textbox' || obj.type === 'i-text' ) && obj.wcGpdLayerType === 'placeholder';
	}

	function layerLabel( obj ) {
		if ( ! obj ) {
			return 'Layer';
		}
		if ( isMockupImage( obj ) ) {
			return 'Mockup photo';
		}
		if ( isGraphicImage( obj ) ) {
			return 'Graphic';
		}
		if ( isGraphicSlot( obj ) ) {
			return 'Graphic pick area';
		}
		if ( isPlaceholder( obj ) ) {
			return obj.wcGpdPlaceholderLabel || 'Variable field';
		}
		if ( isTemplateText( obj ) ) {
			const text = ( obj.text || '' ).trim();
			return text ? text.slice( 0, 32 ) : 'Fixed text';
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
		[ shapePropsFields, imagePropsPanel, textPropsPanel, placeholderPropsPanel, graphicPropsPanel, graphicSlotPropsPanel ].forEach( ( el ) => {
			if ( el ) {
				el.hidden = true;
			}
		} );
	}

	function updateSelectionPanels() {
		const active = canvas.getActiveObject();
		const shapeSelected = isShape( active );
		const imageSelected = isMockupImage( active );
		const textSelected = isTemplateText( active );
		const placeholderSelected = isPlaceholder( active );
		const graphicSelected = isGraphicImage( active );
		const slotSelected = isGraphicSlot( active );

		hideAllPropPanels();

		if ( shapePropsHint ) {
			shapePropsHint.hidden = shapeSelected || imageSelected || textSelected || placeholderSelected || graphicSelected || slotSelected;
		}

		if ( shapeSelected && shapePropsFields ) {
			shapePropsFields.hidden = false;
		}
		if ( imageSelected && imagePropsPanel ) {
			imagePropsPanel.hidden = false;
		}
		if ( textSelected && textPropsPanel ) {
			textPropsPanel.hidden = false;
			syncTextPropsPanel( active );
		}
		if ( placeholderSelected && placeholderPropsPanel ) {
			placeholderPropsPanel.hidden = false;
			syncPlaceholderPropsPanel( active );
		}
		if ( graphicSelected && graphicPropsPanel ) {
			graphicPropsPanel.hidden = false;
			syncGraphicPropsPanel( active );
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

		renderLayersList();
	}

	function syncTextPropsPanel( obj ) {
		const content = document.getElementById( 'wc_gpd_template_text_content' );
		const font = document.getElementById( 'wc_gpd_template_font_family' );
		const size = document.getElementById( 'wc_gpd_template_font_size' );
		const color = document.getElementById( 'wc_gpd_template_text_color' );
		const shrink = document.getElementById( 'wc_gpd_template_shrink_fit' );
		if ( content ) {
			content.value = obj.text || '';
		}
		if ( font ) {
			font.value = obj.fontFamily || 'Arial';
		}
		if ( size ) {
			size.value = String( Math.round( obj.fontSize || 32 ) );
		}
		if ( color ) {
			color.value = obj.fill || '#000000';
		}
		if ( shrink ) {
			shrink.checked = !! obj.wcGpdShrinkToFit;
		}
		setLockCheckboxes( 'wc_gpd_lock_', obj );
	}

	function syncPlaceholderPropsPanel( obj ) {
		const label = document.getElementById( 'wc_gpd_placeholder_label' );
		const key = document.getElementById( 'wc_gpd_placeholder_key' );
		const def = document.getElementById( 'wc_gpd_placeholder_default' );
		const boxW = document.getElementById( 'wc_gpd_placeholder_width' );
		const shrink = document.getElementById( 'wc_gpd_placeholder_shrink_fit' );
		if ( label ) {
			label.value = obj.wcGpdPlaceholderLabel || '';
		}
		if ( key ) {
			key.value = obj.wcGpdPlaceholderKey || '';
		}
		if ( def ) {
			def.value = obj.text || '';
		}
		if ( boxW ) {
			boxW.value = String( Math.round( obj.width || 240 ) );
		}
		if ( shrink ) {
			shrink.checked = !! obj.wcGpdShrinkToFit;
		}
		setLockCheckboxes( 'wc_gpd_ph_lock_', obj );
	}

	function setLockCheckboxes( prefix, obj ) {
		const map = {
			font: 'wcGpdLockFont',
			size: 'wcGpdLockSize',
			color: 'wcGpdLockColor',
			bold: 'wcGpdLockBold',
			italic: 'wcGpdLockItalic',
			align: 'wcGpdLockAlign',
			move: 'wcGpdLockMove',
		};
		Object.keys( map ).forEach( ( key ) => {
			const el = document.getElementById( prefix + key );
			if ( el ) {
				el.checked = !! obj[ map[ key ] ];
			}
		} );
	}

	function applyLockCheckboxes( prefix, obj ) {
		const map = {
			font: 'wcGpdLockFont',
			size: 'wcGpdLockSize',
			color: 'wcGpdLockColor',
			bold: 'wcGpdLockBold',
			italic: 'wcGpdLockItalic',
			align: 'wcGpdLockAlign',
			move: 'wcGpdLockMove',
		};
		Object.keys( map ).forEach( ( key ) => {
			const el = document.getElementById( prefix + key );
			if ( el ) {
				obj[ map[ key ] ] = el.checked;
			}
		} );
	}

	function syncGraphicPropsPanel( obj ) {
		const exp = document.getElementById( 'wc_gpd_graphic_export' );
		const move = document.getElementById( 'wc_gpd_graphic_lock_move' );
		const scale = document.getElementById( 'wc_gpd_graphic_lock_scale' );
		if ( exp ) {
			exp.checked = obj.wcGpdExportGraphic !== false;
		}
		if ( move ) {
			move.checked = !! obj.wcGpdLockMove || obj.wcGpdCustomerMovable === false;
		}
		if ( scale ) {
			scale.checked = !! obj.wcGpdLockScale || obj.wcGpdCustomerResizable === false;
		}
	}

	function syncGraphicSlotPropsPanel( obj ) {
		const move = document.getElementById( 'wc_gpd_slot_lock_move' );
		const scale = document.getElementById( 'wc_gpd_slot_lock_scale' );
		if ( move ) {
			move.checked = ! obj.wcGpdCustomerMovable;
		}
		if ( scale ) {
			scale.checked = ! obj.wcGpdCustomerResizable;
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
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'wc-gpd-tpl-layer-item' + ( canvas.getActiveObject() === obj ? ' is-active' : '' );
			btn.textContent = layerLabel( obj );
			btn.addEventListener( 'click', () => {
				canvas.setActiveObject( obj );
				canvas.requestRenderAll();
				updateSelectionPanels();
			} );
			li.appendChild( btn );
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
		if ( isPlaceholder( obj ) ) {
			obj.set( { editable: true } );
		}
		if ( isTemplateText( obj ) ) {
			obj.set( { editable: true } );
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
	}

	function addTemplateText() {
		const text = new fabric.IText( 'Your text here', {
			left: width / 2,
			top: height / 2,
			originX: 'center',
			originY: 'center',
			fontFamily: 'Arial',
			fontSize: 32,
			fill: '#000000',
			wcGpdUid: uid(),
			wcGpdTemplateLayer: true,
			wcGpdLayerType: 'text',
		} );
		canvas.add( text );
		canvas.setActiveObject( text );
		sortLayers();
		canvas.requestRenderAll();
		updateSelectionPanels();
	}

	function addPlaceholder() {
		const key = `field_${ Date.now().toString( 36 ) }`;
		const box = new fabric.Textbox( 'Name', {
			left: width / 2,
			top: height / 2,
			originX: 'center',
			originY: 'center',
			width: 240,
			fontFamily: 'Arial',
			fontSize: 32,
			fill: '#000000',
			wcGpdUid: uid(),
			wcGpdTemplateLayer: true,
			wcGpdLayerType: 'placeholder',
			wcGpdPlaceholderLabel: 'Name',
			wcGpdPlaceholderKey: key,
			wcGpdShrinkToFit: true,
			wcGpdLockFont: true,
			wcGpdLockSize: true,
			wcGpdLockColor: true,
			wcGpdLockMove: true,
		} );
		canvas.add( box );
		canvas.setActiveObject( box );
		sortLayers();
		canvas.requestRenderAll();
		updateSelectionPanels();
	}

	function addMockupImage( attachment ) {
		const url = attachment.url;
		if ( ! url ) {
			return;
		}
		fabric.Image.fromURL( url, ( img ) => {
			if ( ! img ) {
				return;
			}
			const scale = Math.min( width / img.width, height / img.height ) * 0.75;
			img.set( {
				left: width / 2, top: height / 2, originX: 'center', originY: 'center',
				scaleX: scale, scaleY: scale,
				wcGpdUid: uid(), wcGpdTemplateLayer: true, wcGpdMockupImage: true,
				wcGpdMockupVisible: true, wcGpdLayerType: 'mockup', wcGpdAttachmentId: attachment.id,
			} );
			canvas.add( img );
			canvas.setActiveObject( img );
			sortLayers();
			canvas.requestRenderAll();
			updateSelectionPanels();
		}, { crossOrigin: 'anonymous' } );
	}

	function addGraphicImage( attachment ) {
		const url = attachment.url;
		if ( ! url ) {
			return;
		}
		fabric.Image.fromURL( url, ( img ) => {
			if ( ! img ) {
				return;
			}
			const scale = Math.min( 120 / img.width, 120 / img.height, 1 );
			img.set( {
				left: width / 2, top: height / 2, originX: 'center', originY: 'center',
				scaleX: scale, scaleY: scale,
				wcGpdUid: uid(), wcGpdTemplateLayer: true, wcGpdGraphicLayer: true,
				wcGpdLayerType: 'graphic', wcGpdExportGraphic: true,
				wcGpdAttachmentId: attachment.id, wcGpdLockMove: true, wcGpdLockScale: true,
			} );
			canvas.add( img );
			canvas.setActiveObject( img );
			sortLayers();
			canvas.requestRenderAll();
			updateSelectionPanels();
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

	function loadGraphicLibrary() {
		if ( ! graphicLibraryInput || ! graphicLibraryInput.value ) {
			graphicLibraryIds = [];
			renderGraphicLibraryPreview();
			return;
		}
		try {
			graphicLibraryIds = JSON.parse( graphicLibraryInput.value ) || [];
		} catch ( e ) {
			graphicLibraryIds = [];
		}
		renderGraphicLibraryPreview();
	}

	function saveGraphicLibrary() {
		if ( graphicLibraryInput ) {
			graphicLibraryInput.value = JSON.stringify( graphicLibraryIds );
		}
		renderGraphicLibraryPreview();
	}

	function renderGraphicLibraryPreview() {
		if ( ! graphicLibraryPreview || ! window.wp || ! wp.media ) {
			return;
		}
		graphicLibraryPreview.innerHTML = '';
		graphicLibraryIds.forEach( ( id ) => {
			const attachment = wp.media.attachment( id );
			attachment.fetch().then( () => {
				const url = attachment.get( 'url' );
				if ( ! url ) {
					return;
				}
				const li = document.createElement( 'li' );
				const img = document.createElement( 'img' );
				img.src = url;
				img.alt = attachment.get( 'title' ) || '';
				li.appendChild( img );
				graphicLibraryPreview.appendChild( li );
			} );
		} );
	}

	function manageGraphicLibrary() {
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		const frame = wp.media( {
			title: 'Graphic library',
			button: { text: 'Use selected' },
			multiple: true,
			library: { type: [ 'image' ] },
		} );
		frame.on( 'open', () => {
			const selection = frame.state().get( 'selection' );
			graphicLibraryIds.forEach( ( id ) => {
				const attachment = wp.media.attachment( id );
				attachment.fetch();
				selection.add( attachment );
			} );
		} );
		frame.on( 'select', () => {
			graphicLibraryIds = frame.state().get( 'selection' ).map( ( att ) => att.id );
			saveGraphicLibrary();
		} );
		frame.open();
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
		if ( jsonInput ) {
			jsonInput.value = JSON.stringify( documentData );
		}
		saveGraphicLibrary();
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
		documentData.views = documentData.views.slice( 0, getMaxViews() );
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
		const label = document.getElementById( 'wc-gpd-canvas-size-label' );
		if ( label ) {
			label.textContent = `${ width } × ${ height } px`;
		}
	}

	function resizeCanvasDimensions() {
		syncDimensionFields();
		canvas.setWidth( width );
		canvas.setHeight( height );
		canvas.backgroundColor = canvasBgColor();
		canvas.calcOffset();
		canvas.requestRenderAll();
		applyResponsiveScale();
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

	function bindTextPropInputs() {
		const content = document.getElementById( 'wc_gpd_template_text_content' );
		const font = document.getElementById( 'wc_gpd_template_font_family' );
		const size = document.getElementById( 'wc_gpd_template_font_size' );
		const color = document.getElementById( 'wc_gpd_template_text_color' );
		const shrink = document.getElementById( 'wc_gpd_template_shrink_fit' );

		function activeText() {
			const obj = canvas.getActiveObject();
			return isTemplateText( obj ) ? obj : null;
		}

		if ( content ) {
			content.addEventListener( 'input', () => {
				const obj = activeText();
				if ( obj ) {
					obj.set( 'text', content.value );
					canvas.requestRenderAll();
					renderLayersList();
				}
			} );
		}
		[ font, size, color, shrink ].forEach( ( el ) => {
			if ( ! el ) {
				return;
			}
			el.addEventListener( 'input', () => {
				const obj = activeText();
				if ( ! obj ) {
					return;
				}
				if ( font && el === font ) {
					obj.set( 'fontFamily', font.value );
				}
				if ( size && el === size ) {
					obj.set( 'fontSize', parseInt( size.value, 10 ) || 32 );
				}
				if ( color && el === color ) {
					obj.set( 'fill', color.value );
				}
				if ( shrink && el === shrink ) {
					obj.wcGpdShrinkToFit = shrink.checked;
				}
				canvas.requestRenderAll();
			} );
		} );

		[ 'font', 'size', 'color', 'bold', 'italic', 'align', 'move' ].forEach( ( key ) => {
			const el = document.getElementById( 'wc_gpd_lock_' + key );
			if ( el ) {
				el.addEventListener( 'change', () => {
					const obj = activeText();
					if ( obj ) {
						applyLockCheckboxes( 'wc_gpd_lock_', obj );
					}
				} );
			}
		} );
	}

	function bindPlaceholderPropInputs() {
		const label = document.getElementById( 'wc_gpd_placeholder_label' );
		const key = document.getElementById( 'wc_gpd_placeholder_key' );
		const def = document.getElementById( 'wc_gpd_placeholder_default' );
		const boxW = document.getElementById( 'wc_gpd_placeholder_width' );
		const shrink = document.getElementById( 'wc_gpd_placeholder_shrink_fit' );

		function activePh() {
			const obj = canvas.getActiveObject();
			return isPlaceholder( obj ) ? obj : null;
		}

		if ( label ) {
			label.addEventListener( 'input', () => {
				const obj = activePh();
				if ( obj ) {
					obj.wcGpdPlaceholderLabel = label.value;
					renderLayersList();
				}
			} );
		}
		if ( key ) {
			key.addEventListener( 'input', () => {
				const obj = activePh();
				if ( obj ) {
					obj.wcGpdPlaceholderKey = key.value.replace( /\s+/g, '_' ).toLowerCase();
				}
			} );
		}
		if ( def ) {
			def.addEventListener( 'input', () => {
				const obj = activePh();
				if ( obj ) {
					obj.set( 'text', def.value );
					canvas.requestRenderAll();
				}
			} );
		}
		if ( boxW ) {
			boxW.addEventListener( 'input', () => {
				const obj = activePh();
				if ( obj ) {
					obj.set( 'width', parseInt( boxW.value, 10 ) || 240 );
					canvas.requestRenderAll();
				}
			} );
		}
		if ( shrink ) {
			shrink.addEventListener( 'change', () => {
				const obj = activePh();
				if ( obj ) {
					obj.wcGpdShrinkToFit = shrink.checked;
				}
			} );
		}
		[ 'font', 'size', 'color', 'bold', 'italic', 'align', 'move' ].forEach( ( k ) => {
			const el = document.getElementById( 'wc_gpd_ph_lock_' + k );
			if ( el ) {
				el.addEventListener( 'change', () => {
					const obj = activePh();
					if ( obj ) {
						applyLockCheckboxes( 'wc_gpd_ph_lock_', obj );
					}
				} );
			}
		} );
	}

	function bindGraphicPropInputs() {
		const exp = document.getElementById( 'wc_gpd_graphic_export' );
		const move = document.getElementById( 'wc_gpd_graphic_lock_move' );
		const scale = document.getElementById( 'wc_gpd_graphic_lock_scale' );
		const slotMove = document.getElementById( 'wc_gpd_slot_lock_move' );
		const slotScale = document.getElementById( 'wc_gpd_slot_lock_scale' );

		if ( exp ) {
			exp.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicImage( obj ) ) {
					obj.wcGpdExportGraphic = exp.checked;
				}
			} );
		}
		if ( move ) {
			move.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicImage( obj ) ) {
					obj.wcGpdLockMove = move.checked;
					obj.wcGpdCustomerMovable = ! move.checked;
				}
			} );
		}
		if ( scale ) {
			scale.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicImage( obj ) ) {
					obj.wcGpdLockScale = scale.checked;
					obj.wcGpdCustomerResizable = ! scale.checked;
				}
			} );
		}
		if ( slotMove ) {
			slotMove.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicSlot( obj ) ) {
					obj.wcGpdCustomerMovable = ! slotMove.checked;
				}
			} );
		}
		if ( slotScale ) {
			slotScale.addEventListener( 'change', () => {
				const obj = canvas.getActiveObject();
				if ( isGraphicSlot( obj ) ) {
					obj.wcGpdCustomerResizable = ! slotScale.checked;
				}
			} );
		}
	}

	canvas.on( 'selection:created', updateSelectionPanels );
	canvas.on( 'selection:updated', updateSelectionPanels );
	canvas.on( 'selection:cleared', updateSelectionPanels );
	canvas.on( 'object:modified', () => {
		sortLayers();
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
	document.getElementById( 'wc-gpd-template-delete-graphic' )?.addEventListener( 'click', deleteActiveLayer );
	document.getElementById( 'wc-gpd-template-delete-slot' )?.addEventListener( 'click', deleteActiveLayer );
	document.getElementById( 'wc-gpd-template-delete-layer' )?.addEventListener( 'click', deleteActiveLayer );

	if ( addImageBtn && window.wp && wp.media ) {
		addImageBtn.addEventListener( 'click', () => {
			const frame = wp.media( { title: 'Add mockup photo', button: { text: 'Add to canvas' }, multiple: false } );
			frame.on( 'select', () => {
				const attachment = frame.state().get( 'selection' ).first().toJSON();
				addMockupImage( attachment );
			} );
			frame.open();
		} );
	}

	document.getElementById( 'wc-gpd-template-add-text' )?.addEventListener( 'click', addTemplateText );
	document.getElementById( 'wc-gpd-template-add-placeholder' )?.addEventListener( 'click', addPlaceholder );
	document.getElementById( 'wc-gpd-template-add-graphic-slot' )?.addEventListener( 'click', addGraphicSlot );
	document.getElementById( 'wc-gpd-manage-graphic-library' )?.addEventListener( 'click', manageGraphicLibrary );

	const addGraphicBtn = document.getElementById( 'wc-gpd-template-add-graphic' );
	if ( addGraphicBtn && window.wp && wp.media ) {
		addGraphicBtn.addEventListener( 'click', () => {
			const frame = wp.media( { title: 'Add fixed graphic', button: { text: 'Add to canvas' }, multiple: false, library: { type: [ 'image' ] } } );
			frame.on( 'select', () => {
				const attachment = frame.state().get( 'selection' ).first().toJSON();
				addGraphicImage( attachment );
			} );
			frame.open();
		} );
	}

	$( '.wc-gpd-add-template-rect' ).on( 'click', () => addRect( false ) );
	$( '.wc-gpd-add-template-square' ).on( 'click', () => addRect( true ) );
	$( '.wc-gpd-add-template-circle' ).on( 'click', addCircle );
	$( '#wc-gpd-template-add-view' ).on( 'click', addView );
	$( '#wc-gpd-template-rename-view' ).on( 'click', renameView );
	$( '#wc_gpd_canvas_width, #wc_gpd_canvas_height' ).on( 'change input', resizeCanvasDimensions );

	$( '#wc_gpd_max_design_views' ).on( 'change', () => {
		while ( documentData.views.length > getMaxViews() ) {
			documentData.views.pop();
		}
		renderTabs();
	} );

	if ( popoutBtn && editorRoot && window.WcGpdPopout ) {
		popoutBtn.addEventListener( 'click', () => window.WcGpdPopout.toggle( editorRoot, applyResponsiveScale ) );
		editorRoot.addEventListener( 'wc-gpd-popout-closed', applyResponsiveScale );
	}

	$( '#wc-gpd-template-form, #post' ).on( 'submit', saveJson );
	$( document ).on( 'click', '#publish, #save-post', saveJson );
	window.addEventListener( 'resize', applyResponsiveScale );

	bindTextPropInputs();
	bindPlaceholderPropInputs();
	bindGraphicPropInputs();
	loadGraphicLibrary();
	loadJson();
	applyResponsiveScale();
}( jQuery ) );
