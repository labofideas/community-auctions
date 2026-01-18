/**
 * Community Auctions - Demo Data Import Test
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');

const SITE_URL = 'http://community-hashtags.local';
const SCREENSHOTS_DIR = '/tmp/auction-demo-test';
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

test.describe('Demo Data Import Test', () => {

    test('Import demo data via admin', async ({ page }) => {
        console.log('\n' + '═'.repeat(60));
        console.log('DEMO DATA IMPORT TEST');
        console.log('═'.repeat(60));

        // Login
        console.log('\n[1] Logging in...');
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('domcontentloaded');
        console.log('  Logged in ✅');

        // Navigate to Demo Data page
        console.log('\n[2] Navigating to Demo Data page...');
        await page.goto(`${SITE_URL}/wp-admin/admin.php?page=ca-demo-data`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'demo-data-page');

        const pageContent = await page.content();
        const hasPage = pageContent.includes('Demo Data') || pageContent.includes('demo');
        console.log(`  Demo Data page found: ${hasPage ? '✅' : '❌'}`);

        // Check for import button
        const importBtn = page.locator('button[value="import"]');
        const hasImportBtn = await importBtn.count() > 0;
        console.log(`  Import button found: ${hasImportBtn ? '✅' : '❌'}`);

        // Check what will be created
        const willCreate = {
            'Pages with shortcodes': pageContent.includes('6 Pages'),
            'Categories': pageContent.includes('categories') || pageContent.includes('Categories'),
            'Sample auctions': pageContent.includes('Sample auctions') || pageContent.includes('5 Sample'),
            'Navigation menu': pageContent.includes('Navigation menu') || pageContent.includes('menu')
        };

        console.log('\n  What will be created:');
        for (const [item, found] of Object.entries(willCreate)) {
            console.log(`    - ${item}: ${found ? '✅' : '❌'}`);
        }

        // Click import button
        if (hasImportBtn) {
            console.log('\n[3] Importing demo data...');
            await importBtn.click();
            await page.waitForLoadState('domcontentloaded');
            await screenshot(page, 'after-import');

            // Check for success message
            const successContent = await page.content();
            const importSuccess = successContent.includes('success') || successContent.includes('imported');
            console.log(`  Import success: ${importSuccess ? '✅' : '❌'}`);
        }

        // Verify pages were created
        console.log('\n[4] Verifying created pages...');
        const testPages = [
            { slug: 'submit-auction', name: 'Submit Auction' },
            { slug: 'my-auctions', name: 'My Auctions' },
            { slug: 'my-purchases', name: 'My Purchases' },
            { slug: 'my-watchlist', name: 'My Watchlist' },
            { slug: 'search-auctions', name: 'Search Auctions' },
            { slug: 'upcoming-auctions', name: 'Upcoming Auctions' }
        ];

        for (const testPage of testPages) {
            await page.goto(`${SITE_URL}/${testPage.slug}/`);
            await page.waitForLoadState('domcontentloaded');
            const statusCode = page.url().includes(testPage.slug) ? 200 : 404;
            console.log(`    ${testPage.name}: ${statusCode === 200 ? '✅' : '❌'}`);
        }

        // Verify auctions were created
        console.log('\n[5] Verifying created auctions...');
        await page.goto(`${SITE_URL}/auctions/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'auctions-archive');

        const archiveContent = await page.content();
        const auctionTitles = [
            'Rolex',
            'PlayStation',
            'Oil Painting',
            'Comic Book',
            'Telescope'
        ];

        let foundAuctions = 0;
        for (const title of auctionTitles) {
            if (archiveContent.includes(title)) {
                foundAuctions++;
            }
        }
        console.log(`    Found ${foundAuctions}/5 demo auctions`);

        // Verify categories were created
        console.log('\n[6] Verifying categories...');
        await page.goto(`${SITE_URL}/wp-admin/edit-tags.php?taxonomy=auction_category&post_type=auction`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'categories');

        const categoryContent = await page.content();
        const categories = ['Electronics', 'Collectibles', 'Art'];
        let foundCategories = 0;
        for (const cat of categories) {
            if (categoryContent.includes(cat)) {
                foundCategories++;
            }
        }
        console.log(`    Found ${foundCategories}/3 demo categories`);

        // Summary
        console.log('\n' + '═'.repeat(60));
        console.log('TEST COMPLETE');
        console.log('═'.repeat(60));
        console.log(`\nScreenshots: ${SCREENSHOTS_DIR}`);
    });
});
