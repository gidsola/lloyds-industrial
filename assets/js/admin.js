(() => {
    const bindMediaPicker = (container) => {
        const input = container.querySelector('[data-li-media-id]');
        const label = container.querySelector('[data-li-media-label]');
        const selectButton = container.querySelector('[data-li-media-select]');
        const removeButton = container.querySelector('[data-li-media-remove]');

        if (!input || !selectButton || !window.wp?.media) {
            return;
        }

        selectButton.addEventListener('click', (event) => {
            event.preventDefault();

            const mediaOptions = {
                title: selectButton.dataset.liMediaTitle || 'Select file',
                button: {
                    text: selectButton.dataset.liMediaButton || 'Use file',
                },
                multiple: false,
            };

            if (selectButton.dataset.liMediaType) {
                mediaOptions.library = {
                    type: selectButton.dataset.liMediaType,
                };
            }

            const frame = window.wp.media(mediaOptions);

            frame.on('select', () => {
                const attachment = frame.state().get('selection').first()?.toJSON();

                if (!attachment) {
                    return;
                }

                input.value = attachment.id || '';

                if (label) {
                    label.textContent = attachment.filename || attachment.title || `Attachment ${attachment.id}`;
                }

                if (removeButton) {
                    removeButton.hidden = false;
                }
            });

            frame.open();
        });

        if (removeButton) {
            removeButton.addEventListener('click', (event) => {
                event.preventDefault();

                input.value = '';

                if (label) {
                    label.textContent = 'No file selected';
                }

                removeButton.hidden = true;
            });
        }
    };

    const bindProductFinder = (container) => {
        const searchInput = container.querySelector('[data-li-product-search]');
        const idInput = container.querySelector('[data-li-product-id]');
        const results = container.querySelector('[data-li-product-results]');

        if (!searchInput || !idInput || !results || !window.lloydsAdmin?.ajaxUrl) {
            return;
        }

        let requestId = 0;
        let searchTimer;

        const clearResults = () => {
            results.innerHTML = '';
            results.hidden = true;
        };

        const renderResults = (products) => {
            results.innerHTML = '';

            if (!products.length) {
                const empty = document.createElement('div');
                empty.className = 'li-product-search-results__empty';
                empty.textContent = 'No products found';
                results.append(empty);
                results.hidden = false;
                return;
            }

            products.forEach((product) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'li-product-search-results__item';
                button.textContent = `${product.title} (#${product.id})`;

                button.addEventListener('click', () => {
                    idInput.value = product.id;
                    searchInput.value = product.title;
                    clearResults();
                });

                results.append(button);
            });

            results.hidden = false;
        };

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim();

            window.clearTimeout(searchTimer);

            if (query.length < 2) {
                clearResults();
                return;
            }

            searchTimer = window.setTimeout(async () => {
                const currentRequestId = ++requestId;
                const params = new URLSearchParams({
                    action: 'li_search_products',
                    _ajax_nonce: window.lloydsAdmin.nonce,
                    search: query,
                });

                try {
                    const response = await fetch(`${window.lloydsAdmin.ajaxUrl}?${params.toString()}`, {
                        credentials: 'same-origin',
                    });
                    const payload = await response.json();

                    if (currentRequestId !== requestId || !payload.success) {
                        return;
                    }

                    renderResults(payload.data || []);
                } catch (error) {
                    clearResults();
                }
            }, 250);
        });

        document.addEventListener('click', (event) => {
            if (!container.contains(event.target)) {
                clearResults();
            }
        });
    };

    document.querySelectorAll('[data-li-media-picker]').forEach(bindMediaPicker);
    document.querySelectorAll('[data-li-product-finder]').forEach(bindProductFinder);
})();
