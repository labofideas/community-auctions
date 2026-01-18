# WP-CLI Helpers for Community Auctions

## Create Auction (quick)
```
wp post create --post_type=auction --post_title="Test Auction" --post_status=ca_live
```

## Set Auction Meta
```
wp post meta update <auction_id> ca_end_at "$(date -u '+%Y-%m-%d %H:%M:%S')"
wp post meta update <auction_id> ca_min_increment 1
wp post meta update <auction_id> ca_start_price 10
```

## Trigger Cron
```
wp cron event run community_auctions/close_auctions
```

## List Cron Events
```
wp cron event list | grep community_auctions
```
