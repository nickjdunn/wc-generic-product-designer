/**
 * Batch layout editor — place jobs on bed, export options, auto-save.
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
	let bedW = parseInt( root.dataset.bedWidthPx, 10 ) || 2304;
	let bedH = parseInt( root.dataset.bedHeightPx, 10 ) || 1728;
	let layout = [];
	let exportOptions = {};
	let bedConfig = {};
	let presets = config.exportPresets || [];
	let saveTimer = null;
	let bedRectObj = null;

	try {
		layout = JSON.parse( root.dataset.layout || '[]' ) || [];
		exportOptions = JSON.parse( root.dataset.exportOptions || '{}' ) || {};
		bedConfig = JSON.parse( root.dataset.bed || '{}' ) || {};
	} catch ( e ) {
		layout = [];
	}

	const canvas = new fabric.Canvas( 'wc-gpd-batch-canvas', {
		selection: true,
		preserveObjectStacking: true,
	} );

	const jobGroups = {};

	function updateCanvasSize() {
		canvas.setWidth( Math.min( bedW, window.innerWidth - 520 ) );
		canvas.setHeight( Math.min( bedH, 700 ) );
		const scale = Math.min( canvas.getWidth() / bedW, canvas.getHeight() / bedH );
		if ( bedRectObj ) {
			canvas.remove( bedRectObj );
		}
		bedRectObj = new fabric.Rect( {
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
		canvas.add( bedRectObj );
		canvas.sendToBack( bedRectObj );
		canvas.requestRenderAll();
		return scale;
	}

	let scale = updateCanvasSize();

	function jobKey( row ) {
		return row.order_id + '-' + row.item_id;
	}

	function loadJobSvg( row ) {
		return $.post( config.ajaxUrl, {
			action: 'wc_gpd_batch_job_svg',
			nonce: config.nonce,
			batch_id: batchId,
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
		const s = scale || 1;
		Object.keys( jobGroups ).forEach( function ( key ) {
			const group = jobGroups[ key ];
			if ( ! group ) {
				return;
			}
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

	function readExportOptionsFromUI() {
		return {
			include_background: $( '#wc-gpd-exp-background' ).prop( 'checked' ),
			include_text: $( '#wc-gpd-exp-text' ).prop( 'checked' ),
			include_outlines: $( '#wc-gpd-exp-outlines' ).prop( 'checked' ),
			include_shapes: $( '#wc-gpd-exp-shapes' ).prop( 'checked' ),
			rasterize: false,
			outline_color: $( '#wc-gpd-exp-outline-color' ).val() || '#ff0000',
			outline_width: parseFloat( $( '#wc-gpd-exp-outline-width' ).val() ) || 0.25,
			preset: 'production',
		};
	}

	function readBedFromUI() {
		return {
			width: parseFloat( $( '#wc-gpd-bed-width' ).val() ) || 24,
			height: parseFloat( $( '#wc-gpd-bed-height' ).val() ) || 18,
			unit: $( '#wc-gpd-bed-unit' ).val() || 'in',
			dpi: parseInt( $( '#wc-gpd-bed-dpi' ).val(), 10 ) || 96,
		};
	}

	function applyExportOptionsToUI( opts ) {
		opts = opts || {};
		$( '#wc-gpd-exp-background' ).prop( 'checked', !! opts.include_background );
		$( '#wc-gpd-exp-text' ).prop( 'checked', opts.include_text !== false );
		$( '#wc-gpd-exp-outlines' ).prop( 'checked', !! opts.include_outlines );
		$( '#wc-gpd-exp-shapes' ).prop( 'checked', opts.include_shapes !== false );
		$( '#wc-gpd-exp-outline-color' ).val( opts.outline_color || '#ff0000' );
		$( '#wc-gpd-exp-outline-width' ).val( opts.outline_width || 0.25 );
	}

	function applyBedToUI( bed ) {
		bed = bed || {};
		$( '#wc-gpd-bed-width' ).val( bed.width || 24 );
		$( '#wc-gpd-bed-height' ).val( bed.height || 18 );
		$( '#wc-gpd-bed-unit' ).val( bed.unit || 'in' );
		$( '#wc-gpd-bed-dpi' ).val( bed.dpi || 96 );
	}

	function applyPresetToUI( preset ) {
		if ( ! preset ) {
			return;
		}
		applyExportOptionsToUI( {
			include_background: preset.include_background,
			include_text: preset.include_text,
			include_outlines: preset.include_outlines,
			include_shapes: preset.include_shapes,
			outline_color: preset.outline_color,
			outline_width: preset.outline_width,
		} );
		applyBedToUI( {
			width: preset.bed_width,
			height: preset.bed_height,
			unit: preset.bed_unit,
			dpi: preset.dpi,
		} );
		$( '#wc-gpd-batch-preset' ).val( preset.id );
	}

	function presetFromUI( id, name ) {
		const bed = readBedFromUI();
		const opts = readExportOptionsFromUI();
		return {
			id: id || 'preset-' + Date.now(),
			name: name || window.prompt( config.i18n?.presetName || 'Preset name' ) || 'Preset',
			type: 'production',
			include_background: opts.include_background,
			include_text: opts.include_text,
			include_outlines: opts.include_outlines,
			include_shapes: opts.include_shapes,
			rasterize: false,
			outline_color: opts.outline_color,
			outline_width: opts.outline_width,
			bed_width: bed.width,
			bed_height: bed.height,
			bed_unit: bed.unit,
			dpi: bed.dpi,
		};
	}

	function setSaveStatus( text ) {
		$( '#wc-gpd-batch-save-status' ).text( text || '' );
	}

	function saveBatch( manual ) {
		const opts = readExportOptionsFromUI();
		const bed = readBedFromUI();
		setSaveStatus( config.i18n?.saving || 'Saving…' );
		return $.post( config.ajaxUrl, {
			action: 'wc_gpd_batch_save_layout',
			nonce: config.nonce,
			batch_id: batchId,
			layout: JSON.stringify( collectLayout() ),
			bed: JSON.stringify( bed ),
			export_options: JSON.stringify( opts ),
		} ).done( function ( resp ) {
			if ( resp && resp.success ) {
				exportOptions = opts;
				bedConfig = bed;
				const saved = resp.data && resp.data.saved_at ? resp.data.saved_at : '';
				setSaveStatus( ( config.i18n?.saved || 'Saved' ) + ( saved ? ' ' + saved : '' ) );
			} else {
				setSaveStatus( config.i18n?.error || 'Error' );
				if ( manual ) {
					window.alert( config.i18n?.error || 'Error' );
				}
			}
		} ).fail( function () {
			setSaveStatus( config.i18n?.error || 'Error' );
		} );
	}

	function scheduleAutoSave() {
		if ( saveTimer ) {
			clearTimeout( saveTimer );
		}
		saveTimer = setTimeout( function () {
			saveBatch( false );
		}, 2000 );
	}

	function reloadAllJobs() {
		Object.keys( jobGroups ).forEach( function ( key ) {
			const group = jobGroups[ key ];
			if ( group ) {
				canvas.remove( group );
			}
			delete jobGroups[ key ];
		} );
		const currentLayout = collectLayout();
		if ( ! currentLayout.length ) {
			currentLayout.push.apply( currentLayout, layout );
		}
		currentLayout.forEach( function ( row ) {
			loadJobSvg( row ).then( function ( svg ) {
				if ( svg ) {
					placeSvg( row, svg );
				}
			} );
		} );
	}

	function removeJobFromCanvas( orderId, itemId ) {
		const key = orderId + '-' + itemId;
		const group = jobGroups[ key ];
		if ( group ) {
			canvas.remove( group );
			delete jobGroups[ key ];
			canvas.requestRenderAll();
		}
	}

	applyExportOptionsToUI( exportOptions );
	applyBedToUI( bedConfig );

	canvas.on( 'object:modified', scheduleAutoSave );
	canvas.on( 'object:moved', scheduleAutoSave );
	canvas.on( 'object:scaled', scheduleAutoSave );
	canvas.on( 'object:rotated', scheduleAutoSave );

	$( '#wc-gpd-batch-export-panel input, #wc-gpd-batch-export-panel select' ).on( 'change', scheduleAutoSave );

	document.getElementById( 'wc-gpd-batch-save' )?.addEventListener( 'click', function () {
		saveBatch( true );
	} );

	$( '#wc-gpd-batch-load-preset' ).on( 'click', function () {
		const id = $( '#wc-gpd-batch-preset' ).val();
		const preset = presets.find( function ( p ) { return p.id === id; } );
		if ( preset ) {
			applyPresetToUI( preset );
			scheduleAutoSave();
			reloadAllJobs();
		}
	} );

	$( '#wc-gpd-batch-save-preset' ).on( 'click', function () {
		const id = $( '#wc-gpd-batch-preset' ).val();
		const existing = presets.find( function ( p ) { return p.id === id; } );
		const preset = presetFromUI( existing ? existing.id : '', existing ? existing.name : '' );
		$.post( config.ajaxUrl, {
			action: 'wc_gpd_export_presets_save',
			nonce: config.nonce,
			preset: JSON.stringify( preset ),
		} ).done( function ( resp ) {
			if ( resp && resp.success && resp.data ) {
				presets = resp.data.presets || presets;
				const sel = $( '#wc-gpd-batch-preset' );
				sel.empty();
				presets.forEach( function ( p ) {
					sel.append( $( '<option>', { value: p.id, text: p.name } ) );
				} );
				sel.val( preset.id );
				window.alert( config.i18n?.presetSaved || 'Preset saved.' );
			}
		} );
	} );

	$( '#wc-gpd-batch-delete-preset' ).on( 'click', function () {
		const id = $( '#wc-gpd-batch-preset' ).val();
		if ( ! id || ! window.confirm( config.i18n?.confirmDeletePreset || 'Delete preset?' ) ) {
			return;
		}
		$.post( config.ajaxUrl, {
			action: 'wc_gpd_export_presets_delete',
			nonce: config.nonce,
			preset_id: id,
		} ).done( function ( resp ) {
			if ( resp && resp.success && resp.data ) {
				presets = resp.data.presets || [];
				const sel = $( '#wc-gpd-batch-preset' );
				sel.empty();
				presets.forEach( function ( p ) {
					sel.append( $( '<option>', { value: p.id, text: p.name } ) );
				} );
				if ( presets[ 0 ] ) {
					applyPresetToUI( presets[ 0 ] );
				}
			}
		} );
	} );

	function submitAdminPost( fields ) {
		const form = $( '<form>', { method: 'post', action: config.adminPostUrl || '/wp-admin/admin-post.php' } );
		Object.keys( fields ).forEach( function ( key ) {
			form.append( $( '<input>', { type: 'hidden', name: key, value: fields[ key ] } ) );
		} );
		$( 'body' ).append( form );
		form.trigger( 'submit' );
	}

	$( '.wc-gpd-download-batch' ).on( 'click', function () {
		const btn = $( this );
		submitAdminPost( {
			action: config.downloadBatch,
			batch_id: btn.data( 'batchId' ),
			_wpnonce: btn.data( 'nonce' ),
		} );
	} );

	$( '#wc-gpd-batch-job-list' ).on( 'click', '.wc-gpd-batch-remove-job', function () {
		const li = $( this ).closest( 'li' );
		const orderId = parseInt( li.data( 'order' ), 10 );
		const itemId = parseInt( li.data( 'item' ), 10 );
		if ( ! orderId || ! itemId ) {
			return;
		}
		if ( ! window.confirm( config.i18n?.confirmRemove || 'Remove this job?' ) ) {
			return;
		}
		$.post( config.ajaxUrl, {
			action: 'wc_gpd_batch_remove_item',
			nonce: config.nonce,
			batch_id: batchId,
			order_id: orderId,
			item_id: itemId,
		} ).done( function ( resp ) {
			if ( resp && resp.success ) {
				removeJobFromCanvas( orderId, itemId );
				li.remove();
				scheduleAutoSave();
				if ( ! $( '#wc-gpd-batch-job-list li' ).length ) {
					window.location.href = config.batchEditorUrl
						? config.batchEditorUrl.replace( 'tab=batch', 'tab=batches' )
						: window.location.href.replace( 'tab=batch', 'tab=batches' );
				}
			} else {
				window.alert( ( resp && resp.data && resp.data.message ) || config.i18n?.error || 'Error' );
			}
		} );
	} );
}( jQuery ) );
