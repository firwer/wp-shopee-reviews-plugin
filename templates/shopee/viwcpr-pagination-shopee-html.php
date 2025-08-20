<?php
/**
 * Shopee Style Pagination Template
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total_pages = ceil( $total_reviews / $reviews_per_page );
if ( $total_pages <= 1 ) {
	return;
}

$current_page = max( 1, $current_page );
$pagination_range = 2; // Show 2 pages before and after current

// Determine the base URL for pagination
if ( $is_shortcode ) {
	$base_url = add_query_arg( 'wcpr_page', '%d', remove_query_arg( 'wcpr_page' ) );
} else {
	$base_url = get_pagenum_link( '%d' );
}
?>

<nav class="wcpr-shopee-pagination" aria-label="Reviews pagination">
	<?php if ( $current_page > 1 ) : ?>
		<?php 
		$prev_url = $is_shortcode ? 
			add_query_arg( 'wcpr_page', $current_page - 1, remove_query_arg( 'wcpr_page' ) ) : 
			get_pagenum_link( $current_page - 1 );
		?>
		<a href="<?php echo esc_url( $prev_url ); ?>" class="wcpr-shopee-prev-page">
			<svg viewBox="0 0 11 11" class="wcpr-shopee-arrow-icon">
				<g>
					<path d="m8.5 11c-.1 0-.2 0-.3-.1l-6-5c-.1-.1-.2-.3-.2-.4s.1-.3.2-.4l6-5c .2-.2.5-.1.7.1s.1.5-.1.7l-5.5 4.6 5.5 4.6c.2.2.2.5.1.7-.1.1-.3.2-.4.2z"></path>
				</g>
			</svg>
		</a>
	<?php endif; ?>
	
	<div class="wcpr-shopee-page-numbers">
		<?php
		$start_page = max( 1, $current_page - $pagination_range );
		$end_page = min( $total_pages, $current_page + $pagination_range );
		
		if ( $start_page > 1 ) {
			$url = $is_shortcode ? 
				add_query_arg( 'wcpr_page', 1, remove_query_arg( 'wcpr_page' ) ) : 
				get_pagenum_link( 1 );
			echo '<a href="' . esc_url( $url ) . '" class="wcpr-shopee-page-number">1</a>';
			if ( $start_page > 2 ) {
				echo '<span class="wcpr-shopee-ellipsis">...</span>';
			}
		}
		
		for ( $i = $start_page; $i <= $end_page; $i++ ) {
			$active_class = ( $i === $current_page ) ? 'wcpr-shopee-page-active' : '';
			$url = $is_shortcode ? 
				add_query_arg( 'wcpr_page', $i, remove_query_arg( 'wcpr_page' ) ) : 
				get_pagenum_link( $i );
			echo '<a href="' . esc_url( $url ) . '" class="wcpr-shopee-page-number ' . esc_attr( $active_class ) . '">' . esc_html( $i ) . '</a>';
		}
		
		if ( $end_page < $total_pages ) {
			if ( $end_page < $total_pages - 1 ) {
				echo '<span class="wcpr-shopee-ellipsis">...</span>';
			}
			$url = $is_shortcode ? 
				add_query_arg( 'wcpr_page', $total_pages, remove_query_arg( 'wcpr_page' ) ) : 
				get_pagenum_link( $total_pages );
			echo '<a href="' . esc_url( $url ) . '" class="wcpr-shopee-page-number">' . esc_html( $total_pages ) . '</a>';
		}
		?>
	</div>
	
	<?php if ( $current_page < $total_pages ) : ?>
		<?php 
		$next_url = $is_shortcode ? 
			add_query_arg( 'wcpr_page', $current_page + 1, remove_query_arg( 'wcpr_page' ) ) : 
			get_pagenum_link( $current_page + 1 );
		?>
		<a href="<?php echo esc_url( $next_url ); ?>" class="wcpr-shopee-next-page">
			<svg viewBox="0 0 11 11" class="wcpr-shopee-arrow-icon">
				<path d="m2.5 11c .1 0 .2 0 .3-.1l6-5c .1-.1.2-.3.2-.4s-.1-.3-.2-.4l-6-5c-.2-.2-.5-.1-.7.1s-.1.5.1.7l5.5 4.6-5.5 4.6c-.2.2-.2.5-.1.7.1.1.3.2.4.2z"></path>
			</svg>
		</a>
	<?php endif; ?>
</nav>
