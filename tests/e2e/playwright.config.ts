import { defineConfig, devices } from '@playwright/test';

// roda contra a aplicação no ar
export default defineConfig({
    testDir: '.',
    timeout: 30_000,
    expect: { timeout: 7_000 },
    fullyParallel: true,
    reporter: 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://localhost:8793',
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'mobile', use: { ...devices['Pixel 5'] } },
    ],
});
