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

    document.querySelectorAll('[data-li-media-picker]').forEach(bindMediaPicker);
})();
