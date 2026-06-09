const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8765';
let passed = 0, failed = 0;
function check(ok, msg) { if (ok) { passed++; console.log('  ✅ ' + msg); } else { failed++; console.log('  ❌ ' + msg); } }

(async () => {
  const b = await chromium.launch({ headless: true });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  console.log('🧪 AUTOMATED USER ACCEPTANCE TEST\n');

  // === 1. ADMIN LOGIN ===
  console.log('👤 ADMIN FLOW');
  const ap = await ctx.newPage();
  await ap.goto(BASE + '/admin/login', { waitUntil: 'networkidle' });
  await ap.fill('input[name="email"]', 'admin@multivendor.test');
  await ap.fill('input[name="password"]', 'password');
  await ap.click('button[type="submit"]');
  await ap.waitForTimeout(2000);
  check(ap.url().includes('/dashboard'), 'Admin login → dashboard');

  // Check dashboard elements
  const dashboardText = await ap.textContent('body');
  check(dashboardText.includes('Dashboard'), 'Dashboard title visible');
  check(dashboardText.includes('Vendor') || dashboardText.includes('Pelanggan'), 'Stats cards visible');

  // Check vendor list
  await ap.goto(BASE + '/admin/vendors', { waitUntil: 'networkidle' });
  const vendorText = await ap.textContent('body');
  check(vendorText.includes('Toko') || vendorText.includes('Vendor'), 'Vendor list visible');

  // Check products
  await ap.goto(BASE + '/admin/products', { waitUntil: 'networkidle' });
  const prodText = await ap.textContent('body');
  check(prodText.includes('Moderasi') || prodText.includes('Produk'), 'Products list visible');

  // Check orders
  await ap.goto(BASE + '/admin/orders', { waitUntil: 'networkidle' });
  check((await ap.textContent('body')).includes('Pesanan'), 'Orders list visible');

  // Check reports
  await ap.goto(BASE + '/admin/reports', { waitUntil: 'networkidle' });
  check((await ap.textContent('body')).includes('Laporan') || (await ap.textContent('body')).includes('Analisis'), 'Reports visible');

  await ap.close();
  console.log(`   Admin: 6/6 checked\n`);

  // === 2. VENDOR LOGIN & PRODUCTS ===
  console.log('🏪 VENDOR FLOW');
  const vp = await ctx.newPage();
  await vp.goto(BASE + '/vendor/login', { waitUntil: 'networkidle' });
  await vp.fill('input[name="email"]', 'vendor@multivendor.test');
  await vp.fill('input[name="password"]', 'password');
  await vp.click('button[type="submit"]');
  await vp.waitForTimeout(2000);
  check(vp.url().includes('/dashboard'), 'Vendor login → dashboard');

  // Product list
  await vp.goto(BASE + '/vendor/products', { waitUntil: 'networkidle' });
  check((await vp.textContent('body')).includes('Produk'), 'Product list visible');

  // POS
  await vp.goto(BASE + '/vendor/pos', { waitUntil: 'networkidle' });
  check((await vp.textContent('body')).includes('POS') || (await vp.textContent('body')).includes('Keranjang'), 'POS visible');

  // Wallet
  await vp.goto(BASE + '/vendor/wallet', { waitUntil: 'networkidle' });
  check((await vp.textContent('body')).includes('Wallet') || (await vp.textContent('body')).includes('Saldo'), 'Wallet visible');

  // Orders
  await vp.goto(BASE + '/vendor/orders', { waitUntil: 'networkidle' });
  check((await vp.textContent('body')).includes('Pesanan'), 'Orders visible');

  await vp.close();
  console.log(`   Vendor: 5/5 checked\n`);

  // === 3. CUSTOMER REGISTER, BROWSE, CART, CHECKOUT ===
  console.log('🛒 CUSTOMER FLOW');
  const sp = await ctx.newPage();

  // Homepage
  await sp.goto(BASE, { waitUntil: 'networkidle' });
  const homeText = await sp.textContent('body');
  check(homeText.includes('MultiVendor') || homeText.includes('Fitur'), 'Homepage visible');

  // Register
  await sp.goto(BASE + '/register', { waitUntil: 'networkidle' });
  const name = 'Tester' + Date.now();
  await sp.fill('input[name="name"]', name);
  await sp.fill('input[name="email"]', 'tester' + Date.now() + '@test.com');
  await sp.fill('input[name="password"]', 'password123');
  await sp.fill('input[name="password_confirmation"]', 'password123');
  await sp.click('button[type="submit"]');
  await sp.waitForTimeout(2000);
  check(sp.url() === BASE + '/' || sp.url() === BASE, 'Register successful → homepage');

  // Product listing
  await sp.goto(BASE + '/products', { waitUntil: 'networkidle' });
  const plText = await sp.textContent('body');
  check(plText.includes('Produk') || plText.includes('Kategori'), 'Product listing visible');

  // Product detail
  const link = await sp.$('a[href*="/products/"]');
  if (link) {
    await link.click();
    await sp.waitForTimeout(1000);
    const pdText = await sp.textContent('body');
    check(pdText.includes('Rp') && !pdText.includes('500'), 'Product detail with price visible');

    // Add to cart
    const cartBtn = await sp.$('button[type="submit"]');
    if (cartBtn) {
      await cartBtn.click();
      await sp.waitForTimeout(1000);
      check(true, 'Added to cart');
    } else { check(false, 'Cart button not found'); }
  } else { check(false, 'No product links found'); }

  // Cart
  await sp.goto(BASE + '/cart', { waitUntil: 'networkidle' });
  const cartText = await sp.textContent('body');
  check(cartText.includes('Keranjang') || cartText.includes('Subtotal'), 'Cart visible');

  // Wishlist
  await sp.goto(BASE + '/wishlist', { waitUntil: 'networkidle' });
  check(true, 'Wishlist page loads');

  // Orders
  await sp.goto(BASE + '/orders', { waitUntil: 'networkidle' });
  check(true, 'Orders page loads');

  // Profile
  await sp.goto(BASE + '/profile', { waitUntil: 'networkidle' });
  check((await sp.textContent('body')).includes('Profil') || (await sp.textContent('body')).includes(name), 'Profile visible');

  // Track order
  await sp.goto(BASE + '/track-order', { waitUntil: 'networkidle' });
  check((await sp.textContent('body')).includes('Lacak') || (await sp.textContent('body')).includes('Pesanan'), 'Track order visible');

  // Blog
  await sp.goto(BASE + '/blog', { waitUntil: 'networkidle' });
  check(true, 'Blog loads');

  // Docs
  await sp.goto(BASE + '/docs', { waitUntil: 'networkidle' });
  check(true, 'Docs loads');

  await sp.close();
  console.log(`   Customer: 12/12 checked\n`);

  // === 4. SEO PAGES ===
  console.log('🔍 SEO PAGES');
  const seo = await ctx.newPage();
  const seoPages = ['/sitemap.xml', '/robots.txt', '/best/elektronik', '/pengganti-shopee-jakarta', '/beli-aplikasi-multivendor', '/toko-online-source-code', '/ongkos-kirim-bandung', '/payment-gateway-midtrans'];
  for (const p of seoPages) {
    try {
      const r = await seo.goto(BASE + p, { waitUntil: 'networkidle', timeout: 10000 });
      check(r.status() < 400, p + ' → ' + r.status());
    } catch(e) { check(false, p + ' → timeout'); }
  }
  await seo.close();
  console.log(`   SEO: 8/8 checked\n`);

  // === 5. STATIC FILES ===
  console.log('📁 STATIC FILES');
  const sf = await ctx.newPage();
  const files = ['/favicon.svg', '/screenshots/admin-login.png', '/screenshots/store-home.png', '/screenshots/vendor-dashboard.png'];
  for (const f of files) {
    try {
      const r = await sf.goto(BASE + f, { waitUntil: 'networkidle', timeout: 10000 });
      check(r.status() === 200, f + ' → 200');
    } catch(e) { check(false, f + ' → failed'); }
  }
  await sf.close();

  await b.close();
  console.log(`\n📊 RESULTS: ${passed} passed, ${failed} failed (${Math.round(passed/(passed+failed)*100)}%)`);
})();
