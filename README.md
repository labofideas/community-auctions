# Community Auctions

A WordPress plugin for running member-driven auctions inside BuddyPress communities.

Community Auctions lets users create listings, bid in real time, follow auctions, and complete checkout through WooCommerce or FluentCart.

## Why This Plugin Exists

Most auction plugins are built for marketplace sites where one store owner controls everything.
This plugin is built for community sites where members create and bid on each other's listings.

## Current Status

- Version: `0.1.0`
- WordPress: `6.0+`
- PHP: `7.4+`
- Payments: WooCommerce and FluentCart
- BuddyPress: Optional, but recommended for profile/activity integration

## Core Features

- Frontend auction creation and bidding
- Real-time bid refresh (AJAX polling)
- Buy Now support for instant purchase
- Watchlist and ending-soon reminders
- Seller dashboard (manage active and ended listings)
- Buyer dashboard (won auctions and payment state)
- Search and filtering tools
- Countdown timers with timezone-aware display
- Email notifications (outbid, won, reminders)
- BuddyPress activity + member auction screens
- Gutenberg blocks and shortcodes

## Shortcodes

| Shortcode | Purpose |
| --- | --- |
| `[community_auctions_list]` | Show auction listings |
| `[community_auctions_search]` | Show search + filters |
| `[community_auctions_upcoming]` | Show upcoming auctions |
| `[community_auction_watchlist]` | Show logged-in user watchlist |
| `[community_auction_seller_dashboard]` | Seller management view |
| `[community_auction_buyer_dashboard]` | Buyer purchases/wins view |

## Installation

1. Copy the plugin folder to `wp-content/plugins/community-auctions`
2. Activate **Community Auctions** in WordPress admin
3. Go to `Settings > Community Auctions` and configure permissions/options
4. Add shortcodes to pages (listing, search, seller dashboard, buyer dashboard)
5. Configure WooCommerce or FluentCart if payments are required

## Quick Start (Recommended Page Setup)

Create these pages and place one shortcode on each:

- Auctions: `[community_auctions_list]`
- Search Auctions: `[community_auctions_search]`
- My Watchlist: `[community_auction_watchlist]`
- Seller Dashboard: `[community_auction_seller_dashboard]`
- Buyer Dashboard: `[community_auction_buyer_dashboard]`

## BuddyPress Integration

When BuddyPress is active, the plugin adds:

- Activity entries for auction events
- Member profile auction tabs
- Auction-related notifications
- Group-level auction support (where enabled)

## REST API

Namespace: `community-auctions/v1`

Use the REST endpoints for custom frontend apps or integrations with external systems.

## Development

### JavaScript build

```bash
npm install
npm run build
```

### Watch mode

```bash
npm run start
```

### E2E tests

```bash
npm run test:e2e
```

### PHPUnit tests

```bash
composer install
composer run test:phpunit
```

## Roadmap

- Improve auction moderation workflow
- Expand payment/reconciliation reporting
- Add more block-level customization options
- Improve automated test coverage across payment flows

## Contributing

Issues and pull requests are welcome.
Please include reproduction steps, expected behavior, and environment details in bug reports.

## Changelog

See [CHANGELOG.md](./CHANGELOG.md).

## License

GPL-2.0-or-later

## Maintainer

[Wbcom Designs](https://wbcomdesigns.com)
