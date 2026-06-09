const { chromium } = require('playwright');
const path = require('path');
const BASE = 'http://127.0.0.1:8765';
const DIR = path.join(__dirname, '..', 'public', 'screenshots');

async function login(page, email, password, loginUrl, successUrl) {
  await page.goto(loginUrl, { waitUntil: 'networkidle' });
  await page.fill('input[type="email"], input[name="email"]', email);
  await page.fill('input[type="password"], input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(successUrl, { timeout: 10000 }).catch(() => {});
  await page.waitForTimeout(1000);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();

  // Login as customer
  await login(p, 'customer@multivendor.test', 'password', BASE + '/login', '**/');

  // Cart
  await p.goto(BASE + '/cart', { waitUntil: 'networkidle' });
  await p.screenshot({ path: path.join(DIR, 'store-cart.png'), fullPage: true });
  console.log('✓ store-cart');

  // Wishlist
  await p.goto(BASE + '/wishlist', { waitUntil: 'networkidle' });
  await p.screenshot({ path: path.join(DIR, 'store-wishlist.png'), fullPage: true });
  console.log('✓ store-wishlist');

  // Compare
  await p.goto(BASE + '/compare', { waitUntil: 'networkidle' });
  await p.screenshot({ path: path.join(DIR, 'store-compare.png'), fullPage: true });
  console.log('✓ store-compare');

  // Orders
  await p.goto(BASE + '/orders', { waitUntil: 'networkidle' });
  await p.screenshot({ path: path.join(DIR, 'store-orders.png'), fullPage: true });
  console.log('✓ store-orders');

  // Track Order
  await p.goto(BASE + '/track-order', { waitUntil: 'networkidle' });
  await p.screenshot({ path: path.join(DIR, 'store-track-order.png'), fullPage: true });
  console.log('✓ store-track-order');

  // Loyalty
  await p.goto(BASE + '/loyalty', { waitUntil: 'networkidle' });
  await p.screenshot({ path: path.join(DIR, 'store-loyalty.png'), fullPage: true });
  console.log('✓ store-loyalty');

  // Profile
  await p.goto(BASE + '/profile', { waitUntil: 'networkidle' });
  await p.screenshot({ path: path.join(DIR, 'store-profile.png'), fullPage: true });
  console.log('✓ store-profile');

  // Social Feed
  await p.goto(BASE + '/feed', { waitUntil: 'networkidle' });
  await p.screenshot({ path: path.join(DIR, 'store-feed.png'), fullPage: true });
  console.log('✓ store-feed');

  // Group Buys
  await p.goto(BASE + '/group-buys', { waitUntil: 'networkidle' });
  await p.screenshot({ path: path.join(DIR, 'store-group-buys.png'), fullPage: true });
  console.log('✓ store-group-buys');

  // Leaderboard
  await p.goto(BASE + '/leaderboard', { waitUntil: 'networkidle' });
  await p.screenshot({ path: path.join(DIR, 'store-leaderboard.png'), fullPage: true });
  console.log('✓ store-leaderboard');

  // Login as vendor
  const vp = await ctx.newPage();
  await login(vp, 'vendor@multivendor.test', 'password', BASE + '/vendor/login', '**/vendor/dashboard');

  // Vendor orders
  await vp.goto(BASE + '/vendor/orders', { waitUntil: 'networkidle' });
  await vp.screenshot({ path: path.join(DIR, 'vendor-orders.png'), fullPage: true });
  console.log('✓ vendor-orders');

  // Vendor coupons
  await vp.goto(BASE + '/vendor/coupon', { waitUntil: 'networkidle' });
  await vp.screenshot({ path: path.join(DIR, 'vendor-coupons.png'), fullPage: true });
  console.log('✓ vendor-coupons');

  // Vendor reports
  await vp.goto(BASE + '/vendor/report/products', { waitUntil: 'networkidle' });
  await vp.screenshot({ path: path.join(DIR, 'vendor-reports.png'), fullPage: true });
  console.log('✓ vendor-reports');

  // Vendor barcode
  await vp.goto(BASE + '/vendor/barcode', { waitUntil: 'networkidle' });
  await vp.screenshot({ path: path.join(DIR, 'vendor-barcode.png'), fullPage: true });
  console.log('✓ vendor-barcode');

  // Vendor gallery
  await vp.goto(BASE + '/vendor/gallery', { waitUntil: 'networkidle' });
  await vp.screenshot({ path: path.join(DIR, 'vendor-gallery.png'), fullPage: true });
  console.log('✓ vendor-gallery');

  await browser.close();
  console.log('\nDone!');
})();
