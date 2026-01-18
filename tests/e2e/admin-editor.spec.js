/**
 * Community Auctions - Admin Editor Test
 * Tests the improved admin auction meta box styling
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');

const SITE_URL = 'http://community-hashtags.local';
const SCREENSHOTS_DIR = '/tmp/auction-admin-test';
const ADMIN_USER = 'Steve';
const ADMIN_PASS = 'Steve';

if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

test.describe('Admin Auction Editor', () => {

    test.beforeEach(async ({ page }) => {
        // Login first
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('domcontentloaded');
    });

    test('Admin meta box displays with modern styling', async ({ page }) => {
        console.log('\n' + '='.repeat(60));
        console.log('ADMIN EDITOR TEST');
        console.log('='.repeat(60));

        // Navigate to new auction page
        console.log('\n[1] Loading admin new auction page...');
        await page.goto(`${SITE_URL}/wp-admin/post-new.php?post_type=auction`);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(1000); // Wait for Gutenberg to load
        await page.screenshot({ path: `${SCREENSHOTS_DIR}/01-admin-new-auction.png`, fullPage: true });

        // Check meta box exists
        console.log('\n[2] Checking meta box structure...');
        const metaBox = page.locator('#community_auctions_meta');
        const metaBoxExists = await metaBox.count() > 0;
        console.log(`  Meta box exists: ${metaBoxExists ? 'Yes' : 'No'}`);

        // Check for styled container
        const styledContainer = page.locator('.ca-admin-meta-box');
        const hasStyledContainer = await styledContainer.count() > 0;
        console.log(`  Styled container exists: ${hasStyledContainer ? 'Yes' : 'No'}`);

        // Check sections exist
        const sections = page.locator('.ca-meta-section');
        const sectionCount = await sections.count();
        console.log(`  Number of sections: ${sectionCount}`);
        expect(sectionCount).toBe(3); // Schedule, Pricing, Settings

        // Check section titles
        const sectionTitles = await page.locator('.ca-meta-section-title').allTextContents();
        console.log('  Section titles found:');
        sectionTitles.forEach(title => console.log(`    - ${title.trim()}`));

        // Check for toggle switches
        const toggles = page.locator('.ca-meta-toggle');
        const toggleCount = await toggles.count();
        console.log(`  Toggle switches: ${toggleCount}`);

        // Check for input groups with prefix
        const inputGroups = page.locator('.ca-meta-input-group');
        const inputGroupCount = await inputGroups.count();
        console.log(`  Input groups (with currency prefix): ${inputGroupCount}`);

        // Take screenshot of just the meta box
        console.log('\n[3] Taking meta box screenshot...');
        if (metaBoxExists) {
            await metaBox.screenshot({ path: `${SCREENSHOTS_DIR}/02-meta-box-closeup.png` });
        }

        // Scroll the page to ensure the meta box is visible
        console.log('\n[4] Scrolling to meta box...');
        await page.evaluate(() => {
            const metaBox = document.getElementById('community_auctions_meta');
            if (metaBox) {
                metaBox.scrollIntoView({ behavior: 'instant', block: 'center' });
            }
        });
        await page.waitForTimeout(500);

        await page.screenshot({ path: `${SCREENSHOTS_DIR}/03-meta-box-visible.png`, fullPage: true });
        console.log('  Meta box scrolled into view');

        // Summary
        console.log('\n' + '='.repeat(60));
        console.log('TEST COMPLETE');
        console.log('='.repeat(60));
        console.log(`\nScreenshots saved to: ${SCREENSHOTS_DIR}`);

        // Assertions
        expect(metaBoxExists).toBe(true);
        expect(hasStyledContainer).toBe(true);
        expect(sectionCount).toBe(3);
    });
});
