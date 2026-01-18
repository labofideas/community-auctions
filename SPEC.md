# Community Auctions - Scope and Plan

## Goal
Build a BuddyPress-compatible auction plugin for community sites with optional WooCommerce or FluentCart payment handling. Support front-end auction creation, group and profile views, optional proxy bidding, and configurable fees/approval.

## Assumptions
- WordPress 6.0+ and PHP 8.0+.
- BuddyPress installed and active.
- One payment provider active at a time (WooCommerce or FluentCart).
- FluentCart free plugin is installed on this site.

## Key Requirements (Confirmed)
- Front-end auction creation by members.
- Auctions can appear in BuddyPress profiles and groups.
- Group visibility toggle per auction (group-only vs public).
- Proxy bidding (auto-bid) optional per auction.
- Widget or dashboard panel to show current auctions.
- Payments handled by WooCommerce or FluentCart.
- If both WooCommerce and FluentCart active, show provider selection notice and block bidding until selected.
- Admin approval optional.
- Auction fees optional, with flat or percentage mode.

## Functional Scope

### Core Auction Engine
- Auction lifecycle: draft -> pending -> live -> ended -> closed/expired.
- Anti-sniping: extend end time by N minutes if bid placed within N minutes.
- Reserve price support.
- Buy Now (optional; phase 2).
- Highest bid tracking with conflict-safe updates.

### Bidding
- Validation: minimum increment, bidder eligibility, auction status.
- Proxy bidding (optional): max bid stored, auto-increment to outbid.
- Rate-limit bidding actions to prevent spam.

### BuddyPress Integration
- Activity items on auction creation, bid placed, auction ended, winner.
- Notifications for outbid, win, and payment reminders.
- Profile tab: My Auctions, My Bids.
- Group tab: Auctions list and create form (optional per group).

### Payments (Adapters)
- WooCommerce (default): create order for winner at auction end and send to checkout.
- FluentCart (optional): create draft order and redirect to FluentCart checkout.
- Provider selection (when both active) stored in plugin settings.

### Fees
- Listing fee (flat or percentage).
- Success fee (flat or percentage).
- Fee collection via provider order line items.

### Moderation
- Optional admin approval before auction goes live.
- Optional group admin approval for group auctions.

### Widgets / Dashboard
- Live Auctions widget (sidebar/block).
- Member dashboard panel for My Auctions and My Bids.

## Data Model

### Custom Post Type
- `auction` CPT

### Custom Table (recommended for scale)
- `wp_ca_bids`
  - id (bigint)
  - auction_id (bigint, indexed)
  - user_id (bigint, indexed)
  - amount (decimal, indexed)
  - max_proxy_amount (decimal, nullable)
  - is_proxy (tinyint)
  - created_at (datetime)

### Meta Keys (auction)
- `ca_start_at`, `ca_end_at`
- `ca_reserve_price`, `ca_buy_now_price`
- `ca_min_increment`
- `ca_proxy_enabled`
- `ca_visibility` (group_only|public)
- `ca_group_id`
- `ca_seller_id`
- `ca_payment_provider` (woocommerce|fluentcart)
- `ca_status` (draft|pending|live|ended|closed|expired)

## Plugin Architecture

### File Structure
- `community-auctions.php` (bootstrap)
- `includes/`
  - `class-auction-cpt.php`
  - `class-bid-repository.php`
  - `class-auction-engine.php`
  - `class-buddypress-integration.php`
  - `class-frontend-forms.php`
  - `class-payment-adapter.php`
  - `class-payment-woocommerce.php`
  - `class-payment-fluentcart.php`
  - `class-settings.php`
  - `class-widgets.php`
  - `class-cron.php`
- `templates/`
  - `single-auction.php`
  - `archive-auction.php`
  - `bp-member-auctions.php`
  - `bp-group-auctions.php`
  - `auction-submit.php`
- `assets/`
  - `css/auction.css`
  - `js/auction.js`

## Key Hooks and Touchpoints

### BuddyPress
- `bp_activity_add()` for activity stream.
- `bp_notifications_add_notification()` for outbid and win.
- `bp_core_new_nav_item()` for member tab.
- `groups_register_group_type()` or group meta for group settings.

### WooCommerce
- `wc_create_order()` to generate winner order.
- `woocommerce_order_status_changed` to finalize auction on payment.
- Use Action Scheduler for auction end tasks.

### FluentCart (found in codebase)
- Create order via `FluentCart\App\Helpers\CheckoutProcessor::createDraftOrder()`.
- Listen for `fluent_cart/order_paid_done` to close auctions.
- Use `fluent_cart/payment_status_changed` for payment updates.

## Provider Selection Logic
1. Detect active providers on init.
2. If both active and no selection:
   - Show admin notice and block bidding.
3. Store selection in plugin settings.
4. Per-auction override optional but default to global provider.

## Permissions and Security
- Capabilities: `ca_create_auction`, `ca_place_bid`, `ca_manage_auctions`.
- Nonces for all front-end actions.
- Sanitize inputs, escape outputs.
- Atomic bid updates (single query lock on highest bid).

## Performance
- Bid table indexed by auction_id and amount.
- Cache highest bid and bid count in post meta or transient.
- Batch close auctions via cron.

## UI/UX (Front-End)
- Auction creation form with fields and group visibility toggle.
- Single auction page with current bid, countdown, bid form.
- Dashboard panels for My Auctions/My Bids.

## Implementation Plan

### Phase 1 (MVP)
- CPT + bids table + settings page.
- Front-end create form (members + groups).
- Bidding engine + anti-sniping + proxy option.
- BuddyPress activity + notifications.
- WooCommerce adapter for payments.
- Widget + basic dashboard.

### Phase 2
- FluentCart adapter.
- Fees + approval workflow.
- Advanced filters/search.
- Email templates.

### Phase 3
- Reports/analytics.
- REST API endpoints.
- Escrow/holds (if gateway supports).

## Open Items
- Confirm FluentCart checkout URL format for redirect.
- Confirm group permission rules (group members only vs public).
- Finalize fee collection rules (listing vs success fee timing).
