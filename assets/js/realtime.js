/**
 * Community Auctions - Real-time Bid Updates
 *
 * Polling mechanism for live auction updates with visibility detection.
 */
(function() {
	'use strict';

	const config = window.CommunityAuctionsRealtime || {};
	const i18n = config.i18n || {};

	let pollTimer = null;
	let isVisible = true;
	let trackedAuctions = new Map();
	let lastUpdate = 0;

	/**
	 * Initialize real-time updates.
	 */
	function init() {
		if (!config.enabled) {
			return;
		}

		// Find all auction elements on the page.
		discoverAuctions();

		if (trackedAuctions.size === 0) {
			return;
		}

		// Set up visibility change detection.
		setupVisibilityDetection();

		// Start polling.
		startPolling();

		// Listen for bid placed events to trigger immediate refresh.
		document.addEventListener('ca-bid-placed', function(e) {
			if (e.detail && e.detail.auction_id) {
				fetchSingleAuction(e.detail.auction_id);
			}
		});
	}

	/**
	 * Discover all auction elements on the page.
	 */
	function discoverAuctions() {
		const auctionElements = document.querySelectorAll('[data-auction-id]');

		auctionElements.forEach(function(el) {
			const auctionId = parseInt(el.dataset.auctionId, 10);
			if (auctionId && !trackedAuctions.has(auctionId)) {
				trackedAuctions.set(auctionId, {
					element: el,
					lastBid: parseFloat(el.dataset.currentBid) || 0,
					lastBidder: el.dataset.currentBidder || ''
				});
			}
		});
	}

	/**
	 * Set up page visibility detection.
	 */
	function setupVisibilityDetection() {
		// Handle visibility change.
		document.addEventListener('visibilitychange', function() {
			isVisible = !document.hidden;

			if (isVisible) {
				// Resume polling and do immediate fetch.
				fetchBatchStatus();
				startPolling();
			} else {
				// Pause polling when tab is hidden.
				stopPolling();
			}
		});

		// Handle window focus/blur as backup.
		window.addEventListener('focus', function() {
			if (!isVisible) {
				isVisible = true;
				fetchBatchStatus();
				startPolling();
			}
		});

		window.addEventListener('blur', function() {
			isVisible = false;
			stopPolling();
		});
	}

	/**
	 * Start the polling timer.
	 */
	function startPolling() {
		if (pollTimer) {
			return;
		}

		pollTimer = setInterval(function() {
			if (isVisible && trackedAuctions.size > 0) {
				fetchBatchStatus();
			}
		}, config.interval || 15000);
	}

	/**
	 * Stop the polling timer.
	 */
	function stopPolling() {
		if (pollTimer) {
			clearInterval(pollTimer);
			pollTimer = null;
		}
	}

	/**
	 * Fetch batch status for all tracked auctions.
	 */
	function fetchBatchStatus() {
		const auctionIds = Array.from(trackedAuctions.keys());

		if (auctionIds.length === 0) {
			return;
		}

		fetch(config.restUrl + '/batch', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify({ auction_ids: auctionIds })
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(data) {
			if (data.auctions) {
				processUpdates(data.auctions);
			}
			lastUpdate = data.timestamp || Date.now() / 1000;
		})
		.catch(function(error) {
			console.warn('Real-time update failed:', error);
		});
	}

	/**
	 * Fetch single auction status.
	 *
	 * @param {number} auctionId Auction ID.
	 */
	function fetchSingleAuction(auctionId) {
		fetch(config.restUrl + '/' + auctionId, {
			method: 'GET',
			headers: {
				'X-WP-Nonce': config.nonce
			}
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(data) {
			if (data.auction) {
				const updates = {};
				updates[auctionId] = data.auction;
				processUpdates(updates);
			}
		})
		.catch(function(error) {
			console.warn('Single auction update failed:', error);
		});
	}

	/**
	 * Process auction updates.
	 *
	 * @param {Object} auctions Auction data keyed by ID.
	 */
	function processUpdates(auctions) {
		const currentUserId = getCurrentUserId();

		Object.keys(auctions).forEach(function(auctionId) {
			const auctionData = auctions[auctionId];
			const tracked = trackedAuctions.get(parseInt(auctionId, 10));

			if (!tracked) {
				return;
			}

			// Check if bid has changed.
			const bidChanged = auctionData.current_bid !== tracked.lastBid;
			const bidderChanged = auctionData.current_bidder.id !== tracked.lastBidder;

			// Update the DOM.
			updateAuctionDOM(tracked.element, auctionData);

			// Show notifications for bid changes.
			if (bidChanged || bidderChanged) {
				// Check if current user was outbid.
				if (currentUserId &&
					tracked.lastBidder === currentUserId &&
					auctionData.current_bidder.id !== currentUserId) {
					showNotification(i18n.outbid || 'You have been outbid!', 'warning');
				} else if (bidChanged) {
					showNotification(i18n.bidPlaced || 'New bid placed!', 'info');
				}

				// Update tracked data.
				tracked.lastBid = auctionData.current_bid;
				tracked.lastBidder = auctionData.current_bidder.id;
			}

			// Check if auction has ended.
			if (auctionData.has_ended && !tracked.hasEnded) {
				tracked.hasEnded = true;
				showNotification(i18n.ended || 'Auction ended', 'info');

				// Dispatch custom event.
				document.dispatchEvent(new CustomEvent('ca-auction-ended', {
					detail: { auction_id: parseInt(auctionId, 10) }
				}));
			}
		});
	}

	/**
	 * Update auction DOM elements.
	 *
	 * @param {Element} container Auction container element.
	 * @param {Object} data Auction data.
	 */
	function updateAuctionDOM(container, data) {
		// Update current bid display.
		const bidEl = container.querySelector('.ca-current-bid');
		if (bidEl && data.formatted_bid) {
			const currentText = bidEl.textContent;
			if (currentText !== data.formatted_bid) {
				bidEl.textContent = data.formatted_bid;
				highlightChange(bidEl);
			}
		}

		// Update bid count.
		const bidCountEl = container.querySelector('.ca-bid-count');
		if (bidCountEl) {
			bidCountEl.textContent = data.bid_count;
		}

		// Update unique bidders count.
		const biddersEl = container.querySelector('.ca-unique-bidders');
		if (biddersEl) {
			biddersEl.textContent = data.unique_bidders;
		}

		// Update bidder name.
		const bidderEl = container.querySelector('.ca-current-bidder');
		if (bidderEl && data.current_bidder.name) {
			bidderEl.textContent = data.current_bidder.name;
		}

		// Update data attributes for other scripts.
		container.dataset.currentBid = data.current_bid;
		container.dataset.currentBidder = data.current_bidder.id;
		container.dataset.secondsLeft = data.seconds_left;

		// Update auction status class.
		if (data.has_ended) {
			container.classList.add('ca-auction--ended');
			container.classList.remove('ca-auction--live');
		}
	}

	/**
	 * Highlight an element that changed.
	 *
	 * @param {Element} el Element to highlight.
	 */
	function highlightChange(el) {
		el.classList.add('ca-value-changed');

		setTimeout(function() {
			el.classList.remove('ca-value-changed');
		}, 2000);
	}

	/**
	 * Show a notification to the user.
	 *
	 * @param {string} message Notification message.
	 * @param {string} type Notification type (info, warning, error).
	 */
	function showNotification(message, type) {
		// Check if browser notifications are supported and permitted.
		if ('Notification' in window && Notification.permission === 'granted' && !isVisible) {
			new Notification('Community Auctions', {
				body: message,
				icon: '/wp-content/plugins/community-auctions/assets/images/icon.png'
			});
			return;
		}

		// Fall back to in-page notification.
		const notification = document.createElement('div');
		notification.className = 'ca-notification ca-notification--' + type;
		notification.textContent = message;
		notification.setAttribute('role', 'alert');

		document.body.appendChild(notification);

		// Animate in.
		setTimeout(function() {
			notification.classList.add('ca-notification--visible');
		}, 10);

		// Remove after delay.
		setTimeout(function() {
			notification.classList.remove('ca-notification--visible');
			setTimeout(function() {
				notification.remove();
			}, 300);
		}, 4000);
	}

	/**
	 * Get current user ID from page.
	 *
	 * @return {number|null} User ID or null if not logged in.
	 */
	function getCurrentUserId() {
		const mainConfig = window.CommunityAuctions || {};
		return mainConfig.userId || null;
	}

	/**
	 * Request notification permission.
	 */
	function requestNotificationPermission() {
		if ('Notification' in window && Notification.permission === 'default') {
			Notification.requestPermission();
		}
	}

	// Expose public API.
	window.CommunityAuctionsRealtime = Object.assign(config, {
		refresh: fetchBatchStatus,
		refreshAuction: fetchSingleAuction,
		pause: stopPolling,
		resume: startPolling,
		requestNotifications: requestNotificationPermission
	});

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
