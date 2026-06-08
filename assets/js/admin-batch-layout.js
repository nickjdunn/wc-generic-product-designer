/**
 * Batch layout editor — place production jobs on machine bed canvas.
 */
( function ( $ ) {
	'use strict';

	const config = window.wcGpdProduction || {};
	const root = document.getElementById( 'wc-gpd-batch-editor' );
	const canvasEl = document.getElementById( 'wc-gpd-batch-canvas' );

	if ( ! root || ! canvasEl || typeof fabric === 'undefined' ) {
		return;
	}

	const batchId = parseInt( root.dataset.batchId, 10 ) || 0;
	const bedW = parseInt( root.dataset.bedWidthPx, 10 ) || 2304;
	const bedH = parseInt( root.dataset.bedHeightPx, 10 ) || 1728;
	let layout = [];
	try {
		layout = JSON.parse( root.dataset.layout || '[]' ) || [];
	} catch ( e ) {
		layout = [];
	}

	const canvas = new fabric.Canvas( 'wc-gpd-batch-canvas', {
		selection: true,
		preserveObjectStacking: true,
	} );
	canvas.setWidth( Math.min( bedW, window.innerWidth - 80 ) );
	canvas.setHeight( Math.min( bedH, 700 ) );

	const scale = Math.min( canvas.getWidth() / bedW, canvas.getHeight() / bedH );
	const jobGroups = {};

	function bedRect() {
		return new fabric.Rect( {
			left: 0,
			top: 0,
			width: bedW * scale,
			height: bedH * scale,
			fill: 'transparent',
			stroke: '#94a3b8',
			strokeDashArray: [ 8, 6 ],
			selectable: false,
			evented: false,
			excludeFromExport: true,
		} );
	}

	canvas.add( bedRect() );

	function jobKey( row ) {
		return row.order_id + '-' + row.item_id;
	}

	function loadJobSvg( row ) {
		return $.post( config.ajaxUrl, {
			action: 'wc_gpd_batch_job_svg',
			nonce: config.nonce,
			order_id: row.order_id,
			item_id: row.item_id,
		} ).then( function ( resp ) {
			if ( ! resp || ! resp.success || ! resp.data || ! resp.data.svg ) {
				return null;
			}
			return resp.data.svg;
		} );
	}

	function placeSvg( row, svgString ) {
		return new Promise( function ( resolve ) {
			fabric.loadSVGFromString( svgString, function ( objects, options ) {
				const group = fabric.util.groupSVGElements( objects, options );
				const s = ( row.scale || 1 ) * scale;
				group.set( {
					left: ( row.x || 0 ) * scale,
					top: ( row.y || 0 ) * scale,
					scaleX: s,
					scaleY: s,
					angle: row.rotation || 0,
					hasControls: true,
					lockScalingFlip: true,
				} );
				group.wcGpdJobKey = jobKey( row );
				group.wcGpdOrderId = row.order_id;
				group.wcGpdItemId = row.item_id;
				canvas.add( group );
				jobGroups[ group.wcGpdJobKey ] = group;
				canvas.requestRenderAll();
				resolve( group );
			} );
		} );
	}

	layout.forEach( function ( row ) {
		loadJobSvg( row ).then( function ( svg ) {
			if ( svg ) {
				placeSvg( row, svg );
			}
		} );
	} );

	function collectLayout() {
		const rows = [];
		Object.keys( jobGroups ).forEach( function ( key ) {
			const group = jobGroups[ key ];
			if ( ! group ) {
				return;
			}
			const s = scale || 1;
			rows.push( {
				order_id: group.wcGpdOrderId,
				item_id: group.wcGpdItemId,
				x: group.left / s,
				y: group.top / s,
				scale: group.scaleX / s,
				rotation: group.angle || 0,
				width: group.width * group.scaleX,
				height: group.height * group.scaleY,
			} );
		} );
		return rows;
	}

	document.getElementById( 'wc-gpd-batch-save' )?.addEventListener( 'click', function () {
		$.post( config.ajaxUrl, {
			action: 'wc_gpd_batch_save_layout',
			nonce: config.nonce,
			batch_id: batchId,
			layout: JSON.stringify( collectLayout() ),
		} ).done( function ( resp ) {
			if ( resp && resp.success ) {
				window.alert( config.i18n?.saved || 'Saved' );
			} else {
				window.alert( config.i18n?.error || 'Error' );
			}
		} );
	} );
}( jQuery ) );
