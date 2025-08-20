/**
 * Shopee Admin JavaScript
 * Handles all admin functionality for Shopee sync
 */
(function($) {
	'use strict';
	
	class ShopeeAdmin {
		constructor() {
			this.currentPage = 1;
			this.currentHistoryPage = 1;
			this.isSyncing = false;
			this.init();
		}
		
		init() {
			this.bindEvents();
			this.loadInitialData();
		}
		
		bindEvents() {
			// Tab navigation
			$(document).on('click', '.wcpr-tab-link', this.switchTab.bind(this));
			
			// Product search and filters
			$(document).on('input', '#wcpr-product-search', this.debounce(this.handleProductSearch.bind(this), 300));
			$(document).on('change', '#wcpr-product-category', this.handleProductCategoryChange.bind(this));
			
			// History filters
			$(document).on('change', '#wcpr-history-status, #wcpr-history-type', this.handleHistoryFilterChange.bind(this));
			$(document).on('click', '.wcpr-refresh-history', this.loadHistory.bind(this));
			
			// Sync buttons
			$(document).on('click', '.wcpr-sync-all-btn', this.syncAllProducts.bind(this));
			$(document).on('click', '.wcpr-sync-product-btn', this.syncProduct.bind(this));
			
			// Modal close
			$(document).on('click', '.wcpr-modal-close', this.closeModal.bind(this));
			$(document).on('click', '.wcpr-modal', this.handleModalBackgroundClick.bind(this));
			$(document).on('click', '.wcpr-full-fetch-product-btn', this.fullFetchProduct.bind(this));
		}
		
		/**
		 * Switch between tabs
		 */
		switchTab(event) {
			event.preventDefault();
			const target = $(event.currentTarget).attr('href');
			
			// Update active tab
			$('.wcpr-tab-link').removeClass('active');
			$(event.currentTarget).addClass('active');
			
			// Show target content
			$('.wcpr-tab-content').removeClass('active');
			$(target).addClass('active');
			
			// Load data for the tab
			if (target === '#manual-sync') {
				this.loadProducts();
			} else if (target === '#sync-history') {
				this.loadHistory();
			}
		}
		
		/**
		 * Load initial data
		 */
		loadInitialData() {
			// Load products if on manual sync tab
			if ($('#manual-sync').hasClass('active')) {
				this.loadProducts();
			}
			
			// Load history if on history tab
			if ($('#sync-history').hasClass('active')) {
				this.loadHistory();
			}
		}
		
		/**
		 * Load products for manual sync
		 */
		loadProducts(page = 1) {
			const search = $('#wcpr-product-search').val();
			const category = $('#wcpr-product-category').val();
			
			$('#wcpr-products-tbody').html('<tr><td colspan="5" class="wcpr-loading">Loading products...</td></tr>');
			
			// Disable search inputs during loading
			$('#wcpr-product-search, #wcpr-product-category').prop('disabled', true);
			
			$.ajax({
				url: wcpr_shopee_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'wcpr_shopee_get_products',
					nonce: wcpr_shopee_ajax.nonce,
					search: search,
					category: category,
					page: page
				},
				success: (response) => {
					if (response.success) {
						this.renderProducts(response.data);
					} else {
						$('#wcpr-products-tbody').html('<tr><td colspan="5">Error loading products: ' + response.data.message + '</td></tr>');
					}
				},
				error: () => {
					$('#wcpr-products-tbody').html('<tr><td colspan="5">Error loading products. Please try again.</td></tr>');
				},
				complete: () => {
					// Re-enable inputs after request completes
					$('#wcpr-product-search, #wcpr-product-category').prop('disabled', false);
				}
			});
		}
		
		/**
		 * Render products table
		 */
		renderProducts(data) {
			const tbody = $('#wcpr-products-tbody');
			
			if (!data.products || data.products.length === 0) {
				tbody.html('<tr><td colspan="5">No products found with SKU mapping.</td></tr>');
				return;
			}
			
			let html = '';
			data.products.forEach(product => {
				html += `
					<tr>
						<td>
							<strong><a href="${product.edit_url}" target="_blank">${product.name}</a></strong>
						</td>
						<td><code>${product.sku}</code></td>
						<td>${product.categories}</td>
						<td>${product.last_sync}</td>
						<td>
							<button type="button" class="button wcpr-sync-product-btn" data-product-id="${product.id}" data-product-name="${product.name}">
								Sync Reviews
							</button>
							<button type="button" class="button button-primary wcpr-full-fetch-product-btn" data-product-id="${product.id}" data-product-name="${product.name}" style="margin-left:6px;">
								Full Fetch
							</button>
						</td>
					</tr>
				`;
			});
			
			tbody.html(html);
		}
		
		/**
		 * Load sync history
		 */
		loadHistory(page = 1) {
			const status = $('#wcpr-history-status').val();
			const type = $('#wcpr-history-type').val();
			
			$('#wcpr-history-tbody').html('<tr><td colspan="8" class="wcpr-loading">Loading history...</td></tr>');
			
			$.ajax({
				url: wcpr_shopee_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'wcpr_shopee_get_history',
					nonce: wcpr_shopee_ajax.nonce,
					status: status,
					type: type,
					page: page
				},
				success: (response) => {
					if (response.success) {
						this.renderHistory(response.data);
					} else {
						$('#wcpr-history-tbody').html('<tr><td colspan="8">Error loading history: ' + response.data.message + '</td></tr>');
					}
				},
				error: () => {
					$('#wcpr-history-tbody').html('<tr><td colspan="8">Error loading history. Please try again.</td></tr>');
				}
			});
		}
		
		/**
		 * Render history table
		 */
		renderHistory(data) {
			const tbody = $('#wcpr-history-tbody');
			
			if (!data.history || data.history.length === 0) {
				tbody.html('<tr><td colspan="8">No sync history found.</td></tr>');
				return;
			}
			
			let html = '';
			data.history.forEach(record => {
				const statusClass = `wcpr-status-${record.status}`;
				const statusText = record.status.charAt(0).toUpperCase() + record.status.slice(1);
				
				html += `
					<tr>
						<td>${this.formatDateTime(record.start_time)}</td>
						<td>${record.sync_type.charAt(0).toUpperCase() + record.sync_type.slice(1)}</td>
						<td>${record.product_name || 'All Products'}</td>
						<td>${record.reviews_imported}</td>
						<td>${record.reviews_skipped}</td>
						<td>${record.duration}</td>
						<td><span class="${statusClass}">${statusText}</span></td>
						<td>
							${record.errors ? `<button type="button" class="button button-small" onclick="this.nextElementSibling.style.display='block'; this.style.display='none';">Show Errors</button><div style="display:none; font-size:11px; color:#666; max-width:200px;">${record.errors}</div>` : '-'}
						</td>
					</tr>
				`;
			});
			
			tbody.html(html);
		}
		
		/**
		 * Handle product search
		 */
		handleProductSearch() {
			this.currentPage = 1;
			this.loadProducts();
		}
		
		/**
		 * Handle product category change
		 */
		handleProductCategoryChange() {
			this.currentPage = 1;
			this.loadProducts();
		}
		
		/**
		 * Handle history filter change
		 */
		handleHistoryFilterChange() {
			this.currentHistoryPage = 1;
			this.loadHistory();
		}
		
		/**
		 * Sync all products
		 */
		syncAllProducts() {
			if (this.isSyncing) return;
			
			this.isSyncing = true;
			this.showModal('Syncing all products...');
			
			$.ajax({
				url: wcpr_shopee_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'wcpr_shopee_sync_all',
					nonce: wcpr_shopee_ajax.nonce
				},
				success: (response) => {
					if (response.success) {
						this.showNotice(response.data.message, 'success');
						this.loadHistory(); // Refresh history
					} else {
						this.showNotice('Sync failed: ' + response.data.message, 'error');
					}
				},
				error: () => {
					this.showNotice('Sync failed. Please try again.', 'error');
				},
				complete: () => {
					this.isSyncing = false;
					this.hideModal();
				}
			});
		}
		
		/**
		 * Sync individual product
		 */
		syncProduct(event) {
			if (this.isSyncing) return;
			
			const button = $(event.currentTarget);
			const productId = button.data('product-id');
			const productName = button.data('product-name');
			
			this.isSyncing = true;
			button.prop('disabled', true).text('Syncing...');
			this.showModal(`Syncing ${productName}...`);
			
			$.ajax({
				url: wcpr_shopee_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'wcpr_shopee_sync_product',
					nonce: wcpr_shopee_ajax.nonce,
					product_id: productId
				},
				success: (response) => {
					if (response.success) {
						this.showNotice(response.data.message, 'success');
						this.loadProducts(); // Refresh products
						this.loadHistory(); // Refresh history
					} else {
						this.showNotice('Sync failed: ' + response.data.message, 'error');
					}
				},
				error: () => {
					this.showNotice('Sync failed. Please try again.', 'error');
				},
				complete: () => {
					this.isSyncing = false;
					button.prop('disabled', false).text('Sync Reviews');
					this.hideModal();
				}
			});
		}

		/**
		 * Full fetch for a single product (resets last sync timestamp)
		 */
		fullFetchProduct(event) {
			if (this.isSyncing) return;

			const button = $(event.currentTarget);
			const productId = button.data('product-id');
			const productName = button.data('product-name');

			this.isSyncing = true;
			button.prop('disabled', true).text('Full fetching...');
			this.showModal(`Full fetching ${productName}...`);

			$.ajax({
				url: wcpr_shopee_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'wcpr_shopee_full_sync_product',
					nonce: wcpr_shopee_ajax.nonce,
					product_id: productId
				},
				success: (response) => {
					if (response.success) {
						this.showNotice(response.data.message, 'success');
						this.loadProducts();
						this.loadHistory();
					} else {
						this.showNotice('Full fetch failed: ' + response.data.message, 'error');
					}
				},
				error: () => {
					this.showNotice('Full fetch failed. Please try again.', 'error');
				},
				complete: () => {
					this.isSyncing = false;
					button.prop('disabled', false).text('Full Fetch');
					this.hideModal();
				}
			});
		}
		
		/**
		 * Show sync modal
		 */
		showModal(message) {
			$('.wcpr-sync-message h4').text(message);
			$('#wcpr-sync-modal').show();
			this.updateProgress(0);
		}
		
		/**
		 * Hide sync modal
		 */
		hideModal() {
			$('#wcpr-sync-modal').hide();
		}
		
		/**
		 * Close modal
		 */
		closeModal() {
			this.hideModal();
		}
		
		/**
		 * Handle modal background click
		 */
		handleModalBackgroundClick(event) {
			if (event.target === event.currentTarget) {
				this.hideModal();
			}
		}
		
		/**
		 * Update progress bar
		 */
		updateProgress(percentage) {
			$('.wcpr-progress-fill').css('width', percentage + '%');
			$('.wcpr-progress-text').text(Math.round(percentage) + '%');
		}
		
		/**
		 * Show notice message
		 */
		showNotice(message, type = 'info') {
			const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
			const notice = $(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`);
			
			$('.wrap').first().prepend(notice);
			
			// Auto-dismiss after 5 seconds
			setTimeout(() => {
				notice.fadeOut();
			}, 5000);
		}
		
		/**
		 * Format date time
		 */
		formatDateTime(dateString) {
			const date = new Date(dateString);
			return date.toLocaleString();
		}
		
		/**
		 * Debounce function
		 */
		debounce(func, wait) {
			let timeout;
			return function executedFunction(...args) {
				const later = () => {
					clearTimeout(timeout);
					func(...args);
				};
				clearTimeout(timeout);
				timeout = setTimeout(later, wait);
			};
		}
	}
	
	// Initialize when DOM is ready
	$(document).ready(function() {
		new ShopeeAdmin();
	});
	
})(jQuery);
