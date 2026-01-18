/**
 * Community Auctions - Admin Panel Scripts
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		// Clear cache button.
		$('#ca-clear-cache').on('click', function () {
			var $btn = $(this);
			var originalText = $btn.html();

			$btn.prop('disabled', true).html(
				'<span class="dashicons dashicons-update spin"></span> ' + CaAdmin.strings.clearing
			);

			$.ajax({
				url: CaAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ca_clear_cache',
					nonce: CaAdmin.nonce
				},
				success: function (response) {
					if (response.success) {
						$btn.html(
							'<span class="dashicons dashicons-yes"></span> ' + CaAdmin.strings.cleared
						);
						setTimeout(function () {
							$btn.html(originalText).prop('disabled', false);
						}, 2000);
					} else {
						alert(response.data.message || CaAdmin.strings.error);
						$btn.html(originalText).prop('disabled', false);
					}
				},
				error: function () {
					alert(CaAdmin.strings.error);
					$btn.html(originalText).prop('disabled', false);
				}
			});
		});

		// Recalculate counters button.
		$('#ca-recalculate-counters').on('click', function () {
			var $btn = $(this);
			var originalText = $btn.html();

			$btn.prop('disabled', true).html(
				'<span class="dashicons dashicons-update spin"></span> ' + CaAdmin.strings.recalculating
			);

			$.ajax({
				url: CaAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ca_recalculate_counters',
					nonce: CaAdmin.nonce
				},
				success: function (response) {
					if (response.success) {
						$btn.html(
							'<span class="dashicons dashicons-yes"></span> ' + CaAdmin.strings.recalculated
						);
						setTimeout(function () {
							$btn.html(originalText).prop('disabled', false);
						}, 2000);
					} else {
						alert(response.data.message || CaAdmin.strings.error);
						$btn.html(originalText).prop('disabled', false);
					}
				},
				error: function () {
					alert(CaAdmin.strings.error);
					$btn.html(originalText).prop('disabled', false);
				}
			});
		});

		// Add spinning animation style.
		if (!$('#ca-spin-style').length) {
			$('head').append(
				'<style id="ca-spin-style">' +
				'.spin { animation: ca-spin 1s linear infinite; }' +
				'@keyframes ca-spin { 100% { transform: rotate(360deg); } }' +
				'</style>'
			);
		}
	});
})(jQuery);
