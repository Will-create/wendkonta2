/**
 * DOM regression coverage for Sales -> Add New Sale product selection.
 *
 * This executes the search, delegated click, productSearch, quantity, and
 * removal handlers extracted from the Blade view. Network responses are
 * deterministic fixtures matching SaleController's response contract.
 */
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const root = path.resolve(__dirname, '../..');
const blade = fs.readFileSync(path.join(root, 'resources/views/backend/sale/create.blade.php'), 'utf8');
const jquery = fs.readFileSync(path.join(root, 'public/vendor/jquery/jquery.min.js'), 'utf8');

function between(source, startMarker, endMarker) {
    const start = source.indexOf(startMarker);
    const end = source.indexOf(endMarker, start + startMarker.length);
    if (start < 0 || end < 0) {
        throw new Error(`Unable to extract ${startMarker}`);
    }
    return source.slice(start, end);
}

function stripBladeDirectives(source) {
    return source.split('\n').filter((line) => !line.trim().startsWith('@')).join('\n');
}

const searchHandlers = between(blade, '$(document).ready(function() {', '</script>')
    .replaceAll('{{url("/")}}', '')
    .replaceAll("{{ __('db.In Stock') }}", 'In Stock')
    .replaceAll("{{__('db.expired')}}", 'expired');
const clickHandler = stripBladeDirectives(between(
    blade,
    "$(document).on('click', '.product-img'",
    'function saleProductExtras'
));
const productSearch = between(blade, 'function saleProductExtras', 'function addNewProduct')
    .replaceAll('{{url("sales/lims_product_search")}}', '/sales/lims_product_search')
    .replaceAll('{{ __("db.This product has been added using the weight scale machine.")}}', 'Embedded product');
const deliveryStatus = between(blade, 'function updateDstatus', '//Delete product');
const removeHandler = between(
    blade,
    '$(' + '"table.order-list tbody"' + ').on("click", ".ibtnDel"',
    '//Edit product'
);
const quantityHandlers = between(
    blade,
    '$("#myTable").on(\'click\', \'.plus\'',
    '$("select[name=price_option]")'
);

if (searchHandlers.includes('{{') || productSearch.includes('{{')) {
    throw new Error('An unexpected Blade expression remains in the extracted JavaScript');
}

const catalog = {
    'OIL-1': ['Oil', 12.5, 1, 7],
    'OIL-2': ['Other Oil', 9.5, 2, 4],
    'RICE-1': ['Rice', 20, 3, 5],
};
const searches = {
    1: [
        { code: 'OIL-1', name: 'Oil', qty: 7, price: 12.5 },
        { code: 'OIL-2', name: 'Other Oil', qty: 4, price: 9.5 },
        { code: 'RICE-1', name: 'Rice', qty: 5, price: 20 },
    ],
    2: [{ code: 'OIL-1', name: 'Oil', qty: 2, price: 15 }],
};

function searchResponse(warehouse, term) {
    term = decodeURIComponent(term).toLowerCase();
    return (searches[warehouse] || []).filter((item) =>
        item.name.toLowerCase().includes(term) || item.code.toLowerCase().includes(term)
    ).map((item) => ({
        ...item,
        is_imei: false,
        is_embeded: false,
        product_batch_id: null,
        imei_number: null,
    }));
}

function detailResponse(data) {
    const item = catalog[data.code];
    if (!item) return null;
    const qty = Number(data.pre_qty);
    return [
        item[0], data.code, item[1], 0, 'No Tax', 1,
        'Piece,', '*,', '1,', item[2], null, false, false, false, false,
        qty, 0, 5, '', String(data.qty), 'standard', '', '', qty, `${qty}/${qty}`,
    ];
}

const html = `<!doctype html><html><body>
<form class="payment-form">
  <select id="customer_id" name="customer_id"><option value="10">Customer</option><option value="">None</option></select>
  <select id="warehouse_id" name="warehouse_id"><option value="1">One</option><option value="2">Two</option></select>
  <select id="biller_id" name="biller_id"><option value="5">Biller</option></select>
  <input id="product-search-input" name="product_code_name">
  <div id="product-results-container"></div>
  <div id="no-results-message" style="display:none">No results</div>
  <table id="myTable" class="order-list"><tbody></tbody></table>
</form>
<button id="outside">Outside</button>
<script>${jquery}</script>
<script>
window.requests = [];
window.dialogs = [];
window.scriptErrors = [];
window.addEventListener('error', (event) => window.scriptErrors.push(String(event.error || event.message)));
window.alert = (message) => window.dialogs.push(String(message));
var authUser = 1;
var currency = { exchange_rate: 1 };
var customer_group_rate = 0;
var rowindex;
var product_price = [], wholesale_price = [], product_discount = [];
var tax_rate = [], tax_name = [], tax_method = [];
var unit_name = [], unit_operator = [], unit_operation_value = [];
var is_imei = [], is_variant = [];
function calculateTotal() {}
function checkDiscount() {}
function checkQuantity() {}
function addNewProduct(data) {
    const row = $('<tr><td class="product-title">' + data[0] + '</td>'
        + '<td><button type="button" class="minus">-</button><input class="qty" name="qty[]" value="' + data[15] + '"><button type="button" class="plus">+</button></td>'
        + '<td><button type="button" class="dminus">-</button><input class="dqty" name="dqty[]" value="' + data[23] + '"><button type="button" class="dplus">+</button></td>'
        + '<td class="dstatus"></td>'
        + '<td style="display:none"><input class="dstatus-value" name="dstatus[]">'
        + '<input class="product-code" name="product_code[]" value="' + data[1] + '">'
        + '<input class="product-id" name="product_id[]" value="' + data[9] + '">'
        + '<input class="product_type" value="' + data[20] + '">'
        + '<input class="imei-number" name="imei_number[]" value=""></td>'
        + '<td><button type="button" class="ibtnDel">Remove</button></td></tr>');
    $('table.order-list tbody').prepend(row);
    rowindex = row.index();
    product_price.splice(rowindex, 0, Number(data[2]));
    wholesale_price.splice(rowindex, 0, 0);
    product_discount.splice(rowindex, 0, 0);
    tax_rate.splice(rowindex, 0, 0);
    tax_name.splice(rowindex, 0, 'No Tax');
    tax_method.splice(rowindex, 0, 1);
    unit_name.splice(rowindex, 0, 'Piece,');
    unit_operator.splice(rowindex, 0, '*,');
    unit_operation_value.splice(rowindex, 0, '1,');
    is_imei.splice(rowindex, 0, false);
    is_variant.splice(rowindex, 0, false);
    updateDstatus(rowindex);
}
$.ajax = function (options) {
    const url = new URL(options.url, 'http://pos.test');
    window.requests.push(url.pathname + url.search);
    const match = url.pathname.match(/^\\/sales\\/search\\/(\\d+)\\/(.+)$/);
    if (match) {
        options.success(window.__searchResponse(match[1], match[2]));
        return;
    }
    if (url.pathname === '/sales/lims_product_search') {
        const response = window.__detailResponse(options.data.data);
        response ? options.success(response) : options.error({ responseJSON: { error: 'Product not found' } });
    }
};
${deliveryStatus}
${removeHandler}
${quantityHandlers}
${productSearch}
${clickHandler}
${searchHandlers}
</script></body></html>`;

