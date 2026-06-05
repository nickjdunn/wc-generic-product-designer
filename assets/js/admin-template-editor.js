/**
 * Admin product template outline editor (Fabric.js).
 */
( function ( $ ) {
	'use strict';

	const canvasEl = document.getElementById( 'wc-gpd-template-canvas' );
	const jsonInput = document.getElementById( 'wc_gpd_template_json' );
	const widthInput = document.getElementById( 'wc_gpd_template_canvas_width' );
	const heightInput = document.getElementById( 'wc_gpd_template_canvas_height' );
	const outlineToggle = document.getElementById( 'wc_gpd_template_is_outline' );

	if ( ! canvasEl || typeof fabric === 'undefined' ) {
		return;
	}

	const width = parseInt( widthInput ? widthInput.value : '800', 10 ) || 800;
	const height = parseInt( heightInput ? heightInput.value : '600', 10 ) || 600;

	const canvas = new fabric.Canvas( 'wc-gpd-template-canvas', {
		selection: true,
		preserveObjectStacking: true,
		width,
		height,
	} );

	function syncOutlineFlag( obj ) {
		if ( ! obj || ! outlineToggle ) {
			return;
		}
		obj.wcGpdOutlineLayer = outlineToggle.checked;
		obj.wcGpdLayerType = outlineToggle.checked ? 'outline' : 'shape';
		obj.wcGpdTemplateLayer = true;
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
		} );
		syncOutlineFlag( rect );
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
		} );
		syncOutlineFlag( circle );
		canvas.add( circle );
		canvas.setActiveObject( circle );
		canvas.requestRenderAll();
	}

	function saveJson() {
		if ( ! jsonInput ) {
			return;
		}
		const objects = canvas.getObjects().map( ( obj ) => obj.toObject( [
			'wcGpdOutlineLayer',
			'wcGpdLayerType',
			'wcGpdTemplateLayer',
		] ) );
		jsonInput.value = JSON.stringify( {
			version: fabric.version,
			objects,
		} );
	}

	function loadJson() {
		if ( ! jsonInput || ! jsonInput.value ) {
			return;
		}

		let data;
		try {
			data = JSON.parse( jsonInput.value );
		} catch ( error ) {
			return;
		}

		if ( ! data.objects || ! data.objects.length ) {
			return;
		}

		fabric.util.enlivenObjects( data.objects, ( objects ) => {
			objects.forEach( ( obj ) => {
				obj.wcGpdTemplateLayer = true;
				canvas.add( obj );
			} );
			canvas.requestRenderAll();
		}, 'fabric' );
	}

	canvas.on( 'selection:created', ( event ) => {
		const target = event.selected ? event.selected[ 0 ] : event.target;
		if ( target && outlineToggle ) {
			outlineToggle.checked = !! target.wcGpdOutlineLayer;
		}
	} );

	canvas.on( 'selection:updated', ( event ) => {
		const target = event.selected ? event.selected[ 0 ] : event.target;
		if ( target && outlineToggle ) {
			outlineToggle.checked = !! target.wcGpdOutlineLayer;
		}
	} );

	if ( outlineToggle ) {
		outlineToggle.addEventListener( 'change', () => {
			const active = canvas.getActiveObject();
			if ( active ) {
				syncOutlineFlag( active );
				canvas.requestRenderAll();
			}
		} );
	}

	$( '.wc-gpd-add-template-rect' ).on( 'click', () => addRect( false ) );
	$( '.wc-gpd-add-template-square' ).on( 'click', () => addRect( true ) );
	$( '.wc-gpd-add-template-circle' ).on( 'click', addCircle );

	$( '#post' ).on( 'submit', saveJson );
	$( document ).on( 'click', '#publish, #save-post', saveJson );

	loadJson();
}( jQuery ) );
