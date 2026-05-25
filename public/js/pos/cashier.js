let cart = [];
let customer = null;
let rewardsSettings = null;
let searchDebounce = null;
let activeShift = null;

const config = window.POS_CONFIG || { cashierId: null, branchId: null };

(function init() {
    loadSettings();
    loadShiftStatus();
})();

function loadSettings() {
    fetch('/api/pos/settings')
        .then(res => res.json())
        .then(data => {
            if (data.success) rewardsSettings = data.settings;
        })
        .catch(() => {});
}

function loadShiftStatus() {
    fetch('/api/pos/shift/current')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.shift) {
                activeShift = data.shift;
                showShiftOpen(data.shift);
            } else {
                activeShift = null;
                showShiftClosed();
            }
        })
        .catch(() => {
            activeShift = null;
            showShiftClosed();
        });
}

function showShiftOpen(shift) {
    document.getElementById('shiftClosed').classList.add('hidden');
    document.getElementById('shiftOpen').classList.remove('hidden');
    document.getElementById('shiftOpenedAt').textContent = new Date(shift.opened_at).toLocaleTimeString();
    document.getElementById('shiftOpeningCash').textContent = parseFloat(shift.opening_cash).toFixed(2);

    document.getElementById('posBody').classList.remove('opacity-50', 'pointer-events-none');
}

function showShiftClosed() {
    document.getElementById('shiftClosed').classList.remove('hidden');
    document.getElementById('shiftOpen').classList.add('hidden');

    document.getElementById('posBody').classList.add('opacity-50', 'pointer-events-none');
}

function openShift() {
    const openingCash = prompt('Enter opening cash amount:');
    if (openingCash === null) return;

    const amount = parseFloat(openingCash);
    if (isNaN(amount) || amount < 0) {
        alert('Please enter a valid amount.');
        return;
    }

    fetch('/api/pos/shift/open', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ opening_cash: amount }),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            activeShift = data.shift;
            showShiftOpen(data.shift);
            alert('Shift opened successfully!');
        } else {
            alert(data.message || 'Failed to open shift.');
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}

function closeShift() {
    const closingCash = prompt('Enter closing cash amount:');
    if (closingCash === null) return;

    const amount = parseFloat(closingCash);
    if (isNaN(amount) || amount < 0) {
        alert('Please enter a valid amount.');
        return;
    }

    fetch('/api/pos/shift/close', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ closing_cash: amount }),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const s = data.shift;
            const variance = parseFloat(s.cash_variance);
            const vLabel = variance >= 0 ? `+\u20B1${variance.toFixed(2)}` : `-\u20B1${Math.abs(variance).toFixed(2)}`;
            alert(
                `Shift closed!\n` +
                `System Sales: \u20B1${parseFloat(s.system_sales_total).toFixed(2)}\n` +
                `Expected Cash: \u20B1${(parseFloat(s.opening_cash) + parseFloat(s.system_sales_total)).toFixed(2)}\n` +
                `Closing Cash: \u20B1${parseFloat(s.closing_cash).toFixed(2)}\n` +
                `Variance: ${vLabel}`
            );
            activeShift = null;
            cart = [];
            customer = null;
            document.getElementById('customerInfo').innerHTML = 'No customer selected';
            document.getElementById('amountTendered').value = '';
            renderCart();
            showShiftClosed();
        } else {
            alert(data.message || 'Failed to close shift.');
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}

window.addEventListener('beforeunload', function (e) {
    if (activeShift) {
        e.preventDefault();
        e.returnValue = 'You have an active shift. Please close your shift before leaving.';
    }
});

function openQRScanner() {
    alert("QR Scanner UI will be implemented here.");
}

function openBarcodeScanner() {
    alert("Barcode Scanner UI will be implemented here.");
}

