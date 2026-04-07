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
        const listEl = document.getElementById('event-chat-messages');
        const formEl = document.getElementById('event-chat-form');
        const inputEl = document.getElementById('event-chat-input');

        if (!eventId || !listEl || !formEl || !inputEl) {
            return;
        }

        let lastId = 0;
        let initialized = false;
        let audioUnlocked = false;
        let audioContext = null;
        let loading = false;

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

        function appendMessage(item) {
            const div = document.createElement('div');
            div.className = 'event-chat-message' + (item.is_mine ? ' is-mine' : '');

            div.innerHTML =
                '<div class="event-chat-meta">' +
                    '<strong>' + escapeHtml(item.author_name || item.user || 'uživatel') + '</strong>' +
                    '<span>' + escapeHtml(formatTime(item.created_at)) + '</span>' +
                '</div>' +
                '<div class="event-chat-text">' + escapeHtml(item.message) + '</div>';

            listEl.appendChild(div);
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

        async function loadMessages() {
            if (loading) {
                return;
            }

            if (document.visibilityState !== 'visible') {
                return;
            }

            loading = true;

            try {
                const url = new URL('/api/chat-list.php', window.location.origin);
                url.searchParams.set('event_id', String(eventId));

                if (lastId > 0) {
                    url.searchParams.set('after_id', String(lastId));
                }

                const response = await fetch(url.toString(), {
                    cache: 'no-store'
                });

                if (!response.ok) {
                    loading = false;
                    return;
                }

                const data = await response.json();

                if (!data.ok || !Array.isArray(data.items)) {
                    loading = false;
                    return;
                }

                let gotForeignNew = false;

                data.items.forEach(function (item) {
                    appendMessage(item);
                    lastId = Math.max(lastId, Number(item.id || 0));

                    if (initialized && !item.is_mine) {
                        gotForeignNew = true;
                    }
                });

                if (!initialized) {
                    scrollToBottom();
                } else if (data.items.length > 0) {
                    scrollToBottom();
                }

                if (gotForeignNew) {
                    beepMessage();
                    vibrateMessage();
                }

                if (data.items.length > 0 || !initialized) {
                    await markRead();
                }

                initialized = true;
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