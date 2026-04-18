function animateCountUp(el, target, duration = 600) {
    const start = parseFloat(el.textContent.replace(/[^\d.-]/g, '')) || 0;
    const startTime = performance.now();
    const isInt = Number.isInteger(target);
    function update(t) {
        const p = Math.min((t - startTime) / duration, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = isInt ? Math.round(start + (target - start) * eased).toLocaleString('de-DE') : (start + (target - start) * eased).toFixed(1).replace('.', ',');
        if (p < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
}
function haptic(p = [10]) { if ('vibrate' in navigator) navigator.vibrate(p); }
