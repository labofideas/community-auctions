import { test, expect } from '@playwright/test';

const shouldRun = !!process.env.E2E_BASE_URL && !!process.env.E2E_USER && !!process.env.E2E_PASS;
const listUrl = process.env.E2E_AUCTIONS_LIST_URL || '';
const singleUrl = process.env.E2E_AUCTION_URL || '';

test.describe('Community Auctions - Bid Flow', () => {
  test.skip(!shouldRun, 'Set E2E_BASE_URL, E2E_USER, E2E_PASS to run E2E tests');

  test.skip(!listUrl && !singleUrl, 'Set E2E_AUCTIONS_LIST_URL or E2E_AUCTION_URL to run bid test');
  test('place bid from single auction', async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.locator('#user_login').fill(process.env.E2E_USER || '');
    await page.locator('#user_pass').fill(process.env.E2E_PASS || '');
    await page.getByRole('button', { name: 'Log In' }).click();

    if (singleUrl) {
      await page.goto(singleUrl);
    } else {
      await page.goto(listUrl);
      const firstAuction = page.locator('.community-auction-card a').first();
      await firstAuction.click();
    }

    const bidForm = page.locator('.community-auction-bid-form');
    await expect(bidForm).toBeVisible();

    await bidForm.getByLabel('Your Bid').fill('50');
    await bidForm.getByRole('button', { name: 'Place Bid' }).click();

    await expect(page.locator('.ca-bid-message')).toContainText('Bid');
  });
});
