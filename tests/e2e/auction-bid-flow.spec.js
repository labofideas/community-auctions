/**
 * Auction Bidding Flow Test
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');

const SITE_URL = 'http://community-hashtags.local';
const SCREENSHOTS_DIR = '/tmp/auction-screenshots';

if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

test.describe('Auction Bidding Flow', () => {
    test('View auction with bidding interface', async ({ page }) => {
        console.log('\n' + '═'.repeat(60));
        console.log('AUCTION BIDDING FLOW TEST');
        console.log('═'.repeat(60));

        // 1. Visit auctions archive
        console.log('\n[1] Visiting auctions archive...');
        await page.goto(`${SITE_URL}/auctions/`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/bid-01-archive.png`, fullPage: true });
        console.log('  Screenshot: bid-01-archive.png');

        // 2. View live auction as visitor
        console.log('\n[2] Viewing live auction as visitor...');
        await page.goto(`${SITE_URL}/auctions/verification-test/`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/bid-02-auction-visitor.png`, fullPage: true });
        console.log('  Screenshot: bid-02-auction-visitor.png');

        // Check for auction elements
        const hasPrice = await page.locator('.ca-price-value, .ca-auction-pricing-box').count() > 0;
        const hasBidForm = await page.locator('.ca-bid-form, .ca-bid-form-section').count() > 0;
        const hasCountdown = await page.locator('.ca-countdown-section, [data-ca-countdown]').count() > 0;
        const hasLoginNotice = await page.locator('.ca-login-notice').count() > 0;

        console.log('  Page elements:');
        console.log(`    - Price display: ${hasPrice ? 'YES' : 'NO'}`);
        console.log(`    - Bid form section: ${hasBidForm ? 'YES' : 'NO'}`);
        console.log(`    - Countdown timer: ${hasCountdown ? 'YES' : 'NO'}`);
        console.log(`    - Login notice: ${hasLoginNotice ? 'YES' : 'NO'}`);

        // 3. Login
        console.log('\n[3] Logging in...');
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', 'Steve');
        await page.fill('#user_pass', 'Steve');
        await page.click('#wp-submit');
        await page.waitForLoadState('networkidle');

        if (page.url().includes('wp-admin')) {
            console.log('  Login successful!');
        }

        // 4. View auction as logged-in user
        console.log('\n[4] Viewing auction as logged-in user...');
        await page.goto(`${SITE_URL}/auctions/verification-test/`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/bid-03-auction-logged-in.png`, fullPage: true });
        console.log('  Screenshot: bid-03-auction-logged-in.png');

        // Check for bid form
        const bidFormVisible = await page.locator('.ca-bid-form, .ca-bid-amount-input, input[name="bid_amount"]').count() > 0;
        const bidButtonVisible = await page.locator('.ca-place-bid-btn, button:has-text("Place Bid")').count() > 0;

        console.log('  Logged-in elements:');
        console.log(`    - Bid input visible: ${bidFormVisible ? 'YES' : 'NO'}`);
        console.log(`    - Place Bid button: ${bidButtonVisible ? 'YES' : 'NO'}`);

        // 5. Check other auctions
        console.log('\n[5] Checking another auction...');
        await page.goto(`${SITE_URL}/auctions/test-auction/`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/bid-04-test-auction.png`, fullPage: true });
        console.log('  Screenshot: bid-04-test-auction.png');

        console.log('\n' + '═'.repeat(60));
        console.log('TEST COMPLETE');
        console.log('═'.repeat(60));
        console.log(`\nScreenshots: ${SCREENSHOTS_DIR}`);
    });
});
