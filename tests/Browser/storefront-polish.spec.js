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
    await expect(page.locator('.desktop-nav')).not.toContainText('دسته‌بندی‌ها');
    await expect(page.locator('.desktop-nav')).not.toContainText('برندها');
    for (const position of [3, 6, 12, 30, 60]) {
        const before = await page.locator('header').boundingBox();
        await page.evaluate((scrollY) => window.scrollTo(0, scrollY), position);
        await page.waitForTimeout(180);
        const after = await page.locator('header').boundingBox();
        expect(after?.height).toBe(before?.height);
    }
    await page.evaluate(() => window.scrollTo(0, 0));
    await expect(page.locator('header')).toBeVisible();

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

test('only the main header row sticks while the announcement and navigation scroll normally', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-chromium', 'desktop-only sticky-row assertions');

    for (const width of [1440, 1536, 1920]) {
        await page.setViewportSize({ width, height: 1024 });
        await page.goto('/');

        const announcement = page.locator('[data-storefront-announcement]');
        const mainHeader = page.locator('[data-storefront-main-header]');
        const mainRow = page.locator('.main-header.storefront-main-row');
        const navigation = page.locator('[data-storefront-navigation]');
        const top = await Promise.all([
            announcement.boundingBox(),
            mainHeader.boundingBox(),
            mainRow.boundingBox(),
            navigation.boundingBox(),
        ]);

        expect(top.every(Boolean)).toBeTruthy();
        expect(Math.abs(top[0].y)).toBeLessThanOrEqual(1);
        expect(Math.abs(top[1].y - (top[0].y + top[0].height))).toBeLessThanOrEqual(1);
        expect(Math.abs(top[2].y - top[1].y)).toBeLessThanOrEqual(1);
        expect(Math.abs(top[3].y - (top[1].y + top[1].height))).toBeLessThanOrEqual(1);

        await page.evaluate(() => window.scrollTo(0, 220));
        await page.waitForTimeout(100);
        const scrolled = await Promise.all([
            announcement.boundingBox(),
            mainHeader.boundingBox(),
            mainRow.boundingBox(),
            navigation.boundingBox(),
        ]);

        expect(Math.abs(scrolled[1].y)).toBeLessThanOrEqual(1);
        expect(Math.abs(scrolled[2].y)).toBeLessThanOrEqual(1);
        expect(scrolled[1].height).toBe(top[1].height);
        expect(scrolled[2].height).toBe(top[2].height);
        expect(scrolled[0].y + scrolled[0].height).toBeLessThan(0);
        expect(scrolled[3].y + scrolled[3].height).toBeLessThan(0);
    }

    for (const path of ['/', '/products', '/blog', '/account', '/not-a-real-page']) {
        await page.goto(path);
        await expect(page.locator('[data-storefront-main-header]')).toBeVisible();
    }
});

