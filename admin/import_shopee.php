<?php
// woocommerce-photo-reviews/admin/import_shopee.php
/**
 * Class VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Import_Shopee
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class VI_WOOCOMMERCE_PHOTO_REVIEWS_Admin_Import_Shopee {
	protected $settings;
	protected $options;

	public function __construct() {
		$this->settings = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::get_instance();
		$this->options  = wp_parse_args( get_option( 'wcpr_shopee_options', array() ), array(
			'enabled'         => 1,
			'daily'           => 1,
			'download_media'  => 1,
			'review_status'   => '1', // 1=approved, 0=pending
		) );

		add_action( 'admin_menu', array( $this, 'add_menu' ), 21 );
		add_action( 'admin_init', array( $this, 'maybe_schedule' ) );
		add_action( 'wp_ajax_wcpr_shopee_sync', array( $this, 'ajax_sync' ) );
		add_action( 'admin_post_wcpr_shopee_sync', array( $this, 'handle_post_sync' ) );
		add_action( 'wcpr_shopee_daily_sync', array( $this, 'cron_sync_all' ) );
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
		if ( isset( $_POST['wcpr_shopee_save'] ) && check_admin_referer( 'wcpr_shopee_sync_nonce', 'wcpr_shopee_sync_nonce' ) ) {
			$this->options['enabled']        = isset( $_POST['wcpr_shopee_enabled'] ) ? 1 : 0;
			$this->options['daily']          = isset( $_POST['wcpr_shopee_daily'] ) ? 1 : 0;
			$this->options['download_media'] = isset( $_POST['wcpr_shopee_download'] ) ? 1 : 0;
			$this->options['review_status']  = isset( $_POST['wcpr_shopee_status'] ) && $_POST['wcpr_shopee_status'] === '0' ? '0' : '1';
			update_option( 'wcpr_shopee_options', $this->options );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Shopee Reviews Sync', 'woocommerce-photo-reviews' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'wcpr_shopee_sync_nonce', 'wcpr_shopee_sync_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'woocommerce-photo-reviews' ); ?></th>
						<td><label><input type="checkbox" name="wcpr_shopee_enabled" <?php checked( $this->options['enabled'], 1 ); ?>> <?php esc_html_e( 'Enable Shopee integration', 'woocommerce-photo-reviews' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Daily Sync', 'woocommerce-photo-reviews' ); ?></th>
						<td><label><input type="checkbox" name="wcpr_shopee_daily" <?php checked( $this->options['daily'], 1 ); ?>> <?php esc_html_e( 'Run daily automatic sync', 'woocommerce-photo-reviews' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Media', 'woocommerce-photo-reviews' ); ?></th>
						<td><label><input type="checkbox" name="wcpr_shopee_download" <?php checked( $this->options['download_media'], 1 ); ?>> <?php esc_html_e( 'Download images/videos to Media Library', 'woocommerce-photo-reviews' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Review Status', 'woocommerce-photo-reviews' ); ?></th>
						<td>
							<select name="wcpr_shopee_status">
								<option value="1" <?php selected( $this->options['review_status'], '1' ); ?>><?php esc_html_e( 'Approved', 'woocommerce-photo-reviews' ); ?></option>
								<option value="0" <?php selected( $this->options['review_status'], '0' ); ?>><?php esc_html_e( 'Pending', 'woocommerce-photo-reviews' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
				<p><button class="button button-primary" type="submit" name="wcpr_shopee_save"><?php esc_html_e( 'Save', 'woocommerce-photo-reviews' ); ?></button></p>
			</form>
			<hr>
			<h2><?php esc_html_e( 'Manual Sync', 'woocommerce-photo-reviews' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wcpr_shopee_sync">
				<?php wp_nonce_field( 'wcpr_shopee_sync_now', 'wcpr_shopee_sync_now' ); ?>
				<p>
					<label><?php esc_html_e( 'SKU / item_id', 'woocommerce-photo-reviews' ); ?></label>
					<input type="text" name="sku" value="" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. 26512939660', 'woocommerce-photo-reviews' ); ?>">
					<button class="button button-secondary" type="submit" name="mode" value="by_sku"><?php esc_html_e( 'Sync This Product', 'woocommerce-photo-reviews' ); ?></button>
					<button class="button" type="submit" name="mode" value="all"><?php esc_html_e( 'Sync All Products', 'woocommerce-photo-reviews' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	public function maybe_schedule() {
		if ( ! $this->options['enabled'] || ! $this->options['daily'] ) {
			wp_clear_scheduled_hook( 'wcpr_shopee_daily_sync' );
			return;
		}
		if ( ! wp_next_scheduled( 'wcpr_shopee_daily_sync' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wcpr_shopee_daily_sync' );
		}
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
		if ( ! $this->options['enabled'] ) { return; }
		$search_product_by = VI_WOOCOMMERCE_PHOTO_REVIEWS_DATA::search_product_by();
		$args = array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'private', 'draft' ),
			'fields'         => 'ids',
			'posts_per_page' => 200,
		);
		$offset = 0;
		do {
			$q = new WP_Query( $args + array( 'offset' => $offset ) );
			if ( ! $q->have_posts() ) { break; }
			foreach ( $q->posts as $product_id ) {
				$sku = get_post_meta( $product_id, $search_product_by, true );
				if ( $sku ) {
					$this->sync_one_sku( $sku );
				}
			}
			$offset += $args['posts_per_page'];
			wp_reset_postdata();
		} while ( true );
	}

	protected function sync_one_sku( $sku ) {
		$product_ids = $this->get_product_ids( $sku );
		if ( ! count( $product_ids ) ) { return 0; }
		$list = $this->fetch_reviews( $sku );
		if ( ! is_array( $list ) || ! count( $list ) ) { return 0; }
		$count = 0;
		foreach ( $product_ids as $product_id ) {
			foreach ( $list as $review ) {
				$comment_id = $this->insert_review( $product_id, $review );
				if ( $comment_id ) { $count++; }
			}
		}
		return $count;
	}

	protected function fetch_reviews( $item_id ) {
		$url = 'https://asia-southeast1-starlit-array-328711.cloudfunctions.net/shopeeboy/comments?item_id=' . rawurlencode( $item_id );
		$res = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $res ) ) { return array(); }
		$code = wp_remote_retrieve_response_code( $res );
		if ( $code !== 200 ) { return array(); }
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['response']['item_comment_list'] ) || ! is_array( $body['response']['item_comment_list'] ) ) { return array(); }
		return $body['response']['item_comment_list'];
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
}
