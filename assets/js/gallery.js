/**
 * Community Auctions - Image Gallery
 *
 * Handles image upload, lightbox, and drag-to-reorder functionality.
 */
(function() {
	'use strict';

	const config = window.CommunityAuctionsGallery || {};

	/**
	 * Initialize gallery functionality.
	 */
	function init() {
		initUploadZones();
		initLightbox();
		initDragReorder();
	}

	/**
	 * Initialize upload zones.
	 */
	function initUploadZones() {
		const zones = document.querySelectorAll('.ca-gallery-upload-zone');

		zones.forEach(function(zone) {
			const input = zone.querySelector('.ca-gallery-file-input');
			const container = zone.closest('.ca-gallery-upload');
			const preview = container ? container.querySelector('.ca-gallery-preview') : null;
			const status = container ? container.querySelector('.ca-gallery-upload-status') : null;

			if (!input) return;

			// Click to open file dialog.
			zone.addEventListener('click', function() {
				input.click();
			});

			// Keyboard accessibility.
			zone.addEventListener('keydown', function(e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					input.click();
				}
			});

			// Drag and drop.
			zone.addEventListener('dragover', function(e) {
				e.preventDefault();
				zone.classList.add('ca-gallery-upload-zone--dragover');
			});

			zone.addEventListener('dragleave', function() {
				zone.classList.remove('ca-gallery-upload-zone--dragover');
			});

			zone.addEventListener('drop', function(e) {
				e.preventDefault();
				zone.classList.remove('ca-gallery-upload-zone--dragover');

				const files = e.dataTransfer.files;
				if (files.length) {
					handleFiles(files, preview, status);
				}
			});

			// File input change.
			input.addEventListener('change', function() {
				if (input.files.length) {
					handleFiles(input.files, preview, status);
					input.value = '';
				}
			});
		});

		// Remove buttons.
		document.addEventListener('click', function(e) {
			if (e.target.classList.contains('ca-gallery-remove')) {
				e.preventDefault();
				const item = e.target.closest('.ca-gallery-preview-item');
				if (item && confirm(config.i18n?.confirm || 'Remove this image?')) {
					item.remove();
				}
			}
		});
	}

	/**
	 * Handle file uploads.
	 *
	 * @param {FileList} files   Files to upload.
	 * @param {Element}  preview Preview container.
	 * @param {Element}  status  Status element.
	 */
	function handleFiles(files, preview, status) {
		const maxFiles = config.maxFiles || 10;
		const maxSize = config.maxFileSize || 10485760;
		const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

		const currentCount = preview ? preview.querySelectorAll('.ca-gallery-preview-item').length : 0;
		let uploadCount = 0;

		Array.from(files).forEach(function(file) {
			// Check max files.
			if (currentCount + uploadCount >= maxFiles) {
				showStatus(status, config.i18n?.maxFiles || 'Maximum 10 images allowed.', 'error');
				return;
			}

			// Check file type.
			if (!allowedTypes.includes(file.type)) {
				showStatus(status, config.i18n?.invalidType || 'Only images are allowed.', 'error');
				return;
			}

			// Check file size.
			if (file.size > maxSize) {
				showStatus(status, config.i18n?.maxSize || 'File is too large.', 'error');
				return;
			}

			uploadCount++;
			uploadFile(file, preview, status);
		});
	}

	/**
	 * Upload a single file.
	 *
	 * @param {File}    file    File to upload.
	 * @param {Element} preview Preview container.
	 * @param {Element} status  Status element.
	 */
	function uploadFile(file, preview, status) {
		showStatus(status, config.i18n?.uploading || 'Uploading...', 'loading');

		const formData = new FormData();
		formData.append('file', file);

		fetch(config.uploadUrl, {
			method: 'POST',
			headers: {
				'X-WP-Nonce': config.nonce
			},
			body: formData
		})
		.then(function(response) {
			return response.json();
		})
		.then(function(data) {
			if (data.success && data.data) {
				addPreviewItem(preview, data.data);
				showStatus(status, '', 'success');
			} else {
				showStatus(status, data.message || config.i18n?.uploadError || 'Upload failed.', 'error');
			}
		})
		.catch(function() {
			showStatus(status, config.i18n?.uploadError || 'Upload failed. Please try again.', 'error');
		});
	}

	/**
	 * Add preview item after successful upload.
	 *
	 * @param {Element} preview Preview container.
	 * @param {Object}  data    Upload response data.
	 */
	function addPreviewItem(preview, data) {
		if (!preview) return;

		const item = document.createElement('li');
		item.className = 'ca-gallery-preview-item';
		item.setAttribute('data-id', data.id);
		item.innerHTML =
			'<img src="' + escapeHtml(data.thumbnail) + '" alt="" />' +
			'<input type="hidden" name="ca_gallery_ids[]" value="' + escapeHtml(data.id) + '" />' +
			'<button type="button" class="ca-gallery-remove" aria-label="Remove image">&times;</button>' +
			'<span class="ca-gallery-drag-handle" aria-label="Drag to reorder">&equiv;</span>';

		preview.appendChild(item);
	}

	/**
	 * Show status message.
	 *
	 * @param {Element} status  Status element.
	 * @param {string}  message Message to show.
	 * @param {string}  type    Message type (loading, success, error).
	 */
	function showStatus(status, message, type) {
		if (!status) return;

		status.textContent = message;
		status.className = 'ca-gallery-upload-status';

		if (type) {
			status.classList.add('ca-gallery-upload-status--' + type);
		}
	}

	/**
	 * Initialize lightbox functionality.
	 */
	function initLightbox() {
		const galleries = document.querySelectorAll('.ca-gallery');

		galleries.forEach(function(gallery) {
			const auctionId = gallery.dataset.auctionId;
			const lightbox = document.getElementById('ca-lightbox-' + auctionId);

			if (!lightbox) return;

			const links = gallery.querySelectorAll('.ca-gallery-link');
			const image = lightbox.querySelector('.ca-lightbox-image');
			const caption = lightbox.querySelector('.ca-lightbox-caption');
			const closeBtn = lightbox.querySelector('.ca-lightbox-close');
			const prevBtn = lightbox.querySelector('.ca-lightbox-prev');
			const nextBtn = lightbox.querySelector('.ca-lightbox-next');
			const overlay = lightbox.querySelector('.ca-lightbox-overlay');

			let currentIndex = 0;
			const images = Array.from(links).map(function(link) {
				return {
					url: link.href,
					title: link.title || ''
				};
			});

			function openLightbox(index) {
				currentIndex = index;
				updateLightboxImage();
				lightbox.setAttribute('aria-hidden', 'false');
				lightbox.classList.add('ca-lightbox--open');
				document.body.classList.add('ca-lightbox-open');
				closeBtn.focus();
			}

			function closeLightbox() {
				lightbox.setAttribute('aria-hidden', 'true');
				lightbox.classList.remove('ca-lightbox--open');
				document.body.classList.remove('ca-lightbox-open');
			}

			function updateLightboxImage() {
				if (images[currentIndex]) {
					image.src = images[currentIndex].url;
					image.alt = images[currentIndex].title;
					caption.textContent = images[currentIndex].title;
				}
			}

			function showPrev() {
				currentIndex = (currentIndex - 1 + images.length) % images.length;
				updateLightboxImage();
			}

			function showNext() {
				currentIndex = (currentIndex + 1) % images.length;
				updateLightboxImage();
			}

			// Click handlers.
			links.forEach(function(link, index) {
				link.addEventListener('click', function(e) {
					e.preventDefault();
					openLightbox(index);
				});
			});

			if (closeBtn) {
				closeBtn.addEventListener('click', closeLightbox);
			}

			if (overlay) {
				overlay.addEventListener('click', closeLightbox);
			}

			if (prevBtn) {
				prevBtn.addEventListener('click', showPrev);
			}

			if (nextBtn) {
				nextBtn.addEventListener('click', showNext);
			}

			// Keyboard navigation.
			lightbox.addEventListener('keydown', function(e) {
				if (e.key === 'Escape') {
					closeLightbox();
				} else if (e.key === 'ArrowLeft') {
					showPrev();
				} else if (e.key === 'ArrowRight') {
					showNext();
				}
			});
		});
	}

	/**
	 * Initialize drag-to-reorder functionality.
	 */
	function initDragReorder() {
		const previews = document.querySelectorAll('.ca-gallery-preview');

		previews.forEach(function(preview) {
			let draggedItem = null;

			preview.addEventListener('dragstart', function(e) {
				if (e.target.classList.contains('ca-gallery-preview-item')) {
					draggedItem = e.target;
					e.target.classList.add('ca-gallery-preview-item--dragging');
				}
			});

			preview.addEventListener('dragend', function(e) {
				if (e.target.classList.contains('ca-gallery-preview-item')) {
					e.target.classList.remove('ca-gallery-preview-item--dragging');
					draggedItem = null;
				}
			});

			preview.addEventListener('dragover', function(e) {
				e.preventDefault();
				const afterElement = getDragAfterElement(preview, e.clientY);
				if (draggedItem) {
					if (afterElement === null) {
						preview.appendChild(draggedItem);
					} else {
						preview.insertBefore(draggedItem, afterElement);
					}
				}
			});

			// Make items draggable.
			preview.querySelectorAll('.ca-gallery-preview-item').forEach(function(item) {
				item.setAttribute('draggable', 'true');
			});
		});

		// Watch for new items.
		const observer = new MutationObserver(function(mutations) {
			mutations.forEach(function(mutation) {
				mutation.addedNodes.forEach(function(node) {
					if (node.classList && node.classList.contains('ca-gallery-preview-item')) {
						node.setAttribute('draggable', 'true');
					}
				});
			});
		});

		previews.forEach(function(preview) {
			observer.observe(preview, { childList: true });
		});
	}

	/**
	 * Get element after which to insert dragged item.
	 *
	 * @param {Element} container Container element.
	 * @param {number}  y         Mouse Y position.
	 * @return {Element|null} Element to insert before, or null for end.
	 */
	function getDragAfterElement(container, y) {
		const items = Array.from(container.querySelectorAll('.ca-gallery-preview-item:not(.ca-gallery-preview-item--dragging)'));

		return items.reduce(function(closest, child) {
			const box = child.getBoundingClientRect();
			const offset = y - box.top - box.height / 2;

			if (offset < 0 && offset > closest.offset) {
				return { offset: offset, element: child };
			}

			return closest;
		}, { offset: Number.NEGATIVE_INFINITY }).element || null;
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} str String to escape.
	 * @return {string} Escaped string.
	 */
	function escapeHtml(str) {
		const div = document.createElement('div');
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
