(function () {
    'use strict';

    const ROTATE_MS = 8000;
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

    const state = {
        settings: {
            enabled: true,
            photo: '',
            video: '',
            orientation: 'auto',
            rotation_mode: 'mix',
            show_cart: true,
            store_name: 'INSAPOS',
        },
        cart: null,
        mediaTimer: null,
        mediaIndex: 0,
        mediaItems: [],
    };

    const els = {};

    function $(id) {
        return document.getElementById(id);
    }

    function cacheElements() {
        els.body = document.body;
        els.app = $('app');
        els.storeName = $('storeName');
        els.mediaPanel = $('mediaPanel');
        els.cartPanel = $('cartPanel');
        els.photoEl = $('photoEl');
        els.videoEl = $('videoEl');
        els.mediaPlaceholder = $('mediaPlaceholder');
        els.cartItems = $('cartItems');
        els.cartSubtotal = $('cartSubtotal');
        els.cartDiscount = $('cartDiscount');
        els.cartTotal = $('cartTotal');
        els.discountRow = $('discountRow');
    }

    function parseSettings(raw) {
        if (!raw) return { ...state.settings };
        if (typeof raw === 'string') {
            try { raw = JSON.parse(raw); } catch (_) { return { ...state.settings }; }
        }
        return {
            enabled: raw.enabled !== false && raw.enabled !== '0',
            photo: raw.photo || raw.photo_url || '',
            video: raw.video || raw.video_url || '',
            orientation: raw.orientation || 'auto',
            rotation_mode: raw.rotation_mode || 'mix',
            show_cart: raw.show_cart !== false && raw.show_cart !== '0',
            store_name: raw.store_name || 'INSAPOS',
        };
    }

    function bridgeSettings() {
        if (window.INSAPOS_CD && typeof window.INSAPOS_CD.getCustomerDisplaySettings === 'function') {
            try {
                const raw = window.INSAPOS_CD.getCustomerDisplaySettings();
                return parseSettings(raw);
            } catch (_) {}
        }
        return null;
    }

    function loadSettings(callback) {
        const fromBridge = bridgeSettings();
        if (fromBridge) {
            state.settings = fromBridge;
            applySettings();
            if (typeof callback === 'function') callback(state.settings);
            return;
        }

        fetch('settings.json', { cache: 'no-store' })
            .then((r) => (r.ok ? r.json() : null))
            .then((json) => {
                if (json) state.settings = parseSettings(json);
                applySettings();
                if (typeof callback === 'function') callback(state.settings);
            })
            .catch(() => {
                applySettings();
                if (typeof callback === 'function') callback(state.settings);
            });
    }

    function applyOrientation() {
        const mode = state.settings.orientation || 'auto';
        els.body.classList.remove('orientation-portrait', 'orientation-landscape', 'orientation-auto');
        if (mode === 'portrait') {
            els.body.classList.add('orientation-portrait');
        } else if (mode === 'landscape') {
            els.body.classList.add('orientation-landscape');
        } else {
            const landscape = window.innerWidth > window.innerHeight;
            els.body.classList.add(landscape ? 'orientation-landscape' : 'orientation-portrait', 'orientation-auto');
        }
    }

    function buildMediaList() {
        const photo = state.settings.photo;
        const video = state.settings.video;
        const mode = state.settings.rotation_mode || 'mix';
        const items = [];
        if (mode === 'photos' || mode === 'loop_photos') {
            if (photo) items.push({ type: 'photo', url: photo });
        } else if (mode === 'videos' || mode === 'loop_videos') {
            if (video) items.push({ type: 'video', url: video });
        } else {
            if (photo) items.push({ type: 'photo', url: photo });
            if (video) items.push({ type: 'video', url: video });
        }
        return items;
    }

    function hideMedia() {
        els.photoEl.classList.add('hidden');
        els.photoEl.classList.remove('visible');
        els.videoEl.classList.add('hidden');
        els.videoEl.classList.remove('visible');
        els.videoEl.pause();
        els.mediaPlaceholder.classList.remove('hidden');
    }

    function showPhoto(url) {
        els.videoEl.classList.add('hidden');
        els.videoEl.classList.remove('visible');
        els.videoEl.pause();
        els.photoEl.src = url;
        els.photoEl.classList.remove('hidden');
        requestAnimationFrame(() => els.photoEl.classList.add('visible'));
        els.mediaPlaceholder.classList.add('hidden');
    }

    function showVideo(url) {
        els.photoEl.classList.add('hidden');
        els.photoEl.classList.remove('visible');
        els.videoEl.src = url;
        els.videoEl.classList.remove('hidden');
        requestAnimationFrame(() => els.videoEl.classList.add('visible'));
        els.mediaPlaceholder.classList.add('hidden');
        const play = els.videoEl.play();
        if (play && typeof play.catch === 'function') play.catch(() => {});
    }

    function showMediaItem(item) {
        if (!item || !item.url) {
            hideMedia();
            return;
        }
        if (item.type === 'video') showVideo(item.url);
        else showPhoto(item.url);
    }

    function clearMediaTimer() {
        if (state.mediaTimer) {
            clearInterval(state.mediaTimer);
            state.mediaTimer = null;
        }
    }

    function rotateMedia() {
        clearMediaTimer();
        state.mediaItems = buildMediaList();
        state.mediaIndex = 0;
        if (!state.mediaItems.length) {
            hideMedia();
            return;
        }
        showMediaItem(state.mediaItems[0]);
        if (state.mediaItems.length < 2) return;
        state.mediaTimer = setInterval(() => {
            state.mediaIndex = (state.mediaIndex + 1) % state.mediaItems.length;
            showMediaItem(state.mediaItems[state.mediaIndex]);
        }, ROTATE_MS);
    }

    function loadMedia() {
        rotateMedia();
    }

    function applyCartVisibility() {
        if (state.settings.show_cart) {
            els.app.classList.remove('cart-hidden');
            els.cartPanel.classList.remove('hidden');
        } else {
            els.app.classList.add('cart-hidden');
            els.cartPanel.classList.add('hidden');
        }
    }

    function applySettings() {
        els.storeName.textContent = state.settings.store_name || 'INSAPOS';
        applyOrientation();
        applyCartVisibility();
        loadMedia();
        if (state.cart) updateCart(state.cart);
    }

    function lineTotal(item) {
        if (typeof item.lineTotal === 'number') return item.lineTotal;
        const qty = item.qty || 1;
        const price = item.price || 0;
        return qty * price;
    }

    function updateCart(cartJson) {
        let cart = cartJson;
        if (typeof cart === 'string') {
            try { cart = JSON.parse(cartJson); } catch (_) { return; }
        }
        state.cart = cart;

        if (!state.settings.show_cart) return;

        const items = Array.isArray(cart.items) ? cart.items : [];
        els.cartItems.innerHTML = '';
        if (!items.length) {
            const li = document.createElement('li');
            li.innerHTML = '<span class="cd-item-name">No items yet</span>';
            els.cartItems.appendChild(li);
        } else {
            items.forEach((item) => {
                const qty = item.qty || 1;
                const price = item.price || 0;
                const total = lineTotal(item);
                const li = document.createElement('li');
                li.innerHTML =
                    '<span class="cd-item-name"></span>' +
                    '<span class="cd-item-meta"></span>' +
                    '<span class="cd-item-total"></span>';
                li.querySelector('.cd-item-name').textContent = item.name || item.product_name || 'Item';
                li.querySelector('.cd-item-meta').textContent = qty + ' × ' + money.format(price);
                li.querySelector('.cd-item-total').textContent = money.format(total);
                els.cartItems.appendChild(li);
            });
        }

        const subtotal = typeof cart.subtotal === 'number'
            ? cart.subtotal
            : items.reduce((s, i) => s + lineTotal(i), 0);
        const discount = typeof cart.discount === 'number' ? cart.discount : 0;
        const total = typeof cart.total === 'number' ? cart.total : Math.max(0, subtotal - discount);

        els.cartSubtotal.textContent = money.format(subtotal);
        els.cartTotal.textContent = money.format(total);
        if (discount > 0) {
            els.discountRow.classList.remove('hidden');
            els.cartDiscount.textContent = '-' + money.format(discount);
        } else {
            els.discountRow.classList.add('hidden');
        }

        if (cart.store_name) {
            els.storeName.textContent = cart.store_name;
        }
    }

    window.loadSettings = loadSettings;
    window.applyOrientation = applyOrientation;
    window.loadMedia = loadMedia;
    window.rotateMedia = rotateMedia;
    window.updateCustomerDisplayCart = updateCart;

    document.addEventListener('DOMContentLoaded', () => {
        cacheElements();
        loadSettings();
        window.addEventListener('resize', () => {
            if (state.settings.orientation === 'auto') applyOrientation();
        });
        window.addEventListener('orientationchange', () => {
            if (state.settings.orientation === 'auto') applyOrientation();
        });
    });
})();
