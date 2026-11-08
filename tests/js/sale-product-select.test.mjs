/**
 * Regression test for Sales → Add New Sale product selection.
 *
 * Runs the search, click, and cart handlers copied from
 * resources/views/backend/sale/create.blade.php. A press longer than the
 * 150ms blur clear must add the product. Selecting the same product again
 * must raise the quantity, because lims_product_search index 23 is the
 * delivered quantity and not restaurant extras.
 *
 * Run: node tests/js/sale-product-select.test.mjs
 */
import fs from 'fs';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const bladePath = new URL('../../resources/views/backend/sale/create.blade.php', import.meta.url);
const blade = fs.readFileSync(bladePath, 'utf8');
const controllerPath = new URL('../../app/Http/Controllers/SaleController.php', import.meta.url);
const controller = fs.readFileSync(controllerPath, 'utf8');

const failures = [];
function check(name, condition, detail) {
    if (condition) {
        console.log('PASS', name);
    } else {
        failures.push(name + (detail ? ': ' + detail : ''));
        console.error('FAIL', name, detail || '');
    }
}

function sliceBetween(source, startMarker, endMarker, label) {
    const start = source.indexOf(startMarker);
    const end = source.indexOf(endMarker, start + startMarker.length);
    if (start < 0 || end < 0) {
        throw new Error('Could not extract ' + label + ' from create.blade.php');
    }
    return source.slice(start, end);
}

function stripAtLines(source) {
    return source
        .split('\n')
        .filter((line) => !line.trim().startsWith('@'))
        .join('\n');
}

