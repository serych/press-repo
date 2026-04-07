(function () {
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatTime(value) {
        return value || '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('[data-chat-event-id]');
        if (!root) {
            return;
        }

        const eventId = Number(root.dataset.chatEventId || 0);
        const historyMode = String(root.dataset.chatHistory || '0') === '1';
        const isMobile = window.matchMedia('(max-width: 900px)').matches;
        const compactMobile = isMobile && !historyMode;

        const listEl = document.getElementById('event-chat-messages');
        const formEl = document.getElementById('event-chat-form');
        const inputEl = document.getElementById('event-chat-input');

        if (!eventId || !listEl || !formEl || !inputEl) {
            return;
        }

        let lastId = 0;
        let initialized = false;
        let loading = false;
        let audioUnlocked = false;
        let audioContext = null;
        let allMessages = [];

        function refreshBadgeIfAvailable() {
            if (typeof window.refreshChatBadge === 'function') {
                window.refreshChatBadge();
            }
        }

        function unlockAudio() {
            if (audioUnlocked) {
                return;
            }

            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) {
                    return;
                }

                audioContext = new Ctx();

                if (audioContext.state === 'suspended') {
                    audioContext.resume();
                }

                audioUnlocked = true;
            } catch (e) {
                // ticho
            }
        }

        ['click', 'keydown', 'touchstart'].forEach(function (eventName) {
            document.addEventListener(eventName, unlockAudio, { once: true });
        });

        function beepMessage() {
            if (!audioUnlocked || !audioContext) {
                return;
            }

            try {
                const now = audioContext.currentTime;
                const osc = audioContext.createOscillator();
                const gain = audioContext.createGain();

                osc.type = 'sine';
                osc.frequency.value = 1046;

                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(0.12, now + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.22);

                osc.connect(gain);
                gain.connect(audioContext.destination);

                osc.start(now);
                osc.stop(now + 0.22);
            } catch (e) {
                // ticho
            }
        }

        function vibrateMessage() {
            if (navigator.vibrate) {
                navigator.vibrate(120);
            }
        }

        function renderMessages() {
            let items = allMessages;

            if (compactMobile) {
                items = allMessages.slice(-6);
            }

            listEl.innerHTML = items.map(function (item) {
                return '' +
                    '<div class="event-chat-message' + (item.is_mine ? ' is-mine' : '') + '">' +
                        '<div class="event-chat-meta">' +
                            '<strong>' + escapeHtml(item.author_name || item.user || 'uživatel') + '</strong>' +
                            '<span>' + escapeHtml(formatTime(item.created_at)) + '</span>' +
                        '</div>' +
                        '<div class="event-chat-text">' + escapeHtml(item.message) + '</div>' +
                    '</div>';
            }).join('');
        }

        function scrollToBottom() {
            listEl.scrollTop = listEl.scrollHeight;
        }

        async function markRead() {
            if (lastId <= 0) {
                return;
            }

            try {
                await fetch('/api/chat-mark-read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({
                        event_id: String(eventId),
                        last_id: String(lastId)
                    })
                });

                refreshBadgeIfAvailable();
            } catch (e) {
                // ticho
            }
        }

        async function loadInitialMessages() {
            const url = new URL('/api/chat-list.php', window.location.origin);
            url.searchParams.set('event_id', String(eventId));

            const response = await fetch(url.toString(), {
                cache: 'no-store'
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!data.ok || !Array.isArray(data.items)) {
                return;
            }

            allMessages = data.items.slice();
            lastId = Number(data.last_id || 0);

            renderMessages();
            scrollToBottom();
            await markRead();
            initialized = true;
        }

        async function loadNewMessages() {
            if (lastId <= 0) {
                return;
            }

            const url = new URL('/api/chat-list.php', window.location.origin);
            url.searchParams.set('event_id', String(eventId));
            url.searchParams.set('after_id', String(lastId));

            const response = await fetch(url.toString(), {
                cache: 'no-store'
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!data.ok || !Array.isArray(data.items)) {
                return;
            }

            if (data.items.length === 0) {
                return;
            }

            let gotForeignNew = false;

            data.items.forEach(function (item) {
                allMessages.push(item);
                lastId = Math.max(lastId, Number(item.id || 0));

                if (!item.is_mine) {
                    gotForeignNew = true;
                }
            });

            renderMessages();
            scrollToBottom();

            if (gotForeignNew) {
                beepMessage();
                vibrateMessage();
            }

            await markRead();
        }

        async function loadMessages() {
            if (loading) {
                return;
            }

            if (document.visibilityState !== 'visible') {
                return;
            }

            loading = true;

            try {
                if (!initialized) {
                    await loadInitialMessages();
                } else {
                    await loadNewMessages();
                }
            } catch (e) {
                // ticho
            }

            loading = false;
        }

        inputEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                formEl.requestSubmit();
            }
        });

        formEl.addEventListener('submit', async function (e) {
            e.preventDefault();

            const text = inputEl.value.trim();
            if (!text) {
                return;
            }

            try {
                const response = await fetch('/api/chat-send.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({
                        event_id: String(eventId),
                        message: text
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.ok) {
                    return;
                }

                inputEl.value = '';
                await loadMessages();
                refreshBadgeIfAvailable();
            } catch (e) {
                // ticho
            }
        });

        loadMessages();
        window.setInterval(loadMessages, 5000);

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                loadMessages();
            }
        });
    });
})();