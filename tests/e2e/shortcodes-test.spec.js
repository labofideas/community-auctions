/**
 * Community Auctions - Shortcodes & Pages Test
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');

const SITE_URL = 'http://community-hashtags.local';
const SCREENSHOTS_DIR = '/tmp/auction-shortcodes-test';
const ADMIN_USER = 'Steve';
const ADMIN_PASS = 'Steve';

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

test.describe('Shortcodes & Pages Test', () => {

    test('Test all shortcode pages', async ({ page }) => {
        console.log('\n' + '═'.repeat(60));
        console.log('SHORTCODES & PAGES TEST');
        console.log('═'.repeat(60));

        // Login first
        console.log('\n[Login]');
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('domcontentloaded');
        console.log('  Logged in as Steve');

        // ===== 1. SUBMIT AUCTION =====
        console.log('\n[1] Submit Auction Page...');
        await page.goto(`${SITE_URL}/submit-auction/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'submit-auction');

        const submitContent = await page.content();
        const submitElements = {
            'Title field': submitContent.includes('ca_title') || submitContent.includes('Title'),
            'Description field': submitContent.includes('ca_description') || submitContent.includes('Description'),
            'Start price': submitContent.includes('ca_start_price') || submitContent.includes('Start Price'),
            'Date fields': submitContent.includes('ca_start_at') || submitContent.includes('Start Date'),
            'Submit button': submitContent.includes('Submit Auction'),
            'Category selector': submitContent.includes('categor') || submitContent.includes('Categor'),
            'Gallery upload': submitContent.includes('gallery') || submitContent.includes('image')
        };

        console.log('  Form elements:');
        for (const [name, found] of Object.entries(submitElements)) {
            console.log(`    - ${name}: ${found ? '✅' : '❌'}`);
        }

        // ===== 2. SELLER DASHBOARD =====
        console.log('\n[2] Seller Dashboard (My Auctions)...');
        await page.goto(`${SITE_URL}/my-auctions/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'seller-dashboard');

        const sellerContent = await page.content();
        const sellerElements = {
            'Active auctions': sellerContent.includes('Active') || sellerContent.includes('Live'),
            'Ended auctions': sellerContent.includes('Ended') || sellerContent.includes('Completed'),
            'Auction listings': sellerContent.includes('auction') || sellerContent.includes('Auction'),
            'Stats/earnings': sellerContent.includes('earnings') || sellerContent.includes('Earnings') || sellerContent.includes('Total')
        };

        console.log('  Dashboard elements:');
        for (const [name, found] of Object.entries(sellerElements)) {
            console.log(`    - ${name}: ${found ? '✅' : '❌'}`);
        }

        // ===== 3. BUYER DASHBOARD =====
        console.log('\n[3] Buyer Dashboard (My Purchases)...');
        await page.goto(`${SITE_URL}/my-purchases/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'buyer-dashboard');

        const buyerContent = await page.content();
        const buyerElements = {
            'Won auctions': buyerContent.includes('Won') || buyerContent.includes('won'),
            'Payment status': buyerContent.includes('Payment') || buyerContent.includes('payment'),
            'Order info': buyerContent.includes('Order') || buyerContent.includes('order')
        };

        console.log('  Dashboard elements:');
        for (const [name, found] of Object.entries(buyerElements)) {
            console.log(`    - ${name}: ${found ? '✅' : '❌'}`);
        }

        // ===== 4. WATCHLIST =====
        console.log('\n[4] Watchlist Page...');
        await page.goto(`${SITE_URL}/my-watchlist/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'watchlist');

        const watchlistContent = await page.content();
        const watchlistElements = {
            'Watchlist content': watchlistContent.includes('watchlist') || watchlistContent.includes('Watchlist') || watchlistContent.includes('watching'),
            'Empty/items message': watchlistContent.includes('watching') || watchlistContent.includes('empty') || watchlistContent.includes('auction')
        };

        console.log('  Watchlist elements:');
        for (const [name, found] of Object.entries(watchlistElements)) {
            console.log(`    - ${name}: ${found ? '✅' : '❌'}`);
        }

        // ===== 5. SEARCH PAGE =====
        console.log('\n[5] Search Auctions Page...');
        await page.goto(`${SITE_URL}/search-auctions/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'search-auctions');

        const searchContent = await page.content();
        const searchElements = {
            'Search input': searchContent.includes('search') || searchContent.includes('Search'),
            'Filter options': searchContent.includes('filter') || searchContent.includes('Filter') || searchContent.includes('category'),
            'Results area': searchContent.includes('result') || searchContent.includes('auction')
        };

        console.log('  Search elements:');
        for (const [name, found] of Object.entries(searchElements)) {
            console.log(`    - ${name}: ${found ? '✅' : '❌'}`);
        }

        // ===== 6. UPCOMING AUCTIONS =====
        console.log('\n[6] Upcoming Auctions Page...');
        await page.goto(`${SITE_URL}/upcoming-auctions/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'upcoming-auctions');

        const upcomingContent = await page.content();
        const upcomingElements = {
            'Upcoming content': upcomingContent.includes('upcoming') || upcomingContent.includes('Upcoming') || upcomingContent.includes('Starting'),
            'Auction list': upcomingContent.includes('auction') || upcomingContent.includes('Auction')
        };

        console.log('  Upcoming elements:');
        for (const [name, found] of Object.entries(upcomingElements)) {
            console.log(`    - ${name}: ${found ? '✅' : '❌'}`);
        }

        // ===== SUMMARY =====
        console.log('\n' + '═'.repeat(60));
        console.log('TEST COMPLETE');
        console.log('═'.repeat(60));
        console.log(`\nScreenshots: ${SCREENSHOTS_DIR}`);
    });
});
