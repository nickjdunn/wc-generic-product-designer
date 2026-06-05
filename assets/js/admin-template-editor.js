/**
 * Admin template editor — mockup photos + outlines.
 */
( function ( $ ) {
	'use strict';

	const canvasEl = document.getElementById( 'wc-gpd-template-canvas' );
	const jsonInput = document.getElementById( 'wc_gpd_template_json' );
	const widthInput = document.getElementById( 'wc_gpd_template_canvas_width' );
	const heightInput = document.getElementById( 'wc_gpd_template_canvas_height' );
	const maxViewsInput = document.getElementById( 'wc_gpd_template_max_views' );
	const viewTabsEl = document.getElementById( 'wc-gpd-template-view-tabs' );
	const outlineToggle = document.getElementById( 'wc_gpd_template_is_outline' );
	const bboxToggle = document.getElementById( 'wc_gpd_template_is_bbox' );
	const strokeColorInput = document.getElementById( 'wc_gpd_template_stroke_color' );
	const strokeWidthInput = document.getElementById( 'wc_gpd_template_stroke_width' );
	const shapePropsFields = document.getElementById( 'wc-gpd-shape-props-fields' );
	const shapePropsHint = document.getElementById( 'wc-gpd-shape-props-hint' );
	const imagePropsPanel = document.getElementById( 'wc-gpd-image-props' );
	const mockupVisibleToggle = document.getElementById( 'wc_gpd_template_mockup_visible' );
	const deleteImageBtn = document.getElementById( 'wc-gpd-template-delete-image' );
	const addImageBtn = document.getElementById( 'wc-gpd-template-add-image' );
	const editorRoot = document.getElementById( 'wc-gpd-template-editor-root' );
	const popoutBtn = document.getElementById( 'wc-gpd-template-popout' );

	if ( ! canvasEl || typeof fabric === 'undefined' ) {
		return;
	}

	const width = parseInt( widthInput ? widthInput.value : '800', 10 ) || 800;
	const height = parseInt( heightInput ? heightInput.value : '600', 10 ) || 600;
	const maxViews = parseInt( maxViewsInput ? maxViewsInput.value : '1', 10 ) || 1;

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
		return obj && ( obj.type === 'rect' || obj.type === 'circle' || obj.type === 'ellipse' );
	}

	function isMockupImage( obj ) {
		return obj && obj.type === 'image' && obj.wcGpdMockupImage;
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
		view.objects = canvas.getObjects().map( ( obj ) => obj.toObject( [
			'wcGpdUid', 'wcGpdOutlineLayer', 'wcGpdBoundingBox', 'wcGpdLayerType',
			'wcGpdTemplateLayer', 'wcGpdMockupImage', 'wcGpdMockupVisible', 'wcGpdAttachmentId', 'strokeDashArray',
		] ) );
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

	function updateSelectionPanels() {
		const active = canvas.getActiveObject();
		const shapeSelected = isShape( active );
		const imageSelected = isMockupImage( active );

		if ( shapePropsFields ) {
			shapePropsFields.hidden = ! shapeSelected;
		}
		if ( imagePropsPanel ) {
			imagePropsPanel.hidden = ! imageSelected;
		}
		if ( shapePropsHint ) {
			shapePropsHint.hidden = shapeSelected || imageSelected;
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
			} else if ( isShape( obj ) ) {
				canvas.bringToFront( obj );
			}
		} );
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
					view.objects.unshift( img.toObject( [
						'wcGpdUid', 'wcGpdMockupImage', 'wcGpdMockupVisible', 'wcGpdAttachmentId', 'wcGpdLayerType', 'wcGpdTemplateLayer',
					] ) );
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
					obj.wcGpdTemplateLayer = true;
					if ( isMockupImage( obj ) ) {
						obj.set( { selectable: true, evented: true, hasControls: true, hasBorders: true } );
					} else if ( isShape( obj ) ) {
						obj.set( { selectable: true, evented: true, hasControls: true, hasBorders: true } );
					}
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

	function saveJson() {
		persistCanvasToActiveView();
		if ( jsonInput ) {
			jsonInput.value = JSON.stringify( documentData );
		}
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

	function applyResponsiveScale() {
		const col = editorRoot ? editorRoot.querySelector( '.wc-gpd-tpl-canvas-col' ) : null;
		if ( ! col ) {
			return;
		}
		const isPopout = editorRoot && editorRoot.classList.contains( 'wc-gpd-is-popout' );
		const maxW = isPopout ? Math.min( window.innerWidth - 260, 1100 ) : Math.max( 1, col.clientWidth - 16 );
		const scale = Math.min( 1, maxW / width );
		const displayW = Math.max( 1, Math.floor( width * scale ) );
		const displayH = Math.max( 1, Math.floor( height * scale ) );
		canvas.setDimensions( { width, height } );
		canvas.setDimensions( { width: displayW, height: displayH }, { cssOnly: true } );
		canvas.calcOffset();
		canvas.requestRenderAll();
	}

	canvas.on( 'selection:created', updateSelectionPanels );
	canvas.on( 'selection:updated', updateSelectionPanels );
	canvas.on( 'selection:cleared', updateSelectionPanels );
	canvas.on( 'object:modified', sortLayers );

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
		deleteImageBtn.addEventListener( 'click', () => {
			const active = canvas.getActiveObject();
			if ( ! active || ! isMockupImage( active ) ) {
				return;
			}
			canvas.remove( active );
			canvas.discardActiveObject();
			updateSelectionPanels();
			canvas.requestRenderAll();
		} );
	}

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

	$( '.wc-gpd-add-template-rect' ).on( 'click', () => addRect( false ) );
	$( '.wc-gpd-add-template-square' ).on( 'click', () => addRect( true ) );
	$( '.wc-gpd-add-template-circle' ).on( 'click', addCircle );
	$( '#wc-gpd-template-add-view' ).on( 'click', addView );
	$( '#wc-gpd-template-rename-view' ).on( 'click', renameView );
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

	$( '#post' ).on( 'submit', saveJson );
	$( document ).on( 'click', '#publish, #save-post', saveJson );
	window.addEventListener( 'resize', applyResponsiveScale );

	loadJson();
	applyResponsiveScale();
}( jQuery ) );