check(
    'mousedown keeps the result from blurring the search field',
    blade.includes("$results.on('mousedown', '.product-img, .add-selected-products'") && blade.includes('event.preventDefault()') && blade.includes('pointerOnResult')
);
check(
    'search reads the live warehouse id',
    blade.includes("url: '{{url(\"/\")}}/sales/search/' + $('#warehouse_id').val() + '/' + search")
);
check(
    'delivered qty is not treated as restaurant extras',
    blade.includes('function saleProductExtras(data)') && !/if\s*\(\s*data\[23\]\s*\)\s*\{\s*data\[15\]\s*=\s*1/.test(blade)
);
check(
    'quantity buttons affect only the current row',
    blade.includes("$(this).closest('tr').find('.dplus').trigger('click')") &&
        blade.includes("$(this).closest('tr').find('.dminus').trigger('click')") &&
        !blade.includes('$("#myTable .dplus").click()') &&
        !blade.includes('$("#myTable .dminus").click()')
);
check(
    'a single search result is never auto-selected',
    !blade.includes("$('#product-results-container .product-img').first().trigger('click')")
);
check(
    'backend keeps delivered qty at index 23 and appends extras after it',
    controller.includes('$dqty, //23') &&
        controller.includes("$dqty. '/'.$dqty // 24") &&
        controller.indexOf('$dqty, //23') < controller.indexOf('$productArray[] = $extras')
);

const searchScript = sliceBetween(blade, '$(document).ready(function() {', '</script>', 'search script')
    .replaceAll('{{url("/")}}', '')
    .replaceAll("{{ __('db.In Stock') }}", 'In Stock')
    .replaceAll("{{__('db.expired')}}", 'expired');
if (searchScript.includes('{{')) {
    throw new Error('Unreplaced blade tag in search script');
}

const clickHandler = stripAtLines(sliceBetween(
    blade,
    'function addSaleProductResult',
    'function saleProductExtras',
    'click handler'
));
const productSearchSource = sliceBetween(blade, 'function saleProductExtras', 'function addNewProduct', 'productSearch')
    .replaceAll('{{url("sales/lims_product_search")}}', '/sales/lims_product_search')
    .replaceAll('{{ __("db.This product has been added using the weight scale machine.")}}', 'This product has been added using the weight scale machine.');
const updateDstatusSource = sliceBetween(blade, 'function updateDstatus', '//Delete product', 'updateDstatus');
const deleteHandler = sliceBetween(blade, '$("table.order-list tbody").on("click", ".ibtnDel"', '//Edit product', 'delete handler');
const qtyHandlers = sliceBetween(blade, '$("#myTable").on(\'click\', \'.plus\'', '$("select[name=price_option]")', 'qty handlers');
const checkQuantitySource = sliceBetween(blade, 'function checkQuantity(sale_qty, flag)', 'function checkQuantity2', 'checkQuantity');

const requests = [];

function limsPayload(params) {
    const pre = Number(params.get('data[pre_qty]') || 1);
    const code = params.get('data[code]');
    const catalog = {
        '21014058': ['HUILE 20L SALWA', '21014058', 22188, 151, '249'],
        '15233690': ['RIZ PARABOLE 50KG', '15233690', 25313, 90, '1'],
        '00000000': ['EMPTY STOCK', '00000000', 1000, 1, '0'],
    };
    const item = catalog[code];
    if (!item) {
        return { status: 404, body: { error: 'Product not found' } };
    }
    return {
        status: 200,
        body: [
            item[0], item[1], item[2], 0, 'No Tax', 1,
            'Piece,', '*,', '1,',
            item[3], null, null, null, null, null,
            pre, null, 100, '',
            item[4], 'standard', '', '',
            pre, pre + '/' + pre,
        ],
    };
}

function searchPayload(warehouseId, term) {
    const all = [
        { code: '21014058', name: 'HUILE 20L SALWA', qty: 249, price: 22188, is_imei: null, is_embeded: null, product_batch_id: null, imei_number: null, warehouse_id: '1' },
        { code: '14145202', name: 'HUILE AUTRE', qty: 4, price: 1500, is_imei: null, is_embeded: null, product_batch_id: null, imei_number: null, warehouse_id: '1' },
        { code: '15233690', name: 'RIZ PARABOLE 50KG', qty: 1, price: 25313, is_imei: null, is_embeded: null, product_batch_id: null, imei_number: null, warehouse_id: '1' },
        { code: '20000001', name: 'RIZ LOCAL', qty: 6, price: 900, is_imei: null, is_embeded: null, product_batch_id: null, imei_number: null, warehouse_id: '1' },
        { code: '00000000', name: 'EMPTY STOCK', qty: 0, price: 1000, is_imei: null, is_embeded: null, product_batch_id: null, imei_number: null, warehouse_id: '1' },
        { code: '00000001', name: 'EMPTY BOTTLE', qty: 5, price: 200, is_imei: null, is_embeded: null, product_batch_id: null, imei_number: null, warehouse_id: '1' },
        { code: '21014058', name: 'HUILE 20L SALWA', qty: 3, price: 22188, is_imei: null, is_embeded: null, product_batch_id: null, imei_number: null, warehouse_id: '4' },
        { code: '14145202', name: 'HUILE AUTRE', qty: 8, price: 1500, is_imei: null, is_embeded: null, product_batch_id: null, imei_number: null, warehouse_id: '4' },
    ];
    const q = term.toLowerCase();
    return all.filter((product) => product.warehouse_id === String(warehouseId) && product.qty > 0 && (product.name.toLowerCase().includes(q) || product.code.includes(q)));
}

const jquery = fs.readFileSync(new URL('../../public/vendor/jquery/jquery.min.js', import.meta.url));

const pageHtml = `<!DOCTYPE html>
<html><head><meta charset="utf-8"><base href="http://pos.test/">
<style>
#product-results-container { position: absolute; z-index: 999; background: #f5f6f7; width: 420px; }
#product-results-container .product-img { display: block; padding: 8px; cursor: pointer; }
table { border-collapse: collapse; }
td { padding: 4px; }
</style>
</head><body>
<form class="payment-form" action="/sales" method="POST">
  <select id="customer_id" name="customer_id"><option value="21">Defaut</option><option value="">None</option></select>
  <select id="warehouse_id" name="warehouse_id"><option value="1">Magasin Arbolé</option><option value="4">Magasin Hatté</option></select>
  <select id="biller_id" name="biller_id"><option value="1">Sawadogo Daouda</option></select>
  <input id="product-search-input" name="product_code_name" autofocus>
  <div id="product-results-container"></div>
  <div id="no-results-message" style="display:none">No results found</div>
  <table id="myTable" class="order-list"><tbody></tbody></table>
  <input type="hidden" name="total_qty" value="0">
</form>
<div id="outside">outside</div>
<script>${jquery}</script>
<script>
var authUser = 1;
var currency = { exchange_rate: 1 };
var customer_group_rate = 0;
var rowindex;
var product_price = [];
var wholesale_price = [];
var product_discount = [];
var tax_rate = [];
var tax_name = [];
var tax_method = [];
var unit_name = [];
var unit_operator = [];
var unit_operation_value = [];
var is_imei = [];
var is_variant = [];
var without_stock = 'no';
var is_checking = false;
function calculateTotal() {}
function calculateRowProductData() {}
function checkDiscount() {}
function addNewProduct(data) {
    var qty = data[15];
    var delivered = Array.isArray(data[23]) ? qty : data[23];
    var html = '<tr>'
        + '<td class="product-title">' + data[0] + '</td>'
        + '<td><button type="button" class="minus">-</button>'
        + '<input class="qty" name="qty[]" value="' + qty + '" max="' + String(data[19]) + '">'
        + '<button type="button" class="plus">+</button></td>'
        + '<td><button type="button" class="dminus">-</button>'
        + '<input class="dqty" name="dqty[]" value="' + delivered + '" max="' + qty + '">'
        + '<button type="button" class="dplus">+</button></td>'
        + '<td class="dstatus"></td>'
        + '<input type="hidden" class="dstatus-value" name="dstatus[]">'
        + '<input type="hidden" class="product-code" name="product_code[]" value="' + data[1] + '">'
        + '<input type="hidden" class="product-id" name="product_id[]" value="' + data[9] + '">'
        + '<input type="hidden" class="product_type" value="' + data[20] + '">'
        + '<input type="hidden" class="imei-number" name="imei_number[]" value="">'
        + '<td><button type="button" class="ibtnDel">x</button></td>'
        + '</tr>';
    $('table.order-list tbody').prepend(html);
    rowindex = 0;
    unit_operator.splice(0, 0, '*,');
    unit_operation_value.splice(0, 0, '1,');
    product_price.splice(0, 0, Number(data[2]) || 0);
    wholesale_price.splice(0, 0, 0);
    product_discount.splice(0, 0, 0);
    tax_rate.splice(0, 0, 0);
    tax_name.splice(0, 0, 'No Tax');
    tax_method.splice(0, 0, 1);
    unit_name.splice(0, 0, 'Piece,');
    is_imei.splice(0, 0, 0);
    checkQuantity(String(data[15]), true);
    updateDstatus(rowindex);
}
${updateDstatusSource}
${checkQuantitySource}
${deleteHandler}
${qtyHandlers}
${productSearchSource}
${clickHandler}
${searchScript}
</script>
</body></html>`;

function rows(page) {
    return page.locator('table.order-list tbody tr');
}

async function search(page, term) {
    await page.fill('#product-search-input', '');
    await page.fill('#product-search-input', term);
    await page.waitForTimeout(450);
}

async function slowClick(page, locator) {
    const box = await locator.boundingBox();
    if (!box) {
        throw new Error('Result has no box');
    }
    await page.mouse.move(box.x + 8, box.y + 8);
    await page.mouse.down();
    await page.waitForTimeout(400);
    await page.mouse.up();
    await page.waitForTimeout(50);
}

const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
const dialogs = [];
const pageErrors = [];
page.on('dialog', async (dialog) => {
    dialogs.push(dialog.message());
    await dialog.accept();
});
page.on('pageerror', (error) => pageErrors.push(String(error)));

await page.route('**/*', async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    requests.push(url.pathname + url.search);
    const searchMatch = url.pathname.match(/^\/sales\/search\/([^/]+)\/([^/]+)$/);
    if (searchMatch) {
        const body = searchPayload(decodeURIComponent(searchMatch[1]), decodeURIComponent(searchMatch[2]));
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) });
    } else if (url.pathname === '/sales/lims_product_search') {
        const payload = limsPayload(url.searchParams);
        await route.fulfill({ status: payload.status, contentType: 'application/json', body: JSON.stringify(payload.body) });
    } else if (url.pathname === '/sales' && request.method() === 'POST') {
        await route.fulfill({ status: 200, contentType: 'text/plain', body: request.postData() || '' });
    } else {
        await route.fulfill({ status: 404, body: 'missing' });
    }
});

