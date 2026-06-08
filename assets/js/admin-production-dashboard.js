/**
 * Production dashboard bulk actions, batch generation, and proof downloads.
 */
( function ( $ ) {
	'use strict';

	const config = window.wcGpdProduction || {};

	function parseRefs( selector ) {
		return $( selector )
			.filter( ':checked' )
			.map( function () {
				return $( this ).val();
			} )
			.get();
	}

	function submitAdminPost( fields ) {
		const form = $( '<form>', { method: 'post', action: config.adminPostUrl || '/wp-admin/admin-post.php' } );
		Object.keys( fields ).forEach( function ( key ) {
			form.append( $( '<input>', { type: 'hidden', name: key, value: fields[ key ] } ) );
		} );
		$( 'body' ).append( form );
		form.trigger( 'submit' );
	}

	function createBatch( refs ) {
		if ( ! refs.length ) {
			window.alert( config.i18n?.selectJobs || 'Select at least one job.' );
			return;
		}
		if ( ! window.confirm( config.i18n?.confirmBatch || 'Create batch?' ) ) {
			return;
		}
		$.post( config.ajaxUrl, {
			action: 'wc_gpd_production_create_batch',
			nonce: config.nonce,
			job_refs: refs,
		} ).done( function ( resp ) {
			if ( resp && resp.success && resp.data && resp.data.redirect ) {
				window.location.href = resp.data.redirect;
				return;
			}
			window.alert( ( resp && resp.data && resp.data.message ) || config.i18n?.error || 'Error' );
		} ).fail( function () {
			window.alert( config.i18n?.error || 'Error' );
		} );
	}

	$( '#wc-gpd-select-all-jobs' ).on( 'change', function () {
		$( 'input[name="job_refs[]"]' ).prop( 'checked', this.checked );
	} );

	$( '.wc-gpd-mark-ready' ).on( 'click', function () {
		const btn = $( this );
		$.post( config.ajaxUrl, {
			action: 'wc_gpd_production_update_status',
			nonce: config.nonce,
			order_id: btn.data( 'order' ),
			item_id: btn.data( 'item' ),
			status: 'ready',
		} ).done( function () {
			window.location.reload();
		} );
	} );

	$( document ).on( 'click', '.wc-gpd-download-proof', function () {
		const btn = $( this );
		const row = btn.closest( 'tr' );
		const templateId = row.find( '.wc-gpd-proof-template-select' ).val() || config.defaultProofTemplateId || '';
		submitAdminPost( {
			action: config.downloadProof,
			order_id: btn.data( 'order' ),
			item_id: btn.data( 'item' ),
			template_id: templateId,
			format: btn.data( 'format' ) || 'pdf',
			_wpnonce: btn.data( 'nonce' ),
		} );
	} );

	$( document ).on( 'click', '.wc-gpd-download-batch', function () {
		const btn = $( this );
		submitAdminPost( {
			action: config.downloadBatch,
			batch_id: btn.data( 'batchId' ),
			_wpnonce: btn.data( 'nonce' ),
		} );
	} );

	$( '#wc-gpd-production-jobs-form' ).on( 'submit', function ( event ) {
		event.preventDefault();
		const action = $( 'select[name="bulk_action"]' ).val();
		if ( ! action ) {
			return;
		}
		const refs = parseRefs( 'input[name="job_refs[]"]' );
		if ( ! refs.length ) {
			window.alert( config.i18n?.selectJobs || 'Select at least one job.' );
			return;
		}
		if ( action === 'create_batch' ) {
			createBatch( refs );
			return;
		}
		$.post( config.ajaxUrl, {
			action: 'wc_gpd_production_bulk_status',
			nonce: config.nonce,
			bulk_action: action,
			job_refs: refs,
		} ).done( function () {
			window.location.reload();
		} );
	} );

	// Batches tab — ready job selection.
	$( '#wc-gpd-select-all-ready, #wc-gpd-select-all-ready-cb' ).on( 'change click', function ( event ) {
		if ( event.type === 'click' && this.id === 'wc-gpd-select-all-ready' ) {
			const checked = $( '.wc-gpd-ready-job-cb' ).length > 0 && $( '.wc-gpd-ready-job-cb:checked' ).length < $( '.wc-gpd-ready-job-cb' ).length;
			$( '.wc-gpd-ready-job-cb, #wc-gpd-select-all-ready-cb' ).prop( 'checked', checked );
			event.preventDefault();
			return;
		}
		$( '.wc-gpd-ready-job-cb' ).prop( 'checked', this.checked );
	} );

	$( '#wc-gpd-generate-batch-all' ).on( 'click', function () {
		const refs = ( config.readyJobs || [] ).map( function ( job ) {
			return job.order_id + ':' + job.item_id;
		} );
		if ( ! refs.length ) {
			window.alert( config.i18n?.noReadyJobs || 'No ready jobs.' );
			return;
		}
		createBatch( refs );
	} );

	$( '#wc-gpd-generate-batch-selected' ).on( 'click', function () {
		createBatch( parseRefs( '.wc-gpd-ready-job-cb' ) );
	} );

	$( document ).on( 'click', '.wc-gpd-delete-batch', function () {
		const btn = $( this );
		if ( ! window.confirm( config.i18n?.confirmDeleteBatch || 'Delete this batch?' ) ) {
			return;
		}
		$.post( config.ajaxUrl, {
			action: 'wc_gpd_batch_delete',
			nonce: config.nonce,
			batch_id: btn.data( 'batchId' ),
		} ).done( function ( resp ) {
			if ( resp && resp.success ) {
				const redirect = btn.data( 'redirect' ) || ( resp.data && resp.data.redirect );
				if ( redirect ) {
					window.location.href = redirect;
					return;
				}
				window.location.reload();
				return;
			}
			window.alert( ( resp && resp.data && resp.data.message ) || config.i18n?.error || 'Error' );
		} ).fail( function () {
			window.alert( config.i18n?.error || 'Error' );
		} );
	} );
}( jQuery ) );
