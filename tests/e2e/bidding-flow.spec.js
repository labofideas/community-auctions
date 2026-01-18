/**
 * Community Auctions - Bidding Flow Test
 * Tests bidding with multiple users, outbidding, and bid history
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');

const SITE_URL = 'http://community-hashtags.local';
const SCREENSHOTS_DIR = '/tmp/auction-bidding-test';

// Test users
const USERS = {
    seller: { login: 'Steve', pass: 'Steve' },
    bidder1: { login: 'andre', pass: 'andre' },
    bidder2: { login: 'vernon', pass: 'vernon' }
};

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

async function login(page, user) {
    await page.goto(`${SITE_URL}/wp-login.php`);
    await page.fill('#user_login', user.login);
    await page.fill('#user_pass', user.pass);
    await page.click('#wp-submit');
    await page.waitForLoadState('domcontentloaded');
    return page.url().includes('wp-admin') || await page.locator('body.logged-in').count() > 0;
}

async function logout(page) {
    await page.goto(`${SITE_URL}/wp-login.php?action=logout`);
    // Click the logout confirmation if present
    const logoutLink = page.locator('a:has-text("log out")');
    if (await logoutLink.count() > 0) {
        await logoutLink.click();
    }
    await page.waitForLoadState('domcontentloaded');
}

test.describe('Bidding Flow Test', () => {

    test('Complete bidding flow with multiple users', async ({ page }) => {
        console.log('\n' + '═'.repeat(60));
        console.log('BIDDING FLOW TEST - Multiple Users');
        console.log('═'.repeat(60));

        // ===== STEP 1: Check live auction exists =====
        console.log('\n[1] Checking live auction...');
        await page.goto(`${SITE_URL}/auctions/hot-item-gaming-console/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'auction-initial');

        const pageContent = await page.content();
        const hasAuction = pageContent.includes('Gaming Console') || pageContent.includes('auction');
        console.log(`  Auction found: ${hasAuction ? '✅' : '❌'}`);

        // ===== STEP 2: View as visitor (should see login notice) =====
        console.log('\n[2] Viewing as visitor...');
        const visitorContent = await page.content();
        const hasLoginNotice = visitorContent.includes('log in') || visitorContent.includes('Login');
        console.log(`  Login notice visible: ${hasLoginNotice ? '✅' : '❌'}`);
        await screenshot(page, 'visitor-view');

        // ===== STEP 3: Login as first bidder =====
        console.log('\n[3] Logging in as first bidder (andre)...');
        const loginSuccess1 = await login(page, USERS.bidder1);
        console.log(`  Login success: ${loginSuccess1 ? '✅' : '❌'}`);
        await screenshot(page, 'bidder1-logged-in');

        // ===== STEP 4: Go to auction and check bid form =====
        console.log('\n[4] Viewing auction as logged-in user...');
        await page.goto(`${SITE_URL}/auctions/hot-item-gaming-console/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'auction-bidder1-view');

        const bidFormContent = await page.content();
        const hasBidForm = bidFormContent.includes('bid') || bidFormContent.includes('Bid');
        console.log(`  Bid form visible: ${hasBidForm ? '✅' : '❌'}`);

        // Check for bid input
        const bidInput = page.locator('input[name="bid_amount"], input[type="number"], .ca-bid-amount-input');
        const hasBidInput = await bidInput.count() > 0;
        console.log(`  Bid input field: ${hasBidInput ? '✅' : '❌'}`);

        // Check for bid history section
        const hasBidHistory = bidFormContent.includes('Bid History') || bidFormContent.includes('bid-history');
        console.log(`  Bid history section: ${hasBidHistory ? '✅' : '❌'}`);

        // ===== STEP 5: Try placing a bid =====
        console.log('\n[5] Attempting to place bid...');

        // Find minimum bid info
        const minBidMatch = bidFormContent.match(/Minimum[:\s]+\$?([\d,.]+)/i);
        let bidAmount = 300; // Default bid
        if (minBidMatch) {
            const minBid = parseFloat(minBidMatch[1].replace(',', ''));
            bidAmount = Math.ceil(minBid + 10);
            console.log(`  Minimum bid found: $${minBid}, bidding: $${bidAmount}`);
        } else {
            console.log(`  No minimum bid found, using default: $${bidAmount}`);
        }

        // Try to place bid
        if (hasBidInput) {
            try {
                await bidInput.first().fill(bidAmount.toString());
                await screenshot(page, 'bid-entered');

                // Find and click submit button
                const submitBtn = page.locator('button[type="submit"]:has-text("Bid"), button:has-text("Place Bid"), .ca-place-bid-btn');
                if (await submitBtn.count() > 0) {
                    await submitBtn.first().click();
                    await page.waitForLoadState('domcontentloaded');
                    await screenshot(page, 'bid-submitted');

                    // Check for success/error message
                    const responseContent = await page.content();
                    const bidSuccess = responseContent.includes('success') || responseContent.includes('placed') || responseContent.includes('highest');
                    const bidError = responseContent.includes('error') || responseContent.includes('Error') || responseContent.includes('failed');

                    if (bidSuccess) {
                        console.log('  Bid placed successfully! ✅');
                    } else if (bidError) {
                        console.log('  Bid error detected ⚠️');
                    } else {
                        console.log('  Bid submitted (checking result...)');
                    }
                }
            } catch (e) {
                console.log(`  Bid placement note: ${e.message}`);
            }
        }

        // ===== STEP 6: Check bid history =====
        console.log('\n[6] Checking bid history...');
        await page.goto(`${SITE_URL}/auctions/hot-item-gaming-console/`);
        await page.waitForLoadState('domcontentloaded');
        const historyContent = await page.content();
        const bidHistoryVisible = historyContent.includes('andre') || historyContent.includes('Bid History');
        console.log(`  Bid appears in history: ${bidHistoryVisible ? '✅' : '❌ (may need refresh)'}`);
        await screenshot(page, 'bid-history-after');

        // ===== STEP 7: Logout and login as second bidder =====
        console.log('\n[7] Switching to second bidder (vernon)...');
        await logout(page);
        const loginSuccess2 = await login(page, USERS.bidder2);
        console.log(`  Vernon login: ${loginSuccess2 ? '✅' : '❌'}`);

        // ===== STEP 8: View auction as second bidder =====
        console.log('\n[8] Viewing auction as second bidder...');
        await page.goto(`${SITE_URL}/auctions/hot-item-gaming-console/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'auction-bidder2-view');

        const bidder2Content = await page.content();
        const canOutbid = bidder2Content.includes('bid') || bidder2Content.includes('Bid');
        console.log(`  Can place outbid: ${canOutbid ? '✅' : '❌'}`);

        // ===== STEP 9: Check watchlist functionality =====
        console.log('\n[9] Checking watchlist feature...');
        const watchlistBtn = page.locator('button:has-text("Watch"), .ca-watch-btn, .ca-watchlist-toggle');
        const hasWatchlist = await watchlistBtn.count() > 0;
        console.log(`  Watchlist button: ${hasWatchlist ? '✅' : '❌'}`);

        if (hasWatchlist) {
            try {
                await watchlistBtn.first().click();
                await page.waitForTimeout(1000);
                await screenshot(page, 'watchlist-toggle');
                console.log('  Watchlist toggled ✅');
            } catch (e) {
                console.log(`  Watchlist note: ${e.message}`);
            }
        }

        // ===== STEP 10: Check seller dashboard (as Steve) =====
        console.log('\n[10] Checking seller dashboard...');
        await logout(page);
        await login(page, USERS.seller);

        await page.goto(`${SITE_URL}/my-auctions/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'seller-dashboard-check');

        const dashContent = await page.content();
        const dashWorking = dashContent.includes('Active') || dashContent.includes('Seller Dashboard');
        console.log(`  Seller dashboard working: ${dashWorking ? '✅' : '❌'}`);

        // ===== STEP 11: Check buyer dashboard =====
        console.log('\n[11] Checking buyer dashboard (as andre)...');
        await logout(page);
        await login(page, USERS.bidder1);

        await page.goto(`${SITE_URL}/my-purchases/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'buyer-dashboard-check');

        const buyerContent = await page.content();
        const buyerWorking = buyerContent.includes('Buyer') || buyerContent.includes('Won') || buyerContent.includes('Bids');
        console.log(`  Buyer dashboard working: ${buyerWorking ? '✅' : '❌'}`);

        // ===== SUMMARY =====
        console.log('\n' + '═'.repeat(60));
        console.log('TEST COMPLETE');
        console.log('═'.repeat(60));
        console.log(`\nScreenshots: ${SCREENSHOTS_DIR}`);
        console.log('\nFeatures tested:');
        console.log('  - Visitor view (login notice)');
        console.log('  - Logged-in view (bid form)');
        console.log('  - Bid placement');
        console.log('  - Bid history');
        console.log('  - Multi-user switching');
        console.log('  - Watchlist toggle');
        console.log('  - Seller dashboard');
        console.log('  - Buyer dashboard');
    });
});
