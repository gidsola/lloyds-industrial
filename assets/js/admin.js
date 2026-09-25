(() => {
    const escapeText = (value) => String(value || '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[character]));

    const bindMediaPicker = (container) => {
        const input = container.querySelector('[data-li-media-id]');
        const label = container.querySelector('[data-li-media-label]');
        const selectButton = container.querySelector('[data-li-media-select]');
        const removeButton = container.querySelector('[data-li-media-remove]');

        if (!input || !selectButton || !window.wp?.media) {
            return;
        }

        const renderSelectionCard = (attachment) => {
            let card = container.querySelector('[data-li-media-card]');

            if (!attachment || !attachment.id) {
                if (card) {
                    card.remove();
                }

                return;
            }

            if (!card) {
                card = document.createElement('span');
                card.className = 'li-media-picker-card';
                card.dataset.liMediaCard = 'true';
                input.insertAdjacentElement('afterend', card);
            }

            const title = attachment.filename || attachment.title || `Attachment ${attachment.id}`;
            const type = attachment.mime || attachment.type || 'file';
            const thumb = attachment.sizes?.thumbnail?.url || attachment.icon || '';
            const preview = thumb
                ? `<span class="li-media-picker-card__preview"><img src="${escapeText(thumb)}" alt=""></span>`
                : '<span class="li-media-picker-card__preview li-media-picker-card__preview--icon">File</span>';

            card.innerHTML = `
                ${preview}
                <span class="li-media-picker-card__body">
                    <strong>${escapeText(title)}</strong>
                    <small>${escapeText(type)} - #${escapeText(attachment.id)}</small>
                </span>
            `;
        };

        const hydrateSelection = () => {
            const attachmentId = parseInt(input.value || '0', 10);

            if (!attachmentId || !window.wp?.media?.attachment) {
                renderSelectionCard(null);
                return;
            }

            const attachment = window.wp.media.attachment(attachmentId);
            attachment.fetch().done(() => {
                renderSelectionCard({
                    id: attachmentId,
                    filename: attachment.attributes.filename,
                    title: attachment.attributes.title,
                    mime: attachment.attributes.mime,
                    type: attachment.attributes.type,
                    sizes: attachment.attributes.sizes,
                    icon: attachment.attributes.icon,
                });
            });
        };

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

                renderSelectionCard(attachment);

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

                renderSelectionCard(null);
                removeButton.hidden = true;
            });
        }

        hydrateSelection();
    };

    const bindProductFinder = (container) => {
        const searchInput = container.querySelector('[data-li-product-search]');
        const idInput = container.querySelector('[data-li-product-id]');
        const results = container.querySelector('[data-li-product-results]');

        if (!searchInput || !idInput || !results || !window.b2bAdmin?.ajaxUrl) {
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
                    _ajax_nonce: window.b2bAdmin.nonce,
                    search: query,
                });

                try {
                    const response = await fetch(`${window.b2bAdmin.ajaxUrl}?${params.toString()}`, {
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

    document.querySelectorAll('[data-li-admin-tabs]').forEach((tabs) => {
        const tabButtons = Array.from(tabs.querySelectorAll('[data-li-tab-target]'));
        const scopeSelector = tabs.dataset.liAdminTabs || '';
        const scope = scopeSelector ? document.querySelector(scopeSelector) : tabs.parentElement;
        const panels = scope ? Array.from(scope.querySelectorAll('[data-li-tab-panel]')) : [];

        if (!tabButtons.length || !panels.length) {
            return;
        }

        const storageKey = tabs.dataset.liTabsKey || '';
        const panelIds = panels.map((panel) => panel.dataset.liTabPanel).filter(Boolean);
        const getInitialTab = () => {
            const hash = window.location.hash.replace('#', '');

            if (hash && panelIds.includes(hash)) {
                return hash;
            }

            if (storageKey) {
                const stored = window.sessionStorage.getItem(storageKey);

                if (stored && panelIds.includes(stored)) {
                    return stored;
                }
            }

            return tabButtons[0].dataset.liTabTarget;
        };

        const activateTab = (target, updateHash = true) => {
            if (!panelIds.includes(target)) {
                target = tabButtons[0].dataset.liTabTarget;
            }

            tabButtons.forEach((button) => {
                const isActive = button.dataset.liTabTarget === target;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.liTabPanel !== target;
            });

            if (storageKey) {
                window.sessionStorage.setItem(storageKey, target);
            }

            if (updateHash && window.history?.replaceState) {
                window.history.replaceState(null, '', `${window.location.pathname}${window.location.search}#${target}`);
            }
        };

        tabButtons.forEach((button) => {
            button.addEventListener('click', () => activateTab(button.dataset.liTabTarget));
        });

        window.addEventListener('hashchange', () => {
            const hash = window.location.hash.replace('#', '');

            if (panelIds.includes(hash)) {
                activateTab(hash, false);
            }
        });

        activateTab(getInitialTab(), false);
    });

    document.querySelectorAll('[data-li-auto-dismiss-notice]').forEach((notice) => {
        const dismiss = () => {
            notice.classList.add('is-dismissing');
            window.setTimeout(() => notice.remove(), 220);
        };
        const dismissButton = notice.querySelector('[data-li-dismiss-notice]');

        if (dismissButton) {
            dismissButton.addEventListener('click', dismiss);
        }

        window.setTimeout(dismiss, 5200);
    });

    if (window.b2bAdmin?.mediaLibrary?.enabled) {
        const settings = window.b2bAdmin.mediaLibrary;
        const heading = document.querySelector('.wrap h1');
        const existing = document.querySelector('.li-media-library-banner');

        if (heading && !existing) {
            const banner = document.createElement('section');
            banner.className = 'li-media-library-banner';
            banner.innerHTML = `
                <div>
                    <p class="li-settings-kicker">Media Library</p>
                    <h2>${escapeText(settings.title || 'B2B Media Library')}</h2>
                    <p>${escapeText(settings.copy || '')}</p>
                </div>
                <div class="li-media-library-banner__cards">
                    ${(settings.cards || []).map((card) => `<span>${escapeText(card)}</span>`).join('')}
                </div>
            `;

            heading.insertAdjacentElement('afterend', banner);
        }

        const buildFilter = (name, label, options) => {
            const select = document.createElement('select');
            select.className = 'li-media-grid-filter';
            select.dataset.liMediaGridFilter = name;
            select.innerHTML = `<option value="">${escapeText(label)}</option>${Object.entries(options || {}).map(([value, optionLabel]) => (
                `<option value="${escapeText(value)}">${escapeText(optionLabel)}</option>`
            )).join('')}`;

            return select;
        };

        const injectGridFilters = () => {
            const toolbar = document.querySelector('.media-frame.mode-grid .media-toolbar-secondary');

            if (!toolbar || toolbar.querySelector('[data-li-media-grid-filter]')) {
                return;
            }

            const contextFilter = buildFilter('li_media_context', settings.contextLabel || 'All B2B media uses', settings.contexts);
            const folderFilter = buildFilter('li_media_folder', settings.folderLabel || 'All B2B folders', settings.folders);

            toolbar.append(contextFilter, folderFilter);

            [contextFilter, folderFilter].forEach((filter) => {
                filter.addEventListener('change', () => {
                    const query = window.wp?.media?.frame?.content?.get()?.collection?.props;

                    if (!query) {
                        return;
                    }

                    query.set(filter.dataset.liMediaGridFilter, filter.value || null);
                    query.set('paged', 1);
                    window.wp.media.frame.content.get().collection.more({ reset: true });
                });
            });
        };

        if (window.wp?.media) {
            const interval = window.setInterval(injectGridFilters, 300);
            window.setTimeout(() => window.clearInterval(interval), 6000);
            document.addEventListener('click', () => window.setTimeout(injectGridFilters, 100));
        }
    }
})();
