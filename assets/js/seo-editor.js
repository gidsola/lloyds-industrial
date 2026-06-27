(function () {
    function getLabels() {
        return (window.lloydsSeoEditor && window.lloydsSeoEditor.labels) || {};
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getMeta() {
        return wp.data.select('core/editor').getEditedPostAttribute('meta') || {};
    }

    function updateMeta(key, value) {
        var meta = Object.assign({}, getMeta());
        meta[key] = value;
        wp.data.dispatch('core/editor').editPost({ meta: meta });
    }

    function renderPanel(container) {
        var labels = getLabels();
        var meta = getMeta();
        var socialImageId = parseInt(meta._li_seo_social_image_id || 0, 10);

        container.innerHTML = [
            '<section class="li-seo-editor-panel">',
                '<div class="li-seo-editor-panel__header">',
                    '<span>' + escapeHtml(labels.eyebrow || 'Lloyds SEO') + '</span>',
                    '<div>',
                        '<h2>' + escapeHtml(labels.title || 'Search and Social Preview') + '</h2>',
                        '<p>' + escapeHtml(labels.description || '') + '</p>',
                    '</div>',
                '</div>',
                '<div class="li-seo-editor-panel__grid">',
                    field('li_seo_editor_title', labels.seoTitle, '_li_seo_title', meta._li_seo_title || '', 'text', labels.seoTitleHelp, 70),
                    textarea('li_seo_editor_description', labels.metaDescription, '_li_seo_description', meta._li_seo_description || '', labels.metaDescriptionHelp, 180),
                    field('li_seo_editor_focus', labels.focusKeyword, '_li_seo_focus_keyword', meta._li_seo_focus_keyword || '', 'text', '', 0),
                    field('li_seo_editor_canonical', labels.canonical, '_li_seo_canonical', meta._li_seo_canonical || '', 'url', '', 0),
                    robotsSelect(labels.robots, labels.defaults, meta._li_seo_robots || ''),
                '</div>',
                '<div class="li-seo-editor-panel__social">',
                    '<h3>' + escapeHtml(labels.socialTitle || 'Social') + '</h3>',
                    '<div class="li-seo-editor-panel__grid">',
                        field('li_seo_editor_social_title', labels.socialTitle, '_li_seo_social_title', meta._li_seo_social_title || '', 'text', '', 0),
                        textarea('li_seo_editor_social_description', labels.socialDescription, '_li_seo_social_description', meta._li_seo_social_description || '', '', 0),
                    '</div>',
                    '<div class="li-seo-editor-panel__media">',
                        '<strong>' + escapeHtml(labels.socialImage || 'Social Image') + '</strong>',
                        '<span data-li-seo-editor-image-label>' + escapeHtml(socialImageId ? '#' + socialImageId : (labels.noImage || 'No image selected')) + '</span>',
                        '<div>',
                            '<button type="button" class="button button-secondary" data-li-seo-editor-select-image>' + escapeHtml(labels.selectImage || 'Select Image') + '</button>',
                            '<button type="button" class="button button-link" data-li-seo-editor-remove-image ' + (socialImageId ? '' : 'hidden') + '>' + escapeHtml(labels.removeImage || 'Remove') + '</button>',
                        '</div>',
                    '</div>',
                '</div>',
            '</section>'
        ].join('');

        bindPanel(container);
    }

    function field(id, label, key, value, type, help, maxlength) {
        return [
            '<label class="li-seo-editor-panel__field" for="' + id + '">',
                '<span>' + escapeHtml(label || '') + '</span>',
                '<input id="' + id + '" type="' + type + '" value="' + escapeHtml(value) + '" data-li-seo-editor-field="' + key + '"' + (maxlength ? ' maxlength="' + maxlength + '"' : '') + '>',
                help ? '<em>' + escapeHtml(help) + '</em>' : '',
            '</label>'
        ].join('');
    }

    function textarea(id, label, key, value, help, maxlength) {
        return [
            '<label class="li-seo-editor-panel__field" for="' + id + '">',
                '<span>' + escapeHtml(label || '') + '</span>',
                '<textarea id="' + id + '" rows="3" data-li-seo-editor-field="' + key + '"' + (maxlength ? ' maxlength="' + maxlength + '"' : '') + '>' + escapeHtml(value) + '</textarea>',
                help ? '<em>' + escapeHtml(help) + '</em>' : '',
            '</label>'
        ].join('');
    }

    function robotsSelect(label, defaultLabel, value) {
        var options = [
            ['', defaultLabel || 'Use global defaults'],
            ['index,follow', 'index, follow'],
            ['noindex,follow', 'noindex, follow'],
            ['noindex,nofollow', 'noindex, nofollow']
        ];

        return [
            '<label class="li-seo-editor-panel__field" for="li_seo_editor_robots">',
                '<span>' + escapeHtml(label || 'Robots') + '</span>',
                '<select id="li_seo_editor_robots" data-li-seo-editor-field="_li_seo_robots">',
                    options.map(function (option) {
                        return '<option value="' + escapeHtml(option[0]) + '"' + (value === option[0] ? ' selected' : '') + '>' + escapeHtml(option[1]) + '</option>';
                    }).join(''),
                '</select>',
            '</label>'
        ].join('');
    }

    function bindPanel(container) {
        Array.prototype.forEach.call(container.querySelectorAll('[data-li-seo-editor-field]'), function (input) {
            input.addEventListener('input', function () {
                updateMeta(input.getAttribute('data-li-seo-editor-field'), input.value);
            });

            input.addEventListener('change', function () {
                updateMeta(input.getAttribute('data-li-seo-editor-field'), input.value);
            });
        });

        bindMedia(container);
    }

    function bindMedia(container) {
        var labels = getLabels();
        var select = container.querySelector('[data-li-seo-editor-select-image]');
        var remove = container.querySelector('[data-li-seo-editor-remove-image]');
        var imageLabel = container.querySelector('[data-li-seo-editor-image-label]');

        if (select) {
            select.addEventListener('click', function (event) {
                event.preventDefault();

                if (!window.wp || !window.wp.media) {
                    return;
                }

                var frame = window.wp.media({
                    title: labels.selectImageTitle || 'Select SEO Social Image',
                    button: {
                        text: labels.useImage || 'Use This Image'
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: false
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    updateMeta('_li_seo_social_image_id', parseInt(attachment.id || 0, 10));

                    if (imageLabel) {
                        imageLabel.textContent = attachment.filename || attachment.title || '#' + attachment.id;
                    }

                    if (remove) {
                        remove.hidden = false;
                    }
                });

                frame.open();
            });
        }

        if (remove) {
            remove.addEventListener('click', function (event) {
                event.preventDefault();
                updateMeta('_li_seo_social_image_id', 0);

                if (imageLabel) {
                    imageLabel.textContent = labels.noImage || 'No image selected';
                }

                remove.hidden = true;
            });
        }
    }

    function insertPanel() {
        var postType = wp.data.select('core/editor').getCurrentPostType();
        var supported = (window.lloydsSeoEditor && window.lloydsSeoEditor.supportedPostTypes) || [];

        if (supported.indexOf(postType) === -1 || document.querySelector('[data-li-seo-editor-panel]')) {
            return true;
        }

        var target = document.querySelector('.edit-post-visual-editor') ||
            document.querySelector('.editor-styles-wrapper') ||
            document.querySelector('.interface-interface-skeleton__content');

        if (!target || !target.parentNode) {
            return false;
        }

        var wrapper = document.createElement('div');
        wrapper.className = 'li-seo-editor-panel-wrap';
        wrapper.setAttribute('data-li-seo-editor-panel', 'true');
        renderPanel(wrapper);
        target.parentNode.insertBefore(wrapper, target);

        return true;
    }

    wp.domReady(function () {
        var attempts = 0;
        var interval = window.setInterval(function () {
            attempts++;

            if (insertPanel() || attempts > 40) {
                window.clearInterval(interval);
            }
        }, 250);
    });
})();
