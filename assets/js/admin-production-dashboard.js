/**
 * Production dashboard bulk actions and quick status updates.
 */
( function ( $ ) {
	'use strict';

	const config = window.wcGpdProduction || {};

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

	$( '#wc-gpd-production-jobs-form' ).on( 'submit', function ( event ) {
		const action = $( 'select[name="bulk_action"]' ).val();
		if ( action === 'create_batch' ) {
			const checked = $( 'input[name="job_refs[]"]:checked' ).length;
			if ( ! checked ) {
				event.preventDefault();
				window.alert( 'Select at least one job.' );
				return;
			}
			if ( ! window.confirm( config.i18n?.confirmBatch || 'Create batch?' ) ) {
				event.preventDefault();
			}
		}
	} );
}( jQuery ) );
