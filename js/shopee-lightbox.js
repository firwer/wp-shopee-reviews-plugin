/**
 * Shopee Style Lightbox JavaScript - Enhanced with Error Handling
 */
(function($) {
	'use strict';
	
	class ShopeeLightbox {
		constructor() {
			this.currentIndex = 0;
			this.mediaItems = [];
			this.currentCommentId = null;
			this.init();
		}
		
		init() {
			this.bindEvents();
		}
		
		bindEvents() {
			$(document).on('click', '.wcpr-shopee-media-item', this.openLightbox.bind(this));
			$(document).on('click', '.wcpr-shopee-lightbox-close', this.closeLightbox.bind(this));
			$(document).on('click', '.wcpr-shopee-lightbox-prev', this.showPrevious.bind(this));
			$(document).on('click', '.wcpr-shopee-lightbox-next', this.showNext.bind(this));
			$(document).on('keydown', this.handleKeydown.bind(this));
			$(document).on('click', '.wcpr-shopee-lightbox', this.handleBackgroundClick.bind(this));
		}
		
		openLightbox(event) {
			event.preventDefault();
			const mediaItem = $(event.currentTarget);
			const commentId = mediaItem.closest('.wcpr-shopee-review-item').data('comment-id');
			const mediaUrl = mediaItem.data('media');
			const mediaType = mediaItem.data('type');
			
			// Validate media URL before opening
			if (!mediaUrl || !this.isValidUrl(mediaUrl)) {
				console.warn('Invalid media URL:', mediaUrl);
				return;
			}
			
			this.currentCommentId = commentId;
			this.currentIndex = mediaItem.index();
			this.mediaItems = mediaItem.closest('.wcpr-shopee-media-grid').find('.wcpr-shopee-media-item');
			
			this.showMedia(mediaUrl, mediaType);
			this.showLightbox();
			this.updateNavigation();
		}
		
		isValidUrl(string) {
			try {
				new URL(string);
				return true;
			} catch (_) {
				return false;
			}
		}
		
		showLightbox() {
			const lightbox = $('#wcpr-shopee-lightbox');
			lightbox.fadeIn(300);
			$('body').addClass('wcpr-lightbox-open');
		}
		
		closeLightbox() {
			$('#wcpr-shopee-lightbox').fadeOut(300);
			$('body').removeClass('wcpr-lightbox-open');
			this.currentCommentId = null;
			this.mediaItems = [];
			this.currentIndex = 0;
		}
		
		showMedia(url, type) {
			const container = $('.wcpr-shopee-lightbox-container');
			const caption = $('.wcpr-shopee-lightbox-caption');
			
			container.empty();
			
			if (type === 'video') {
				const video = $(`<video src="${url}" controls class="wcpr-shopee-lightbox-video" onerror="this.parentNode.innerHTML='<div class=\'wcpr-lightbox-error\'>Video unavailable</div>'"></video>`);
				container.append(video);
				caption.text('Video');
			} else {
				const img = $(`<img src="${url}" alt="Review image" class="wcpr-shopee-lightbox-image" onerror="this.parentNode.innerHTML='<div class=\'wcpr-lightbox-error\'>Image unavailable</div>'">`);
				container.append(img);
				caption.text('Image');
			}
		}
		
		showPrevious() {
			if (this.mediaItems.length > 1) {
				this.currentIndex = (this.currentIndex - 1 + this.mediaItems.length) % this.mediaItems.length;
				const mediaItem = this.mediaItems.eq(this.currentIndex);
				const mediaUrl = mediaItem.data('media');
				const mediaType = mediaItem.data('type');
				
				this.showMedia(mediaUrl, mediaType);
				this.updateNavigation();
			}
		}
		
		showNext() {
			if (this.mediaItems.length > 1) {
				this.currentIndex = (this.currentIndex + 1) % this.mediaItems.length;
				const mediaItem = this.mediaItems.eq(this.currentIndex);
				const mediaUrl = mediaItem.data('media');
				const mediaType = mediaItem.data('type');
				
				this.showMedia(mediaUrl, mediaType);
				this.updateNavigation();
			}
		}
		
		updateNavigation() {
			const totalItems = this.mediaItems.length;
			const prevBtn = $('.wcpr-shopee-lightbox-prev');
			const nextBtn = $('.wcpr-shopee-lightbox-next');
			
			if (totalItems <= 1) {
				prevBtn.hide();
				nextBtn.hide();
			} else {
				prevBtn.show();
				nextBtn.show();
			}
		}
		
		handleKeydown(event) {
			if (event.key === 'Escape') {
				this.closeLightbox();
			} else if (event.key === 'ArrowLeft') {
				this.showPrevious();
			} else if (event.key === 'ArrowRight') {
				this.showNext();
			}
		}
		
		handleBackgroundClick(event) {
			if (event.target === event.currentTarget) {
				this.closeLightbox();
			}
		}
	}
	
	// Initialize when DOM is ready
	$(document).ready(function() {
		new ShopeeLightbox();
	});
	
})(jQuery);
