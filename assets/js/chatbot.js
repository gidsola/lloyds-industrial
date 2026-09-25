(() => {
    const config = window.b2bChatbot || {};
    const settings = config.settings || {};
    const root = document.getElementById('b2b-chatbot-root');

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

    const isLoggedIn = () => Boolean(config.user?.loggedIn && config.user?.id);
    const userScope = isLoggedIn() ? `user-${config.user.id}` : 'guest';
    const sessionKey = `b2bChatbotSession:${userScope}`;
    let sessionId = isLoggedIn()
        ? (window.sessionStorage.getItem(sessionKey) || `li-chat-${Date.now()}-${Math.random().toString(16).slice(2)}`)
        : `li-chat-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    let history = [];

    if (isLoggedIn()) {
        window.sessionStorage.setItem(sessionKey, sessionId);
    }

    root.style.setProperty('--b2b-chatbot-accent', settings.accentColor || '#17443b');
    root.classList.add(`b2b-chatbot-root--${settings.position || 'bottom-right'}`);
    root.innerHTML = `
        <button class="b2b-chatbot-launcher" type="button" aria-expanded="false">
            <span class="b2b-chatbot-launcher__avatar">${escapeText(settings.avatarText || 'L')}</span>
            <span>${escapeText(settings.buttonLabel || 'Chat')}</span>
        </button>
        <section class="b2b-chatbot" hidden>
            <header class="b2b-chatbot__header">
                <div>
                    <span class="b2b-chatbot__avatar">${escapeText(settings.avatarText || 'L')}</span>
                    <strong>${escapeText(settings.title || 'B2B Assistant')}</strong>
                </div>
                <div class="b2b-chatbot__header-actions">
                    <button type="button" class="b2b-chatbot__new" aria-label="${escapeText(config.i18n?.newChat || 'New chat')}">${escapeText(config.i18n?.newChat || 'New')}</button>
                    <button type="button" class="b2b-chatbot__close" aria-label="${escapeText(config.i18n?.close || 'Close chat')}">&times;</button>
                </div>
            </header>
            <div class="b2b-chatbot__messages" role="log" aria-live="polite"></div>
            <form class="b2b-chatbot__form">
                <textarea rows="1" placeholder="${escapeText(settings.placeholder || '')}"></textarea>
                <button type="submit">${escapeText(config.i18n?.send || 'Send')}</button>
            </form>
        </section>
    `;

    const launcher = root.querySelector('.b2b-chatbot-launcher');
    const panel = root.querySelector('.b2b-chatbot');
    const newChatButton = root.querySelector('.b2b-chatbot__new');
    const closeButton = root.querySelector('.b2b-chatbot__close');
    const messages = root.querySelector('.b2b-chatbot__messages');
    const form = root.querySelector('.b2b-chatbot__form');
    const input = form.querySelector('textarea');

    const persistHistory = () => {
        history = history.slice(-16);

        if (!isLoggedIn()) {
            return;
        }

        const body = new URLSearchParams({
            action: 'li_chatbot_save_history',
            nonce: config.nonce,
            session_id: sessionId,
            history: JSON.stringify(history),
        });

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
        }).catch(() => {});
    };

    const rememberLoggedInSession = () => {
        if (!isLoggedIn()) {
            return;
        }

        window.sessionStorage.setItem(`b2bChatbotSession:user-${config.user.id}`, sessionId);
    };

    const clearServerHistory = () => {
        if (!isLoggedIn()) {
            return Promise.resolve();
        }

        const body = new URLSearchParams({
            action: 'li_chatbot_clear_history',
            nonce: config.nonce,
            session_id: sessionId,
        });

        return fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
        }).catch(() => {});
    };

    const scrollMessages = () => {
        messages.scrollTop = messages.scrollHeight;
    };

    const appendMessage = (role, text, actions = [], persist = true, sources = []) => {
        const item = document.createElement('div');
        item.className = `b2b-chatbot-message b2b-chatbot-message--${role}`;
        item.innerHTML = `<div class="b2b-chatbot-message__bubble">${escapeText(text).replace(/\n/g, '<br>')}</div>`;

        if (actions.length) {
            const actionList = document.createElement('div');
            actionList.className = 'b2b-chatbot-actions';

            actions.forEach((action) => {
                if (action.type === 'login') {
                    const login = document.createElement('form');
                    login.className = 'b2b-chatbot-login';
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
                    link.className = 'b2b-chatbot-action';
                    link.textContent = action.label || 'Open';
                    link.target = '_self';
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
            history.push({ role, content: text, actions, sources });
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
            history: JSON.stringify(history.slice(-12)),
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

            if (payload.data?.user) {
                config.user = payload.data.user;
                rememberLoggedInSession();
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
        thinking.className = 'b2b-chatbot-message b2b-chatbot-message--assistant b2b-chatbot-message--thinking';
        thinking.innerHTML = `<div class="b2b-chatbot-message__bubble">${escapeText(config.i18n?.thinking || 'Checking...')}</div>`;
        messages.append(thinking);
        scrollMessages();

        try {
            const payload = await requestChat(message);
            thinking.remove();

            if (!payload.success) {
                appendMessage('assistant', payload.data?.message || config.i18n?.error || 'Something went wrong.');
                return;
            }

            appendMessage('assistant', payload.data.message, payload.data.actions || [], true, payload.data.sources || []);
        } catch (error) {
            thinking.remove();
            appendMessage('assistant', config.i18n?.error || 'Something went wrong.');
        } finally {
            setBusy(false);
        }
    });

    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(132, input.scrollHeight)}px`;
    });

    launcher.addEventListener('click', () => setOpen(panel.hidden));
    closeButton.addEventListener('click', () => setOpen(false));
    newChatButton.addEventListener('click', async () => {
        await clearServerHistory();
        history = [];
        messages.innerHTML = '';
        sessionId = `li-chat-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        rememberLoggedInSession();

        if (settings.greeting) {
            appendMessage('assistant', settings.greeting, [], false);
        }

        input.value = '';
        input.style.height = '';
        input.focus();
        trackEvent('new_chat');
    });

    const hydrateHistory = async () => {
        if (!isLoggedIn()) {
            if (settings.greeting) {
                appendMessage('assistant', settings.greeting, [], false);
            }

            return;
        }

        try {
            const body = new URLSearchParams({
                action: 'li_chatbot_load_history',
                nonce: config.nonce,
                session_id: sessionId,
            });
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body,
            });
            const payload = await response.json();

            history = payload.success && Array.isArray(payload.data?.history)
                ? payload.data.history
                : [];
        } catch (error) {
            history = [];
        }

        if (!history.length && settings.greeting) {
            appendMessage('assistant', settings.greeting, [], false);
            return;
        }

        history.slice(-12).forEach((item) => appendMessage(item.role, item.content, item.actions || [], false));
    };

    hydrateHistory();
})();