function openPhoneSearch() {
    const phone = prompt("Enter phone number:");
    if (!phone) return;

    fetch('/api/pos/customer/lookup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'phone', value: phone })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            customer = data.data;
            document.getElementById('customerInfo').innerHTML =
                `${customer.full_name} <br> Points: ${customer.loyalty_points}`;
            renderCart();
        } else {
            alert("Customer not found.");
        }
    });
}

function handleProductSearch(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = e.target.value.trim();
        if (!code) return;

        hideDropdown();
        lookupAndAddProduct(code);
        e.target.value = "";
    }
}

function handleProductAutocomplete(e) {
    const query = e.target.value.trim();

    clearTimeout(searchDebounce);

    if (query.length < 2) {
        hideDropdown();
        return;
    }

    searchDebounce = setTimeout(() => {
        fetch('/api/pos/products/search?q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(products => {
                if (products.length === 0) {
                    hideDropdown();
                    return;
                }
                showDropdown(products);
            })
            .catch(() => hideDropdown());
    }, 250);
}

function showDropdown(products) {
    const dd = document.getElementById('productDropdown');
    dd.innerHTML = '';

    products.forEach(p => {
        const item = document.createElement('div');
        item.className = 'p-3 hover:bg-blue-50 cursor-pointer border-b last:border-0 flex justify-between items-center';
        item.innerHTML = `
            <div>
                <div class="font-medium text-sm">${p.name}</div>
                <div class="text-xs text-gray-400">${p.sku || ''} ${p.barcode ? '| ' + p.barcode : ''}</div>
            </div>
            <div class="font-mono text-sm font-bold">\u20B1${parseFloat(p.price).toFixed(2)}</div>
        `;
        item.addEventListener('click', () => {
            addToCart({
                product_id: p.id,
                product_name: p.name,
                sku: p.sku,
                barcode: p.barcode,
                price: parseFloat(p.price),
                qty: 1,
                discount: 0
            });
            document.getElementById('productSearch').value = '';
            hideDropdown();
            document.getElementById('productSearch').focus();
        });
        dd.appendChild(item);
    });

    dd.classList.remove('hidden');
}

function hideDropdown() {
    document.getElementById('productDropdown').classList.add('hidden');
}

function lookupAndAddProduct(code) {
    fetch('/api/pos/products/search?q=' + encodeURIComponent(code))
        .then(res => res.json())
        .then(products => {
            if (products.length === 0) {
                alert("Product not found: " + code);
                return;
            }
            const p = products[0];
            addToCart({
                product_id: p.id,
                product_name: p.name,
                sku: p.sku,
                barcode: p.barcode,
                price: parseFloat(p.price),
                qty: 1,
                discount: 0
            });
        })
        .catch(() => {
            alert("Error searching for product.");
        });
}

function addToCart(item) {
    const existing = cart.find(i => i.product_id === item.product_id);
    if (existing) {
        existing.qty += item.qty;
    } else {
        cart.push({ ...item });
    }
    renderCart();
}

function renderCart() {
    const tbody = document.getElementById('cartItems');
    tbody.innerHTML = "";

    let subtotal = 0;
    let discountTotal = 0;

    cart.forEach((item, index) => {
        const lineTotal = item.qty * item.price;
        const lineDiscount = item.discount || 0;
        subtotal += lineTotal;
        discountTotal += lineDiscount;

        tbody.innerHTML += `
            <tr class="border-b">
                <td class="py-2">
                    <div class="font-medium">${item.product_name}</div>
                    <div class="text-xs text-gray-400">${item.sku || ''}</div>
                </td>
                <td class="text-right">${item.qty}</td>
                <td class="text-right">\u20B1${item.price.toFixed(2)}</td>
                <td class="text-right">\u20B1${(lineTotal - lineDiscount).toFixed(2)}</td>
                <td class="text-right">
                    <button onclick="removeItem(${index})" class="text-red-600 hover:text-red-800 font-bold">X</button>
                </td>
            </tr>
        `;
    });

    const grandTotal = subtotal - discountTotal;

    document.getElementById('subtotal').innerText = `\u20B1${subtotal.toFixed(2)}`;
    document.getElementById('discountTotal').innerText = `\u20B1${discountTotal.toFixed(2)}`;
    document.getElementById('grandTotal').innerText = `\u20B1${grandTotal.toFixed(2)}`;

    updateRewardsPreview(grandTotal);
}

