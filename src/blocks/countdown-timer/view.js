/**
 * Countdown Timer - Frontend View Script
 *
 * Updates countdown timers in real-time on the frontend.
 */

( function () {
	'use strict';

	const DAY_IN_SECONDS = 86400;
	const HOUR_IN_SECONDS = 3600;
	const MINUTE_IN_SECONDS = 60;

	/**
	 * Initialize all countdown timers on the page.
	 */
	function initCountdowns() {
		const countdowns = document.querySelectorAll( '[data-ca-countdown]' );

		countdowns.forEach( ( element ) => {
			const endTime = element.getAttribute( 'data-end-time' );
			if ( ! endTime ) {
				return;
			}

			const showSeconds = element.getAttribute( 'data-show-seconds' ) === 'true';
			const showLabels = element.getAttribute( 'data-show-labels' ) === 'true';
			const endedText = element.getAttribute( 'data-ended-text' ) || 'Auction Ended';
			const urgentThreshold = parseInt( element.getAttribute( 'data-urgent-threshold' ), 10 ) || 60;

			const endTimestamp = new Date( endTime ).getTime();

			// Start the countdown.
			updateCountdown( element, endTimestamp, showSeconds, showLabels, endedText, urgentThreshold );

			// Update every second if showing seconds, otherwise every minute.
			const interval = showSeconds ? 1000 : 60000;
			const timer = setInterval( () => {
				const isEnded = updateCountdown( element, endTimestamp, showSeconds, showLabels, endedText, urgentThreshold );
				if ( isEnded ) {
					clearInterval( timer );
				}
			}, interval );

			// Store timer for cleanup.
			element._caCountdownTimer = timer;
		} );
	}

	/**
	 * Update a single countdown element.
	 *
	 * @param {HTMLElement} element         The countdown element.
	 * @param {number}      endTimestamp    End timestamp in milliseconds.
	 * @param {boolean}     showSeconds     Whether to show seconds.
	 * @param {boolean}     showLabels      Whether to show labels.
	 * @param {string}      endedText       Text to show when ended.
	 * @param {number}      urgentThreshold Minutes threshold for urgent state.
	 * @return {boolean} Whether the countdown has ended.
	 */
	function updateCountdown( element, endTimestamp, showSeconds, showLabels, endedText, urgentThreshold ) {
		const now = Date.now();
		const diff = Math.floor( ( endTimestamp - now ) / 1000 );

		// Update urgency classes.
		element.classList.remove( 'ca-countdown-urgent', 'ca-countdown-critical' );

		if ( diff <= 0 ) {
			element.classList.add( 'ca-countdown-ended' );
			element.innerHTML = `<div class="ca-countdown-ended-text">${ escapeHtml( endedText ) }</div>`;

			// Dispatch custom event.
			element.dispatchEvent( new CustomEvent( 'ca-countdown-ended', {
				bubbles: true,
				detail: { element },
			} ) );

			return true;
		}

		const minutesLeft = Math.floor( diff / MINUTE_IN_SECONDS );
		if ( minutesLeft <= 5 ) {
			element.classList.add( 'ca-countdown-critical' );
		} else if ( minutesLeft <= urgentThreshold ) {
			element.classList.add( 'ca-countdown-urgent' );
		}

		// Calculate time units.
		const days = Math.floor( diff / DAY_IN_SECONDS );
		const hours = Math.floor( ( diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
		const minutes = Math.floor( ( diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		const seconds = diff % MINUTE_IN_SECONDS;

		// Build HTML.
		let html = '<div class="ca-countdown-units">';

		if ( days > 0 ) {
			html += buildUnitHtml( days, days === 1 ? 'Day' : 'Days', 'days', showLabels );
		}

		html += buildUnitHtml( hours, hours === 1 ? 'Hour' : 'Hours', 'hours', showLabels, true );
		html += buildUnitHtml( minutes, minutes === 1 ? 'Min' : 'Mins', 'minutes', showLabels, true );

		if ( showSeconds ) {
			html += buildUnitHtml( seconds, seconds === 1 ? 'Sec' : 'Secs', 'seconds', showLabels, true );
		}

		html += '</div>';

		element.innerHTML = html;

		return false;
	}

	/**
	 * Build HTML for a countdown unit.
	 *
	 * @param {number}  value      The value.
	 * @param {string}  label      The label text.
	 * @param {string}  className  Additional class name.
	 * @param {boolean} showLabels Whether to show labels.
	 * @param {boolean} padZero    Whether to pad with zeros.
	 * @return {string} HTML string.
	 */
	function buildUnitHtml( value, label, className, showLabels, padZero = false ) {
		const displayValue = padZero ? String( value ).padStart( 2, '0' ) : value;

		let html = `<div class="ca-countdown-unit ca-countdown-${ className }">`;
		html += `<span class="ca-countdown-value">${ displayValue }</span>`;
		if ( showLabels ) {
			html += `<span class="ca-countdown-label">${ escapeHtml( label ) }</span>`;
		}
		html += '</div>';

		return html;
	}

	/**
	 * Escape HTML special characters.
	 *
	 * @param {string} str String to escape.
	 * @return {string} Escaped string.
	 */
	function escapeHtml( str ) {
		const div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initCountdowns );
	} else {
		initCountdowns();
	}

	// Re-initialize for dynamically loaded content.
	document.addEventListener( 'ca-content-loaded', initCountdowns );
} )();
