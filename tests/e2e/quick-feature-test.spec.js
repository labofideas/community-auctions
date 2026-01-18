/**
 * Quick Community Auctions Feature Test
 *
 * Tests core features with real auction data
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');

const SITE_URL = 'http://community-hashtags.local';
const SCREENSHOTS_DIR = '/tmp/auction-feature-test';
const ADMIN_USER = 'Steve';
const ADMIN_PASS = 'Steve';

// Create screenshots directory
if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

let step = 1;

async function screenshot(page, name) {
    const filename = `${SCREENSHOTS_DIR}/${step.toString().padStart(2, '0')}-${name}.png`;
    await page.screenshot({ path: filename, fullPage: true });
    console.log(`  📸 ${step.toString().padStart(2, '0')}-${name}.png`);
    step++;
}

test.describe('Community Auctions Feature Test', () => {

    test('Complete auction flow test', async ({ page }) => {
        console.log('\n' + '═'.repeat(60));
        console.log('COMMUNITY AUCTIONS - FEATURE TEST');
        console.log('═'.repeat(60));

        // ===== PART 1: ARCHIVE PAGE =====
        console.log('\n[1] Testing Auctions Archive...');
        await page.goto(`${SITE_URL}/auctions/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'archive-page');

        const archiveContent = await page.content();
        const hasAuctions = archiveContent.includes('Vintage Watch') || archiveContent.includes('Gaming Console');
        console.log(`  Archive shows auctions: ${hasAuctions ? '✅' : '❌'}`);

        // ===== PART 2: SINGLE LIVE AUCTION (VISITOR) =====
        console.log('\n[2] Testing Single Auction (Visitor)...');
        await page.goto(`${SITE_URL}/auctions/hot-item-gaming-console/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'single-auction-visitor');

        // Check for auction elements
        const pageContent = await page.content();
        const elements = {
            'Status badge': pageContent.includes('ca-auction-status') || pageContent.includes('LIVE'),
            'Price display': pageContent.includes('$') || pageContent.includes('ca-price'),
            'Login notice': pageContent.includes('log in') || pageContent.includes('login'),
            'Seller info': pageContent.includes('Seller') || pageContent.includes('seller'),
            'Bid history': pageContent.includes('Bid History') || pageContent.includes('bid-history')
        };

        console.log('  Page elements:');
        for (const [name, found] of Object.entries(elements)) {
            console.log(`    - ${name}: ${found ? '✅' : '❌'}`);
        }

        // ===== PART 3: LOGIN =====
        console.log('\n[3] Logging in...');
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('domcontentloaded');

        const loginSuccess = page.url().includes('wp-admin');
        console.log(`  Login: ${loginSuccess ? '✅' : '❌'}`);
        await screenshot(page, 'after-login');

        // ===== PART 4: SINGLE AUCTION (LOGGED IN) =====
        console.log('\n[4] Testing Single Auction (Logged In)...');
        await page.goto(`${SITE_URL}/auctions/live-auction-vintage-watch/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'single-auction-logged-in');

        const loggedInContent = await page.content();
        const loggedInElements = {
            'Bid form': loggedInContent.includes('bid-form') || loggedInContent.includes('Place Bid'),
            'Bid input': loggedInContent.includes('bid_amount') || loggedInContent.includes('bid-amount'),
            'Minimum bid hint': loggedInContent.includes('Minimum') || loggedInContent.includes('minimum')
        };

        console.log('  Logged-in elements:');
        for (const [name, found] of Object.entries(loggedInElements)) {
            console.log(`    - ${name}: ${found ? '✅' : '❌'}`);
        }

        // ===== PART 5: ENDED AUCTION =====
        console.log('\n[5] Testing Ended Auction...');
        await page.goto(`${SITE_URL}/auctions/ended-rare-collectible/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'ended-auction');

        const endedContent = await page.content();
        const hasEnded = endedContent.includes('ended') || endedContent.includes('Ended') || endedContent.includes('ca_ended');
        console.log(`  Shows ended state: ${hasEnded ? '✅' : '❌'}`);

        // ===== PART 6: ADMIN AUCTION LIST =====
        console.log('\n[6] Testing Admin Auction List...');
        await page.goto(`${SITE_URL}/wp-admin/edit.php?post_type=auction`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'admin-auction-list');

        const auctionCount = await page.locator('table.wp-list-table tbody tr').count();
        console.log(`  Auctions in admin: ${auctionCount}`);

        // ===== PART 7: ADMIN SETTINGS =====
        console.log('\n[7] Testing Admin Settings...');
        await page.goto(`${SITE_URL}/wp-admin/admin.php?page=community-auctions`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'admin-settings');

        const settingsContent = await page.content();
        const settingsSections = {
            'General Settings': settingsContent.includes('General Settings'),
            'Payment Provider': settingsContent.includes('Payment Provider'),
            'Bidding Rules': settingsContent.includes('Bidding Rules'),
            'Fees': settingsContent.includes('Fees')
        };

        console.log('  Settings sections:');
        for (const [name, found] of Object.entries(settingsSections)) {
            console.log(`    - ${name}: ${found ? '✅' : '❌'}`);
        }

        // ===== PART 8: CATEGORIES =====
        console.log('\n[8] Testing Categories...');
        await page.goto(`${SITE_URL}/wp-admin/edit-tags.php?taxonomy=auction_category&post_type=auction`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'admin-categories');

        const categoryCount = await page.locator('table.wp-list-table tbody tr').count();
        console.log(`  Categories: ${categoryCount}`);

        // ===== SUMMARY =====
        console.log('\n' + '═'.repeat(60));
        console.log('TEST COMPLETE');
        console.log('═'.repeat(60));
        console.log(`\nScreenshots saved to: ${SCREENSHOTS_DIR}`);
        console.log('\nTo view: open ' + SCREENSHOTS_DIR);
    });
});
