const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM} = require('jsdom');
const requests=[];
const config={summary:'/dashboard/mobile-summary',strings:{summary_error:'Overview unavailable',retry:'Retry'}};
const dom=new JSDOM(`<!doctype html><html lang="en"><script id="salepro-mobile-config" type="application/json">${JSON.stringify(config)}</script><select id="warehouse_btn"><option value="0">All</option><option value="1">Main</option><option value="2">Other</option></select><p data-sp-summary-period></p><div data-sp-summary></div></html>`,{url:'https://pos.test/dashboard',runScripts:'dangerously',pretendToBeVisual:true,beforeParse(w){
    w.matchMedia=()=>({matches:true,addEventListener(){}});
    w.fetch=(url,options)=>new Promise((resolve,reject)=>{
        requests.push({url,options,resolve,reject});
        options.signal.addEventListener('abort',()=>reject(new w.DOMException('Aborted','AbortError')));
    });
}});
const w=dom.window;
const document=w.document;
w.eval(fs.readFileSync(path.join(__dirname,'../../public/js/salepro-mobile.js'),'utf8'));
const overview=document.querySelector('[data-sp-summary]');
const warehouse=document.getElementById('warehouse_btn');
const tick=()=>new Promise(resolve=>setImmediate(resolve));
const data={start_date:'2026-11-01',end_date:'2026-11-14',precision:0,currency:'XOF',currency_position:'suffix',metrics:{
    due:{label:'Customer balances',type:'money',value:'123',url:'/customer'},
    stock:{label:'Stock quantity',type:'quantity',value:'1.25',url:'/products'}
}};
(async()=>{
    assert.equal(requests.length,1);
    warehouse.value='1';warehouse.dispatchEvent(new w.Event('change'));
    await tick();
    assert.equal(requests.length,2);
    assert.equal(requests[0].options.signal.aborted,true);
    assert.equal(overview.getAttribute('aria-busy'),'true','old aborted request must not finish the current loading state');
    assert.equal(new URL(requests[1].url,w.location.href).searchParams.get('warehouse_id'),'1');
    assert.equal(requests[1].options.cache,'no-store');
    requests[1].resolve({ok:true,json:async()=>data});await tick();
    assert.equal(overview.getAttribute('aria-busy'),'false');
    assert.equal(overview.querySelectorAll('a').length,2);
    assert.equal(overview.querySelectorAll('strong')[0].textContent,'123 XOF');
    assert.equal(overview.querySelectorAll('strong')[1].textContent,'1.25','quantity decimals must survive a zero-decimal currency');
    warehouse.value='2';warehouse.dispatchEvent(new w.Event('change'));
    requests[2].resolve({ok:false});await tick();
    assert.match(overview.textContent,/Overview unavailable/);
    overview.querySelector('button').click();
    assert.equal(requests.length,4);
    requests[3].resolve({ok:true,json:async()=>({...data,currency_position:'prefix'})});await tick();
    assert.equal(overview.querySelector('strong').textContent,'XOF 123');
    dom.window.close();
    console.log('PASS dashboard filter races, retry recovery, currency placement and fractional stock display');
})().catch(error=>{dom.window.close();console.error(error);process.exitCode=1;});
