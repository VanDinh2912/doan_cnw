// Storefront — application JavaScript entry point.
// Loaded only on public storefront pages via @vite.
// Uses only vanilla DOM APIs — no new dependencies.

import 'bootstrap/dist/css/bootstrap.min.css';
import '@fortawesome/fontawesome-free/css/all.min.css';

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

/**
 * Hero carousel.
 * Auto-rotates every 5s, pauses on hover/focus, respects prefers-reduced-motion.
 */
function initHeroCarousel() {
    const hero = document.querySelector('.sf-hero');
    if (!hero) return;

    const slides = hero.querySelectorAll('.sf-hero-slide');
    const dots = hero.querySelectorAll('.sf-hero-dot');
    if (slides.length < 2) return;

    let current = 0;
    let timer = null;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function go(index) {
        slides[current].classList.remove('is-active');
        dots[current]?.classList.remove('is-active');
        current = (index + slides.length) % slides.length;
        slides[current].classList.add('is-active');
        dots[current]?.classList.add('is-active');
    }

    function start() {
        if (reducedMotion) return;
        stop();
        timer = setInterval(() => go(current + 1), 5000);
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    dots.forEach((dot, i) => {
        dot.addEventListener('click', () => {
            go(i);
            start();
        });
    });

    hero.addEventListener('mouseenter', stop);
    hero.addEventListener('mouseleave', start);
    hero.addEventListener('focusin', stop);
    hero.addEventListener('focusout', start);

    start();
}

/**
 * Flash-sale countdown: resets to "ends at midnight" each day.
 * Purely client-side for the placeholder; a real campaign would ship
 * a server-computed expiry timestamp.
 */
function initFlashCountdown() {
    const root = document.querySelector('.sf-flash-countdown');
    if (!root) return;

    const hh = root.querySelector('[data-unit="h"]');
    const mm = root.querySelector('[data-unit="m"]');
    const ss = root.querySelector('[data-unit="s"]');
    if (!hh || !mm || !ss) return;

    function tick() {
        const now = new Date();
        const end = new Date(now);
        end.setHours(23, 59, 59, 999);
        let diff = Math.max(0, Math.floor((end - now) / 1000));
        hh.textContent = String(Math.floor(diff / 3600)).padStart(2, '0');
        diff %= 3600;
        mm.textContent = String(Math.floor(diff / 60)).padStart(2, '0');
        ss.textContent = String(diff % 60).padStart(2, '0');
    }

    tick();
    setInterval(tick, 1000);
}

/**
 * Favourite button — persistent in localStorage so customers
 * browsing the homepage can bookmark phones they like.
 */
function initFavouriteButtons() {
    const STORAGE_KEY = 'phoneshop_favs';
    let favs = [];
    try {
        favs = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
    } catch {
        favs = [];
    }

    function updateBtnState(btn, isActive) {
        btn.classList.toggle('is-active', isActive);
        const icon = btn.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-solid', isActive);
            icon.classList.toggle('fa-regular', !isActive);
        }
    }

    document.querySelectorAll('.js-fav-toggle').forEach((btn) => {
        const id = btn.dataset.id;
        if (favs.includes(id)) {
            updateBtnState(btn, true);
        }

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const idx = favs.indexOf(id);
            if (idx >= 0) {
                favs.splice(idx, 1);
                updateBtnState(btn, false);
            } else {
                favs.push(id);
                updateBtnState(btn, true);
            }
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(favs));
            } catch {
                // Ignore storage quota errors
            }
        });
    });
}

/**
 * Quick View Modal — binds data from product card to the Bootstrap modal.
 */
function initQuickView() {
    const modalEl = document.getElementById('sfQuickViewModal');
    if (!modalEl || !window.bootstrap?.Modal) return;

    const modal = new window.bootstrap.Modal(modalEl);

    const qvTitle = document.getElementById('qvTitle');
    const qvPrice = document.getElementById('qvPrice');
    const qvSku = document.getElementById('qvSku');
    const qvImage = document.getElementById('qvImage');
    const qvCategory = document.getElementById('qvCategory');
    const qvStock = document.getElementById('qvStock');

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.js-quick-view');
        if (!trigger) return;

        e.preventDefault();
        const card = trigger.closest('.sf-product-card');
        if (!card) return;

        if (qvTitle) qvTitle.textContent = card.dataset.name || '';
        if (qvPrice) qvPrice.textContent = card.dataset.price || '0 ₫';
        if (qvSku) qvSku.textContent = card.dataset.sku || '--';
        if (qvCategory) qvCategory.textContent = card.dataset.category || 'Điện thoại';
        if (qvImage) {
            qvImage.src = card.dataset.image || '';
            qvImage.alt = card.dataset.name || 'Sản phẩm';
        }

        if (qvStock) {
            qvStock.textContent = card.dataset.stockLabel || 'Còn hàng';
            qvStock.className = `sf-stock-badge ${card.dataset.stockClass || 'is-in'}`;
        }

        modal.show();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initHeroCarousel();
    initFlashCountdown();
    initFavouriteButtons();
    initQuickView();
});