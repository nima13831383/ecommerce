import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    reporter: 'list',
    use: {
        baseURL: 'http://127.0.0.1:8015',
        trace: 'retain-on-failure',
    },
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8015',
        url: 'http://127.0.0.1:8015',
        reuseExistingServer: true,
        timeout: 30_000,
    },
    projects: [
        { name: 'desktop-chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
        { name: 'tablet-768-chromium', use: { browserName: 'chromium', viewport: { width: 768, height: 1024 } } },
        { name: 'mobile-430-chromium', use: { browserName: 'chromium', viewport: { width: 430, height: 932 }, isMobile: true } },
        { name: 'mobile-390-chromium', use: { browserName: 'chromium', viewport: { width: 390, height: 844 }, isMobile: true } },
        { name: 'mobile-375-chromium', use: { browserName: 'chromium', viewport: { width: 375, height: 812 }, isMobile: true } },
        { name: 'mobile-320-chromium', use: { browserName: 'chromium', viewport: { width: 320, height: 568 }, isMobile: true } },
    ],
});
