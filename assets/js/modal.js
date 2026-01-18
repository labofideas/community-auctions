/**
 * Community Auctions - Bid Confirmation Modal
 *
 * Accessible modal with focus trap and keyboard navigation.
 */
(function() {
	'use strict';

	const config = window.CommunityAuctionsModal || {};
	const i18n = config.i18n || {};

	let modal = null;
	let lastFocusedElement = null;
	let focusableElements = [];
	let confirmCallback = null;

	/**
	 * Initialize modal functionality.
	 */
	function init() {
		modal = document.getElementById('ca-bid-modal');

		if (!modal) {
			return;
		}

		// Get focusable elements.
		focusableElements = modal.querySelectorAll(
			'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
		);

		// Close button handlers.
		const closeButtons = modal.querySelectorAll('[data-ca-modal-close]');
		closeButtons.forEach(function(btn) {
			btn.addEventListener('click', close);
		});

		// Keyboard navigation.
		modal.addEventListener('keydown', handleKeyDown);

		// Intercept bid form submissions.
		interceptBidForms();
	}

	/**
	 * Intercept bid form submissions to show confirmation.
	 */
	function interceptBidForms() {
		document.addEventListener('submit', function(e) {
			const form = e.target;

			if (!form.classList.contains('community-auction-bid-form')) {
				return;
			}

			e.preventDefault();

			const auctionContainer = form.closest('.community-auction-single');
			if (!auctionContainer) {
				return;
			}

			const auctionId = auctionContainer.dataset.auctionId;
			const amountInput = form.querySelector('[name="amount"]');
			const proxyInput = form.querySelector('[name="proxy_max"]');

			if (!amountInput || !amountInput.value) {
				return;
			}

			const bidData = {
				auctionId: auctionId,
				amount: parseFloat(amountInput.value),
				proxyMax: proxyInput ? parseFloat(proxyInput.value) || null : null,
				title: auctionContainer.querySelector('h2')?.textContent || 'Auction',
				currentBid: auctionContainer.querySelector('.ca-current-bid')?.textContent || '-',
				form: form
			};

			open(bidData);
		});
	}

	/**
	 * Open modal with bid data.
	 *
	 * @param {Object} bidData Bid information.
	 */
	function open(bidData) {
		if (!modal) {
			return;
		}

		// Store last focused element for return.
		lastFocusedElement = document.activeElement;

		// Populate modal content.
		const titleEl = document.getElementById('ca-modal-auction-title');
		const currentBidEl = document.getElementById('ca-modal-current-bid');
		const bidAmountEl = document.getElementById('ca-modal-bid-amount');
		const proxyMaxEl = document.getElementById('ca-modal-proxy-max');
		const proxyDetail = modal.querySelector('.ca-modal-detail--proxy');

		if (titleEl) titleEl.textContent = bidData.title;
		if (currentBidEl) currentBidEl.textContent = bidData.currentBid;
		if (bidAmountEl) bidAmountEl.textContent = formatCurrency(bidData.amount);

		if (bidData.proxyMax && proxyDetail) {
			proxyDetail.style.display = '';
			if (proxyMaxEl) proxyMaxEl.textContent = formatCurrency(bidData.proxyMax);
		} else if (proxyDetail) {
			proxyDetail.style.display = 'none';
		}

		// Set up confirm callback.
		confirmCallback = function() {
			submitBid(bidData);
		};

		const confirmBtn = document.getElementById('ca-modal-confirm');
		if (confirmBtn) {
			confirmBtn.onclick = confirmCallback;
		}

		// Show modal.
		modal.setAttribute('aria-hidden', 'false');
		modal.classList.add('ca-modal--open');
		document.body.classList.add('ca-modal-open');

		// Focus first focusable element.
		if (focusableElements.length) {
			focusableElements[0].focus();
		}
	}

	/**
	 * Close modal.
	 */
	function close() {
		if (!modal) {
			return;
		}

		modal.setAttribute('aria-hidden', 'true');
		modal.classList.remove('ca-modal--open');
		document.body.classList.remove('ca-modal-open');

		// Hide loading state.
		hideLoading();

		// Restore focus.
		if (lastFocusedElement) {
			lastFocusedElement.focus();
		}

		confirmCallback = null;
	}

	/**
	 * Handle keyboard events.
	 *
	 * @param {KeyboardEvent} e Keyboard event.
	 */
	function handleKeyDown(e) {
		if (e.key === 'Escape') {
			close();
			return;
		}

		if (e.key === 'Tab') {
			handleTabKey(e);
		}
	}

	/**
	 * Handle Tab key for focus trap.
	 *
	 * @param {KeyboardEvent} e Keyboard event.
	 */
	function handleTabKey(e) {
		if (!focusableElements.length) {
			return;
		}

		const firstElement = focusableElements[0];
		const lastElement = focusableElements[focusableElements.length - 1];

		if (e.shiftKey && document.activeElement === firstElement) {
			e.preventDefault();
			lastElement.focus();
		} else if (!e.shiftKey && document.activeElement === lastElement) {
			e.preventDefault();
			firstElement.focus();
		}
	}

	/**
	 * Show loading state.
	 */
	function showLoading() {
		const loading = modal.querySelector('.ca-modal-loading');
		const footer = modal.querySelector('.ca-modal-footer');

		if (loading) loading.style.display = '';
		if (footer) footer.style.display = 'none';
	}

	/**
	 * Hide loading state.
	 */
	function hideLoading() {
		const loading = modal.querySelector('.ca-modal-loading');
		const footer = modal.querySelector('.ca-modal-footer');

		if (loading) loading.style.display = 'none';
		if (footer) footer.style.display = '';
	}

	/**
	 * Submit the bid via REST API.
	 *
	 * @param {Object} bidData Bid information.
	 */
	function submitBid(bidData) {
		showLoading();

		const mainConfig = window.CommunityAuctions || {};

		const requestBody = {
			auction_id: parseInt(bidData.auctionId, 10),
			amount: bidData.amount
		};

		if (bidData.proxyMax) {
			requestBody.proxy_max = bidData.proxyMax;
		}

		fetch(mainConfig.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': mainConfig.nonce
			},
			body: JSON.stringify(requestBody)
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(data) {
			hideLoading();

			if (data.success) {
				// Update UI with new bid.
				const container = bidData.form.closest('.community-auction-single');
				if (container) {
					const currentBidEl = container.querySelector('.ca-current-bid');
					if (currentBidEl && data.data) {
						currentBidEl.textContent = formatCurrency(data.data.current_highest);
					}
				}

				// Show success message.
				const messageEl = bidData.form.querySelector('.ca-bid-message');
				if (messageEl) {
					messageEl.textContent = i18n.success || 'Bid placed successfully!';
					messageEl.classList.add('ca-bid-message--success');
				}

				// Clear form.
				const amountInput = bidData.form.querySelector('[name="amount"]');
				if (amountInput) amountInput.value = '';

				const proxyInput = bidData.form.querySelector('[name="proxy_max"]');
				if (proxyInput) proxyInput.value = '';

				close();

				// Trigger custom event.
				document.dispatchEvent(new CustomEvent('ca-bid-placed', {
					detail: data.data
				}));
			} else {
				// Show error.
				const messageEl = bidData.form.querySelector('.ca-bid-message');
				if (messageEl) {
					messageEl.textContent = data.message || 'Failed to place bid.';
					messageEl.classList.add('ca-bid-message--error');
				}
				close();
			}
		})
		.catch(function(error) {
			hideLoading();

			const messageEl = bidData.form.querySelector('.ca-bid-message');
			if (messageEl) {
				messageEl.textContent = i18n.error || 'An error occurred. Please try again.';
				messageEl.classList.add('ca-bid-message--error');
			}
			close();
		});
	}

	/**
	 * Format number as currency.
	 *
	 * @param {number} amount Amount to format.
	 * @return {string} Formatted currency string.
	 */
	function formatCurrency(amount) {
		if (typeof amount !== 'number' || isNaN(amount)) {
			return '-';
		}

		return new Intl.NumberFormat('en-US', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		}).format(amount);
	}

	// Expose public API.
	window.CommunityAuctionsModal = Object.assign(config, {
		open: open,
		close: close
	});

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
