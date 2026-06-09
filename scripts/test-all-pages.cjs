const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8765';
(async () => {
  const b = await chromium.launch({ headless: true });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const errors = [];

  // Admin pages test (logged in)
  const ap = await ctx.newPage();
  try {
    await ap.goto(BASE + '/admin/login', { waitUntil: 'networkidle', timeout: 15000 });
    await ap.fill('input[name="email"]', 'admin@multivendor.test');
    await ap.fill('input[name="password"]', 'password');
    await ap.click('button[type="submit"]');
    await ap.waitForTimeout(3000);
    console.log('✓ Admin logged in');
  } catch(e) { console.log('✗ Admin login failed:', e.message); await ap.close(); }

  const adminPages = [
    'dashboard','vendors','products','categories','brands','orders','transactions',
    'coupons','flashdeals','deals','featured-deals','most-demanded',
    'customers','delivery-men','employees',
    'banners','blog','push-notifications','product-seo','support-tickets',
    'settings','language','currency','vat','translation','roles',
    'withdraws','email-templates','offline-payment','providers','file-manager',
    'reports','stock-report','vendor-sale-report','export',
    'sms-gateway','third-party','maintenance','pages','help-topics','contacts',
    'vendor-settings','inhouse-shop','notifications',
  ];
  
  for (const page of adminPages) {
    try {
      const resp = await ap.goto(`${BASE}/admin/${page}`, { waitUntil: 'networkidle', timeout: 10000 });
      const status = resp.status();
      const url = ap.url();
      if (status >= 500) {
        errors.push(`❌ admin/${page} → ${status}`);
        console.log(`❌ admin/${page} → ${status}`);
      } else {
        console.log(`✓ admin/${page} → ${status}`);
      }
    } catch(e) {
      errors.push(`❌ admin/${page} → ${e.message}`);
      console.log(`❌ admin/${page} → ${e.message}`);
    }
  }
  await ap.close();

  // Vendor pages
  const vp = await ctx.newPage();
  try {
    await vp.goto(BASE + '/vendor/login', { waitUntil: 'networkidle', timeout: 15000 });
    await vp.fill('input[name="email"]', 'vendor@multivendor.test');
    await vp.fill('input[name="password"]', 'password');
    await vp.click('button[type="submit"]');
    await vp.waitForTimeout(3000);
    console.log('\n✓ Vendor logged in');
  } catch(e) { console.log('\n✗ Vendor login failed'); await vp.close(); }

  const vendorPages = [
    'dashboard','products','pos','orders','coupon','wallet',
    'report/products','report/orders','report/transactions',
    'barcode','bulk-import','gallery','digital','clearance','refund',
    'reviews','limited-stock','restock-requests','shipping','chat',
    'shop/settings',
  ];
  
  for (const page of vendorPages) {
    try {
      const resp = await vp.goto(`${BASE}/vendor/${page}`, { waitUntil: 'networkidle', timeout: 10000 });
      const status = resp.status();
      if (status >= 500) {
        errors.push(`❌ vendor/${page} → ${status}`);
        console.log(`❌ vendor/${page} → ${status}`);
      } else {
        console.log(`✓ vendor/${page} → ${status}`);
      }
    } catch(e) {
      errors.push(`❌ vendor/${page} → ${e.message}`);
      console.log(`❌ vendor/${page} → ${e.message}`);
    }
  }
  await vp.close();

  // Storefront pages
  const sp = await ctx.newPage();
  try {
    await sp.goto(BASE + '/login', { waitUntil: 'networkidle', timeout: 15000 });
    await sp.fill('input[name="email"]', 'customer@multivendor.test');
    await sp.fill('input[name="password"]', 'password');
    await sp.click('button[type="submit"]');
    await sp.waitForTimeout(3000);
    console.log('\n✓ Customer logged in');
  } catch(e) { console.log('\n✗ Customer login failed'); }

  const storePages = [
    '', 'products', 'blog', 'docs', 'feed', 'group-buys', 'leaderboard',
    'cart', 'wishlist', 'compare', 'orders', 'track-order', 'loyalty', 'profile',
    'tickets', 'bundles',
  ];
  
  for (const page of storePages) {
    try {
      const url = page ? `${BASE}/${page}` : BASE;
      const resp = await sp.goto(url, { waitUntil: 'networkidle', timeout: 10000 });
      const status = resp.status();
      if (status >= 500) {
        errors.push(`❌ ${page||'home'} → ${status}`);
        console.log(`❌ ${page||'home'} → ${status}`);
      } else {
        console.log(`✓ ${page||'home'} → ${status}`);
      }
    } catch(e) {
      errors.push(`❌ ${page||'home'} → ${e.message}`);
      console.log(`❌ ${page||'home'} → ${e.message}`);
    }
  }
  await sp.close();

  await b.close();
  console.log('\n\n=== ERRORS ===');
  if (errors.length === 0) console.log('✅ ALL PAGES PASS');
  else errors.forEach(e => console.log(e));
})();
