<?php
/**
 * Shopee Style Template for WooCommerce Photo Reviews
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( empty( $my_comments ) || ! is_array( $my_comments ) || empty( $settings ) ) {
	return;
}

$prefix       = $is_shortcode ? 'shortcode_' : '';
$prefix_class = $is_shortcode ? 'shortcode-' : '';
global $product;
$return_product = $product;

// Use passed rating data if available, otherwise calculate it
$average_rating = isset( $average_rating ) ? $average_rating : 0;
$count_reviews = isset( $count_reviews ) ? $count_reviews : 0;

// If data wasn't passed, calculate it
if ( $average_rating == 0 && $count_reviews == 0 ) {
	$product_id = $product ? $product->get_id() : 0;
	if ($product_id) {
		$args = array(
			'post_id'  => $product_id,
			'count'    => true,
			'meta_key' => 'rating',
			'status'   => 'approve'
		);
		
		// Get review count
		$count_reviews = get_comments($args);
		
		// Get average rating
		$average_rating = $product->get_average_rating();
	}
}

// Start the reviews container
echo '<div class="wcpr-shopee-reviews-container">';

// Add overall rating at the top (between filters and comment list)
if ($count_reviews > 0) {
	?>
	<div class="wcpr-shopee-overall-rating">
		<div class="wcpr-shopee-rating-summary">
			<div class="wcpr-shopee-rating-score">
				<span class="wcpr-shopee-rating-number"><?php echo esc_html( number_format( $average_rating, 1 ) ); ?></span>
				<span class="wcpr-shopee-rating-out-of"><?php esc_html_e( 'out of 5', 'woocommerce-photo-reviews' ); ?></span>
			</div>
			<div class="wcpr-shopee-rating-stars">
				<?php
				$rating = $average_rating;
				for ( $i = 1; $i <= 5; $i++ ) {
					// Fix: Use proper star filling logic
					if ( $i <= round($rating) ) {
						echo '<svg viewBox="0 0 15 15" class="wcpr-shopee-star wcpr-shopee-star-filled">';
						echo '<polygon points="7.5 .8 9.7 5.4 14.5 5.9 10.7 9.1 11.8 14.2 7.5 11.6 3.2 14.2 4.3 9.1 .5 5.9 5.3 5.4" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polygon>';
						echo '</svg>';
					} else {
						echo '<svg viewBox="0 0 15 15" class="wcpr-shopee-star wcpr-shopee-star-empty">';
						echo '<polygon fill="none" points="7.5 .8 9.7 5.4 14.5 5.9 10.7 9.1 11.8 14.2 7.5 11.6 3.2 14.2 4.3 9.1 .5 5.9 5.3 5.4" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polygon>';
						echo '</svg>';
					}
				}
				?>
			</div>
			<div class="wcpr-review-container">
				<div class="wcpr-shopee-review-count">
					<?php printf( esc_html( _n( '%s review', '%s reviews', $count_reviews, 'woocommerce-photo-reviews' ) ), number_format_i18n( $count_reviews ) ); ?>
				</div>
				<div class="wcpr-info-icon-wrapper" title="100% Authentic Reviews from our Shopee Store and Website!">
					<svg fill="#ee4d2d" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="15px" height="15px" viewBox="0 0 416.979 416.979" xml:space="preserve" class="wcpr-info-icon">
						<g>
							<path d="M356.004,61.156c-81.37-81.47-213.377-81.551-294.848-0.182c-81.47,81.371-81.552,213.379-0.181,294.85
								c81.369,81.47,213.378,81.551,294.849,0.181C437.293,274.636,437.375,142.626,356.004,61.156z M237.6,340.786
								c0,3.217-2.607,5.822-5.822,5.822h-46.576c-3.215,0-5.822-2.605-5.822-5.822V167.885c0-3.217,2.607-5.822,5.822-5.822h46.576
								c3.215,0,5.822,2.604,5.822,5.822V340.786z M208.49,137.901c-18.618,0-33.766-15.146-33.766-33.765
								c0-18.617,15.147-33.766,33.766-33.766c18.619,0,33.766,15.148,33.766,33.766C242.256,122.755,227.107,137.901,208.49,137.901z"></path>
						</g>
					</svg>
				</div>
			</div>
		</div>
	</div>
	<?php
}

// Loop through comments (let the original plugin handle pagination)
foreach ( $my_comments as $comment ) {
	$comment_id = $comment->comment_ID;
	$rating     = get_comment_meta( $comment_id, 'rating', true );
	$verified   = get_comment_meta( $comment_id, 'verified', true );
	$images     = get_comment_meta( $comment_id, 'reviews-images', true );
	$videos     = get_comment_meta( $comment_id, 'reviews-videos', true );
	$source     = get_comment_meta( $comment_id, 'id_import_reviews_from_shopee', true );
	
	// Check if this is a Shopee review
	$is_shopee_review = ! empty( $source );
	
	?>
	<div class="wcpr-shopee-review-item" data-comment-id="<?php echo esc_attr( $comment_id ); ?>">
		<div class="wcpr-shopee-review-header">
			<div class="wcpr-shopee-avatar">
				<div class="shopee-avatar__placeholder">
					<svg enable-background="new 0 0 15 15" viewBox="0 0 15 15" x="0" y="0" class="shopee-svg-icon icon-headshot">
						<g><circle cx="7.5" cy="4.5" fill="none" r="3.8" stroke-miterlimit="10"></circle><path d="m1.5 14.2c0-3.3 2.7-6 6-6s6 2.7 6 6" fill="none" stroke-linecap="round" stroke-miterlimit="10"></path></g>
					</svg>
				</div>
			</div>
			<div class="wcpr-shopee-review-content">
				<div class="wcpr-shopee-review-meta">
					<span class="wcpr-shopee-author"><?php echo esc_html( $comment->comment_author ); ?></span>
					<?php if ( $rating ) : ?>
						<div class="wcpr-shopee-rating">
							<?php
							for ( $i = 1; $i <= 5; $i++ ) {
								// Fix: Use consistent star filling logic for individual comments too
								if ( $i <= round($rating) ) {
									echo '<svg viewBox="0 0 15 15" class="wcpr-shopee-star wcpr-shopee-star-filled">';
									echo '<polygon points="7.5 .8 9.7 5.4 14.5 5.9 10.7 9.1 11.8 14.2 7.5 11.6 3.2 14.2 4.3 9.1 .5 5.9 5.3 5.4" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polygon>';
									echo '</svg>';
								} else {
									echo '<svg viewBox="0 0 15 15" class="wcpr-shopee-star wcpr-shopee-star-empty">';
									echo '<polygon fill="none" points="7.5 .8 9.7 5.4 14.5 5.9 10.7 9.1 11.8 14.2 7.5 11.6 3.2 14.2 4.3 9.1 .5 5.9 5.3 5.4" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"></polygon>';
									echo '</svg>';
								}
							}
							?>
						</div>
					<?php endif; ?>
					<span class="wcpr-shopee-date"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $comment->comment_date ) ) ); ?></span>
					<?php if ( $is_shopee_review ) : ?>
						<div class="wcpr-shopee-badge">
							Shopee
						</div>
					<?php endif; ?>
				</div>
				
				<?php if ( $comment->comment_content ) : ?>
					<div class="wcpr-shopee-review-text"><?php echo wp_kses_post( $comment->comment_content ); ?></div>
				<?php endif; ?>
				
				<?php if ( $images || $videos ) : ?>
					<div class="wcpr-shopee-media-section">
						<div class="wcpr-shopee-media-grid">
							<?php
							// Enhanced media handling with proper validation and fallbacks
							$all_media = array();
							
							// Process images
							if ( $images && is_array( $images ) ) {
								foreach ( $images as $image_item ) {
									if ( ! empty( $image_item ) ) {
										$all_media[] = array(
											'url' => $image_item,
											'type' => 'image',
											'id' => $image_item
										);
									}
								}
							}
							
							// Process videos
							if ( $videos && is_array( $videos ) ) {
								foreach ( $videos as $video_item ) {
									if ( ! empty( $video_item ) ) {
										$all_media[] = array(
											'url' => $video_item,
											'type' => 'video',
											'id' => $video_item
										);
									}
								}
							}
							
							// Display all media with proper validation
							if ( ! empty( $all_media ) ) {
								foreach ( $all_media as $media ) {
									$media_url = $media['url'];
									$media_type = $media['type'];
									$media_id = $media['id'];
									
									// Validate URL and determine if it's accessible
									$is_valid_url = function_exists( 'villatheme_is_url' ) ? villatheme_is_url( $media_url ) : filter_var( $media_url, FILTER_VALIDATE_URL );
									
									if ( $is_valid_url && $media_url ) {
										// Determine if it's actually a video based on file extension
										$file_extension = strtolower( pathinfo( $media_url, PATHINFO_EXTENSION ) );
										$is_video = in_array( $file_extension, array( 'mp4', 'webm', 'mov', 'avi', 'mkv' ) );
										
										// Override type if file extension indicates video
										if ( $is_video ) {
											$media_type = 'video';
										}
										
										if ( $media_type === 'video' ) {
											echo '<div class="wcpr-shopee-media-item wcpr-shopee-media-video" data-media="' . esc_url( $media_url ) . '" data-type="video" data-media-id="' . esc_attr( $media_id ) . '">';
											echo '<div class="wcpr-shopee-video-thumbnail">';
											echo '<svg viewBox="0 0 24 24" class="wcpr-shopee-play-icon">';
											echo '<path d="M8 5v14l11-7z" fill="currentColor"/>';
											echo '</svg>';
											echo '</div>';
											echo '</div>';
										} else {
											echo '<div class="wcpr-shopee-media-item wcpr-shopee-media-image" data-media="' . esc_url( $media_url ) . '" data-type="image" data-media-id="' . esc_attr( $media_id ) . '">';
											echo '<img src="' . esc_url( $media_url ) . '" alt="Review Image" loading="lazy" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';" onload="this.nextElementSibling.style.display=\'none\';">';
											echo '<div class="wcpr-shopee-image-fallback" style="display: none;">';
											echo '<svg viewBox="0 0 24 24" class="wcpr-shopee-image-error-icon">';
											echo '<path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM9 13c0-1.66 1.34-3 3-3s3 1.34 3 3-1.34 3-3 3-3-1.34-3-3z" fill="currentColor"/>';
											echo '</svg>';
											echo '<span>Image unavailable</span>';
											echo '</div>';
											echo '</div>';
										}
									}
								}
							}
							?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

echo '</div>';

// Add lightbox modal (single instance for all reviews)
echo '<div class="wcpr-shopee-lightbox" id="wcpr-shopee-lightbox">';
echo '<div class="wcpr-shopee-lightbox-content">';
echo '<span class="wcpr-shopee-lightbox-close">&times;</span>';
echo '<div class="wcpr-shopee-lightbox-media">';
echo '<div class="wcpr-shopee-lightbox-prev">&#10094;</div>';
echo '<div class="wcpr-shopee-lightbox-next">&#10095;</div>';
echo '<div class="wcpr-shopee-lightbox-container">';
echo '<!-- Media content will be loaded here -->';
echo '</div>';
echo '</div>';
echo '<div class="wcpr-shopee-lightbox-caption"></div>';
echo '</div>';
echo '</div>';

$product = $return_product;
?>
