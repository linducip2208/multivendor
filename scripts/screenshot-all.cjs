const { chromium } = require('playwright');
const path = require('path');
const BASE = 'http://127.0.0.1:8765';
const DIR = path.join(__dirname, '..', 'public', 'screenshots');

async function login(page, email, password, loginUrl) {
  await page.goto(loginUrl, { waitUntil: 'networkidle' });
  await page.fill('input[type="email"], input[name="email"]', email);
  await page.fill('input[type="password"], input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2000);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });

  // === ADMIN (logged in) ===
  console.log('=== ADMIN ===');
  const ap = await ctx.newPage();
  await login(ap, 'admin@multivendor.test', 'password', BASE + '/admin/login');
  
  const adminPages = [
    'dashboard', 'vendors', 'products', 'categories', 'brands', 'orders', 'transactions',
    'coupons', 'flashdeals', 'deals', 'featured-deals', 'most-demanded',
    'customers', 'delivery-men', 'employees',
    'banners', 'blog', 'push-notifications', 'product-seo', 'support-tickets',
    'settings', 'language', 'currency', 'vat', 'translation', 'roles',
    'withdraws', 'email-templates', 'offline-payment', 'providers',
    'file-manager', 'reports', 'stock-report', 'vendor-sale-report', 'export',
    'sms-gateway', 'third-party', 'maintenance', 'pages', 'help-topics', 'contacts',
    'vendor-settings', 'inhouse-shop', 'bundles', 'notifications',
  ];
  for (const page of adminPages) {
    try {
      await ap.goto(`${BASE}/admin/${page}`, { waitUntil: 'networkidle', timeout: 10000 });
      await ap.screenshot({ path: path.join(DIR, `admin-${page}.png`), fullPage: true });
      console.log('  ✓ admin/' + page);
    } catch (e) { console.log('  ✗ admin/' + page); }
  }
  await ap.close();

  // === VENDOR (logged in) ===
  console.log('=== VENDOR ===');
  const vp = await ctx.newPage();
  await login(vp, 'vendor@multivendor.test', 'password', BASE + '/vendor/login');
  
  const vendorPages = [
    'dashboard', 'products', 'pos', 'orders', 'coupon', 'wallet',
    'report/products', 'report/orders', 'report/transactions',
    'barcode', 'bulk-import', 'gallery', 'digital', 'clearance', 'refund',
    'reviews', 'limited-stock', 'restock-requests', 'shipping', 'chat',
    'shop/settings',
  ];
  for (const page of vendorPages) {
    try {
      await vp.goto(`${BASE}/vendor/${page}`, { waitUntil: 'networkidle', timeout: 10000 });
      await vp.screenshot({ path: path.join(DIR, `vendor-${page.replace(/\//g,'-')}.png`), fullPage: true });
      console.log('  ✓ vendor/' + page);
    } catch (e) { console.log('  ✗ vendor/' + page); }
  }
  await vp.close();

  // === STOREFRONT ===
  console.log('=== STOREFRONT ===');
  const sp = await ctx.newPage();
  await login(sp, 'customer@multivendor.test', 'password', BASE + '/login');

  const storePages = [
    '', 'products', 'blog', 'docs', 'feed', 'group-buys', 'leaderboard', 'bundles',
    'cart', 'wishlist', 'compare', 'orders', 'track-order', 'loyalty', 'profile',
    'tickets', 'sitemap.xml', 'robots.txt',
  ];
  for (const page of storePages) {
    try {
      const url = page ? `${BASE}/${page}` : BASE;
      await sp.goto(url, { waitUntil: 'networkidle', timeout: 10000 });
      await sp.screenshot({ path: path.join(DIR, `store-${page||'home'}.png`), fullPage: true });
      console.log('  ✓ ' + (page || 'home'));
    } catch (e) { console.log('  ✗ ' + (page || 'home')); }
  }

  // Product detail
  try {
    await sp.goto(BASE + '/products', { waitUntil: 'networkidle' });
    const first = await sp.$('a[href*="/products/"]');
    if (first) { await first.click(); await sp.waitForTimeout(1000); await sp.screenshot({ path: path.join(DIR, 'store-product-detail.png'), fullPage: true }); console.log('  ✓ product-detail'); }
  } catch (e) { console.log('  ✗ product-detail'); }

  // Blog detail
  try {
    await sp.goto(BASE + '/blog', { waitUntil: 'networkidle' });
    const bfirst = await sp.$('a[href*="/blog/"]');
    if (bfirst) { await bfirst.click(); await sp.waitForTimeout(1000); await sp.screenshot({ path: path.join(DIR, 'store-blog-detail.png'), fullPage: true }); console.log('  ✓ blog-detail'); }
  } catch (e) { console.log('  ✗ blog-detail'); }

  await sp.close();
  await browser.close();
  console.log('\n✅ ALL SCREENSHOTS DONE');
})();
