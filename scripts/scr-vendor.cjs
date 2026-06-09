const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true });
  const p = await b.newPage();
  await p.goto('http://127.0.0.1:8765/vendor/login', { waitUntil: 'networkidle' });
  await p.fill('input[name="email"]', 'vendor@multivendor.test');
  await p.fill('input[name="password"]', 'password');
  await p.click('button[type="submit"]');
  await p.waitForTimeout(2000);

  const pages = ['limited-stock', 'restock-requests'];
  for (const page of pages) {
    await p.goto(`http://127.0.0.1:8765/vendor/${page}`, { waitUntil: 'networkidle' });
    await p.screenshot({ path: `public/screenshots/vendor-${page}.png`, fullPage: true });
    console.log('✓ vendor/' + page);
  }
  await b.close();
  console.log('Done');
})();
