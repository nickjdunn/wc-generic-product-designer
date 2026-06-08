/**
 * Visual proof header designer with autofill token palette.
 */
( function ( $ ) {
	'use strict';

	const cfg = window.wcGpdProofHeader || {};
	const canvasEl = document.getElementById( 'wc-gpd-proof-header-canvas' );
	const form = document.getElementById( 'wc-gpd-proof-header-form' );

	if ( ! canvasEl || typeof fabric === 'undefined' || ! form ) {
		return;
	}

	const design = cfg.design || { width: 800, height: 120, background: '#1e293b', elements: [] };
	const width = design.width || 800;
	const height = design.height || 120;

	const canvas = new fabric.Canvas( 'wc-gpd-proof-header-canvas', {
		selection: true,
		preserveObjectStacking: true,
	} );
	canvas.setWidth( width );
	canvas.setHeight( height );

	let showSample = true;

	function sampleValue( token ) {
		if ( ! showSample || ! cfg.sample ) {
			return '{' + token + '}';
		}
		return cfg.sample[ token ] || '{' + token + '}';
	}

	function replaceTokens( text ) {
		let out = text;
		Object.keys( cfg.sample || {} ).forEach( function ( key ) {
			const val = showSample ? cfg.sample[ key ] : '{' + key + '}';
			out = out.split( '{' + key + '}' ).join( val );
		} );
		return out;
	}

	function backgroundRect() {
		return new fabric.Rect( {
			left: 0,
			top: 0,
			width: width,
			height: height,
			fill: design.background || '#1e293b',
			selectable: false,
			evented: false,
			excludeFromExport: true,
			wcGpdBg: true,
		} );
	}

	canvas.add( backgroundRect() );

	function addTextElement( element ) {
		const token = element.token || '';
		const rawText = element.text || ( token ? '{' + token + '}' : 'Text' );
		const text = new fabric.IText( replaceTokens( rawText ), {
			left: element.left || 20,
			top: element.top || 20,
			fontSize: element.fontSize || 14,
			fill: element.fill || '#ffffff',
			fontFamily: element.fontFamily || 'Arial, sans-serif',
			fontWeight: element.fontWeight || '400',
		} );
		text.wcGpdType = 'text';
		text.wcGpdToken = token;
		text.wcGpdRawText = rawText;
		canvas.add( text );
		canvas.requestRenderAll();
	}

	function addLogoElement( element ) {
		if ( ! cfg.logoUrl ) {
			return;
		}
		fabric.Image.fromURL( cfg.logoUrl, function ( img ) {
			img.set( {
				left: element.left || 680,
				top: element.top || 20,
				scaleX: ( element.width || 100 ) / img.width,
				scaleY: ( element.height || 80 ) / img.height,
			} );
			img.wcGpdType = 'logo';
			img.wcGpdLogoWidth = element.width || 100;
			img.wcGpdLogoHeight = element.height || 80;
			canvas.add( img );
			canvas.requestRenderAll();
		}, { crossOrigin: 'anonymous' } );
	}

	( design.elements || [] ).forEach( function ( element ) {
		if ( element.type === 'logo' ) {
			addLogoElement( element );
		} else if ( element.type === 'text' ) {
			addTextElement( element );
		}
	} );

	function refreshPreviewText() {
		canvas.getObjects().forEach( function ( obj ) {
			if ( obj.wcGpdType === 'text' && obj.wcGpdRawText ) {
				obj.set( 'text', replaceTokens( obj.wcGpdRawText ) );
			}
		} );
		canvas.requestRenderAll();
	}

	$( '#wc-gpd-proof-preview-sample' ).on( 'change', function () {
		showSample = this.checked;
		refreshPreviewText();
	} );

	$( '.wc-gpd-proof-add-token' ).on( 'click', function () {
		const token = $( this ).data( 'token' );
		const defaultText = ( cfg.defaultText && cfg.defaultText[ token ] ) || '{' + token + '}';
		addTextElement( {
			type: 'text',
			token: token,
			text: defaultText,
			left: 20,
			top: 20 + canvas.getObjects().length * 4,
			fontSize: 14,
			fill: '#ffffff',
			fontFamily: 'Arial, sans-serif',
		} );
	} );

	$( '#wc-gpd-proof-add-logo' ).on( 'click', function () {
		if ( ! cfg.logoUrl ) {
			window.alert( 'Select a logo first.' );
			return;
		}
		addLogoElement( { type: 'logo', left: 680, top: 20, width: 100, height: 80 } );
	} );

	function serializeDesign() {
		const elements = [];
		canvas.getObjects().forEach( function ( obj ) {
			if ( obj.wcGpdBg ) {
				return;
			}
			if ( obj.wcGpdType === 'logo' ) {
				elements.push( {
					type: 'logo',
					left: obj.left,
					top: obj.top,
					width: obj.wcGpdLogoWidth || ( obj.width * obj.scaleX ),
					height: obj.wcGpdLogoHeight || ( obj.height * obj.scaleY ),
				} );
				return;
			}
			if ( obj.wcGpdType === 'text' || obj.type === 'i-text' || obj.type === 'text' ) {
				elements.push( {
					type: 'text',
					token: obj.wcGpdToken || '',
					text: obj.wcGpdRawText || obj.text || '',
					left: obj.left,
					top: obj.top,
					fontSize: obj.fontSize,
					fill: obj.fill,
					fontFamily: obj.fontFamily,
					fontWeight: obj.fontWeight || '400',
				} );
			}
		} );
		const bg = canvas.getObjects().find( function ( o ) { return o.wcGpdBg; } );
		return {
			width: width,
			height: height,
			background: bg ? bg.fill : ( design.background || '#1e293b' ),
			elements: elements,
		};
	}

	form.addEventListener( 'submit', function () {
		document.getElementById( 'wc-gpd-proof-design-json' ).value = JSON.stringify( serializeDesign() );
	} );
}( jQuery ) );
