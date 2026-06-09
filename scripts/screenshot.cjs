const { chromium } = require('playwright');
const path = require('path');

const BASE = 'http://127.0.0.1:8765';
const SCREENSHOT_DIR = path.join(__dirname, '..', 'public', 'screenshots');

async function login(page, email, password, loginUrl, successUrl) {
  await page.goto(loginUrl, { waitUntil: 'networkidle' });
  await page.fill('input[type="email"], input[name="email"]', email);
  await page.fill('input[type="password"], input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(successUrl, { timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(1000);
}

async function screenshot(page, name, fullPage = true) {
  const filepath = path.join(SCREENSHOT_DIR, name + '.png');
  await page.screenshot({ path: filepath, fullPage });
  console.log('  ✓ ' + name);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });

  console.log('=== Admin Panel Screenshots ===');
  const adminPage = await context.newPage();

  // 1. Admin Login
  await adminPage.goto(BASE + '/admin/login', { waitUntil: 'networkidle' });
  await screenshot(adminPage, 'admin-login', false);

  // 2. Login
  await login(adminPage, 'admin@multivendor.test', 'password', BASE + '/admin/login', '**/admin/dashboard');

  // 3. Dashboard
  await screenshot(adminPage, 'admin-dashboard');

  // 4. Vendors
  await adminPage.goto(BASE + '/admin/vendors', { waitUntil: 'networkidle' });
  await screenshot(adminPage, 'admin-vendors');

  // 5. Products
  await adminPage.goto(BASE + '/admin/products', { waitUntil: 'networkidle' });
  await screenshot(adminPage, 'admin-products');

  // 6. Orders
  await adminPage.goto(BASE + '/admin/orders', { waitUntil: 'networkidle' });
  await screenshot(adminPage, 'admin-orders');

  // 7. Coupons
  await adminPage.goto(BASE + '/admin/coupons', { waitUntil: 'networkidle' });
  await screenshot(adminPage, 'admin-coupons');

  // 8. Flash Deals
  await adminPage.goto(BASE + '/admin/flashdeals', { waitUntil: 'networkidle' });
  await screenshot(adminPage, 'admin-flashdeals');

  // 9. Reports
  await adminPage.goto(BASE + '/admin/reports', { waitUntil: 'networkidle' });
  await screenshot(adminPage, 'admin-reports');

  // 10. Integrasi
  await adminPage.goto(BASE + '/admin/providers', { waitUntil: 'networkidle' });
  await screenshot(adminPage, 'admin-providers');

  await adminPage.close();

  // === Vendor Panel ===
  console.log('=== Vendor Panel Screenshots ===');
  const vendorPage = await context.newPage();

  // 11. Vendor Login
  await vendorPage.goto(BASE + '/vendor/login', { waitUntil: 'networkidle' });
  await screenshot(vendorPage, 'vendor-login', false);

  // 12. Login vendor
  await login(vendorPage, 'vendor@multivendor.test', 'password', BASE + '/vendor/login', '**/vendor/dashboard');

  // 13. Dashboard
  await screenshot(vendorPage, 'vendor-dashboard');

  // 14. Products
  await vendorPage.goto(BASE + '/vendor/products', { waitUntil: 'networkidle' });
  await screenshot(vendorPage, 'vendor-products');

  // 15. POS
  await vendorPage.goto(BASE + '/vendor/pos', { waitUntil: 'networkidle' });
  await screenshot(vendorPage, 'vendor-pos', false);

  // 16. Wallet
  await vendorPage.goto(BASE + '/vendor/wallet', { waitUntil: 'networkidle' });
  await screenshot(vendorPage, 'vendor-wallet');

  // 17. Shop Settings
  await vendorPage.goto(BASE + '/vendor/shop/settings', { waitUntil: 'networkidle' });
  await screenshot(vendorPage, 'vendor-settings');

  await vendorPage.close();

  // === Storefront ===
  console.log('=== Storefront Screenshots ===');
  const storePage = await context.newPage();

  // 18. Homepage
  await storePage.goto(BASE, { waitUntil: 'networkidle' });
  await screenshot(storePage, 'store-home');

  // 19. Products
  await storePage.goto(BASE + '/products', { waitUntil: 'networkidle' });
  await screenshot(storePage, 'store-products');

  // 20. Product Detail
  await storePage.goto(BASE + '/products', { waitUntil: 'networkidle' });
  const firstProduct = await storePage.$('a[href*="/products/"]');
  if (firstProduct) {
    const href = await firstProduct.getAttribute('href');
    await storePage.goto(href, { waitUntil: 'networkidle' });
    await screenshot(storePage, 'store-product-detail');
  }

  // 21. Blog
  await storePage.goto(BASE + '/blog', { waitUntil: 'networkidle' });
  await screenshot(storePage, 'store-blog');

  // 22. Docs
  await storePage.goto(BASE + '/docs', { waitUntil: 'networkidle' });
  await screenshot(storePage, 'store-docs');

  await storePage.close();

  await browser.close();
  console.log('\n✅ All screenshots saved to public/screenshots/');
})();
