const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true });
  const p = await b.newPage();
  await p.goto('http://127.0.0.1:8765/login', { waitUntil: 'networkidle' });
  await p.fill('input[type="email"]', 'customer@multivendor.test');
  await p.fill('input[type="password"]', 'password');
  await p.click('button[type="submit"]');
  await p.waitForTimeout(2000);

  // Add product to cart
  await p.goto('http://127.0.0.1:8765/products', { waitUntil: 'networkidle' });
  const link = await p.$('a[href*="/products/"]');
  if (link) {
    await link.click();
    await p.waitForTimeout(1000);
    const addBtn = await p.$('button[type="submit"]');
    if (addBtn) { await addBtn.click(); await p.waitForTimeout(1000); }
  }

  await p.goto('http://127.0.0.1:8765/checkout', { waitUntil: 'networkidle' });
  await p.screenshot({ path: 'public/screenshots/store-checkout.png', fullPage: true });
  console.log('✓ checkout');
  await b.close();
})();
