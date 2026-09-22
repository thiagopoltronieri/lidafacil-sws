import { test, expect, Page } from '@playwright/test';

// as senhas vêm do ambiente; há um padrão de desenvolvimento como conveniência
const ADMIN = {
    email: 'admin@lidafacil.local',
    password: process.env.SEED_ADMIN_PASSWORD || 'senha-admin-2026',
};
const CLIENT = {
    email: 'client@lidafacil.local',
    password: process.env.SEED_CLIENT_PASSWORD || 'senha-client-2026',
};

// limpa qualquer token antigo do storage e faz login pela interface
async function login(page: Page, user: { email: string; password: string }): Promise<void> {
    await page.goto('/');
    await page.evaluate(() => {
        try {
            localStorage.clear();
        } catch (e) {
            // ignora storage indisponível
        }
    });
    await page.goto('/');
    await page.locator('#login-email').fill(user.email);
    await page.locator('#login-password').fill(user.password);
    await page.locator('#login-form button[type="submit"]').click();
}

test('admin faz login e enxerga a gestão de usuários', async ({ page }) => {
    await login(page, ADMIN);

    await expect(page.locator('#view-app')).toBeVisible();
    await expect(page.locator('#current-role')).toHaveText('Administrador');
    await expect(page.locator('#new-user-btn')).toBeVisible();
    await expect(page.locator('.data-table')).toBeVisible();
    await expect(page.locator('#response-meta')).toContainText('/api/v1/users -> 200');
});

test('cliente vê apenas o próprio perfil e não acessa o rebanho', async ({ page }) => {
    await login(page, CLIENT);

    await expect(page.locator('#view-app')).toBeVisible();
    await expect(page.locator('#current-role')).toHaveText('Cliente');
    await expect(page.locator('#users-title')).toHaveText('Meu perfil');
    await expect(page.locator('#tab-animals-btn')).toBeHidden();
    await expect(page.locator('#new-user-btn')).toBeHidden();
});

test('credencial inválida mostra erro e não entra no console', async ({ page }) => {
    await login(page, { email: ADMIN.email, password: 'senha-obviamente-errada' });

    await expect(page.locator('#login-error')).toBeVisible();
    await expect(page.locator('#view-app')).toBeHidden();
});
