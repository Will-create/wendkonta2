(function (global) {
    'use strict';
    // Same checkbox/add-selected interaction as sales; line creation stays in the caller.
    global.SaleProProductPicker = function (root, options) {
        const input = document.getElementById(root.dataset.input);
        const s = options.strings;
        const selected = new Map();
        const status = document.createElement('p');
        status.setAttribute('role', 'status');
        const list = document.createElement('div');
        list.className = 'sp-product-options';
        const actions = document.createElement('div');
        actions.className = 'sp-picker-actions';
        const all = document.createElement('button');
        all.type = 'button'; all.className = 'btn btn-outline-primary'; all.textContent = s.select_all;
        const add = document.createElement('button');
        add.type = 'button'; add.className = 'btn btn-primary'; add.disabled = true;
        actions.append(all, add); root.append(status, list, actions); root.hidden = true;
        let timer, controller, generation = 0, products = [], busy = false;
        function count() { add.textContent = s.add_selected + ' (' + selected.size + ')'; add.disabled = busy || !selected.size; }
        function render() {
            list.replaceChildren();
            products.forEach(product => {
                const label = document.createElement('label'); label.className = 'sp-product-option';
                const check = document.createElement('input'); check.type = 'checkbox'; check.checked = selected.has(product.code);
                const caption = document.createElement('span'); caption.textContent = product.name + ' · ' + product.code;
                check.addEventListener('change', () => { if (check.checked) selected.set(product.code, product); else selected.delete(product.code); count(); });
                label.append(check, caption); list.append(label);
            });
            all.hidden = !products.length; count();
        }
        async function search() {
            const q = input.value.trim();
            const current = ++generation;
            if (controller) controller.abort();
            if (q.length < 2) { products = []; render(); root.hidden = selected.size === 0; return; }
            controller = new AbortController(); root.hidden = false; root.setAttribute('aria-busy', 'true'); status.textContent = s.loading;
            try {
                const response = await fetch(root.dataset.search + '?' + new URLSearchParams({q}), {signal: controller.signal, headers: {'Accept': 'application/json'}});
                if (!response.ok) throw new Error();
                const data = await response.json();
                if (current !== generation) return;
                products = data.products; status.textContent = data.more ? s.refine_search : products.length ? '' : s.no_products; render();
            } catch (error) { if (error.name !== 'AbortError' && current === generation) status.textContent = s.search_error; }
            finally { if (current === generation) root.removeAttribute('aria-busy'); }
        }
        input.addEventListener('input', () => {
            ++generation; if (controller) controller.abort(); clearTimeout(timer); timer = setTimeout(search, 250);
        });
        input.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); clearTimeout(timer); search(); } });
        all.addEventListener('click', () => { products.forEach(product => selected.set(product.code, product)); render(); });
        add.addEventListener('click', async () => {
            if (busy) return;
            busy = true; input.disabled = true; all.disabled = true; list.inert = true; count();
            root.setAttribute('aria-busy', 'true');
            try {
                for (const [key, product] of selected) { await options.add(product); selected.delete(key); }
                input.value = ''; products = []; render(); root.hidden = true; input.focus();
            } catch (error) { status.textContent = s.search_error; render(); }
            finally { busy = false; input.disabled = false; all.disabled = false; list.inert = false; root.removeAttribute('aria-busy'); count(); }
        });
        return {created(product) { selected.set(product.code, product); products = [product]; root.hidden = false; status.textContent = s.saved; render(); }};
    };
})(window);
