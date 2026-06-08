<?php
/**
 * Production dashboard, batch layout editor, proof and Etsy admin.
 *
 * @package WC_Generic_Product_Designer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Production admin screens.
 */
class WC_GPD_Admin_Production implements WC_GPD_Module {

	const PAGE_SLUG           = 'wc-gpd-production';
	const NONCE_ACTION        = 'wc_gpd_production';
	const DOWNLOAD_BATCH      = 'wc_gpd_download_batch';
	const DOWNLOAD_PROOF      = 'wc_gpd_download_proof';

	/**
	 * @var WC_GPD_Admin_Production|null
	 */
	private static $instance = null;

	/**
	 * @return WC_GPD_Admin_Production
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
		add_action( 'init', array( 'WC_GPD_Batch_Layout', 'register_post_type' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 58 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_BATCH, array( $this, 'handle_download_batch' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_PROOF, array( $this, 'handle_download_proof' ) );
		add_action( 'wp_ajax_wc_gpd_production_update_status', array( $this, 'ajax_update_status' ) );
		add_action( 'wp_ajax_wc_gpd_production_create_batch', array( $this, 'ajax_create_batch' ) );
		add_action( 'wp_ajax_wc_gpd_production_bulk_status', array( $this, 'ajax_bulk_status' ) );
		add_action( 'wp_ajax_wc_gpd_batch_save_layout', array( $this, 'ajax_save_batch_layout' ) );
		add_action( 'wp_ajax_wc_gpd_batch_job_svg', array( $this, 'ajax_batch_job_svg' ) );
		add_action( 'wp_ajax_wc_gpd_batch_remove_item', array( $this, 'ajax_batch_remove_item' ) );
		add_action( 'wp_ajax_wc_gpd_batch_delete', array( $this, 'ajax_batch_delete' ) );
		add_action( 'woocommerce_order_status_changed', array( 'WC_GPD_Production_Jobs', 'on_order_status_changed' ), 10, 4 );
		add_action( 'wp_ajax_wc_gpd_export_presets_save', array( $this, 'ajax_export_presets_save' ) );
		add_action( 'wp_ajax_wc_gpd_export_presets_delete', array( $this, 'ajax_export_presets_delete' ) );
		add_action( 'wp_ajax_wc_gpd_proof_templates_save', array( $this, 'ajax_proof_templates_save' ) );
		add_action( 'wp_ajax_wc_gpd_proof_templates_delete', array( $this, 'ajax_proof_templates_delete' ) );
		add_action( 'wp_ajax_wc_gpd_proof_templates_duplicate', array( $this, 'ajax_proof_templates_duplicate' ) );
		add_action( 'wp_ajax_wc_gpd_etsy_sync_now', array( $this, 'ajax_etsy_sync' ) );
		add_action( 'admin_init', array( $this, 'run_migrations' ) );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'order_columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'order_column_content' ), 10, 2 );
	}

	/**
	 * Register submenu.
	 */
	public function register_menu() {
		add_submenu_page(
			WC_GPD_Admin_Templates::PAGE_SLUG,
			__( 'Production', 'wc-generic-product-designer' ),
			__( 'Production', 'wc-generic-product-designer' ),
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

		wp_enqueue_media();
		wp_enqueue_style(
			'wc-gpd-admin-templates',
			WC_GPD_PLUGIN_URL . 'assets/css/admin-templates.css',
			array(),
			WC_GPD_VERSION
		);
		wp_enqueue_style(
			'wc-gpd-admin-production',
			WC_GPD_PLUGIN_URL . 'assets/css/admin-production.css',
			array( 'wc-gpd-admin-templates' ),
			WC_GPD_VERSION
		);

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'batch' === $tab ) {
			wp_enqueue_script(
				'fabric-js',
				'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js',
				array(),
				'5.3.1',
				true
			);
			wp_enqueue_script(
				'wc-gpd-admin-batch-layout',
				WC_GPD_PLUGIN_URL . 'assets/js/admin-batch-layout.js',
				array( 'jquery', 'fabric-js' ),
				WC_GPD_VERSION,
				true
			);
			wp_localize_script(
				'wc-gpd-admin-batch-layout',
				'wcGpdProduction',
				$this->production_script_config( false, true )
			);
		} elseif ( 'proof' === $tab ) {
			wp_enqueue_script(
				'fabric-js',
				'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js',
				array(),
				'5.3.1',
				true
			);
			wp_enqueue_script(
				'wc-gpd-admin-proof-template',
				WC_GPD_PLUGIN_URL . 'assets/js/admin-proof-template-designer.js',
				array( 'jquery', 'fabric-js' ),
				WC_GPD_VERSION,
				true
			);
			$default_template = WC_GPD_Proof_Template::get_default();
			wp_localize_script(
				'wc-gpd-admin-proof-template',
				'wcGpdProofTemplate',
				array(
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
					'nonce'            => wp_create_nonce( self::NONCE_ACTION ),
					'templates'        => WC_GPD_Proof_Template::list(),
					'defaultId'        => WC_GPD_Proof_Template::default_id(),
					'activeTemplate'   => $default_template,
					'tokens'           => WC_GPD_Proof_Header::token_labels(),
					'sample'           => WC_GPD_Proof_Header::sample_tokens(),
					'logoUrl'          => ! empty( $default_template['logo_id'] ) ? wp_get_attachment_url( $default_template['logo_id'] ) : '',
					'mockupUrl'        => ! empty( $default_template['mockup_attachment_id'] ) ? wp_get_attachment_url( $default_template['mockup_attachment_id'] ) : '',
					'defaultText'      => array(
						'site_name'               => '{site_name}',
						'site_url'                => '{site_url}',
						'order_number'            => 'Order {order_number}',
						'order_id'                => 'Order ID {order_id}',
						'customer_name'           => '{customer_name}',
						'order_date'              => '{order_date}',
						'product_name'            => '{product_name}',
						'personalization_summary' => '{personalization_summary}',
					),
				)
			);
		} else {
			wp_enqueue_script(
				'wc-gpd-admin-production-dashboard',
				WC_GPD_PLUGIN_URL . 'assets/js/admin-production-dashboard.js',
				array( 'jquery' ),
				WC_GPD_VERSION,
				true
			);
			wp_localize_script(
				'wc-gpd-admin-production-dashboard',
				'wcGpdProduction',
				$this->production_script_config(
					'batches' === $tab,
					false,
					in_array( $tab, array( 'dashboard', 'completed' ), true )
				)
			);
		}
	}

