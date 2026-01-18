/* global CommunityAuctions */
document.addEventListener('submit', function (event) {
  var form = event.target;
  if (!form.classList.contains('community-auction-bid-form')) {
    return;
  }

  event.preventDefault();

  var wrapper = form.closest('.community-auction-single');
  var messageEl = form.querySelector('.ca-bid-message');
  var amountInput = form.querySelector('input[name="amount"]');
  var proxyInput = form.querySelector('input[name="proxy_max"]');
  var auctionId = wrapper ? wrapper.getAttribute('data-auction-id') : null;

  if (!auctionId) {
    return;
  }

  var payload = {
    auction_id: parseInt(auctionId, 10),
    amount: amountInput ? parseFloat(amountInput.value || '0') : 0
  };

  if (proxyInput && proxyInput.value) {
    payload.proxy_max = parseFloat(proxyInput.value);
  }

  if (messageEl) {
    messageEl.setAttribute('role', 'status');
    messageEl.textContent = 'Submitting bid...';
  }

  fetch(CommunityAuctions.restUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': CommunityAuctions.nonce
    },
    body: JSON.stringify(payload)
  })
    .then(function (response) {
      return response.json().then(function (data) {
        return { ok: response.ok, data: data };
      });
    })
    .then(function (result) {
      if (!result.ok || !result.data.success) {
        if (amountInput) {
          amountInput.setAttribute('aria-invalid', 'true');
        }
        if (proxyInput) {
          proxyInput.removeAttribute('aria-invalid');
        }
        if (messageEl) {
          messageEl.setAttribute('role', 'alert');
          messageEl.textContent = result.data && result.data.message ? result.data.message : 'Bid failed.';
        }
        return;
      }

      if (messageEl) {
        messageEl.setAttribute('role', 'status');
        messageEl.textContent = 'Bid placed successfully.';
        if (result.data && result.data.data && String(result.data.data.current_highest_bidder) === String(CommunityAuctions.currentUserId)) {
          messageEl.textContent = 'Bid placed. You are now the highest bidder.';
        }
      }
      if (amountInput) {
        amountInput.removeAttribute('aria-invalid');
      }
      if (proxyInput) {
        proxyInput.removeAttribute('aria-invalid');
      }

      var currentBidEl = wrapper.querySelector('.ca-current-bid');
      if (currentBidEl && result.data.data && result.data.data.current_highest) {
        currentBidEl.textContent = result.data.data.current_highest;
      }
    })
    .catch(function () {
      if (amountInput) {
        amountInput.setAttribute('aria-invalid', 'true');
      }
      if (messageEl) {
        messageEl.setAttribute('role', 'alert');
        messageEl.textContent = 'Bid failed.';
      }
    });
});

document.addEventListener('click', function (event) {
  // Buy Now button handler.
  var buyNowBtn = event.target.closest ? event.target.closest('.ca-buy-now-button') : null;
  if (buyNowBtn) {
    event.preventDefault();

    var auctionId = buyNowBtn.getAttribute('data-auction-id');
    var price = buyNowBtn.getAttribute('data-price');
    var title = buyNowBtn.getAttribute('data-title') || 'this auction';

    if (!auctionId) {
      return;
    }

    /* eslint-disable no-alert */
    var confirmed = window.confirm(
      'Are you sure you want to buy "' + title + '" now for ' + price + '?\n\n' +
      'This will end the auction immediately and you will be taken to complete payment.'
    );
    /* eslint-enable no-alert */

    if (!confirmed) {
      return;
    }

    buyNowBtn.disabled = true;
    buyNowBtn.textContent = 'Processing...';

    var buyNowUrl = CommunityAuctions.restBase + 'auctions/' + auctionId + '/buy-now';

    fetch(buyNowUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': CommunityAuctions.nonce
      }
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.data.success) {
          buyNowBtn.disabled = false;
          buyNowBtn.textContent = 'Buy It Now';
          /* eslint-disable no-alert */
          window.alert(result.data && result.data.message ? result.data.message : 'Purchase failed. Please try again.');
          /* eslint-enable no-alert */
          return;
        }

        // Redirect to payment URL if provided.
        if (result.data.data && result.data.data.payment_url) {
          window.location.href = result.data.data.payment_url;
        } else {
          // Reload page to show ended state.
          window.location.reload();
        }
      })
      .catch(function () {
        buyNowBtn.disabled = false;
        buyNowBtn.textContent = 'Buy It Now';
        /* eslint-disable no-alert */
        window.alert('Purchase failed. Please try again.');
        /* eslint-enable no-alert */
      });

    return;
  }

  var link = event.target.closest ? event.target.closest('.community-auction-bid-link') : null;
  if (!link) {
    link = event.target.closest ? event.target.closest('.ca-legend-toggle') : null;
    if (!link) {
      return;
    }

    var tooltip = document.getElementById('ca-legend-tooltip');
    if (!tooltip) {
      return;
    }

    var expanded = link.getAttribute('aria-expanded') === 'true';
    link.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    tooltip.hidden = expanded;
    return;
  }

  var targetId = link.getAttribute('href');
  if (!targetId || targetId.charAt(0) !== '#') {
    return;
  }

  var target = document.querySelector(targetId);
  if (!target) {
    return;
  }

  event.preventDefault();
  target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  var focusTarget = target.querySelector('input, button, textarea, select');
  if (focusTarget && focusTarget.focus) {
    focusTarget.focus({ preventScroll: true });
  }
});
