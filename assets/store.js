'use strict';

const menu = document.querySelector('.menu-button');
const sidebar = document.querySelector('.sidebar');
const shade = document.querySelector('.mobile-shade');
const main = document.querySelector('.main-shell');

function showMenu(open) {
    sidebar.classList.toggle('open', open);
    shade.hidden = !open;
    menu.setAttribute('aria-expanded', String(open));
    main.inert = open;
    if (open) {
        sidebar.querySelector('a').focus();
    } else {
        menu.focus();
    }
}

if (menu && sidebar && shade) {
    menu.addEventListener('click', () => showMenu(true));
    shade.addEventListener('click', () => showMenu(false));
    document.addEventListener('keydown', event => {
        if (!sidebar.classList.contains('open')) return;
        if (event.key === 'Escape') showMenu(false);
        if (event.key === 'Tab') {
            const links = Array.from(sidebar.querySelectorAll('a[href], button:not(:disabled)'));
            if (event.shiftKey && document.activeElement === links[0]) {
                event.preventDefault();
                links.at(-1).focus();
            } else if (!event.shiftKey && document.activeElement === links.at(-1)) {
                event.preventDefault();
                links[0].focus();
            }
        }
    });
    matchMedia('(min-width: 761px)').addEventListener('change', event => {
        if (event.matches && sidebar.classList.contains('open')) showMenu(false);
    });
}

document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

const productList = document.querySelector('[data-product-list]');
const productSearch = productList?.querySelector('[data-product-search]');
if (productSearch) {
    const rows = productList.querySelectorAll('[data-product-name]');
    const noMatches = productList.querySelector('[data-product-search-empty]');

    const filterProducts = () => {
        const term = productSearch.value.trim().toLocaleLowerCase();
        let hasMatch = false;
        for (const row of rows) {
            const { productName, productSku } = row.dataset;
            const matches = [productName, productSku].some(value => value.toLocaleLowerCase().includes(term));
            row.hidden = !matches;
            hasMatch ||= matches;
        }
        noMatches.hidden = hasMatch;
    };

    productSearch.addEventListener('input', filterProducts);
    filterProducts();
}

const stockForm = document.querySelector('[data-stock-form]');
if (stockForm) {
    const reason = stockForm.elements.reason;
    const direction = stockForm.elements.direction;
    const updateStock = () => {
        const canRemove = reason.value === 'adjustment';
        direction.options[1].disabled = !canRemove;
        if (!canRemove) direction.value = 'add';
        stockForm.elements.note.required = direction.value === 'remove';
    };
    reason.addEventListener('change', updateStock);
    direction.addEventListener('change', updateStock);
    updateStock();
}

const debtForm = document.querySelector('[data-debt-form]');
if (debtForm) {
    const referenceLabel = debtForm.querySelector('[data-debt-reference]');
    const updateReference = () => {
        const visible = debtForm.elements.method.value === 'gcash';
        referenceLabel.hidden = !visible;
        debtForm.elements.reference.disabled = !visible;
        debtForm.elements.reference.required = visible;
    };
    debtForm.elements.method.addEventListener('change', updateReference);
    updateReference();
}

const completed = document.querySelector('[data-sale-completed]');
if (completed) {
    try {
        sessionStorage.removeItem('sarisari-cart-' + completed.dataset.saleCompleted);
    } catch {
        // Checkout still works when session storage is unavailable.
    }
}

