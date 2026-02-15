=== Community Auctions ===

Contributors: wbcomdesigns
Tags: auctions, buddypress, bidding, ecommerce
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

# Community Auctions

A BuddyPress-compatible auction plugin for WordPress with WooCommerce or FluentCart payment integration.

## Description

Community Auctions enables your community members to create and participate in auctions. Built with BuddyPress integration, it provides a seamless auction experience within your community platform.

## Features

- **Auction Management** - Create, edit, and manage auctions with ease
- **BuddyPress Integration** - Member profiles, activity stream, notifications
- **Real-time Updates** - Live bid updates without page refresh
- **Payment Integration** - WooCommerce and FluentCart support
- **Watchlist** - Follow auctions and get notified
- **Buy It Now** - Optional instant purchase option
- **Image Gallery** - Multiple images per auction with lightbox
- **Search & Filter** - AJAX-powered search with filters
- **Seller Dashboard** - Manage your auctions and track earnings
- **Buyer Dashboard** - View won auctions and payment status
- **Email Notifications** - Outbid, won, payment reminders
- **Countdown Timers** - Visual countdown with urgency states
- **Currency Support** - Configurable currency formatting
- **Timezone Handling** - Proper timezone support

## Requirements

- WordPress 6.0+
- PHP 7.4+
- BuddyPress (recommended)
- WooCommerce or FluentCart (for payments)

## Installation

1. Upload the `community-auctions` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under Settings > Community Auctions
4. (Optional) Set up WooCommerce or FluentCart for payments

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[community_auctions_list]` | Display auction listings |
| `[community_auctions_search]` | Search and filter auctions |
| `[community_auctions_upcoming]` | Show upcoming auctions |
| `[community_auction_watchlist]` | User's watchlist |
| `[community_auction_seller_dashboard]` | Seller management dashboard |
| `[community_auction_buyer_dashboard]` | Buyer purchases dashboard |

## REST API

The plugin provides a comprehensive REST API under the `community-auctions/v1` namespace.

## BuddyPress Integration

When BuddyPress is active:
- Auction activity appears in the activity stream
- Member profile tabs for auctions and bids
- BuddyPress notifications for auction events
- Group auction support

## License

GPL-2.0+

## Author

[Wbcom Designs](https://wbcomdesigns.com)
