<?php
/**
 * WooCommerce product admin: apply design template to product.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin product settings.
 */
class WC_GPD_Admin_Product implements WC_GPD_Module {

	/**
	 * @var WC_GPD_Admin_Product|null
	 */
	private static $instance = null;

	const NONCE_ACTION = 'wc_gpd_save_product_meta';
	const NONCE_NAME   = 'wc_gpd_product_meta_nonce';

	/**
	 * @return WC_GPD_Admin_Product
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Hooks registered via register().
	}

	/**
	 * Register module hooks.
	 */
	public function register() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_tab_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
	}

	/**
	 * Register Product Designer tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_product_tab( $tabs ) {
		$tabs['wc_gpd_designer'] = array(
			'label'    => __( 'Product Designer', 'wc-generic-product-designer' ),
			'target'   => 'wc_gpd_product_designer_panel',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Render simplified product tab.
	 */
	public function render_product_tab_panel() {
		global $post;

		$product_id  = $post ? absint( $post->ID ) : 0;
		$enabled     = get_post_meta( $product_id, WC_GPD_Product_Meta::META_ENABLED, true );
		$template_ref = absint( get_post_meta( $product_id, WC_GPD_Product_Meta::META_TEMPLATE_REF, true ) );
		if ( ! $template_ref && $product_id ) {
			$legacy_json = get_post_meta( $product_id, WC_GPD_Product_Meta::META_TEMPLATE_JSON, true );
			if ( is_string( $legacy_json ) && '' !== trim( $legacy_json ) ) {
				$template_ref = WC_GPD_Design_Template::migrate_from_product( $product_id );
			}
		}

		$templates = WC_GPD_Design_Template::list_templates();
		$applied   = $template_ref ? WC_GPD_Design_Template::get_settings( $template_ref ) : null;
		$new_url   = wp_nonce_url(
			add_query_arg(
				array(
					'page'   => WC_GPD_Admin_Templates::PAGE_SLUG,
					'action' => 'new',
				),
				admin_url( 'admin.php' )
			),
			'wc_gpd_new_template'
		);

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div id="wc_gpd_product_designer_panel" class="panel woocommerce_options_panel hidden wc-gpd-product-apply-panel">
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'wc_gpd_enabled',
						'label'       => __( 'Enable product designer', 'wc-generic-product-designer' ),
						'description' => __( 'Show the canvas designer on the product page.', 'wc-generic-product-designer' ),
						'value'       => 'yes' === $enabled ? 'yes' : 'no',
					)
				);
				?>
				<p class="form-field">
					<label for="wc_gpd_template_ref"><?php esc_html_e( 'Design template', 'wc-generic-product-designer' ); ?></label>
					<select id="wc_gpd_template_ref" name="wc_gpd_template_ref" style="min-width:240px;">
						<option value=""><?php esc_html_e( '— Select template —', 'wc-generic-product-designer' ); ?></option>
						<?php foreach ( $templates as $tpl ) : ?>
							<option value="<?php echo esc_attr( (string) $tpl['id'] ); ?>" <?php selected( $template_ref, $tpl['id'] ); ?>>
								<?php echo esc_html( $tpl['title'] . ' (' . $tpl['width'] . '×' . $tpl['height'] . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="description">
						<?php esc_html_e( 'Templates are built in Template Designer (left menu).', 'wc-generic-product-designer' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WC_GPD_Admin_Templates::PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Manage templates', 'wc-generic-product-designer' ); ?></a>
						| <a href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Create new', 'wc-generic-product-designer' ); ?></a>
					</span>
				</p>
				<?php if ( $applied ) : ?>
					<div class="wc-gpd-product-template-preview">
						<h4><?php esc_html_e( 'Template preview', 'wc-generic-product-designer' ); ?></h4>
						<p>
							<strong><?php echo esc_html( $applied['title'] ); ?></strong><br />
							<?php
							printf(
								/* translators: 1: width 2: height 3: view count */
								esc_html__( 'Canvas: %1$d × %2$d px · %3$d design area(s)', 'wc-generic-product-designer' ),
								(int) $applied['width'],
								(int) $applied['height'],
								(int) $applied['max_views']
							);
							?>
						</p>
						<div class="wc-gpd-template-preview-box" style="width:<?php echo esc_attr( (string) min( 280, $applied['width'] ) ); ?>px;aspect-ratio:<?php echo esc_attr( (string) $applied['width'] ); ?>/<?php echo esc_attr( (string) $applied['height'] ); ?>;background:<?php echo esc_attr( $applied['product_settings']['canvas_bg_color'] ); ?>;border:1px solid #ccc;border-radius:4px;"></div>
						<p>
							<a href="<?php echo esc_url( WC_GPD_Design_Template::edit_url( $template_ref ) ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Open Template Designer', 'wc-generic-product-designer' ); ?>
							</a>
						</p>
					</div>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Select a template or create one in Template Designer.', 'wc-generic-product-designer' ); ?></p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Customers customize via the Start designing button, which opens the full-screen designer while keeping product photos visible.', 'wc-generic-product-designer' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product meta.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_meta( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$enabled = isset( $_POST['wc_gpd_enabled'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, WC_GPD_Product_Meta::META_ENABLED, $enabled );

		$template_ref = isset( $_POST['wc_gpd_template_ref'] ) ? absint( $_POST['wc_gpd_template_ref'] ) : 0;
		if ( $template_ref && WC_GPD_Design_Template::POST_TYPE !== get_post_type( $template_ref ) ) {
			$template_ref = 0;
		}
		update_post_meta( $post_id, WC_GPD_Product_Meta::META_TEMPLATE_REF, $template_ref );

		WC_GPD_Logger::info(
			'Product designer settings saved',
			array(
				'product_id'   => $post_id,
				'enabled'      => $enabled,
				'template_ref' => $template_ref,
			)
		);
	}
}
