/**
 * Community Auctions - Full Plugin Test
 * Comprehensive testing of all plugin features with screenshots
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const SITE_URL = 'http://community-hashtags.local';
const SCREENSHOTS_DIR = '/Users/shashank/Desktop/community-auction';
const ADMIN_USER = 'Steve';
const ADMIN_PASS = 'Steve';

// Ensure screenshots directory exists
if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

let screenshotCounter = 1;

async function screenshot(page, name) {
    const filename = path.join(SCREENSHOTS_DIR, `${screenshotCounter.toString().padStart(2, '0')}-${name}.png`);
    await page.screenshot({ path: filename, fullPage: true });
    console.log(`  Screenshot saved: ${screenshotCounter.toString().padStart(2, '0')}-${name}.png`);
    screenshotCounter++;
}

test.describe('Community Auctions - Full Plugin Test', () => {

    test.beforeEach(async ({ page }) => {
        // Login to WordPress
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('domcontentloaded');
    });

    test('Complete plugin walkthrough with screenshots', async ({ page }) => {
        console.log('\n' + '='.repeat(70));
        console.log('COMMUNITY AUCTIONS - FULL PLUGIN TEST');
        console.log('='.repeat(70));

        // ============================================
        // SECTION 1: ADMIN DASHBOARD
        // ============================================
        console.log('\n[SECTION 1] Admin Dashboard');
        console.log('-'.repeat(40));

        // Main plugin dashboard
        console.log('  Loading plugin dashboard...');
        await page.goto(`${SITE_URL}/wp-admin/admin.php?page=community-auctions`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);
        await screenshot(page, 'admin-dashboard');

        // Plugin settings
        console.log('  Loading plugin settings...');
        await page.goto(`${SITE_URL}/wp-admin/admin.php?page=ca-settings`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(500);
        await screenshot(page, 'admin-settings');

        // ============================================
        // SECTION 2: DEMO DATA IMPORT
        // ============================================
        console.log('\n[SECTION 2] Demo Data Import');
        console.log('-'.repeat(40));

        console.log('  Loading demo data page...');
        await page.goto(`${SITE_URL}/wp-admin/admin.php?page=ca-demo-data`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'demo-data-page');

        // Check if demo data already imported
        const importButton = page.locator('button[value="import"]');
        const buttonText = await importButton.textContent();

        if (buttonText.includes('Re-import')) {
            console.log('  Demo data already imported, re-importing...');
        } else {
            console.log('  Importing demo data...');
        }

        // Import demo data
        await importButton.click();
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(2000);
        await screenshot(page, 'demo-data-imported');
        console.log('  Demo data imported successfully!');

        // ============================================
        // SECTION 3: AUCTION MANAGEMENT (ADMIN)
        // ============================================
        console.log('\n[SECTION 3] Auction Management (Admin)');
        console.log('-'.repeat(40));

        // All auctions list
        console.log('  Loading all auctions...');
        await page.goto(`${SITE_URL}/wp-admin/edit.php?post_type=auction`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(500);
        await screenshot(page, 'admin-auctions-list');

        // New auction page
        console.log('  Loading new auction editor...');
        await page.goto(`${SITE_URL}/wp-admin/post-new.php?post_type=auction`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(2000); // Wait for Gutenberg
        await screenshot(page, 'admin-new-auction');

        // Scroll to meta box
        const metaBox = page.locator('#community_auctions_meta');
        if (await metaBox.count() > 0) {
            await page.evaluate(() => {
                const box = document.getElementById('community_auctions_meta');
                if (box) box.scrollIntoView({ behavior: 'instant', block: 'center' });
            });
            await page.waitForTimeout(500);
            await screenshot(page, 'admin-auction-metabox');
        }

        // Auction categories
        console.log('  Loading auction categories...');
        await page.goto(`${SITE_URL}/wp-admin/edit-tags.php?taxonomy=auction_category&post_type=auction`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'admin-categories');

        // ============================================
        // SECTION 4: FRONTEND - AUCTION ARCHIVE
        // ============================================
        console.log('\n[SECTION 4] Frontend - Auction Archive');
        console.log('-'.repeat(40));

        console.log('  Loading auction archive...');
        await page.goto(`${SITE_URL}/auctions/`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);
        await screenshot(page, 'frontend-auction-archive');

        // Check for auctions
        const auctionCards = page.locator('.ca-auction-card, .auction-item, article');
        const auctionCount = await auctionCards.count();
        console.log(`  Found ${auctionCount} auction items on archive`);

        // ============================================
        // SECTION 5: FRONTEND - SINGLE AUCTION
        // ============================================
        console.log('\n[SECTION 5] Frontend - Single Auction');
        console.log('-'.repeat(40));

        // Find a live auction to view
        console.log('  Finding a live auction...');
        await page.goto(`${SITE_URL}/wp-admin/edit.php?post_type=auction&post_status=ca_live`);
        await page.waitForLoadState('domcontentloaded');

        const firstAuctionLink = page.locator('td.title a.row-title').first();
        if (await firstAuctionLink.count() > 0) {
            const auctionTitle = await firstAuctionLink.textContent();
            console.log(`  Opening auction: ${auctionTitle}`);

            // Get the view link
            await firstAuctionLink.hover();
            await page.waitForTimeout(300);
            const viewLink = page.locator('span.view a').first();
            if (await viewLink.count() > 0) {
                const auctionUrl = await viewLink.getAttribute('href');
                await page.goto(auctionUrl);
            } else {
                // Try clicking on title and getting permalink
                await firstAuctionLink.click();
                await page.waitForLoadState('domcontentloaded');
                const permalinkInput = page.locator('input#sample-permalink, .editor-post-link__link');
                if (await permalinkInput.count() > 0) {
                    const permalink = await permalinkInput.getAttribute('href') || await permalinkInput.inputValue();
                    await page.goto(permalink.replace(/\/+$/, ''));
                }
            }
        } else {
            // Go to archive and click first auction
            await page.goto(`${SITE_URL}/auctions/`);
            await page.waitForLoadState('domcontentloaded');
            const firstAuction = page.locator('a[href*="/auction/"]').first();
            if (await firstAuction.count() > 0) {
                await firstAuction.click();
            }
        }

        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);
        await screenshot(page, 'frontend-single-auction');

        // Scroll to bid form if exists
        const bidForm = page.locator('.ca-bid-form, .bid-form, #bid-form');
        if (await bidForm.count() > 0) {
            await bidForm.scrollIntoViewIfNeeded();
            await page.waitForTimeout(500);
            await screenshot(page, 'frontend-bid-form');
        }

        // ============================================
        // SECTION 6: FRONTEND - SUBMIT AUCTION PAGE
        // ============================================
        console.log('\n[SECTION 6] Frontend - Submit Auction');
        console.log('-'.repeat(40));

        console.log('  Loading submit auction page...');
        await page.goto(`${SITE_URL}/submit-auction/`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);
        await screenshot(page, 'frontend-submit-auction-step1');

        // Check for step-based form
        const stepForm = page.locator('.ca-submit-form, .ca-step');
        if (await stepForm.count() > 0) {
            console.log('  Step-based form detected');

            // Fill step 1
            const titleInput = page.locator('#ca_title');
            if (await titleInput.count() > 0) {
                await titleInput.fill('Test Auction from Playwright');
                const descInput = page.locator('#ca_description');
                if (await descInput.count() > 0) {
                    await descInput.fill('This is a test auction created during automated testing.');
                }
                await screenshot(page, 'frontend-submit-step1-filled');

                // Go to step 2
                const nextBtn = page.locator('.ca-btn-next[data-next="2"]');
                if (await nextBtn.count() > 0) {
                    await nextBtn.click();
                    await page.waitForTimeout(500);
                    await screenshot(page, 'frontend-submit-step2');
                }
            }
        }

        // ============================================
        // SECTION 7: FRONTEND - SEARCH PAGE
        // ============================================
        console.log('\n[SECTION 7] Frontend - Search Auctions');
        console.log('-'.repeat(40));

        console.log('  Loading search page...');
        await page.goto(`${SITE_URL}/search-auctions/`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);
        await screenshot(page, 'frontend-search-page');

        // ============================================
        // SECTION 8: FRONTEND - SELLER DASHBOARD
        // ============================================
        console.log('\n[SECTION 8] Frontend - Seller Dashboard');
        console.log('-'.repeat(40));

        console.log('  Loading seller dashboard...');
        await page.goto(`${SITE_URL}/my-auctions/`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);
        await screenshot(page, 'frontend-seller-dashboard');

        // ============================================
        // SECTION 9: FRONTEND - BUYER DASHBOARD
        // ============================================
        console.log('\n[SECTION 9] Frontend - Buyer Dashboard');
        console.log('-'.repeat(40));

        console.log('  Loading buyer dashboard...');
        await page.goto(`${SITE_URL}/my-purchases/`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);
        await screenshot(page, 'frontend-buyer-dashboard');

        // ============================================
        // SECTION 10: FRONTEND - WATCHLIST
        // ============================================
        console.log('\n[SECTION 10] Frontend - Watchlist');
        console.log('-'.repeat(40));

        console.log('  Loading watchlist page...');
        await page.goto(`${SITE_URL}/my-watchlist/`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);
        await screenshot(page, 'frontend-watchlist');

        // ============================================
        // SECTION 11: FRONTEND - UPCOMING AUCTIONS
        // ============================================
        console.log('\n[SECTION 11] Frontend - Upcoming Auctions');
        console.log('-'.repeat(40));

        console.log('  Loading upcoming auctions...');
        await page.goto(`${SITE_URL}/upcoming-auctions/`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);
        await screenshot(page, 'frontend-upcoming-auctions');

        // ============================================
        // SECTION 12: BUDDYPRESS INTEGRATION
        // ============================================
        console.log('\n[SECTION 12] BuddyPress Integration');
        console.log('-'.repeat(40));

        // Activity stream
        console.log('  Loading activity stream...');
        await page.goto(`${SITE_URL}/activity/`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);

        // Check for auction activities
        const auctionActivities = page.locator('.activity-content:has-text("auction")');
        const activityCount = await auctionActivities.count();
        console.log(`  Found ${activityCount} auction-related activities`);
        await screenshot(page, 'buddypress-activity-stream');

        // Member profile with auctions
        console.log('  Loading member auctions tab...');
        await page.goto(`${SITE_URL}/members/${ADMIN_USER.toLowerCase()}/auctions/`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000);
        await screenshot(page, 'buddypress-member-auctions');

        // ============================================
        // SECTION 13: NAVIGATION MENU CHECK
        // ============================================
        console.log('\n[SECTION 13] Navigation Menu');
        console.log('-'.repeat(40));

        console.log('  Checking navigation menu...');
        await page.goto(`${SITE_URL}`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(500);

        // Look for Auctions menu item
        const auctionsMenu = page.locator('nav a:has-text("Auctions"), .menu a:has-text("Auctions")');
        if (await auctionsMenu.count() > 0) {
            console.log('  Auctions menu item found in navigation!');
            // Hover to show dropdown
            await auctionsMenu.first().hover();
            await page.waitForTimeout(500);
            await screenshot(page, 'navigation-menu-auctions');
        } else {
            console.log('  Auctions menu item not found (may be in different location)');
            await screenshot(page, 'navigation-homepage');
        }

        // ============================================
        // SUMMARY
        // ============================================
        console.log('\n' + '='.repeat(70));
        console.log('TEST COMPLETE');
        console.log('='.repeat(70));
        console.log(`\nTotal screenshots saved: ${screenshotCounter - 1}`);
        console.log(`Screenshots location: ${SCREENSHOTS_DIR}`);
        console.log('\nScreenshot list:');

        const files = fs.readdirSync(SCREENSHOTS_DIR).filter(f => f.endsWith('.png')).sort();
        files.forEach(file => console.log(`  - ${file}`));
    });
});