	/**
	 * Run data migrations for presets.
	 */
	public function run_migrations() {
		WC_GPD_Export_Presets::maybe_migrate();
		WC_GPD_Proof_Template::maybe_migrate();
	}

	/**
	 * Route tabs.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( isset( $_POST['wc_gpd_etsy_save'] ) ) {
			check_admin_referer( self::NONCE_ACTION );
			$this->save_etsy_settings();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Etsy settings saved.', 'wc-generic-product-designer' ) . '</p></div>';
		}

		echo '<div class="wrap wc-gpd-production-wrap">';
		echo '<h1>' . esc_html__( 'Production', 'wc-generic-product-designer' ) . '</h1>';
		$this->render_tabs( $tab );

		switch ( $tab ) {
			case 'batch':
				$this->render_batch_editor();
				break;
			case 'batches':
				$this->render_batches_list();
				break;
			case 'proof':
				$this->render_proof_header_settings();
				break;
			case 'etsy':
				$this->render_etsy_settings();
				break;
			case 'completed':
				$this->render_jobs_dashboard( 'completed' );
				break;
			default:
				$this->render_jobs_dashboard( 'active' );
		}

		echo '</div>';
	}

	/**
	 * @param string $active Active tab.
	 */
	private function render_tabs( $active ) {
		$base = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		$tabs = array(
			'dashboard' => __( 'Jobs', 'wc-generic-product-designer' ),
			'completed' => __( 'Completed', 'wc-generic-product-designer' ),
			'batches'   => __( 'Batches', 'wc-generic-product-designer' ),
			'proof'     => __( 'Proof templates', 'wc-generic-product-designer' ),
			'etsy'      => __( 'Etsy', 'wc-generic-product-designer' ),
		);
		echo '<nav class="nav-tab-wrapper wc-gpd-production-tabs">';
		foreach ( $tabs as $key => $label ) {
			$url = add_query_arg( 'tab', $key, $base );
			$cls = $key === $active ? ' nav-tab-active' : '';
			echo '<a class="nav-tab' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Jobs dashboard (active queue or completed archive).
	 *
	 * @param string $view `active` or `completed`.
	 */
	private function render_jobs_dashboard( $view = 'active' ) {
		$is_completed = 'completed' === $view;
		$tab_slug     = $is_completed ? 'completed' : 'dashboard';
		$status       = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$product_id   = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query = WC_GPD_Production_Jobs::query(
			array(
				'view'       => $view,
				'status'     => $status,
				'search'     => $search,
				'product_id' => $product_id,
				'page'       => $page,
				'per_page'   => 20,
			)
		);

		$status_choices = WC_GPD_Production_Jobs::statuses();
		if ( ! $is_completed ) {
			$status_choices = array_values(
				array_filter(
					$status_choices,
					function ( $st ) {
						return WC_GPD_Production_Jobs::STATUS_COMPLETED !== $st;
					}
				)
			);
		}
		?>
		<?php if ( $is_completed ) : ?>
			<p class="description"><?php esc_html_e( 'Finished production jobs — exported designs and orders marked complete in WooCommerce.', 'wc-generic-product-designer' ); ?></p>
		<?php endif; ?>
		<form method="get" class="wc-gpd-production-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab_slug ); ?>" />
			<select name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'wc-generic-product-designer' ); ?></option>
				<?php foreach ( $status_choices as $st ) : ?>
					<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $status, $st ); ?>><?php echo esc_html( WC_GPD_Production_Jobs::status_label( $st ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search orders…', 'wc-generic-product-designer' ); ?>" />
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wc-generic-product-designer' ); ?></button>
		</form>

		<form method="post" id="wc-gpd-production-jobs-form" action="#">
			<?php if ( ! $is_completed ) : ?>
			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<select name="bulk_action">
						<option value=""><?php esc_html_e( 'Bulk actions', 'wc-generic-product-designer' ); ?></option>
						<option value="ready"><?php esc_html_e( 'Mark ready', 'wc-generic-product-designer' ); ?></option>
						<option value="pending"><?php esc_html_e( 'Mark pending', 'wc-generic-product-designer' ); ?></option>
						<option value="proof_sent"><?php esc_html_e( 'Mark proof sent', 'wc-generic-product-designer' ); ?></option>
						<option value="proof_approved"><?php esc_html_e( 'Mark proof approved', 'wc-generic-product-designer' ); ?></option>
						<option value="create_batch"><?php esc_html_e( 'Create batch', 'wc-generic-product-designer' ); ?></option>
					</select>
					<button type="submit" class="button action"><?php esc_html_e( 'Apply', 'wc-generic-product-designer' ); ?></button>
				</div>
			</div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped wc-gpd-production-table">
				<thead>
					<tr>
						<?php if ( ! $is_completed ) : ?>
							<td class="check-column"><input type="checkbox" id="wc-gpd-select-all-jobs" /></td>
						<?php endif; ?>
						<th><?php esc_html_e( 'Preview', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Order', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Product', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Template', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wc-generic-product-designer' ); ?></th>
						<?php if ( $is_completed ) : ?>
							<th><?php esc_html_e( 'Order status', 'wc-generic-product-designer' ); ?></th>
						<?php else : ?>
							<th><?php esc_html_e( 'Source', 'wc-generic-product-designer' ); ?></th>
						<?php endif; ?>
						<th><?php esc_html_e( 'Actions', 'wc-generic-product-designer' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $query['items'] ) ) : ?>
					<tr><td colspan="<?php echo $is_completed ? '7' : '8'; ?>"><?php echo $is_completed ? esc_html__( 'No completed jobs yet.', 'wc-generic-product-designer' ) : esc_html__( 'No production jobs found.', 'wc-generic-product-designer' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $query['items'] as $job ) : ?>
						<tr>
							<?php if ( ! $is_completed ) : ?>
							<th scope="row" class="check-column">
								<input type="checkbox" name="job_refs[]" value="<?php echo esc_attr( $job['order_id'] . ':' . $job['item_id'] ); ?>" />
							</th>
							<?php endif; ?>
							<td>
								<?php if ( $job['preview_url'] ) : ?>
									<img src="<?php echo esc_url( $job['preview_url'] ); ?>" alt="" class="wc-gpd-production-thumb" width="64" height="48" />
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( $job['order_edit_url'] ); ?>">#<?php echo esc_html( $job['order_number'] ); ?></a><br />
								<span class="description"><?php echo esc_html( $job['order_date'] ); ?></span><br />
								<span class="description"><?php echo esc_html( $job['customer_name'] ); ?></span>
							</td>
							<td><?php echo esc_html( $job['product_name'] ); ?></td>
							<td><?php echo esc_html( $job['template_name'] ); ?></td>
							<td><span class="wc-gpd-status-badge wc-gpd-status-badge--<?php echo esc_attr( $job['status'] ); ?>"><?php echo esc_html( $job['status_label'] ); ?></span></td>
							<?php if ( $is_completed ) : ?>
								<td><?php echo esc_html( wc_get_order_status_name( $job['order_status'] ) ); ?></td>
							<?php else : ?>
								<td><?php echo esc_html( ucfirst( $job['source'] ) ); ?></td>
							<?php endif; ?>
							<td class="wc-gpd-production-actions">
								<a class="button button-small" href="<?php echo esc_url( $job['order_edit_url'] ); ?>"><?php esc_html_e( 'View order', 'wc-generic-product-designer' ); ?></a>
								<?php if ( ! $is_completed && $job['edit_url'] ) : ?>
									<a class="button button-small" href="<?php echo esc_url( $job['edit_url'] ); ?>"><?php esc_html_e( 'Edit design', 'wc-generic-product-designer' ); ?></a>
								<?php endif; ?>
								<select class="wc-gpd-proof-template-select" title="<?php esc_attr_e( 'Proof template', 'wc-generic-product-designer' ); ?>">
									<?php foreach ( WC_GPD_Proof_Template::list() as $proof_tpl ) : ?>
										<option value="<?php echo esc_attr( $proof_tpl['id'] ); ?>" <?php selected( WC_GPD_Proof_Template::default_id(), $proof_tpl['id'] ); ?>><?php echo esc_html( $proof_tpl['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="button" class="button button-small wc-gpd-download-proof"
									data-format="pdf"
									data-order="<?php echo esc_attr( (string) $job['order_id'] ); ?>"
									data-item="<?php echo esc_attr( (string) $job['item_id'] ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( self::DOWNLOAD_PROOF . '_' . $job['order_id'] . '_' . $job['item_id'] ) ); ?>"><?php esc_html_e( 'Proof PDF', 'wc-generic-product-designer' ); ?></button>
								<button type="button" class="button button-small wc-gpd-download-proof"
									data-format="svg"
									data-order="<?php echo esc_attr( (string) $job['order_id'] ); ?>"
									data-item="<?php echo esc_attr( (string) $job['item_id'] ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( self::DOWNLOAD_PROOF . '_' . $job['order_id'] . '_' . $job['item_id'] ) ); ?>"><?php esc_html_e( 'Proof SVG', 'wc-generic-product-designer' ); ?></button>
								<?php if ( ! $is_completed ) : ?>
								<button type="button" class="button button-small wc-gpd-mark-ready" data-order="<?php echo esc_attr( (string) $job['order_id'] ); ?>" data-item="<?php echo esc_attr( (string) $job['item_id'] ); ?>"><?php esc_html_e( 'Ready', 'wc-generic-product-designer' ); ?></button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</form>
		<?php
	}

	/**
	 * Saved batches list.
	 */
	private function render_batches_list() {
		$batches    = WC_GPD_Batch_Layout::list_batches();
		$ready_jobs = WC_GPD_Production_Jobs::get_ready_jobs();
		?>
		<div class="wc-gpd-batch-generate">
			<h2><?php esc_html_e( 'Generate batch', 'wc-generic-product-designer' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Ready jobs can be added to a new batch. Select specific jobs or generate from all ready designs.', 'wc-generic-product-designer' ); ?></p>
			<?php if ( empty( $ready_jobs ) ) : ?>
				<p><?php esc_html_e( 'No jobs are marked ready yet. Approve proofs on the Jobs tab first.', 'wc-generic-product-designer' ); ?></p>
			<?php else : ?>
				<div class="wc-gpd-batch-generate__toolbar">
					<button type="button" class="button" id="wc-gpd-select-all-ready"><?php esc_html_e( 'Select all', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button button-primary" id="wc-gpd-generate-batch-all"><?php esc_html_e( 'Generate batch (all ready)', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button button-primary" id="wc-gpd-generate-batch-selected"><?php esc_html_e( 'Generate batch (selected)', 'wc-generic-product-designer' ); ?></button>
				</div>
				<table class="wp-list-table widefat fixed striped wc-gpd-ready-jobs-table">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" id="wc-gpd-select-all-ready-cb" /></td>
							<th><?php esc_html_e( 'Preview', 'wc-generic-product-designer' ); ?></th>
							<th><?php esc_html_e( 'Job', 'wc-generic-product-designer' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $ready_jobs as $job ) : ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" class="wc-gpd-ready-job-cb" name="ready_job_refs[]" value="<?php echo esc_attr( $job['order_id'] . ':' . $job['item_id'] ); ?>" />
							</th>
							<td>
								<?php if ( ! empty( $job['preview'] ) ) : ?>
									<img src="<?php echo esc_url( $job['preview'] ); ?>" alt="" class="wc-gpd-production-thumb" width="64" height="48" />
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $job['label'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<h2><?php esc_html_e( 'Saved batches', 'wc-generic-product-designer' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Batch', 'wc-generic-product-designer' ); ?></th>
					<th><?php esc_html_e( 'Date', 'wc-generic-product-designer' ); ?></th>
					<th><?php esc_html_e( 'Jobs', 'wc-generic-product-designer' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wc-generic-product-designer' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $batches ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No batches yet. Select ready jobs and create a batch from the Jobs tab.', 'wc-generic-product-designer' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $batches as $batch ) : ?>
					<tr>
						<td><?php echo esc_html( $batch['title'] ); ?></td>
						<td><?php echo esc_html( $batch['date'] ); ?></td>
						<td><?php echo esc_html( (string) $batch['count'] ); ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'batch', 'batch_id' => $batch['id'] ), admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) ); ?>"><?php esc_html_e( 'Open layout', 'wc-generic-product-designer' ); ?></a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<?php wp_nonce_field( self::DOWNLOAD_BATCH . '_' . $batch['id'] ); ?>
								<input type="hidden" name="action" value="<?php echo esc_attr( self::DOWNLOAD_BATCH ); ?>" />
								<input type="hidden" name="batch_id" value="<?php echo esc_attr( (string) $batch['id'] ); ?>" />
								<button type="submit" class="button button-small button-primary"><?php esc_html_e( 'Download SVG', 'wc-generic-product-designer' ); ?></button>
							</form>
							<button type="button" class="button button-small button-link-delete wc-gpd-delete-batch" data-batch-id="<?php echo esc_attr( (string) $batch['id'] ); ?>"><?php esc_html_e( 'Delete', 'wc-generic-product-designer' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Batch layout editor.
	 */
	private function render_batch_editor() {
		$batch_id = isset( $_GET['batch_id'] ) ? absint( $_GET['batch_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $batch_id ) {
			echo '<p>' . esc_html__( 'No batch selected.', 'wc-generic-product-designer' ) . '</p>';
			return;
		}

		$batch = WC_GPD_Batch_Layout::get( $batch_id );
		if ( ! $batch ) {
			echo '<p>' . esc_html__( 'Batch not found.', 'wc-generic-product-designer' ) . '</p>';
			return;
		}

		$bed    = $batch['bed'];
		$export = $batch['export_options'];
		?>
		<div class="wc-gpd-batch-editor" id="wc-gpd-batch-editor"
			data-batch-id="<?php echo esc_attr( (string) $batch_id ); ?>"
			data-bed-width-px="<?php echo esc_attr( (string) ( $bed['width_px'] ?? 2304 ) ); ?>"
			data-bed-height-px="<?php echo esc_attr( (string) ( $bed['height_px'] ?? 1728 ) ); ?>"
			data-layout="<?php echo esc_attr( wp_json_encode( $batch['layout'] ) ); ?>"
			data-export-options="<?php echo esc_attr( wp_json_encode( $export ) ); ?>"
			data-bed="<?php echo esc_attr( wp_json_encode( $bed ) ); ?>">
			<div class="wc-gpd-batch-editor__toolbar">
				<button type="button" class="button button-primary" id="wc-gpd-batch-save"><?php esc_html_e( 'Save now', 'wc-generic-product-designer' ); ?></button>
				<button type="button" class="button wc-gpd-download-batch"
					data-batch-id="<?php echo esc_attr( (string) $batch_id ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( self::DOWNLOAD_BATCH . '_' . $batch_id ) ); ?>"><?php esc_html_e( 'Download combined SVG', 'wc-generic-product-designer' ); ?></button>
				<button type="button" class="button button-link-delete wc-gpd-delete-batch" data-batch-id="<?php echo esc_attr( (string) $batch_id ); ?>" data-redirect="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => 'batches' ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Delete batch', 'wc-generic-product-designer' ); ?></button>
				<span id="wc-gpd-batch-save-status" class="description"></span>
			</div>
			<div class="wc-gpd-batch-editor__body">
				<aside class="wc-gpd-batch-editor__sidebar">
					<h3><?php esc_html_e( 'Jobs in batch', 'wc-generic-product-designer' ); ?></h3>
					<ul class="wc-gpd-batch-job-list" id="wc-gpd-batch-job-list">
					<?php foreach ( $batch['layout'] as $row ) : ?>
						<?php
						$order_id = absint( $row['order_id'] ?? 0 );
						$item_id  = absint( $row['item_id'] ?? 0 );
						$label    = sprintf( 'Order %d / Item %d', $order_id, $item_id );
						$order    = wc_get_order( $order_id );
						$item     = $order ? $order->get_item( $item_id ) : null;
						if ( $order && $item ) {
							$label = sprintf( '#%s — %s', $order->get_order_number(), $item->get_name() );
						}
						?>
						<li data-order="<?php echo esc_attr( (string) $order_id ); ?>" data-item="<?php echo esc_attr( (string) $item_id ); ?>">
							<span><?php echo esc_html( $label ); ?></span>
							<button type="button" class="button-link-delete wc-gpd-batch-remove-job"><?php esc_html_e( 'Remove', 'wc-generic-product-designer' ); ?></button>
						</li>
					<?php endforeach; ?>
					</ul>
				</aside>
				<div class="wc-gpd-batch-editor__canvas-wrap">
					<canvas id="wc-gpd-batch-canvas"></canvas>
				</div>
				<aside class="wc-gpd-batch-editor__export-panel" id="wc-gpd-batch-export-panel">
					<h3><?php esc_html_e( 'Export options', 'wc-generic-product-designer' ); ?></h3>
					<p>
						<label for="wc-gpd-batch-preset"><?php esc_html_e( 'Preset', 'wc-generic-product-designer' ); ?></label>
						<select id="wc-gpd-batch-preset" class="widefat">
							<?php foreach ( WC_GPD_Export_Presets::list() as $preset ) : ?>
								<option value="<?php echo esc_attr( $preset['id'] ); ?>"><?php echo esc_html( $preset['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="wc-gpd-batch-preset-actions">
						<button type="button" class="button button-small" id="wc-gpd-batch-load-preset"><?php esc_html_e( 'Load preset', 'wc-generic-product-designer' ); ?></button>
						<button type="button" class="button button-small" id="wc-gpd-batch-save-preset"><?php esc_html_e( 'Save as preset', 'wc-generic-product-designer' ); ?></button>
						<button type="button" class="button button-small" id="wc-gpd-batch-delete-preset"><?php esc_html_e( 'Delete preset', 'wc-generic-product-designer' ); ?></button>
					</p>
					<fieldset class="wc-gpd-batch-export-toggles">
						<label><input type="checkbox" id="wc-gpd-exp-background" /> <?php esc_html_e( 'Product background', 'wc-generic-product-designer' ); ?></label>
						<label><input type="checkbox" id="wc-gpd-exp-text" /> <?php esc_html_e( 'Customer text', 'wc-generic-product-designer' ); ?></label>
						<label><input type="checkbox" id="wc-gpd-exp-outlines" /> <?php esc_html_e( 'Template outlines', 'wc-generic-product-designer' ); ?></label>
						<label><input type="checkbox" id="wc-gpd-exp-shapes" /> <?php esc_html_e( 'Shapes & graphics', 'wc-generic-product-designer' ); ?></label>
						<p class="description"><?php esc_html_e( 'Combined batch download is always SVG.', 'wc-generic-product-designer' ); ?></p>
					</fieldset>
					<p>
						<label><?php esc_html_e( 'Outline color', 'wc-generic-product-designer' ); ?>
							<input type="color" id="wc-gpd-exp-outline-color" value="#ff0000" />
						</label>
					</p>
					<p>
						<label><?php esc_html_e( 'Outline width', 'wc-generic-product-designer' ); ?>
							<input type="number" id="wc-gpd-exp-outline-width" min="0.1" max="20" step="0.1" value="0.25" />
						</label>
					</p>
					<h4><?php esc_html_e( 'Machine bed', 'wc-generic-product-designer' ); ?></h4>
					<p class="wc-gpd-batch-bed-fields">
						<input type="number" id="wc-gpd-bed-width" min="1" step="0.1" />
						<input type="number" id="wc-gpd-bed-height" min="1" step="0.1" />
						<select id="wc-gpd-bed-unit">
							<option value="in"><?php esc_html_e( 'in', 'wc-generic-product-designer' ); ?></option>
							<option value="mm"><?php esc_html_e( 'mm', 'wc-generic-product-designer' ); ?></option>
						</select>
					</p>
					<p>
						<label><?php esc_html_e( 'DPI', 'wc-generic-product-designer' ); ?>
							<input type="number" id="wc-gpd-bed-dpi" min="72" max="600" step="1" />
						</label>
					</p>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * Proof templates tab.
	 */
	private function render_proof_header_settings() {
		$tokens = WC_GPD_Proof_Header::token_labels();
		?>
		<div class="wc-gpd-proof-templates" id="wc-gpd-proof-templates">
			<aside class="wc-gpd-proof-templates__list">
				<h3><?php esc_html_e( 'Templates', 'wc-generic-product-designer' ); ?></h3>
				<ul id="wc-gpd-proof-template-list"></ul>
				<p>
					<button type="button" class="button" id="wc-gpd-proof-template-add"><?php esc_html_e( 'Add template', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button" id="wc-gpd-proof-template-duplicate"><?php esc_html_e( 'Duplicate', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button" id="wc-gpd-proof-template-delete"><?php esc_html_e( 'Delete', 'wc-generic-product-designer' ); ?></button>
				</p>
			</aside>
			<div class="wc-gpd-proof-templates__editor">
				<p class="description"><?php esc_html_e( 'Design proof layouts with a branded header, optional product mockup photo, and layer options. Proofs download as PDF by default.', 'wc-generic-product-designer' ); ?></p>
				<p>
					<label><?php esc_html_e( 'Template name', 'wc-generic-product-designer' ); ?>
						<input type="text" id="wc-gpd-proof-template-name" class="regular-text" />
					</label>
				</p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Logo', 'wc-generic-product-designer' ); ?></th>
						<td>
							<input type="hidden" id="wc-gpd-proof-logo-id" value="0" />
							<button type="button" class="button" id="wc-gpd-proof-logo-pick"><?php esc_html_e( 'Select logo', 'wc-generic-product-designer' ); ?></button>
							<button type="button" class="button" id="wc-gpd-proof-add-logo"><?php esc_html_e( 'Add logo to header', 'wc-generic-product-designer' ); ?></button>
							<span id="wc-gpd-proof-logo-preview"></span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Proof mockup photo', 'wc-generic-product-designer' ); ?></th>
						<td>
							<input type="hidden" id="wc-gpd-proof-mockup-id" value="0" />
							<button type="button" class="button" id="wc-gpd-proof-mockup-pick"><?php esc_html_e( 'Select mockup (e.g. slate)', 'wc-generic-product-designer' ); ?></button>
							<span id="wc-gpd-proof-mockup-preview"></span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Proof body layers', 'wc-generic-product-designer' ); ?></th>
						<td>
							<label><input type="checkbox" id="wc-gpd-proof-inc-background" /> <?php esc_html_e( 'Template background / mockup layer', 'wc-generic-product-designer' ); ?></label><br />
							<label><input type="checkbox" id="wc-gpd-proof-inc-text" /> <?php esc_html_e( 'Customer text', 'wc-generic-product-designer' ); ?></label><br />
							<label><input type="checkbox" id="wc-gpd-proof-inc-outlines" /> <?php esc_html_e( 'Template outlines', 'wc-generic-product-designer' ); ?></label><br />
							<label><input type="checkbox" id="wc-gpd-proof-inc-shapes" /> <?php esc_html_e( 'Shapes & graphics', 'wc-generic-product-designer' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'PDF quality', 'wc-generic-product-designer' ); ?></th>
						<td>
							<label><?php esc_html_e( 'DPI', 'wc-generic-product-designer' ); ?>
								<input type="number" id="wc-gpd-proof-pdf-dpi" min="72" max="600" value="150" />
							</label>
						</td>
					</tr>
				</table>
				<div class="wc-gpd-proof-designer">
					<aside class="wc-gpd-proof-designer__palette">
						<h3><?php esc_html_e( 'Header tokens', 'wc-generic-product-designer' ); ?></h3>
						<div class="wc-gpd-proof-token-list">
							<?php foreach ( $tokens as $key => $label ) : ?>
								<?php if ( 'logo' === $key ) : continue; endif; ?>
								<button type="button" class="button wc-gpd-proof-add-token" data-token="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
							<?php endforeach; ?>
						</div>
						<label><input type="checkbox" id="wc-gpd-proof-preview-sample" checked="checked" /> <?php esc_html_e( 'Show sample values', 'wc-generic-product-designer' ); ?></label>
					</aside>
					<div class="wc-gpd-proof-designer__canvas-wrap">
						<canvas id="wc-gpd-proof-header-canvas" width="800" height="120"></canvas>
					</div>
				</div>
				<p>
					<button type="button" class="button" id="wc-gpd-proof-set-default"><?php esc_html_e( 'Set as default', 'wc-generic-product-designer' ); ?></button>
					<button type="button" class="button button-primary" id="wc-gpd-proof-template-save"><?php esc_html_e( 'Save template', 'wc-generic-product-designer' ); ?></button>
					<span id="wc-gpd-proof-template-status" class="description"></span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Etsy settings tab.
	 */
	private function render_etsy_settings() {
		$s       = WC_GPD_Settings::all();
		$map     = WC_GPD_Etsy_Client::get_listing_map();
		$products = wc_get_products( array( 'limit' => 200, 'status' => 'publish' ) );
		?>
		<form method="post">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<input type="hidden" name="wc_gpd_etsy_save" value="1" />
			<h2><?php esc_html_e( 'API credentials', 'wc-generic-product-designer' ); ?></h2>
			<table class="form-table">
				<tr><th><?php esc_html_e( 'API key (keystring)', 'wc-generic-product-designer' ); ?></th><td><input type="text" class="regular-text" name="etsy_api_key" value="<?php echo esc_attr( $s['etsy_api_key'] ?? '' ); ?>" /></td></tr>
				<tr><th><?php esc_html_e( 'Shared secret', 'wc-generic-product-designer' ); ?></th><td><input type="text" class="regular-text" name="etsy_shared_secret" value="<?php echo esc_attr( $s['etsy_shared_secret'] ?? '' ); ?>" /></td></tr>
				<tr><th><?php esc_html_e( 'Refresh token', 'wc-generic-product-designer' ); ?></th><td><input type="text" class="large-text" name="etsy_refresh_token" value="<?php echo esc_attr( $s['etsy_refresh_token'] ?? '' ); ?>" /></td></tr>
				<tr><th><?php esc_html_e( 'Shop ID', 'wc-generic-product-designer' ); ?></th><td><input type="text" class="regular-text" name="etsy_shop_id" value="<?php echo esc_attr( $s['etsy_shop_id'] ?? '' ); ?>" /></td></tr>
			</table>

			<h2><?php esc_html_e( 'Listing map', 'wc-generic-product-designer' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Connect Etsy listing IDs to WooCommerce products with design templates. Personalization rules JSON maps Etsy fields to template placeholder keys.', 'wc-generic-product-designer' ); ?></p>
			<table class="widefat" id="wc-gpd-etsy-map-table">
				<thead><tr><th><?php esc_html_e( 'Etsy listing ID', 'wc-generic-product-designer' ); ?></th><th><?php esc_html_e( 'WC product', 'wc-generic-product-designer' ); ?></th><th><?php esc_html_e( 'Rules JSON', 'wc-generic-product-designer' ); ?></th></tr></thead>
				<tbody>
				<?php
				$rows = $map ? $map : array( '' => array() );
				if ( empty( $rows ) ) {
					$rows = array( '' => array() );
				}
				foreach ( $rows as $listing_id => $row ) :
					?>
					<tr>
						<td><input type="text" name="etsy_listing_ids[]" value="<?php echo esc_attr( (string) $listing_id ); ?>" class="regular-text" /></td>
						<td>
							<select name="etsy_product_ids[]">
								<option value=""><?php esc_html_e( '— Select —', 'wc-generic-product-designer' ); ?></option>
								<?php foreach ( $products as $product ) : ?>
									<option value="<?php echo esc_attr( (string) $product->get_id() ); ?>" <?php selected( absint( $row['product_id'] ?? 0 ), $product->get_id() ); ?>><?php echo esc_html( $product->get_name() ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><textarea name="etsy_rules[]" class="large-text code" rows="3"><?php echo esc_textarea( ! empty( $row['rules'] ) ? wp_json_encode( $row['rules'], JSON_PRETTY_PRINT ) : '{"fields":[{"etsy_label":"Name","placeholder_key":"name"}],"font_map":{"Script":"great_vibes"}}' ); ?></textarea></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="wc-gpd-etsy-add-row"><?php esc_html_e( 'Add mapping row', 'wc-generic-product-designer' ); ?></button></p>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Etsy settings', 'wc-generic-product-designer' ); ?></button>
				<button type="button" class="button" id="wc-gpd-etsy-sync-now"><?php esc_html_e( 'Sync orders now', 'wc-generic-product-designer' ); ?></button>
				<span id="wc-gpd-etsy-sync-result" class="description"></span>
			</p>
		</form>
		<script>
		jQuery(function($){
			$('#wc-gpd-etsy-add-row').on('click', function(){
				$('#wc-gpd-etsy-map-table tbody').append($('#wc-gpd-etsy-map-table tbody tr:first').clone());
				$('#wc-gpd-etsy-map-table tbody tr:last input, #wc-gpd-etsy-map-table tbody tr:last textarea').val('');
			});
			$('#wc-gpd-etsy-sync-now').on('click', function(){
				$.post(ajaxurl, { action: 'wc_gpd_etsy_sync_now', nonce: '<?php echo esc_js( wp_create_nonce( self::NONCE_ACTION ) ); ?>' }, function(resp){
					$('#wc-gpd-etsy-sync-result').text(resp.data && resp.data.message ? resp.data.message : 'Done');
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Script config for production dashboard / batch editor.
	 *
	 * @param bool $include_ready_jobs Include ready jobs list.
	 * @return array<string,mixed>
	 */
	private function production_script_config( $include_ready_jobs = false, $include_export_presets = false, $include_proof_templates = false ) {
		$config = array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'adminPostUrl'   => admin_url( 'admin-post.php' ),
			'nonce'          => wp_create_nonce( self::NONCE_ACTION ),
			'downloadProof'  => self::DOWNLOAD_PROOF,
			'downloadBatch'  => self::DOWNLOAD_BATCH,
			'batchEditorUrl' => add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					'tab'  => 'batch',
				),
				admin_url( 'admin.php' )
			),
			'i18n'           => array(
				'confirmBatch'     => __( 'Create a batch from selected jobs?', 'wc-generic-product-designer' ),
				'confirmRemove'    => __( 'Remove this job from the batch? It will return to Ready status.', 'wc-generic-product-designer' ),
				'selectJobs'       => __( 'Select at least one job.', 'wc-generic-product-designer' ),
				'noReadyJobs'      => __( 'No ready jobs available.', 'wc-generic-product-designer' ),
				'saved'            => __( 'Layout saved.', 'wc-generic-product-designer' ),
				'saving'           => __( 'Saving…', 'wc-generic-product-designer' ),
				'error'            => __( 'Something went wrong.', 'wc-generic-product-designer' ),
				'batchCreated'     => __( 'Batch created.', 'wc-generic-product-designer' ),
				'presetSaved'      => __( 'Preset saved.', 'wc-generic-product-designer' ),
				'presetDeleted'    => __( 'Preset deleted.', 'wc-generic-product-designer' ),
				'confirmDeletePreset' => __( 'Delete this preset?', 'wc-generic-product-designer' ),
				'confirmDeleteBatch'  => __( 'Delete this batch? Jobs will return to Ready status.', 'wc-generic-product-designer' ),
				'batchDeleted'        => __( 'Batch deleted.', 'wc-generic-product-designer' ),
			),
		);
		if ( $include_ready_jobs ) {
			$config['readyJobs'] = WC_GPD_Production_Jobs::get_ready_jobs();
		}
		if ( $include_export_presets ) {
			$config['exportPresets'] = WC_GPD_Export_Presets::list();
		}
		if ( $include_proof_templates ) {
			$config['proofTemplates'] = WC_GPD_Proof_Template::list();
			$config['defaultProofTemplateId'] = WC_GPD_Proof_Template::default_id();
		}
		return $config;
	}

	/**
	 * Parse job ref strings into arrays.
	 *
	 * @param array $refs Ref strings order_id:item_id.
	 * @return array<int,array{order_id:int,item_id:int}>
	 */
	private function parse_job_refs( array $refs ) {
		$parsed = array();
		foreach ( $refs as $ref ) {
			$parts = explode( ':', (string) $ref );
			if ( count( $parts ) === 2 ) {
				$parsed[] = array(
					'order_id' => absint( $parts[0] ),
					'item_id'  => absint( $parts[1] ),
				);
			}
		}
		return $parsed;
	}

	/**
	 * Save Etsy settings.
	 */
	private function save_etsy_settings() {
		$map    = array();
		$ids    = isset( $_POST['etsy_listing_ids'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['etsy_listing_ids'] ) ) : array();
		$pids   = isset( $_POST['etsy_product_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['etsy_product_ids'] ) ) : array();
		$rules  = isset( $_POST['etsy_rules'] ) ? wp_unslash( $_POST['etsy_rules'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		foreach ( $ids as $i => $listing_id ) {
			$listing_id = trim( $listing_id );
			if ( ! $listing_id ) {
				continue;
			}
			$rule_json = isset( $rules[ $i ] ) ? $rules[ $i ] : '{}';
			$decoded   = json_decode( $rule_json, true );
			$map[ $listing_id ] = array(
				'product_id' => isset( $pids[ $i ] ) ? absint( $pids[ $i ] ) : 0,
				'rules'      => is_array( $decoded ) ? $decoded : array(),
			);
		}

		WC_GPD_Settings::update(
			array(
				'etsy_api_key'       => isset( $_POST['etsy_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['etsy_api_key'] ) ) : '',
				'etsy_shared_secret' => isset( $_POST['etsy_shared_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['etsy_shared_secret'] ) ) : '',
				'etsy_refresh_token' => isset( $_POST['etsy_refresh_token'] ) ? sanitize_text_field( wp_unslash( $_POST['etsy_refresh_token'] ) ) : '',
				'etsy_shop_id'       => isset( $_POST['etsy_shop_id'] ) ? sanitize_text_field( wp_unslash( $_POST['etsy_shop_id'] ) ) : '',
				'etsy_listing_map'   => $map,
			)
		);
		delete_transient( 'wc_gpd_etsy_access_token' );
	}

	/**
	 * AJAX create batch from job refs.
	 */
	public function ajax_create_batch() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-generic-product-designer' ) ) );
		}

		$refs = isset( $_POST['job_refs'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['job_refs'] ) ) : array();
		$parsed = $this->parse_job_refs( $refs );
		if ( empty( $parsed ) ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one job.', 'wc-generic-product-designer' ) ) );
		}

		$result = WC_GPD_Batch_Layout::create( $parsed );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'batch_id' => $result,
				'redirect' => add_query_arg(
					array(
						'page'     => self::PAGE_SLUG,
						'tab'      => 'batch',
						'batch_id' => $result,
					),
					admin_url( 'admin.php' )
				),
			)
		);
	}

	/**
	 * AJAX bulk status update.
	 */
	public function ajax_bulk_status() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$refs   = isset( $_POST['job_refs'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['job_refs'] ) ) : array();
		$parsed = $this->parse_job_refs( $refs );

		if ( empty( $parsed ) || ! in_array( $action, WC_GPD_Production_Jobs::statuses(), true ) ) {
			wp_send_json_error();
		}

		$count = WC_GPD_Production_Jobs::bulk_set_status( $parsed, $action );
		wp_send_json_success( array( 'count' => $count ) );
	}

	/**
	 * AJAX delete batch and release jobs.
	 */
	public function ajax_batch_delete() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-generic-product-designer' ) ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		$result   = WC_GPD_Batch_Layout::delete_batch( $batch_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'redirect' => add_query_arg(
					array(
						'page' => self::PAGE_SLUG,
						'tab'  => 'batches',
					),
					admin_url( 'admin.php' )
				),
			)
		);
	}

	/**
	 * AJAX remove job from batch.
	 */
	public function ajax_batch_remove_item() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}

		$batch_id = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

		$result = WC_GPD_Batch_Layout::remove_item( $batch_id, $order_id, $item_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success();
	}

	/**
	 * AJAX status update.
	 */
	public function ajax_update_status() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wc-generic-product-designer' ) ) );
		}
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : WC_GPD_Production_Jobs::STATUS_READY;
		$order    = wc_get_order( $order_id );
		$item     = $order ? $order->get_item( $item_id ) : null;
		if ( ! $item ) {
			wp_send_json_error();
		}
		WC_GPD_Production_Jobs::set_status( $item, $status, $order );
		wp_send_json_success();
	}

	/**
	 * AJAX save batch layout.
	 */
	public function ajax_save_batch_layout() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$batch_id = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		$layout   = isset( $_POST['layout'] ) ? json_decode( wp_unslash( $_POST['layout'] ), true ) : array();
		$bed      = isset( $_POST['bed'] ) ? json_decode( wp_unslash( $_POST['bed'] ), true ) : array();
		$options  = isset( $_POST['export_options'] ) ? json_decode( wp_unslash( $_POST['export_options'] ), true ) : array();
		$result   = WC_GPD_Batch_Layout::save_batch_state(
			$batch_id,
			is_array( $layout ) ? $layout : array(),
			is_array( $bed ) ? $bed : array(),
			is_array( $options ) ? $options : array()
		);
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'saved_at' => wp_date( 'g:i A' ) ) );
	}

	/**
	 * AJAX per-job SVG for batch editor.
	 */
	public function ajax_batch_job_svg() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$item = WC_GPD_Production_Jobs::get_item(
			isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0,
			isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0
		);
		if ( ! $item ) {
			wp_send_json_error();
		}
		$batch_id = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		$options  = WC_GPD_Settings::export_defaults();
		if ( $batch_id ) {
			$batch = WC_GPD_Batch_Layout::get( $batch_id );
			if ( $batch && ! empty( $batch['export_options'] ) ) {
				$options = $batch['export_options'];
			}
		}
		$result = WC_GPD_Export::build_for_order_item( $item, $options );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'svg' => $result['content'] ) );
	}

	/**
	 * AJAX save export preset.
	 */
	public function ajax_export_presets_save() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$preset = isset( $_POST['preset'] ) ? json_decode( wp_unslash( $_POST['preset'] ), true ) : array();
		$result = WC_GPD_Export_Presets::save( is_array( $preset ) ? $preset : array() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'preset' => $result, 'presets' => WC_GPD_Export_Presets::list() ) );
	}

	/**
	 * AJAX delete export preset.
	 */
	public function ajax_export_presets_delete() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$id     = isset( $_POST['preset_id'] ) ? sanitize_key( wp_unslash( $_POST['preset_id'] ) ) : '';
		$result = WC_GPD_Export_Presets::delete( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'presets' => WC_GPD_Export_Presets::list() ) );
	}

	/**
	 * AJAX save proof template.
	 */
	public function ajax_proof_templates_save() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$template = isset( $_POST['template'] ) ? json_decode( wp_unslash( $_POST['template'] ), true ) : array();
		$result   = WC_GPD_Proof_Template::save( is_array( $template ) ? $template : array() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		if ( ! empty( $_POST['set_default'] ) ) {
			WC_GPD_Proof_Template::set_default( $result['id'] );
		}
		wp_send_json_success( array( 'template' => $result, 'templates' => WC_GPD_Proof_Template::list() ) );
	}

	/**
	 * AJAX delete proof template.
	 */
	public function ajax_proof_templates_delete() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$id     = isset( $_POST['template_id'] ) ? sanitize_key( wp_unslash( $_POST['template_id'] ) ) : '';
		$result = WC_GPD_Proof_Template::delete( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'templates' => WC_GPD_Proof_Template::list(), 'defaultId' => WC_GPD_Proof_Template::default_id() ) );
	}

	/**
	 * AJAX duplicate proof template.
	 */
	public function ajax_proof_templates_duplicate() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$id     = isset( $_POST['template_id'] ) ? sanitize_key( wp_unslash( $_POST['template_id'] ) ) : '';
		$result = WC_GPD_Proof_Template::duplicate( $id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'template' => $result, 'templates' => WC_GPD_Proof_Template::list() ) );
	}

