(function () {
    'use strict';

    if (window.__VoraWebChat) return;
    window.__VoraWebChat = true;

    var BASE_URL = window.VORA_WEBCHAT_URL || 'https://medser.voraadigital.com';
    var TENANT_SLUG = window.VORA_TENANT_SLUG || 'default';
    var PHONE_KEY = 'vora_webchat_phone';
    var NAME_KEY = 'vora_webchat_name';

    var state = {
        open: false,
        phone: localStorage.getItem(PHONE_KEY) || '',
        name: localStorage.getItem(NAME_KEY) || '',
        tenantId: null,
        tenantName: 'Atendimento',
        greeting: 'Ol\u00e1! Como podemos ajudar?',
        primaryColor: '#6366f1',
        enabled: false,
        ticketStatus: null,
        canSend: false,
        ticketId: null,
        messages: [],
        loading: false,
        sending: false,
        hasMore: true,
    };

    function css() {
        return '.vora-widget *{box-sizing:border-box;margin:0;padding:0}' +
            '.vora-widget{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:14px;line-height:1.5;color:#1f2937}' +
            '.vora-widget button{cursor:pointer;border:none;background:none;font:inherit}' +
            '.vora-toggle{position:fixed;bottom:20px;z-index:2147483646;width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.2);transition:transform .2s;color:#fff}' +
            '.vora-toggle:hover{transform:scale(1.1)}' +
            '.vora-toggle svg{width:26px;height:26px}' +
            '.vora-panel{position:fixed;z-index:2147483647;bottom:90px;width:380px;max-width:calc(100vw - 32px);height:560px;max-height:calc(100vh - 120px);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.18);display:flex;flex-direction:column;overflow:hidden;animation:voraFadeIn .2s ease;background:#fff}' +
            '.vora-header{padding:16px 20px;color:#fff;font-weight:600;font-size:16px;display:flex;align-items:center;justify-content:space-between}' +
            '.vora-close{color:rgba(255,255,255,.8);font-size:22px;line-height:1;padding:4px}' +
            '.vora-close:hover{color:#fff}' +
            '.vora-body{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:8px}' +
            '.vora-msg{max-width:85%;padding:10px 14px;border-radius:16px;word-wrap:break-word;line-height:1.45}' +
            '.vora-msg-inbound{background:#f3f4f6;color:#1f2937;align-self:flex-start;border-bottom-left-radius:4px}' +
            '.vora-msg-outbound{color:#fff;align-self:flex-end;border-bottom-right-radius:4px}' +
            '.vora-msg-time{font-size:10px;color:#9ca3af;margin-top:4px;text-align:right}' +
            '.vora-input-area{border-top:1px solid #e5e7eb;padding:12px 16px;display:flex;gap:8px;align-items:flex-end}' +
            '.vora-input{flex:1;border:1px solid #d1d5db;border-radius:20px;padding:10px 16px;outline:none;resize:none;font:inherit;font-size:14px;max-height:100px}' +
            '.vora-input:focus{border-color:var(--vora-primary)}' +
            '.vora-send{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0}' +
            '.vora-send:disabled{opacity:.5}' +
            '.vora-send svg{width:18px;height:18px}' +
            '.vora-loading{text-align:center;padding:20px;color:#9ca3af}' +
            '.vora-greeting{text-align:center;padding:16px;color:#6b7280;font-size:13px}' +
            '.vora-name-input{display:flex;flex-direction:column;gap:12px;padding:24px 16px;align-items:center}' +
            '.vora-name-input label{font-weight:500;font-size:15px}' +
            '.vora-name-input input{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:10px 14px;font:inherit;outline:none}' +
            '.vora-name-input input:focus{border-color:var(--vora-primary)}' +
            '.vora-name-input button{padding:10px 24px;border-radius:8px;color:#fff;font-weight:500}' +
            '@keyframes voraFadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}' +
            '@media(max-width:480px){.vora-panel{width:100vw;max-width:100vw;bottom:0;right:0!important;left:0!important;border-radius:12px 12px 0 0;max-height:calc(100vh - 80px)}.vora-toggle{bottom:12px}}';
    }

    function injectStyles() {
        var s = document.createElement('style');
        s.textContent = css();
        document.head.appendChild(s);
    }

    function iconClose() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    }

    function iconChat() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
    }

    function iconSend() {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
    }

    function escHtml(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function timeStr(d) {
        return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    function render() {
        var align = window.VORA_WIDGET_POSITION === 'left' ? 'left' : 'right';
        var sideProp = align === 'left' ? 'left' : 'right';
        var oppSide = align === 'left' ? 'right' : 'left';

        var html = '<div class="vora-widget" id="vora-widget-root">';

        // Toggle button
        html += '<button class="vora-toggle" id="vora-toggle" style="' + sideProp + ':20px;background:' + state.primaryColor + '">';
        if (state.open) {
            html += iconClose();
        } else {
            html += iconChat();
        }
        html += '</button>';

        if (state.open && state.enabled) {
            html += '<div class="vora-panel" id="vora-panel" style="' + sideProp + ':0">';
            html += '<div class="vora-header" style="background:' + state.primaryColor + '">';
            html += '<span>' + escHtml(state.tenantName) + '</span>';
            html += '<button class="vora-close" id="vora-close-btn">' + iconClose() + '</button>';
            html += '</div>';

            if (!state.name) {
                // Ask for name
                html += '<div class="vora-body" id="vora-body">';
                html += '<div class="vora-name-input">';
                html += '<label>Antes de come\u00e7ar, qual seu nome?</label>';
                html += '<input type="text" id="vora-name-input-field" placeholder="Seu nome" maxlength="100" value="' + escHtml(state.name || '') + '" />';
                html += '<button id="vora-name-start" style="background:' + state.primaryColor + '">Iniciar conversa</button>';
                html += '</div></div>';
            } else {
                // Messages area
                html += '<div class="vora-body" id="vora-body">';
                if (state.messages.length === 0) {
                    html += '<div class="vora-greeting">' + escHtml(state.greeting) + '</div>';
                }
                for (var i = 0; i < state.messages.length; i++) {
                    var m = state.messages[i];
                    var cls = m.direction === 'outbound' ? 'vora-msg-outbound' : 'vora-msg-inbound';
                    var time = m.created_at ? timeStr(new Date(m.created_at)) : '';
                    html += '<div class="vora-msg ' + cls + '" style="' + (m.direction === 'outbound' ? 'background:' + state.primaryColor : '') + '">';
                    html += '<div>' + escHtml(m.body || '') + '</div>';
                    if (time) html += '<div class="vora-msg-time">' + time + '</div>';
                    html += '</div>';
                }
                if (state.sending) {
                    html += '<div class="vora-loading">Enviando...</div>';
                }
                html += '</div>';

                // Input area
                if (state.canSend) {
                    html += '<div class="vora-input-area">';
                    html += '<textarea class="vora-input" id="vora-msg-input" placeholder="Digite sua mensagem..." rows="1" style="--vora-primary:' + state.primaryColor + '"></textarea>';
                    html += '<button class="vora-send" id="vora-send-btn" style="background:' + state.primaryColor + '">' + iconSend() + '</button>';
                    html += '</div>';
                } else if (state.ticketStatus === 'closed') {
                    html += '<div class="vora-greeting">Este atendimento foi encerrado. Envie uma nova mensagem para abrir outro.</div>';
                }
            }

            html += '</div>';
        }

        html += '</div>';

        // Replace or insert
        var existing = document.getElementById('vora-widget-root');
        if (existing) {
            existing.outerHTML = html;
        } else {
            var div = document.createElement('div');
            div.innerHTML = html;
            document.body.appendChild(div.firstElementChild);
        }

        // Bind events
        document.getElementById('vora-toggle').onclick = function () {
            state.open = !state.open;
            if (state.open && state.phone) {
                loadConfig();
            }
            render();
        };

        var closeBtn = document.getElementById('vora-close-btn');
        if (closeBtn) {
            closeBtn.onclick = function () {
                state.open = false;
                render();
            };
        }

        var nameField = document.getElementById('vora-name-input-field');
        var nameStart = document.getElementById('vora-name-start');
        if (nameStart && nameField) {
            var startChat = function () {
                var name = nameField.value.trim();
                if (name.length < 2) return;
                state.name = name;
                localStorage.setItem(NAME_KEY, name);
                if (!state.phone) {
                    state.phone = 'wc_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
                    localStorage.setItem(PHONE_KEY, state.phone);
                }
                loadHistory();
                render();
            };
            nameStart.onclick = startChat;
            nameField.onkeydown = function (e) {
                if (e.key === 'Enter') startChat();
            };
            nameField.focus();
        }

        var input = document.getElementById('vora-msg-input');
        var sendBtn = document.getElementById('vora-send-btn');
        if (sendBtn && input) {
            var doSend = function () {
                var text = input.value.trim();
                if (!text) return;
                sendMessage(text);
                input.value = '';
                input.style.height = 'auto';
            };
            sendBtn.onclick = doSend;
            input.onkeydown = function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    doSend();
                }
            };
            input.oninput = function () {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            };
            input.focus();

            // Scroll to bottom
            var body = document.getElementById('vora-body');
            if (body) body.scrollTop = body.scrollHeight;
        }

        if (!state.open && document.getElementById('vora-panel')) {
            document.getElementById('vora-panel').style.display = 'none';
        }
    }

    function loadConfig() {
        fetch(BASE_URL + '/api/v1/webchat/config?tenant=' + TENANT_SLUG)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                state.tenantId = d.tenant_id;
                state.tenantName = d.name || 'Atendimento';
                state.greeting = d.greeting || 'Ol\u00e1! Como podemos ajudar?';
                state.primaryColor = d.primary_color || '#6366f1';
                state.enabled = d.enabled;
                if (!state.phone && !state.name) {
                    render();
                } else if (state.phone) {
                    loadHistory();
                }
            })
            .catch(function () {});
    }

    function loadHistory() {
        if (!state.tenantId || !state.phone) return;
        state.loading = true;
        render();

        fetch(BASE_URL + '/api/v1/webchat/history', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tenant_id: state.tenantId, phone: state.phone }),
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                state.messages = d.data || [];
                state.ticketStatus = d.ticket_status;
                state.canSend = d.can_send !== false;
                state.ticketId = d.ticket_id;
                state.loading = false;
                render();
            })
            .catch(function () {
                state.loading = false;
                render();
            });
    }

    function sendMessage(text) {
        if (!state.tenantId || !state.phone || !state.name) return;
        state.sending = true;
        render();

        fetch(BASE_URL + '/api/v1/webchat/send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tenant_id: state.tenantId,
                phone: state.phone,
                name: state.name,
                message: text,
            }),
        })
            .then(function () {
                state.messages.push({
                    direction: 'outbound',
                    body: text,
                    created_at: new Date().toISOString(),
                });
                state.sending = false;
                // Poll for new messages after a short delay
                setTimeout(loadHistory, 500);
                render();
            })
            .catch(function () {
                state.sending = false;
                render();
            });
    }

    // Poll for new messages every 5 seconds when open
    setInterval(function () {
        if (state.open && state.phone && state.tenantId) {
            fetch(BASE_URL + '/api/v1/webchat/history', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tenant_id: state.tenantId, phone: state.phone }),
            })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var msgs = d.data || [];
                    if (msgs.length !== state.messages.length) {
                        state.messages = msgs;
                        state.ticketStatus = d.ticket_status;
                        state.canSend = d.can_send !== false;
                        state.ticketId = d.ticket_id;
                        render();
                    }
                })
                .catch(function () {});
        }
    }, 5000);

    function init() {
        injectStyles();
        state.open = false;
        render();
        loadConfig();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
