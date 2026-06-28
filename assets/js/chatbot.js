(() => {
    const config = window.lloydsChatbot || {};
    const settings = config.settings || {};
    const root = document.getElementById('lloyds-chatbot-root');

    if (!root || !config.ajaxUrl) {
        return;
    }

    const escapeText = (value) => String(value || '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[character]));

    const sessionKey = 'lloydsChatbotSession';
    const historyKey = 'lloydsChatbotHistory';
    const sessionId = window.localStorage.getItem(sessionKey) || `li-chat-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    let history = [];

    window.localStorage.setItem(sessionKey, sessionId);

    try {
        history = JSON.parse(window.localStorage.getItem(historyKey) || '[]');
    } catch (error) {
        history = [];
    }

    root.style.setProperty('--lloyds-chatbot-accent', settings.accentColor || '#17443b');
    root.classList.add(`lloyds-chatbot-root--${settings.position || 'bottom-right'}`);
    root.innerHTML = `
        <button class="lloyds-chatbot-launcher" type="button" aria-expanded="false">
            <span class="lloyds-chatbot-launcher__avatar">${escapeText(settings.avatarText || 'L')}</span>
            <span>${escapeText(settings.buttonLabel || 'Chat')}</span>
        </button>
        <section class="lloyds-chatbot" hidden>
            <header class="lloyds-chatbot__header">
                <div>
                    <span class="lloyds-chatbot__avatar">${escapeText(settings.avatarText || 'L')}</span>
                    <strong>${escapeText(settings.title || 'Lloyds Assistant')}</strong>
                </div>
                <button type="button" class="lloyds-chatbot__close" aria-label="${escapeText(config.i18n?.close || 'Close chat')}">&times;</button>
            </header>
            <div class="lloyds-chatbot__messages" role="log" aria-live="polite"></div>
            <form class="lloyds-chatbot__form">
                <textarea rows="1" placeholder="${escapeText(settings.placeholder || '')}"></textarea>
                <button type="submit">${escapeText(config.i18n?.send || 'Send')}</button>
            </form>
        </section>
    `;

    const launcher = root.querySelector('.lloyds-chatbot-launcher');
    const panel = root.querySelector('.lloyds-chatbot');
    const closeButton = root.querySelector('.lloyds-chatbot__close');
    const messages = root.querySelector('.lloyds-chatbot__messages');
    const form = root.querySelector('.lloyds-chatbot__form');
    const input = form.querySelector('textarea');

    const persistHistory = () => {
        window.localStorage.setItem(historyKey, JSON.stringify(history.slice(-12)));
    };

    const scrollMessages = () => {
        messages.scrollTop = messages.scrollHeight;
    };

    const appendMessage = (role, text, actions = [], persist = true) => {
        const item = document.createElement('div');
        item.className = `lloyds-chatbot-message lloyds-chatbot-message--${role}`;
        item.innerHTML = `<div class="lloyds-chatbot-message__bubble">${escapeText(text).replace(/\n/g, '<br>')}</div>`;

        if (actions.length) {
            const actionList = document.createElement('div');
            actionList.className = 'lloyds-chatbot-actions';

            actions.forEach((action) => {
                if (action.type === 'login') {
                    const login = document.createElement('form');
                    login.className = 'lloyds-chatbot-login';
                    login.innerHTML = `
                        <input type="text" name="username" placeholder="${escapeText(config.i18n?.username || 'Username or email')}" autocomplete="username">
                        <input type="password" name="password" placeholder="${escapeText(config.i18n?.password || 'Password')}" autocomplete="current-password">
                        <button type="submit">${escapeText(config.i18n?.login || 'Sign In')}</button>
                    `;
                    login.addEventListener('submit', handleLogin);
                    actionList.append(login);
                    return;
                }

                if (action.url) {
                    const link = document.createElement('a');
                    link.href = action.url;
                    link.className = 'lloyds-chatbot-action';
                    link.textContent = action.label || 'Open';
                    link.target = action.type === 'download' ? '_self' : '_blank';
                    link.rel = 'noopener';
                    link.addEventListener('click', () => {
                        trackEvent(action.type === 'download' ? 'download_click' : 'action_click', {
                            label: action.label || '',
                            url: action.url || '',
                        });
                    });
                    actionList.append(link);
                }
            });

            item.append(actionList);
        }

        messages.append(item);
        scrollMessages();

        if (persist && role !== 'system') {
            history.push({ role, content: text });
            persistHistory();
        }
    };

    const setOpen = (open) => {
        panel.hidden = !open;
        launcher.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            trackEvent('open');
            input.focus();
            scrollMessages();
        }
    };

    const setBusy = (busy) => {
        form.classList.toggle('is-busy', busy);
        input.disabled = busy;
        form.querySelector('button').disabled = busy;
    };

    const requestChat = async (message) => {
        const body = new URLSearchParams({
            action: 'li_chatbot_message',
            nonce: config.nonce,
            message,
            session_id: sessionId,
            history: JSON.stringify(history.slice(-8)),
        });

        const response = await fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
        });

        return response.json();
    };

    const trackEvent = (event, data = {}) => {
        const body = new URLSearchParams({
            action: 'li_chatbot_track_action',
            nonce: config.nonce,
            event,
            session_id: sessionId,
            label: data.label || '',
            url: data.url || '',
        });

        if (navigator.sendBeacon) {
            navigator.sendBeacon(config.ajaxUrl, body);
            return;
        }

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
        }).catch(() => {});
    };

    async function handleLogin(event) {
        event.preventDefault();

        const loginForm = event.currentTarget;
        const formData = new FormData(loginForm);
        const body = new URLSearchParams({
            action: 'li_chatbot_login',
            nonce: config.nonce,
            username: formData.get('username') || '',
            password: formData.get('password') || '',
            remember: '1',
        });

        loginForm.classList.add('is-busy');

        try {
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body,
            });
            const payload = await response.json();

            if (!payload.success) {
                appendMessage('assistant', payload.data?.message || config.i18n?.error || 'Login failed.');
                return;
            }

            appendMessage('assistant', payload.data.message);

            const lastUserMessage = [...history].reverse().find((item) => item.role === 'user')?.content;

            if (lastUserMessage) {
                const followup = await requestChat(lastUserMessage);

                if (followup.success) {
                    appendMessage('assistant', followup.data.message, followup.data.actions || []);
                }
            }
        } catch (error) {
            appendMessage('assistant', config.i18n?.error || 'Something went wrong.');
        } finally {
            loginForm.classList.remove('is-busy');
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const message = input.value.trim();

        if (!message) {
            return;
        }

        input.value = '';
        appendMessage('user', message);
        setBusy(true);

        const thinking = document.createElement('div');
        thinking.className = 'lloyds-chatbot-message lloyds-chatbot-message--assistant lloyds-chatbot-message--thinking';
        thinking.innerHTML = `<div class="lloyds-chatbot-message__bubble">${escapeText(config.i18n?.thinking || 'Checking...')}</div>`;
        messages.append(thinking);
        scrollMessages();

        try {
            const payload = await requestChat(message);
            thinking.remove();

            if (!payload.success) {
                appendMessage('assistant', payload.data?.message || config.i18n?.error || 'Something went wrong.');
                return;
            }

            appendMessage('assistant', payload.data.message, payload.data.actions || []);
        } catch (error) {
            thinking.remove();
            appendMessage('assistant', config.i18n?.error || 'Something went wrong.');
        } finally {
            setBusy(false);
        }
    });

    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(110, input.scrollHeight)}px`;
    });

    launcher.addEventListener('click', () => setOpen(panel.hidden));
    closeButton.addEventListener('click', () => setOpen(false));

    if (!history.length && settings.greeting) {
        appendMessage('assistant', settings.greeting);
    } else {
        history.slice(-8).forEach((item) => appendMessage(item.role, item.content, [], false));
    }
})();
