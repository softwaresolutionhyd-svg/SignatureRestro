/**
 * Shared notification tune presets (Web Audio — no MP3 files).
 * Used by Settings preview + stair-notifications.js
 */
(() => {
    const TUNES = [
        {
            id: 'soft_ding',
            label: 'Soft Ding',
            speed: 'slow',
            notes: [
                { f: 784, at: 0, dur: 0.35, peak: 0.22, type: 'sine' },
            ],
        },
        {
            id: 'soft_double',
            label: 'Soft Double',
            speed: 'slow',
            notes: [
                { f: 660, at: 0, dur: 0.28, peak: 0.2, type: 'sine' },
                { f: 880, at: 0.32, dur: 0.35, peak: 0.22, type: 'sine' },
            ],
        },
        {
            id: 'warm_chime',
            label: 'Warm Chime',
            speed: 'slow',
            notes: [
                { f: 523, at: 0, dur: 0.3, peak: 0.24, type: 'triangle' },
                { f: 659, at: 0.28, dur: 0.3, peak: 0.24, type: 'triangle' },
                { f: 784, at: 0.56, dur: 0.4, peak: 0.26, type: 'triangle' },
            ],
        },
        {
            id: 'doorbell',
            label: 'Doorbell',
            speed: 'slow',
            notes: [
                { f: 740, at: 0, dur: 0.4, peak: 0.28, type: 'sine' },
                { f: 554, at: 0.45, dur: 0.55, peak: 0.3, type: 'sine' },
            ],
        },
        {
            id: 'rising',
            label: 'Rising Notes',
            speed: 'medium',
            notes: [
                { f: 523, at: 0, dur: 0.18, peak: 0.28, type: 'triangle' },
                { f: 659, at: 0.16, dur: 0.18, peak: 0.3, type: 'triangle' },
                { f: 784, at: 0.32, dur: 0.18, peak: 0.32, type: 'triangle' },
                { f: 1047, at: 0.48, dur: 0.28, peak: 0.34, type: 'triangle' },
            ],
        },
        {
            id: 'chime_mid',
            label: 'Mid Chime',
            speed: 'medium',
            notes: [
                { f: 880, at: 0, dur: 0.16, peak: 0.35, type: 'sine' },
                { f: 1175, at: 0.14, dur: 0.22, peak: 0.32, type: 'sine' },
            ],
        },
        {
            id: 'chime_fast',
            label: 'Fast Alert (loud)',
            speed: 'fast',
            notes: [
                { f: 988, at: 0, dur: 0.16, peak: 0.52, type: 'square' },
                { f: 1319, at: 0.18, dur: 0.16, peak: 0.55, type: 'square' },
                { f: 1568, at: 0.36, dur: 0.22, peak: 0.5, type: 'square' },
            ],
        },
        {
            id: 'pulse',
            label: 'Quick Pulse',
            speed: 'fast',
            notes: [
                { f: 1100, at: 0, dur: 0.08, peak: 0.45, type: 'square' },
                { f: 1100, at: 0.12, dur: 0.08, peak: 0.45, type: 'square' },
                { f: 1100, at: 0.24, dur: 0.08, peak: 0.45, type: 'square' },
                { f: 1400, at: 0.38, dur: 0.12, peak: 0.5, type: 'square' },
            ],
        },
        {
            id: 'cash_bell',
            label: 'Cash Bell',
            speed: 'fast',
            notes: [
                { f: 1568, at: 0, dur: 0.12, peak: 0.48, type: 'square' },
                { f: 2093, at: 0.1, dur: 0.18, peak: 0.42, type: 'triangle' },
                { f: 1568, at: 0.28, dur: 0.2, peak: 0.4, type: 'square' },
            ],
        },
        {
            id: 'siren_short',
            label: 'Short Siren',
            speed: 'fast',
            notes: [
                { f: 700, at: 0, dur: 0.14, peak: 0.4, type: 'sawtooth' },
                { f: 1100, at: 0.12, dur: 0.14, peak: 0.42, type: 'sawtooth' },
                { f: 700, at: 0.24, dur: 0.14, peak: 0.4, type: 'sawtooth' },
                { f: 1100, at: 0.36, dur: 0.18, peak: 0.45, type: 'sawtooth' },
            ],
        },
    ];

    let sharedCtx = null;

    function ensureCtx() {
        if (sharedCtx) return sharedCtx;
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        sharedCtx = new Ctx();
        return sharedCtx;
    }

    function playNote(ctx, note, t0) {
        const osc = ctx.createOscillator();
        const g = ctx.createGain();
        osc.type = note.type || 'sine';
        osc.frequency.value = note.f;
        const start = t0 + (note.at || 0);
        const dur = note.dur || 0.2;
        const peak = Math.max(0.05, Math.min(0.7, note.peak || 0.3));
        g.gain.setValueAtTime(0.0001, start);
        g.gain.exponentialRampToValueAtTime(peak, start + 0.015);
        g.gain.exponentialRampToValueAtTime(peak * 0.65, start + dur * 0.55);
        g.gain.exponentialRampToValueAtTime(0.0001, start + dur);
        osc.connect(g);
        g.connect(ctx.destination);
        osc.start(start);
        osc.stop(start + dur + 0.04);
    }

    function getTune(id) {
        return TUNES.find((t) => t.id === id) || TUNES.find((t) => t.id === 'chime_fast') || TUNES[0];
    }

    function play(id) {
        try {
            const ctx = ensureCtx();
            if (!ctx) return false;
            if (ctx.state === 'suspended') ctx.resume();
            const tune = getTune(id);
            const t0 = ctx.currentTime;
            (tune.notes || []).forEach((n) => playNote(ctx, n, t0));
            return true;
        } catch (_) {
            return false;
        }
    }

    window.NotificationTunes = {
        list: TUNES,
        getTune,
        play,
        ensureCtx,
        defaultId: 'chime_fast',
    };
})();
