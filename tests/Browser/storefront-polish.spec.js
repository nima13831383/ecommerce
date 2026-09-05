import { expect, test } from '@playwright/test';

function captureBrowserFailures(page) {
    const failures = [];

    page.on('console', (message) => {
        if (message.type() === 'error') failures.push(`console: ${message.text()}`);
    });
    page.on('pageerror', (error) => failures.push(`page: ${error.message}`));
    page.on('requestfailed', (request) => failures.push(`network: ${request.url()}`));
    page.on('response', (response) => {
        if (response.status() >= 400) failures.push(`http ${response.status()}: ${response.url()}`);
    });

    return failures;
}

test('desktop storefront preserves the shared header, archive pagination, auth shell, and static pages', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-chromium', 'desktop-only header assertions');
    const failures = captureBrowserFailures(page);
    await page.goto('/products');
    await expect(page.locator('header')).toBeVisible();
    for (const [position, hidden] of [[67, false], [72, true], [77, true]]) {
        await page.evaluate((scrollY) => window.scrollTo(0, scrollY), position);
        await page.waitForTimeout(180);
        await expect(page.locator('.desktop-nav')).toHaveClass(hidden ? /is-hidden/ : /^(?!.*is-hidden).*$/);
        await page.waitForTimeout(180);
        await expect(page.locator('.desktop-nav')).toHaveClass(hidden ? /is-hidden/ : /^(?!.*is-hidden).*$/);
    }
    await page.evaluate(() => window.scrollTo(0, 0));
    await expect(page.locator('.desktop-nav')).not.toHaveClass(/is-hidden/);

    await expect(page.locator('.category-pagination')).toBeVisible();
    await expect(page.locator('.category-pagination [aria-current="page"]')).toContainText('۱');

    await page.goto('/blog');
    await expect(page.locator('.article-pagination')).toBeVisible();
    await expect(page.locator('.article-pagination [aria-current="page"]')).toHaveClass(/is-active/);

    await page.goto('/login');
    await expect(page.locator('header')).toBeVisible();
    await expect(page.locator('#footer')).toHaveCount(0);
    if (await page.locator('#mobile').count()) {
        await expect(page.locator('#mobile')).toBeVisible();
    } else {
        await expect(page.locator('.password-toggle svg use')).toHaveAttribute('href', '#i-eye');
        await page.locator('[data-password-toggle]').click();
        await expect(page.locator('#password')).toHaveAttribute('type', 'text');
    }
    await expect(page.locator('.auth-trust')).toHaveCount(0);
    await expect(page.locator('.auth-card')).toBeVisible();
    expect(await page.locator('body').evaluate((body) => body.scrollWidth <= window.innerWidth)).toBeTruthy();

    await page.goto('/about');
    await expect(page.getByRole('heading', { name: 'داستان ما' })).toBeVisible();
    await page.goto('/faq');
    await expect(page.getByRole('heading', { name: 'سوالات متداول' })).toBeVisible();
    expect(failures).toEqual([]);
});

test('mobile product drawer locks, closes, and sends filtering through the progressive archive contract', async ({ page }, testInfo) => {
    test.skip(!testInfo.project.name.startsWith('mobile-'), 'mobile-only drawer assertions');
    const failures = captureBrowserFailures(page);
    await page.goto('/products');
    const open = page.locator('[data-filter-open]');
    await expect(open).toBeVisible();
    await open.click();

    const drawer = page.locator('#category-filter-drawer');
    await expect(drawer).toHaveClass(/is-open/);
    await expect(page.locator('body')).toHaveClass(/category-filter-open/);
    const firstAccordion = drawer.locator('details').first();
    await firstAccordion.locator('summary').click();
    await expect(firstAccordion).toHaveAttribute('open', '');
    await page.keyboard.press('Escape');
    await expect(drawer).not.toHaveClass(/is-open/);
    await expect(page.locator('body')).not.toHaveClass(/category-filter-open/);

    await open.click();
    const availability = drawer.locator('input[name="in_stock"]');
    await availability.locator('xpath=ancestor::details').locator('summary').click();
    await availability.check();
    await expect(page).toHaveURL(/in_stock=1/);
    await expect(page.locator('.category-pagination a').first()).toHaveAttribute('href', /in_stock=1/);
    await page.locator('.category-filter-backdrop').click({ position: { x: 4, y: 4 } });
    await expect(drawer).not.toHaveClass(/is-open/);

    await open.click();
    await page.locator('[data-filter-close]').click();
    await expect(drawer).not.toHaveClass(/is-open/);
    expect(failures).toEqual([]);
});

test('auth pages retain the shared header and form spacing without a trust section or footer', async ({ page }) => {
    const failures = captureBrowserFailures(page);

    await page.goto('/login');
    await expect(page.locator('header')).toBeVisible();
    await expect(page.locator('.auth-form')).toBeVisible();
    await expect(page.locator('.auth-trust')).toHaveCount(0);
    await expect(page.locator('#footer')).toHaveCount(0);
    if (await page.locator('#mobile').count()) {
        await expect(page.locator('#mobile')).toBeVisible();
        await expect(page.locator('[data-password-toggle]')).toHaveCount(0);
    } else {
        await expect(page.locator('.password-toggle svg use')).toHaveAttribute('href', '#i-eye');
        await page.locator('[data-password-toggle]').click();
        await expect(page.locator('#password')).toHaveAttribute('type', 'text');
    }

    const headerBottom = await page.locator('header').evaluate((header) => header.getBoundingClientRect().bottom);
    const cardTop = await page.locator('.auth-card').evaluate((card) => card.getBoundingClientRect().top);
    expect(cardTop).toBeGreaterThan(headerBottom);
    expect(await page.locator('body').evaluate((body) => body.scrollWidth <= window.innerWidth)).toBeTruthy();
    expect(failures).toEqual([]);
});