const dom = new JSDOM(html, {
    runScripts: 'dangerously',
    pretendToBeVisual: true,
    beforeParse(window) {
        window.__searchResponse = searchResponse;
        window.__detailResponse = detailResponse;
    },
});
const { window } = dom;
const $ = window.$;
const failures = [];

function check(name, condition, detail = '') {
    if (condition) console.log(`PASS ${name}`);
    else {
        failures.push(name);
        console.error(`FAIL ${name}${detail ? `: ${detail}` : ''}`);
    }
}
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function search(term) {
    const input = window.document.querySelector('#product-search-input');
    input.value = term;
    input.dispatchEvent(new window.Event('input', { bubbles: true }));
    await wait(350);
}

async function slowClick(element) {
    const input = window.document.querySelector('#product-search-input');
    input.focus();
    const down = new window.MouseEvent('mousedown', { bubbles: true, cancelable: true });
    element.dispatchEvent(down);
    // Emulate the browser's focus-changing default action.
    if (!down.defaultPrevented) input.blur();
    await wait(225);
    if (element.isConnected) {
        element.dispatchEvent(new window.MouseEvent('mouseup', { bubbles: true }));
        element.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
    }
    await wait(10);
}

(async () => {
    await wait(10);
    await search('oil');
    let results = [...window.document.querySelectorAll('.product-img')];
    check('search returns multiple selectable products', results.length === 2, `count=${results.length}`);

    await slowClick(results[0]);
    check('a slow pointer click adds the selected product', $('tbody tr').length === 1);
    check('the selected product identifier reaches the cart', $('.product-code').val() === 'OIL-1');
    check('search remains usable and is cleared after selection', $('#product-search-input').val() === '' && $('.product-img').length === 0);

    await search('oil');
    results = [...window.document.querySelectorAll('.product-img')];
    await slowClick(results[0]);
    check('repeated selection does not duplicate the row', $('tbody tr').length === 1);
    check('repeated selection increments ordered and delivered quantities', $('.qty').val() === '2' && $('.dqty').val() === '2');
    check('delivery ratio is synchronized for submission', $('.dstatus-value').val() === '2 / 2');

    await search('rice');
    await wait(10); // one result auto-selects
    check('a different product is inserted as a separate row', $('tbody tr').length === 2);

    const firstRow = $('tbody tr').first();
    const otherRow = $('tbody tr').last();
    otherRow.find('.dqty').val('1');
    firstRow.find('.plus').trigger('click');
    check('quantity controls update only their own row', firstRow.find('.qty').val() === '2' && otherRow.find('.dqty').val() === '1');
    firstRow.find('.ibtnDel').trigger('click');
    check('item removal preserves the other cart row', $('tbody tr').length === 1 && $('.product-code').val() === 'OIL-1');

    await search('oil');
    $('#warehouse_id').val('2').trigger('change');
    check('changing warehouse invalidates stale results', $('.product-img').length === 0);
    await search('oil');
    check('subsequent search uses the live warehouse', window.requests.some((url) => url.startsWith('/sales/search/2/')));

    $('#warehouse_id').val('1').trigger('change');
    await search('missing');
    check('empty search shows a clear no-results state', $('#no-results-message').css('display') !== 'none' && $('.product-img').length === 0);
    await search('empty');
    check('unavailable products are absent from selectable results', $('.product-img').length === 0);

    $('#customer_id').val('');
    await search('oil');
    results = [...window.document.querySelectorAll('.product-img')];
    const rowsBefore = $('tbody tr').length;
    await slowClick(results[0]);
    check('customer validation prevents cart insertion', $('tbody tr').length === rowsBefore && window.dialogs.some((message) => message.includes('Customer')));

    check('cart fields serialize for sale submission', $('.payment-form').serialize().includes('product_code%5B%5D=OIL-1'));
    check('selection workflow emits no JavaScript errors', window.scriptErrors.length === 0, window.scriptErrors.join(' | '));

    dom.window.close();
    if (failures.length) process.exitCode = 1;
    else console.log('\nAll DOM sale product-selection checks passed');
})().catch((error) => {
    console.error(error);
    dom.window.close();
    process.exitCode = 1;
});
