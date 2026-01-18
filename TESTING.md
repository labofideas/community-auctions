# Community Auctions - Testing Checklist

## Prerequisites
- BuddyPress active.
- Either WooCommerce or FluentCart active (not both unless provider is chosen in settings).
- WP debug enabled for local testing (optional):
  - `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`.

## Manual Functional Tests

### Settings
- Select payment provider in Community Auctions settings.
- Set anti-sniping minutes, reminder hours, fee modes.
- Save and confirm values persist after reload.

### Auction Creation
- Use `[community_auction_submit]` on a page.
- Submit auction with required fields.
- Verify status:
  - `ca_live` if approval off
  - `ca_pending` if approval on
- Check meta saved: start/end, reserve, increment, visibility, proxy.

### Bidding (REST)
- Place bid via UI on `[community_auction_single]`.
- Confirm bid validation (min increment).
- Check current bid updates in UI.
- Verify outbid notification to previous bidder.

### BuddyPress
- Member tab: Auctions + My Bids.
- Group tab: Auctions visible.
- Group restriction toggle (admin/mod):
  - When enabled, new auctions forced to group-only.
- Notifications preferences saved and reflected.

### List & Single Views
- `[community_auctions_list]`:
  - `status=live|ended`
  - `ending=ending_soon`
  - `min_bid`
  - `paged`
- Group-only badge shows when enabled.

### Cron & Closing
- Set end time in the past and run cron (or wait).
- Auction status changes to `ca_ended`.
- Winner stored (`ca_winner_id`).
- Order created with provider and `ca_order_id` saved.

### Reminder
- Set reminder hours to 1, close auction, wait.
- Confirm reminder fires:
  - BuddyPress notification
  - Email (WooCommerce order note or FluentCart Mailer).

## WooCommerce-Specific Tests
- Order created with “Auction #ID” fee item.
- Order status pending.
- Payment complete marks order as paid (manual).
- Reminder should stop after payment.

## FluentCart-Specific Tests
- Draft order created via `CheckoutProcessor`.
- Confirm order exists and payment flow reachable.
- Reminder sends via FluentCart Mailer if order unpaid.

## Debug Checks
- Inspect `wp-content/debug.log` for PHP notices.
- Use Query Monitor if installed to inspect queries and REST calls.

## E2E (Optional)
- Playwright scenario:
  1) Login
  2) Create auction
  3) Place bid
  4) Verify notification badge
  5) Trigger cron close and validate order

## Automated Tests

### PHPUnit Unit Tests
- Location: `tests/phpunit/`
- Config: `phpunit.xml`

#### Test Files

| File | Class Under Test | Tests |
|------|-----------------|-------|
| `test-auction-cpt.php` | Auction CPT | Post type registration |
| `test-bid-endpoint.php` | Auction Engine REST | Auth, nonces, capabilities |
| `test-bid-repository.php` | Bid Repository | Insert, get, count, pagination |
| `test-settings.php` | Settings | Defaults, sanitization, validation |
| `test-auction-engine.php` | Auction Engine | Bid placement, validation, hooks |
| `test-currency.php` | Currency | Formatting, parsing, symbols |
| `test-watchlist.php` | Watchlist | Add, remove, toggle, counts |
| `test-buy-now.php` | Buy Now | Availability, pricing, permissions |
| `test-fluentcart-webhook.php` | FluentCart Integration | Webhook handling |

#### Running Tests

```bash
# Install WordPress test suite (one-time setup)
bin/install-wp-tests.sh wordpress_test root password localhost latest

# Run all tests
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit

# Run specific test file
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit tests/phpunit/test-bid-repository.php

# Run specific test method
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit --filter test_insert_bid_returns_id

# Run with coverage (requires Xdebug)
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit --coverage-html coverage/
```

#### Test Coverage Summary

- **Bid Repository**: 14 tests covering CRUD operations, pagination, counting
- **Settings**: 13 tests covering sanitization, defaults, validation
- **Auction Engine**: 14 tests covering bid placement, validation, hooks
- **Currency**: 21 tests covering formatting, parsing, symbols, positions
- **Watchlist**: 18 tests covering add/remove, toggle, counting
- **Buy Now**: 19 tests covering availability, pricing, permissions

### Playwright
- Location: `wp-content/plugins/community-auctions/tests/e2e`
- Config: `wp-content/plugins/community-auctions/playwright.config.ts`
- Install:
  - `npm install` (from plugin directory)
- Run:
  - `E2E_BASE_URL=http://your-site.test E2E_USER=admin E2E_PASS=pass E2E_AUCTIONS_LIST_URL=/auctions/ npm run test:e2e`
  - Optional: `E2E_AUCTION_URL=/auction/sample-auction`

### WP-CLI Helpers
- Location: `wp-content/plugins/community-auctions/tests/wp-cli-helpers.md`
