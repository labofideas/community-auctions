/**
 * Community Auctions - Countdown Timer
 *
 * Handles countdown display with urgency states.
 */
(function() {
	'use strict';

	const config = window.CommunityAuctionsCountdown || {};
	const i18n = config.i18n || {
		days: 'd',
		hours: 'h',
		minutes: 'm',
		seconds: 's',
		ended: 'Auction ended',
		starts: 'Starts in'
	};

	// Update interval (1 second).
	const UPDATE_INTERVAL = 1000;

	// Urgency thresholds in seconds.
	const CRITICAL_THRESHOLD = 300;  // 5 minutes
	const URGENT_THRESHOLD = 3600;   // 1 hour

	/**
	 * Initialize countdown timers.
	 */
	function init() {
		const countdowns = document.querySelectorAll('[data-ca-countdown]');

		if (!countdowns.length) {
			return;
		}

		// Initial update.
		countdowns.forEach(updateCountdown);

		// Start interval.
		setInterval(function() {
			countdowns.forEach(updateCountdown);
		}, UPDATE_INTERVAL);
	}

	/**
	 * Update a single countdown element.
	 *
	 * @param {Element} element Countdown element.
	 */
	function updateCountdown(element) {
		const endTime = parseInt(element.dataset.endTime, 10);
		const type = element.dataset.type || 'end';
		const showSeconds = element.dataset.showSeconds !== '0';
		const showLabels = element.dataset.showLabels !== '0';

		if (!endTime) {
			return;
		}

		const now = Math.floor(Date.now() / 1000);
		const diff = endTime - now;

		// Check if ended/started.
		if (diff <= 0) {
			handleExpired(element, type);
			return;
		}

		// Update time display.
		const timeEl = element.querySelector('.ca-countdown-time');
		if (timeEl) {
			timeEl.textContent = formatTime(diff, showSeconds, showLabels);
		}

		// Update urgency class.
		updateUrgencyClass(element, diff, type);
	}

	/**
	 * Handle expired countdown.
	 *
	 * @param {Element} element Countdown element.
	 * @param {string}  type    Timer type (end/start).
	 */
	function handleExpired(element, type) {
		element.classList.remove('ca-countdown--normal', 'ca-countdown--urgent', 'ca-countdown--critical');
		element.classList.add('ca-countdown--ended');

		if (type === 'start') {
			element.innerHTML = '<span class="ca-countdown-started">Auction is live</span>';
		} else {
			element.innerHTML = '<span class="ca-countdown-ended">' + i18n.ended + '</span>';
		}

		// Trigger custom event.
		const event = new CustomEvent('ca-countdown-expired', {
			detail: {
				element: element,
				type: type
			},
			bubbles: true
		});
		element.dispatchEvent(event);
	}

	/**
	 * Update urgency class based on remaining time.
	 *
	 * @param {Element} element Countdown element.
	 * @param {number}  seconds Seconds remaining.
	 * @param {string}  type    Timer type (end/start).
	 */
	function updateUrgencyClass(element, seconds, type) {
		// Remove existing urgency classes.
		element.classList.remove('ca-countdown--normal', 'ca-countdown--urgent', 'ca-countdown--critical');

		// Start countdowns don't have urgency.
		if (type === 'start') {
			element.classList.add('ca-countdown--normal');
			return;
		}

		if (seconds <= CRITICAL_THRESHOLD) {
			element.classList.add('ca-countdown--critical');
		} else if (seconds <= URGENT_THRESHOLD) {
			element.classList.add('ca-countdown--urgent');
		} else {
			element.classList.add('ca-countdown--normal');
		}
	}

	/**
	 * Format time for display.
	 *
	 * @param {number}  totalSeconds Total seconds remaining.
	 * @param {boolean} showSeconds  Whether to show seconds.
	 * @param {boolean} showLabels   Whether to show labels.
	 * @return {string} Formatted time string.
	 */
	function formatTime(totalSeconds, showSeconds, showLabels) {
		if (totalSeconds <= 0) {
			return i18n.ended;
		}

		const days = Math.floor(totalSeconds / 86400);
		const hours = Math.floor((totalSeconds % 86400) / 3600);
		const minutes = Math.floor((totalSeconds % 3600) / 60);
		const seconds = totalSeconds % 60;

		const parts = [];

		if (days > 0) {
			parts.push(days + (showLabels ? i18n.days : 'd'));
		}

		if (hours > 0 || days > 0) {
			parts.push(hours + (showLabels ? i18n.hours : 'h'));
		}

		if (minutes > 0 || hours > 0 || days > 0) {
			parts.push(minutes + (showLabels ? i18n.minutes : 'm'));
		}

		// Only show seconds if no days and showSeconds is true.
		if (showSeconds && days === 0) {
			parts.push(seconds + (showLabels ? i18n.seconds : 's'));
		}

		return parts.join(' ');
	}

	/**
	 * Get remaining time for an element.
	 *
	 * @param {Element} element Countdown element.
	 * @return {number} Seconds remaining.
	 */
	function getRemainingTime(element) {
		const endTime = parseInt(element.dataset.endTime, 10);
		if (!endTime) {
			return 0;
		}
		const now = Math.floor(Date.now() / 1000);
		return Math.max(0, endTime - now);
	}

	// Expose public API.
	window.CommunityAuctionsCountdown = Object.assign(config, {
		init: init,
		getRemainingTime: getRemainingTime,
		formatTime: formatTime
	});

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
