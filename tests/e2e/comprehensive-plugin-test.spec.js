/**
 * Comprehensive Community Auctions Plugin Test
 *
 * Tests all plugin features including:
 * - Auctions archive and single pages
 * - Frontend auction submission
 * - Bidding flow
 * - Seller & Buyer dashboards
 * - Watchlist
 * - Admin settings and management
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');

const SITE_URL = 'http://community-hashtags.local';
const SCREENSHOTS_DIR = '/tmp/auction-test-screenshots';
const ADMIN_USER = 'Steve';
const ADMIN_PASS = 'Steve';

// Create screenshots directory
if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

let stepCounter = 1;

async function screenshot(page, name) {
    const filename = `${SCREENSHOTS_DIR}/${stepCounter.toString().padStart(2, '0')}-${name}.png`;
    await page.screenshot({ path: filename, fullPage: true });
    console.log(`  📸 ${stepCounter.toString().padStart(2, '0')}-${name}.png`);
    stepCounter++;
    return filename;
}

function logSection(title) {
    console.log('\n' + '═'.repeat(60));
    console.log(`📌 ${title}`);
    console.log('═'.repeat(60));
}

function logStep(description) {
    console.log(`\n[Step ${stepCounter}] ${description}`);
}

test.describe('Community Auctions - Complete Feature Test', () => {

    test.beforeAll(async () => {
        console.log('\n' + '🏷️'.repeat(30));
        console.log('COMMUNITY AUCTIONS - COMPREHENSIVE PLUGIN TEST');
        console.log('🏷️'.repeat(30));
        console.log(`\nScreenshots will be saved to: ${SCREENSHOTS_DIR}`);
    });

    test('1. Public Auction Pages (Visitor)', async ({ page }) => {
        logSection('PART 1: PUBLIC PAGES (NOT LOGGED IN)');

        // Auctions Archive
        logStep('Visiting auctions archive...');
        await page.goto(`${SITE_URL}/auctions/`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'archive-visitor');

        const auctionLinks = await page.locator('a[href*="/auctions/"]').count();
        console.log(`  Found ${auctionLinks} auction links`);

        // Single Auction as Visitor
        logStep('Viewing single auction as visitor...');
        await page.goto(`${SITE_URL}/auctions/verification-test/`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'single-auction-visitor');

        // Check elements
        const elements = {
            'Status badge': '.ca-auction-status',
            'Price display': '.ca-price-value, .ca-auction-pricing-box',
            'Countdown': '.ca-countdown-section, [data-ca-countdown]',
            'Bid form section': '.ca-bid-form-section',
            'Login notice': '.ca-login-notice',
            'Seller info': '.ca-seller-section',
            'Bid history': '.ca-bid-history-section'
        };

        console.log('  Checking auction page elements:');
        for (const [name, selector] of Object.entries(elements)) {
            const exists = await page.locator(selector).count() > 0;
            console.log(`    - ${name}: ${exists ? '✅' : '❌'}`);
        }
    });

    test('2. User Login', async ({ page }) => {
        logSection('PART 2: USER LOGIN');

        logStep('Logging in...');
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        if (page.url().includes('wp-admin')) {
            console.log('  ✅ Login successful');
            await screenshot(page, 'login-success');
        } else {
            console.log('  ⚠️ Login may have failed');
            await screenshot(page, 'login-result');
        }
    });

    test('3. Auction Participation (Logged In)', async ({ page }) => {
        logSection('PART 3: AUCTION PARTICIPATION');

        // Login first
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        // View auction as logged-in user
        logStep('Viewing auction as logged-in user...');
        await page.goto(`${SITE_URL}/auctions/verification-test/`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'single-auction-logged-in');

        // Check for bid form
        const hasBidForm = await page.locator('.ca-bid-form, input[name="bid_amount"]').count() > 0;
        const hasBidButton = await page.locator('.ca-place-bid-btn, button:has-text("Place Bid")').count() > 0;
        const hasSellerNotice = await page.locator('.ca-seller-notice').count() > 0;

        console.log('  Logged-in elements:');
        console.log(`    - Bid form: ${hasBidForm ? '✅' : '❌'}`);
        console.log(`    - Place Bid button: ${hasBidButton ? '✅' : '❌'}`);
        console.log(`    - Seller notice (own auction): ${hasSellerNotice ? '✅' : '❌'}`);
    });

    test('4. Frontend Auction Submission Form', async ({ page }) => {
        logSection('PART 4: FRONTEND AUCTION SUBMISSION');

        // Login first
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        // Check if submit page exists
        logStep('Looking for auction submission page...');

        // Try common URLs for submission form
        const submitUrls = [
            '/submit-auction/',
            '/create-auction/',
            '/auctions/submit/',
            '/auction/submit/'
        ];

        let formFound = false;
        for (const url of submitUrls) {
            await page.goto(`${SITE_URL}${url}`);
            await page.waitForLoadState('networkidle');

            const hasForm = await page.locator('input[name="ca_title"], #ca_title').count() > 0;
            if (hasForm) {
                console.log(`  ✅ Found submission form at ${url}`);
                formFound = true;
                await screenshot(page, 'frontend-submit-form');
                break;
            }
        }

        if (!formFound) {
            console.log('  ⚠️ No submission page found at common URLs');
            console.log('  Creating test page with shortcode...');

            // Create a test page via admin
            await page.goto(`${SITE_URL}/wp-admin/post-new.php?post_type=page`);
            await page.waitForLoadState('networkidle');
            await screenshot(page, 'admin-create-submit-page');
        }
    });

    test('5. Seller Dashboard', async ({ page }) => {
        logSection('PART 5: SELLER DASHBOARD');

        // Login
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        // Try common URLs for seller dashboard
        const dashboardUrls = [
            '/seller-dashboard/',
            '/my-auctions/',
            '/auctions/dashboard/',
            '/members/steve/auctions/'
        ];

        logStep('Looking for seller dashboard...');
        let dashboardFound = false;

        for (const url of dashboardUrls) {
            await page.goto(`${SITE_URL}${url}`);
            await page.waitForLoadState('networkidle');

            const content = await page.content();
            if (content.includes('dashboard') || content.includes('My Auctions') || content.includes('Active') || content.includes('Ended')) {
                console.log(`  ✅ Found dashboard-like content at ${url}`);
                dashboardFound = true;
                await screenshot(page, 'seller-dashboard');
                break;
            }
        }

        if (!dashboardFound) {
            console.log('  ⚠️ No seller dashboard page found');
        }
    });

    test('6. Buyer Dashboard', async ({ page }) => {
        logSection('PART 6: BUYER DASHBOARD');

        // Login
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        // Try common URLs
        const dashboardUrls = [
            '/buyer-dashboard/',
            '/my-purchases/',
            '/won-auctions/'
        ];

        logStep('Looking for buyer dashboard...');

        for (const url of dashboardUrls) {
            await page.goto(`${SITE_URL}${url}`);
            await page.waitForLoadState('networkidle');

            const content = await page.content();
            if (content.includes('purchases') || content.includes('won') || content.includes('Won Auctions')) {
                console.log(`  ✅ Found buyer dashboard at ${url}`);
                await screenshot(page, 'buyer-dashboard');
                break;
            }
        }
    });

    test('7. Watchlist', async ({ page }) => {
        logSection('PART 7: WATCHLIST FEATURE');

        // Login
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        // Check for watchlist button on auction page
        logStep('Checking for watchlist feature on auction page...');
        await page.goto(`${SITE_URL}/auctions/verification-test/`);
        await page.waitForLoadState('networkidle');

        const hasWatchlistBtn = await page.locator('[class*="watchlist"], [data-watchlist], button:has-text("Watch")').count() > 0;
        console.log(`  Watchlist button on auction: ${hasWatchlistBtn ? '✅' : '❌'}`);

        // Try watchlist page URLs
        const watchlistUrls = [
            '/watchlist/',
            '/my-watchlist/',
            '/auctions/watchlist/'
        ];

        for (const url of watchlistUrls) {
            await page.goto(`${SITE_URL}${url}`);
            await page.waitForLoadState('networkidle');

            const content = await page.content();
            if (content.includes('watchlist') || content.includes('Watchlist') || content.includes('watching')) {
                console.log(`  ✅ Found watchlist page at ${url}`);
                await screenshot(page, 'watchlist-page');
                break;
            }
        }
    });

    test('8. Admin Settings', async ({ page }) => {
        logSection('PART 8: ADMIN SETTINGS');

        // Login
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        // Plugin Settings Page
        logStep('Checking plugin settings page...');
        await page.goto(`${SITE_URL}/wp-admin/admin.php?page=community-auctions`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'admin-settings');

        // Check for settings sections
        const settingsContent = await page.content();
        const sections = [
            'General Settings',
            'Payment Provider',
            'Bidding Rules',
            'Real-time Updates',
            'Buy It Now',
            'Fees'
        ];

        console.log('  Settings sections found:');
        for (const section of sections) {
            const found = settingsContent.includes(section);
            console.log(`    - ${section}: ${found ? '✅' : '❌'}`);
        }
    });

    test('9. Admin Auction Management', async ({ page }) => {
        logSection('PART 9: ADMIN AUCTION MANAGEMENT');

        // Login
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        // Auctions List
        logStep('Checking auctions admin list...');
        await page.goto(`${SITE_URL}/wp-admin/edit.php?post_type=auction`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'admin-auction-list');

        const auctionCount = await page.locator('table.wp-list-table tbody tr').count();
        console.log(`  Found ${auctionCount} auctions in admin`);

        // Check for auction statuses
        const statusFilters = await page.locator('.subsubsub a').allTextContents();
        console.log(`  Status filters: ${statusFilters.join(', ')}`);

        // New Auction Form
        logStep('Checking new auction form...');
        await page.goto(`${SITE_URL}/wp-admin/post-new.php?post_type=auction`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'admin-new-auction');

        // Check for meta boxes
        const metaBoxTitles = await page.locator('.postbox h2').allTextContents();
        console.log('  Meta boxes found:');
        for (const title of metaBoxTitles.slice(0, 8)) {
            if (title.trim()) console.log(`    - ${title.trim()}`);
        }

        // Edit Existing Auction
        logStep('Checking existing auction edit...');
        await page.goto(`${SITE_URL}/wp-admin/edit.php?post_type=auction`);
        await page.waitForLoadState('networkidle');

        // Click first auction to edit
        const firstAuction = page.locator('table.wp-list-table tbody tr .row-title').first();
        if (await firstAuction.count() > 0) {
            await firstAuction.click();
            await page.waitForLoadState('networkidle');
            await screenshot(page, 'admin-edit-auction');
        }
    });

    test('10. Auction Categories', async ({ page }) => {
        logSection('PART 10: AUCTION CATEGORIES');

        // Login
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        // Categories admin
        logStep('Checking auction categories...');
        await page.goto(`${SITE_URL}/wp-admin/edit-tags.php?taxonomy=auction_category&post_type=auction`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'admin-categories');

        const categoryCount = await page.locator('table.wp-list-table tbody tr').count();
        console.log(`  Found ${categoryCount} categories`);
    });

    test('11. BuddyPress Integration', async ({ page }) => {
        logSection('PART 11: BUDDYPRESS INTEGRATION');

        // Login
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        // Check member profile for auction tab
        logStep('Checking BuddyPress member profile...');
        await page.goto(`${SITE_URL}/members/steve/`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'bp-member-profile');

        // Look for auction nav items
        const content = await page.content();
        const hasAuctionNav = content.includes('Auctions') || content.includes('auctions');
        console.log(`  Auctions tab in profile: ${hasAuctionNav ? '✅' : '❌'}`);

        if (hasAuctionNav) {
            // Try to click auctions tab
            const auctionLink = page.locator('a:has-text("Auctions")').first();
            if (await auctionLink.count() > 0) {
                await auctionLink.click();
                await page.waitForLoadState('networkidle');
                await screenshot(page, 'bp-member-auctions');
            }
        }
    });

    test('12. Gutenberg Blocks', async ({ page }) => {
        logSection('PART 12: GUTENBERG BLOCKS');

        // Login
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        // Create new page
        logStep('Checking Gutenberg block inserter...');
        await page.goto(`${SITE_URL}/wp-admin/post-new.php?post_type=page`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000); // Wait for editor

        await screenshot(page, 'gutenberg-editor');

        // Try to open block inserter
        const inserterBtn = page.locator('button[aria-label*="block inserter"], button[aria-label*="Add block"]').first();
        if (await inserterBtn.count() > 0) {
            await inserterBtn.click();
            await page.waitForTimeout(1000);

            // Search for auction blocks
            const searchInput = page.locator('input[placeholder*="Search"], input[type="search"]').first();
            if (await searchInput.count() > 0) {
                await searchInput.fill('community auction');
                await page.waitForTimeout(1000);
                await screenshot(page, 'gutenberg-auction-blocks');

                // Check what blocks are found
                const blockItems = await page.locator('.block-editor-block-types-list__item').allTextContents();
                console.log('  Auction blocks found:');
                for (const block of blockItems) {
                    if (block.toLowerCase().includes('auction')) {
                        console.log(`    - ${block.trim()}`);
                    }
                }
            }
        }
    });

    test('13. REST API Endpoints', async ({ page }) => {
        logSection('PART 13: REST API ENDPOINTS');

        // Login first to get auth
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        logStep('Testing REST API endpoints...');

        const endpoints = [
            '/wp-json/community-auctions/v1/auctions',
            '/wp-json/community-auctions/v1/categories'
        ];

        for (const endpoint of endpoints) {
            await page.goto(`${SITE_URL}${endpoint}`);
            await page.waitForLoadState('networkidle');

            const content = await page.content();
            const isJson = content.includes('[') || content.includes('{');
            console.log(`  ${endpoint}: ${isJson ? '✅ JSON response' : '❌ Error or HTML'}`);
        }
    });

    test.afterAll(async () => {
        console.log('\n' + '═'.repeat(60));
        console.log('📊 TEST COMPLETE');
        console.log('═'.repeat(60));
        console.log(`\nScreenshots saved to: ${SCREENSHOTS_DIR}`);
        console.log(`Total screenshots: ${stepCounter - 1}`);
        console.log('\nTo view screenshots:');
        console.log(`  open ${SCREENSHOTS_DIR}`);
    });
});
