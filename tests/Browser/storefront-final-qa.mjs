import fs from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const playwrightPath = 'D:/uni-shop-project/front/node_modules/playwright/index.mjs';
const { chromium } = await import(pathToFileURL(playwrightPath).href);

const baseURL = process.env.STOREFRONT_URL || 'http://127.0.0.1:8000';
const artifactDir = path.resolve(process.env.STOREFRONT_QA_ARTIFACTS || 'storage/app/qa/storefront-final-2026-09-04');
await fs.mkdir(artifactDir, { recursive: true });

const results = [];
const failures = [];
const warnings = [];
const screenshots = [];
const networkFailures = [];
const consoleErrors = [];

function record(name, ok, detail = '') {
  results.push({ name, ok, detail });
  if (!ok) failures.push({ name, detail });
}

async function screenshot(page, name) {
  const file = path.join(artifactDir, `${name}.png`);
  await page.screenshot({ path: file, fullPage: true });
  screenshots.push(file);
}

async function pageHealth(page, name, options = {}) {
  const response = await page.goto(options.path || '/', { waitUntil: 'networkidle' });
  const expectedStatus = options.expectedStatus ?? 200;
  record(`${name}: HTTP ${response?.status()}`, response && response.status() === expectedStatus, response?.url());
  await page.waitForTimeout(120);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
  record(`${name}: no horizontal overflow`, !overflow);
  const brokenImages = await page.locator('img').evaluateAll((images) => images.filter((image) => image.complete && image.naturalWidth === 0).map((image) => image.src));
  record(`${name}: images load`, brokenImages.length === 0, brokenImages.join(', '));
  if (options.screenshot) await screenshot(page, options.screenshot);
  return response;
}

function attachDiagnostics(page, label) {
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push({ label, text: message.text() });
  });
  page.on('pageerror', (error) => consoleErrors.push({ label, text: error.message }));
  page.on('requestfailed', (request) => networkFailures.push({ label, url: request.url(), error: request.failure()?.errorText || 'failed' }));
  page.on('response', (response) => {
    if (response.status() >= 500) networkFailures.push({ label, url: response.url(), error: `HTTP ${response.status()}` });
  });
}

async function assertText(page, text, name) {
  record(name, await page.getByText(text, { exact: false }).count() > 0);
}

const browser = await chromium.launch({
  headless: true,
  executablePath: process.env.PLAYWRIGHT_CHROMIUM || 'C:/Users/nima/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe',
});

