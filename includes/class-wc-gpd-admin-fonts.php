<?php
/**
 * Fonts admin screen under Template Designer.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Font management admin UI.
 */
class WC_GPD_Admin_Fonts implements WC_GPD_Module {

	const PAGE_SLUG     = 'wc-gpd-fonts';
	const NONCE_ACTION  = 'wc_gpd_save_fonts';
	const NONCE_NAME    = 'wc_gpd_fonts_nonce';

	/**
	 * @var WC_GPD_Admin_Fonts|null
	 */
	private static $instance = null;

	/**
	 * @return WC_GPD_Admin_Fonts
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 59 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu.
	 */
	public function register_menu() {
		add_submenu_page(
			WC_GPD_Admin_Templates::PAGE_SLUG,
			__( 'Fonts', 'wc-generic-product-designer' ),
			__( 'Fonts', 'wc-generic-product-designer' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook Hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'template-designer_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'wc-gpd-admin-templates',
			WC_GPD_PLUGIN_URL . 'assets/css/admin-templates.css',
			array(),
			WC_GPD_VERSION
		);
		WC_GPD_Font_Registry::enqueue_for_designer();
	}

	/**
	 * Render fonts page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$registry = WC_GPD_Font_Registry::get_registry();
		$catalog  = WC_GPD_Font_Registry::google_catalog();

		if ( isset( $_POST['wc_gpd_fonts_save'] ) ) {
			check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );
			$enabled = isset( $_POST['wc_gpd_enabled_fonts'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['wc_gpd_enabled_fonts'] ) ) : array();
			$default = isset( $_POST['wc_gpd_default_font'] ) ? sanitize_key( wp_unslash( $_POST['wc_gpd_default_font'] ) ) : 'times_new_roman';
			$custom  = array();
			if ( ! empty( $_POST['wc_gpd_custom_fonts_json'] ) ) {
				$decoded = json_decode( wp_unslash( $_POST['wc_gpd_custom_fonts_json'] ), true );
				if ( is_array( $decoded ) ) {
					$custom = $decoded;
				}
			}
			WC_GPD_Font_Registry::save_registry(
				array(
					'enabled_google' => $enabled,
					'default_font'   => $default,
					'custom'         => $custom,
				)
			);
			$registry = WC_GPD_Font_Registry::get_registry();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Fonts saved.', 'wc-generic-product-designer' ) . '</p></div>';
		}

		$custom_json = ! empty( $registry['custom'] ) ? wp_json_encode( array_values( $registry['custom'] ) ) : '[]';
		?>
		<div class="wrap wc-gpd-templates-wrap">
			<h1><?php esc_html_e( 'Designer fonts', 'wc-generic-product-designer' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Choose which fonts load on the storefront designer. Assign a subset per template in the template editor.', 'wc-generic-product-designer' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
				<input type="hidden" name="wc_gpd_fonts_save" value="1" />
				<input type="hidden" id="wc_gpd_custom_fonts_json" name="wc_gpd_custom_fonts_json" value="<?php echo esc_attr( $custom_json ); ?>" />

				<div class="wc-gpd-settings-grid">
					<div class="wc-gpd-settings-card">
						<h4><?php esc_html_e( 'Google & system fonts', 'wc-generic-product-designer' ); ?></h4>
						<?php foreach ( $catalog as $key => $font ) : ?>
							<label class="wc-gpd-settings-check wc-gpd-font-preview-row" style="font-family:<?php echo esc_attr( $font['family'] ); ?>">
								<input type="checkbox" name="wc_gpd_enabled_fonts[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $registry['enabled_google'], true ) ); ?> />
								<?php echo esc_html( $font['label'] ); ?>
							</label>
						<?php endforeach; ?>
						<p>
							<label><?php esc_html_e( 'Default font', 'wc-generic-product-designer' ); ?>
								<select name="wc_gpd_default_font">
									<?php foreach ( $catalog as $key => $font ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $registry['default_font'], $key ); ?> style="font-family:<?php echo esc_attr( $font['family'] ); ?>"><?php echo esc_html( $font['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</p>
					</div>
					<div class="wc-gpd-settings-card">
						<h4><?php esc_html_e( 'Custom uploaded fonts', 'wc-generic-product-designer' ); ?></h4>
						<p class="description"><?php esc_html_e( 'Upload .woff, .woff2, or .ttf files. Rename the display label for customers.', 'wc-generic-product-designer' ); ?></p>
						<button type="button" class="button" id="wc-gpd-add-custom-font"><?php esc_html_e( 'Upload font', 'wc-generic-product-designer' ); ?></button>
						<ul id="wc-gpd-custom-fonts-list" class="wc-gpd-custom-fonts-list"></ul>
					</div>
				</div>
				<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save fonts', 'wc-generic-product-designer' ); ?></button></p>
			</form>
		</div>
		<script>
		( function () {
			const list = document.getElementById( 'wc-gpd-custom-fonts-list' );
			const hidden = document.getElementById( 'wc_gpd_custom_fonts_json' );
			let custom = [];
			try { custom = JSON.parse( hidden.value || '[]' ); } catch ( e ) { custom = []; }

			function persist() {
				hidden.value = JSON.stringify( custom );
				render();
			}
			function render() {
				list.innerHTML = '';
				custom.forEach( ( row, index ) => {
					const li = document.createElement( 'li' );
					li.className = 'wc-gpd-custom-font-row';
					li.innerHTML = '<input type="text" class="wc-gpd-custom-font-label" value="' + ( row.label || '' ).replace( /"/g, '&quot;' ) + '" style="font-family:' + ( row.family || 'inherit' ) + '" />'
						+ ' <button type="button" class="button-link-delete wc-gpd-remove-custom-font" data-index="' + index + '">Remove</button>';
					list.appendChild( li );
					const input = li.querySelector( '.wc-gpd-custom-font-label' );
					input.addEventListener( 'input', () => { custom[ index ].label = input.value; persist(); } );
					li.querySelector( '.wc-gpd-remove-custom-font' ).addEventListener( 'click', () => {
						custom.splice( index, 1 );
						persist();
					} );
				} );
			}
			document.getElementById( 'wc-gpd-add-custom-font' )?.addEventListener( 'click', () => {
				if ( ! window.wp || ! wp.media ) return;
				const frame = wp.media( { title: 'Upload font', button: { text: 'Use font' }, library: { type: [] }, multiple: false } );
				frame.on( 'select', () => {
					const att = frame.state().get( 'selection' ).first().toJSON();
					const id = 'font_' + Date.now().toString( 36 );
					custom.push( { id, label: att.title || 'Custom font', attachment_id: att.id, family: 'wc-gpd-custom-' + id } );
					persist();
				} );
				frame.open();
			} );
			render();
		}() );
		</script>
		<?php
	}
}