const pos = document.querySelector('[data-pos]');
if (pos) {
    const currency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const peso = cents => currency.format(cents / 100);
    const buttons = Array.from(pos.querySelectorAll('[data-add]'));
    const products = new Map(buttons.map(button => [button.dataset.add, {
        name: button.dataset.name,
        unit: button.dataset.unit,
        price: Number(button.dataset.price),
        stock: Number(button.dataset.stock)
    }]));
    const form = pos.querySelector('[data-checkout]');
    const items = pos.querySelector('[data-cart-items]');
    const status = pos.querySelector('[data-cart-status]');
    const submit = pos.querySelector('[data-complete]');
    const clear = pos.querySelector('[data-clear-cart]');
    const exact = pos.querySelector('[data-exact]');
    const search = pos.querySelector('[data-catalog-search]');
    const filters = pos.querySelectorAll('[data-category]');
    const noMatches = pos.querySelector('[data-catalog-empty]');
    const storageKey = 'sarisari-cart-' + pos.dataset.user;
    const cart = new Map();
    let category = '';
    let total = 0;

    function announce(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }

    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
        for (const [id, quantity, price] of saved) {
            const product = products.get(id);
            if (product?.stock && Number.isInteger(quantity) && quantity > 0) {
                cart.set(id, Math.min(quantity, product.stock, 10000));
            }
            if (!product || product.stock < quantity || product.price !== price) {
                announce('The cart was updated to match current prices and stock. Review it before charging.');
            }
        }
    } catch {
        // Start with the current in-memory cart.
    }

    function filterCatalog() {
        const term = search.value.trim().toLocaleLowerCase();
        let visible = 0;
        for (const button of buttons) {
            const match = (!category || category === button.dataset.group)
                && [button.dataset.name, button.dataset.sku].some(value => value.toLocaleLowerCase().includes(term));
            button.hidden = !match;
            if (match) visible += 1;
        }
        if (noMatches) noMatches.hidden = visible > 0;
    }

    function tendered() {
        const match = /^(\d{1,12})(?:\.(\d{1,2}))?$/.exec(form.elements.tendered.value.trim());
        return match ? Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0')) : 0;
    }

    function updatePayment() {
        const method = form.elements.method.value;
        for (const section of form.querySelectorAll('[data-payment]')) {
            const show = method === section.dataset.payment;
            section.hidden = !show;
            const control = section.querySelector('input, select');
            control.disabled = !show;
            control.required = show;
        }
        pos.querySelector('[data-change]').textContent = peso(Math.max(0, tendered() - total));
        submit.disabled = !cart.size || (method === 'cash' && tendered() < total)
            || (method === 'gcash' && !form.elements.reference.value.trim())
            || (method === 'utang' && !form.elements.customer_id.value);
    }

    function quantityButton(text, label, id, delta, disabled) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = text;
        button.setAttribute('aria-label', label);
        button.dataset.quantityId = id;
        button.dataset.delta = String(delta);
        button.disabled = disabled;
        return button;
    }

    function renderCart() {
        items.replaceChildren();
        let count = 0;
        total = 0;
        const order = [];
        for (const [id, quantity] of cart) {
            const product = products.get(id);
            const line = product.price * quantity;
            total += line;
            count += quantity;
            order.push({ id: Number(id), quantity, price: product.price });

            const row = document.createElement('li');
            const description = document.createElement('div');
            const name = document.createElement('strong');
            const unit = document.createElement('small');
            name.textContent = product.name;
            unit.textContent = peso(product.price) + ' / ' + product.unit;
            description.append(name, unit);

            const step = document.createElement('div');
            step.className = 'qty-step';
            const units = document.createElement('span');
            units.textContent = String(quantity);
            step.append(
                quantityButton('−', 'Remove one ' + product.name, id, -1, false),
                units,
                quantityButton('+', 'Add one ' + product.name, id, 1, quantity >= Math.min(product.stock, 10000))
            );
            const subtotal = document.createElement('strong');
            subtotal.textContent = peso(line);
            row.append(description, step, subtotal);
            items.append(row);
        }
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(order.map(({ id, quantity, price }) => [String(id), quantity, price])));
        } catch {
            // An unavailable storage API does not affect the sale.
        }
        pos.querySelector('[data-empty-cart]').hidden = count > 0;
        pos.querySelector('[data-cart-count]').textContent = count + (count === 1 ? ' item' : ' items');
        document.querySelector('[data-cart-jump-count]').textContent = String(count);
        pos.querySelector('[data-total]').textContent = peso(total);
        pos.querySelector('[data-cart-json]').value = JSON.stringify(order);
        clear.disabled = !count;
        exact.disabled = !count;
        updatePayment();
    }

    function changeQuantity(id, change) {
        const current = cart.get(id) || 0;
        const next = current + change;
        const limit = Math.min(products.get(id).stock, 10000);
        if (next > limit) {
            announce('Only ' + limit + ' available for this sale.');
            return;
        }
        if (!current && cart.size >= 100) {
            announce('A sale can include up to 100 different products.');
            return;
        }
        if (next <= 0) cart.delete(id);
        else cart.set(id, next);
        announce();
        renderCart();
    }

    buttons.forEach(button => button.addEventListener('click', () => changeQuantity(button.dataset.add, 1)));
    items.addEventListener('click', event => {
        const button = event.target.closest('[data-quantity-id]');
        if (!button) return;
        const { quantityId, delta } = button.dataset;
        changeQuantity(quantityId, Number(delta));
        const replacements = Array.from(items.querySelectorAll('[data-quantity-id]')).filter(item => item.dataset.quantityId === quantityId);
        const focusTarget = replacements.find(item => item.dataset.delta === delta && !item.disabled)
            || replacements.find(item => !item.disabled)
            || buttons.find(item => item.dataset.add === quantityId && !item.hidden);
        focusTarget?.focus();
    });
    search.addEventListener('input', filterCatalog);
    filters.forEach(button => button.addEventListener('click', () => {
        category = button.dataset.category;
        filters.forEach(filter => {
            const active = button === filter;
            filter.classList.toggle('active', active);
            filter.setAttribute('aria-pressed', String(active));
        });
        filterCatalog();
    }));
    clear.addEventListener('click', () => {
        cart.clear();
        announce();
        renderCart();
        search.focus();
    });
    exact.addEventListener('click', () => {
        form.elements.tendered.value = (total / 100).toFixed(2);
        updatePayment();
    });
    form.addEventListener('input', updatePayment);
    form.addEventListener('change', updatePayment);
    form.addEventListener('submit', event => {
        updatePayment();
        if (submit.disabled) {
            event.preventDefault();
            return;
        }
        submit.disabled = true;
    });
    renderCart();
}
