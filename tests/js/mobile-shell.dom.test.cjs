const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const root = path.resolve(__dirname, '../..');
const config = {strings: {details:'Details', less:'Less', offline:'Connect to save', pending:'Saving', unsaved:'Unsaved', summary_error:'Failed', retry:'Retry'}};
const html = `<!doctype html><html><head><script id="salepro-mobile-config" type="application/json">${JSON.stringify(config)}</script></head><body>
<header class="sp-mobile-header"></header><nav class="side-navbar"><a href="/products">Products</a></nav>
<button class="sp-menu-toggle"></button><button class="sp-menu-backdrop" hidden></button><button class="sp-menu-close"></button>
<table id="listing"><thead><tr>${['Name','Price','Stock','Status','Extra','Hidden detail','Actions'].map(label=>'<th>'+label+'</th>').join('')}</tr></thead><tbody><tr>${['Oil', '12.5','7','Active','kg','Details','Edit'].map(value=>'<td>'+value+'</td>').join('')}</tr></tbody></table>
<form id="sale" method="post" action="/sales"><table id="myTable" class="order-list"><thead><tr><th>Product</th><th>Quantity</th><th>Delivered</th></tr></thead><tbody><tr><td>Oil<input type="hidden" name="product_id[]" value="1"></td><td><input class="qty" name="qty[]" value="2"></td><td><input class="dqty" name="dqty[]" value="1"></td></tr></tbody></table><div><button name="draft" value="1" type="submit">Save</button></div></form>
<form id="settings" action="/setting/general_setting" method="post"><input name="site_title"></form>
</body></html>`;
const dom = new JSDOM(html, {url:'https://pos.test/sales/create', runScripts:'dangerously', pretendToBeVisual:true,
    beforeParse(window) {
        window.matchMedia = () => ({matches:true, addEventListener(){}});
        Object.defineProperty(window.navigator,'onLine',{value:true, configurable:true});
        window.HTMLElement.prototype.scrollIntoView = function(){};
    }});
const w = dom.window;
const errors = [];
w.addEventListener('error', event => errors.push(event.message));
w.eval(fs.readFileSync(path.join(root,'public/js/salepro-mobile.js'),'utf8'));
const document = w.document;
const sale = document.getElementById('sale');
const originalFields = Array.from(sale.querySelectorAll('table input')).map(input=>[input.name,input.value]);
assert.equal(sale.querySelector('[name=operation_token]').value.length,36);
const token = sale.querySelector('[name=operation_token]').value;
w.SaleProMobile.decorate();
assert.equal(sale.querySelector('[name=operation_token]').value, token, 'same form must retain token');
assert.equal(document.querySelector('#settings [name=operation_token]'), null, 'unrelated settings payload must remain unchanged');
assert.deepEqual(Array.from(sale.querySelectorAll('table input')).map(input=>[input.name,input.value]), originalFields);
assert.equal(document.querySelectorAll('#myTable tbody tr').length,1);
assert(document.querySelector('#myTable').classList.contains('sp-cart-table'));
assert.equal(document.querySelector('.qty').inputMode,'decimal');
const quantity = document.querySelector('.qty');
let parsedQuantity;
quantity.addEventListener('input', () => { parsedQuantity = parseFloat(quantity.value); });
quantity.value = '1,5';
quantity.dispatchEvent(new w.Event('input',{bubbles:true}));
assert.equal(parsedQuantity,1.5,'French decimal keypad must normalize before existing calculation handlers');
quantity.value = '٢٫٥';
quantity.dispatchEvent(new w.Event('input',{bubbles:true}));
assert.equal(parsedQuantity,2.5,'Arabic decimal keypad must normalize before existing calculation handlers');
quantity.value = '1,234,567';
quantity.dispatchEvent(new w.Event('input',{bubbles:true}));
assert.equal(quantity.value,'1,234,567','ambiguous grouping must not be silently interpreted as a decimal');
quantity.value = '2';
assert.equal(document.querySelector('#listing tbody td').dataset.label,'Name');
assert.equal(document.querySelector('#listing tbody tr').textContent, 'Oil12.57ActivekgDetailsEdit', 'mobile controls must not add text to table exports');
const details = document.querySelector('.sp-record-toggle');
details.click();
assert.equal(details.getAttribute('aria-expanded'),'true');
details.click();
assert.equal(details.getAttribute('aria-expanded'),'false');
document.querySelector('.sp-menu-toggle').click();
assert(document.body.classList.contains('sp-menu-open'));
assert.equal(document.querySelector('.sp-menu-toggle').getAttribute('aria-expanded'),'true');
document.dispatchEvent(new w.KeyboardEvent('keydown',{key:'Escape', bubbles:true}));
assert(!document.body.classList.contains('sp-menu-open'));
document.querySelector('.sp-menu-toggle').click();
document.querySelector('.sp-menu-close').click();
assert(!document.body.classList.contains('sp-menu-open'));
Object.defineProperty(w.navigator,'onLine',{value:false, configurable:true});
w.dispatchEvent(new w.Event('offline'));
assert.equal(document.querySelector('.sp-connectivity').hidden,false);
const offlineSubmit = new w.Event('submit',{bubbles:true,cancelable:true});
sale.dispatchEvent(offlineSubmit);
assert(offlineSubmit.defaultPrevented);
assert.equal(sale.querySelector('.sp-submit-feedback').textContent,'Connect to save');
Object.defineProperty(w.navigator,'onLine',{value:true, configurable:true});
w.dispatchEvent(new w.Event('online'));
const submit = new w.Event('submit',{bubbles:true,cancelable:true});
sale.dispatchEvent(submit);
assert(!submit.defaultPrevented);
assert.equal(new w.FormData(sale,sale.querySelector('button')).get('draft'),'1','submitter must be serialized before buttons are disabled');
const duplicate = new w.Event('submit',{bubbles:true,cancelable:true});
sale.dispatchEvent(duplicate);
assert(duplicate.defaultPrevented);
w.dispatchEvent(new w.Event('pageshow'));
assert.equal(sale.dataset.spPending,'false');
assert.deepEqual(errors,[]);
dom.window.close();
console.log('PASS mobile navigation, labels, cart contracts, submission tokens, offline guard, duplicate guard, and submitter serialization');
