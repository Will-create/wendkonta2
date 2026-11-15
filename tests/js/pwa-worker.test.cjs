const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const events = {};
const entries = new Map([['/offline.html',new Response('<h1>Offline</h1>')]]);
const deleted = [];
const cached = [];
let failNetwork = false;
let activated = false;
const context = {URL, Request, Response, console, self:{location:new URL('https://pos.test/service-worker.js?build=test'),
    addEventListener:(name,handler)=>events[name]=handler, clients:{claim:async()=>{}}, skipWaiting:()=>{activated=true;}},
    caches:{open:async()=>({addAll:async assets=>cached.push(...assets),match:async key=>entries.get(key),put:async(key,value)=>entries.set(key,value)}),
        match:async key=>entries.get(key),keys:async()=>['salepro-public-old','salepro-public-test','other-app-cache'],delete:async key=>deleted.push(key)},
    fetch:async()=>{if(failNetwork)throw new Error('offline');return new Response('private customer record');}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/service-worker.js'),'utf8'),context);
function fetchEvent(url,method='GET',mode='cors') {
    let result;
    events.fetch({request:{url:'https://pos.test'+url,method,mode},respondWith:value=>result=value});
    return result;
}
(async()=>{
    let pending;
    events.install({waitUntil:value=>pending=value});await pending;
    assert(cached.some(request=>new URL(request.url).pathname==='/offline.html'));
    assert(cached.every(request=>request.cache==='reload' && request.credentials==='omit'),'new builds must bypass stale browser asset caches');
    assert(!cached.some(request=>/sales|purchases|dashboard|customer|uploads/.test(request.url)));
    assert.equal(fetchEvent('/sales','POST'),undefined);
    assert.equal(fetchEvent('/sales/search/1/oil'),undefined);
    assert.equal(fetchEvent('/images/product/customer-upload.jpg'),undefined);
    const online = await fetchEvent('/dashboard','GET','navigate');
    assert.equal(await online.text(),'private customer record');
    assert.equal(entries.has('/dashboard'),false);
    failNetwork=true;
    const fallback = await fetchEvent('/dashboard','GET','navigate');
    assert.equal(fallback.status,200);
    assert.equal(fallback.headers.get('X-SalePro-Offline'),'true');
    assert.match(await fallback.text(),/Offline/);
    events.activate({waitUntil:value=>pending=value});await pending;
    assert.deepEqual(deleted,['salepro-public-old']);
    assert.equal(activated,false,'worker must not interrupt an active sale');
    events.message({data:{type:'ACTIVATE_UPDATE'}});
    assert.equal(activated,true);
    console.log('PASS PWA private-data exclusion, online navigation, offline fallback, cache isolation and explicit update activation');
})().catch(error=>{console.error(error);process.exitCode=1;});
