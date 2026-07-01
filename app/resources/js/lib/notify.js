// Notificação de nova mensagem: som (Web Audio, sem arquivo), piscar o título da
// aba e notificação do navegador quando a aba não está focada.

let audioCtx = null;

function beep() {
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const o = audioCtx.createOscillator();
        const g = audioCtx.createGain();
        o.connect(g);
        g.connect(audioCtx.destination);
        o.type = 'sine';
        o.frequency.value = 880;
        const t = audioCtx.currentTime;
        g.gain.setValueAtTime(0.0001, t);
        g.gain.exponentialRampToValueAtTime(0.18, t + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t + 0.35);
        o.start(t);
        o.stop(t + 0.36);
    } catch (e) { /* silencioso */ }
}

let titleTimer = null;
let originalTitle = null;

function flashTitle(text) {
    if (document.hasFocus()) return; // só pisca se a aba não estiver em foco
    if (originalTitle === null) originalTitle = document.title;
    let on = false;
    clearInterval(titleTimer);
    titleTimer = setInterval(() => {
        document.title = on ? originalTitle : text;
        on = !on;
    }, 1000);
    const restore = () => {
        clearInterval(titleTimer);
        if (originalTitle !== null) document.title = originalTitle;
        originalTitle = null;
        window.removeEventListener('focus', restore);
    };
    window.addEventListener('focus', restore);
}

export function requestNotifyPermission() {
    try {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    } catch (e) { /* ignore */ }
}

export function notifyNewMessage(msg) {
    beep();
    flashTitle('🔴 Nova mensagem');
    try {
        if ('Notification' in window && Notification.permission === 'granted' && !document.hasFocus()) {
            const body = (msg?.body || 'Mídia recebida').toString().slice(0, 90);
            new Notification('Nova mensagem no atendimento', { body });
        }
    } catch (e) { /* ignore */ }
}
