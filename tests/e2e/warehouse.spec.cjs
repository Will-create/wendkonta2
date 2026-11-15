const {test, expect} = require('@playwright/test');
const fs = require('node:fs');
const widths = [320,360,375,390,412,414,768,1024,1366,1920];

async function login(page, user='e2e-owner') {
    await page.goto('/login');
    await page.locator('input[name="name"]').fill(user);
    await page.locator('input[name="password"]').fill('e2e-fixture-password');
    await page.locator('button[type="submit"],input[type="submit"]').first().click();
    await expect(page).toHaveURL(/dashboard|\/home/);
    await expect(page.locator('#salepro-mobile-config')).toBeAttached();
}
async function select(page, name, value) {
    const field = page.locator('select[name="'+name+'"]').first();
    await field.selectOption(String(value), {force:true});
    await field.evaluate(node => { if (window.jQuery) jQuery(node).trigger('change'); });
}
async function state(page) {
    const response = await page.request.get('/__e2e/state');
    expect(response.ok()).toBeTruthy();
    return response.json();
}
async function settle(page) {
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('#salepro-mobile-config')).toBeAttached();
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
}
async function noOverflow(page) {
    const overflow = await page.evaluate(() => ({
        width:document.documentElement.clientWidth, scroll:document.documentElement.scrollWidth,
        elements:Array.from(document.querySelectorAll('body *')).filter(node => {
            if (node.closest('nav.side-navbar,.modal:not(.show)')) return false;
            const r=node.getBoundingClientRect();return r.width && r.right>document.documentElement.clientWidth+1 && getComputedStyle(node).visibility!=='hidden';
        }).slice(0,12).map(node=>({element:node.tagName+'.'+node.className,right:node.getBoundingClientRect().right,width:node.getBoundingClientRect().width}))
    }));
    expect(overflow.scroll, JSON.stringify(overflow)).toBeLessThanOrEqual(overflow.width+1);
}
async function csrf(page) { return page.locator('meta[name="csrf-token"]').getAttribute('content'); }
async function post(page, url, values) {
    const body = new URLSearchParams();
    Object.entries(values).forEach(([key,value]) => Array.isArray(value) ? value.forEach(item=>body.append(key+'[]',String(item))) : body.append(key,String(value)));
    return page.request.post(url,{data:body.toString(),headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':await csrf(page),Accept:'application/json'}});
}
function stockQty(saved, product, warehouse) {
    const row = saved.warehouse_stock.find(item => Number(item.product_id) === product && Number(item.warehouse_id) === warehouse);
    return row ? Number(row.qty) : undefined;
}
async function chooseCatalogItem(page, input, term) {
    await input.fill(term);
    if (await page.locator('#purchase-product-picker').count()) {
        const option = page.locator('#purchase-product-picker input[type=checkbox]').first();
        await expect(option).toBeVisible(); await option.check();
        await page.locator('#purchase-product-picker .sp-picker-actions .btn-primary').click();
        await expect(page.locator('table.order-list tbody tr')).toHaveCount(1);
        return;
    }
    const menu = page.locator('.ui-autocomplete:visible .ui-menu-item').first();
    const row = page.locator('table.order-list tbody tr');
    await expect.poll(async () => (await menu.count()) + (await row.count())).toBeGreaterThan(0);
    if (await menu.isVisible()) await menu.click();
    await expect(row).toHaveCount(1);
}
async function addSaleItem(page) {
    await select(page,'customer_id',1);await select(page,'warehouse_id',1);
    if (await page.locator('select[name="biller_id"]').count()) await select(page,'biller_id',1);
    await page.locator('#product-search-input').fill('E2E Oil');
    await expect(page.locator('#product-results-container .product-img').first()).toBeVisible();
    await page.locator('#product-results-container .product-img').first().click();
    await expect(page.locator('table.order-list tbody tr')).toHaveCount(1);
}

test.beforeEach(async ({page,request}) => {
    const status=await request.get('/__e2e/status');
    expect((await status.json()).isolated).toBe(true);
    const reset = await request.post('/__e2e/reset');
    expect(reset.ok(), await reset.text()).toBeTruthy();
    page.on('dialog',dialog=>dialog.accept());
    await login(page);
});

test('all enabled read screens fit the complete viewport matrix', async ({page}, info) => {
    test.setTimeout(900000);
    const paths=await (await page.request.get('/__e2e/screens')).json();
    const results=[];
    const errors=[];
    page.on('pageerror',error=>errors.push(error.message));
    for (const path of paths) {
        const response=await page.goto(path);
        expect(response.status(),path).toBeLessThan(400);
        await settle(page);
        // An addon/permission redirect is reported as excluded rather than a tested screen.
        if (new URL(page.url()).pathname !== path) {results.push({path,status:'redirected',destination:page.url()});continue;}
        for (const width of widths) {
            await page.setViewportSize({width,height:900});
            await settle(page);await noOverflow(page);
        }
        results.push({path,status:'verified',widths});
    }
    fs.mkdirSync('/results/screens',{recursive:true});
    fs.writeFileSync('/results/screens/'+info.project.name+'.json',JSON.stringify(results,null,2));
    expect(errors).toEqual([]);
});

test('phone drawer exposes the full menu and account controls', async ({page}) => {
    await page.setViewportSize({width:360,height:800});
    await page.locator('.sp-menu-toggle').click();
    const menu=page.locator('nav.side-navbar');
    await expect(menu).toBeVisible();
    await expect(menu).toHaveCSS('opacity','1');
    await expect(page.locator('.sp-mobile-menu-tools')).toContainText(/profile/i);
    await expect(page.locator('.sp-mobile-menu-tools form[action$="/logout"] button')).toBeVisible();
    await page.locator('.sp-menu-close').click();
    await expect(menu).not.toBeVisible();
});

test('warehouse sale uses touch search and persists ordered/delivered stock correctly', async ({page}) => {
    await page.goto('/sales/create');await addSaleItem(page);
    const row=page.locator('table.order-list tbody tr');
    await row.locator('.plus').click();
    await expect(row.locator('input[name="qty[]"]')).toHaveValue('2');
    await page.locator('form.payment-form input[name="reference_no"]').fill('E2E-SALE-TOUCH');
    await select(page,'sale_status',1);await select(page,'payment_status',1);
    const response=page.waitForResponse(r=>new URL(r.url()).pathname==='/sales' && r.request().method()==='POST');
    await page.locator('#submit-button').click();
    expect((await response).status()).toBeLessThan(400);
    await expect.poll(async()=> (await state(page)).sales.length).toBe(1);
    const saved=await state(page);
    expect(Number(saved.sales[0].grand_total)).toBe(25);
    expect(stockQty(saved, 1, 1)).toBe(98);
    await page.goto('/sales');await noOverflow(page);
    await expect(page.locator('body')).toContainText('E2E-SALE-TOUCH');
});

test('matching retries create one sale and conflicting retries change nothing', async ({page}) => {
    await page.goto('/sales/create');
    const token='11111111-2222-4333-8444-555555555555';
    const payload={operation_token:token,reference_no:'E2E-SALE-RETRY',customer_id:1,warehouse_id:1,biller_id:1,item:1,
        total_qty:2,total_discount:0,total_tax:0,total_price:25,order_tax_rate:0,order_tax:0,order_discount:0,
        shipping_cost:0,grand_total:25,sale_status:1,payment_status:1,paid_amount:0,
        product_id:[1],product_code:['E2E-OIL'],product_batch_id:[''],imei_number:[''],qty:[2],dqty:[2],dstatus:['2 / 2'],
        sale_unit:['Piece'],net_unit_price:[12.5],discount:[0],tax_rate:[0],tax:[0],subtotal:[25]};
    const responses=await Promise.all([post(page,'/sales',payload),post(page,'/sales',payload)]);
    responses.forEach(response=>expect(response.ok()).toBeTruthy());
    expect(await responses[0].text()).toBe(await responses[1].text());
    const conflict=await post(page,'/sales',{...payload,grand_total:30,subtotal:[30]});
    expect(conflict.status()).toBe(409);
    const saved=await state(page);expect(saved.sales).toHaveLength(1);
    expect(stockQty(saved, 1, 1)).toBe(98);
});

test('purchase receiving through the rendered form increases stock', async ({page}) => {
    await page.goto('/purchases/create');
    await select(page,'warehouse_id',1);await select(page,'supplier_id',1);await select(page,'currency_id',1);
    await page.locator('#purchase-form input[name="reference_no"]').fill('E2E-PURCHASE-RECEIVED');
    await chooseCatalogItem(page, page.locator('#purchase-form input[name="product_code_name"]'), 'E2E-OIL');
    await select(page,'status',1);await select(page,'payment_status',1);
    await page.locator('#purchase-form [type="submit"]').first().click();
    await expect(page).toHaveURL(/\/purchases$/);
    const saved=await state(page);expect(saved.purchases).toHaveLength(1);
    expect(stockQty(saved, 1, 1)).toBe(101);
});

test('product creation and editing use existing forms and persist catalog fields', async ({page}) => {
    await page.goto('/products/create');
    await page.locator('#product-form input[name="name"]').fill('E2E Created Product');
    await page.locator('#product-form input[name="code"]').fill('E2E-CREATED');
    await select(page,'type','standard');await select(page,'category_id',1);await select(page,'unit_id',1);
    if (await page.locator('select[name="sale_unit_id"]').count()) await select(page,'sale_unit_id',1);
    if (await page.locator('select[name="purchase_unit_id"]').count()) await select(page,'purchase_unit_id',1);
    await page.locator('#product-form input[name="cost"]').fill('5');
    await page.locator('#product-form input[name="price"]').fill('10');
    await page.locator('#product-form input[name="price"]').dispatchEvent('change');
    await page.locator('#product-form [type="submit"]').first().click();
    await expect(page).toHaveURL(/\/products$/);
    await expect.poll(async()=> (await state(page)).products.some(product=>product.code==='E2E-CREATED')).toBe(true);
    const product=(await state(page)).products.find(product=>product.code==='E2E-CREATED');
    await page.goto('/products/'+product.id+'/edit');
    await page.locator('input[name="name"]').first().fill('E2E Edited Product');
    await page.locator('#product-form [type="submit"]').first().click();
    await expect(page.locator('body')).not.toContainText('SQLSTATE');
    await expect.poll(async()=> (await state(page)).products.find(row=>row.id===product.id)?.name).toBe('E2E Edited Product');
    const current=(await state(page)).products.find(row=>row.id===product.id);
    expect(Number(current.price)).toBe(10);
});

test('payments persist once and the dashboard reconciles the remaining balance', async ({page}) => {
    // A real sale is created via the application endpoint; payment is then exercised through its modal.
    await page.goto('/sales/create');await addSaleItem(page);
    await page.locator('form.payment-form input[name="reference_no"]').fill('E2E-PAYMENT-SALE');
    await select(page,'sale_status',1);await select(page,'payment_status',1);
    await page.locator('#submit-button').click();
    await expect.poll(async()=> (await state(page)).sales.length).toBe(1);
    await page.goto('/sales');
    const row=page.locator('table tbody tr').filter({hasText:'E2E-PAYMENT-SALE'}).first();
    await row.locator('[data-toggle="dropdown"]').first().click();
    await row.locator('button.add-payment[data-target="#add-payment"]').click();
    const modal=page.locator('.modal.show');
    await modal.locator('input[name="amount"]').fill('5');
    await modal.locator('input[name="paying_amount"]').fill('5');
    await modal.locator('[type="submit"]').first().click();
    await expect.poll(async()=>Number((await state(page)).sales[0].paid_amount)).toBe(5);
    await page.goto('/dashboard');
    await expect(page.locator('[data-sp-summary]')).toHaveAttribute('aria-busy','false');
    const summary=await (await page.request.get('/dashboard/mobile-summary')).json();
    expect(Number(summary.metrics.customer_due.value)).toBe(7.5);
    await expect(page.locator('[data-sp-summary]')).toContainText('7.50');
});

test('stock transfer uses source and destination warehouses without changing total stock', async ({page}) => {
    await page.goto('/transfers/create');
    const catalog=page.waitForResponse(response=>response.url().includes('/getproduct/') && response.ok());
    await select(page,'from_warehouse_id',1);
    await catalog;
    await select(page,'to_warehouse_id',2);
    await chooseCatalogItem(page, page.locator('#lims_productcodeSearch'), 'E2E-OIL');
    await select(page,'status',1);
    await page.locator('form [type="submit"]').first().click();
    await expect.poll(async()=>stockQty(await state(page), 1, 2)).toBe(11);
    const saved=await state(page);
    expect(stockQty(saved, 1, 1)).toBe(99);
    expect(Number(saved.products.find(row=>row.id===1).qty)).toBe(110);
});

test('PWA registration, static-only caches, offline fallback and online-only forms', async ({page,context}) => {
    await expect.poll(()=>page.evaluate(async()=>!!(await navigator.serviceWorker.getRegistration('/')))).toBe(true);
    await page.reload();await page.evaluate(()=>navigator.serviceWorker.ready);
    await expect.poll(()=>page.evaluate(()=>!!navigator.serviceWorker.controller)).toBe(true);
    const manifest=await (await page.request.get('/manifest.json')).json();
    expect(manifest.name).toBe('SalePro POS');expect(manifest.start_url).toBe('/dashboard');
    expect((await page.request.get('/icons/icon-192x192.png')).ok()).toBeTruthy();
    await page.goto('/sales/create');await addSaleItem(page);
    await context.setOffline(true);
    await page.locator('#submit-button').click();
    await expect(page.locator('.sp-submit-feedback')).toBeVisible();
    await page.goto('/products');
    await expect(page.locator('h2')).toContainText('offline');
    await context.setOffline(false);await page.goto('/dashboard');
    expect((await state(page)).sales).toHaveLength(0);
    const cached=await page.evaluate(async()=>{
        const urls=[];for(const key of await caches.keys())for(const request of await (await caches.open(key)).keys())urls.push(new URL(request.url).pathname);return urls;
    });
    expect(cached).not.toContain('/dashboard');expect(cached).not.toContain('/sales/create');
    expect(cached.every(url=>['/offline.html','/icons/icon-192x192.png','/icons/icon-512x512.png','/css/salepro-mobile.css','/js/salepro-mobile.js','/js/salepro-pwa.js'].includes(url))).toBe(true);
});

test('French RTL and dark mode preserve navigation and readable page width', async ({page,context}) => {
    const origin=new URL(process.env.BASE_URL).origin;
    await context.addCookies([{name:'language',value:'fr',url:origin},{name:'theme',value:'dark',url:origin}]);
    await page.goto('/dashboard');await page.setViewportSize({width:360,height:800});await noOverflow(page);
    await expect(page.locator('.sp-bottom-nav')).toContainText('Accueil');
    await context.addCookies([{name:'language',value:'ar',url:origin}]);
    await page.goto('/products');await expect(page.locator('html')).toHaveAttribute('dir','rtl');await noOverflow(page);
});

test('restricted stock clerk cannot submit payments or inspect another warehouse', async ({page,context}) => {
    await page.request.post('/logout',{headers:{'X-CSRF-TOKEN':await csrf(page)}});
    await context.clearCookies();await login(page,'e2e-clerk');
    const response=await post(page,'/sales/add_payment',{operation_token:'22222222-2222-4222-8222-222222222222',amount:5,sale_id:1});
    expect(response.status()).toBe(403);
    expect((await page.request.get('/dashboard/mobile-summary?warehouse_id=2')).status()).toBe(403);
});

test('purchase defaults and inline product creation preserve the active purchase', async ({page}) => {
    await page.goto('/purchases/create');
    await expect(page.locator('select[name="supplier_id"]')).toHaveValue('2');
    await expect(page.locator('select[name="warehouse_id"]')).toHaveValue('1');
    await page.locator('input[name="reference_no"]').fill('E2E-INLINE-PRODUCT');
    await page.locator('[data-target="#quick-product-modal"]').click();
    await page.locator('#quick-name').fill('E2E Inline Product');
    await page.locator('[data-generate-code]').click();
    await expect(page.locator('#quick-code')).toHaveValue(/^\d{8}$/);
    await page.locator('#quick-code').fill('E2E-INLINE');
    await page.locator('#quick-cost').fill('3.25'); await page.locator('#quick-price').fill('5.50');
    await page.locator('#quick-category').selectOption('1'); await page.locator('#quick-unit').selectOption('1');
    await page.locator('#quick-product-form button[type="submit"]').click();
    await expect(page.locator('#quick-product-modal')).not.toBeVisible();
    await expect(page.locator('input[name="reference_no"]')).toHaveValue('E2E-INLINE-PRODUCT');
    await expect(page.locator('#purchase-product-picker input[type="checkbox"]')).toBeChecked();
    await page.locator('#purchase-product-picker .btn-primary').click();
    await expect(page.locator('#myTable .product-code')).toHaveValue('E2E-INLINE');
    await noOverflow(page);
});

test('customer deposit receipt allocates debt and preserves excess credit', async ({page}) => {
    await page.goto('/sales/create'); await addSaleItem(page);
    await select(page,'sale_status',1); await select(page,'payment_status',1);
    await page.locator('#submit-button').click();
    await expect.poll(async()=> (await state(page)).sales.length).toBe(1);
    const before=(await state(page)).sales[0];
    await page.goto('/customer/1/account');
    await page.locator('#deposit-amount').fill('50.00');
    await page.locator('#deposit-account').selectOption('1');
    await page.locator('#customer-deposit button[type="submit"]').click();
    await expect(page).toHaveURL(/\/customer\/1\/account\/deposits\/\d+$/);
    const saved=await state(page);
    expect(saved.customer_deposits).toHaveLength(1);
    expect(saved.deposit_allocations).toHaveLength(1);
    expect(Number(saved.sales[0].paid_amount)).toBe(Number(before.grand_total));
    for (const width of [320,390,768,1366]) {await page.setViewportSize({width,height:850});await settle(page);await noOverflow(page);}
    await page.locator('#reversal-note').fill('E2E reversal');
    await page.locator('form[action$="/reverse"] button[type="submit"]').click();
    await expect.poll(async()=>Number((await state(page)).sales[0].paid_amount)).toBe(0);
});

test('inventory category search pagination and warehouse context agree', async ({page}) => {
    await page.goto('/products');
    const rows=page.locator('#product-data-table tbody tr.product-link');
    await expect(rows).toHaveCount(2);
    await page.locator('#category_id').selectOption('2');
    await expect(rows).toHaveCount(1); await expect(rows).toContainText('E2E Flour');
    await page.locator('#product-data-table_filter input').fill('Flour');
    await expect(rows).toHaveCount(1);
    await page.locator('#category_id').selectOption('1'); await expect(rows).toHaveCount(0);
    await page.locator('#category_id').selectOption('0'); await expect(rows).toHaveCount(1);
    await page.locator('#warehouse_id').selectOption('2');
    await expect(page.locator('#stock-scope')).toContainText('Other');
    await expect(rows.locator('[data-stock-status=out_of_stock]')).toBeVisible();
    for(const width of [320,360,375,390,414]) {await page.setViewportSize({width,height:850});await settle(page);await noOverflow(page);await expect(rows.locator('.sp-stock-quantity')).toBeVisible();}
});

test('every dashboard chart fills a separate mobile row at all required widths', async ({page}) => {
    await page.goto('/dashboard');
    for(const width of [320,360,375,390,414]) {
        await page.setViewportSize({width,height:850});await settle(page);
        await page.waitForFunction(()=>document.querySelectorAll('.sp-dashboard-chart').length===3 && window.Chart && Object.keys(Chart.instances).length>=3);
        const frames=await page.locator('.sp-dashboard-chart').evaluateAll(nodes=>nodes.map(node=>{
            const frame=node.getBoundingClientRect(),canvas=node.querySelector('canvas').getBoundingClientRect();
            return {top:frame.top,bottom:frame.bottom,width:frame.width,right:frame.right,canvasWidth:canvas.width,height:frame.height};
        }));
        for(let i=0;i<frames.length;i++) {
            expect(frames[i].width).toBeGreaterThan(width-90);expect(frames[i].right).toBeLessThanOrEqual(width+1);
            expect(Math.abs(frames[i].width-frames[i].canvasWidth)).toBeLessThan(2);
            expect(frames[i].height).toBeGreaterThanOrEqual(280);
            if(i) expect(frames[i].top).toBeGreaterThanOrEqual(frames[i-1].bottom);
        }
        await noOverflow(page);
    }
});

test('quick product modal footer stays inside a keyboard-sized visual viewport', async ({page}) => {
    await page.setViewportSize({width:320,height:750});await page.goto('/purchases/create');
    await page.locator('[data-target="#quick-product-modal"]').click();
    await page.locator('#quick-code').focus();await page.setViewportSize({width:320,height:400});await settle(page);
    const bounds=await page.locator('#quick-product-modal .modal-footer').evaluate(node=>{const r=node.getBoundingClientRect();return {top:r.top,bottom:r.bottom};});
    expect(bounds.top).toBeGreaterThanOrEqual(0);expect(bounds.bottom).toBeLessThanOrEqual(400);
    await expect(page.locator('#quick-product-modal .close')).toBeVisible();await noOverflow(page);
});

test('account cards show historical invoice payments and a direct deposit action', async ({page}) => {
    await page.goto('/sales/create');await addSaleItem(page);await select(page,'sale_status',1);await select(page,'payment_status',1);
    await page.locator('#submit-button').click();await expect.poll(async()=>(await state(page)).sales.length).toBe(1);
    const sale=(await state(page)).sales[0];
    const response=await post(page,'/sales/add_payment',{sale_id:sale.id,amount:'5',paying_amount:'5',paid_by_id:1,account_id:1,payment_note:'Historical payment',payment_receiver:'Cashier'});
    expect(response.status()).toBeLessThan(400);
    await page.goto('/customer-accounts');
    const card=page.locator('.sp-customer-account').first();
    await expect(card).toContainText('5.00');await expect(card).toContainText('7.50');
    await page.setViewportSize({width:320,height:850});await settle(page);
    await expect(card.locator('.sp-direct-deposit')).toBeVisible();await noOverflow(page);
    await card.locator('.sp-direct-deposit').click();await expect(page).toHaveURL(/#customer-deposit$/);
});
