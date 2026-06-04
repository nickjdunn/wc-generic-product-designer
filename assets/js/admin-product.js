/**
 * Product edit: template image media uploader.
 */
( function ( $ ) {
	'use strict';

	let frame = null;

	function setPreview( url ) {
		const $preview = $( '.wc_gpd_template_preview' );
		if ( url ) {
			$preview.html(
				`<img src="${ url }" alt="" style="max-width:120px;height:auto;display:block;margin-bottom:8px;" />`
			);
			$( '.wc_gpd_remove_template' ).show();
		} else {
			$preview.empty();
			$( '.wc_gpd_remove_template' ).hide();
		}
	}

	$( document ).on( 'click', '.wc_gpd_upload_template', function ( e ) {
		e.preventDefault();

		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title: 'Select blank template image',
			button: { text: 'Use this image' },
			library: { type: 'image' },
			multiple: false,
		} );

		frame.on( 'select', function () {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#wc_gpd_template_image_id' ).val( attachment.id );
			const thumb =
				attachment.sizes && attachment.sizes.thumbnail
					? attachment.sizes.thumbnail.url
					: attachment.url;
			setPreview( thumb );
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.wc_gpd_remove_template', function ( e ) {
		e.preventDefault();
		$( '#wc_gpd_template_image_id' ).val( '' );
		setPreview( '' );
	} );
} )( jQuery );
