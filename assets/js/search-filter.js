/**
 * Auction search and filtering functionality.
 *
 * @package CommunityAuctions
 */

(function () {
	'use strict';

	var container = document.querySelector('.ca-search-container');
	if (!container) {
		return;
	}

	var searchInput = container.querySelector('.ca-search-input');
	var searchBtn = container.querySelector('.ca-search-btn');
	var resultsContainer = container.querySelector('.ca-search-results');
	var paginationContainer = container.querySelector('.ca-search-pagination');
	var resultCount = container.querySelector('.ca-result-count');
	var activeFilters = container.querySelector('.ca-active-filters');

	var perPage = parseInt(container.getAttribute('data-per-page'), 10) || 12;
	var defaultStatus = container.getAttribute('data-default-status') || 'live';

	var filters = {
		q: '',
		category: 0,
		min_price: 0,
		max_price: 0,
		status: defaultStatus,
		ending_soon: false,
		sort: 'ending_soon',
		page: 1,
		per_page: perPage
	};

	var filterOptions = {
		categories: [],
		price_ranges: []
	};

	var debounceTimer = null;

	/**
	 * Initialize search.
	 */
	function init() {
		loadFilterOptions();
		bindEvents();
		performSearch();
	}

	/**
	 * Load filter options from API.
	 */
	function loadFilterOptions() {
		fetch(CaSearch.filtersUrl, {
			method: 'GET',
			headers: {
				'X-WP-Nonce': CaSearch.nonce
			}
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (data) {
				filterOptions = data;
				populateFilterDropdowns(data);
			})
			.catch(function (error) {
				console.error('Failed to load filter options:', error);
			});
	}

	/**
	 * Populate filter dropdown options.
	 *
	 * @param {Object} data Filter options data.
	 */
	function populateFilterDropdowns(data) {
		// Populate categories.
		var categorySelect = container.querySelector('[data-filter="category"]');
		if (categorySelect && data.categories) {
			data.categories.forEach(function (cat) {
				var option = document.createElement('option');
				option.value = cat.id;
				option.textContent = cat.name + ' (' + cat.count + ')';
				categorySelect.appendChild(option);
			});
		}

		// Populate price ranges.
		var priceSelect = container.querySelector('[data-filter="price"]');
		if (priceSelect && data.price_ranges) {
			data.price_ranges.forEach(function (range) {
				var option = document.createElement('option');
				option.value = range.min + '-' + range.max;
				option.textContent = range.label;
				priceSelect.appendChild(option);
			});
		}
	}

	/**
	 * Bind event listeners.
	 */
	function bindEvents() {
		// Search input.
		if (searchInput) {
			searchInput.addEventListener('input', function () {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(function () {
					filters.q = searchInput.value.trim();
					filters.page = 1;
					performSearch();
				}, 300);
			});

			searchInput.addEventListener('keypress', function (e) {
				if (e.key === 'Enter') {
					clearTimeout(debounceTimer);
					filters.q = searchInput.value.trim();
					filters.page = 1;
					performSearch();
				}
			});
		}

		// Search button.
		if (searchBtn) {
			searchBtn.addEventListener('click', function () {
				clearTimeout(debounceTimer);
				filters.q = searchInput ? searchInput.value.trim() : '';
				filters.page = 1;
				performSearch();
			});
		}

		// Filter selects.
		container.querySelectorAll('.ca-filter-select').forEach(function (select) {
			select.addEventListener('change', function () {
				handleFilterChange(select);
			});
		});

		// Filter checkboxes.
		container.querySelectorAll('.ca-filter-checkbox').forEach(function (checkbox) {
			checkbox.addEventListener('change', function () {
				handleCheckboxChange(checkbox);
			});
		});
	}

	/**
	 * Handle filter select change.
	 *
	 * @param {HTMLSelectElement} select Select element.
	 */
	function handleFilterChange(select) {
		var filterType = select.getAttribute('data-filter');
		var value = select.value;

		switch (filterType) {
			case 'category':
				filters.category = parseInt(value, 10) || 0;
				break;

			case 'price':
				if (value) {
					var parts = value.split('-');
					filters.min_price = parseFloat(parts[0]) || 0;
					filters.max_price = parseFloat(parts[1]) || 0;
				} else {
					filters.min_price = 0;
					filters.max_price = 0;
				}
				break;

			case 'status':
				filters.status = value || 'live';
				break;

			case 'sort':
				filters.sort = value || 'ending_soon';
				break;
		}

		filters.page = 1;
		performSearch();
		updateActiveFilters();
	}

	/**
	 * Handle checkbox filter change.
	 *
	 * @param {HTMLInputElement} checkbox Checkbox element.
	 */
	function handleCheckboxChange(checkbox) {
		var filterType = checkbox.getAttribute('data-filter');

		if (filterType === 'ending_soon') {
			filters.ending_soon = checkbox.checked;
		}

		filters.page = 1;
		performSearch();
		updateActiveFilters();
	}

	/**
	 * Update active filters display.
	 */
	function updateActiveFilters() {
		if (!activeFilters) {
			return;
		}

		var tags = [];

		if (filters.q) {
			tags.push(createFilterTag('Search: ' + filters.q, function () {
				filters.q = '';
				if (searchInput) {
					searchInput.value = '';
				}
			}));
		}

		if (filters.category > 0) {
			var catName = getCategoryName(filters.category);
			tags.push(createFilterTag('Category: ' + catName, function () {
				filters.category = 0;
				var select = container.querySelector('[data-filter="category"]');
				if (select) {
					select.value = '';
				}
			}));
		}

		if (filters.min_price > 0 || filters.max_price > 0) {
			var priceLabel = filters.max_price > 0
				? '$' + filters.min_price + ' - $' + filters.max_price
				: 'Over $' + filters.min_price;
			tags.push(createFilterTag('Price: ' + priceLabel, function () {
				filters.min_price = 0;
				filters.max_price = 0;
				var select = container.querySelector('[data-filter="price"]');
				if (select) {
					select.value = '';
				}
			}));
		}

		if (filters.ending_soon) {
			tags.push(createFilterTag('Ending within 24h', function () {
				filters.ending_soon = false;
				var checkbox = container.querySelector('[data-filter="ending_soon"]');
				if (checkbox) {
					checkbox.checked = false;
				}
			}));
		}

		activeFilters.innerHTML = '';
		tags.forEach(function (tag) {
			activeFilters.appendChild(tag);
		});

		if (tags.length > 1) {
			var clearAll = document.createElement('button');
			clearAll.className = 'ca-filter-clear-all';
			clearAll.textContent = 'Clear All';
			clearAll.addEventListener('click', function () {
				clearAllFilters();
			});
			activeFilters.appendChild(clearAll);
		}
	}

	/**
	 * Create a filter tag element.
	 *
	 * @param {string}   label   Tag label.
	 * @param {Function} onRemove Callback when removed.
	 * @return {HTMLElement} Tag element.
	 */
	function createFilterTag(label, onRemove) {
		var tag = document.createElement('span');
		tag.className = 'ca-filter-tag';
		tag.innerHTML = '<span class="ca-filter-tag-label">' + escapeHtml(label) + '</span>' +
			'<button type="button" class="ca-filter-tag-remove" aria-label="Remove filter">&times;</button>';

		tag.querySelector('.ca-filter-tag-remove').addEventListener('click', function () {
			onRemove();
			filters.page = 1;
			performSearch();
			updateActiveFilters();
		});

		return tag;
	}

	/**
	 * Get category name by ID.
	 *
	 * @param {number} id Category ID.
	 * @return {string} Category name.
	 */
	function getCategoryName(id) {
		var cat = filterOptions.categories.find(function (c) {
			return c.id === id;
		});
		return cat ? cat.name : 'Unknown';
	}

	/**
	 * Clear all filters.
	 */
	function clearAllFilters() {
		filters.q = '';
		filters.category = 0;
		filters.min_price = 0;
		filters.max_price = 0;
		filters.ending_soon = false;
		filters.page = 1;

		// Reset form elements.
		if (searchInput) {
			searchInput.value = '';
		}

		container.querySelectorAll('.ca-filter-select').forEach(function (select) {
			if (select.getAttribute('data-filter') !== 'status' && select.getAttribute('data-filter') !== 'sort') {
				select.value = '';
			}
		});

		container.querySelectorAll('.ca-filter-checkbox').forEach(function (checkbox) {
			checkbox.checked = false;
		});

		performSearch();
		updateActiveFilters();
	}

	/**
	 * Perform search request.
	 */
	function performSearch() {
		showLoading();

		var params = new URLSearchParams();

		Object.keys(filters).forEach(function (key) {
			var value = filters[key];
			if (value !== '' && value !== 0 && value !== false) {
				params.append(key, value);
			}
		});

		fetch(CaSearch.restUrl + '?' + params.toString(), {
			method: 'GET',
			headers: {
				'X-WP-Nonce': CaSearch.nonce
			}
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Search failed');
				}
				return response.json();
			})
			.then(function (data) {
				renderResults(data);
				renderPagination(data);
				updateResultCount(data);
			})
			.catch(function (error) {
				console.error('Search error:', error);
				showError();
			});
	}

	/**
	 * Show loading state.
	 */
	function showLoading() {
		resultsContainer.innerHTML = '<div class="ca-search-loading">' +
			'<span class="spinner is-active"></span> ' +
			CaSearch.strings.loading +
			'</div>';
	}

	/**
	 * Show error state.
	 */
	function showError() {
		resultsContainer.innerHTML = '<div class="ca-search-error">' +
			CaSearch.strings.error +
			'</div>';
	}

	/**
	 * Render search results.
	 *
	 * @param {Object} data Search response data.
	 */
	function renderResults(data) {
		if (!data.auctions || data.auctions.length === 0) {
			resultsContainer.innerHTML = '<div class="ca-search-empty">' +
				CaSearch.strings.noResults +
				'</div>';
			return;
		}

		var html = '';

		data.auctions.forEach(function (auction) {
			html += renderAuctionCard(auction);
		});

		resultsContainer.innerHTML = html;
	}

	/**
	 * Render a single auction card.
	 *
	 * @param {Object} auction Auction data.
	 * @return {string} HTML string.
	 */
	function renderAuctionCard(auction) {
		var statusClass = 'ca-status-' + auction.status.replace('ca_', '');
		var urgentClass = auction.time_left_seconds < 3600 ? ' ca-urgent' : '';

		var thumbnail = auction.thumbnail
			? '<img src="' + escapeHtml(auction.thumbnail) + '" alt="' + escapeHtml(auction.title) + '" />'
			: '<div class="ca-no-image"><span class="dashicons dashicons-format-image"></span></div>';

		var bidLabel = auction.bid_count === 1 ? CaSearch.strings.bid : CaSearch.strings.bids;

		var buyNowHtml = '';
		if (auction.buy_now_formatted) {
			buyNowHtml = '<span class="ca-buy-now-badge">' + CaSearch.strings.buyNow + ': ' + escapeHtml(auction.buy_now_formatted) + '</span>';
		}

		var endingBadge = '';
		if (auction.time_left_seconds > 0 && auction.time_left_seconds < 3600) {
			endingBadge = '<span class="ca-ending-badge">' + CaSearch.strings.endingSoon + '</span>';
		}

		return '<article class="ca-search-card ' + statusClass + urgentClass + '">' +
			'<a href="' + escapeHtml(auction.url) + '" class="ca-card-link">' +
			'<div class="ca-card-image">' + thumbnail + endingBadge + '</div>' +
			'<div class="ca-card-content">' +
			'<h3 class="ca-card-title">' + escapeHtml(auction.title) + '</h3>' +
			'<div class="ca-card-price">' + escapeHtml(auction.current_bid_formatted) + '</div>' +
			'<div class="ca-card-meta">' +
			'<span class="ca-card-bids">' + auction.bid_count + ' ' + bidLabel + '</span>' +
			'<span class="ca-card-time" data-ca-countdown="' + auction.end_timestamp + '">' + escapeHtml(auction.time_left) + '</span>' +
			'</div>' +
			buyNowHtml +
			'</div>' +
			'</a>' +
			'</article>';
	}

	/**
	 * Render pagination.
	 *
	 * @param {Object} data Search response data.
	 */
	function renderPagination(data) {
		if (!paginationContainer || data.total_pages <= 1) {
			if (paginationContainer) {
				paginationContainer.innerHTML = '';
			}
			return;
		}

		var html = '<div class="ca-pagination">';

		// Previous button.
		if (data.page > 1) {
			html += '<button type="button" class="ca-page-btn ca-page-prev" data-page="' + (data.page - 1) + '">&laquo; Previous</button>';
		}

		// Page numbers.
		var startPage = Math.max(1, data.page - 2);
		var endPage = Math.min(data.total_pages, data.page + 2);

		if (startPage > 1) {
			html += '<button type="button" class="ca-page-btn" data-page="1">1</button>';
			if (startPage > 2) {
				html += '<span class="ca-page-ellipsis">...</span>';
			}
		}

		for (var i = startPage; i <= endPage; i++) {
			var activeClass = i === data.page ? ' ca-page-active' : '';
			html += '<button type="button" class="ca-page-btn' + activeClass + '" data-page="' + i + '">' + i + '</button>';
		}

		if (endPage < data.total_pages) {
			if (endPage < data.total_pages - 1) {
				html += '<span class="ca-page-ellipsis">...</span>';
			}
			html += '<button type="button" class="ca-page-btn" data-page="' + data.total_pages + '">' + data.total_pages + '</button>';
		}

		// Next button.
		if (data.page < data.total_pages) {
			html += '<button type="button" class="ca-page-btn ca-page-next" data-page="' + (data.page + 1) + '">Next &raquo;</button>';
		}

		html += '</div>';

		paginationContainer.innerHTML = html;

		// Bind pagination events.
		paginationContainer.querySelectorAll('.ca-page-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				filters.page = parseInt(btn.getAttribute('data-page'), 10);
				performSearch();
				scrollToResults();
			});
		});
	}

	/**
	 * Update result count display.
	 *
	 * @param {Object} data Search response data.
	 */
	function updateResultCount(data) {
		if (!resultCount) {
			return;
		}

		var start = ((data.page - 1) * data.per_page) + 1;
		var end = Math.min(data.page * data.per_page, data.total);

		if (data.total === 0) {
			resultCount.textContent = '';
		} else {
			resultCount.textContent = 'Showing ' + start + '-' + end + ' of ' + data.total + ' auctions';
		}
	}

	/**
	 * Scroll to results container.
	 */
	function scrollToResults() {
		var offset = container.getBoundingClientRect().top + window.pageYOffset - 100;
		window.scrollTo({
			top: offset,
			behavior: 'smooth'
		});
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} str Input string.
	 * @return {string} Escaped string.
	 */
	function escapeHtml(str) {
		if (!str) {
			return '';
		}
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
