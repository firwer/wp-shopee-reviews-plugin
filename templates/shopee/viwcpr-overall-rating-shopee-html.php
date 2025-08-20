<?php
/**
 * Shopee Style Overall Rating Template
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wcpr-shopee-overall-rating">
	<div class="wcpr-shopee-rating-summary">
		<div class="wcpr-shopee-rating-score">
			<span class="wcpr-shopee-rating-number"><?php echo esc_html( number_format( $arg['average_rating'], 1 ) ); ?></span>
			<span class="wcpr-shopee-rating-out-of"><?php esc_html_e( 'out of 5', 'woocommerce-photo-reviews' ); ?></span>
		</div>
		<div class="wcpr-shopee-rating-stars">
			<?php
			$rating = $arg['average_rating'];
			for ( $i = 1; $i <= 5; $i++ ) {
				if ( $i <= $rating ) {
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
			<?php printf( esc_html( _n( '%s review', '%s reviews', $arg['count_reviews'], 'woocommerce-photo-reviews' ) ), number_format_i18n( $arg['count_reviews'] ) ); ?>
		</div>
	</div>
</div>
