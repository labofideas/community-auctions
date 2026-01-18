/**
 * Community Auctions - Submit Form Test
 * Tests the step-based auction creation wizard
 */

const { test, expect } = require('@playwright/test');
const fs = require('fs');

const SITE_URL = 'http://community-hashtags.local';
const SCREENSHOTS_DIR = '/tmp/auction-submit-test';
const ADMIN_USER = 'Steve';
const ADMIN_PASS = 'Steve';

if (!fs.existsSync(SCREENSHOTS_DIR)) {
    fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });
}

let step = 1;

async function screenshot(page, name) {
    const filename = `${SCREENSHOTS_DIR}/${step.toString().padStart(2, '0')}-${name}.png`;
    await page.screenshot({ path: filename, fullPage: true });
    console.log(`  Screenshot: ${step.toString().padStart(2, '0')}-${name}.png`);
    step++;
}

test.describe('Submit Auction Form', () => {

    test.beforeEach(async ({ page }) => {
        // Login first
        await page.goto(`${SITE_URL}/wp-login.php`);
        await page.fill('#user_login', ADMIN_USER);
        await page.fill('#user_pass', ADMIN_PASS);
        await page.click('#wp-submit');
        await page.waitForLoadState('domcontentloaded');
    });

    test('Step-based form navigation works', async ({ page }) => {
        console.log('\n' + '='.repeat(60));
        console.log('SUBMIT FORM TEST');
        console.log('='.repeat(60));

        // Navigate to submit page
        console.log('\n[1] Loading submit page...');
        await page.goto(`${SITE_URL}/submit-auction/`);
        await page.waitForLoadState('domcontentloaded');
        await screenshot(page, 'step1-initial');

        // Check step 1 is active
        const step1Active = await page.locator('.ca-step[data-step="1"]').evaluate(el =>
            el.classList.contains('ca-step-active')
        );
        console.log(`  Step 1 active: ${step1Active ? 'Yes' : 'No'}`);
        expect(step1Active).toBe(true);

        // Check form elements exist
        const formExists = await page.locator('.ca-submit-form').count() > 0;
        console.log(`  Form exists: ${formExists ? 'Yes' : 'No'}`);
        expect(formExists).toBe(true);

        // Fill step 1
        console.log('\n[2] Filling Step 1 - Item Details...');
        await page.fill('#ca_title', 'Test Auction Item');
        await page.fill('#ca_description', 'This is a test auction description with details about the item.');
        await screenshot(page, 'step1-filled');

        // Click next
        console.log('\n[3] Navigating to Step 2...');
        await page.click('.ca-btn-next[data-next="2"]');
        await page.waitForTimeout(500);
        await screenshot(page, 'step2-pricing');

        // Check step 2 is now active
        const step2Active = await page.locator('.ca-step[data-step="2"]').evaluate(el =>
            el.classList.contains('ca-step-active')
        );
        console.log(`  Step 2 active: ${step2Active ? 'Yes' : 'No'}`);
        expect(step2Active).toBe(true);

        // Fill step 2
        console.log('\n[4] Filling Step 2 - Pricing...');
        await page.fill('#ca_start_price', '100');
        await page.fill('#ca_min_increment', '5');
        await screenshot(page, 'step2-filled');

        // Test Buy Now toggle (if visible)
        const buyNowToggle = page.locator('#ca_buy_now_toggle');
        if (await buyNowToggle.count() > 0) {
            console.log('  Testing Buy Now toggle...');
            await buyNowToggle.click();
            await page.waitForTimeout(300);

            const buyNowField = page.locator('#ca_buy_now_field');
            const isVisible = await buyNowField.isVisible();
            console.log(`  Buy Now field visible: ${isVisible ? 'Yes' : 'No'}`);

            if (isVisible) {
                await page.fill('#ca_buy_now_price', '500');
            }
            await screenshot(page, 'step2-buynow');
        }

        // Click next to step 3
        console.log('\n[5] Navigating to Step 3...');
        await page.click('.ca-btn-next[data-next="3"]');
        await page.waitForTimeout(500);
        await screenshot(page, 'step3-schedule');

        // Check duration preview exists
        const durationPreview = await page.locator('.ca-duration-preview').count() > 0;
        console.log(`  Duration preview exists: ${durationPreview ? 'Yes' : 'No'}`);

        // Click next to step 4 (review)
        console.log('\n[6] Navigating to Step 4 (Review)...');
        await page.click('.ca-btn-next[data-next="4"]');
        await page.waitForTimeout(500);
        await screenshot(page, 'step4-review');

        // Check review data is populated
        const reviewTitle = await page.locator('#review-title').textContent();
        console.log(`  Review title: ${reviewTitle}`);
        expect(reviewTitle).toBe('Test Auction Item');

        const reviewPrice = await page.locator('#review-start-price').textContent();
        console.log(`  Review start price: ${reviewPrice}`);
        expect(reviewPrice).toContain('100');

        // Test back navigation
        console.log('\n[7] Testing back navigation...');
        await page.click('.ca-btn-prev[data-prev="3"]');
        await page.waitForTimeout(300);

        const backToStep3 = await page.locator('.ca-step[data-step="3"]').evaluate(el =>
            el.classList.contains('ca-step-active')
        );
        console.log(`  Back to Step 3: ${backToStep3 ? 'Yes' : 'No'}`);
        expect(backToStep3).toBe(true);
        await screenshot(page, 'back-to-step3');

        // Summary
        console.log('\n' + '='.repeat(60));
        console.log('TEST COMPLETE');
        console.log('='.repeat(60));
        console.log(`\nScreenshots saved to: ${SCREENSHOTS_DIR}`);
    });

    test('Form validation works', async ({ page }) => {
        console.log('\n' + '='.repeat(60));
        console.log('VALIDATION TEST');
        console.log('='.repeat(60));

        await page.goto(`${SITE_URL}/submit-auction/`);
        await page.waitForLoadState('domcontentloaded');

        // Try to proceed without filling required fields
        console.log('\n[1] Testing empty form validation...');
        await page.click('.ca-btn-next[data-next="2"]');
        await page.waitForTimeout(300);

        // Should still be on step 1
        const stillStep1 = await page.locator('.ca-step[data-step="1"]').evaluate(el =>
            el.classList.contains('ca-step-active')
        );
        console.log(`  Still on Step 1: ${stillStep1 ? 'Yes' : 'No'}`);
        expect(stillStep1).toBe(true);

        // Check for error message
        const hasError = await page.locator('.ca-error-message').count() > 0;
        console.log(`  Error message shown: ${hasError ? 'Yes' : 'No'}`);
        expect(hasError).toBe(true);

        await screenshot(page, 'validation-error');

        console.log('\n' + '='.repeat(60));
        console.log('VALIDATION TEST COMPLETE');
        console.log('='.repeat(60));
    });
});
