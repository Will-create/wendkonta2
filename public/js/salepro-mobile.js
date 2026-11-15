(function () {
    'use strict';
    const element = document.getElementById('salepro-mobile-config');
    if (!element) return;
    const config = JSON.parse(element.textContent);
    const strings = config.strings;
    const mobile = matchMedia('(max-width: 767.98px)');
    const clean = node => (node.textContent || '').replace(/\s+/g, ' ').trim();
    const submitStates = new WeakMap();
    let scheduled = false;
    const paginationControls = new WeakMap();
    function decoratePagination() {
        if (!window.jQuery || !jQuery.fn.dataTable) return;
        document.querySelectorAll('table.dataTable').forEach(table => {
            if (!jQuery.fn.dataTable.isDataTable(table)) return;
            const api = jQuery(table).DataTable();
            const wrapper = api.table().container();
            let controls = paginationControls.get(table);
            if (!controls || controls.nav.parentElement !== wrapper) {
                if (controls) controls.nav.remove();
                const nav = document.createElement('nav');
                nav.className = 'sp-pagination'; nav.setAttribute('aria-label', strings.pagination || 'Pagination');
                const previous = document.createElement('button'), next = document.createElement('button');
                previous.type = next.type = 'button';
                previous.textContent = strings.previous || 'Previous'; next.textContent = strings.next || 'Next';
                const status = document.createElement('span'); status.setAttribute('role', 'status');
                const size = document.createElement('select'); size.setAttribute('aria-label', strings.records || 'Records');
                [10, 25, 50, 100].forEach(value => size.add(new Option(String(value), String(value))));
                previous.addEventListener('click', () => api.page('previous').draw('page'));
                next.addEventListener('click', () => api.page('next').draw('page'));
                size.addEventListener('change', () => api.page.len(Number(size.value)).draw());
                nav.append(previous, status, next, size); wrapper.append(nav);
                controls = {nav, previous, next, status, size}; paginationControls.set(table, controls);
            }
            const info = api.page.info();
            const caption = (strings.page || 'Page') + ' ' + (info.pages ? info.page + 1 : 0) + ' / ' + info.pages + ' · ' + info.recordsDisplay + ' ' + (strings.records || 'records');
            if (controls.status.textContent !== caption) controls.status.textContent = caption;
            controls.previous.disabled = info.page <= 0;
            controls.next.disabled = !info.pages || info.page + 1 >= info.pages;
            if (!Array.from(controls.size.options).some(option => Number(option.value) === info.length)) {
                controls.size.add(new Option(info.length === -1 ? '∞' : String(info.length), String(info.length)));
            }
            controls.size.value = String(info.length);
            wrapper.classList.add('sp-paginated');
        });
    }
    function feedback(form, message) {
        let box = form.querySelector('.sp-submit-feedback');
        if (!box) {
            box = document.createElement('div');
            box.className = 'sp-submit-feedback';
            box.setAttribute('role', 'alert');
            form.prepend(box);
        }
        box.textContent = message;
    }
    function recoverForm(form, message) {
        form.dataset.spPending = 'false';
        form.dataset.spDirty = 'true';
        form.removeAttribute('aria-busy');
        form.querySelectorAll('button[type="submit"],input[type="submit"],#submit-button').forEach(button => {
            const state = submitStates.get(button);
            if (state) { button.disabled = state.disabled; button.innerHTML = state.html; button.value = state.value; }
        });
        feedback(form, message || (navigator.onLine ? strings.unsaved : strings.offline));
    }
    function menu(open) {
        const sidebar = document.querySelector('nav.side-navbar');
        if (!sidebar) return;
        sidebar.id = 'salepro-mobile-menu';
        document.body.classList.toggle('sp-menu-open', open);
        document.querySelectorAll('.sp-menu-toggle').forEach(button => button.setAttribute('aria-expanded', String(open)));
        const backdrop = document.querySelector('.sp-menu-backdrop');
        if (backdrop) backdrop.hidden = !open;
        if (open) {
            const link = sidebar.querySelector('a[href]');
            if (link) link.focus();
        } else {
            const button = document.querySelector('.sp-menu-toggle');
            if (button) button.focus();
        }
    }
    function decorateTables() {
        document.querySelectorAll('table').forEach(table => {
            if (table.closest('.invoice,.invoice-container,[data-mobile-table="native"]') || table.classList.contains('totals')) return;
            const headers = Array.from(table.querySelectorAll('thead tr:first-child th'));
            if (headers.length < 2) return;
            const cart = table.classList.contains('order-list') || ['myTable', 'myTable1'].includes(table.id);
            table.classList.add('sp-card-table', cart ? 'sp-cart-table' : 'sp-list-table');
            Array.from(table.tBodies).forEach(body => Array.from(body.rows).forEach(row => {
                if (row.classList.contains('child') || row.cells.length < 2 || Array.from(row.cells).some(cell => cell.colSpan > 1)) return;
                Array.from(row.cells).forEach((cell, index) => {
                    const header = headers[index];
                    if (header && clean(header)) cell.dataset.label = clean(header);
                    const important = /name|customer|supplier|client|fournisseur|product|reference|date|total|amount|status|stock|quantity|qty|qté|balance|credit|debit|due|paid|nom|produit|référence|montant|statut|quantité|solde|العميل|المورد|المنتج|الكمية|الحالة|الإجمالي|الرصيد|الدائن|المدين/iu.test(header ? clean(header) : '');
                    const priority = header?.dataset.mobilePriority === 'true';
                    const secondary = header?.dataset.mobileSecondary === 'true';
                    cell.classList.toggle('sp-secondary', !cart && !priority && (secondary || (!important && index > 3 && index < row.cells.length - 1)));
                    if (cart) {
                        cell.querySelectorAll('input.qty,input.dqty,input[name="qty[]"],input[name="dqty[]"],input[name="recieved[]"]').forEach(input => {
                            input.inputMode = 'decimal';
                            if (!input.getAttribute('aria-label')) input.setAttribute('aria-label', clean(header || cell));
                        });
                    }
                });
                if (!cart && row.cells.length > 5 && !row.querySelector('.sp-record-toggle')) {
                    const target = row.cells[row.cells.length - 1];
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'sp-record-toggle';
                    button.dataset.caption = strings.details;
                    button.setAttribute('aria-label', strings.details);
                    button.setAttribute('aria-expanded', 'false');
                    button.addEventListener('click', event => {
                        event.stopPropagation();
                        const expanded = row.classList.toggle('sp-expanded');
                        button.setAttribute('aria-expanded', String(expanded));
                        button.dataset.caption = expanded ? strings.less : strings.details;
                        button.setAttribute('aria-label', button.dataset.caption);
                    });
                    target.append(button);
                }
            }));
        });
    }
    function decorateForms() {
        document.querySelectorAll('form').forEach(form => {
            if ((form.method || 'get').toLowerCase() === 'get') return;
            const url = new URL(form.action || location.href, location.href);
            if (url.origin !== location.origin || /^\/(login|logout|register|password)(\/|$)/.test(url.pathname)) return;
            const operational = /^\/(sales|purchases|purchase-products|products|transfers|qty_adjustment|stock-count|return-sale|return-purchase|customers?|suppliers?)(\/|$)/.test(url.pathname);
            if (operational && !form.querySelector('[name="operation_token"]') && crypto.randomUUID) {
                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = 'operation_token';
                token.value = crypto.randomUUID();
                form.append(token);
            }
            form.querySelectorAll('input[name*="price"],input[name*="amount"],input[name*="qty"],input[name*="discount"],input[name*="cost"],input[name*="rate"]').forEach(input => {
                if (input.type !== 'hidden' && !input.inputMode) input.inputMode = 'decimal';
            });
            const submits = form.querySelectorAll('button[type="submit"],input[type="submit"],#submit-button');
            submits.forEach(button => {
                if (!submitStates.has(button)) submitStates.set(button, {disabled:button.disabled, html:button.innerHTML, value:button.value});
            });
            if (submits.length && !form.closest('.modal,table,.dropdown-menu')) {
                const last = submits[submits.length - 1].parentElement;
                if (last && last !== form && !last.closest('table') && !last.querySelector('input:not([type="submit"]):not([type="hidden"]),select,textarea')) last.classList.add('sp-submit-bar');
            }
            if (/^\/(sales|purchases)(\/|$)/.test(url.pathname) && form.querySelector('table.order-list')) {
                form.classList.add('sp-operational-form');
                const fields = ['biller_id', 'currency_id', 'created_at', 'document', 'staff_note', 'sale_note'];
                fields.forEach(name => {
                    const input = form.querySelector('[name="' + name + '"]');
                    if (!input || (input.required && !input.value)) return;
                    const group = input.closest('.form-group');
                    const column = group && group.closest('[class*="col-"]');
                    if (column && column.querySelectorAll('.form-group').length <= 1) column.classList.add('sp-advanced-field');
                });
                const advanced = form.querySelector('.sp-advanced-field');
                if (advanced && !form.querySelector('.sp-order-details-toggle')) {
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = 'sp-order-details-toggle btn btn-outline-primary';
                    button.textContent = strings.order_details; button.setAttribute('aria-expanded', 'false');
                    button.addEventListener('click', () => button.setAttribute('aria-expanded', String(form.classList.toggle('sp-show-details'))));
                    const row = advanced.closest('.row');
                    if (row) row.before(button);
                }
                const bar = form.querySelector('.sp-submit-bar');
                const grandTotal = form.querySelector('input[name="grand_total"]');
                if (bar && grandTotal) {
                    let summary = bar.querySelector('.sp-checkout-total');
                    if (!summary) { summary = document.createElement('strong'); summary.className = 'sp-checkout-total'; summary.setAttribute('aria-live','polite'); bar.prepend(summary); }
                    const value = strings.total + ': ' + (grandTotal.value || '0');
                    if (summary.textContent !== value) summary.textContent = value;
                }
            }
        });
    }
    function decorate() {
        scheduled = false;
        decorateTables();
        decoratePagination();
        decorateForms();
        document.querySelectorAll('canvas').forEach(canvas => {
            const frame = canvas.closest('.line-chart,.pie-chart,.bar-chart');
            if (frame) frame.classList.add('sp-chart-frame');
        });
        document.querySelectorAll('.sp-theme-toggle').forEach(button => button.hidden = !document.querySelector('#switch-theme'));
        if (window.Chart && Chart.instances) Object.values(Chart.instances).forEach(chart => {
            if (!chart.canvas || !chart.data || !chart.canvas.closest('.sp-chart-frame')) return;
            if (window.ResizeObserver && !chart.spResizeObserver) {
                chart.spResizeObserver = new ResizeObserver(entries => {
                    const {width, height} = entries[0].contentRect;
                    if (!width || !height || (chart.spWidth === width && chart.spHeight === height)) return;
                    chart.spWidth = width; chart.spHeight = height;
                    requestAnimationFrame(() => { if (chart.canvas?.isConnected) chart.resize(); });
                });
                chart.spResizeObserver.observe(chart.canvas.closest('.sp-chart-frame'));
            }
            if (mobile.matches && !chart.spMobile) {
                chart.spAspectRatio = chart.options.maintainAspectRatio;
                chart.options.maintainAspectRatio = false;
                const axes = chart.options.scales || {};
                (axes.xAxes || []).forEach(axis => {
                    axis.ticks = axis.ticks || {};
                    axis.spTicks = Object.assign({}, axis.ticks);
                    Object.assign(axis.ticks, {autoSkip:true, maxTicksLimit:6, maxRotation:0});
                });
                if (chart.options.legend) {
                    chart.spLegend = chart.options.legend.position;
                    chart.options.legend.position = 'bottom';
                }
                chart.spMobile = true;
                chart.resize(); chart.update(0);
            } else if (!mobile.matches && chart.spMobile) {
                chart.options.maintainAspectRatio = chart.spAspectRatio;
                ((chart.options.scales || {}).xAxes || []).forEach(axis => { if (axis.spTicks) { axis.ticks = axis.spTicks; delete axis.spTicks; } });
                if (chart.options.legend && chart.spLegend) chart.options.legend.position = chart.spLegend;
                chart.spMobile = false;
                chart.resize(); chart.update(0);
            }
            let summary = chart.spSummary;
            if (!summary) {
                summary = document.createElement('div'); summary.className = 'sp-chart-summary';
                chart.spSummary = summary;
                chart.canvas.parentElement.after(summary);
            }
            const formatter = new Intl.NumberFormat(document.documentElement.lang || 'fr', {maximumFractionDigits:config.precision ?? 2});
            const datasets = chart.data.datasets || [];
            const segments = ['pie','doughnut','polarArea'].includes(chart.config?.type);
            const captions = segments && datasets.length === 1
                ? (datasets[0].data || []).map((value, index) => (chart.data.labels?.[index] || '') + ': ' + formatter.format(Number(value) || 0) + ' ' + (config.currency || ''))
                : datasets.map(dataset => (dataset.label || '') + ': ' + formatter.format((dataset.data || []).reduce((sum, value) => sum + (Number(value) || 0), 0)) + ' ' + (config.currency || ''));
            const caption = captions.join(' · ');
            if (summary.textContent !== caption) summary.textContent = caption;
            chart.canvas.setAttribute('role', 'img'); chart.canvas.setAttribute('aria-label', caption);
        });
    }
    function scheduleDecorate() {
        if (!window.document || !document.body) return;
        if (!scheduled) { scheduled = true; requestAnimationFrame(decorate); }
    }
    const banner = document.createElement('div');
    banner.className = 'sp-connectivity';
    banner.setAttribute('role', 'status');
    banner.textContent = strings.offline;
    const header = document.querySelector('.sp-mobile-header');
    if (header) header.after(banner); else document.body.prepend(banner);
    const connection = () => { banner.hidden = navigator.onLine; };
    connection();
    addEventListener('online', connection);
    addEventListener('offline', connection);
    document.querySelectorAll('.sp-menu-toggle').forEach(button => button.addEventListener('click', () => menu(!document.body.classList.contains('sp-menu-open'))));
    const backdrop = document.querySelector('.sp-menu-backdrop');
    if (backdrop) backdrop.addEventListener('click', () => menu(false));
    document.querySelectorAll('.sp-menu-close').forEach(button => button.addEventListener('click', () => menu(false)));
    document.querySelectorAll('.sp-theme-toggle').forEach(button => button.addEventListener('click', () => {
        if (window.jQuery) jQuery('#switch-theme').trigger('click');
    }));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') menu(false);
        if (event.key === 'Tab' && document.body.classList.contains('sp-menu-open')) {
            const links = Array.from(document.querySelectorAll('nav.side-navbar a[href],nav.side-navbar button')).filter(item => item.getClientRects().length);
            if (!links.length) return;
            if (event.shiftKey && document.activeElement === links[0]) { event.preventDefault(); links[links.length - 1].focus(); }
            else if (!event.shiftKey && document.activeElement === links[links.length - 1]) { event.preventDefault(); links[0].focus(); }
        }
    });
    document.addEventListener('input', event => {
        const form = event.target.form;
        if (form && form.querySelector('[name="operation_token"]')) form.dataset.spDirty = 'true';
    });
    document.addEventListener('input', event => {
        const input = event.target;
        if (event.isComposing || input.tagName !== 'INPUT' || input.type !== 'text' || input.inputMode !== 'decimal') return;
        const value = input.value.replace(/[٠-٩]/g, digit => String(digit.charCodeAt(0) - 0x660))
            .replace(/[۰-۹]/g, digit => String(digit.charCodeAt(0) - 0x6f0)).replace(/[,٫]/g, '.');
        if (value !== input.value && /^[+-]?\d*(?:\.\d*)?$/.test(value)) {
            const start = input.selectionStart, end = input.selectionEnd;
            input.value = value;
            input.setSelectionRange(start, end);
        }
    }, true);
    document.addEventListener('change', event => {
        const form = event.target.form;
        if (form && form.querySelector('[name="operation_token"]')) form.dataset.spDirty = 'true';
    });
    document.addEventListener('click', event => {
        const button = event.target.closest('button[type="submit"],input[type="submit"],#submit-button');
        const form = button && button.form;
        if (!form || form.method.toLowerCase() === 'get') return;
        if (!navigator.onLine || form.dataset.spPending === 'true') {
            event.preventDefault();
            event.stopImmediatePropagation();
            feedback(form, navigator.onLine ? strings.pending : strings.offline);
        }
    }, true);
    document.addEventListener('submit', event => {
        const form = event.target;
        if (form.method.toLowerCase() === 'get') return;
        if (!navigator.onLine || form.dataset.spPending === 'true') {
            event.preventDefault();
            event.stopImmediatePropagation();
            feedback(form, navigator.onLine ? strings.pending : strings.offline);
        }
    }, true);
    document.addEventListener('submit', event => {
        const form = event.target;
        if (event.defaultPrevented || form.method.toLowerCase() === 'get' || !form.checkValidity()) return;
        form.dataset.spPending = 'true';
        form.dataset.spDirty = 'false';
        form.setAttribute('aria-busy', 'true');
        setTimeout(() => form.querySelectorAll('button[type="submit"],input[type="submit"],#submit-button').forEach(button => {
            button.dataset.spWasDisabled = String(button.disabled);
            button.disabled = true;
        }), 0);
    });
    addEventListener('pageshow', () => document.querySelectorAll('form[data-sp-pending]').forEach(form => {
        form.dataset.spPending = 'false';
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-sp-was-disabled]').forEach(button => button.disabled = button.dataset.spWasDisabled === 'true');
    }));
    addEventListener('beforeunload', event => {
        if (document.querySelector('form[data-sp-dirty="true"]:not([data-sp-pending="true"])')) { event.preventDefault(); event.returnValue = ''; }
    });
    document.addEventListener('invalid', event => {
        if (event.target.closest('.sp-advanced-field')) event.target.form?.classList.add('sp-show-details');
        event.target.setAttribute('aria-invalid', 'true');
        event.target.scrollIntoView({block: 'center', behavior: 'smooth'});
    }, true);
    if (window.visualViewport) {
        let fullHeight = visualViewport.height;
        const viewport = () => {
            document.documentElement.style.setProperty('--sp-visual-height', visualViewport.height + 'px');
            document.documentElement.style.setProperty('--sp-visual-top', Math.max(0, visualViewport.offsetTop || 0) + 'px');
            const editing = document.activeElement && /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName);
            if (!editing) fullHeight = visualViewport.height;
            document.body.classList.toggle('sp-keyboard-open', mobile.matches && editing && fullHeight - visualViewport.height > 150);
        };
        visualViewport.addEventListener('resize', viewport);
        visualViewport.addEventListener('scroll', viewport);
        document.addEventListener('focusout', () => setTimeout(viewport, 0));
        viewport();
    }
    mobile.addEventListener('change', () => { menu(false); scheduleDecorate(); });
    addEventListener('load', scheduleDecorate);
    if (window.jQuery) {
        const activeRequests = new WeakMap();
        jQuery(document).on('init.dt draw.dt responsive-display.dt', scheduleDecorate);
        const requestForms = options => {
            const data = typeof options.data === 'string' ? new URLSearchParams(options.data) : options.data instanceof FormData ? options.data : null;
            const token = data ? data.get('operation_token') : options.data && options.data.operation_token;
            return Array.from(document.forms).filter(form => token && form.querySelector('[name="operation_token"]')?.value === token);
        };
        jQuery(document).ajaxSend((event, xhr, options) => requestForms(options).forEach(form => {
            if (!navigator.onLine || activeRequests.has(form)) {
                xhr.spBlocked = true;
                xhr.abort('salepro-blocked');
                feedback(form, navigator.onLine ? strings.pending : strings.offline);
                return;
            }
            activeRequests.set(form, xhr);
            form.dataset.spPending = 'true';
            form.dataset.spDirty = 'false';
            form.setAttribute('aria-busy', 'true');
        }));
        jQuery(document).ajaxComplete((event, xhr, options) => requestForms(options).forEach(form => {
            if (activeRequests.get(form) !== xhr) return;
            activeRequests.delete(form);
            form.dataset.spPending = 'false';
            form.removeAttribute('aria-busy');
            if (xhr.status >= 400 || xhr.status === 0) {
                recoverForm(form, form.querySelector('.sp-submit-feedback')?.textContent);
            }
            if (xhr.getResponseHeader('X-SalePro-Operation') === 'complete' && crypto.randomUUID) form.querySelector('[name="operation_token"]').value = crypto.randomUUID();
        }));
        jQuery(document).ajaxError((event, xhr, options) => {
            if (xhr.spBlocked) return;
            const forms = requestForms(options);
            if (forms.length) {
                const errors = xhr.responseJSON && xhr.responseJSON.errors;
                const message = errors && Object.values(errors).flat().join(' ') || xhr.responseJSON && xhr.responseJSON.message || (navigator.onLine ? strings.unsaved : strings.offline);
                forms.forEach(form => { form.dataset.spDirty = 'true'; feedback(form, message); });
            }
            if ([409, 419].includes(xhr.status)) {
                const notice = document.createElement('div');
                notice.className = 'sp-notice';
                notice.setAttribute('role', 'alert');
                notice.textContent = xhr.status === 409 ? strings.pending : strings.unsaved;
                document.body.append(notice);
            }
        });
    }
    const overview = document.querySelector('[data-sp-summary]');
    if (overview) {
        let currentRequest;
        const filters = new URLSearchParams({
            warehouse_id: document.querySelector('#warehouse_btn')?.value || '0',
            start_date: document.querySelector('input[name="start_date"]')?.value || '',
            end_date: document.querySelector('input[name="end_date"]')?.value || ''
        });
        const loadSummary = async () => {
            if (currentRequest) currentRequest.abort();
            const request = new AbortController();
            currentRequest = request;
            overview.setAttribute('aria-busy', 'true');
            try {
                const response = await fetch(config.summary + '?' + filters, {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}, signal: request.signal});
                if (!response.ok) throw new Error('summary failed');
                const data = await response.json();
                if (currentRequest !== request) return;
                const period = document.querySelector('[data-sp-summary-period]');
                if (period) period.textContent = data.start_date + ' — ' + data.end_date;
                overview.replaceChildren();
                Object.values(data.metrics).forEach(metric => {
                    const card = document.createElement('a');
                    card.className = 'sp-summary-card';
                    card.href = metric.url;
                    const label = document.createElement('span');
                    label.textContent = metric.label;
                    const value = document.createElement('strong');
                    const number = new Intl.NumberFormat(document.documentElement.lang || 'en', {maximumFractionDigits: metric.type === 'money' ? data.precision : 6, minimumFractionDigits: metric.type === 'money' ? data.precision : 0}).format(Number(metric.value));
                    value.textContent = metric.type === 'money' && data.currency
                        ? data.currency_position === 'prefix' ? data.currency + ' ' + number : number + ' ' + data.currency : number;
                    card.append(label, value);
                    overview.append(card);
                });
            } catch (error) {
                if (error.name === 'AbortError') return;
                const message = document.createElement('div');
                message.className = 'sp-summary-error';
                message.textContent = strings.summary_error + ' ';
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'btn btn-outline-primary';
                retry.textContent = strings.retry;
                retry.addEventListener('click', loadSummary);
                message.append(retry);
                overview.replaceChildren(message);
            } finally { if (currentRequest === request) overview.setAttribute('aria-busy', 'false'); }
        };
        document.querySelector('#warehouse_btn')?.addEventListener('change', event => { filters.set('warehouse_id', event.target.value); loadSummary(); });
        if (window.jQuery) jQuery(document).ajaxSuccess((event, xhr, options) => {
            const match = options.url.match(/dashboard-filter\/([^/]+)\/([^/]+)\/([^/?]+)/);
            if (match) {
                filters.set('start_date', match[1]); filters.set('end_date', match[2]); filters.set('warehouse_id', match[3]);
                loadSummary();
            }
        });
        loadSummary();
    }
    decorate();
    new MutationObserver(scheduleDecorate).observe(document.body, {childList: true, subtree: true});
    window.SaleProMobile = {decorate, menu, config, recoverForm};
}());
