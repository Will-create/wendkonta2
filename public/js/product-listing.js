(function (global) {
    'use strict';
    global.SaleProInventory = {
        quantity(value, type, row, strings) {
            if (type !== 'display') return value;
            const box = document.createElement('div'); box.className = 'sp-stock-quantity';
            box.dataset.stockStatus = row.stock_status || 'in_stock';
            const amount = document.createElement('strong');
            amount.textContent = row.stock_status === 'untracked' ? '—' : new Intl.NumberFormat(document.documentElement.lang || 'fr', {maximumFractionDigits:6}).format(Number(value) || 0);
            const status = document.createElement('span'); status.className = 'sp-stock-status';
            status.textContent = (row.stock_status === 'low_stock' ? strings.stock_low : strings[row.stock_status]) || strings.in_stock;
            box.append(amount);
            if (row.unit && row.unit !== 'N/A' && row.stock_status !== 'untracked') {
                const unit = document.createElement('small'); unit.textContent = row.unit; box.append(unit);
            }
            box.append(status); return box.outerHTML;
        },
        bind(table) {
            const form = document.getElementById('product-filter-form');
            const category = document.getElementById('category_id');
            const warehouse = document.getElementById('warehouse_id');
            const scope = document.getElementById('stock-scope');
            const caption = () => { if (scope) scope.textContent = warehouse.options[warehouse.selectedIndex]?.textContent || ''; };
            const refresh = () => { caption(); table.ajax.reload(null, true); };
            category.addEventListener('change', refresh); warehouse.addEventListener('change', refresh);
            form.addEventListener('submit', event => { event.preventDefault(); refresh(); });
            caption();
        }
    };
})(window);