test('mobile main header row reaches the top without scroll jitter', async ({ page }, testInfo) => {
    test.skip(!testInfo.project.name.startsWith('mobile-'), 'mobile-only sticky-row assertions');

    const width = testInfo.project.name === 'mobile-430-chromium' ? 430 : 390;
    await page.setViewportSize({ width, height: 844 });
    await page.goto('/');

    const announcement = page.locator('[data-storefront-announcement]');
    const mainHeader = page.locator('[data-storefront-main-header]');
    const mainRow = page.locator('.mobile-header__row.storefront-main-row');
    const desktopNavigation = page.locator('[data-storefront-navigation]');
    const mobileSearch = page.locator('[data-storefront-mobile-search]');
    const initial = await Promise.all([
        announcement.boundingBox(),
        mainHeader.boundingBox(),
        mainRow.boundingBox(),
        mobileSearch.boundingBox(),
    ]);

    expect(initial.every(Boolean)).toBeTruthy();
    expect(await desktopNavigation.isVisible()).toBeFalsy();
    expect(Math.abs(initial[1].y - (initial[0].y + initial[0].height))).toBeLessThanOrEqual(1);
    expect(Math.abs(initial[2].y - initial[1].y)).toBeLessThanOrEqual(1);
    expect(Math.abs(initial[3].y - (initial[1].y + initial[1].height))).toBeLessThanOrEqual(1);

    const positions = [0, 3, 6, 10, 20, 50, 100];
    const samples = [];
    for (const position of positions) {
        await page.evaluate((scrollY) => window.scrollTo(0, scrollY), position);
        await page.waitForTimeout(60);
        const header = await mainHeader.boundingBox();
        const row = await mainRow.boundingBox();
        samples.push({ position, header, row });
        expect(header.height).toBe(initial[1].height);
        expect(row.height).toBe(initial[2].height);
    }

    for (let index = 1; index < samples.length; index += 1) {
        expect(samples[index].header.y).toBeLessThanOrEqual(samples[index - 1].header.y + 1);
    }
    expect(Math.abs(samples.at(-1).header.y)).toBeLessThanOrEqual(1);
    expect(Math.abs(samples.at(-1).row.y)).toBeLessThanOrEqual(1);

    await page.evaluate(() => window.scrollTo(0, 220));
    await page.waitForTimeout(100);
    const scrolledAnnouncement = await announcement.boundingBox();
    const scrolledSearch = await mobileSearch.boundingBox();
    expect(scrolledAnnouncement.y + scrolledAnnouncement.height).toBeLessThan(0);
    expect(scrolledSearch.y + scrolledSearch.height).toBeLessThan(0);
    expect(Math.abs((await mainHeader.boundingBox()).y)).toBeLessThanOrEqual(1);
});

test('blog pagination stays on the blog archive', async ({ page }) => {
    const failures = captureBrowserFailures(page);
    await page.goto('/blog');

    const secondPage = page.locator('.article-pagination a').filter({ hasText: '۲' });
    await expect(secondPage).toHaveAttribute('href', /\/blog\?page=2/);
    await expect(page.locator('.article-pagination a.is-active')).toHaveAttribute('aria-current', 'page');
    await secondPage.click();
    await expect(page).toHaveURL(/\/blog\?page=2/);
    await expect(page.locator('.blog-intro h1')).toContainText('مجله لوکسیر');
    await expect(page.locator('[data-component="hero-slider"]')).toHaveCount(0);
    await expect(page.locator('.article-pagination a.is-active')).toHaveAttribute('aria-current', 'page');
    expect(await page.evaluate(() => document.body.scrollWidth <= window.innerWidth)).toBeTruthy();
    expect(failures).toEqual([]);
});

