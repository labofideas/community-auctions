/**
 * Community Auctions - Submit Form JavaScript
 * Step-based auction creation wizard
 */

(function() {
	'use strict';

	// Configuration
	const config = {
		currentStep: 1,
		totalSteps: 4,
		formData: {}
	};

	// DOM Elements
	let form, steps, sections, nextBtns, prevBtns, submitBtn;

	/**
	 * Initialize the form
	 */
	function init() {
		form = document.querySelector('.ca-submit-form');
		if (!form) return;

		steps = document.querySelectorAll('.ca-step');
		sections = document.querySelectorAll('.ca-form-section');
		nextBtns = document.querySelectorAll('.ca-btn-next');
		prevBtns = document.querySelectorAll('.ca-btn-prev');
		submitBtn = document.querySelector('.ca-btn-submit');

		bindEvents();
		initDateFields();
		updateDurationPreview();
	}

	/**
	 * Bind event listeners
	 */
	function bindEvents() {
		// Next buttons
		nextBtns.forEach(btn => {
			btn.addEventListener('click', handleNext);
		});

		// Previous buttons
		prevBtns.forEach(btn => {
			btn.addEventListener('click', handlePrev);
		});

		// Buy It Now toggle
		const buyNowToggle = document.getElementById('ca_buy_now_toggle');
		if (buyNowToggle) {
			buyNowToggle.addEventListener('change', toggleBuyNowField);
		}

		// Date fields for duration preview
		const startDate = document.getElementById('ca_start_at');
		const endDate = document.getElementById('ca_end_at');
		if (startDate) startDate.addEventListener('change', updateDurationPreview);
		if (endDate) endDate.addEventListener('change', updateDurationPreview);

		// Form submission
		if (form) {
			form.addEventListener('submit', handleSubmit);
		}

		// Step click navigation (for completed steps)
		steps.forEach(step => {
			step.addEventListener('click', function() {
				const stepNum = parseInt(this.dataset.step);
				if (this.classList.contains('ca-step-complete') || stepNum < config.currentStep) {
					goToStep(stepNum);
				}
			});
		});
	}

	/**
	 * Handle next button click
	 */
	function handleNext(e) {
		e.preventDefault();

		if (!validateStep(config.currentStep)) {
			return;
		}

		if (config.currentStep < config.totalSteps) {
			// Mark current step as complete
			steps[config.currentStep - 1].classList.add('ca-step-complete');

			config.currentStep++;
			updateStepUI();

			// If going to review step, populate review data
			if (config.currentStep === config.totalSteps) {
				populateReview();
			}
		}
	}

	/**
	 * Handle previous button click
	 */
	function handlePrev(e) {
		e.preventDefault();

		if (config.currentStep > 1) {
			config.currentStep--;
			updateStepUI();
		}
	}

	/**
	 * Go to a specific step
	 */
	function goToStep(stepNum) {
		if (stepNum >= 1 && stepNum <= config.totalSteps) {
			config.currentStep = stepNum;
			updateStepUI();

			if (config.currentStep === config.totalSteps) {
				populateReview();
			}
		}
	}

	/**
	 * Update step UI (indicators and sections)
	 */
	function updateStepUI() {
		// Update step indicators
		steps.forEach((step, index) => {
			const stepNum = index + 1;
			step.classList.remove('ca-step-active');

			if (stepNum === config.currentStep) {
				step.classList.add('ca-step-active');
			}
		});

		// Update sections
		sections.forEach((section, index) => {
			const sectionNum = index + 1;
			section.classList.remove('ca-section-active');

			if (sectionNum === config.currentStep) {
				section.classList.add('ca-section-active');
			}
		});

		// Scroll to top of form
		form.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	/**
	 * Validate current step
	 */
	function validateStep(stepNum) {
		const section = sections[stepNum - 1];
		if (!section) return true;

		const requiredFields = section.querySelectorAll('[required]');
		let valid = true;
		let firstInvalid = null;

		requiredFields.forEach(field => {
			// Remove previous error state
			field.classList.remove('ca-input-error');
			const errorMsg = field.parentNode.querySelector('.ca-error-message');
			if (errorMsg) errorMsg.remove();

			if (!field.value.trim()) {
				valid = false;
				field.classList.add('ca-input-error');

				// Add error message
				const error = document.createElement('span');
				error.className = 'ca-error-message';
				error.textContent = 'This field is required';
				error.style.cssText = 'color: #ef4444; font-size: 12px; display: block; margin-top: 4px;';
				field.parentNode.appendChild(error);

				if (!firstInvalid) firstInvalid = field;
			}
		});

		// Additional validation for step 2 (pricing)
		if (stepNum === 2) {
			const startPrice = document.getElementById('ca_start_price');
			if (startPrice && parseFloat(startPrice.value) <= 0) {
				valid = false;
				startPrice.classList.add('ca-input-error');
				if (!firstInvalid) firstInvalid = startPrice;
			}
		}

		// Additional validation for step 3 (schedule)
		if (stepNum === 3) {
			const startDate = document.getElementById('ca_start_at');
			const endDate = document.getElementById('ca_end_at');

			if (startDate && endDate && startDate.value && endDate.value) {
				const start = new Date(startDate.value);
				const end = new Date(endDate.value);

				if (end <= start) {
					valid = false;
					endDate.classList.add('ca-input-error');

					const error = document.createElement('span');
					error.className = 'ca-error-message';
					error.textContent = 'End date must be after start date';
					error.style.cssText = 'color: #ef4444; font-size: 12px; display: block; margin-top: 4px;';
					endDate.parentNode.appendChild(error);

					if (!firstInvalid) firstInvalid = endDate;
				}
			}
		}

		if (firstInvalid) {
			firstInvalid.focus();
		}

		return valid;
	}

	/**
	 * Toggle Buy It Now price field visibility
	 */
	function toggleBuyNowField() {
		const buyNowField = document.getElementById('ca_buy_now_field');
		const buyNowToggle = document.getElementById('ca_buy_now_toggle');

		if (buyNowField && buyNowToggle) {
			if (buyNowToggle.checked) {
				buyNowField.style.display = 'block';
				buyNowField.classList.add('ca-field-visible');
			} else {
				buyNowField.style.display = 'none';
				buyNowField.classList.remove('ca-field-visible');
			}
		}
	}

	/**
	 * Initialize date fields with defaults
	 */
	function initDateFields() {
		const startDate = document.getElementById('ca_start_at');
		const endDate = document.getElementById('ca_end_at');

		if (startDate && !startDate.value) {
			// Default start: now
			const now = new Date();
			startDate.value = formatDateTimeLocal(now);
		}

		if (endDate && !endDate.value) {
			// Default end: 7 days from now
			const end = new Date();
			end.setDate(end.getDate() + 7);
			endDate.value = formatDateTimeLocal(end);
		}
	}

	/**
	 * Format date for datetime-local input
	 */
	function formatDateTimeLocal(date) {
		const year = date.getFullYear();
		const month = String(date.getMonth() + 1).padStart(2, '0');
		const day = String(date.getDate()).padStart(2, '0');
		const hours = String(date.getHours()).padStart(2, '0');
		const minutes = String(date.getMinutes()).padStart(2, '0');

		return `${year}-${month}-${day}T${hours}:${minutes}`;
	}

	/**
	 * Update duration preview
	 */
	function updateDurationPreview() {
		const startDate = document.getElementById('ca_start_at');
		const endDate = document.getElementById('ca_end_at');
		const durationText = document.querySelector('.ca-duration-text');

		if (!startDate || !endDate || !durationText) return;

		if (startDate.value && endDate.value) {
			const start = new Date(startDate.value);
			const end = new Date(endDate.value);
			const diff = end - start;

			if (diff > 0) {
				const days = Math.floor(diff / (1000 * 60 * 60 * 24));
				const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

				let text = 'Auction will run for ';
				if (days > 0) {
					text += days + ' day' + (days !== 1 ? 's' : '');
					if (hours > 0) {
						text += ' and ' + hours + ' hour' + (hours !== 1 ? 's' : '');
					}
				} else if (hours > 0) {
					text += hours + ' hour' + (hours !== 1 ? 's' : '');
				} else {
					text += 'less than an hour';
				}

				durationText.textContent = text;
			} else {
				durationText.textContent = 'End date must be after start date';
			}
		} else {
			durationText.textContent = 'Select start and end dates';
		}
	}

	/**
	 * Populate review section with form data
	 */
	function populateReview() {
		// Item Details
		setReviewValue('review-title', getFieldValue('ca_title'));
		setReviewValue('review-description', truncateText(getFieldValue('ca_description'), 150));
		setReviewValue('review-category', getSelectText('ca_category'));

		// Pricing
		setReviewValue('review-start-price', formatCurrency(getFieldValue('ca_start_price')));
		setReviewValue('review-increment', formatCurrency(getFieldValue('ca_min_increment')));

		const reservePrice = getFieldValue('ca_reserve_price');
		setReviewValue('review-reserve', reservePrice ? formatCurrency(reservePrice) : 'No reserve');

		const buyNowEnabled = document.getElementById('ca_buy_now_toggle');
		const buyNowPrice = getFieldValue('ca_buy_now_price');
		if (buyNowEnabled && buyNowEnabled.checked && buyNowPrice) {
			setReviewValue('review-buy-now', formatCurrency(buyNowPrice));
		} else {
			setReviewValue('review-buy-now', 'Not available');
		}

		// Schedule
		const startDate = getFieldValue('ca_start_at');
		const endDate = getFieldValue('ca_end_at');
		setReviewValue('review-start', startDate ? formatDateTime(startDate) : 'Now');
		setReviewValue('review-end', endDate ? formatDateTime(endDate) : 'Not set');

		// Calculate duration for review
		if (startDate && endDate) {
			const start = new Date(startDate);
			const end = new Date(endDate);
			const diff = end - start;
			const days = Math.floor(diff / (1000 * 60 * 60 * 24));
			const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

			let durationText = '';
			if (days > 0) durationText += days + 'd ';
			if (hours > 0) durationText += hours + 'h';
			setReviewValue('review-duration', durationText || 'Less than 1 hour');
		}

		// Settings - proxy bidding uses name attribute since it doesn't have an ID
		const proxyBidding = document.querySelector('input[name="ca_proxy_enabled"]');
		setReviewValue('review-proxy', proxyBidding && proxyBidding.checked ? 'Enabled' : 'Disabled');

		const visibility = getSelectText('ca_visibility');
		setReviewValue('review-visibility', visibility || 'Public');
	}

	/**
	 * Get field value by ID
	 */
	function getFieldValue(id) {
		const field = document.getElementById(id);
		return field ? field.value : '';
	}

	/**
	 * Get selected option text
	 */
	function getSelectText(id) {
		const select = document.getElementById(id);
		if (select && select.selectedIndex >= 0) {
			return select.options[select.selectedIndex].text;
		}
		return '';
	}

	/**
	 * Set review section value
	 */
	function setReviewValue(id, value) {
		const element = document.getElementById(id);
		if (element) {
			element.textContent = value || '-';
		}
	}

	/**
	 * Format currency
	 */
	function formatCurrency(value) {
		const num = parseFloat(value);
		if (isNaN(num)) return '-';

		// Use settings currency symbol if available
		const symbol = (window.CommunityAuctions && window.CommunityAuctions.currencySymbol) || '$';
		return symbol + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	/**
	 * Format date/time for display
	 */
	function formatDateTime(dateStr) {
		const date = new Date(dateStr);
		return date.toLocaleString('en-US', {
			month: 'short',
			day: 'numeric',
			year: 'numeric',
			hour: 'numeric',
			minute: '2-digit',
			hour12: true
		});
	}

	/**
	 * Truncate text with ellipsis
	 */
	function truncateText(text, maxLength) {
		if (!text) return '';
		if (text.length <= maxLength) return text;
		return text.substring(0, maxLength) + '...';
	}

	/**
	 * Handle form submission
	 */
	function handleSubmit(e) {
		// Validate all steps before submission
		for (let i = 1; i <= config.totalSteps - 1; i++) {
			if (!validateStep(i)) {
				e.preventDefault();
				goToStep(i);
				return;
			}
		}

		// Show loading state
		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.innerHTML = '<span class="ca-spinner"></span> Creating Auction...';
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();
