/* ============================================================
   MODERN.JS — TableSpot Dark Theme Interactions
   Vanilla JS only — no libraries
   Optimized: all scroll via rAF+ticking, IntersectionObserver
   ============================================================ */

(function () {
    'use strict';

    /* ==========================================================
       UNIFIED SCROLL HANDLER — single listener, rAF-throttled
       ========================================================== */
    var scrollY = 0;
    var ticking = false;
    var headerScrolled = false; // hysteresis state
    var header = document.querySelector('.site-header');
    var heroBg = null; // set after DOM injection
    var scrollBtn = null; // set after DOM injection
    var scrollBtnVisible = false;

    function onScrollFrame() {
        // 1. Header: toggle with 80px threshold + hysteresis
        if (header) {
            var shouldScroll = headerScrolled ? scrollY > 40 : scrollY > 80;
            if (shouldScroll !== headerScrolled) {
                headerScrolled = shouldScroll;
                header.classList.toggle('scrolled', headerScrolled);
            }
        }

        // 2. Hero parallax via GPU transform
        if (heroBg) {
            heroBg.style.transform = 'translateY(' + (scrollY * 0.4) + 'px)';
        }

        // 3. Scroll-to-top button visibility
        if (scrollBtn) {
            var show = scrollY > 300;
            if (show !== scrollBtnVisible) {
                scrollBtnVisible = show;
                scrollBtn.classList.toggle('visible', show);
            }
        }

        ticking = false;
    }

    window.addEventListener('scroll', function () {
        scrollY = window.scrollY;
        if (!ticking) {
            requestAnimationFrame(onScrollFrame);
            ticking = true;
        }
    }, { passive: true });

    /* ==========================================================
       HERO BACKGROUND — inject div.hero-bg for GPU parallax
       ========================================================== */
    var homeHero = document.querySelector('.home-hero');
    if (homeHero) {
        // Only inject if not placement page (it has its own bg)
        if (!homeHero.closest('.placement-page')) {
            var bg = document.createElement('div');
            bg.className = 'hero-bg';
            homeHero.insertBefore(bg, homeHero.firstChild);
            heroBg = bg;
        }
    }

    // Run initial frame
    scrollY = window.scrollY;
    onScrollFrame();

    /* ==========================================================
       CARD CLICK — whole card navigates to restaurant page
       ========================================================== */
    document.addEventListener('click', function (e) {
        // Skip clicks on fav button or other interactive elements
        if (e.target.closest('.fav-btn')) return;
        var card = e.target.closest('.restaurant-card');
        if (!card) return;
        var link = card.querySelector('.restaurant-card-book');
        if (link && link.href) {
            window.location.href = link.href;
        }
    });

    /* ==========================================================
       CARD REVEAL — IntersectionObserver (no scroll events)
       ========================================================== */
    var cards = document.querySelectorAll('.restaurant-card');
    if (cards.length > 0 && 'IntersectionObserver' in window) {
        var cardObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var idx = parseInt(entry.target.getAttribute('data-reveal-idx') || '0', 10);
                    entry.target.style.animationDelay = (idx * 0.08) + 's';
                    entry.target.classList.add('reveal');
                    cardObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        cards.forEach(function (card, i) {
            card.setAttribute('data-reveal-idx', String(i));
            cardObserver.observe(card);
        });
    } else {
        cards.forEach(function (card) { card.classList.add('reveal'); });
    }

    /* ==========================================================
       STATS COUNTER — IntersectionObserver
       ========================================================== */
    var statNums = document.querySelectorAll('.stat-num');
    if (statNums.length > 0 && 'IntersectionObserver' in window) {
        var animated = new Set();

        var animateCounter = function (el) {
            var text = el.textContent.trim();
            var match = text.match(/^(\d+)/);
            if (!match) return;

            var target = parseInt(match[1], 10);
            var suffix = text.slice(match[1].length);
            var duration = 1200;
            var start = null;

            el.textContent = '0' + suffix;

            var step = function (timestamp) {
                if (!start) start = timestamp;
                var progress = Math.min((timestamp - start) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(target * eased) + suffix;
                if (progress < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        };

        var statsObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && !animated.has(entry.target)) {
                    animated.add(entry.target);
                    animateCounter(entry.target);
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        statNums.forEach(function (el) { statsObserver.observe(el); });
    }

    /* ---------- SVG location icon ---------- */
    document.querySelectorAll('.stat-num--icon[data-icon="location"]').forEach(function (el) {
        el.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="#D4A017">' +
            '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>' +
            '</svg>';
    });

    /* ==========================================================
       CUSTOM SELECT DROPDOWN — fixed positioning, opens downward
       ========================================================== */
    (function () {
        var dropdown = document.getElementById('cuisine-dropdown');
        var hiddenSelect = document.getElementById('cuisine-filter');
        if (!dropdown || !hiddenSelect) return;

        var btn = dropdown.querySelector('.custom-select-btn');
        var textEl = dropdown.querySelector('.custom-select-text');
        var list = dropdown.querySelector('.custom-select-list');
        var options = dropdown.querySelectorAll('.custom-select-option');

        // Move list to body so it escapes overflow:hidden and backdrop-filter containing blocks
        document.body.appendChild(list);

        function positionList() {
            var rect = btn.getBoundingClientRect();
            list.style.top = (rect.bottom + 8) + 'px';
            list.style.left = rect.left + 'px';
            list.style.width = rect.width + 'px';
        }

        function openDropdown() {
            positionList();
            dropdown.classList.add('open');
            // Re-trigger animation by removing and re-adding class
            list.classList.remove('open');
            void list.offsetWidth;
            list.classList.add('open');
        }

        function closeDropdown() {
            dropdown.classList.remove('open');
            list.classList.remove('open');
        }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (dropdown.classList.contains('open')) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        list.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        options.forEach(function (opt) {
            opt.addEventListener('click', function () {
                var val = opt.getAttribute('data-value');
                textEl.textContent = opt.textContent;
                hiddenSelect.value = val;
                options.forEach(function (o) { o.classList.remove('selected'); });
                opt.classList.add('selected');
                closeDropdown();
                hiddenSelect.dispatchEvent(new Event('change'));
            });
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target) && !list.contains(e.target)) {
                if (dropdown.classList.contains('open')) closeDropdown();
            }
        });
        window.addEventListener('scroll', function () {
            if (dropdown.classList.contains('open')) positionList();
        }, { passive: true });
        window.addEventListener('resize', function () {
            if (dropdown.classList.contains('open')) positionList();
        }, { passive: true });

    })();

    /* ==========================================================
       CLIENT-SIDE FILTERING
       ========================================================== */
    (function () {
        var cuisineSelect = document.getElementById('cuisine-filter');
        var ratingBtns = document.querySelectorAll('.filter-chip[data-rating]');
        var openNowBtn = document.getElementById('filter-open-now');
        var resetBtn = document.getElementById('filter-reset');
        var findBtn = document.getElementById('hero-find-btn');
        var filterCards = document.querySelectorAll('.restaurant-card[data-cuisine]');
        var grid = document.querySelector('.restaurants-grid');
        var dropdown = document.getElementById('cuisine-dropdown');

        if (!cuisineSelect || filterCards.length === 0) return;

        var state = { cuisine: '', rating: '', openNow: false };

        function pad2(n) { return String(n).padStart(2, '0'); }
        function currentTime() {
            var d = new Date();
            return pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':00';
        }

        function applyFilters() {
            var now = currentTime();
            var anyVisible = false;

            filterCards.forEach(function (card) {
                var show = true;
                if (state.cuisine && card.getAttribute('data-cuisine') !== state.cuisine) show = false;
                if (show && state.rating) {
                    if (parseFloat(card.getAttribute('data-rating') || '0') < parseFloat(state.rating)) show = false;
                }
                if (show && state.openNow) {
                    var s = card.getAttribute('data-hours-start') || '';
                    var e = card.getAttribute('data-hours-end') || '';
                    if (s && e && !(now >= s && now <= e)) show = false;
                }
                card.style.display = show ? '' : 'none';
                if (show) anyVisible = true;
            });

            var emptyEl = grid ? grid.querySelector('.empty-filter') : null;
            if (!anyVisible) {
                if (!emptyEl && grid) {
                    emptyEl = document.createElement('div');
                    emptyEl.className = 'empty empty-filter';
                    emptyEl.style.gridColumn = '1 / -1';
                    emptyEl.textContent = 'Ничего не найдено. Попробуйте другие фильтры.';
                    grid.appendChild(emptyEl);
                }
            } else if (emptyEl) {
                emptyEl.remove();
            }

        }

        // Cuisine change (from custom select or hidden select)
        cuisineSelect.addEventListener('change', function () {
            state.cuisine = this.value;
            applyFilters();
        });

        // "Найти" button — scroll to cards
        if (findBtn) {
            findBtn.addEventListener('click', function () {
                state.cuisine = cuisineSelect.value;
                applyFilters();
                if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }

        ratingBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                ratingBtns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                state.rating = btn.getAttribute('data-rating');
                applyFilters();
            });
        });

        if (openNowBtn) {
            openNowBtn.addEventListener('click', function () {
                state.openNow = !state.openNow;
                openNowBtn.classList.toggle('active', state.openNow);
                applyFilters();
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                state.cuisine = '';
                state.rating = '';
                state.openNow = false;
                cuisineSelect.value = '';
                // Reset custom select text
                if (dropdown) {
                    var textEl = dropdown.querySelector('.custom-select-text');
                    var opts = dropdown.querySelectorAll('.custom-select-option');
                    if (textEl) textEl.textContent = 'Все кухни';
                    opts.forEach(function (o) { o.classList.toggle('selected', o.getAttribute('data-value') === ''); });
                }
                ratingBtns.forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-rating') === ''); });
                if (openNowBtn) openNowBtn.classList.remove('active');
                applyFilters();
            });
        }
    })();

    /* ==========================================================
       HERO FEATURES LINE
       ========================================================== */
    var heroInner = document.querySelector('.home-hero .hero-inner');
    var heroSearch = document.querySelector('.hero-search');
    if (heroInner && heroSearch) {
        var features = document.createElement('div');
        features.className = 'hero-features';
        features.innerHTML =
            '<span class="hf-item"><svg class="hf-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>Лучшие рестораны</span>' +
            '<span class="hf-dot"></span>' +
            '<span class="hf-item"><svg class="hf-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>Проверенные отзывы</span>' +
            '<span class="hf-dot"></span>' +
            '<span class="hf-item"><svg class="hf-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Мгновенное бронирование</span>';
        heroInner.insertBefore(features, heroSearch);
    }

    /* ==========================================================
       HERO PARTICLES
       ========================================================== */
    if (homeHero) {
        var particlesContainer = document.createElement('div');
        particlesContainer.className = 'hero-particles';
        homeHero.appendChild(particlesContainer);

        for (var i = 0; i < 18; i++) {
            var p = document.createElement('div');
            p.className = 'hero-particle';
            p.style.setProperty('--size', (3 + Math.random() * 3) + 'px');
            p.style.setProperty('--delay', (Math.random() * 8) + 's');
            p.style.setProperty('--dur', (6 + Math.random() * 6) + 's');
            p.style.left = (Math.random() * 100) + '%';
            p.style.bottom = '-10px';
            particlesContainer.appendChild(p);
        }
    }

    /* ==========================================================
       CTA SVG ICON
       ========================================================== */
    var ctaInnerDiv = document.querySelector('.placement-cta-inner > div:first-child');
    if (ctaInnerDiv) {
        var iconWrap = document.createElement('div');
        iconWrap.className = 'cta-icon';
        iconWrap.innerHTML =
            '<svg viewBox="0 0 64 64" width="60" height="60" fill="none" xmlns="http://www.w3.org/2000/svg">' +
            '<path d="M20 4v20c0 3.3 2.7 6 6 6h2v26a2 2 0 004 0V30h2c3.3 0 6-2.7 6-6V4" stroke="#D4A017" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>' +
            '<line x1="26" y1="4" x2="26" y2="16" stroke="#D4A017" stroke-width="2.5" stroke-linecap="round"/>' +
            '<line x1="30" y1="4" x2="30" y2="16" stroke="#D4A017" stroke-width="2.5" stroke-linecap="round"/>' +
            '<line x1="34" y1="4" x2="34" y2="16" stroke="#D4A017" stroke-width="2.5" stroke-linecap="round"/>' +
            '<path d="M44 4c0 0-4 6-4 14s4 10 4 10v28a2 2 0 004 0V28s4-2 4-10S48 4 48 4" stroke="#D4A017" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>' +
            '</svg>';
        ctaInnerDiv.insertBefore(iconWrap, ctaInnerDiv.firstChild);
    }

    /* ==========================================================
       SECTION REVEALS — IntersectionObserver (no scroll events)
       ========================================================== */
    var revealSections = document.querySelectorAll(
        '.stats-bar, .map-section, .placement-cta, .benefits-grid, .recommendations, .auth-card'
    );
    if (revealSections.length > 0 && 'IntersectionObserver' in window) {
        revealSections.forEach(function (el) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        });

        var sectionObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    sectionObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -30px 0px' });

        revealSections.forEach(function (el) { sectionObserver.observe(el); });
    }

    /* ==========================================================
       BURGER MENU (480px)
       ========================================================== */
    var nav = document.querySelector('.nav');
    var headerInner = document.querySelector('.header-inner');
    if (nav && headerInner) {
        var burger = document.createElement('button');
        burger.className = 'burger-btn';
        burger.setAttribute('aria-label', 'Меню');
        burger.innerHTML = '<span></span><span></span><span></span>';
        headerInner.appendChild(burger);

        burger.addEventListener('click', function () {
            burger.classList.toggle('open');
            nav.classList.toggle('mobile-open');
        });

        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                burger.classList.remove('open');
                nav.classList.remove('mobile-open');
            });
        });
    }

    /* ==========================================================
       FAVORITES — AJAX toggle + auth modal
       ========================================================== */
    (function () {
        var overlay = document.createElement('div');
        overlay.className = 'fav-modal-overlay';
        var cssLink = document.querySelector('link[href*="/assets/css/style.css"]');
        var favBase = '';
        if (cssLink) {
            favBase = (cssLink.getAttribute('href') || '').replace('/assets/css/style.css', '');
        }
        overlay.innerHTML =
            '<div class="fav-modal">' +
                '<h3>Войдите в аккаунт</h3>' +
                '<p>Чтобы сохранить ресторан в избранное, нужно войти или зарегистрироваться.</p>' +
                '<div class="fav-modal-actions">' +
                    '<button class="fav-modal-close" type="button">Закрыть</button>' +
                    '<a class="btn btn-primary" href="' + favBase + '/auth/login.php">Войти</a>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.classList.contains('fav-modal-close')) {
                overlay.classList.remove('open');
            }
        });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.fav-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();

            var rid = btn.getAttribute('data-id');
            var csrfToken = btn.getAttribute('data-csrf');
            if (!rid) return;

            if (!csrfToken) {
                overlay.classList.add('open');
                return;
            }

            fetch(favBase + '/api/toggle-favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ csrf_token: csrfToken, restaurant_id: rid })
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) {
                    if (data.error === 'auth_required') overlay.classList.add('open');
                    return;
                }
                btn.classList.toggle('is-fav', data.favorited);
                btn.classList.remove('pop');
                void btn.offsetWidth;
                btn.classList.add('pop');

                if (!data.favorited && window.location.pathname.indexOf('favorites.php') !== -1) {
                    var card = btn.closest('.restaurant-card');
                    if (card) {
                        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(function () { card.remove(); }, 300);
                    }
                }
            })
            .catch(function () {});
        });
    })();

    /* ==========================================================
       SCROLL-TO-TOP BUTTON
       ========================================================== */
    scrollBtn = document.createElement('button');
    scrollBtn.className = 'scroll-top-btn';
    scrollBtn.setAttribute('aria-label', 'Наверх');
    scrollBtn.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" stroke="#0f0f0f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
        '<polyline points="18 15 12 9 6 15"></polyline>' +
        '</svg>';
    document.body.appendChild(scrollBtn);

    scrollBtn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    /* ==========================================================
       LAZY IMAGES — ensure loading=lazy + decoding=async
       ========================================================== */
    document.querySelectorAll('.restaurant-card img, .rec-card img').forEach(function (img) {
        img.loading = 'lazy';
        img.decoding = 'async';
    });

})();
