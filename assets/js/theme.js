(() => {
    const header = document.querySelector('.site-header');

    if (!header) {
        return;
    }

    const updateHeaderState = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 24);
    };

    updateHeaderState();

    window.addEventListener('scroll', updateHeaderState, {
        passive: true,
    });
})();

(() => {
    const containers = document.querySelectorAll('[data-li-product-search]');
    const config = window.lloydsTheme || {};

    if (!containers.length || !config.ajaxUrl) {
        return;
    }

    const minLength = Number(config.productSearchMinLength || 2);
    const messages = config.i18n || {};

    const escapeHtml = (value) => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const renderMessage = (results, message) => {
        results.innerHTML = `<div class="li-product-search-results__message">${escapeHtml(message)}</div>`;
        results.hidden = false;
    };

    const renderResults = (results, products) => {
        if (!products.length) {
            renderMessage(results, messages.noResults || 'No matching products found.');
            return;
        }

        results.innerHTML = products.map((product) => {
            const tags = [
                ...(Array.isArray(product.categories) ? product.categories : []),
                ...(Array.isArray(product.industries) ? product.industries : []),
            ].slice(0, 3);

            const tagHtml = tags.length
                ? `<div class="li-product-search-results__tags">${tags.map((tag) => `<span>${escapeHtml(tag)}</span>`).join('')}</div>`
                : '';

            const summary = product.summary
                ? `<p>${escapeHtml(product.summary)}</p>`
                : '';

            return `
                <a class="li-product-search-result" href="${escapeHtml(product.url)}">
                    <span class="li-product-search-result__title">${escapeHtml(product.title)}</span>
                    ${summary}
                    ${tagHtml}
                </a>
            `;
        }).join('');
        results.hidden = false;
    };

    containers.forEach((container) => {
        const input = container.querySelector('[data-li-product-search-input]');
        const results = container.querySelector('[data-li-product-search-results]');

        if (!input || !results) {
            return;
        }

        let controller = null;
        let timer = null;

        const runSearch = () => {
            const search = input.value.trim();

            if (search.length < minLength) {
                results.hidden = true;
                results.innerHTML = '';
                return;
            }

            if (controller) {
                controller.abort();
            }

            controller = new AbortController();
            renderMessage(results, messages.searching || 'Searching products...');

            const url = new URL(config.ajaxUrl);
            url.searchParams.set('action', 'li_frontend_product_search');
            url.searchParams.set('_ajax_nonce', config.productSearchNonce || '');
            url.searchParams.set('search', search);

            fetch(url.toString(), {
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload || !payload.success || !Array.isArray(payload.data)) {
                        throw new Error('Invalid product search response.');
                    }

                    renderResults(results, payload.data);
                })
                .catch((error) => {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    renderMessage(results, messages.error || 'Product search is unavailable right now.');
                });
        };

        input.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(runSearch, 220);
        });

        input.addEventListener('focus', () => {
            if (input.value.trim().length >= minLength && results.innerHTML.trim() !== '') {
                results.hidden = false;
            }
        });

        document.addEventListener('click', (event) => {
            if (!container.contains(event.target)) {
                results.hidden = true;
            }
        });
    });
})();

(() => {
    const noticeSelector = [
        '.woocommerce-message',
        '.woocommerce-info',
        '.woocommerce-error',
        '.wc-block-components-notice-banner',
    ].join(',');

    const enhanceNotice = (notice) => {
        if (notice.dataset.liDismissReady === '1') {
            return;
        }

        notice.dataset.liDismissReady = '1';

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'li-notice-dismiss';
        close.setAttribute('aria-label', 'Dismiss notice');
        close.textContent = 'x';
        notice.appendChild(close);

        const dismiss = () => {
            notice.classList.add('is-dismissing');
            window.setTimeout(() => {
                notice.remove();
            }, 260);
        };

        close.addEventListener('click', dismiss);
        window.setTimeout(dismiss, 6500);
    };

    const enhanceNotices = (root = document) => {
        root.querySelectorAll(noticeSelector).forEach(enhanceNotice);
    };

    enhanceNotices();

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof HTMLElement)) {
                    return;
                }

                if (node.matches(noticeSelector)) {
                    enhanceNotice(node);
                    return;
                }

                enhanceNotices(node);
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
})();

(() => {
    const carousels = document.querySelectorAll('[data-li-product-carousel]');

    carousels.forEach((carousel) => {
        const viewport = carousel.querySelector('[data-li-carousel-viewport]');
        const previous = carousel.querySelector('[data-li-carousel-prev]');
        const next = carousel.querySelector('[data-li-carousel-next]');

        if (!viewport || !previous || !next) {
            return;
        }

        const getStep = () => {
            const firstCard = viewport.querySelector('.li-product-carousel-card');
            const cardWidth = firstCard ? firstCard.getBoundingClientRect().width : viewport.clientWidth * 0.8;
            return Math.max(260, cardWidth + 22);
        };

        const scroll = (direction) => {
            viewport.scrollBy({
                left: getStep() * direction,
                behavior: 'smooth',
            });
        };

        previous.addEventListener('click', () => scroll(-1));
        next.addEventListener('click', () => scroll(1));

        viewport.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                scroll(-1);
            }

            if (event.key === 'ArrowRight') {
                event.preventDefault();
                scroll(1);
            }
        });

        if (carousel.dataset.autoplay !== 'true') {
            return;
        }

        let timer = null;
        const interval = Number(carousel.dataset.interval || 5200);

        const start = () => {
            window.clearInterval(timer);
            timer = window.setInterval(() => {
                const nearEnd = viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 12;

                if (nearEnd) {
                    viewport.scrollTo({
                        left: 0,
                        behavior: 'smooth',
                    });
                    return;
                }

                scroll(1);
            }, interval);
        };

        const stop = () => {
            window.clearInterval(timer);
        };

        carousel.addEventListener('mouseenter', stop);
        carousel.addEventListener('mouseleave', start);
        carousel.addEventListener('focusin', stop);
        carousel.addEventListener('focusout', start);

        start();
    });
})();
