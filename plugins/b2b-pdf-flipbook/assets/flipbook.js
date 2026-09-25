(function () {
    function ready(callback) {
        if (document.readyState !== 'loading') {
            callback();
            return;
        }

        document.addEventListener('DOMContentLoaded', callback);
    }

    function button(root, selector) {
        return root.querySelector(selector);
    }

    function setDisabled(control, disabled) {
        if (control) {
            control.disabled = disabled;
        }
    }

    ready(function () {
        document.querySelectorAll('.b2b-flipbook').forEach(function (root) {
            var endpoint = root.dataset.flipbookEndpoint;
            var id = root.dataset.flipbookId;
            var nonce = root.dataset.flipbookNonce;
            var pdfUrl = root.dataset.pdfUrl;
            var stage = button(root, '[data-flipbook-stage]');
            var thumbs = button(root, '[data-flipbook-thumbs]');
            var status = button(root, '[data-flipbook-status]');
            var prev = button(root, '[data-flipbook-prev]');
            var next = button(root, '[data-flipbook-next]');
            var fullscreen = button(root, '[data-flipbook-fullscreen]');
            var pages = [];
            var current = 0;
            var turnDirection = 'forward';

            function update() {
                if (!pages.length) {
                    return;
                }

                var leftIndex = current;
                var rightIndex = current + 1;
                var spread = document.createElement('div');
                spread.className = 'b2b-flipbook__spread';

                [leftIndex, rightIndex].forEach(function (pageIndex) {
                    var page = document.createElement('figure');
                    page.className = 'b2b-flipbook__page';

                    if (pages[pageIndex]) {
                        var img = document.createElement('img');
                        img.src = pages[pageIndex];
                        img.alt = 'Page ' + (pageIndex + 1);
                        page.appendChild(img);

                        var caption = document.createElement('figcaption');
                        caption.textContent = pageIndex + 1;
                        page.appendChild(caption);
                    } else {
                        page.classList.add('is-empty');
                    }

                    spread.appendChild(page);
                });

                spread.classList.add('is-turning-' + turnDirection);
                stage.replaceChildren(spread);

                window.setTimeout(function () {
                    spread.classList.remove('is-turning-forward', 'is-turning-back');
                }, 420);

                status.textContent = 'Pages ' + (current + 1) + '-' + Math.min(current + 2, pages.length) + ' of ' + pages.length;
                setDisabled(prev, current === 0);
                setDisabled(next, current + 2 >= pages.length);

                thumbs.querySelectorAll('button').forEach(function (thumb, index) {
                    thumb.classList.toggle('is-active', index === current);
                });
            }

            function fallback(message) {
                var wrapper = document.createElement('div');
                wrapper.className = 'b2b-flipbook__fallback';

                var book = document.createElement('div');
                book.className = 'b2b-flipbook__fallback-book';

                var page = document.createElement('div');
                page.className = 'b2b-flipbook__fallback-page';

                var title = document.createElement('h3');
                title.textContent = 'Page rendering needs setup';
                page.appendChild(title);

                var copy = document.createElement('p');
                copy.textContent = message;
                page.appendChild(copy);

                if (pdfUrl) {
                    var link = document.createElement('a');
                    link.href = pdfUrl;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.textContent = 'Open PDF';
                    page.appendChild(link);
                }

                book.appendChild(page);
                wrapper.appendChild(book);
                stage.replaceChildren(wrapper);
                status.textContent = 'Renderer setup required';
                setDisabled(prev, true);
                setDisabled(next, true);
            }

            function buildThumbs() {
                thumbs.replaceChildren();
                pages.forEach(function (src, index) {
                    var thumb = document.createElement('button');
                    thumb.type = 'button';
                    thumb.style.backgroundImage = 'url("' + src + '")';
                    thumb.setAttribute('aria-label', 'Go to page ' + (index + 1));

                    thumb.addEventListener('click', function () {
                        turnDirection = index > current ? 'forward' : 'back';
                        current = index % 2 === 0 ? index : index - 1;
                        update();
                    });

                    thumbs.appendChild(thumb);
                });
            }

            setDisabled(prev, true);
            setDisabled(next, true);

            prev.addEventListener('click', function () {
                turnDirection = 'back';
                current = Math.max(0, current - 2);
                update();
            });

            next.addEventListener('click', function () {
                turnDirection = 'forward';
                current = Math.min(pages.length - 1, current + 2);
                update();
            });

            if (fullscreen) {
                fullscreen.addEventListener('click', function () {
                    if (!document.fullscreenElement && root.requestFullscreen) {
                        root.requestFullscreen();
                    } else if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                });
            }

            fetch(endpoint + '?action=b2b_flipbook_manifest&id=' + encodeURIComponent(id) + '&nonce=' + encodeURIComponent(nonce))
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload.success || !payload.data || !payload.data.rendered) {
                        fallback((payload.data && payload.data.message) || 'This PDF is available as a local file.');
                        return;
                    }

                    pages = payload.data.pages || [];

                    if (!pages.length) {
                        fallback('No rendered pages are available yet.');
                        return;
                    }

                    buildThumbs();
                    update();
                })
                .catch(function () {
                    fallback('The flipbook could not be loaded. The PDF is still available as a local file.');
                });
        });
    });
})();
