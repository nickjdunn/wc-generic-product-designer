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
		add_action( 'wp_ajax_wc_gpd_etsy_sync_now', array( $this, 'ajax_etsy_sync' ) );
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
				$this->production_script_config()
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
				'wc-gpd-admin-proof-header',
				WC_GPD_PLUGIN_URL . 'assets/js/admin-proof-header-designer.js',
				array( 'jquery', 'fabric-js' ),
				WC_GPD_VERSION,
				true
			);
			$logo_id = absint( WC_GPD_Settings::get( 'proof_header_logo_id', 0 ) );
			wp_localize_script(
				'wc-gpd-admin-proof-header',
				'wcGpdProofHeader',
				array(
					'design'      => WC_GPD_Proof_Header::get_design(),
					'tokens'      => WC_GPD_Proof_Header::token_labels(),
					'sample'      => WC_GPD_Proof_Header::sample_tokens(),
					'logoUrl'     => $logo_id ? wp_get_attachment_url( $logo_id ) : '',
					'defaultText' => array(
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
				$this->production_script_config( 'batches' === $tab )
			);
		}
	}

	/**
	 * Route tabs.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( isset( $_POST['wc_gpd_proof_header_save'] ) ) {
			check_admin_referer( self::NONCE_ACTION );
			$this->save_proof_header_settings();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Proof header saved.', 'wc-generic-product-designer' ) . '</p></div>';
		}

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
			default:
				$this->render_dashboard();
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
			'batches'   => __( 'Batches', 'wc-generic-product-designer' ),
			'proof'     => __( 'Proof header', 'wc-generic-product-designer' ),
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
	 * Jobs dashboard.
	 */
	private function render_dashboard() {
		$status      = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$product_id  = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page        = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query = WC_GPD_Production_Jobs::query(
			array(
				'status'     => $status,
				'search'     => $search,
				'product_id' => $product_id,
				'page'       => $page,
				'per_page'   => 20,
			)
		);

		$base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		?>
		<form method="get" class="wc-gpd-production-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
			<select name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'wc-generic-product-designer' ); ?></option>
				<?php foreach ( WC_GPD_Production_Jobs::statuses() as $st ) : ?>
					<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $status, $st ); ?>><?php echo esc_html( WC_GPD_Production_Jobs::status_label( $st ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search orders…', 'wc-generic-product-designer' ); ?>" />
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wc-generic-product-designer' ); ?></button>
		</form>

		<form method="post" id="wc-gpd-production-jobs-form" action="#">
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

			<table class="wp-list-table widefat fixed striped wc-gpd-production-table">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" id="wc-gpd-select-all-jobs" /></td>
						<th><?php esc_html_e( 'Preview', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Order', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Product', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Template', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Status', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Source', 'wc-generic-product-designer' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wc-generic-product-designer' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $query['items'] ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No production jobs found.', 'wc-generic-product-designer' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $query['items'] as $job ) : ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="job_refs[]" value="<?php echo esc_attr( $job['order_id'] . ':' . $job['item_id'] ); ?>" />
							</th>
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
							<td><?php echo esc_html( ucfirst( $job['source'] ) ); ?></td>
							<td class="wc-gpd-production-actions">
								<?php if ( $job['edit_url'] ) : ?>
									<a class="button button-small" href="<?php echo esc_url( $job['edit_url'] ); ?>"><?php esc_html_e( 'Edit design', 'wc-generic-product-designer' ); ?></a>
								<?php endif; ?>
								<button type="button" class="button button-small wc-gpd-download-proof"
									data-order="<?php echo esc_attr( (string) $job['order_id'] ); ?>"
									data-item="<?php echo esc_attr( (string) $job['item_id'] ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( self::DOWNLOAD_PROOF . '_' . $job['order_id'] . '_' . $job['item_id'] ) ); ?>"><?php esc_html_e( 'Proof', 'wc-generic-product-designer' ); ?></button>
								<button type="button" class="button button-small wc-gpd-mark-ready" data-order="<?php echo esc_attr( (string) $job['order_id'] ); ?>" data-item="<?php echo esc_attr( (string) $job['item_id'] ); ?>"><?php esc_html_e( 'Ready', 'wc-generic-product-designer' ); ?></button>
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

		$bed = $batch['bed'];
		?>
		<div class="wc-gpd-batch-editor" id="wc-gpd-batch-editor"
			data-batch-id="<?php echo esc_attr( (string) $batch_id ); ?>"
			data-bed-width-px="<?php echo esc_attr( (string) ( $bed['width_px'] ?? 2304 ) ); ?>"
			data-bed-height-px="<?php echo esc_attr( (string) ( $bed['height_px'] ?? 1728 ) ); ?>"
			data-layout="<?php echo esc_attr( wp_json_encode( $batch['layout'] ) ); ?>">
			<div class="wc-gpd-batch-editor__toolbar">
				<button type="button" class="button button-primary" id="wc-gpd-batch-save"><?php esc_html_e( 'Save layout', 'wc-generic-product-designer' ); ?></button>
				<button type="button" class="button wc-gpd-download-batch"
					data-batch-id="<?php echo esc_attr( (string) $batch_id ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( self::DOWNLOAD_BATCH . '_' . $batch_id ) ); ?>"><?php esc_html_e( 'Download combined SVG', 'wc-generic-product-designer' ); ?></button>
				<span class="description"><?php echo esc_html( sprintf( __( 'Bed: %1$s × %2$s %3$s', 'wc-generic-product-designer' ), $bed['width'], $bed['height'], $bed['unit'] ) ); ?></span>
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
			</div>
		</div>
		<?php
	}

	/**
	 * Proof header settings tab.
	 */
	private function render_proof_header_settings() {
		$logo_id = absint( WC_GPD_Settings::get( 'proof_header_logo_id', 0 ) );
		$tokens  = WC_GPD_Proof_Header::token_labels();
		?>
		<form method="post" id="wc-gpd-proof-header-form">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<input type="hidden" name="wc_gpd_proof_header_save" value="1" />
			<input type="hidden" id="wc-gpd-proof-design-json" name="proof_header_design" value="" />
			<p class="description"><?php esc_html_e( 'Design the branded header shown above proofs. Drag elements on the canvas and insert autofill tokens from the palette.', 'wc-generic-product-designer' ); ?></p>
			<table class="form-table">
				<tr>
					<th><label for="wc_gpd_proof_logo"><?php esc_html_e( 'Logo', 'wc-generic-product-designer' ); ?></label></th>
					<td>
						<input type="hidden" id="wc_gpd_proof_logo_id" name="proof_header_logo_id" value="<?php echo esc_attr( (string) $logo_id ); ?>" />
						<button type="button" class="button" id="wc-gpd-proof-logo-pick"><?php esc_html_e( 'Select logo', 'wc-generic-product-designer' ); ?></button>
						<button type="button" class="button" id="wc-gpd-proof-add-logo"><?php esc_html_e( 'Add logo to header', 'wc-generic-product-designer' ); ?></button>
						<span id="wc-gpd-proof-logo-preview"><?php echo $logo_id ? wp_get_attachment_image( $logo_id, 'thumbnail' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</td>
				</tr>
			</table>
			<div class="wc-gpd-proof-designer">
				<aside class="wc-gpd-proof-designer__palette">
					<h3><?php esc_html_e( 'Autofill tokens', 'wc-generic-product-designer' ); ?></h3>
					<p class="description"><?php esc_html_e( 'Click to add a text block. Tokens are replaced with real order data on each proof.', 'wc-generic-product-designer' ); ?></p>
					<div class="wc-gpd-proof-token-list">
						<?php foreach ( $tokens as $key => $label ) : ?>
							<?php if ( 'logo' === $key ) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<button type="button" class="button wc-gpd-proof-add-token" data-token="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
					</div>
					<h3><?php esc_html_e( 'Preview data', 'wc-generic-product-designer' ); ?></h3>
					<label><input type="checkbox" id="wc-gpd-proof-preview-sample" checked="checked" /> <?php esc_html_e( 'Show sample values', 'wc-generic-product-designer' ); ?></label>
				</aside>
				<div class="wc-gpd-proof-designer__canvas-wrap">
					<canvas id="wc-gpd-proof-header-canvas" width="800" height="120"></canvas>
				</div>
			</div>
			<p><button type="submit" class="button button-primary" id="wc-gpd-proof-save"><?php esc_html_e( 'Save proof header', 'wc-generic-product-designer' ); ?></button></p>
		</form>
		<script>
		jQuery(function($){
			$('#wc-gpd-proof-logo-pick').on('click', function(e){
				e.preventDefault();
				var frame = wp.media({ title: 'Logo', button: { text: 'Use logo' }, multiple: false });
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$('#wc-gpd-proof-logo_id').val(att.id);
					$('#wc-gpd-proof-logo-preview').html('<img src="'+att.url+'" style="max-height:60px" />');
					if ( window.wcGpdProofHeader ) {
						window.wcGpdProofHeader.logoUrl = att.url;
					}
				});
				frame.open();
			});
		});
		</script>
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
	private function production_script_config( $include_ready_jobs = false ) {
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
				'error'            => __( 'Something went wrong.', 'wc-generic-product-designer' ),
				'batchCreated'     => __( 'Batch created.', 'wc-generic-product-designer' ),
			),
		);
		if ( $include_ready_jobs ) {
			$config['readyJobs'] = WC_GPD_Production_Jobs::get_ready_jobs();
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
	 * Save proof header settings.
	 */
	private function save_proof_header_settings() {
		$design_raw = isset( $_POST['proof_header_design'] ) ? wp_unslash( $_POST['proof_header_design'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$design     = is_string( $design_raw ) ? json_decode( $design_raw, true ) : array();
		WC_GPD_Settings::update(
			array(
				'proof_header_design'   => is_array( $design ) ? wp_json_encode( $design ) : '',
				'proof_header_logo_id'  => isset( $_POST['proof_header_logo_id'] ) ? absint( $_POST['proof_header_logo_id'] ) : 0,
			)
		);
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
		$result   = WC_GPD_Batch_Layout::save_layout( $batch_id, is_array( $layout ) ? $layout : array() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success();
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
		$result = WC_GPD_Export::build_for_order_item( $item, WC_GPD_Settings::export_defaults() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'svg' => $result['content'] ) );
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
		$result = WC_GPD_Export::build_proof_for_order_item( $item );
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
