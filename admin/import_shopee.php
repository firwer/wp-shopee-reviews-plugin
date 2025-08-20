<?php
// woocommerce-photo-reviews/admin/import_shopee.php
/**
 * Class VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Import_Shopee
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Import_Shopee {
	protected $settings;
	protected $options;
	protected $table_name;

	public function __construct() {
		global $wpdb;
		$this->settings = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
		$this->table_name = $wpdb->prefix . 'wcpr_shopee_sync_history';
		
		$this->options = wp_parse_args( get_option( 'wcpr_shopee_options', array() ), array(
			'enabled'         => 1,
			'endpoint'        => 'https://asia-southeast1-starlit-array-328711.cloudfunctions.net/shopeeboy/comments/sync',
			'daily'           => 1,
			'cron_interval'   => 'daily',
			'cron_time'       => '02:00',
			'download_media'  => 1,
			'review_status'   => '1',
			'page_size'       => 100,
			'max_fetch'       => 500,
			'lookback_seconds' => 3600,
		) );

		add_action( 'admin_menu', array( $this, 'add_menu' ), 21 );
		add_action( 'admin_init', array( $this, 'maybe_schedule' ) );
		add_action( 'wp_ajax_wcpr_shopee_sync', array( $this, 'ajax_sync' ) );
		add_action( 'wp_ajax_wcpr_shopee_sync_product', array( $this, 'ajax_sync_product' ) );
		add_action( 'wp_ajax_wcpr_shopee_get_products', array( $this, 'ajax_get_products' ) );
		add_action( 'wp_ajax_wcpr_shopee_get_history', array( $this, 'ajax_get_history' ) );
		add_action( 'admin_post_wcpr_shopee_sync', array( $this, 'handle_post_sync' ) );
		add_action( 'wcpr_shopee_daily_sync', array( $this, 'cron_sync_all' ) );
		add_action( 'wp_ajax_wcpr_shopee_sync_all', array( $this, 'ajax_sync_all' ) );
		
		// Add custom cron intervals
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
		
		// Create table if needed
		add_action( 'init', array( $this, 'maybe_create_table' ) );
	}

	public function add_menu() {
		add_submenu_page(
			'woocommerce-photo-reviews',
			esc_html__( 'Shopee Sync', 'woocommerce-photo-reviews' ),
			esc_html__( 'Shopee Sync', 'woocommerce-photo-reviews' ),
			$this->settings->get_setting_capability(),
			'wcpr_shopee_sync',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		// Handle settings save
		if ( isset( $_POST['wcpr_shopee_save'] ) && check_admin_referer( 'wcpr_shopee_sync_nonce', 'wcpr_shopee_sync_nonce' ) ) {
			$this->options['enabled']        = isset( $_POST['wcpr_shopee_enabled'] ) ? 1 : 0;
			$this->options['endpoint']       = sanitize_url( $_POST['wcpr_shopee_endpoint'] );
			
			// Validate endpoint
			if ( ! filter_var( $this->options['endpoint'], FILTER_VALIDATE_URL ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid API endpoint URL', 'woocommerce-photo-reviews' ) . '</p></div>';
				return;
			}
			
			$this->options['daily']          = isset( $_POST['wcpr_shopee_daily'] ) ? 1 : 0;
			$this->options['cron_interval']  = sanitize_text_field( $_POST['wcpr_shopee_cron_interval'] );
			$this->options['cron_time']      = sanitize_text_field( $_POST['wcpr_shopee_cron_time'] );
			$this->options['download_media'] = isset( $_POST['wcpr_shopee_download'] ) ? 1 : 0;
			$this->options['review_status']  = isset( $_POST['wcpr_shopee_status'] ) && $_POST['wcpr_shopee_status'] === '0' ? '0' : '1';
			$this->options['page_size']        = isset( $_POST['wcpr_shopee_page_size'] ) ? max( 1, min( 100, intval( $_POST['wcpr_shopee_page_size'] ) ) ) : 100;
			$this->options['max_fetch']        = isset( $_POST['wcpr_shopee_max_fetch'] ) ? max( 1, min( 1000, intval( $_POST['wcpr_shopee_max_fetch'] ) ) ) : 500;
			$this->options['lookback_seconds'] = isset( $_POST['wcpr_shopee_lookback'] ) ? max( 0, intval( $_POST['wcpr_shopee_lookback'] ) ) : 3600;
			
			update_option( 'wcpr_shopee_options', $this->options );
			
			// Reschedule cron if needed
			if ( $this->options['enabled'] && $this->options['daily'] ) {
				$this->reschedule_cron();
			}
			
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully!', 'woocommerce-photo-reviews' ) . '</p></div>';
		}
		
		// Enqueue required scripts and styles
		wp_enqueue_script( 'wcpr-shopee-admin', VI_WOOCOMMERCE_PHOTO_REVIEWS_JS . 'shopee-admin.js', array( 'jquery' ), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION, true );
		wp_enqueue_style( 'wcpr-shopee-admin', VI_WOOCOMMERCE_PHOTO_REVIEWS_CSS . 'shopee-admin.css', array(), VI_WOOCOMMERCE_PHOTO_REVIEWS_VERSION );
		
		// Localize script with AJAX URL and nonce
		wp_localize_script( 'wcpr-shopee-admin', 'wcpr_shopee_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wcpr_shopee_ajax_nonce' ),
			'strings'  => array(
				'syncing'     => __( 'Sync Reviews', 'woocommerce-photo-reviews' ),
				'success'     => __( 'Sync completed successfully!', 'woocommerce-photo-reviews' ),
				'error'       => __( 'Sync failed!', 'woocommerce-photo-reviews' ),
				'no_products' => __( 'No products found with SKU mapping.', 'woocommerce-photo-reviews' ),
			)
		) );
		
		?>
		<div class="wrap wcpr-shopee-admin">
			<h1><?php esc_html_e( 'Shopee Reviews Sync', 'woocommerce-photo-reviews' ); ?></h1>
			
			<!-- Settings Tab -->
			<div class="wcpr-tab-container">
				<nav class="wcpr-tab-nav">
					<a href="#settings" class="wcpr-tab-link active"><?php esc_html_e( 'Settings', 'woocommerce-photo-reviews' ); ?></a>
					<a href="#manual-sync" class="wcpr-tab-link"><?php esc_html_e( 'Manual Sync', 'woocommerce-photo-reviews' ); ?></a>
					<a href="#sync-history" class="wcpr-tab-link"><?php esc_html_e( 'Sync History', 'woocommerce-photo-reviews' ); ?></a>
				</nav>
				
				<!-- Settings Tab Content -->
				<div id="settings" class="wcpr-tab-content active">
					<form method="post" class="wcpr-settings-form">
						<?php wp_nonce_field( 'wcpr_shopee_sync_nonce', 'wcpr_shopee_sync_nonce' ); ?>
						
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Integration', 'woocommerce-photo-reviews' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wcpr_shopee_enabled" <?php checked( $this->options['enabled'], 1 ); ?>>
										<?php esc_html_e( 'Enable Shopee integration', 'woocommerce-photo-reviews' ); ?>
									</label>
								</td>
							</tr>
							
							<tr>
								<th scope="row"><?php esc_html_e( 'API Endpoint', 'woocommerce-photo-reviews' ); ?></th>
								<td>
									<input type="url" name="wcpr_shopee_endpoint" value="<?php echo esc_attr( $this->options['endpoint'] ); ?>" class="regular-text" required>
									<p class="description"><?php esc_html_e( 'Enter the Shopee API endpoint URL', 'woocommerce-photo-reviews' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row"><?php esc_html_e( 'Automatic Sync', 'woocommerce-photo-reviews' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wcpr_shopee_daily" <?php checked( $this->options['daily'], 1 ); ?>>
										<?php esc_html_e( 'Enable automatic sync', 'woocommerce-photo-reviews' ); ?>
									</label>
								</td>
							</tr>
							
							<tr>
								<th scope="row"><?php esc_html_e( 'Sync Frequency', 'woocommerce-photo-reviews' ); ?></th>
								<td>
									<select name="wcpr_shopee_cron_interval">
										<option value="hourly" <?php selected( $this->options['cron_interval'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'woocommerce-photo-reviews' ); ?></option>
										<option value="twice_hourly" <?php selected( $this->options['cron_interval'], 'twice_hourly' ); ?>><?php esc_html_e( 'Twice Hourly', 'woocommerce-photo-reviews' ); ?></option>
										<option value="daily" <?php selected( $this->options['cron_interval'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'woocommerce-photo-reviews' ); ?></option>
										<option value="weekly" <?php selected( $this->options['cron_interval'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'woocommerce-photo-reviews' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'How often should the automatic sync run?', 'woocommerce-photo-reviews' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row"><?php esc_html_e( 'Sync Time', 'woocommerce-photo-reviews' ); ?></th>
								<td>
									<input type="time" name="wcpr_shopee_cron_time" value="<?php echo esc_attr( $this->options['cron_time'] ); ?>" required>
									<p class="description"><?php esc_html_e( 'What time should the sync run? (24-hour format)', 'woocommerce-photo-reviews' ); ?></p>
								</td>
							</tr>
							
							<tr>
								<th scope="row"><?php esc_html_e( 'Media Download', 'woocommerce-photo-reviews' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wcpr_shopee_download" <?php checked( $this->options['download_media'], 1 ); ?>>
										<?php esc_html_e( 'Download images/videos to Media Library', 'woocommerce-photo-reviews' ); ?>
									</label>
								</td>
							</tr>
							
							<tr>
								<th scope="row"><?php esc_html_e( 'Review Status', 'woocommerce-photo-reviews' ); ?></th>
								<td>
									<select name="wcpr_shopee_status">
										<option value="1" <?php selected( $this->options['review_status'], '1' ); ?>><?php esc_html_e( 'Approved', 'woocommerce-photo-reviews' ); ?></option>
										<option value="0" <?php selected( $this->options['review_status'], '0' ); ?>><?php esc_html_e( 'Pending', 'woocommerce-photo-reviews' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Default status for imported reviews', 'woocommerce-photo-reviews' ); ?></p>
								</td>
							</tr>
							<tr>
    <th scope="row"><?php esc_html_e( 'Page Size', 'woocommerce-photo-reviews' ); ?></th>
    <td>
        <input type="number" name="wcpr_shopee_page_size" value="<?php echo esc_attr( $this->options['page_size'] ); ?>" min="1" max="100" class="small-text">
        <p class="description"><?php esc_html_e( 'Number of reviews per API call (1-100, default: 100)', 'woocommerce-photo-reviews' ); ?></p>
    </td>
</tr>

<tr>
    <th scope="row"><?php esc_html_e( 'Max Fetch Per Run', 'woocommerce-photo-reviews' ); ?></th>
    <td>
        <input type="number" name="wcpr_shopee_max_fetch" value="<?php echo esc_attr( $this->options['max_fetch'] ); ?>" min="1" max="1000" class="small-text">
        <p class="description"><?php esc_html_e( 'Maximum reviews to fetch per sync run (1-1000, default: 500)', 'woocommerce-photo-reviews' ); ?></p>
    </td>
</tr>

<tr>
    <th scope="row"><?php esc_html_e( 'Lookback Safety Window', 'woocommerce-photo-reviews' ); ?></th>
    <td>
        <input type="number" name="wcpr_shopee_lookback" value="<?php echo esc_attr( $this->options['lookback_seconds'] ); ?>" min="0" class="small-text">
        <p class="description"><?php esc_html_e( 'Seconds to look back for safety (default: 3600 = 1 hour)', 'woocommerce-photo-reviews' ); ?></p>
    </td>
</tr>
						</table>
						
						<p>
							<button class="button button-primary" type="submit" name="wcpr_shopee_save">
								<?php esc_html_e( 'Save Settings', 'woocommerce-photo-reviews' ); ?>
							</button>
						</p>
					</form>
					
					<!-- Current Cron Status -->
					<div class="wcpr-cron-status">
						<h3><?php esc_html_e( 'Current Cron Status', 'woocommerce-photo-reviews' ); ?></h3>
						<?php
						$next_scheduled = wp_next_scheduled( 'wcpr_shopee_daily_sync' );
						if ( $next_scheduled ) {
							echo '<p><strong>' . esc_html__( 'Next sync scheduled for:', 'woocommerce-photo-reviews' ) . '</strong> ' . esc_html( get_date_from_gmt( date( 'Y-m-d H:i:s', $next_scheduled ), 'F j, Y g:i a' ) ) . '</p>';
						} else {
							echo '<p><strong>' . esc_html__( 'No sync scheduled', 'woocommerce-photo-reviews' ) . '</strong></p>';
						}
						?>
					</div>
				</div>
				
				<!-- Manual Sync Tab Content -->
				<div id="manual-sync" class="wcpr-tab-content">
					<div class="wcpr-sync-controls">
						<h3><?php esc_html_e( 'Sync All Products', 'woocommerce-photo-reviews' ); ?></h3>
						<p><?php esc_html_e( 'Sync reviews for all products that have SKU mapping configured.', 'woocommerce-photo-reviews' ); ?></p>
						<button type="button" class="button button-primary wcpr-sync-all-btn">
							<?php esc_html_e( 'Sync All Products', 'woocommerce-photo-reviews' ); ?>
						</button>
						
						<div class="wcpr-sync-progress" style="display: none;">
							<div class="wcpr-progress-bar">
								<div class="wcpr-progress-fill"></div>
							</div>
							<div class="wcpr-progress-text"><?php esc_html_e( 'Syncing...', 'woocommerce-photo-reviews' ); ?></div>
						</div>
					</div>
					
					<hr>
					
					<div class="wcpr-product-sync">
						<h3><?php esc_html_e( 'Sync Individual Products', 'woocommerce-photo-reviews' ); ?></h3>
						<p><?php esc_html_e( 'Select specific products to sync reviews for.', 'woocommerce-photo-reviews' ); ?></p>
						
						<div class="wcpr-product-filters">
							<input type="text" id="wcpr-product-search" placeholder="<?php esc_attr_e( 'Search products...', 'woocommerce-photo-reviews' ); ?>" class="regular-text">
							<select id="wcpr-product-category">
								<option value=""><?php esc_html_e( 'All Categories', 'woocommerce-photo-reviews' ); ?></option>
								<?php
								$categories = get_terms( array(
									'taxonomy' => 'product_cat',
									'hide_empty' => false,
								) );
								if ( $categories && ! is_wp_error( $categories ) ) {
									foreach ( $categories as $category ) {
										echo '<option value="' . esc_attr( $category->term_id ) . '">' . esc_html( $category->name ) . '</option>';
									}
								}
								?>
							</select>
						</div>
						
						<div class="wcpr-products-table-container">
							<table class="wp-list-table widefat fixed striped wcpr-products-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Product', 'woocommerce-photo-reviews' ); ?></th>
										<th><?php esc_html_e( 'SKU', 'woocommerce-photo-reviews' ); ?></th>
										<th><?php esc_html_e( 'Category', 'woocommerce-photo-reviews' ); ?></th>
										<th><?php esc_html_e( 'Last Sync', 'woocommerce-photo-reviews' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'woocommerce-photo-reviews' ); ?></th>
									</tr>
								</thead>
								<tbody id="wcpr-products-tbody">
									<tr>
										<td colspan="5" class="wcpr-loading">
											<?php esc_html_e( 'Loading products...', 'woocommerce-photo-reviews' ); ?>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
				
				<!-- Sync History Tab Content -->
				<div id="sync-history" class="wcpr-tab-content">
					<div class="wcpr-history-controls">
						<h3><?php esc_html_e( 'Sync History', 'woocommerce-photo-reviews' ); ?></h3>
						<p><?php esc_html_e( 'View detailed history of all sync operations.', 'woocommerce-photo-reviews' ); ?></p>
						
						<div class="wcpr-history-filters">
							<select id="wcpr-history-status">
								<option value=""><?php esc_html_e( 'All Statuses', 'woocommerce-photo-reviews' ); ?></option>
								<option value="completed"><?php esc_html_e( 'Completed', 'woocommerce-photo-reviews' ); ?></option>
								<option value="running"><?php esc_html_e( 'Running', 'woocommerce-photo-reviews' ); ?></option>
								<option value="failed"><?php esc_html_e( 'Failed', 'woocommerce-photo-reviews' ); ?></option>
							</select>
							
							<select id="wcpr-history-type">
								<option value=""><?php esc_html_e( 'All Types', 'woocommerce-photo-reviews' ); ?></option>
								<option value="manual"><?php esc_html_e( 'Manual', 'woocommerce-photo-reviews' ); ?></option>
								<option value="cron"><?php esc_html_e( 'Automatic', 'woocommerce-photo-reviews' ); ?></option>
							</select>
							
							<button type="button" class="button wcpr-refresh-history">
								<?php esc_html_e( 'Refresh', 'woocommerce-photo-reviews' ); ?>
							</button>
						</div>
						
						<div class="wcpr-history-table-container">
							<table class="wp-list-table widefat fixed striped wcpr-history-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Date/Time', 'woocommerce-photo-reviews' ); ?></th>
										<th><?php esc_html_e( 'Type', 'woocommerce-photo-reviews' ); ?></th>
										<th><?php esc_html_e( 'Product', 'woocommerce-photo-reviews' ); ?></th>
										<th><?php esc_html_e( 'Reviews Imported', 'woocommerce-photo-reviews' ); ?></th>
										<th><?php esc_html_e( 'Reviews Skipped', 'woocommerce-photo-reviews' ); ?></th>
										<th><?php esc_html_e( 'Duration', 'woocommerce-photo-reviews' ); ?></th>
										<th><?php esc_html_e( 'Status', 'woocommerce-photo-reviews' ); ?></th>
										<th><?php esc_html_e( 'Details', 'woocommerce-photo-reviews' ); ?></th>
									</tr>
								</thead>
								<tbody id="wcpr-history-tbody">
									<tr>
										<td colspan="8" class="wcpr-loading">
											<?php esc_html_e( 'Loading history...', 'woocommerce-photo-reviews' ); ?>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Sync Progress Modal -->
		<div id="wcpr-sync-modal" class="wcpr-modal" style="display: none;">
			<div class="wcpr-modal-content">
				<div class="wcpr-modal-header">
					<h3><?php esc_html_e( 'Sync Progress', 'woocommerce-photo-reviews' ); ?></h3>
					<span class="wcpr-modal-close">&times;</span>
				</div>
				<div class="wcpr-modal-body">
					<div class="wcpr-sync-status">
						<div class="wcpr-sync-icon">
							<div class="wcpr-spinner"></div>
						</div>
						<div class="wcpr-sync-message">
							<h4><?php esc_html_e( 'Syncing Reviews...', 'woocommerce-photo-reviews' ); ?></h4>
							<p><?php esc_html_e( 'Please wait while we sync reviews from Shopee. This may take a few minutes.', 'woocommerce-photo-reviews' ); ?></p>
						</div>
					</div>
					<div class="wcpr-sync-progress-detail">
						<div class="wcpr-progress-bar">
							<div class="wcpr-progress-fill"></div>
						</div>
						<div class="wcpr-progress-text">0%</div>
					</div>
					<div class="wcpr-sync-log">
						<div class="wcpr-log-header">
							<strong><?php esc_html_e( 'Sync Log:', 'woocommerce-photo-reviews' ); ?></strong>
						</div>
						<div class="wcpr-log-content"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function maybe_schedule() {
		if ( ! $this->options['enabled'] || ! $this->options['daily'] ) {
			wp_clear_scheduled_hook( 'wcpr_shopee_daily_sync' );
			return;
		}
		
		$this->reschedule_cron();
	}

	public function handle_post_sync() {
		if ( ! current_user_can( $this->settings->get_setting_capability() ) ) { wp_die( 403 ); }
		if ( ! isset( $_POST['wcpr_shopee_sync_now'] ) || ! wp_verify_nonce( $_POST['wcpr_shopee_sync_now'], 'wcpr_shopee_sync_now' ) ) { wp_die( 403 ); }
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'by_sku';
		$sku  = isset( $_POST['sku'] ) ? sanitize_text_field( $_POST['sku'] ) : '';
		if ( $mode === 'all' ) {
			$this->cron_sync_all();
		} elseif ( $sku ) {
			$this->sync_one_sku( $sku );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wcpr_shopee_sync' ) );
		exit;
	}

	public function ajax_sync() {
		if ( ! current_user_can( $this->settings->get_setting_capability() ) ) { wp_send_json_error( array( 'message' => 'Permission denied' ) ); }
		check_ajax_referer( 'wcpr_shopee_sync_ajax', 'nonce' );
		$sku = isset( $_POST['sku'] ) ? sanitize_text_field( $_POST['sku'] ) : '';
		if ( ! $sku ) { wp_send_json_error( array( 'message' => 'Invalid SKU' ) ); }
		$count = $this->sync_one_sku( $sku );
		wp_send_json_success( array( 'imported' => $count ) );
	}

	public function cron_sync_all() {
		if ( ! $this->options['enabled'] ) { 
			return array( 'imported' => 0, 'skipped' => 0, 'errors' => 'Integration disabled', 'duration' => 0, 'success' => false ); 
		}
		
		// Record sync start
		$sync_id = $this->record_sync_start( 'cron' );
		
		$search_product_by = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::search_product_by();
		$args = array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'private', 'draft' ),
			'fields'         => 'ids',
			'posts_per_page' => 200,
		);
		
		$total_imported = 0;
		$total_skipped = 0;
		$errors = array();
		$start_time = time();
		
		$offset = 0;
		do {
			$q = new WP_Query( $args + array( 'offset' => $offset ) );
			if ( ! $q->have_posts() ) { break; }
			
			foreach ( $q->posts as $product_id ) {
				$sku = get_post_meta( $product_id, $search_product_by, true );
				if ( $sku ) {
					try {
						$result = $this->sync_one_sku( $sku, $product_id );
						$total_imported += $result['imported'];
						$total_skipped += $result['skipped'];
					} catch ( Exception $e ) {
						$errors[] = "Product ID {$product_id}: " . $e->getMessage();
					}
				}
			}
			$offset += $args['posts_per_page'];
			wp_reset_postdata();
		} while ( true );
		
		$duration = time() - $start_time;
		
		// Record sync completion
		$result = array(
			'imported' => $total_imported,
			'skipped'  => $total_skipped,
			'errors'   => implode( "\n", $errors ),
			'duration' => $duration,
			'success'  => empty( $errors ),
		);
		
		$this->record_sync_completion( $sync_id, $result );
		
		// Return the result for AJAX calls
		return $result;
	}

	protected function sync_one_sku( $sku, $sync_id = null ) {
		if ( empty( $sku ) ) {
			return array( 'imported' => 0, 'skipped' => 0, 'errors' => 'Invalid SKU provided', 'duration' => 0, 'success' => false );
		}
		
		$start_time = time();
		$product_ids = $this->get_product_ids( $sku );
		
		if ( ! count( $product_ids ) ) { 
			return array( 'imported' => 0, 'skipped' => 0, 'errors' => 'No products found with this SKU', 'duration' => 0, 'success' => false );
		}
		
		try {
			$list = $this->fetch_reviews( $sku, $product_ids[0] );
		} catch ( Exception $e ) {
			return array( 'imported' => 0, 'skipped' => 0, 'errors' => 'API Error: ' . $e->getMessage(), 'duration' => 0, 'success' => false );
		}
		
		if ( ! is_array( $list ) || ! count( $list ) ) { 
			return array( 'imported' => 0, 'skipped' => 0, 'errors' => 'No reviews found', 'duration' => 0, 'success' => true );
		}
		
		$count = 0;
		$skipped = 0;
		$errors = array();
		
		foreach ( $product_ids as $product_id ) {
			foreach ( $list as $review ) {
				try {
					$comment_id = $this->insert_review( $product_id, $review );
					if ( $comment_id ) { 
						$count++; 
					} else {
						$skipped++;
					}
				} catch ( Exception $e ) {
					$errors[] = "Review {$review['comment_id']}: " . $e->getMessage();
					$skipped++;
				}
			}
		}
		
		$duration = time() - $start_time;
		
		return array(
			'imported' => $count,
			'skipped'  => $skipped,
			'errors'   => implode( "\n", $errors ),
			'duration' => $duration,
			'success'  => empty( $errors ),
		);
	}

	protected function fetch_reviews( $item_id, $product_id = 0 ) {
		if ( empty( $this->options['endpoint'] ) ) {
			throw new Exception( 'API endpoint not configured' );
		}
		
		// Get last sync timestamp for this product
		$last_sync_ts = 0;
		if ( $product_id ) {
			$last_sync_ts = get_post_meta( $product_id, '_shopee_last_review_ts', true );
			$last_sync_ts = intval( $last_sync_ts );
		}
		
		// Apply lookback safety window
		$since_ts = max( 0, $last_sync_ts - $this->options['lookback_seconds'] );
		
		$all_reviews = array();
		$cursor = '';
		$total_fetched = 0;
		
		do {
			// Build API URL with parameters
			$url = add_query_arg( array(
				'item_id' => rawurlencode( $item_id ),
				'since_ts' => $since_ts,
				'page_size' => $this->options['page_size'],
				'max_fetch' => $this->options['max_fetch'],
				'cursor' => $cursor,
			), $this->options['endpoint'] );
			
			$res = wp_remote_get( $url, array( 'timeout' => 30 ) );
			
			if ( is_wp_error( $res ) ) { 
				throw new Exception( 'HTTP request failed: ' . $res->get_error_message() );
			}
			
			$code = wp_remote_retrieve_response_code( $res );
			if ( $code !== 200 ) { 
				throw new Exception( "HTTP request failed with status code: {$code}" );
			}
			
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new Exception( 'Invalid JSON response from API' );
			}
			
			if ( ! empty( $body['error'] ) ) {
				throw new Exception( 'API Error: ' . $body['message'] );
			}
			
			$response = $body['response'] ?? array();
			$reviews = $response['items'] ?? array();
			$resume_cursor = $response['resume_cursor'] ?? '';
			$latest_ts = $response['latest_ts'] ?? 0;
			
			if ( ! empty( $reviews ) && is_array( $reviews ) ) {
				$all_reviews = array_merge( $all_reviews, $reviews );
				$total_fetched += count( $reviews );
			}
			
			// Continue with next page if resume_cursor is provided
			$cursor = $resume_cursor;
			
			// Safety check to prevent infinite loops
			if ( $total_fetched >= $this->options['max_fetch'] * 2 ) {
				break;
			}
			
		} while ( ! empty( $resume_cursor ) && $total_fetched < $this->options['max_fetch'] );
		
		// Store the latest timestamp for this product
		if ( $product_id && $latest_ts > $last_sync_ts ) {
			update_post_meta( $product_id, '_shopee_last_review_ts', $latest_ts );
		}
		
		return $all_reviews;
	}

	protected function insert_review( $product_id, $r ) {
		$review_id   = isset( $r['comment_id'] ) ? strval( $r['comment_id'] ) : '';
		if ( ! $review_id ) { return 0; }
		
		// Skip duplicates - check by comment_id from Shopee
		$existing_review = get_comments( array(
			'post_id'    => $product_id,
			'status'     => array( 0, 1, 'spam' ),
			'meta_key'   => 'id_import_reviews_from_shopee',
			'meta_value' => $review_id,
			'number'     => 1,
			'count'      => true,
		) );
		
		if ( $existing_review > 0 ) {
			return 0; // Skip duplicate
		}
		
		$rating = isset( $r['rating_star'] ) ? intval( $r['rating_star'] ) : 0;
		if ( $rating < 1 || $rating > 5 ) { return 0; }
		
		$author  = isset( $r['buyer_username'] ) ? sanitize_text_field( $r['buyer_username'] ) : '';
		$content = isset( $r['comment'] ) ? wp_kses_post( $r['comment'] ) : '';
		$ts      = isset( $r['create_time'] ) ? intval( $r['create_time'] ) : current_time( 'timestamp' );

		$comment_id = wp_insert_comment( array(
			'comment_post_ID'      => $product_id,
			'comment_author'       => $author,
			'comment_author_email' => '',
			'comment_author_url'   => '',
			'comment_content'      => $content,
			'comment_type'         => 'review',
			'comment_parent'       => 0,
			'user_id'              => 0,
			'comment_author_IP'    => '',
			'comment_agent'        => 'wcpr-shopee',
			'comment_date'         => date( 'Y-m-d H:i:s', $ts ),
			'comment_date_gmt'     => get_gmt_from_date( date( 'Y-m-d H:i:s', $ts ) ),
			'comment_approved'     => $this->options['review_status'] === '1' ? 1 : 0,
		) );

		if ( ! $comment_id ) { return 0; }

		update_comment_meta( $comment_id, 'rating', $rating );
		update_comment_meta( $comment_id, 'wcpr_vote_up_count', 0 );
		update_comment_meta( $comment_id, 'wcpr_vote_down_count', 0 );
		update_comment_meta( $comment_id, 'id_import_reviews_from_shopee', $review_id );
		update_comment_meta( $comment_id, 'wcpr_source', 'shopee' );

		$images = array();
		if ( ! empty( $r['media']['image_url_list'] ) && is_array( $r['media']['image_url_list'] ) ) {
			$images = array_merge( $images, array_map( 'esc_url_raw', $r['media']['image_url_list'] ) );
		}
		if ( ! empty( $r['media']['video_url_list'] ) && is_array( $r['media']['video_url_list'] ) ) {
			$images = array_merge( $images, array_map( 'esc_url_raw', $r['media']['video_url_list'] ) );
		}
		if ( count( $images ) ) {
			update_comment_meta( $comment_id, 'reviews-images', $images );
			if ( $this->options['download_media'] ) {
				VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Import_Csv::$background_process->push_to_queue( array( 'comment_id' => $comment_id ) );
				VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Import_Csv::$background_process->save()->dispatch();
			}
		}
		if ( $this->options['review_status'] === '1' ) {
			VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Import_Csv::update_product_reviews_and_rating( $product_id, $rating );
		}
		WC_Comments::clear_transients( $product_id );
		return $comment_id;
	}

	protected function get_product_ids( $product_sku ) {
		$search_product_by = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::search_product_by();
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 50,
			'meta_key'       => $search_product_by,
			'meta_value'     => $product_sku,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'fields'         => 'ids',
		);
		$q = new WP_Query( $args );
		return $q->posts;
	}

	/**
	 * Create sync history table if it doesn't exist
	 */
	public function maybe_create_table() {
		global $wpdb;
		
		// Check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" );
		if ( $table_exists ) {
			return;
		}
		
		// Check if we can create tables
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			sync_type varchar(50) NOT NULL,
			product_sku varchar(255),
			product_name varchar(255),
			reviews_imported int(11) DEFAULT 0,
			reviews_skipped int(11) DEFAULT 0,
			errors text,
			start_time datetime DEFAULT CURRENT_TIMESTAMP,
			end_time datetime NULL,
			duration_seconds int(11) DEFAULT 0,
			status varchar(50) DEFAULT 'running',
			PRIMARY KEY (id),
			KEY sync_type (sync_type),
			KEY status (status),
			KEY start_time (start_time)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Add custom cron intervals
	 */
	public function add_cron_intervals( $schedules ) {
		$schedules['twice_hourly'] = array(
			'interval' => 1800,
			'display'  => __( 'Twice Hourly', 'woocommerce-photo-reviews' )
		);
		$schedules['weekly'] = array(
			'interval' => 604800,
			'display'  => __( 'Weekly', 'woocommerce-photo-reviews' )
		);
		return $schedules;
	}

	/**
	 * Reschedule cron job
	 */
	private function reschedule_cron() {
		wp_clear_scheduled_hook( 'wcpr_shopee_daily_sync' );
		
		if ( $this->options['enabled'] && $this->options['daily'] ) {
			$time_parts = explode( ':', $this->options['cron_time'] );
			$hour = intval( $time_parts[0] );
			$minute = intval( $time_parts[1] );
			
			$next_run = strtotime( "today {$hour}:{$minute}:00" );
			if ( $next_run <= time() ) {
				$next_run = strtotime( "tomorrow {$hour}:{$minute}:00" );
			}
			
			wp_schedule_event( $next_run, $this->options['cron_interval'], 'wcpr_shopee_daily_sync' );
		}
	}

	/**
	 * AJAX: Get products for manual sync
	 */
	public function ajax_get_products() {
		if ( ! check_ajax_referer( 'wcpr_shopee_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}
		
		if ( ! current_user_can( $this->settings->get_setting_capability() ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$category = isset( $_POST['category'] ) ? intval( $_POST['category'] ) : 0;
		$page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$per_page = 20;
		
		$args = array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'meta_query'     => array(
				array(
					'key'     => VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::search_product_by(),
					'compare' => 'EXISTS',
				)
			)
		);
		
		if ( $search ) {
			$args['s'] = $search;
		}
		
		if ( $category ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $category,
				)
			);
		}
		
		$query = new WP_Query( $args );
		$products = array();
		
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $product ) {
				$sku = get_post_meta( $product->ID, VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::search_product_by(), true );
				$categories = get_the_terms( $product->ID, 'product_cat' );
				$category_names = array();
				
				if ( $categories && ! is_wp_error( $categories ) ) {
					foreach ( $categories as $cat ) {
						$category_names[] = $cat->name;
					}
				}
				
				// Get last sync info
				global $wpdb;
				$last_sync = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$this->table_name} WHERE product_sku = %s ORDER BY start_time DESC LIMIT 1",
					$sku
				) );
				
				$products[] = array(
					'id'          => $product->ID,
					'name'        => $product->post_title,
					'sku'         => $sku,
					'categories'  => implode( ', ', $category_names ),
					'last_sync'   => $this->get_product_sync_stats( $product->ID )['last_sync_human'],
					'edit_url'    => get_edit_post_link( $product->ID ),
					'sync_stats'  => $this->get_product_sync_stats( $product->ID ),
				);
			}
		}
		
		wp_send_json_success( array(
			'products' => $products,
			'total'    => $query->found_posts,
			'pages'    => ceil( $query->found_posts / $per_page ),
			'current'  => $page,
		) );
	}

	/**
	 * AJAX: Get sync history
	 */
	public function ajax_get_history() {
		check_ajax_referer( 'wcpr_shopee_ajax_nonce', 'nonce' );
		
		if ( ! current_user_can( $this->settings->get_setting_capability() ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
		$page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$per_page = 20;
		
		global $wpdb;
		
		$where_conditions = array();
		$where_values = array();
		
		if ( $status ) {
			$where_conditions[] = 'status = %s';
			$where_values[] = $status;
		}
		
		if ( $type ) {
			$where_conditions[] = 'sync_type = %s';
			$where_values[] = $type;
		}
		
		$where_clause = '';
		if ( ! empty( $where_conditions ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
		}
		
		$offset = ( $page - 1 ) * $per_page;
		
		// Get total count
		$count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_values );
		}
		$total = $wpdb->get_var( $count_query );
		
		// Get records
		$query = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY start_time DESC LIMIT %d OFFSET %d";
		$where_values[] = $per_page;
		$where_values[] = $offset;
		$records = $wpdb->get_results( $wpdb->prepare( $query, $where_values ) );
		
		$history = array();
		foreach ( $records as $record ) {
			$history[] = array(
				'id'              => $record->id,
				'start_time'      => $record->start_time,
				'sync_type'       => $record->sync_type,
				'product_sku'     => $record->product_sku,
				'product_name'    => $record->product_name,
				'reviews_imported' => $record->reviews_imported,
				'reviews_skipped' => $record->reviews_skipped,
				'duration'        => $this->format_duration( $record->duration_seconds ),
				'status'          => $record->status,
				'errors'          => ! empty( $record->errors ) ? wp_kses_post( $record->errors ) : '',
			);
		}
		
		wp_send_json_success( array(
			'history' => $history,
			'total'   => $total,
			'pages'   => ceil( $total / $per_page ),
			'current' => $page,
		) );
	}

	/**
	 * AJAX: Sync individual product
	 */
	public function ajax_sync_product() {
		check_ajax_referer( 'wcpr_shopee_ajax_nonce', 'nonce' );
		
		if ( ! current_user_can( $this->settings->get_setting_capability() ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => 'Invalid product ID' ) );
		}
		
		$product = get_post( $product_id );
		if ( ! $product || $product->post_type !== 'product' ) {
			wp_send_json_error( array( 'message' => 'Product not found' ) );
		}
		
		$sku = get_post_meta( $product_id, VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::search_product_by(), true );
		if ( ! $sku ) {
			wp_send_json_error( array( 'message' => 'Product has no SKU mapping' ) );
		}
		
		// Record sync start
		$sync_id = $this->record_sync_start( 'manual', $sku, $product->post_title );
		
		// Perform sync
		$result = $this->sync_one_sku( $sku, $product_id );
		
		// Record sync completion
		$this->record_sync_completion( $sync_id, $result );
		
		wp_send_json_success( array(
			'message' => sprintf( __( 'Sync completed. %d reviews imported, %d skipped.', 'woocommerce-photo-reviews' ), $result['imported'], $result['skipped'] ),
			'result'  => $result,
		) );
	}

	/**
	 * AJAX: Sync all products
	 */
	public function ajax_sync_all() {
		check_ajax_referer( 'wcpr_shopee_ajax_nonce', 'nonce' );
		
		if ( ! current_user_can( $this->settings->get_setting_capability() ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		// Record sync start
		$sync_id = $this->record_sync_start( 'manual' );
		
		// Perform sync for all products
		$result = $this->cron_sync_all();
		
		// Record sync completion
		$this->record_sync_completion( $sync_id, $result );
		
		wp_send_json_success( array(
			'message' => sprintf( __( 'Sync completed. %d reviews imported, %d skipped.', 'woocommerce-photo-reviews' ), $result['imported'], $result['skipped'] ),
			'result'  => $result,
		) );
	}

	/**
	 * Record sync start
	 */
	private function record_sync_start( $type, $sku = '', $product_name = '' ) {
		global $wpdb;
		
		$result = $wpdb->insert(
			$this->table_name,
			array(
				'sync_type'      => $type,
				'product_sku'    => $sku,
				'product_name'   => $product_name,
				'start_time'     => current_time( 'mysql' ),
				'status'         => 'running',
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
		
		if ( $result === false ) {
			error_log( 'Failed to insert sync record: ' . $wpdb->last_error );
			return 0;
		}
		
		return $wpdb->insert_id;
	}

	/**
	 * Record sync completion
	 */
	private function record_sync_completion( $sync_id, $result ) {
		global $wpdb;
		
		$wpdb->update(
			$this->table_name,
			array(
				'reviews_imported' => $result['imported'],
				'reviews_skipped'  => $result['skipped'],
				'errors'           => $result['errors'],
				'end_time'         => current_time( 'mysql' ),
				'duration_seconds' => $result['duration'],
				'status'           => $result['success'] ? 'completed' : 'failed',
			),
			array( 'id' => $sync_id ),
			array( '%d', '%d', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Format duration in human readable format
	 */
	private function format_duration( $seconds ) {
		if ( $seconds < 60 ) {
			return $seconds . 's';
		} elseif ( $seconds < 3600 ) {
			return floor( $seconds / 60 ) . 'm ' . ( $seconds % 60 ) . 's';
		} else {
			return floor( $seconds / 3600 ) . 'h ' . floor( ( $seconds % 3600 ) / 60 ) . 'm';
		}
	}

	/**
	 * Get last sync timestamp for a product
	 */
	private function get_last_sync_timestamp( $product_id ) {
		$timestamp = get_post_meta( $product_id, '_shopee_last_review_ts', true );
		return intval( $timestamp );
	}

	/**
	 * Update last sync timestamp for a product
	 */
	private function update_last_sync_timestamp( $product_id, $timestamp ) {
		update_post_meta( $product_id, '_shopee_last_review_ts', $timestamp );
	}

	/**
	 * Get sync statistics for a product
	 */
	public function get_product_sync_stats( $product_id ) {
		$last_sync_ts = $this->get_last_sync_timestamp( $product_id );
		$last_sync_date = $last_sync_ts > 0 ? date( 'Y-m-d H:i:s', $last_sync_ts ) : 'Never';
		
		// Count reviews for this product
		$review_count = get_comments( array(
			'post_id' => $product_id,
			'type' => 'review',
			'status' => array( 1, 0 ),
			'count' => true,
			'meta_query' => array(
				array(
					'key' => 'wcpr_source',
					'value' => 'shopee',
					'compare' => '='
				)
			)
		) );
		
		return array(
			'last_sync_timestamp' => $last_sync_ts,
			'last_sync_date' => $last_sync_date,
			'total_reviews' => $review_count,
			'last_sync_human' => $last_sync_ts > 0 ? human_time_diff( $last_sync_ts ) . ' ago' : 'Never'
		);
	}
}