try {
    await page.setContent(pageHtml, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => typeof productSearch === 'function' && typeof saleProductExtras === 'function');

    const extrasDecision = await page.evaluate(() => {
        const normal = ['n', 'c', 1, 0, 'No Tax', 1, 'Piece,', '*,', '1,', 1, null, null, null, null, null, 2, null, 1, '', '5', 'standard', '', '', 2, '2/2'];
        const withExtras = normal.slice();
        withExtras[25] = [{ id: 9, name: 'Topping' }];
        const legacy = normal.slice();
        legacy[23] = [{ id: 3 }];
        function forcesNewRow(data) {
            let preQty = 1;
            let saleQty = data[15];
            const extras = saleProductExtras(data);
            if (extras && extras.length) {
                saleQty = 1;
                preQty = 0;
            }
            return { preQty, saleQty, extras: !!extras };
        }
        return {
            normal: forcesNewRow(normal),
            restaurant: forcesNewRow(withExtras),
            legacy: forcesNewRow(legacy),
        };
    });
    check('index 23 string/number does not force a new row', extrasDecision.normal.preQty === 1 && extrasDecision.normal.extras === false, JSON.stringify(extrasDecision.normal));
    check('restaurant extras at index 25 force a separate row', extrasDecision.restaurant.preQty === 0, JSON.stringify(extrasDecision.restaurant));
    check('legacy extras array at index 23 still forces a separate row', extrasDecision.legacy.preQty === 0, JSON.stringify(extrasDecision.legacy));

    await search(page, 'huile');
    await page.waitForSelector('#product-results-container .product-img');
    const resultCount = await page.locator('#product-results-container .product-img').count();
    check('search returns more than one match so the click is manual', resultCount > 1, String(resultCount));

    await page.locator('.add-selected-products').click();
    check('bulk add requires a selected product', await rows(page).count() === 0 && dialogs.some((message) => message.includes('at least one')));
    const resultCheckboxes = page.locator('.product-result-select');
    for (let i = 0; i < await resultCheckboxes.count(); i++) {
        await resultCheckboxes.nth(i).check();
    }
    await page.locator('.add-selected-products').click();
    check('bulk add pushes all selected results into the cart', await rows(page).count() === resultCount);
    while (await rows(page).count()) {
        await rows(page).first().locator('.ibtnDel').click();
    }

    await search(page, 'huile');
    await page.waitForSelector('#product-results-container .product-img');

    await slowClick(page, page.locator('#product-results-container .product-img').first());
    check('slow click adds the product to the cart', await rows(page).count() === 1, 'rows=' + await rows(page).count());
    check('added row is HUILE 20L SALWA', (await rows(page).nth(0).locator('.product-code').inputValue()) === '21014058');
    check('first add sets quantity and delivered quantity to 1',
        (await rows(page).nth(0).locator('.qty').inputValue()) === '1' &&
        (await rows(page).nth(0).locator('.dqty').inputValue()) === '1');
    check('delivery status is stored for submit', (await rows(page).nth(0).locator('.dstatus-value').inputValue()) === '1 / 1');
    check('search field is cleared after add', (await page.inputValue('#product-search-input')) === '');
    check('results close after add', await page.locator('#product-results-container .product-img').count() === 0);

    await search(page, 'huile');
    await page.waitForSelector('#product-results-container .product-img');
    await slowClick(page, page.locator('#product-results-container .product-img', { hasText: '21014058' }).first());
    check('selecting the same product again keeps a single row', await rows(page).count() === 1, 'rows=' + await rows(page).count());
    check('repeated selection increments quantity and delivered quantity',
        (await rows(page).nth(0).locator('.qty').inputValue()) === '2' &&
        (await rows(page).nth(0).locator('.dqty').inputValue()) === '2');

    await search(page, 'riz');
    await page.waitForSelector('#product-results-container .product-img');
    await slowClick(page, page.locator('#product-results-container .product-img').first());
    check('a different product is added as its own row', await rows(page).count() === 2);
    check('newest row is the rice product', (await rows(page).nth(0).locator('.product-code').inputValue()) === '15233690');

    await rows(page).nth(1).locator('.dqty').evaluate((el) => { el.value = '1'; });
    await rows(page).nth(0).locator('.plus').click();
    check('plus updates only the clicked row quantity', (await rows(page).nth(0).locator('.qty').inputValue()) === '2');
    check('plus does not change another row delivered quantity', (await rows(page).nth(1).locator('.dqty').inputValue()) === '1', await rows(page).nth(1).locator('.dqty').inputValue());
    check('plus also advances delivered quantity on that row', (await rows(page).nth(0).locator('.dqty').inputValue()) === '2');

    await rows(page).nth(0).locator('.ibtnDel').click();
    check('removing an item leaves the other product', await rows(page).count() === 1);
    check('remaining product is the oil', (await rows(page).nth(0).locator('.product-code').inputValue()) === '21014058');

    await page.selectOption('#customer_id', '');
    await search(page, 'riz');
    await page.waitForSelector('#product-results-container .product-img');
    const rowsBeforeMissingCustomer = await rows(page).count();
    await page.locator('.product-result-select').first().check();
    await page.locator('.add-selected-products').click();
    check('missing customer blocks bulk product insertion', await rows(page).count() === rowsBeforeMissingCustomer);
    check('missing customer explains why', dialogs.some((message) => message.includes('Customer')));
    await page.selectOption('#customer_id', '21');

    await page.selectOption('#warehouse_id', '4');
    await search(page, 'huile');
    await page.waitForSelector('#product-results-container .product-img');
    const warehouseRequests = requests.filter((path) => path.startsWith('/sales/search/4/'));
    check('changing warehouse searches that warehouse', warehouseRequests.length > 0, requests.filter((path) => path.includes('/sales/search/')).join(','));
    check('other warehouse result shows that warehouse stock', (await page.locator('#product-results-container').innerText()).includes('3'));
    await page.selectOption('#warehouse_id', '1');

    const rowsBeforeEmpty = await rows(page).count();
    await search(page, 'zzz');
    await page.waitForTimeout(200);
    check('empty search shows the no-results message', await page.locator('#no-results-message').isVisible());
    check('empty search does not add a row', await rows(page).count() === rowsBeforeEmpty);

    await search(page, 'huile');
    await page.waitForSelector('#product-results-container .product-img');
    await page.fill('#product-search-input', 'ab');
    await page.waitForTimeout(50);
    check('a short query clears the result list', await page.locator('#product-results-container .product-img').count() === 0);

    await search(page, 'huile');
    await page.waitForSelector('#product-results-container .product-img');
    await page.click('#outside');
    await page.waitForTimeout(250);
    check('clicking outside closes the result list', await page.locator('#product-results-container .product-img').count() === 0);
    check('clicking outside does not add a product', await rows(page).count() === rowsBeforeEmpty);

    await search(page, '00000000');
    await page.waitForTimeout(200);
    check('out of stock product is not selectable', await page.locator('#product-results-container .product-img').count() === 0);
    check('out of stock search shows the no-results message', await page.locator('#no-results-message').isVisible());

    const rowsBeforeSingle = await rows(page).count();
    await search(page, 'parabole');
    await page.waitForSelector('#product-results-container .product-img');
    check('the only search match waits for an explicit click', await rows(page).count() === rowsBeforeSingle);
    await slowClick(page, page.locator('#product-results-container .product-img').first());
    check('clicking the only match adds it', await rows(page).count() === rowsBeforeSingle + 1);
    check('the clicked match is the rice product', (await rows(page).nth(0).locator('.product-code').inputValue()) === '15233690');

    const serialized = await page.evaluate(async () => {
        const body = $('form.payment-form').serialize();
        const response = await fetch('/sales', { method: 'POST', headers: { 'content-type': 'application/x-www-form-urlencoded' }, body });
        return await response.text();
    });
    check('sale payload includes the selected product code', serialized.includes('product_code%5B%5D=21014058') || serialized.includes('product_code[]=21014058'), serialized);
    check('sale payload includes the incremented quantity', serialized.includes('qty%5B%5D=2') || serialized.includes('qty[]=2'), serialized);
    check('no javascript errors', pageErrors.length === 0, pageErrors.join(' | '));
} finally {
    await browser.close();
}

if (failures.length) {
    console.error('\n' + failures.length + ' failed');
    process.exit(1);
}
console.log('\nAll sale product-selection checks passed');
