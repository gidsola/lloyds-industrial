(function () {
    function ready(callback) {
        if (document.readyState !== 'loading') {
            callback();
            return;
        }

        document.addEventListener('DOMContentLoaded', callback);
    }

    ready(function () {
        var selectButton = document.querySelector('[data-b2b-flipbook-select-pdf]');
        var removeButton = document.querySelector('[data-b2b-flipbook-remove-pdf]');
        var input = document.querySelector('[data-b2b-flipbook-pdf-id]');
        var label = document.querySelector('[data-b2b-flipbook-pdf-label]');
        var status = document.querySelector('[data-b2b-flipbook-pdf-status]');
        var openLink = document.querySelector('[data-b2b-flipbook-pdf-open]');

        if (!selectButton || !input) {
            return;
        }

        selectButton.addEventListener('click', function (event) {
            event.preventDefault();

            if (!window.wp || !window.wp.media) {
                window.alert('The WordPress media picker is not available on this screen. Please refresh the page and try again.');
                return;
            }

            var frame = window.wp.media({
                title: 'Select PDF',
                button: {
                    text: 'Use This PDF'
                },
                library: {
                    type: 'application/pdf'
                },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();

                input.value = attachment.id || '';

                if (label) {
                    label.textContent = attachment.filename || attachment.title || 'Selected PDF';
                }

                if (status) {
                    status.textContent = 'Ready';
                }

                if (openLink) {
                    openLink.href = attachment.url || '#';
                    openLink.hidden = !attachment.url;
                }

                if (removeButton) {
                    removeButton.hidden = false;
                }
            });

            frame.open();
        });

        if (removeButton) {
            removeButton.addEventListener('click', function (event) {
                event.preventDefault();
                input.value = '';

                if (label) {
                    label.textContent = 'No PDF selected';
                }

                if (status) {
                    status.textContent = 'Needs PDF';
                }

                if (openLink) {
                    openLink.href = '#';
                    openLink.hidden = true;
                }

                removeButton.hidden = true;
            });
        }
    });
})();
