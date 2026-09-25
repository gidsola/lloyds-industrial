/**
 * B2B Industrial - Mega Menu Admin JavaScript
 * Handles the form-based mega menu builder interface
 */

(function($) {
    'use strict';

    // Main container selector
    var $container = $('#li_mega_menu_items');
    var $jsonField = $('#li_mega_menu_data_json');
    
    // Check if we're on the right page
    if (!$container.length || !$jsonField.length) {
        return;
    }

    // Initialize the mega menu builder
    function initMegaMenuBuilder() {
        // Make items sortable
        $container.sortable({
            handle: '.li-mega-menu-item-handle',
            placeholder: 'li-mega-menu-item-placeholder',
            forcePlaceholderSize: true,
            tolerance: 'pointer',
            update: function(event, ui) {
                updateJsonFromForm();
            }
        });

        // Also make child containers sortable
        $container.on('sortupdate', '.li-mega-menu-children', function() {
            updateJsonFromForm();
        });

        // Toggle item settings
        $container.on('click', '.li-mega-menu-item-toggle', function() {
            var $button = $(this);
            var $item = $(this).closest('.li-mega-menu-item-form');
            var $body = $item.children('.li-mega-menu-item-body');
            var $icon = $button.find('.dashicons');
            var showLabel = liMegaMenu.labels?.showSettings || 'Show item settings';
            var hideLabel = liMegaMenu.labels?.hideSettings || 'Hide item settings';
            
            if ($body.is(':visible')) {
                $body.hide();
                $icon.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
                $button.attr('aria-label', showLabel).attr('title', showLabel);
            } else {
                $body.show();
                $icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
                $button.attr('aria-label', hideLabel).attr('title', hideLabel);
            }
        });

        // Remove item
        $container.on('click', '.li-mega-menu-item-remove', function() {
            if (confirm(liMegaMenu.confirmations.removeItem)) {
                var $item = $(this).closest('.li-mega-menu-item-form');
                
                // Check if it's a child item
                if ($item.hasClass('li-mega-menu-item-form--child')) {
                    $item.remove();
                } else {
                    // For top-level items, remove all children too
                    $item.find('.li-mega-menu-children').remove();
                    $item.remove();
                }
                
                updateJsonFromForm();
            }
        });

        // Add new top-level item
        $('.li-mega-menu-add-item').on('click', function() {
            addNewItem();
        });

        // Add child item
        $container.on('click', '.li-mega-menu-add-child', function() {
            var $parent = $(this).closest('.li-mega-menu-item-form');
            var parentIndex = getItemIndex($parent);
            
            addNewChildItem($parent, parentIndex);
        });

        // Toggle featured panel
        $container.on('change', '.li-featured-enabled-toggle', function() {
            var targetId = $(this).data('target');
            if (targetId) {
                $('#' + targetId).toggle(this.checked);
            }
        });

        $container.on('click', '.li-mega-menu-inner-tab', function() {
            var $button = $(this);
            var $fields = $button.closest('.li-mega-menu-item-fields');

            activateMegaMenuItemTab($fields, $button.data('megaMenuInnerTab'));
        });

        // Reset to defaults
        $('.li-mega-menu-reset').on('click', function() {
            if (confirm(liMegaMenu.confirmations.reset)) {
                $container.html('');
                
                // Add default items
                $.each(liMegaMenu.defaultMenu, function(index, item) {
                    addNewItem(item, index);
                });
                
                updateJsonFromForm();
            }
        });

        // Initialize media pickers for dynamic content
        initDynamicMediaPickers();

        // Initialize color pickers for dynamic content
        initDynamicColorPickers();

        // Initialize inner tabs for expanded item settings
        initMegaMenuItemTabs($container);

        // Initial form to JSON sync
        updateJsonFromForm();

        // Form submission handler
        $('form').on('submit', function() {
            updateJsonFromForm();
        });
    }

    // Add a new top-level item
    function addNewItem(itemData, index) {
        var template = $('#li-mega-menu-item-template').html();
        
        // Replace placeholder with actual index
        var itemHtml = template.replace(/__INDEX__/g, index !== undefined ? index : getNextIndex());
        
        var $item = $(itemHtml).appendTo($container);
        
        // If we have item data, populate the form
        if (itemData) {
            populateItemForm($item, itemData);
        }
        
        // Initialize media pickers for this item
        initMediaPickerForElement($item);
        initColorPickersForElement($item);
        initMegaMenuItemTabs($item);
        
        // Initialize children if they exist
        if (itemData && itemData.children && itemData.children.length > 0) {
            var parentIndex = index !== undefined ? index : getItemIndex($item);
            $.each(itemData.children, function(childIndex, child) {
                addNewChildItem($item, parentIndex, child, childIndex);
            });
        }
        
        updateJsonFromForm();
    }

    // Add a new child item
    function addNewChildItem($parent, parentIndex, childData, childIndex) {
        var template = $('#li-mega-menu-child-template').html();
        
        // Replace placeholders
        var childHtml = template
            .replace(/__PARENT_INDEX__/g, parentIndex)
            .replace(/__INDEX__/g, childIndex !== undefined ? childIndex : 'new');
        
        var $childrenContainer = $parent.find('.li-mega-menu-children');
        if (!$childrenContainer.length) {
            // Create children container if it doesn't exist
            $childrenContainer = $('<div class="li-mega-menu-children" data-parent-index="' + parentIndex + '"></div>');
            var $group = $('<div class="li-mega-menu-field-group"><h5>Child Items</h5></div>');
            $group.append($childrenContainer).appendTo($parent.find('.li-mega-menu-item-fields'));
            initMegaMenuItemTabs($parent);
        }
        
        var $child = $(childHtml).appendTo($childrenContainer);
        
        // Make children sortable
        $childrenContainer.sortable({
            handle: '.li-mega-menu-item-handle',
            placeholder: 'li-mega-menu-item-placeholder',
            forcePlaceholderSize: true,
            tolerance: 'pointer',
            update: function() {
                updateJsonFromForm();
            }
        });
        
        // If we have child data, populate the form
        if (childData) {
            populateItemForm($child, childData);
        }
        
        // Initialize media pickers for this child
        initMediaPickerForElement($child);
        initColorPickersForElement($child);
        initMegaMenuItemTabs($child);
        
        updateJsonFromForm();
    }

    function initMegaMenuItemTabs($context) {
        $context.find('.li-mega-menu-item-fields').addBack('.li-mega-menu-item-fields').each(function() {
            var $fields = $(this);
            var $groups = $fields.children('.li-mega-menu-field-group');

            if (!$groups.length) {
                return;
            }

            var $existingTabs = $fields.children('.li-mega-menu-inner-tabs');
            var activeSlug = $existingTabs.find('.is-active').data('megaMenuInnerTab') || $groups.first().data('megaMenuInnerPanel');

            $existingTabs.remove();
            $groups.each(function(index) {
                var $group = $(this);
                var label = $.trim($group.children('h5').first().text()) || 'Section ' + (index + 1);
                var slug = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'section-' + index;

                $group.attr('data-mega-menu-inner-panel', slug);
            });

            if (!activeSlug || !$groups.filter('[data-mega-menu-inner-panel="' + activeSlug + '"]').length) {
                activeSlug = $groups.first().data('megaMenuInnerPanel');
            }

            var $tabs = $('<div class="li-mega-menu-inner-tabs" role="tablist"></div>');

            $groups.each(function(index) {
                var $group = $(this);
                var slug = $group.data('megaMenuInnerPanel');
                var label = $.trim($group.children('h5').first().text()) || 'Section ' + (index + 1);
                var $button = $('<button type="button" class="li-mega-menu-inner-tab" role="tab"></button>');

                $button.text(label).attr('data-mega-menu-inner-tab', slug);
                $tabs.append($button);
            });

            $fields.prepend($tabs);
            activateMegaMenuItemTab($fields, activeSlug);
        });
    }

    function activateMegaMenuItemTab($fields, slug) {
        $fields.children('.li-mega-menu-inner-tabs').find('.li-mega-menu-inner-tab').each(function() {
            var $button = $(this);
            var isActive = $button.data('megaMenuInnerTab') === slug;

            $button.toggleClass('is-active', isActive).attr('aria-selected', isActive ? 'true' : 'false');
        });

        $fields.children('.li-mega-menu-field-group').each(function() {
            var $group = $(this);
            $group.toggle($group.data('megaMenuInnerPanel') === slug);
        });
    }

    // Get the next available index
    function getNextIndex() {
        return $container.find('.li-mega-menu-item-form:not(.li-mega-menu-item-form--child)').length;
    }

    // Get the index of an item
    function getItemIndex($item) {
        if ($item.hasClass('li-mega-menu-item-form--child')) {
            // For child items
            var $parent = $item.parents('.li-mega-menu-item-form:not(.li-mega-menu-item-form--child):first');
            var parentIndex = $parent.index();
            var childIndex = $item.index();
            return parentIndex + '-' + childIndex;
        } else {
            // For top-level items
            return $item.index();
        }
    }

    // Populate a form with item data
    function populateItemForm($item, data) {
        // Set the data-index attribute
        $item.data('index', data.index || '');
        
        // Basic fields
        setFieldValue($item, 'label', data.label || '');
        setFieldValue($item, 'url', data.url || '');
        setFieldValue($item, 'icon', data.icon || '');
        setFieldValue($item, 'description', data.description || '');
        
        // Appearance fields
        setFieldValue($item, 'bg_color', data.bg_color || '');
        setFieldValue($item, 'text_color', data.text_color || '');
        setFieldValue($item, 'hover_color', data.hover_color || '');
        
        // Badge fields
        setFieldValue($item, 'badge', data.badge || '');
        setFieldValue($item, 'badge_color', data.badge_color || '#0066cc');
        setFieldValue($item, 'badge_text_color', data.badge_text_color || '#ffffff');
        
        // Layout fields
        setCheckboxValue($item, 'enabled', data.enabled !== false);
        setCheckboxValue($item, 'new_tab', data.new_tab || false);
        setCheckboxValue($item, 'mobile_visible', data.mobile_visible !== false);
        setFieldValue($item, 'mobile_order', data.mobile_order || 0);
        setFieldValue($item, 'column', data.column || 1);
        
        // Featured panel
        if (data.featured && data.featured.enabled) {
            var $featuredCheckbox = $item.find('input[name*="[featured][enabled]"]');
            $featuredCheckbox.prop('checked', true);
            
            var $panel = $item.find('.li-featured-panel-settings');
            if ($panel.length) {
                $panel.show();
                setFieldValue($item, 'featured_title', data.featured.title || '');
                setFieldValue($item, 'featured_text', data.featured.text || '');
                setFieldValue($item, 'featured_url', data.featured.url || '');
                setFieldValue($item, 'featured_button_label', data.featured.button_label || '');
                setFieldValue($item, 'featured_image', data.featured.image || '');
                
                // Update the media label for featured image
                var $mediaLabel = $item.find('[name*="[featured][image]"]').siblings('[data-li-media-label]');
                if ($mediaLabel.length && data.featured.image) {
                    updateMediaLabel($mediaLabel, data.featured.image);
                }
            }
        }
        
        // Update the title in the header
        var $title = $item.find('.li-mega-menu-item-title');
        if ($title.length && data.label) {
            $title.text(data.label);
        }
    }

    // Set field value for various input types
    function setFieldValue($item, fieldName, value) {
        // Try data-field attribute first
        var $field = $item.find('[data-field="' + fieldName + '"]');
        
        // Then try various name patterns
        if (!$field.length) {
            var namePatterns = [
                '[name="' + fieldName + '"]',
                '[name*="[' + fieldName + ']"]',
                '[name*="[' + fieldName + '_]"]',
                '[name*="' + fieldName + '"]'
            ];
            
            for (var i = 0; i < namePatterns.length; i++) {
                $field = $item.find(namePatterns[i]);
                if ($field.length) {
                    break;
                }
            }
        }
        
        if ($field.length) {
            if ($field.is('input') || $field.is('textarea') || $field.is('select')) {
                $field.val(value);
            }
        }
    }

    // Set checkbox value
    function setCheckboxValue($item, fieldName, checked) {
        // Try data-field attribute first
        var $field = $item.find('[data-field="' + fieldName + '"]');
        
        // Then try various name patterns
        if (!$field.length) {
            var namePatterns = [
                '[name="' + fieldName + '"]',
                '[name*="[' + fieldName + ']"]',
                '[name*="[' + fieldName + '_]"]',
                '[name*="' + fieldName + '"]'
            ];
            
            for (var i = 0; i < namePatterns.length; i++) {
                $field = $item.find(namePatterns[i]);
                if ($field.length) {
                    break;
                }
            }
        }
        
        if ($field.length) {
            $field.prop('checked', checked);
        }
    }

    // Update media picker label
    function updateMediaLabel($label, attachmentId) {
        if (attachmentId && window.wp && window.wp.media) {
            // Try to get attachment details
            var attachment = wp.media.attachment(attachmentId);
            attachment.fetch().done(function() {
                $label.text(attachment.attributes.filename || attachment.attributes.title || 'Attachment ' + attachmentId);
            }).fail(function() {
                $label.text('Attachment ' + attachmentId);
            });
        } else {
            $label.text(attachmentId ? 'Attachment ' + attachmentId : 'No image selected');
        }
    }

    // Collect form data and update JSON field
    function updateJsonFromForm() {
        var menuData = [];
        
        // Process top-level items
        $container.find('.li-mega-menu-item-form:not(.li-mega-menu-item-form--child)').each(function() {
            var $item = $(this);
            var itemData = collectItemData($item);
            
            // Process children
            var children = [];
            var $childrenContainer = $item.find('.li-mega-menu-children');
            if ($childrenContainer.length) {
                $childrenContainer.find('.li-mega-menu-item-form').each(function() {
                    var $child = $(this);
                    children.push(collectItemData($child, true));
                });
            }
            
            if (!children.length) {
                children = [];
            }
            
            itemData.children = children;
            menuData.push(itemData);
        });
        
        // Update the JSON field
        $jsonField.val(JSON.stringify(menuData, null, 2));
    }

    // Collect data from a single item form
    function collectItemData($item, isChild) {
        var data = {
            label: getFieldValue($item, 'label'),
            url: getFieldValue($item, 'url'),
            icon: getFieldValue($item, 'icon'),
            description: getFieldValue($item, 'description'),
            bg_color: getFieldValue($item, 'bg_color'),
            text_color: getFieldValue($item, 'text_color'),
            hover_color: getFieldValue($item, 'hover_color'),
            badge: getFieldValue($item, 'badge'),
            badge_color: getFieldValue($item, 'badge_color'),
            badge_text_color: getFieldValue($item, 'badge_text_color'),
            column: parseInt(getFieldValue($item, 'column')) || 1,
            enabled: getCheckboxValue($item, 'enabled'),
            new_tab: getCheckboxValue($item, 'new_tab'),
            mobile_order: parseInt(getFieldValue($item, 'mobile_order')) || 0,
            mobile_visible: getCheckboxValue($item, 'mobile_visible'),
            children: [],
        };
        
        // Featured panel (only for top-level items)
        if (!isChild) {
            var featuredEnabled = getCheckboxValue($item, 'featured-enabled') || getCheckboxValue($item, 'featured_enabled');
            if (featuredEnabled) {
                data.featured = {
                    enabled: true,
                    title: getFieldValue($item, 'featured-title') || getFieldValue($item, 'featured_title'),
                    text: getFieldValue($item, 'featured-text') || getFieldValue($item, 'featured_text'),
                    url: getFieldValue($item, 'featured-url') || getFieldValue($item, 'featured_url'),
                    button_label: getFieldValue($item, 'featured-button_label') || getFieldValue($item, 'featured_button_label'),
                    image: getFieldValue($item, 'featured-image') || getFieldValue($item, 'featured_image'),
                };
            } else {
                data.featured = { enabled: false };
            }
        } else {
            data.featured = { enabled: false };
        }
        
        // Remove empty values (except for boolean false and numeric 0)
        return cleanEmptyValues(data);
    }

    // Get field value
    function getFieldValue($item, fieldName) {
        // Try data-field attribute first
        var $field = $item.find('[data-field="' + fieldName + '"]');
        if ($field.length) {
            return $field.val();
        }
        
        // Then try various name patterns
        var namePatterns = [
            '[name="' + fieldName + '"]',
            '[name*="[' + fieldName + ']"]',
            '[name*="[' + fieldName + '_]"]',
            '[name*="' + fieldName + '"]'
        ];
        
        for (var i = 0; i < namePatterns.length; i++) {
            $field = $item.find(namePatterns[i]);
            if ($field.length) {
                return $field.val();
            }
        }
        
        return '';
    }

    // Get checkbox value
    function getCheckboxValue($item, fieldName) {
        // Try data-field attribute first
        var $field = $item.find('[data-field="' + fieldName + '"]');
        if ($field.length) {
            return $field.is(':checked');
        }
        
        // Then try various name patterns
        var namePatterns = [
            '[name="' + fieldName + '"]',
            '[name*="[' + fieldName + ']"]',
            '[name*="[' + fieldName + '_]"]',
            '[name*="' + fieldName + '"]'
        ];
        
        for (var i = 0; i < namePatterns.length; i++) {
            $field = $item.find(namePatterns[i]);
            if ($field.length) {
                return $field.is(':checked');
            }
        }
        
        return false;
    }

    // Clean empty values from object
    function cleanEmptyValues(obj) {
        if (Array.isArray(obj)) {
            return obj.map(cleanEmptyValues).filter(function(item) {
                return item !== null && typeof item === 'object';
            });
        } else if (typeof obj === 'object' && obj !== null) {
            var result = {};
            // Required keys that should always be present even if empty
            var requiredKeys = ['label', 'url', 'children', 'enabled', 'column', 'mobile_order', 'mobile_visible', 'featured'];
            
            for (var key in obj) {
                if (obj.hasOwnProperty(key)) {
                    var value = cleanEmptyValues(obj[key]);
                    // Always include required keys, even if empty
                    if (requiredKeys.indexOf(key) !== -1 || (value !== '' && value !== null && (typeof value !== 'object' || Object.keys(value).length > 0))) {
                        result[key] = value;
                    }
                }
            }
            return result;
        }
        return obj;
    }

    // Initialize media pickers for dynamic content
    function initDynamicMediaPickers() {
        // Bind media pickers for existing items
        $container.find('[data-li-media-picker]').each(function() {
            initMediaPickerForElement($(this));
        });
    }

    // Initialize media picker for a specific element
    function initMediaPickerForElement($element) {
        var container = $element.get(0) || $element.find('[data-li-media-picker]').get(0);
        if (container) {
            // Check if bindMediaPicker is available
            if (typeof bindMediaPicker === 'function') {
                bindMediaPicker(container);
            } else if (window.bindMediaPicker && typeof window.bindMediaPicker === 'function') {
                window.bindMediaPicker(container);
            } else {
                // Fallback: initialize basic media picker functionality
                initFallbackMediaPicker(container);
            }
        }
    }

    // Fallback media picker initialization
    function initFallbackMediaPicker(container) {
        var input = container.querySelector('[data-li-media-id]');
        var label = container.querySelector('[data-li-media-label]');
        var selectButton = container.querySelector('[data-li-media-select]');
        var removeButton = container.querySelector('[data-li-media-remove]');

        if (!input || !selectButton || !window.wp?.media) {
            return;
        }

        selectButton.addEventListener('click', function(event) {
            event.preventDefault();

            var mediaOptions = {
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

            var frame = window.wp.media(mediaOptions);

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first()?.toJSON();

                if (!attachment) {
                    return;
                }

                input.value = attachment.id || '';

                if (label) {
                    label.textContent = attachment.filename || attachment.title || 'Attachment ' + attachment.id;
                }

                if (removeButton) {
                    removeButton.hidden = false;
                }
            });

            frame.open();
        });

        if (removeButton) {
            removeButton.addEventListener('click', function(event) {
                event.preventDefault();

                input.value = '';

                if (label) {
                    label.textContent = 'No file selected';
                }

                removeButton.hidden = true;
            });
        }
    }

    // Initialize color pickers for dynamic content
    function initDynamicColorPickers() {
        $container.find('.li-color-picker').wpColorPicker();
    }

    // Initialize color pickers for a specific element
    function initColorPickersForElement($element) {
        $element.find('.li-color-picker').wpColorPicker();
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        initMegaMenuBuilder();
    });

})(jQuery);
