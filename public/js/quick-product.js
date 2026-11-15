(function (global) {
    'use strict';
    global.SaleProQuickProduct = function (form, strings) {
        const button = form.querySelector('[data-generate-code]');
        const code = form.querySelector('[name="code"]');
        const status = form.querySelector('[data-code-status]');
        let generation = 0, controller;
        const cancel = () => {
            ++generation; controller?.abort(); status.textContent = '';
            button.disabled = false; button.removeAttribute('aria-busy');
        };
        form.addEventListener('reset', cancel);
        form.addEventListener('submit', cancel, true);
        if (global.jQuery) global.jQuery(form.closest('.modal')).on('hidden.bs.modal', cancel);
        button.addEventListener('click', async () => {
            const original = code.value;
            if (original.trim() && !global.confirm(strings.replace_code)) return;
            const ticket = ++generation;
            controller = new AbortController();
            button.disabled = true; button.setAttribute('aria-busy', 'true'); status.textContent = strings.loading;
            try {
                const response = await fetch(button.dataset.generateCode, {signal:controller.signal, headers:{Accept:'text/plain'}});
                if (!response.ok) throw new Error();
                const value = (await response.text()).trim();
                if (ticket !== generation) return;
                if (!/^\d{8}$/.test(value)) throw new Error();
                if (code.value !== original) { status.textContent = strings.code_preserved; return; }
                code.value = value; code.dispatchEvent(new Event('input', {bubbles:true}));
                status.textContent = strings.code_generated;
            } catch (error) { if (ticket === generation && error.name !== 'AbortError') status.textContent = strings.search_error; }
            finally { if (ticket === generation) { button.disabled = false; button.removeAttribute('aria-busy'); } }
        });
    };
})(window);
