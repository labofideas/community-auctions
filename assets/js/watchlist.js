/**
 * Community Auctions - Watchlist
 *
 * Handles watchlist add/remove/toggle functionality.
 */
(function() {
	'use strict';

	const config = window.CommunityAuctionsWatchlist || {};
	const i18n = config.i18n || {};

	/**
	 * Initialize watchlist functionality.
	 */
	function init() {
		// Handle watchlist button clicks.
		document.addEventListener('click', handleButtonClick);

		// Handle remove from watchlist page.
		document.addEventListener('click', handleRemoveClick);

		// Handle notify checkbox changes.
		document.addEventListener('change', handleNotifyChange);
	}

	/**
	 * Handle watchlist button click.
	 *
	 * @param {Event} e Click event.
	 */
	function handleButtonClick(e) {
		const button = e.target.closest('.ca-watchlist-button');
		if (!button) {
			return;
		}

		e.preventDefault();

		if (!config.loggedIn) {
			showMessage(i18n.loginRequired || 'Please log in to use the watchlist.', 'error');
			return;
		}

		const auctionId = button.dataset.auctionId;
		const isWatching = button.getAttribute('aria-pressed') === 'true';

		if (isWatching) {
			removeFromWatchlist(auctionId, button);
		} else {
			addToWatchlist(auctionId, button);
		}
	}

	/**
	 * Handle remove button click on watchlist page.
	 *
	 * @param {Event} e Click event.
	 */
	function handleRemoveClick(e) {
		const button = e.target.closest('.ca-watchlist-remove');
		if (!button) {
			return;
		}

		e.preventDefault();

		const auctionId = button.dataset.auctionId;
		const item = button.closest('.ca-watchlist-item');

		removeFromWatchlist(auctionId, null, item);
	}

	/**
	 * Handle notify checkbox change.
	 *
	 * @param {Event} e Change event.
	 */
	function handleNotifyChange(e) {
		const checkbox = e.target;
		if (!checkbox.classList.contains('ca-watchlist-notify-checkbox')) {
			return;
		}

		const auctionId = checkbox.dataset.auctionId;
		const notifyEnding = checkbox.checked;

		updateNotifyPreference(auctionId, notifyEnding);
	}

	/**
	 * Add auction to watchlist.
	 *
	 * @param {string} auctionId Auction ID.
	 * @param {Element} button Watchlist button element.
	 */
	function addToWatchlist(auctionId, button) {
		button.disabled = true;

		fetch(config.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify({
				auction_id: parseInt(auctionId, 10),
				notify_ending: true
			})
		})
		.then(function(response) {
			return response.json().then(function(data) {
				return { ok: response.ok, data: data };
			});
		})
		.then(function(result) {
			button.disabled = false;

			if (result.ok && result.data.success) {
				// Update button state.
				button.setAttribute('aria-pressed', 'true');
				button.classList.add('ca-watchlist-button--watching');

				const icon = button.querySelector('.ca-watchlist-button-icon');
				const text = button.querySelector('.ca-watchlist-button-text');

				if (icon) icon.textContent = '★';
				if (text) text.textContent = i18n.remove || 'Watching';

				showMessage(i18n.added || 'Added to watchlist!', 'success');

				// Dispatch custom event.
				document.dispatchEvent(new CustomEvent('ca-watchlist-added', {
					detail: { auction_id: auctionId }
				}));
			} else {
				showMessage(result.data.message || i18n.error || 'An error occurred.', 'error');
			}
		})
		.catch(function(error) {
			button.disabled = false;
			showMessage(i18n.error || 'An error occurred. Please try again.', 'error');
			console.error('Watchlist add error:', error);
		});
	}

	/**
	 * Remove auction from watchlist.
	 *
	 * @param {string} auctionId Auction ID.
	 * @param {Element|null} button Watchlist button element.
	 * @param {Element|null} listItem List item element (for watchlist page).
	 */
	function removeFromWatchlist(auctionId, button, listItem) {
		if (button) {
			button.disabled = true;
		}

		fetch(config.restUrl + '/' + auctionId, {
			method: 'DELETE',
			headers: {
				'X-WP-Nonce': config.nonce
			}
		})
		.then(function(response) {
			return response.json().then(function(data) {
				return { ok: response.ok, data: data };
			});
		})
		.then(function(result) {
			if (button) {
				button.disabled = false;
			}

			if (result.ok && result.data.success) {
				if (button) {
					// Update button state.
					button.setAttribute('aria-pressed', 'false');
					button.classList.remove('ca-watchlist-button--watching');

					const icon = button.querySelector('.ca-watchlist-button-icon');
					const text = button.querySelector('.ca-watchlist-button-text');

					if (icon) icon.textContent = '☆';
					if (text) text.textContent = i18n.add || 'Watch';
				}

				if (listItem) {
					// Animate removal from list.
					listItem.style.opacity = '0';
					listItem.style.transform = 'translateX(-20px)';
					setTimeout(function() {
						listItem.remove();

						// Check if list is empty.
						const list = document.querySelector('.ca-watchlist-list');
						if (list && list.children.length === 0) {
							const watchlist = document.querySelector('.ca-watchlist');
							if (watchlist) {
								list.remove();
								const emptyMsg = document.createElement('p');
								emptyMsg.className = 'ca-watchlist-empty';
								emptyMsg.textContent = 'You are not watching any auctions.';
								watchlist.appendChild(emptyMsg);
							}
						}
					}, 300);
				}

				showMessage(i18n.removed || 'Removed from watchlist.', 'success');

				// Dispatch custom event.
				document.dispatchEvent(new CustomEvent('ca-watchlist-removed', {
					detail: { auction_id: auctionId }
				}));
			} else {
				showMessage(result.data.message || i18n.error || 'An error occurred.', 'error');
			}
		})
		.catch(function(error) {
			if (button) {
				button.disabled = false;
			}
			showMessage(i18n.error || 'An error occurred. Please try again.', 'error');
			console.error('Watchlist remove error:', error);
		});
	}

	/**
	 * Update notify preference.
	 *
	 * @param {string} auctionId Auction ID.
	 * @param {boolean} notifyEnding Notify preference.
	 */
	function updateNotifyPreference(auctionId, notifyEnding) {
		fetch(config.restUrl + '/' + auctionId + '/notify', {
			method: 'PATCH',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify({
				notify_ending: notifyEnding
			})
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(data) {
			if (!data.success) {
				console.warn('Failed to update notify preference:', data.message);
			}
		})
		.catch(function(error) {
			console.error('Notify preference update error:', error);
		});
	}

	/**
	 * Show a message to the user.
	 *
	 * @param {string} message Message text.
	 * @param {string} type Message type (success, error).
	 */
	function showMessage(message, type) {
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
		}, 3000);
	}

	/**
	 * Check watchlist status for an auction.
	 *
	 * @param {string} auctionId Auction ID.
	 * @return {Promise} Promise resolving to watchlist status.
	 */
	function checkStatus(auctionId) {
		return fetch(config.restUrl + '/check/' + auctionId, {
			method: 'GET',
			headers: {
				'X-WP-Nonce': config.nonce
			}
		})
		.then(function(response) {
			return response.json();
		});
	}

	// Expose public API.
	window.CommunityAuctionsWatchlist = Object.assign(config, {
		add: addToWatchlist,
		remove: removeFromWatchlist,
		check: checkStatus,
		updateNotify: updateNotifyPreference
	});

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
