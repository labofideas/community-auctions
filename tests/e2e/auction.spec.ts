import { test, expect } from '@playwright/test';

const shouldRun = !!process.env.E2E_BASE_URL && !!process.env.E2E_USER && !!process.env.E2E_PASS;
const listUrl = process.env.E2E_AUCTIONS_LIST_URL || '';
const singleUrl = process.env.E2E_AUCTION_URL || '';

test.describe('Community Auctions', () => {
  test.skip(!shouldRun, 'Set E2E_BASE_URL, E2E_USER, E2E_PASS to run E2E tests');

  test.skip(!listUrl, 'Set E2E_AUCTIONS_LIST_URL to run list view test');
  test('list view renders', async ({ page }) => {
    await page.goto(listUrl);
    await expect(page.locator('.community-auctions-list')).toBeVisible();
  });

  test.skip(!listUrl && !singleUrl, 'Set E2E_AUCTIONS_LIST_URL or E2E_AUCTION_URL to run single view test');
  test('single auction bid form visible for logged-in user', async ({ page }) => {
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

    await expect(page.locator('.community-auction-bid-form')).toBeVisible();
  });
});
