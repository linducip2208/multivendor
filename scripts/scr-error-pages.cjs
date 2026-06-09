const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8765';
(async () => {
  const b = await chromium.launch({ headless: true });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });

  // Admin
  const ap = await ctx.newPage();
  await ap.goto(BASE + '/admin/login', { waitUntil: 'networkidle' });
  await ap.fill('input[name="email"]', 'admin@multivendor.test');
  await ap.fill('input[name="password"]', 'password');
  await ap.click('button[type="submit"]');
  await ap.waitForTimeout(2000);

  const adminPages = ['settings', 'language', 'currency', 'export'];
  for (const p of adminPages) {
    await ap.goto(`${BASE}/admin/${p}`, { waitUntil: 'networkidle', timeout: 10000 });
    await ap.screenshot({ path: `public/screenshots/admin-${p}.png`, fullPage: true });
    console.log('✓ admin/' + p);
  }
  await ap.close();

  // Vendor
  const vp = await ctx.newPage();
  await vp.goto(BASE + '/vendor/login', { waitUntil: 'networkidle' });
  await vp.fill('input[name="email"]', 'vendor@multivendor.test');
  await vp.fill('input[name="password"]', 'password');
  await vp.click('button[type="submit"]');
  await vp.waitForTimeout(2000);

  const vendorPages = ['limited-stock', 'restock-requests', 'refund', 'clearance'];
  for (const p of vendorPages) {
    await vp.goto(`${BASE}/vendor/${p}`, { waitUntil: 'networkidle', timeout: 10000 });
    await vp.screenshot({ path: `public/screenshots/vendor-${p}.png`, fullPage: true });
    console.log('✓ vendor/' + p);
  }
  await vp.close();

  // Storefront (customer logged in)
  const sp = await ctx.newPage();
  await sp.goto(BASE + '/login', { waitUntil: 'networkidle' });
  await sp.fill('input[name="email"]', 'customer@multivendor.test');
  await sp.fill('input[name="password"]', 'password');
  await sp.click('button[type="submit"]');
  await sp.waitForTimeout(2000);

  const storePages = ['feed', 'group-buys', 'leaderboard', 'bundles', 'tickets'];
  for (const p of storePages) {
    await sp.goto(`${BASE}/${p}`, { waitUntil: 'networkidle', timeout: 10000 });
    await sp.screenshot({ path: `public/screenshots/store-${p}.png`, fullPage: true });
    console.log('✓ ' + p);
  }
  await sp.close();

  await b.close();
  console.log('\nDone!');
})();
