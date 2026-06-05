<?php
/**
 * Design preview markup for cart and checkout.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preview HTML helpers.
 */
class WC_GPD_Preview {

	/**
	 * Build cart/checkout thumbnail HTML for a designed item.
	 *
	 * @param string $svg        Sanitized design SVG (text layers).
	 * @param array  $settings   Product designer settings from WC_GPD_Product_Meta::get_settings().
	 * @param string $alt        Image alt text.
	 * @return string
	 */
	public static function cart_thumbnail_html( $svg, $settings, $alt = '' ) {
		if ( ! $svg ) {
			return '';
		}

		$width  = isset( $settings['width'] ) ? absint( $settings['width'] ) : 800;
		$height = isset( $settings['height'] ) ? absint( $settings['height'] ) : 600;
		$ratio  = $height > 0 ? ( $width / $height ) : 1.333;

		$template_url = ! empty( $settings['template_url'] ) ? $settings['template_url'] : '';

		ob_start();
		?>
		<div class="wc-gpd-cart-thumb" style="--wc-gpd-thumb-ratio: <?php echo esc_attr( (string) $ratio ); ?>;">
			<?php if ( $template_url ) : ?>
				<img
					class="wc-gpd-cart-thumb__template"
					src="<?php echo esc_url( $template_url ); ?>"
					alt=""
					loading="lazy"
					decoding="async"
				/>
			<?php else : ?>
				<span class="wc-gpd-cart-thumb__placeholder" aria-hidden="true"></span>
			<?php endif; ?>
			<div class="wc-gpd-cart-thumb__design" aria-hidden="true">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized SVG.
				echo self::inline_svg( $svg );
				?>
			</div>
			<span class="screen-reader-text"><?php echo esc_html( $alt ); ?></span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Return sanitized inline SVG for embedding.
	 *
	 * @param string $svg Sanitized SVG string.
	 * @return string
	 */
	public static function inline_svg( $svg ) {
		$clean = WC_GPD_SVG_Sanitizer::sanitize( $svg );
		return $clean ? $clean : '';
	}
}