	/**
	 * AJAX Etsy sync.
	 */
	public function ajax_etsy_sync() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error();
		}
		$result = WC_GPD_Etsy_Sync::sync_recent_receipts();
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: 1: imported count, 2: skipped count */
					__( 'Imported %1$d, skipped %2$d.', 'wc-generic-product-designer' ),
					$result['imported'],
					$result['skipped']
				),
			)
		);
	}

	/**
	 * Download combined batch SVG.
	 */
	public function handle_download_batch() {
		$batch_id = isset( $_POST['batch_id'] ) ? absint( $_POST['batch_id'] ) : 0;
		check_admin_referer( self::DOWNLOAD_BATCH . '_' . $batch_id );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-generic-product-designer' ), 403 );
		}
		$result = WC_GPD_Export::build_for_batch( $batch_id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), 500 );
		}
		header( 'Content-Type: ' . $result['mime'] );
		header( 'Content-Disposition: attachment; filename="' . $result['filename'] . '"' );
		header( 'Content-Length: ' . strlen( $result['content'] ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $result['content'];
		exit;
	}

	/**
	 * Download proof SVG.
	 */
	public function handle_download_proof() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$item_id  = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;
		check_admin_referer( self::DOWNLOAD_PROOF . '_' . $order_id . '_' . $item_id );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'wc-generic-product-designer' ), 403 );
		}
		$item = WC_GPD_Production_Jobs::get_item( $order_id, $item_id );
		if ( ! $item ) {
			wp_die( esc_html__( 'Job not found.', 'wc-generic-product-designer' ), 404 );
		}
		$template_id = isset( $_POST['template_id'] ) ? sanitize_key( wp_unslash( $_POST['template_id'] ) ) : '';
		$format      = isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'pdf';
		if ( 'svg' === $format ) {
			$result = WC_GPD_Export::build_proof_for_order_item( $item, $template_id );
		} else {
			$result = WC_GPD_Export::build_proof_pdf_for_order_item( $item, $template_id );
		}
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), 500 );
		}
		$order = wc_get_order( $order_id );
		if ( $order ) {
			WC_GPD_Production_Jobs::set_status( $item, WC_GPD_Production_Jobs::STATUS_PROOF_SENT, $order );
		}
		header( 'Content-Type: ' . $result['mime'] );
		header( 'Content-Disposition: attachment; filename="' . $result['filename'] . '"' );
		header( 'Content-Length: ' . strlen( $result['content'] ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $result['content'];
		exit;
	}

	/**
	 * @param array $columns Columns.
	 * @return array
	 */
	public function order_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new['wc_gpd_design'] = __( 'Design', 'wc-generic-product-designer' );
			}
		}
		return $new;
	}

	/**
	 * @param string $column Column.
	 * @param int    $post_id Post ID.
	 */
	public function order_column_content( $column, $post_id ) {
		if ( 'wc_gpd_design' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			if ( WC_GPD_Production_Jobs::item_has_design( $item ) ) {
				echo '<span class="dashicons dashicons-art" title="' . esc_attr__( 'Has custom design', 'wc-generic-product-designer' ) . '"></span>';
				return;
			}
		}
	}
}
