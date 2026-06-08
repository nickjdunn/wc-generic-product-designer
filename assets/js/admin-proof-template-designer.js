/**
 * Proof template designer — multiple templates with header, mockup, layers.
 */
( function ( $ ) {
	'use strict';

	const cfg = window.wcGpdProofTemplate || {};
	const canvasEl = document.getElementById( 'wc-gpd-proof-header-canvas' );

	if ( ! canvasEl || typeof fabric === 'undefined' ) {
		return;
	}

	let templates = cfg.templates || [];
	let activeId = cfg.defaultId || ( templates[ 0 ] && templates[ 0 ].id );
	let activeTemplate = cfg.activeTemplate || {};
	let showSample = true;
	let canvas = null;
	let logoUrl = cfg.logoUrl || '';
	let mockupUrl = cfg.mockupUrl || '';

	function defaultTemplate() {
		return {
			id: 'proof-' + Date.now(),
			name: 'New proof template',
			header_design: { width: 800, height: 120, background: '#1e293b', elements: [] },
			logo_id: 0,
			mockup_attachment_id: 0,
			canvas_width: 800,
			design_height: 600,
			export_options: {
				include_background: true,
				include_text: true,
				include_outlines: false,
				include_shapes: true,
			},
			pdf_dpi: 150,
		};
	}

	function replaceTokens( text ) {
		let out = text;
		Object.keys( cfg.sample || {} ).forEach( function ( key ) {
			const val = showSample ? cfg.sample[ key ] : '{' + key + '}';
			out = out.split( '{' + key + '}' ).join( val );
		} );
		return out;
	}

	function initCanvas() {
		if ( canvas ) {
			canvas.dispose();
		}
		const design = activeTemplate.header_design || { width: 800, height: 120, background: '#1e293b', elements: [] };
		const width = design.width || 800;
		const height = design.height || 120;
		canvas = new fabric.Canvas( 'wc-gpd-proof-header-canvas', { selection: true, preserveObjectStacking: true } );
		canvas.setWidth( width );
		canvas.setHeight( height );

		const bg = new fabric.Rect( {
			left: 0, top: 0, width: width, height: height,
			fill: design.background || '#1e293b',
			selectable: false, evented: false, excludeFromExport: true, wcGpdBg: true,
		} );
		canvas.add( bg );

		( design.elements || [] ).forEach( function ( element ) {
			if ( element.type === 'logo' ) {
				addLogoElement( element );
			} else if ( element.type === 'text' ) {
				addTextElement( element );
			}
		} );
	}

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
		if ( ! logoUrl ) {
			return;
		}
		fabric.Image.fromURL( logoUrl, function ( img ) {
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

	function serializeHeaderDesign() {
		const elements = [];
		const bg = canvas.getObjects().find( function ( o ) { return o.wcGpdBg; } );
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
			if ( obj.wcGpdType === 'text' || obj.type === 'i-text' ) {
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
		return {
			width: canvas.getWidth(),
			height: canvas.getHeight(),
			background: bg ? bg.fill : '#1e293b',
			elements: elements,
		};
	}

	function collectTemplateFromUI() {
		return {
			id: activeTemplate.id || activeId,
			name: $( '#wc-gpd-proof-template-name' ).val() || activeTemplate.name || 'Proof template',
			header_design: serializeHeaderDesign(),
			logo_id: parseInt( $( '#wc-gpd-proof-logo-id' ).val(), 10 ) || 0,
			mockup_attachment_id: parseInt( $( '#wc-gpd-proof-mockup-id' ).val(), 10 ) || 0,
			canvas_width: 800,
			design_height: 600,
			export_options: {
				include_background: $( '#wc-gpd-proof-inc-background' ).prop( 'checked' ),
				include_text: $( '#wc-gpd-proof-inc-text' ).prop( 'checked' ),
				include_outlines: $( '#wc-gpd-proof-inc-outlines' ).prop( 'checked' ),
				include_shapes: $( '#wc-gpd-proof-inc-shapes' ).prop( 'checked' ),
			},
			pdf_dpi: parseInt( $( '#wc-gpd-proof-pdf-dpi' ).val(), 10 ) || 150,
		};
	}

	function applyTemplateToUI( tpl ) {
		activeTemplate = tpl;
		activeId = tpl.id;
		$( '#wc-gpd-proof-template-name' ).val( tpl.name || '' );
		$( '#wc-gpd-proof-logo-id' ).val( tpl.logo_id || 0 );
		$( '#wc-gpd-proof-mockup-id' ).val( tpl.mockup_attachment_id || 0 );
		const opts = tpl.export_options || {};
		$( '#wc-gpd-proof-inc-background' ).prop( 'checked', !! opts.include_background );
		$( '#wc-gpd-proof-inc-text' ).prop( 'checked', opts.include_text !== false );
		$( '#wc-gpd-proof-inc-outlines' ).prop( 'checked', !! opts.include_outlines );
		$( '#wc-gpd-proof-inc-shapes' ).prop( 'checked', opts.include_shapes !== false );
		$( '#wc-gpd-proof-pdf-dpi' ).val( tpl.pdf_dpi || 150 );
		logoUrl = tpl.logo_id && tpl.logo_url ? tpl.logo_url : logoUrl;
		mockupUrl = tpl.mockup_url || mockupUrl;
		$( '#wc-gpd-proof-logo-preview' ).html( logoUrl ? '<img src="' + logoUrl + '" style="max-height:60px" />' : '' );
		$( '#wc-gpd-proof-mockup-preview' ).html( mockupUrl ? '<img src="' + mockupUrl + '" style="max-height:80px" />' : '' );
		initCanvas();
		renderTemplateList();
	}

	function renderTemplateList() {
		const list = $( '#wc-gpd-proof-template-list' );
		list.empty();
		templates.forEach( function ( tpl ) {
			const li = $( '<li>', {
				text: tpl.name + ( tpl.id === cfg.defaultId ? ' ★' : '' ),
				'data-id': tpl.id,
				class: tpl.id === activeId ? 'is-active' : '',
			} );
			list.append( li );
		} );
	}

	function saveTemplate( setDefault ) {
		const payload = collectTemplateFromUI();
		$.post( cfg.ajaxUrl, {
			action: 'wc_gpd_proof_templates_save',
			nonce: cfg.nonce,
			template: JSON.stringify( payload ),
			set_default: setDefault ? 1 : 0,
		} ).done( function ( resp ) {
			if ( resp && resp.success && resp.data ) {
				templates = resp.data.templates || templates;
				if ( setDefault && resp.data.template ) {
					cfg.defaultId = resp.data.template.id;
				}
				activeTemplate = resp.data.template || payload;
				activeId = activeTemplate.id;
				$( '#wc-gpd-proof-template-status' ).text( 'Saved.' );
				renderTemplateList();
			}
		} );
	}

	renderTemplateList();
	if ( activeTemplate && activeTemplate.id ) {
		applyTemplateToUI( activeTemplate );
	} else if ( templates[ 0 ] ) {
		applyTemplateToUI( templates[ 0 ] );
	} else {
		applyTemplateToUI( defaultTemplate() );
	}

	$( '#wc-gpd-proof-template-list' ).on( 'click', 'li', function () {
		const id = $( this ).data( 'id' );
		const tpl = templates.find( function ( t ) { return t.id === id; } );
		if ( tpl ) {
			applyTemplateToUI( tpl );
		}
	} );

	$( '#wc-gpd-proof-template-add' ).on( 'click', function () {
		const tpl = defaultTemplate();
		templates.push( tpl );
		applyTemplateToUI( tpl );
	} );

	$( '#wc-gpd-proof-template-duplicate' ).on( 'click', function () {
		$.post( cfg.ajaxUrl, {
			action: 'wc_gpd_proof_templates_duplicate',
			nonce: cfg.nonce,
			template_id: activeId,
		} ).done( function ( resp ) {
			if ( resp && resp.success && resp.data ) {
				templates = resp.data.templates || templates;
				applyTemplateToUI( resp.data.template );
			}
		} );
	} );

	$( '#wc-gpd-proof-template-delete' ).on( 'click', function () {
		if ( ! window.confirm( 'Delete this template?' ) ) {
			return;
		}
		$.post( cfg.ajaxUrl, {
			action: 'wc_gpd_proof_templates_delete',
			nonce: cfg.nonce,
			template_id: activeId,
		} ).done( function ( resp ) {
			if ( resp && resp.success && resp.data ) {
				templates = resp.data.templates || [];
				cfg.defaultId = resp.data.defaultId;
				if ( templates[ 0 ] ) {
					applyTemplateToUI( templates[ 0 ] );
				}
			}
		} );
	} );

	$( '#wc-gpd-proof-template-save' ).on( 'click', function () {
		saveTemplate( false );
	} );

	$( '#wc-gpd-proof-set-default' ).on( 'click', function () {
		saveTemplate( true );
	} );

	$( '#wc-gpd-proof-preview-sample' ).on( 'change', function () {
		showSample = this.checked;
		canvas.getObjects().forEach( function ( obj ) {
			if ( obj.wcGpdType === 'text' && obj.wcGpdRawText ) {
				obj.set( 'text', replaceTokens( obj.wcGpdRawText ) );
			}
		} );
		canvas.requestRenderAll();
	} );

	$( '.wc-gpd-proof-add-token' ).on( 'click', function () {
		const token = $( this ).data( 'token' );
		const defaultText = ( cfg.defaultText && cfg.defaultText[ token ] ) || '{' + token + '}';
		addTextElement( { type: 'text', token: token, text: defaultText, left: 20, top: 20, fontSize: 14, fill: '#ffffff' } );
	} );

	$( '#wc-gpd-proof-add-logo' ).on( 'click', function () {
		if ( ! logoUrl ) {
			window.alert( 'Select a logo first.' );
			return;
		}
		addLogoElement( { type: 'logo', left: 680, top: 20, width: 100, height: 80 } );
	} );

	function pickMedia( title, callback ) {
		const frame = wp.media( { title: title, button: { text: 'Use' }, multiple: false } );
		frame.on( 'select', function () {
			const att = frame.state().get( 'selection' ).first().toJSON();
			callback( att );
		} );
		frame.open();
	}

	$( '#wc-gpd-proof-logo-pick' ).on( 'click', function ( e ) {
		e.preventDefault();
		pickMedia( 'Logo', function ( att ) {
			$( '#wc-gpd-proof-logo-id' ).val( att.id );
			logoUrl = att.url;
			$( '#wc-gpd-proof-logo-preview' ).html( '<img src="' + att.url + '" style="max-height:60px" />' );
		} );
	} );

	$( '#wc-gpd-proof-mockup-pick' ).on( 'click', function ( e ) {
		e.preventDefault();
		pickMedia( 'Proof mockup', function ( att ) {
			$( '#wc-gpd-proof-mockup-id' ).val( att.id );
			mockupUrl = att.url;
			$( '#wc-gpd-proof-mockup-preview' ).html( '<img src="' + att.url + '" style="max-height:80px" />' );
		} );
	} );
}( jQuery ) );
