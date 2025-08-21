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
			<div class="wcpr-shopee-rating-left">
				<div class="wcpr-shopee-rating-score">
					<span class="wcpr-shopee-rating-number"><?php echo esc_html( number_format( $average_rating, 1 ) ); ?></span>
					<span class="wcpr-shopee-rating-out-of"><?php esc_html_e( 'out of 5', 'woocommerce-photo-reviews' ); ?></span>
				</div>
				<div class="wcpr-shopee-rating-stars">
					<?php
					$rating = $average_rating;
					for ( $i = 1; $i <= 5; $i++ ) {
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
				<div class="wcpr-shopee-review-count">
					<?php printf( esc_html( _n( '%s review', '%s reviews', $count_reviews, 'woocommerce-photo-reviews' ) ), number_format_i18n( $count_reviews ) ); ?>
				</div>
			</div>
			
			<div class="wcpr-shopee-authentic-badge">
				<div class="wcpr-authentic-icon">
					<svg viewBox="0 0 24 24" fill="currentColor" class="wcpr-checkmark-icon">
						<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
					</svg>
				</div>
				<div class="wcpr-authentic-text">
					<span class="wcpr-authentic-main">100% Authentic Reviews</span>
					<span class="wcpr-authentic-sub">From Our Shopee Store & Website</span>
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
						<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="20" viewBox="0 0 20 20" enable-background="new 0 0 1043 1043" xml:space="preserve" height="20"><path fill="#FE5721" opacity="1.000000" stroke="none" d="M20.019 9.243c0 0.518 0 1.036 -0.008 1.559 -0.015 0.018 -0.028 0.03 -0.029 0.044 -0.021 0.191 -0.031 0.383 -0.061 0.572 -0.047 0.299 -0.096 0.599 -0.164 0.894 -0.129 0.563 -0.308 1.109 -0.538 1.64 -0.226 0.52 -0.488 1.02 -0.794 1.497 -0.302 0.471 -0.639 0.918 -1.023 1.323 -0.329 0.348 -0.676 0.683 -1.047 0.986 -0.362 0.296 -0.755 0.556 -1.148 0.81 -0.587 0.379 -1.22 0.673 -1.882 0.894 -0.377 0.126 -0.763 0.229 -1.15 0.325 -0.251 0.062 -0.509 0.098 -0.765 0.136 -0.183 0.027 -0.369 0.039 -0.553 0.061 -0.015 0.002 -0.028 0.024 -0.042 0.037 -0.518 0 -1.036 0 -1.559 -0.008 -0.021 -0.015 -0.037 -0.028 -0.053 -0.03 -0.184 -0.02 -0.37 -0.031 -0.553 -0.06 -0.299 -0.047 -0.6 -0.092 -0.895 -0.16 -0.562 -0.131 -1.11 -0.307 -1.64 -0.538 -0.526 -0.229 -1.031 -0.494 -1.513 -0.804 -0.468 -0.301 -0.912 -0.637 -1.315 -1.018 -0.348 -0.329 -0.677 -0.682 -0.988 -1.045 -0.305 -0.356 -0.57 -0.744 -0.813 -1.147 -0.359 -0.596 -0.664 -1.218 -0.887 -1.877 -0.127 -0.377 -0.23 -0.763 -0.326 -1.15 -0.068 -0.279 -0.116 -0.563 -0.154 -0.848 -0.034 -0.253 -0.041 -0.509 -0.062 -0.764C0.054 10.548 0.032 10.529 0.019 10.508c0 -0.314 0 -0.627 0.008 -0.946 0.015 -0.018 0.028 -0.03 0.029 -0.044 0.02 -0.245 0.035 -0.491 0.06 -0.735 0.02 -0.19 0.043 -0.38 0.082 -0.567 0.06 -0.29 0.132 -0.577 0.204 -0.864 0.132 -0.521 0.324 -1.021 0.55 -1.507 0.257 -0.554 0.55 -1.089 0.915 -1.579 0.27 -0.362 0.555 -0.716 0.858 -1.051 0.393 -0.435 0.824 -0.834 1.304 -1.172 0.362 -0.255 0.732 -0.501 1.113 -0.727 0.492 -0.292 1.014 -0.528 1.557 -0.711 0.386 -0.13 0.781 -0.235 1.177 -0.332 0.278 -0.068 0.563 -0.116 0.848 -0.154 0.253 -0.034 0.509 -0.041 0.764 -0.062C9.509 0.054 9.529 0.032 9.549 0.019c0.314 0 0.627 0 0.946 0.008 0.021 0.015 0.037 0.028 0.054 0.03 0.176 0.014 0.353 0.021 0.529 0.041 0.253 0.028 0.507 0.05 0.756 0.099 0.541 0.106 1.075 0.244 1.594 0.434 0.556 0.204 1.088 0.456 1.602 0.749a9.258 9.258 0 0 1 1.746 1.283c0.348 0.324 0.672 0.677 0.982 1.039 0.305 0.356 0.57 0.744 0.813 1.147 0.361 0.599 0.668 1.223 0.89 1.885 0.122 0.363 0.22 0.734 0.312 1.105 0.065 0.266 0.107 0.539 0.149 0.811 0.029 0.183 0.039 0.369 0.061 0.553 0.002 0.015 0.024 0.028 0.037 0.042M13.03 7.175c-0.153 0 -0.307 -0.003 -0.46 0.001 -0.089 0.002 -0.119 -0.031 -0.128 -0.122 -0.027 -0.284 -0.05 -0.571 -0.106 -0.851 -0.086 -0.425 -0.228 -0.833 -0.433 -1.22 -0.14 -0.265 -0.314 -0.5 -0.519 -0.715 -0.207 -0.217 -0.456 -0.372 -0.738 -0.477 -0.387 -0.145 -0.781 -0.147 -1.174 -0.049 -0.527 0.13 -0.914 0.455 -1.215 0.899 -0.271 0.4 -0.444 0.841 -0.546 1.306 -0.074 0.338 -0.117 0.684 -0.156 1.028 -0.021 0.181 -0.02 0.199 -0.208 0.199 -0.796 0 -1.591 -0.003 -2.387 0.003 -0.115 0.001 -0.241 0.019 -0.341 0.069 -0.241 0.122 -0.271 0.351 -0.254 0.593 0.021 0.296 0.04 0.592 0.063 0.887 0.037 0.477 0.079 0.953 0.115 1.43 0.032 0.417 0.058 0.834 0.087 1.251 0.047 0.671 0.094 1.343 0.141 2.014 0.02 0.286 0.035 0.573 0.062 0.858 0.033 0.352 0.033 0.715 0.125 1.052 0.185 0.676 0.619 0.99 1.227 0.999 0.894 0.014 1.789 0.002 2.684 0.002 1.646 0 3.291 0.007 4.937 -0.002 0.442 -0.002 0.794 -0.205 1.037 -0.586 0.184 -0.287 0.264 -0.606 0.293 -0.938 0.043 -0.495 0.071 -0.992 0.107 -1.488 0.044 -0.604 0.091 -1.209 0.137 -1.813 0.028 -0.371 0.059 -0.743 0.085 -1.114 0.025 -0.366 0.045 -0.732 0.069 -1.098 0.022 -0.331 0.051 -0.662 0.07 -0.993 0.014 -0.232 0.028 -0.465 0.023 -0.697 -0.005 -0.226 -0.233 -0.425 -0.461 -0.425 -0.706 -0.002 -1.412 -0.004 -2.137 -0.006" stroke-width="0.019175455417066157"/><path fill="#FEFEFD" opacity="1.000000" stroke="none" d="M13.039 7.175c0.716 0.002 1.422 0.004 2.128 0.006 0.228 0.001 0.456 0.2 0.461 0.425 0.005 0.232 -0.009 0.465 -0.023 0.697 -0.02 0.331 -0.048 0.662 -0.07 0.993 -0.024 0.366 -0.044 0.732 -0.069 1.098 -0.026 0.372 -0.057 0.743 -0.085 1.114 -0.046 0.604 -0.092 1.208 -0.137 1.813 -0.036 0.496 -0.065 0.992 -0.107 1.488 -0.029 0.332 -0.109 0.651 -0.293 0.938 -0.243 0.381 -0.596 0.584 -1.037 0.586 -1.646 0.009 -3.291 0.002 -4.937 0.002 -0.895 0 -1.79 0.012 -2.684 -0.002 -0.609 -0.01 -1.043 -0.323 -1.227 -0.999 -0.092 -0.337 -0.092 -0.7 -0.125 -1.052 -0.027 -0.286 -0.042 -0.572 -0.062 -0.858 -0.047 -0.671 -0.094 -1.343 -0.141 -2.014 -0.029 -0.417 -0.056 -0.834 -0.087 -1.251 -0.036 -0.477 -0.078 -0.954 -0.115 -1.43 -0.023 -0.296 -0.042 -0.592 -0.063 -0.887 -0.017 -0.242 0.014 -0.471 0.254 -0.593 0.1 -0.051 0.227 -0.068 0.341 -0.069 0.796 -0.006 1.591 -0.003 2.387 -0.003 0.188 0 0.187 -0.017 0.208 -0.199 0.039 -0.344 0.082 -0.69 0.156 -1.028 0.103 -0.466 0.276 -0.907 0.546 -1.306 0.3 -0.444 0.688 -0.769 1.215 -0.899 0.394 -0.097 0.787 -0.096 1.174 0.049 0.282 0.106 0.531 0.26 0.738 0.477 0.204 0.215 0.378 0.45 0.519 0.715 0.205 0.387 0.347 0.794 0.433 1.22 0.056 0.279 0.079 0.566 0.106 0.851 0.009 0.091 0.039 0.124 0.128 0.122 0.153 -0.004 0.307 -0.001 0.47 -0.001m-1.62 -1.729c-0.101 -0.225 -0.219 -0.437 -0.387 -0.622 -0.162 -0.179 -0.336 -0.323 -0.567 -0.418 -0.528 -0.216 -1.208 -0.014 -1.586 0.472 -0.241 0.31 -0.391 0.662 -0.5 1.035 -0.111 0.384 -0.166 0.777 -0.191 1.176 -0.004 0.072 0.02 0.088 0.085 0.088 1.15 -0.001 2.299 -0.001 3.449 0 0.075 0 0.095 -0.027 0.084 -0.098 -0.026 -0.167 -0.048 -0.334 -0.071 -0.501 -0.054 -0.387 -0.152 -0.761 -0.316 -1.132M10.953 11.665c-0.267 -0.113 -0.528 -0.24 -0.801 -0.335 -0.37 -0.129 -0.724 -0.282 -1.032 -0.527 -0.277 -0.221 -0.357 -0.454 -0.285 -0.801 0.095 -0.455 0.597 -0.761 1.05 -0.816 0.444 -0.054 0.853 0.064 1.239 0.278 0.075 0.041 0.144 0.093 0.222 0.128 0.089 0.04 0.172 0.001 0.223 -0.092 0.056 -0.102 0.052 -0.215 -0.021 -0.26 -0.168 -0.103 -0.332 -0.221 -0.513 -0.293 -0.196 -0.078 -0.411 -0.111 -0.617 -0.163 -0.366 -0.094 -0.725 -0.039 -1.068 0.091 -0.428 0.162 -0.739 0.458 -0.898 0.898 -0.111 0.306 -0.115 0.61 0.027 0.908 0.101 0.212 0.258 0.379 0.441 0.522 0.315 0.247 0.68 0.392 1.049 0.534 0.261 0.1 0.526 0.199 0.772 0.331 0.263 0.14 0.515 0.301 0.674 0.576 0.219 0.379 0.155 0.858 -0.178 1.15 -0.254 0.223 -0.563 0.326 -0.891 0.358 -0.361 0.035 -0.713 -0.029 -1.052 -0.157 -0.287 -0.108 -0.552 -0.255 -0.802 -0.432 -0.112 -0.079 -0.228 -0.046 -0.308 0.066 -0.063 0.089 -0.044 0.197 0.053 0.28 0.309 0.263 0.675 0.424 1.053 0.55 0.36 0.12 0.735 0.16 1.115 0.113 0.403 -0.05 0.782 -0.157 1.095 -0.438 0.233 -0.209 0.398 -0.454 0.457 -0.764 0.077 -0.4 -0.014 -0.765 -0.256 -1.084 -0.196 -0.259 -0.448 -0.46 -0.749 -0.618" stroke-width="0.019175455417066157"/><path fill="#FE5722" opacity="1.000000" stroke="none" d="M11.423 5.452c0.161 0.364 0.259 0.739 0.313 1.125 0.023 0.167 0.045 0.334 0.071 0.501 0.011 0.07 -0.009 0.098 -0.084 0.098 -1.15 -0.002 -2.299 -0.001 -3.449 0 -0.065 0 -0.089 -0.017 -0.085 -0.088 0.025 -0.399 0.08 -0.792 0.191 -1.176 0.108 -0.372 0.259 -0.725 0.5 -1.035 0.378 -0.485 1.058 -0.688 1.586 -0.472 0.231 0.095 0.405 0.239 0.567 0.418 0.168 0.185 0.286 0.398 0.39 0.629" stroke-width="0.019175455417066157"/><path fill="#FD5824" opacity="1.000000" stroke="none" d="M10.959 11.668c0.295 0.155 0.547 0.355 0.743 0.614 0.242 0.32 0.333 0.685 0.256 1.084 -0.06 0.311 -0.224 0.555 -0.457 0.764 -0.313 0.281 -0.692 0.388 -1.095 0.438 -0.38 0.047 -0.754 0.007 -1.115 -0.113 -0.379 -0.126 -0.744 -0.287 -1.053 -0.55 -0.097 -0.083 -0.116 -0.192 -0.053 -0.28 0.08 -0.112 0.196 -0.146 0.308 -0.066 0.25 0.177 0.515 0.324 0.802 0.432 0.339 0.128 0.692 0.192 1.052 0.157 0.328 -0.032 0.636 -0.135 0.891 -0.358 0.332 -0.292 0.396 -0.77 0.178 -1.15 -0.159 -0.276 -0.411 -0.436 -0.674 -0.576 -0.246 -0.131 -0.511 -0.23 -0.772 -0.331 -0.369 -0.141 -0.734 -0.287 -1.049 -0.534 -0.183 -0.144 -0.34 -0.31 -0.441 -0.522 -0.142 -0.298 -0.138 -0.602 -0.027 -0.908 0.159 -0.439 0.469 -0.736 0.898 -0.898 0.343 -0.13 0.701 -0.185 1.068 -0.091 0.206 0.053 0.421 0.085 0.617 0.163 0.181 0.072 0.345 0.19 0.513 0.293 0.073 0.045 0.077 0.158 0.021 0.26 -0.051 0.093 -0.134 0.133 -0.223 0.092 -0.077 -0.035 -0.147 -0.086 -0.222 -0.128 -0.386 -0.214 -0.795 -0.332 -1.239 -0.278 -0.453 0.055 -0.956 0.361 -1.05 0.816 -0.072 0.347 0.008 0.58 0.285 0.801 0.308 0.245 0.662 0.398 1.032 0.527 0.273 0.095 0.534 0.222 0.807 0.339" stroke-width="0.019175455417066157"/></svg>
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
