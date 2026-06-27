(function (wp) {
    if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.components || !wp.data) {
        return;
    }

    var registerPlugin = wp.plugins.registerPlugin;
    var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
    var el = wp.element.createElement;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var SelectControl = wp.components.SelectControl;
    var Button = wp.components.Button;
    var Notice = wp.components.Notice;
    var settings = window.lloydsSeoEditor || {};
    var supportedPostTypes = settings.supportedPostTypes || [];
    var labels = settings.labels || {};

    if (!PluginDocumentSettingPanel) {
        return;
    }

    function label(key, fallback) {
        return labels[key] || fallback;
    }

    function SeoDocumentPanel() {
        var editorState = useSelect(function (select) {
            var editor = select('core/editor');

            return {
                meta: editor.getEditedPostAttribute('meta') || {},
                postType: editor.getCurrentPostType(),
            };
        }, []);
        var editPost = useDispatch('core/editor').editPost;
        var meta = editorState.meta;

        if (supportedPostTypes.indexOf(editorState.postType) === -1) {
            return null;
        }

        function updateMeta(key, value) {
            var nextMeta = {};

            Object.keys(meta).forEach(function (metaKey) {
                nextMeta[metaKey] = meta[metaKey];
            });

            nextMeta[key] = value;
            editPost({ meta: nextMeta });
        }

        function selectSocialImage() {
            if (!wp.media) {
                return;
            }

            var frame = wp.media({
                title: label('selectImageTitle', 'Select SEO Social Image'),
                button: {
                    text: label('useImage', 'Use This Image'),
                },
                library: {
                    type: 'image',
                },
                multiple: false,
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first();
                var image = attachment && attachment.toJSON ? attachment.toJSON() : attachment;

                if (image) {
                    updateMeta('_li_seo_social_image_id', image.id || 0);
                }
            });

            frame.open();
        }

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'lloyds-seo-document-panel',
                title: label('eyebrow', 'Lloyds SEO'),
                className: 'lloyds-seo-document-panel',
                initialOpen: false,
            },
            el(
                'div',
                { className: 'lloyds-seo-document-panel__intro' },
                el('span', null, label('eyebrow', 'Lloyds SEO')),
                el('h3', null, label('title', 'Search and Social Preview')),
                el('p', null, label('description', 'Tune how this content appears in search results, social cards, and canonical discovery.'))
            ),
            el(TextControl, {
                label: label('seoTitle', 'SEO Title'),
                help: label('seoTitleHelp', 'Recommended: 50-60 characters. Leave blank to auto-generate.'),
                value: meta._li_seo_title || '',
                onChange: function (value) {
                    updateMeta('_li_seo_title', value);
                },
            }),
            el(TextareaControl, {
                label: label('metaDescription', 'Meta Description'),
                help: label('metaDescriptionHelp', 'Recommended: 140-160 characters. Product summaries and excerpts are used as fallback.'),
                value: meta._li_seo_description || '',
                onChange: function (value) {
                    updateMeta('_li_seo_description', value);
                },
            }),
            el(TextControl, {
                label: label('focusKeyword', 'Focus Keyword'),
                value: meta._li_seo_focus_keyword || '',
                onChange: function (value) {
                    updateMeta('_li_seo_focus_keyword', value);
                },
            }),
            el(TextControl, {
                label: label('canonical', 'Canonical URL'),
                value: meta._li_seo_canonical || '',
                onChange: function (value) {
                    updateMeta('_li_seo_canonical', value);
                },
            }),
            el(SelectControl, {
                label: label('robots', 'Robots'),
                value: meta._li_seo_robots || '',
                options: [
                    { label: label('defaults', 'Use global defaults'), value: '' },
                    { label: 'index, follow', value: 'index,follow' },
                    { label: 'noindex, follow', value: 'noindex,follow' },
                    { label: 'noindex, nofollow', value: 'noindex,nofollow' },
                ],
                onChange: function (value) {
                    updateMeta('_li_seo_robots', value);
                },
            }),
            el(
                'div',
                { className: 'lloyds-seo-document-panel__social' },
                el('h4', null, label('socialTitle', 'Social Title')),
                el(TextControl, {
                    label: label('socialTitle', 'Social Title'),
                    value: meta._li_seo_social_title || '',
                    onChange: function (value) {
                        updateMeta('_li_seo_social_title', value);
                    },
                }),
                el(TextareaControl, {
                    label: label('socialDescription', 'Social Description'),
                    value: meta._li_seo_social_description || '',
                    onChange: function (value) {
                        updateMeta('_li_seo_social_description', value);
                    },
                }),
                el(
                    'div',
                    { className: 'lloyds-seo-document-panel__media' },
                    el('strong', null, label('socialImage', 'Social Image')),
                    el('span', null, meta._li_seo_social_image_id ? '#' + meta._li_seo_social_image_id : label('noImage', 'No image selected')),
                    el(
                        'div',
                        { className: 'lloyds-seo-document-panel__actions' },
                        el(
                            Button,
                            {
                                isPrimary: true,
                                onClick: selectSocialImage,
                            },
                            label('selectImage', 'Select Image')
                        ),
                        meta._li_seo_social_image_id
                            ? el(
                                Button,
                                {
                                    isSecondary: true,
                                    isDestructive: true,
                                    onClick: function () {
                                        updateMeta('_li_seo_social_image_id', 0);
                                    },
                                },
                                label('removeImage', 'Remove')
                            )
                            : null
                    )
                )
            ),
            el(
                Notice,
                {
                    status: 'info',
                    isDismissible: false,
                },
                label('sidebarNote', 'SEO fields save with this content item.')
            )
        );
    }

    registerPlugin('lloyds-seo-document-panel', {
        render: SeoDocumentPanel,
        icon: 'search',
    });
})(window.wp);
