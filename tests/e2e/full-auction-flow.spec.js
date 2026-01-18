/**
 * Complete Auction Participation Flow Test
 *
 * Tests the entire user journey for participating in an auction.
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');

const SITE_URL = 'http://community-hashtags.local';
const SCREENSHOTS_DIR = '/tmp/auction-screenshots';
const WP_USER = 'Steve';
const WP_PASS = 'Steve'; // Local by Flywheel default

// Create screenshots directory
if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

async function screenshot(page, name, step) {
    const filename = `${SCREENSHOTS_DIR}/${step.toString().padStart(2, '0')}-${name}.png`;
    await page.screenshot({ path: filename, fullPage: true });
    console.log(`  📸 Screenshot: ${step.toString().padStart(2, '0')}-${name}.png`);
    return filename;
}

test.describe('Complete Auction Flow', () => {

    test('Full auction participation journey', async ({ page }) => {
        console.log('\n' + '═'.repeat(60));
        console.log('🏷️  COMPLETE AUCTION PARTICIPATION FLOW');
        console.log('═'.repeat(60));

        let step = 1;

        // =====================================================
        // PART 1: VISITOR JOURNEY (Not Logged In)
        // =====================================================
        console.log('\n📌 PART 1: VISITOR JOURNEY (Not Logged In)');
        console.log('-'.repeat(50));

        // Step 1: Homepage
        console.log(`\n[Step ${step}] Visiting site homepage...`);
        await page.goto(SITE_URL);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'homepage', step++);

        // Step 2: Auctions archive
        console.log(`\n[Step ${step}] Browsing auctions archive...`);
        await page.goto(`${SITE_URL}/auctions/`);
        await page.waitForLoadState('networkidle');
        const archiveTitle = await page.title();
        console.log(`  Page title: ${archiveTitle}`);
        await screenshot(page, 'auctions-archive', step++);

        // Step 3: View single live auction
        console.log(`\n[Step ${step}] Viewing a live auction...`);
        await page.goto(`${SITE_URL}/auctions/verification-test/`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'single-auction-visitor', step++);

        // Check what visitor sees
        const auctionContent = await page.content();
        console.log('  Checking auction page elements...');
        console.log(`    - Title visible: ${auctionContent.includes('Verification Test')}`);
        console.log(`    - Has bid form: ${auctionContent.includes('bid') || auctionContent.includes('Bid')}`);
        console.log(`    - Login prompt: ${auctionContent.includes('log in') || auctionContent.includes('login')}`);

        // =====================================================
        // PART 2: USER LOGIN
        // =====================================================
        console.log('\n📌 PART 2: USER LOGIN');
        console.log('-'.repeat(50));

        // Step 4: Go to login
        console.log(`\n[Step ${step}] Navigating to login page...`);
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'login-page', step++);

        // Step 5: Log in
        console.log(`\n[Step ${step}] Logging in as "${WP_USER}"...`);
        await page.fill('#user_login', WP_USER);
        await page.fill('#user_pass', WP_PASS);
        await screenshot(page, 'login-filled', step++);

        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        // Check login result
        if (page.url().includes('wp-admin')) {
            console.log('  ✅ Login successful!');
            await screenshot(page, 'login-success-dashboard', step++);
        } else {
            console.log('  ⚠️  May need different credentials');
            await screenshot(page, 'login-result', step++);
        }

        // =====================================================
        // PART 3: LOGGED IN USER - AUCTION PARTICIPATION
        // =====================================================
        console.log('\n📌 PART 3: AUCTION PARTICIPATION (Logged In)');
        console.log('-'.repeat(50));

        // Step 6: View auction while logged in
        console.log(`\n[Step ${step}] Viewing auction as logged-in user...`);
        await page.goto(`${SITE_URL}/auctions/verification-test/`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'single-auction-logged-in', step++);

        // Check for bid form
        const bidFormCheck = await page.locator('form.ca-bid-form, .ca-bid-form, [class*="bid-form"], input[name*="bid_amount"]');
        if (await bidFormCheck.count() > 0) {
            console.log('  ✅ Bid form is visible!');
        } else {
            console.log('  ⚠️  No dedicated bid form found');
        }

        // =====================================================
        // PART 4: ADMIN - AUCTION MANAGEMENT
        // =====================================================
        console.log('\n📌 PART 4: ADMIN - AUCTION MANAGEMENT');
        console.log('-'.repeat(50));

        // Step 7: Auctions admin list
        console.log(`\n[Step ${step}] Viewing auctions in admin...`);
        await page.goto(`${SITE_URL}/wp-admin/edit.php?post_type=auction`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'admin-auction-list', step++);

        const rowCount = await page.locator('table.wp-list-table tbody tr').count();
        console.log(`  Found ${rowCount} auctions`);

        // Step 8: Create new auction form
        console.log(`\n[Step ${step}] Checking new auction form...`);
        await page.goto(`${SITE_URL}/wp-admin/post-new.php?post_type=auction`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'admin-new-auction', step++);

        // Check for auction meta fields
        const metaBoxes = await page.locator('.postbox').allTextContents();
        console.log('  Meta boxes found:');
        for (const box of metaBoxes.slice(0, 5)) {
            const title = box.split('\n')[0].trim().substring(0, 40);
            if (title) console.log(`    - ${title}`);
        }

        // Step 9: Plugin settings
        console.log(`\n[Step ${step}] Checking plugin settings...`);
        await page.goto(`${SITE_URL}/wp-admin/admin.php?page=community-auctions`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'plugin-settings', step++);

        // Step 10: Edit live auction
        console.log(`\n[Step ${step}] Editing live auction...`);
        await page.goto(`${SITE_URL}/wp-admin/post.php?post=92&action=edit`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'edit-live-auction', step++);

        // =====================================================
        // PART 5: BUDDYPRESS INTEGRATION
        // =====================================================
        console.log('\n📌 PART 5: BUDDYPRESS INTEGRATION');
        console.log('-'.repeat(50));

        // Step 11: Check member profile
        console.log(`\n[Step ${step}] Checking BuddyPress member profile...`);
        await page.goto(`${SITE_URL}/members/steve/`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'member-profile', step++);

        // Look for auctions tab
        const auctionsLink = page.locator('a[href*="auctions"]');
        if (await auctionsLink.count() > 0) {
            console.log('  Found auctions tab in profile!');
            await auctionsLink.first().click();
            await page.waitForLoadState('networkidle');
            await screenshot(page, 'member-auctions-tab', step++);
        } else {
            console.log('  No auctions tab found in member profile');
        }

        // =====================================================
        // PART 6: GUTENBERG BLOCKS
        // =====================================================
        console.log('\n📌 PART 6: GUTENBERG BLOCKS');
        console.log('-'.repeat(50));

        // Step 12: Create new page with blocks
        console.log(`\n[Step ${step}] Testing Gutenberg block inserter...`);
        await page.goto(`${SITE_URL}/wp-admin/post-new.php?post_type=page`);
        await page.waitForLoadState('networkidle');
        await screenshot(page, 'gutenberg-new-page', step++);

        // Wait for editor to load
        await page.waitForTimeout(2000);

        // Try to open block inserter
        const addBlockBtn = page.locator('button.editor-document-tools__inserter-toggle, button[aria-label*="Add block"], button[aria-label*="Toggle block inserter"]');
        if (await addBlockBtn.count() > 0) {
            console.log('  Opening block inserter...');
            await addBlockBtn.first().click();
            await page.waitForTimeout(1000);
            await screenshot(page, 'block-inserter-open', step++);

            // Search for auction blocks
            const searchInput = page.locator('input[placeholder*="Search"], input[type="search"]');
            if (await searchInput.count() > 0) {
                await searchInput.first().fill('auction');
                await page.waitForTimeout(1000);
                await screenshot(page, 'block-search-auction', step++);
                console.log('  Searched for "auction" blocks');
            }
        }

        // =====================================================
        // SUMMARY
        // =====================================================
        console.log('\n' + '═'.repeat(60));
        console.log('📊 TEST SUMMARY');
        console.log('═'.repeat(60));
        console.log(`\n  Screenshots saved to: ${SCREENSHOTS_DIR}`);
        console.log(`  Total steps: ${step - 1}`);
        console.log('\n  To view all screenshots:');
        console.log(`    open ${SCREENSHOTS_DIR}`);
        console.log('\n' + '═'.repeat(60));
    });
});