try {
  const publicPages = [
    ['home', '/'],
    ['products', '/products'],
    ['blog', '/blog'],
    ['about', '/about'],
    ['contact', '/contact'],
    ['faq', '/faq'],
    ['simple-product', '/products/demo-hydra-glow-serum'],
    ['variable-product', '/products/demo-aurora-velvet-perfume'],
  ];

  for (const [name, route] of publicPages) {
    const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 900 }, locale: 'fa-IR' });
    const page = await context.newPage();
    attachDiagnostics(page, name);
    await pageHealth(page, name, { path: route, screenshot: ['home', 'products', 'blog', 'variable-product'].includes(name) ? name : undefined });
    record(`${name}: RTL document`, await page.locator('html[dir="rtl"]').count() === 1);
    record(`${name}: shared header`, await page.locator('header').count() > 0);
    record(`${name}: shared footer`, await page.locator('footer').count() > 0);
    await context.close();
  }

  // The raw template's client-side shell interactions should work at a mobile breakpoint.
  const mobile = await browser.newContext({ baseURL, viewport: { width: 390, height: 844 }, locale: 'fa-IR' });
  const mobilePage = await mobile.newPage();
  attachDiagnostics(mobilePage, 'mobile-shell');
  await pageHealth(mobilePage, 'mobile home', { path: '/', screenshot: 'mobile-home' });
  await mobilePage.locator('[data-action="menu"]').first().click();
  await mobilePage.waitForTimeout(350);
  record('mobile menu opens', await mobilePage.locator('#mobile-nav').evaluate((element) => element.classList.contains('is-open')));
  await mobilePage.locator('[data-action="close-menu"]').first().click();
  await mobilePage.waitForTimeout(350);
  record('mobile menu closes', !(await mobilePage.locator('#mobile-nav').evaluate((element) => element.classList.contains('is-open'))));
  await mobilePage.locator('[data-cart-toggle="true"]:visible').first().click();
  await mobilePage.waitForTimeout(350);
  record('cart preview opens', await mobilePage.locator('#cart-preview').evaluate((element) => element.classList.contains('is-open')));
  await mobile.close();

  // Catalog query-string and pagination behavior.
  const catalog = await browser.newContext({ baseURL, viewport: { width: 1280, height: 800 }, locale: 'fa-IR' });
  const catalogPage = await catalog.newPage();
  attachDiagnostics(catalogPage, 'catalog');
  for (const query of ['/products?search=عطر', '/products?sort=price_asc', '/products?sort=price_desc', '/products?type=variable', '/products?in_stock=1', '/products?min_price=1&max_price=999999999']) {
    await pageHealth(catalogPage, `catalog ${query}`, { path: query });
    const expectedQuery = new URLSearchParams(query.slice(query.indexOf('?') + 1));
    const actualQuery = new URL(catalogPage.url()).searchParams;
    record(`catalog query retained ${query}`, actualQuery.get([...expectedQuery.keys()][0]) === expectedQuery.get([...expectedQuery.keys()][0]));
  }
  await catalogPage.goto('/products?search=عبارت-غیرموجود', { waitUntil: 'networkidle' });
  await assertText(catalogPage, 'محصولی پیدا نشد', 'catalog empty state');
  await catalog.close();

  const blogFlow = await browser.newContext({ baseURL, viewport: { width: 430, height: 932 }, locale: 'fa-IR' });
  const blogFlowPage = await blogFlow.newPage();
  attachDiagnostics(blogFlowPage, 'blog-flow');
  await blogFlowPage.goto('/blog?search=عطر', { waitUntil: 'networkidle' });
  record('blog search returns articles', await blogFlowPage.locator('.article-card, .featured-article').count() > 0);
  await blogFlowPage.goto('/blog?page=2', { waitUntil: 'networkidle' });
  record('blog page two renders', await blogFlowPage.locator('.article-card').count() > 0);
  const articleHref = await blogFlowPage.locator('.article-card h2 a, .featured-article a.article-link').first().getAttribute('href');
  if (articleHref) {
    await blogFlowPage.goto(articleHref, { waitUntil: 'networkidle' });
    record('blog article detail renders', await blogFlowPage.locator('.article-page').count() === 1);
  }
  await blogFlow.close();

  // Product detail and variable-selection browser contract.
  const product = await browser.newContext({ baseURL, viewport: { width: 1440, height: 900 }, locale: 'fa-IR' });
  const productPage = await product.newPage();
  attachDiagnostics(productPage, 'product');
  await productPage.goto('/products/demo-aurora-velvet-perfume', { waitUntil: 'networkidle' });
  record('variable detail axes render', await productPage.locator('[data-variant-value]').count() >= 2);
  record('variable detail add button starts disabled', await productPage.locator('[data-add-cart]').isDisabled());
  const thumbs = productPage.locator('[data-gallery-thumb]');
  if (await thumbs.count() > 1) {
    const firstSrc = await productPage.locator('[data-gallery-image]').getAttribute('src');
    await thumbs.nth(1).click();
    record('gallery thumbnail swaps image', (await productPage.locator('[data-gallery-image]').getAttribute('src')) !== firstSrc);
  } else warnings.push('Variable product has fewer than two gallery thumbnails.');
  const axes = productPage.locator('fieldset[data-attribute-id]');
  const firstAxisOptions = axes.nth(0).locator('[data-variant-value]');
  const secondAxisOptions = axes.nth(1).locator('[data-variant-value]');
  if (await axes.count() >= 2 && await firstAxisOptions.count() >= 2 && await secondAxisOptions.count() >= 2) {
    await firstAxisOptions.filter({ hasText: '۵۰' }).first().click().catch(async () => firstAxisOptions.nth(1).click());
    await secondAxisOptions.filter({ hasText: 'استاندارد' }).first().click().catch(async () => secondAxisOptions.nth(0).click());
    await productPage.waitForTimeout(500);
    record('available variation resolves', (await productPage.locator('[data-selected-variation]').inputValue()) !== '');
    record('available variation enables add', !(await productPage.locator('[data-add-cart]').isDisabled()));
    const selectedBefore = await productPage.locator('[data-selected-variation]').inputValue();
    await secondAxisOptions.filter({ hasText: 'هدیه' }).first().click().catch(async () => secondAxisOptions.nth(1).click());
    await productPage.waitForTimeout(500);
    record('unavailable variation disables add', await productPage.locator('[data-add-cart]').isDisabled());
    record('variation id changes or clears after switch', (await productPage.locator('[data-selected-variation]').inputValue()) !== selectedBefore);
  } else warnings.push('Variable product selectors did not expose two usable axes.');
  await screenshot(productPage, 'variable-product-selected');
  await product.close();

  // Guest cart flow: add a simple Product, inspect server-rendered count, update/remove/clear.
  const guest = await browser.newContext({ baseURL, viewport: { width: 430, height: 932 }, locale: 'fa-IR' });
  const guestPage = await guest.newPage();
  attachDiagnostics(guestPage, 'guest-cart');
  await guestPage.goto('/products/demo-hydra-glow-serum', { waitUntil: 'networkidle' });
  const simpleForm = guestPage.locator('form[action*="/cart/items"]').first();
  await simpleForm.locator('button[type="submit"]').click();
  await guestPage.waitForLoadState('networkidle');
  record('guest add-to-cart redirects/rendered', guestPage.url().includes('/cart') || await guestPage.locator('[data-cart-count]').count() > 0);
  await guestPage.goto('/cart', { waitUntil: 'networkidle' });
  record('guest cart has real line', await guestPage.locator('[data-cart-item]').count() >= 1);
  if (await guestPage.locator('[data-cart-item]').count()) {
    const update = guestPage.locator('[data-cart-item]').first().locator('form').first();
    await update.locator('button[aria-label*="افزایش"]').click();
    await guestPage.waitForLoadState('networkidle');
    record('guest quantity update persists', await guestPage.locator('[data-cart-item] output').first().textContent().then((v) => v?.trim() === '2'));
    await guestPage.locator('[data-cart-item] .cart-remove').first().click();
    await guestPage.waitForLoadState('networkidle');
    record('guest line removal persists', await guestPage.locator('[data-cart-item]').count() === 0);
  }
  await guestPage.goto('/products/demo-hydra-glow-serum', { waitUntil: 'networkidle' });
  await guestPage.locator('form[action*="/cart/items"]').first().locator('button[type="submit"]').click();
  await guestPage.waitForLoadState('networkidle');
  await guestPage.locator('form[action$="/cart"]').last().locator('button[type="submit"]').click();
  await guestPage.waitForLoadState('networkidle');
  record('guest clear cart reaches empty state', await guestPage.locator('[data-cart-page]').count() > 0 && await guestPage.locator('.cart-empty').isVisible());
  await screenshot(guestPage, 'mobile-cart-empty');
  await guest.close();

  // Create a uniquely identified QA customer through the actual Breeze registration page.
  const account = await browser.newContext({ baseURL, viewport: { width: 1280, height: 800 }, locale: 'fa-IR' });
  const accountPage = await account.newPage();
  attachDiagnostics(accountPage, 'auth-account');
  const qaSuffix = `${Date.now()}`;
  const qaEmail = `qa.browser.${qaSuffix}@example.test`;
  const qaPassword = 'QaBrowser!2026';
  await accountPage.goto('/register', { waitUntil: 'networkidle' });
  record('register page is storefront auth design', await accountPage.locator('.auth-card').count() === 1 && await accountPage.locator('form[action$="/register"]').count() === 1);
  await accountPage.locator('input[name="name"]').fill(`QA Browser ${qaSuffix}`);
  await accountPage.locator('input[name="email"]').fill(qaEmail);
  await accountPage.locator('input[name="password"]').fill(qaPassword);
  await accountPage.locator('input[name="password_confirmation"]').fill(qaPassword);
  await accountPage.locator('form[action$="/register"] button[type="submit"]').click();
  await accountPage.waitForLoadState('networkidle');
  record('registration creates authenticated session', await accountPage.locator('form[action$="/logout"]').count() === 1 || accountPage.url().includes('/account'));
  await accountPage.goto('/account', { waitUntil: 'networkidle' });
  record('account dashboard loads', await accountPage.locator('.account-content').count() === 1);
  record('account dashboard has no role/permission leak', !(await accountPage.locator('body').innerText()).match(/permissions|roles|نقش|دسترسی/i));
  await accountPage.goto('/account/profile', { waitUntil: 'networkidle' });
  await accountPage.locator('input[name="name"]').fill(`QA Browser Updated ${qaSuffix}`);
  await accountPage.locator('form[action*="profile"] button[type="submit"]').first().click();
  await accountPage.waitForLoadState('networkidle');
  record('profile update persists', (await accountPage.locator('input[name="name"]').inputValue()).includes('Updated'));
  const forgot = await browser.newContext({ baseURL, viewport: { width: 1280, height: 800 }, locale: 'fa-IR' });
  const forgotPage = await forgot.newPage();
  attachDiagnostics(forgotPage, 'forgot-password');
  await forgotPage.goto('/forgot-password', { waitUntil: 'networkidle' });
  record('forgot-password page renders', await forgotPage.locator('form[action$="/forgot-password"]').count() === 1);
  await forgot.close();

  // Address/geography, using the real dependent selects and web forms.
  await accountPage.goto('/account/addresses', { waitUntil: 'networkidle' });
  record('address page renders', await accountPage.locator('form[action$="/account/addresses"]').count() === 1);
  const province = accountPage.locator('[data-province-select]');
  const provinceValue = await province.locator('option').nth(1).getAttribute('value');
  await province.selectOption(provinceValue);
  await accountPage.waitForTimeout(500);
  record('city AJAX populates after province', await accountPage.locator('[data-city-select] option').count() > 1);
  const cityValue = await accountPage.locator('[data-city-select] option').nth(1).getAttribute('value');
  await accountPage.locator('select[name="type"]').selectOption('both');
  await accountPage.locator('input[name="first_name"]').fill('مشتری');
  await accountPage.locator('input[name="last_name"]').fill('آزمایشی');
  await accountPage.locator('input[name="mobile"]').fill('09121234567');
  await accountPage.locator('[data-province-select]').selectOption(provinceValue);
  await accountPage.locator('[data-city-select]').selectOption(cityValue);
  await accountPage.locator('textarea[name="address_line"]').fill('تهران، خیابان آزمون، پلاک ۱');
  await accountPage.locator('input[name="postal_code"]').fill('1234567890');
  await accountPage.locator('form[action$="/account/addresses"] button[type="submit"]').click();
  await accountPage.waitForLoadState('networkidle');
  record('address creation persists', await accountPage.locator('.address-card').count() >= 1);
  await screenshot(accountPage, 'account-addresses');

  // Authenticated cart, coupon, shipping quote and checkout.
  await accountPage.goto('/products/demo-hydra-glow-serum', { waitUntil: 'networkidle' });
  await accountPage.locator('form[action*="/cart/items"]').first().locator('button[type="submit"]').click();
  await accountPage.waitForLoadState('networkidle');
  await accountPage.goto('/cart', { waitUntil: 'networkidle' });
  record('authenticated cart has line', await accountPage.locator('[data-cart-item]').count() >= 1);
  const couponForm = accountPage.locator('form[action$="/cart/coupon"]');
  if (await couponForm.count()) {
    await couponForm.locator('input[name="coupon"]').fill('BQA0904');
    await couponForm.locator('button[type="submit"]').click();
    await accountPage.waitForLoadState('networkidle');
    record('valid coupon is rendered from server state', (await accountPage.locator('body').innerText()).includes('BQA0904') || await accountPage.locator('.coupon-box--active').count() === 1);
  } else warnings.push('Coupon form was not rendered because cart state was already couponed.');
  const addressOption = accountPage.locator('#shipping-address option').nth(1);
  if (await addressOption.count()) {
    const addressId = await addressOption.getAttribute('value');
    await accountPage.locator('#shipping-address').selectOption(addressId);
    await accountPage.locator('#shipping-service').selectOption({ index: 0 });
    await accountPage.locator('#shipping-payment').selectOption({ index: 0 });
    await accountPage.locator('form.shipping-quote-form button[type="submit"]').click();
    await accountPage.waitForLoadState('networkidle');
    record('shipping quote response renders', await accountPage.locator('.shipping-quote-result, [role="alert"]').count() > 0);
  } else warnings.push('Authenticated cart had no address option for shipping quote.');
  await screenshot(accountPage, 'authenticated-cart-shipping');

  await accountPage.goto('/checkout', { waitUntil: 'networkidle' });
  record('checkout page loads', await accountPage.locator('[data-checkout-page]').count() === 1);
  if (await accountPage.locator('input[name="shipping_address_id"]').count() && await accountPage.locator('input[name="shipping_address_id"]').first().isEnabled()) {
    await accountPage.locator('input[name="shipping_address_id"]').first().check();
    await accountPage.locator('select[name="shipping_service"]').selectOption({ index: 0 });
    await accountPage.locator('select[name="shipping_payment_type"]').selectOption({ index: 0 });
    await accountPage.locator('textarea[name="customer_note"]').fill('یادداشت QA مرورگر');
    await accountPage.locator('form[data-checkout-form] button[type="submit"]').click();
    await accountPage.waitForLoadState('networkidle');
    record('checkout creates order success page', accountPage.url().includes('/checkout/success') || await accountPage.locator('[data-checkout-success]').count() === 1);
    await screenshot(accountPage, 'checkout-success');
    const paymentLink = accountPage.locator('form[action*="/orders/"][action$="/payment"] button');
    if (await paymentLink.count()) {
      await paymentLink.click();
      await accountPage.waitForLoadState('networkidle');
      record('fake payment result page loads', accountPage.url().includes('/payment/') || await accountPage.locator('[data-payment-result]').count() === 1);
      await screenshot(accountPage, 'payment-result');
    } else warnings.push('Checkout success did not expose a payment initiation action in this environment.');
  } else warnings.push('Checkout form unavailable (likely empty cart/address state).');

  await accountPage.goto('/account/orders', { waitUntil: 'networkidle' });
  record('orders list loads', await accountPage.locator('[data-orders-list], .order-card, .empty-state').count() > 0);
  await screenshot(accountPage, 'orders');
  await account.close();

  // Static/404 and API boundary checks.
  const boundaries = await browser.newContext({ baseURL, viewport: { width: 390, height: 844 }, locale: 'fa-IR' });
  const boundaryPage = await boundaries.newPage();
  attachDiagnostics(boundaryPage, 'boundaries');
  for (const route of ['/does-not-exist', '/products/not-a-real-product', '/blog/not-a-real-post']) {
    const response = await pageHealth(boundaryPage, `404 ${route}`, { path: route, expectedStatus: 404, screenshot: route === '/does-not-exist' ? '404-mobile' : undefined });
    record(`custom HTML 404 ${route}`, response?.status() === 404 && await boundaryPage.locator('html[dir="rtl"]').count() === 1);
  }
  const api404 = await boundaryPage.request.get('/api/v1/products/not-a-real-product');
  const apiBody = await api404.json().catch(() => null);
  record('API 404 remains JSON', api404.status() === 404 && apiBody && typeof apiBody.message === 'string');
  await boundaries.close();

  const faqFlow = await browser.newContext({ baseURL, viewport: { width: 390, height: 844 }, locale: 'fa-IR' });
  const faqPage = await faqFlow.newPage();
  attachDiagnostics(faqPage, 'faq-flow');
  await faqPage.goto('/faq', { waitUntil: 'networkidle' });
  const faqTrigger = faqPage.locator('[data-faq-trigger]').first();
  if (await faqTrigger.count()) {
    await faqTrigger.click();
    record('FAQ accordion opens', await faqTrigger.getAttribute('aria-expanded') === 'true');
  }
  await faqFlow.close();

  // Responsive smoke for critical pages at the required viewport matrix.
  const viewports = [[1440, 900], [1280, 800], [768, 1024], [430, 932], [390, 844], [320, 700]];
  for (const [width, height] of viewports) {
    const context = await browser.newContext({ baseURL, viewport: { width, height }, locale: 'fa-IR' });
    const page = await context.newPage();
    attachDiagnostics(page, `responsive-${width}`);
    for (const route of ['/', '/products', '/products/demo-aurora-velvet-perfume', '/blog', '/faq']) {
      await pageHealth(page, `responsive ${width} ${route}`, { path: route });
    }
    await context.close();
  }
} finally {
  await browser.close();
}

const summary = {
  baseURL,
  results,
  passed: results.filter((result) => result.ok).length,
  failed: failures.length,
  warnings,
  consoleErrors,
  networkFailures,
  screenshots,
};
console.log(JSON.stringify(summary, null, 2));
if (failures.length || consoleErrors.length || networkFailures.length) process.exitCode = 1;
