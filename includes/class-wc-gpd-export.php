<?php
/**
 * Build downloadable design files with configurable layers.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Design export builder.
 */
class WC_GPD_Export {

	/**
	 * Build export file for an order line item.
	 *
	 * @param WC_Order_Item_Product $item    Line item.
	 * @param array                 $options Export options.
	 * @return array{content:string,filename:string,mime:string}|WP_Error
	 */
	public static function build_for_order_item( $item, array $options = array() ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return new WP_Error( 'wc_gpd_invalid_item', __( 'Invalid order line item.', 'wc-generic-product-designer' ) );
		}

		$product_id = $item->get_product_id();
		if ( ! $product_id ) {
			return new WP_Error( 'wc_gpd_missing_product', __( 'Product not found for this line item.', 'wc-generic-product-designer' ) );
		}

		$settings         = WC_GPD_Product_Meta::get_settings( $product_id );
		$product_settings = ! empty( $settings['product_settings'] ) && is_array( $settings['product_settings'] )
			? $settings['product_settings']
			: WC_GPD_Product_Settings::get( $product_id );
		$options          = self::normalize_options( $options );

		$design_svg  = WC_GPD_SVG_Sanitizer::sanitize( $item->get_meta( WC_GPD_Product_Meta::ORDER_META_DESIGN_SVG, true ) );
		$design_json = $item->get_meta( WC_GPD_Product_Meta::ORDER_META_DESIGN_JSON, true );
		$template_json = get_post_meta( $product_id, WC_GPD_Product_Meta::META_TEMPLATE_JSON, true );

		$document = self::build_svg_document(
			$settings,
			$design_svg,
			$design_json,
			$template_json,
			$options,
			$product_settings
		);

		if ( ! $document ) {
			return new WP_Error( 'wc_gpd_empty_export', __( 'Nothing to export with the selected options.', 'wc-generic-product-designer' ) );
		}

		if ( ! empty( $options['rasterize'] ) ) {
			$png = self::rasterize_svg( $document, ! empty( $options['transparent_raster'] ) );
			if ( is_wp_error( $png ) ) {
				return $png;
			}

			return array(
				'content'  => $png,
				'filename' => self::build_filename( $item, 'png', $options ),
				'mime'     => 'image/png',
			);
		}

