/**
 * Auction Participation Flow Test
 *
 * Tests the complete flow of participating in an auction.
 */

const { test, expect } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

const SITE_URL = 'http://community-hashtags.local';
const SCREENSHOTS_DIR = '/tmp/auction-screenshots';

// Create screenshots directory
if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

test.describe('Auction Participation Flow', () => {

    test('Complete auction flow with screenshots', async ({ page }) => {
        console.log('='.repeat(60));
        console.log('AUCTION PARTICIPATION FLOW TEST');
        console.log('='.repeat(60));

        // Step 1: Visit homepage
        console.log('\n[Step 1] Visiting homepage...');
        await page.goto(SITE_URL);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/01-homepage.png`, fullPage: true });
        console.log('  Screenshot: 01-homepage.png');

        // Step 2: Try auctions archive
        console.log('\n[Step 2] Accessing auctions archive...');
        await page.goto(`${SITE_URL}/auctions/`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/02-auctions-archive.png`, fullPage: true });
        console.log('  Screenshot: 02-auctions-archive.png');
        console.log(`  Page title: ${await page.title()}`);

        // Step 3: Login to admin
        console.log('\n[Step 3] Logging into WordPress admin...');
        await page.goto(`${SITE_URL}/wp-admin/`);
        await page.waitForLoadState('networkidle');

        if (page.url().includes('login')) {
            await page.screenshot({ path: `${SCREENSHOTS_DIR}/03-login-page.png` });
            console.log('  Screenshot: 03-login-page.png');

            await page.fill('#user_login', 'admin');
            await page.fill('#user_pass', 'admin');
            await page.click('#wp-submit');
            await page.waitForLoadState('networkidle');

            // Check if login succeeded
            if (page.url().includes('login')) {
                console.log('  Trying alternate password...');
                await page.fill('#user_login', 'admin');
                await page.fill('#user_pass', 'password');
                await page.click('#wp-submit');
                await page.waitForLoadState('networkidle');
            }
        }

        await page.screenshot({ path: `${SCREENSHOTS_DIR}/04-admin-dashboard.png`, fullPage: true });
        console.log('  Screenshot: 04-admin-dashboard.png');

        // Step 4: Check auctions in admin
        console.log('\n[Step 4] Checking auctions in admin...');
        await page.goto(`${SITE_URL}/wp-admin/edit.php?post_type=auction`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/05-auction-list.png`, fullPage: true });
        console.log('  Screenshot: 05-auction-list.png');

        const auctionRows = await page.locator('table.wp-list-table tbody tr').count();
        console.log(`  Found ${auctionRows} rows in auction list`);

        // Step 5: Create new auction form
        console.log('\n[Step 5] Checking auction creation form...');
        await page.goto(`${SITE_URL}/wp-admin/post-new.php?post_type=auction`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/06-create-auction-form.png`, fullPage: true });
        console.log('  Screenshot: 06-create-auction-form.png');

        // Step 6: Check plugin settings
        console.log('\n[Step 6] Checking plugin settings...');
        await page.goto(`${SITE_URL}/wp-admin/admin.php?page=community-auctions`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/07-plugin-settings.png`, fullPage: true });
        console.log('  Screenshot: 07-plugin-settings.png');

        // Step 7: Check if any live auctions exist
        console.log('\n[Step 7] Looking for live auctions...');
        await page.goto(`${SITE_URL}/wp-admin/edit.php?post_type=auction&post_status=ca_live`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/08-live-auctions.png`, fullPage: true });
        console.log('  Screenshot: 08-live-auctions.png');

        // Step 8: Check BuddyPress integration
        console.log('\n[Step 8] Checking BuddyPress member auctions...');
        await page.goto(`${SITE_URL}/members/admin/`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/09-member-profile.png`, fullPage: true });
        console.log('  Screenshot: 09-member-profile.png');

        // Look for auctions tab
        const auctionsTab = page.locator('a[href*="auctions"], nav a:has-text("Auction")');
        if (await auctionsTab.count() > 0) {
            console.log('  Found auctions tab, clicking...');
            await auctionsTab.first().click();
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: `${SCREENSHOTS_DIR}/10-member-auctions-tab.png`, fullPage: true });
            console.log('  Screenshot: 10-member-auctions-tab.png');
        } else {
            console.log('  No auctions tab found in member profile');
        }

        // Step 9: View a single auction
        console.log('\n[Step 9] Viewing single auction...');
        await page.goto(`${SITE_URL}/wp-admin/edit.php?post_type=auction`);
        await page.waitForLoadState('networkidle');

        const viewLink = page.locator('table.wp-list-table tbody tr .row-actions .view a').first();
        if (await viewLink.count() > 0) {
            const auctionUrl = await viewLink.getAttribute('href');
            console.log(`  Opening auction: ${auctionUrl}`);
            await page.goto(auctionUrl);
            await page.waitForLoadState('networkidle');
            await page.screenshot({ path: `${SCREENSHOTS_DIR}/11-single-auction-view.png`, fullPage: true });
            console.log('  Screenshot: 11-single-auction-view.png');

            // Check for bid form
            const bidForm = page.locator('form.ca-bid-form, .ca-bid-form, input[name*="bid"]');
            if (await bidForm.count() > 0) {
                console.log('  Found bid form on page!');
            } else {
                console.log('  No bid form found');
            }
        } else {
            console.log('  No auctions available to view');
        }

        // Summary
        console.log('\n' + '='.repeat(60));
        console.log('TEST COMPLETE');
        console.log('='.repeat(60));
        console.log(`\nScreenshots saved to: ${SCREENSHOTS_DIR}`);
        console.log(`\nView screenshots: open ${SCREENSHOTS_DIR}`);
    });
});