function updateRewardsPreview(total) {
    const section = document.getElementById('rewardsSection');

    if (!rewardsSettings || !customer || rewardsSettings.rewards_enabled?.value !== '1') {
        section.classList.add('hidden');
        return;
    }

    const blockAmount = parseFloat(rewardsSettings.reward_block_amount?.value || 200);
    const rewardValue = parseFloat(rewardsSettings.reward_value?.value || 0.50);
    const mode = rewardsSettings.reward_mode?.value || 'rebate';
    const blocks = Math.floor(total / blockAmount);
    const reward = blocks * rewardValue;

    if (blocks > 0) {
        const label = mode === 'points' ? 'Points' : 'Rebate';
        const prefix = mode === 'points' ? '' : '\u20B1';
        document.getElementById('rewardsLabel').textContent = `${label} Earned (${blocks} block${blocks > 1 ? 's' : ''})`;
        document.getElementById('rewardsValue').textContent = `${prefix}${reward.toFixed(2)}`;
        section.classList.remove('hidden');
    } else {
        section.classList.add('hidden');
    }
}

function removeItem(index) {
    cart.splice(index, 1);
    renderCart();
}

function completeSale() {
    if (!activeShift) {
        alert('You must open a shift before processing sales.');
        return;
    }

    if (cart.length === 0) {
        alert("Cart is empty.");
        return;
    }

    if (!config.branchId) {
        alert("You are not assigned to a branch. Please contact admin.");
        return;
    }

    const amountTendered = parseFloat(document.getElementById('amountTendered').value || 0);
    const grandTotal = cart.reduce((sum, item) => {
        return sum + (item.qty * item.price) - (item.discount || 0);
    }, 0);

    if (amountTendered < grandTotal) {
        alert(`Insufficient amount. Total is \u20B1${grandTotal.toFixed(2)}`);
        return;
    }

    fetch('/api/pos/sales', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            branch_id: config.branchId,
            shift_id: activeShift.id,
            cashier_id: config.cashierId,
            member_id: customer ? customer.uuid : null,
            payment_method: "cash",
            amount_tendered: amountTendered,
            items: cart
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const sale = data.sale;
            const change = parseFloat(sale.change_due).toFixed(2);

            let msg = `Sale completed!\nSale #: ${sale.sale_number}\nChange: \u20B1${change}`;

            if (rewardsSettings && customer && rewardsSettings.rewards_enabled?.value === '1') {
                const blockAmt = parseFloat(rewardsSettings.reward_block_amount?.value || 200);
                const rwdVal = parseFloat(rewardsSettings.reward_value?.value || 0.50);
                const mode = rewardsSettings.reward_mode?.value || 'rebate';
                const blocks = Math.floor(parseFloat(sale.total) / blockAmt);
                const reward = blocks * rwdVal;
                if (blocks > 0) {
                    const label = mode === 'points' ? 'Points' : 'Rebate';
                    msg += `\n${label} Earned: ${mode === 'points' ? '' : '\u20B1'}${reward.toFixed(2)}`;
                }
            }

            alert(msg);
            cart = [];
            customer = null;
            document.getElementById('customerInfo').innerHTML = 'No customer selected';
            document.getElementById('amountTendered').value = '';
            document.getElementById('rewardsSection').classList.add('hidden');
            renderCart();
        } else {
            alert(data.message || "Error completing sale.");
        }
    })
    .catch(() => {
        alert("Network error. Please try again.");
    });
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#productSearch') && !e.target.closest('#productDropdown')) {
        hideDropdown();
    }
});