		return array(
			'content'  => $document,
			'filename' => self::build_filename( $item, 'svg', $options ),
			'mime'     => 'image/svg+xml',
		);
	}

	/**
	 * Merge options with plugin defaults.
	 *
	 * @param array $options Partial options.
	 * @return array
	 */
	public static function normalize_options( array $options ) {
		$defaults = WC_GPD_Settings::export_defaults();
		$merged   = wp_parse_args( $options, $defaults );

		$color = sanitize_hex_color( $merged['outline_color'] ?? '#ff0000' );

		return array(
			'include_background' => ! empty( $merged['include_background'] ),
			'include_text'       => ! empty( $merged['include_text'] ),
			'include_outlines'   => ! empty( $merged['include_outlines'] ),
			'include_shapes'     => ! empty( $merged['include_shapes'] ),
			'rasterize'          => ! empty( $merged['rasterize'] ),
			'transparent_raster' => ! empty( $merged['transparent_raster'] ),
			'outline_color'      => $color ? $color : '#ff0000',
			'outline_width'      => max( 0.1, min( 20, (float) ( $merged['outline_width'] ?? 0.25 ) ) ),
			'preset'             => isset( $merged['preset'] ) ? sanitize_key( (string) $merged['preset'] ) : 'custom',
			'preset_id'          => isset( $merged['preset_id'] ) ? sanitize_key( (string) $merged['preset_id'] ) : '',
			'template_id'        => isset( $merged['template_id'] ) ? sanitize_key( (string) $merged['template_id'] ) : '',
		);
	}

	/**
	 * Parse checkbox options from request.
	 *
	 * @param array $source Request data.
	 * @return array
	 */
	public static function options_from_request( array $source ) {
		return array(
			'include_background' => self::request_flag( $source, 'wc_gpd_inc_background' ),
			'include_text'       => self::request_flag( $source, 'wc_gpd_inc_text' ),
			'include_outlines'   => self::request_flag( $source, 'wc_gpd_inc_outlines' ),
			'include_shapes'     => self::request_flag( $source, 'wc_gpd_inc_shapes' ),
			'rasterize'          => self::request_flag( $source, 'wc_gpd_rasterize' ),
			'transparent_raster' => self::request_flag( $source, 'wc_gpd_transparent_raster' ),
			'preset'             => isset( $source['wc_gpd_preset'] ) ? sanitize_key( (string) $source['wc_gpd_preset'] ) : 'custom',
		);
	}

	/**
	 * @param array  $source Request source.
	 * @param string $key    Field name.
	 * @return bool
	 */
	private static function request_flag( array $source, $key ) {
		if ( ! isset( $source[ $key ] ) ) {
			return false;
		}
		return '1' === (string) $source[ $key ] || 1 === $source[ $key ] || true === $source[ $key ];
	}

	/**
	 * @param array  $settings      Product settings.
	 * @param string $design_svg    Customer design SVG.
	 * @param string $design_json   Customer design JSON.
	 * @param string $template_json Template JSON.
	 * @param array  $options       Export options.
	 * @return string
	 */
	public static function build_svg_document( $settings, $design_svg, $design_json, $template_json, array $options, array $product_settings = array() ) {
		$width  = isset( $settings['width'] ) ? absint( $settings['width'] ) : WC_GPD_Product_Meta::DEFAULT_WIDTH;
		$height = isset( $settings['height'] ) ? absint( $settings['height'] ) : WC_GPD_Product_Meta::DEFAULT_HEIGHT;

		$parts   = array();
		$parts[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$parts[] = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '">';

		$template_doc = WC_GPD_Template_Json::parse( $template_json );
		$design_doc   = WC_GPD_Design_Json::parse( $design_json );
		$views        = ! empty( $template_doc['views'] ) && is_array( $template_doc['views'] )
			? $template_doc['views']
			: array();

		if ( empty( $views ) ) {
			$views = array( WC_GPD_Template_Json::empty_view( 'view_front', __( 'Front', 'wc-generic-product-designer' ) ) );
		}

		foreach ( $views as $view ) {
			if ( ! is_array( $view ) || empty( $view['id'] ) ) {
				continue;
			}

			$view_id = sanitize_key( (string) $view['id'] );
			$parts[] = '<g data-wc-gpd-view="' . esc_attr( $view_id ) . '">';

			if ( ! empty( $options['include_background'] ) && ! empty( $view['objects'] ) && is_array( $view['objects'] ) ) {
				$mockup_objects = WC_GPD_Fabric_Svg::filter_by_layer_type( $view['objects'], 'mockup' );
				$mockup_markup  = WC_GPD_Fabric_Svg::objects_to_fragment( $mockup_objects );
				if ( $mockup_markup ) {
					$parts[] = '<g data-wc-gpd-layer="mockup">' . $mockup_markup . '</g>';
				} elseif ( ! empty( $view['template_image_id'] ) ) {
					$href = WC_GPD_Preview::template_href_for_export( absint( $view['template_image_id'] ) );
					if ( $href ) {
						$parts[] = '<image x="0" y="0" width="' . $width . '" height="' . $height . '" preserveAspectRatio="xMidYMid slice" href="' . esc_attr( $href ) . '" />';
					}
				}
			}

			if ( ! empty( $options['include_outlines'] ) && ! empty( $view['objects'] ) && is_array( $view['objects'] ) ) {
				$outline_objects = WC_GPD_Fabric_Svg::filter_by_layer_type( $view['objects'], 'outline' );
				$outline_style   = array(
					'outline_color' => $options['outline_color'] ?? '#ff0000',
					'outline_width' => $options['outline_width'] ?? 0.25,
				);
				$outline_objects = WC_GPD_Fabric_Svg::apply_export_outline_style( $outline_objects, $outline_style );
				$outline_markup  = WC_GPD_Fabric_Svg::objects_to_fragment( $outline_objects );
				if ( $outline_markup ) {
					$parts[] = '<g data-wc-gpd-layer="outlines">' . $outline_markup . '</g>';
				}
			}

			$view_design_objects = array();
			if ( ! empty( $design_doc['views'][ $view_id ]['objects'] ) && is_array( $design_doc['views'][ $view_id ]['objects'] ) ) {
				$view_design_objects = $design_doc['views'][ $view_id ]['objects'];
			}

			if ( ! empty( $options['include_text'] ) ) {
				$text_objects = WC_GPD_Fabric_Svg::filter_by_layer_type( $view_design_objects, 'text' );
				if ( ! empty( $view['objects'] ) && is_array( $view['objects'] ) ) {
					$text_objects = array_merge(
						$text_objects,
						WC_GPD_Fabric_Svg::filter_by_layer_type( $view['objects'], 'text' )
					);
				}
				$placeholder_objects = WC_GPD_Fabric_Svg::filter_by_layer_type( $view_design_objects, 'placeholder' );
				$text_objects        = array_merge( $text_objects, $placeholder_objects );
				$text_markup         = WC_GPD_Fabric_Svg::objects_to_fragment( $text_objects );
				if ( $text_markup ) {
					$parts[] = '<g data-wc-gpd-layer="text">' . $text_markup . '</g>';
				}
			}

			if ( ! empty( $options['include_shapes'] ) && ! empty( $view['objects'] ) && is_array( $view['objects'] ) ) {
				$graphic_objects = WC_GPD_Fabric_Svg::filter_export_graphics( $view['objects'] );
				$graphic_objects = array_merge(
					$graphic_objects,
					WC_GPD_Fabric_Svg::filter_by_layer_type( $view_design_objects, 'graphic' )
				);
				$graphic_markup = WC_GPD_Fabric_Svg::objects_to_fragment( $graphic_objects );
				if ( $graphic_markup ) {
					$parts[] = '<g data-wc-gpd-layer="graphics">' . $graphic_markup . '</g>';
				}
			}

			if ( ! empty( $options['include_shapes'] ) ) {
				$shape_objects = WC_GPD_Fabric_Svg::filter_by_layer_type( $view_design_objects, 'shape' );
				$shape_markup  = WC_GPD_Fabric_Svg::objects_to_fragment( $shape_objects );
				if ( $shape_markup ) {
					$parts[] = '<g data-wc-gpd-layer="shapes">' . $shape_markup . '</g>';
				}
			}

			$parts[] = '</g>';
		}

		if ( ! empty( $options['include_text'] ) && empty( $design_doc['views'] ) && $design_svg ) {
			$inner = WC_GPD_Preview::extract_svg_inner_public( $design_svg );
			if ( $inner ) {
				$parts[] = '<g data-wc-gpd-layer="text">' . $inner . '</g>';
			}
		}

		$parts[] = '</svg>';

		$document = implode( "\n", $parts );
		if ( strlen( $document ) < 200 ) {
			return '';
		}

		return $document;
	}

	/**
	 * Rasterize SVG via Imagick when available.
	 *
	 * @param string $svg SVG document.
	 * @return string|WP_Error
	 */
	private static function rasterize_svg( $svg, $transparent = true, $dpi = 96 ) {
		if ( ! class_exists( 'Imagick' ) ) {
			return new WP_Error(
				'wc_gpd_no_imagick',
				__( 'Raster export requires the Imagick PHP extension on your server.', 'wc-generic-product-designer' )
			);
		}

		$dpi = max( 72, min( 600, absint( $dpi ) ) );

		try {
			$imagick = new Imagick();
			$imagick->setBackgroundColor( new ImagickPixel( $transparent ? 'transparent' : 'white' ) );
			$imagick->setResolution( $dpi, $dpi );
			$imagick->readImageBlob( $svg );
			$imagick->setImageFormat( 'png32' );
			$imagick->setImageResolution( $dpi, $dpi );
			$binary = $imagick->getImageBlob();
			$imagick->clear();
			$imagick->destroy();

			return $binary ? $binary : new WP_Error( 'wc_gpd_raster_failed', __( 'Raster export failed.', 'wc-generic-product-designer' ) );
		} catch ( Exception $exception ) {
			return new WP_Error( 'wc_gpd_raster_failed', $exception->getMessage() );
		}
	}

	/**
	 * Wrap raster image bytes in a single-page PDF.
	 *
	 * @param string $png_blob PNG binary.
	 * @param int    $width_px Image width.
	 * @param int    $height_px Image height.
	 * @return string|WP_Error
	 */
	private static function png_to_pdf( $png_blob, $width_px, $height_px ) {
		if ( ! class_exists( 'Imagick' ) ) {
			return new WP_Error(
				'wc_gpd_no_imagick',
				__( 'PDF export requires the Imagick PHP extension on your server.', 'wc-generic-product-designer' )
			);
		}

		try {
			$imagick = new Imagick();
			$imagick->readImageBlob( $png_blob );
			$imagick->setImageFormat( 'pdf' );
			$imagick->setImagePage( $width_px, $height_px, 0, 0 );
			$binary = $imagick->getImageBlob();
			$imagick->clear();
			$imagick->destroy();

			return $binary ? $binary : new WP_Error( 'wc_gpd_pdf_failed', __( 'PDF export failed.', 'wc-generic-product-designer' ) );
		} catch ( Exception $exception ) {
			return new WP_Error( 'wc_gpd_pdf_failed', $exception->getMessage() );
		}
	}

	/**
	 * @param WC_Order_Item_Product $item    Line item.
	 * @param string                $ext     File extension.
	 * @param array                 $options Export options.
	 * @return string
	 */
	private static function build_filename( $item, $ext, array $options ) {
		$order_id = $item->get_order_id();
		$item_id  = $item->get_id();
		$preset   = ! empty( $options['preset'] ) ? $options['preset'] : 'custom';

		return sanitize_file_name(
			sprintf(
				'order-%d-item-%d-%s.%s',
				absint( $order_id ),
				absint( $item_id ),
				$preset,
				$ext
			)
		);
	}

	/**
	 * Build combined SVG for a production batch.
	 *
	 * @param int   $batch_id Batch post ID.
	 * @param array $options  Export options.
	 * @return array{content:string,filename:string,mime:string}|WP_Error
	 */
	public static function build_for_batch( $batch_id, array $options = array() ) {
		$batch = WC_GPD_Batch_Layout::get( $batch_id );
		if ( ! $batch || empty( $batch['layout'] ) ) {
			return new WP_Error( 'wc_gpd_batch_empty', __( 'Batch layout is empty.', 'wc-generic-product-designer' ) );
		}

		$bed     = $batch['bed'];
		$width   = absint( $bed['width_px'] ?? 2304 );
		$height  = absint( $bed['height_px'] ?? 1728 );
		$options = self::normalize_options( ! empty( $options ) ? $options : ( $batch['export_options'] ?? array() ) );

		$parts   = array();
		$parts[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$parts[] = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '">';
		$parts[] = '<rect x="0" y="0" width="100%" height="100%" fill="none" stroke="#cccccc" stroke-width="1" stroke-dasharray="4 4" data-wc-gpd-bed="1" />';

		foreach ( $batch['layout'] as $placement ) {
			$item = WC_GPD_Production_Jobs::get_item( $placement['order_id'], $placement['item_id'] );
			if ( ! $item ) {
				continue;
			}

			$result = self::build_for_order_item( $item, $options );
			if ( is_wp_error( $result ) || empty( $result['content'] ) ) {
				continue;
			}

			$inner = WC_GPD_Preview::extract_svg_inner_public( $result['content'] );
			if ( ! $inner ) {
				continue;
			}

			$x        = (float) ( $placement['x'] ?? 0 );
			$y        = (float) ( $placement['y'] ?? 0 );
			$scale    = max( 0.01, (float) ( $placement['scale'] ?? 1 ) );
			$rotation = (float) ( $placement['rotation'] ?? 0 );
			$cx       = (float) ( $placement['width'] ?? 0 ) * $scale / 2;
			$cy       = (float) ( $placement['height'] ?? 0 ) * $scale / 2;

			$transform = 'translate(' . $x . ',' . $y . ')';
			if ( $rotation ) {
				$transform .= ' rotate(' . $rotation . ',' . $cx . ',' . $cy . ')';
			}
			if ( 1.0 !== $scale ) {
				$transform .= ' scale(' . $scale . ')';
			}

			$parts[] = '<g data-wc-gpd-batch-item="' . esc_attr( $placement['order_id'] . '-' . $placement['item_id'] ) . '" transform="' . esc_attr( $transform ) . '">' . $inner . '</g>';

			$order = wc_get_order( absint( $placement['order_id'] ) );
			if ( $order ) {
				WC_GPD_Production_Jobs::set_status( $item, WC_GPD_Production_Jobs::STATUS_EXPORTED, $order );
			}
		}

		$parts[] = '</svg>';
		$content = implode( "\n", $parts );

		return array(
			'content'  => $content,
			'filename' => sanitize_file_name( 'batch-' . absint( $batch_id ) . '-' . wp_date( 'Y-m-d' ) . '.svg' ),
			'mime'     => 'image/svg+xml',
		);
	}

	/**
	 * Build proof composite SVG with template preset.
	 *
	 * @param WC_Order_Item_Product $item        Line item.
	 * @param string                $template_id Proof template ID.
	 * @return array{content:string,filename:string,mime:string}|WP_Error
	 */
	public static function build_proof_for_order_item( $item, $template_id = '' ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return new WP_Error( 'wc_gpd_invalid_item', __( 'Invalid order line item.', 'wc-generic-product-designer' ) );
		}

		$order = wc_get_order( $item->get_order_id() );
		if ( ! $order ) {
			return new WP_Error( 'wc_gpd_missing_order', __( 'Order not found.', 'wc-generic-product-designer' ) );
		}

		$template_id = $template_id ? sanitize_key( $template_id ) : WC_GPD_Proof_Template::default_id();
		$svg         = WC_GPD_Proof_Template::render_composite_svg( $order, $item, $template_id );
		if ( is_wp_error( $svg ) ) {
			return $svg;
		}

		return array(
			'content'  => $svg,
			'filename' => sanitize_file_name(
				sprintf( 'proof-order-%d-item-%d.svg', $order->get_id(), $item->get_id() )
			),
			'mime'     => 'image/svg+xml',
		);
	}

	/**
	 * Build raster PDF proof for customer delivery.
	 *
	 * @param WC_Order_Item_Product $item        Line item.
	 * @param string                $template_id Proof template ID.
	 * @return array{content:string,filename:string,mime:string}|WP_Error
	 */
	public static function build_proof_pdf_for_order_item( $item, $template_id = '' ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return new WP_Error( 'wc_gpd_invalid_item', __( 'Invalid order line item.', 'wc-generic-product-designer' ) );
		}

		$order = wc_get_order( $item->get_order_id() );
		if ( ! $order ) {
			return new WP_Error( 'wc_gpd_missing_order', __( 'Order not found.', 'wc-generic-product-designer' ) );
		}

		$template_id = $template_id ? sanitize_key( $template_id ) : WC_GPD_Proof_Template::default_id();
		$template    = WC_GPD_Proof_Template::get( $template_id );
		if ( ! $template ) {
			$template = WC_GPD_Proof_Template::get_default();
		}

		$svg = WC_GPD_Proof_Template::render_composite_svg( $order, $item, $template );
		if ( is_wp_error( $svg ) ) {
			return $svg;
		}

		$width  = absint( $template['canvas_width'] ?? 800 );
		$header = absint( $template['header_design']['height'] ?? 120 );
		$design = absint( $template['design_height'] ?? 600 );
		$height = $header + $design;
		$dpi    = absint( $template['pdf_dpi'] ?? 150 );

		$png = self::rasterize_svg( $svg, false, $dpi );
		if ( is_wp_error( $png ) ) {
			return $png;
		}

		$pdf = self::png_to_pdf( $png, $width, $height );
		if ( is_wp_error( $pdf ) ) {
			return $pdf;
		}

		return array(
			'content'  => $pdf,
			'filename' => sanitize_file_name(
				sprintf( 'proof-order-%d-item-%d.pdf', $order->get_id(), $item->get_id() )
			),
			'mime'     => 'application/pdf',
		);
	}
}
