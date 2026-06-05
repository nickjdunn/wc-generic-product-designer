/**
 * Admin multi-view template editor (Fabric.js).
 */
( function ( $ ) {
	'use strict';

	const canvasEl = document.getElementById( 'wc-gpd-template-canvas' );
	const jsonInput = document.getElementById( 'wc_gpd_template_json' );
	const widthInput = document.getElementById( 'wc_gpd_template_canvas_width' );
	const heightInput = document.getElementById( 'wc_gpd_template_canvas_height' );
	const maxViewsInput = document.getElementById( 'wc_gpd_template_max_views' );
	const defaultImageInput = document.getElementById( 'wc_gpd_template_default_image_id' );
	const viewTabsEl = document.getElementById( 'wc-gpd-template-view-tabs' );
	const viewImageInput = document.getElementById( 'wc_gpd_template_view_image_id' );
	const outlineToggle = document.getElementById( 'wc_gpd_template_is_outline' );
	const bboxToggle = document.getElementById( 'wc_gpd_template_is_bbox' );

	if ( ! canvasEl || typeof fabric === 'undefined' ) {
		return;
	}

	const width = parseInt( widthInput ? widthInput.value : '800', 10 ) || 800;
	const height = parseInt( heightInput ? heightInput.value : '600', 10 ) || 600;
	const maxViews = parseInt( maxViewsInput ? maxViewsInput.value : '1', 10 ) || 1;

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

	function ensureDocument() {
		if ( ! documentData.views.length ) {
			documentData = {
				version: 2,
				views: [
					{
						id: 'view_front',
						label: 'Front',
						template_image_id: 0,
						bounding_box_uid: '',
						objects: [],
					},
				],
			};
		}
		if ( ! activeViewId ) {
			activeViewId = documentData.views[ 0 ].id;
		}
	}

	function persistCanvasToActiveView() {
		const view = getActiveView();
		if ( ! view ) {
			return;
		}
		view.objects = canvas.getObjects().map( ( obj ) => obj.toObject( [
			'wcGpdUid',
			'wcGpdOutlineLayer',
			'wcGpdBoundingBox',
			'wcGpdLayerType',
			'wcGpdTemplateLayer',
		] ) );
		view.bounding_box_uid = '';
		view.objects.forEach( ( obj ) => {
			if ( obj.wcGpdBoundingBox && obj.wcGpdUid ) {
				view.bounding_box_uid = obj.wcGpdUid;
			}
		} );
	}

	function syncShapeFlags( obj ) {
		if ( ! obj ) {
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
			if ( ! obj.wcGpdOutlineLayer ) {
				obj.wcGpdOutlineLayer = true;
				obj.wcGpdLayerType = 'outline';
				if ( outlineToggle ) {
					outlineToggle.checked = true;
				}
			}
			canvas.getObjects().forEach( ( other ) => {
				if ( other !== obj ) {
					other.wcGpdBoundingBox = false;
				}
			} );
			obj.wcGpdBoundingBox = true;
			const view = getActiveView();
			if ( view ) {
				view.bounding_box_uid = obj.wcGpdUid;
			}
		} else if ( bboxToggle ) {
			obj.wcGpdBoundingBox = false;
		}
		updateBboxToggleState();
	}

	function updateBboxToggleState() {
		const active = canvas.getActiveObject();
		const shapeSelected = active && ( active.type === 'rect' || active.type === 'circle' || active.type === 'ellipse' );
		if ( bboxToggle ) {
			bboxToggle.disabled = ! shapeSelected || ! ( outlineToggle && outlineToggle.checked );
			if ( active && shapeSelected ) {
				bboxToggle.checked = !! active.wcGpdBoundingBox;
			}
		}
		if ( outlineToggle && active ) {
			outlineToggle.checked = !! active.wcGpdOutlineLayer;
		}
	}

	function clearCanvas() {
		canvas.getObjects().slice().forEach( ( obj ) => canvas.remove( obj ) );
		canvas.discardActiveObject();
		canvas.requestRenderAll();
	}

	function loadBackgroundForView( view ) {
		const imageId = view.template_image_id || parseInt( defaultImageInput ? defaultImageInput.value : '0', 10 ) || 0;
		canvas.backgroundImage = null;
		canvas.backgroundColor = '#f8f8f8';
		if ( ! imageId || ! window.wp || ! wp.media ) {
			canvas.requestRenderAll();
			return;
		}
		wp.media.attachment( imageId ).fetch().then( () => {
			const attachment = wp.media.attachment( imageId );
			const url = attachment.get( 'url' );
			if ( ! url ) {
				canvas.requestRenderAll();
				return;
			}
			fabric.Image.fromURL( url, ( img ) => {
				if ( ! img ) {
					canvas.requestRenderAll();
					return;
				}
				const scaleX = width / img.width;
				const scaleY = height / img.height;
				const scale = Math.max( scaleX, scaleY );
				img.set( {
					originX: 'center',
					originY: 'center',
					left: width / 2,
					top: height / 2,
					scaleX: scale,
					scaleY: scale,
					selectable: false,
					evented: false,
				} );
				canvas.backgroundImage = img;
				canvas.requestRenderAll();
			}, { crossOrigin: 'anonymous' } );
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
		if ( viewImageInput ) {
			viewImageInput.value = String( view.template_image_id || 0 );
		}
		loadBackgroundForView( view );
		if ( ! view.objects || ! view.objects.length ) {
			renderTabs();
			updateBboxToggleState();
			return;
		}
		fabric.util.enlivenObjects( view.objects, ( objects ) => {
			objects.forEach( ( obj ) => {
				obj.wcGpdTemplateLayer = true;
				canvas.add( obj );
			} );
			canvas.requestRenderAll();
			renderTabs();
			updateBboxToggleState();
		}, 'fabric' );
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
			btn.setAttribute( 'role', 'tab' );
			btn.setAttribute( 'aria-selected', view.id === activeViewId ? 'true' : 'false' );
			btn.addEventListener( 'click', () => loadView( view.id ) );
			viewTabsEl.appendChild( btn );
		} );
	}

	function addRect( square ) {
		const size = square ? 120 : 180;
		const rect = new fabric.Rect( {
			left: width / 2,
			top: height / 2,
			originX: 'center',
			originY: 'center',
			width: size,
			height: square ? size : 90,
			fill: 'transparent',
			stroke: '#111111',
			strokeWidth: 2,
			wcGpdUid: uid(),
		} );
		syncShapeFlags( rect );
		canvas.add( rect );
		canvas.setActiveObject( rect );
		canvas.requestRenderAll();
	}

	function addCircle() {
		const circle = new fabric.Circle( {
			left: width / 2,
			top: height / 2,
			originX: 'center',
			originY: 'center',
			radius: 60,
			fill: 'transparent',
			stroke: '#111111',
			strokeWidth: 2,
			wcGpdUid: uid(),
		} );
		syncShapeFlags( circle );
		canvas.add( circle );
		canvas.setActiveObject( circle );
		canvas.requestRenderAll();
	}

	function addView() {
		if ( documentData.views.length >= getMaxViews() ) {
			window.alert( `Maximum of ${ getMaxViews() } design areas allowed.` );
			return;
		}
		persistCanvasToActiveView();
		const index = documentData.views.length + 1;
		const presets = [ 'Front', 'Back', 'Left sleeve', 'Right sleeve', 'Inside', 'Custom' ];
		const label = presets[ index - 1 ] || `Area ${ index }`;
		const id = `view_${ index }`;
		documentData.views.push( {
			id,
			label,
			template_image_id: 0,
			bounding_box_uid: '',
			objects: [],
		} );
		loadView( id );
	}

	function renameView() {
		const view = getActiveView();
		if ( ! view ) {
			return;
		}
		const next = window.prompt( 'Design area name', view.label || view.id );
		if ( ! next ) {
			return;
		}
		view.label = next.trim();
		renderTabs();
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
		} catch ( error ) {
			ensureDocument();
		}
		if ( ! documentData.views || ! documentData.views.length ) {
			ensureDocument();
		}
		documentData.views = documentData.views.slice( 0, getMaxViews() );
		loadView( documentData.views[ 0 ].id );
	}

	canvas.on( 'selection:created', updateBboxToggleState );
	canvas.on( 'selection:updated', updateBboxToggleState );
	canvas.on( 'selection:cleared', updateBboxToggleState );

	if ( outlineToggle ) {
		outlineToggle.addEventListener( 'change', () => {
			const active = canvas.getActiveObject();
			if ( ! active ) {
				updateBboxToggleState();
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
			canvas.requestRenderAll();
			updateBboxToggleState();
		} );
	}

	if ( bboxToggle ) {
		bboxToggle.addEventListener( 'change', () => {
			const active = canvas.getActiveObject();
			if ( ! active ) {
				return;
			}
			if ( bboxToggle.checked ) {
				syncShapeFlags( active );
			} else {
				active.wcGpdBoundingBox = false;
				const view = getActiveView();
				if ( view ) {
					view.bounding_box_uid = '';
				}
			}
			canvas.requestRenderAll();
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

	if ( window.wp && wp.media ) {
		$( '#wc-gpd-template-view-image-select' ).on( 'click', ( event ) => {
			event.preventDefault();
			const frame = wp.media( {
				title: 'Select area background',
				button: { text: 'Use image' },
				multiple: false,
			} );
			frame.on( 'select', () => {
				const attachment = frame.state().get( 'selection' ).first().toJSON();
				const view = getActiveView();
				if ( view ) {
					view.template_image_id = attachment.id;
					if ( viewImageInput ) {
						viewImageInput.value = String( attachment.id );
					}
					loadBackgroundForView( view );
				}
			} );
			frame.open();
		} );
	}

	$( '#wc-gpd-template-view-image-clear' ).on( 'click', () => {
		const view = getActiveView();
		if ( view ) {
			view.template_image_id = 0;
			if ( viewImageInput ) {
				viewImageInput.value = '0';
			}
			loadBackgroundForView( view );
		}
	} );

	$( '#post' ).on( 'submit', saveJson );
	$( document ).on( 'click', '#publish, #save-post', saveJson );

	loadJson();
}( jQuery ) );
