function showToast(message, type = 'info') {
    const container = document.getElementById('toasts');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'pf-toast' + (type === 'badge' ? ' pf-toast-badge' : type === 'xp' ? ' pf-toast-xp' : '');
    const icons = { fame: '🌟', shame: '😬', badge: '🏅', xp: '⚡', error: '❌', info: 'ℹ️', success: '✅' };
    toast.innerHTML = `<span>${icons[type] || '📢'} ${escapeHtml(message)}</span>`;
    container.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; toast.style.transition = 'all 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
}

function triggerConfetti() {
    const colors = ['#00ff88', '#00d4ff', '#ff6b35', '#ffd700', '#ff44ff', '#44ff44'];
    for (let i = 0; i < 50; i++) {
        const piece = document.createElement('div');
        piece.className = 'confetti-piece';
        piece.style.left = Math.random() * 100 + 'vw';
        piece.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        piece.style.animationDelay = Math.random() * 0.5 + 's';
        piece.style.animationDuration = (1.5 + Math.random()) + 's';
        piece.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
        piece.style.width = (4 + Math.random() * 8) + 'px';
        piece.style.height = (4 + Math.random() * 8) + 'px';
        document.body.appendChild(piece);
        setTimeout(() => piece.remove(), 3000);
    }
}

function escapeHtml(str) { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; }

async function apiCall(url, data = {}) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    try {
        const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(data) });
        return await r.json();
    } catch (err) { console.error('API Error:', err); return { error: 'Netzwerkfehler' }; }
}

function formatNumber(n) {
    if (typeof n !== 'number') n = parseFloat(n) || 0;
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 10000) return (n / 1000).toFixed(1) + 'k';
    if (Number.isInteger(n)) return n.toLocaleString('de-DE');
    return n.toLocaleString('de-DE', { maximumFractionDigits: 1 });
}

let touchStartY = 0;
document.addEventListener('touchstart', e => { if (window.scrollY === 0) touchStartY = e.touches[0].clientY; }, { passive: true });
document.addEventListener('touchend', e => { if (window.scrollY === 0 && e.changedTouches[0].clientY - touchStartY > 100) window.location.reload(); }, { passive: true });
