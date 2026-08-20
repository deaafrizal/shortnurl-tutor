const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({headless: true});
  const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, bypassCSP: true });
  const page = await context.newPage();
  const requests = [];
  const errors = [];
  const consoles = [];
  const responses = [];
  await page.addInitScript(() => {
    const OriginalObserver = window.IntersectionObserver;
    window.IntersectionObserver = class extends OriginalObserver {
      constructor(callback, options) { console.log(`IO constructed ${JSON.stringify(options)}`); super(callback, options); }
      observe(target) { console.log(`IO observing ${target.id}`); return super.observe(target); }
    };
  });
  page.on('request', request => { if (request.url().includes('activity_page=')) requests.push(request.url()); });
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => consoles.push(`${message.type()}: ${message.text()}`));
  page.on('response', response => { if (response.url().includes('/assets/app.js')) responses.push(`${response.status()} ${response.url()}`); });
  await page.goto('https://shortnurl.deaafrizal.tech/', { waitUntil: 'networkidle' });
  const initial = await page.locator('#activity-list .activity-row').count();
  console.log('attrs', await page.locator('#activity-loader').evaluate(node => ({hasMore: node.dataset.hasMore, next: node.dataset.nextPage})), 'ids', await page.locator('#activity-list').count(), await page.locator('#activity-sentinel').count());
  console.log('scripts', await page.locator('script[src]').count(), await page.locator('script[src]').evaluateAll(nodes => nodes.map(node => ({src: node.src, defer: node.defer, type: node.type, readyState: node.readyState}))));
  await page.addScriptTag({ url: 'https://shortnurl.deaafrizal.tech/assets/app.js' });
  if (initial !== 10) throw new Error(`expected 10 initial rows, got ${initial}`);
  if (requests.length !== 0) throw new Error(`loaded data before scroll: ${requests}`);
  await page.mouse.wheel(0, 2000);
  await page.locator('#activity-load-more').click();
  for (let i = 0; i < 20 && await page.locator('#activity-list .activity-row').count() <= 10; i++) await new Promise(resolve => setTimeout(resolve, 500));
  const afterPage2 = await page.locator('#activity-list .activity-row').count();
  if (afterPage2 <= 10) throw new Error(`page 2 did not append rows: requests=${requests}, errors=${errors}, console=${consoles}, app=${responses}, loader=${await page.locator('#activity-loader').getAttribute('data-has-more')}, button=${await page.locator('#activity-load-more').count()}, sentinel=${JSON.stringify(await page.locator('#activity-sentinel').boundingBox())}`);
  if (!requests.some(url => url.includes('activity_page=2'))) throw new Error(`page 2 was not requested: ${requests}`);
  await page.mouse.wheel(0, 3000);
  for (let i = 0; i < 20 && await page.locator('#activity-list .activity-row').count() <= 20; i++) await new Promise(resolve => setTimeout(resolve, 500));
  const afterPage3 = await page.locator('#activity-list .activity-row').count();
  if (afterPage3 <= 20) throw new Error(`page 3 did not append rows: ${requests}`);
  if (!requests.some(url => url.includes('activity_page=3'))) throw new Error(`page 3 was not requested: ${requests}`);
  console.log(JSON.stringify({initial, afterPage2, afterPage3, requests, errors}));
  await browser.close();
})().catch(error => { console.error(error); process.exit(1); });