test('mobile product drawer locks, closes, and sends filtering through the progressive archive contract', async ({ page }, testInfo) => {
    test.skip(!testInfo.project.name.startsWith('mobile-'), 'mobile-only drawer assertions');
    const failures = captureBrowserFailures(page);
    await page.goto('/products');
    await page.locator('[data-action="menu"]').first().click();
    await expect(page.locator('#mobile-nav')).not.toContainText('دسته‌بندی‌ها');
    await expect(page.locator('#mobile-nav')).not.toContainText('برندها');
    await page.locator('[data-action="close-menu"]').first().click();
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

test('hero maps horizontal trackpad wheel gestures to one slide without hijacking vertical scroll', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-chromium', 'desktop trackpad event assertions');

    await page.goto('/');
    const hero = page.locator('[data-component="hero-slider"]');
    await hero.evaluate((element) => {
        [20, 30, 40].forEach((deltaX) => element.dispatchEvent(new WheelEvent('wheel', { bubbles: true, cancelable: true, deltaX, deltaY: 2 })));
    });
    await page.waitForTimeout(100);
    await expect(hero.locator('.slider-dot.is-active')).toHaveAttribute('data-slide', '1');

    await hero.evaluate((element) => element.dispatchEvent(new WheelEvent('wheel', { bubbles: true, cancelable: true, deltaX: 2, deltaY: 80 })));
    await expect(hero.locator('.slider-dot.is-active')).toHaveAttribute('data-slide', '1');

    await page.waitForTimeout(400);
    await hero.evaluate((element) => {
        [20, 30, 40].forEach((deltaX) => element.dispatchEvent(new WheelEvent('wheel', { bubbles: true, cancelable: true, deltaX: -deltaX, deltaY: 2 })));
    });
    await page.waitForTimeout(100);
    await expect(hero.locator('.slider-dot.is-active')).toHaveAttribute('data-slide', '0');
});

test('hero copy uses physical desktop coordinates that preserve each artwork subject', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop-chromium', 'desktop art-direction assertions');

    for (const width of [1440, 1536, 1920]) {
        await page.setViewportSize({ width, height: 1024 });
        await page.goto('/');

        const hero = page.locator('[data-component="hero-slider"]');

        for (const [slide, expectedSide] of [[0, 'right'], [1, 'left'], [2, 'right']]) {
            await hero.locator('.slider-dot').nth(slide).click();
            await page.waitForTimeout(400);

            const coordinates = await hero.evaluate((element, index) => {
                const heroBox = element.getBoundingClientRect();
                const copyBox = element.querySelector(`.hero-slide--${index + 1} .hero-copy`).getBoundingClientRect();
                const leftArrow = element.querySelector('[data-action="next"]').getBoundingClientRect();
                const rightArrow = element.querySelector('[data-action="prev"]').getBoundingClientRect();

                return {
                    heroCenter: (heroBox.left + heroBox.right) / 2,
                    copyCenter: (copyBox.left + copyBox.right) / 2,
                    copyLeft: copyBox.left,
                    copyRight: copyBox.right,
                    leftArrowRight: leftArrow.right,
                    rightArrowLeft: rightArrow.left,
                };
            }, slide);

            if (expectedSide === 'right') {
                expect(coordinates.copyCenter).toBeGreaterThan(coordinates.heroCenter);
                expect(coordinates.copyRight).toBeLessThan(coordinates.rightArrowLeft - 24);
            } else {
                expect(coordinates.copyCenter).toBeLessThan(coordinates.heroCenter);
                expect(coordinates.copyLeft).toBeGreaterThan(coordinates.leftArrowRight + 24);
            }
        }
    }
});

test('hero arrows, dots, and touch swipes retain the shared slider state', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile-390-chromium', 'mobile touch interaction assertions');

    await page.goto('/');

    const hero = page.locator('[data-component="hero-slider"]');

    await hero.locator('[data-action="next"]').click();
    await page.waitForTimeout(400);
    await expect(hero.locator('.slider-dot.is-active')).toHaveAttribute('data-slide', '1');

    await hero.locator('.slider-dot').nth(2).click();
    await page.waitForTimeout(400);
    await expect(hero.locator('.slider-dot.is-active')).toHaveAttribute('data-slide', '2');

    await hero.evaluate((element) => {
        const touch = (x, y) => new Touch({ identifier: 1, target: element, clientX: x, clientY: y });
        const dispatch = (type, touches, changedTouches) => element.dispatchEvent(new TouchEvent(type, {
            bubbles: true,
            cancelable: true,
            touches,
            changedTouches,
        }));
        const start = touch(240, 150);
        const end = touch(120, 150);

        dispatch('touchstart', [start], []);
        dispatch('touchmove', [end], []);
        dispatch('touchend', [], [end]);
    });
    await page.waitForTimeout(400);
    await expect(hero.locator('.slider-dot.is-active')).toHaveAttribute('data-slide', '0');

    await hero.evaluate((element) => {
        const touch = (x, y) => new Touch({ identifier: 2, target: element, clientX: x, clientY: y });
        const dispatch = (type, touches, changedTouches) => element.dispatchEvent(new TouchEvent(type, {
            bubbles: true,
            cancelable: true,
            touches,
            changedTouches,
        }));
        const start = touch(180, 120);
        const verticalEnd = touch(176, 260);

        dispatch('touchstart', [start], []);
        dispatch('touchmove', [verticalEnd], []);
        dispatch('touchend', [], [verticalEnd]);
    });
    await page.waitForTimeout(100);
    await expect(hero.locator('.slider-dot.is-active')).toHaveAttribute('data-slide', '0');
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
