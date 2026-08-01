<div id="pwa-install-banner" class="pwa-install-banner d-none" role="dialog" aria-live="polite">
    <div class="pwa-install-banner__inner">
        <div class="pwa-install-banner__text">
            <strong>{{ config('app.name', 'Stair') }}</strong>
            <span>{{ __('Install app for quick access') }}</span>
        </div>
        <div class="pwa-install-banner__actions">
            <button type="button" class="btn btn-sm btn-light" id="pwa-install-dismiss">{{ __('Not now') }}</button>
            <button type="button" class="btn btn-sm btn-primary" id="pwa-install-btn">{{ __('Install app') }}</button>
        </div>
    </div>
</div>

<style>
.pwa-install-banner {
    position: fixed;
    left: 12px;
    right: 12px;
    bottom: 12px;
    z-index: 1080;
    pointer-events: none;
}
.pwa-install-banner__inner {
    pointer-events: auto;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    border-radius: 14px;
    background: #1f1f1f;
    color: #fff;
    box-shadow: 0 10px 30px rgba(0,0,0,.28);
}
.pwa-install-banner__text {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.pwa-install-banner__text span {
    font-size: .85rem;
    opacity: .85;
}
.pwa-install-banner__actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}
@media (display-mode: standalone), (display-mode: fullscreen) {
    .pwa-install-banner { display: none !important; }
}
</style>

<script>
(function () {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    var dismissKey = 'pwa_install_dismissed_v1';
    var deferredPrompt = null;
    var banner = document.getElementById('pwa-install-banner');
    var installBtn = document.getElementById('pwa-install-btn');
    var dismissBtn = document.getElementById('pwa-install-dismiss');
    var secureOk = window.isSecureContext === true;

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
    }

    function isIos() {
        return /iphone|ipad|ipod/i.test(window.navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    function showBanner(mode) {
        if (!banner || isStandalone()) {
            return;
        }
        if (localStorage.getItem(dismissKey) === '1') {
            return;
        }
        var text = banner.querySelector('.pwa-install-banner__text span');
        if (mode === 'ios' && text) {
            text.textContent = 'Share → Add to Home Screen se install karein';
            if (installBtn) {
                installBtn.classList.add('d-none');
            }
        }
        banner.classList.remove('d-none');
    }

    function hideBanner() {
        if (banner) {
            banner.classList.add('d-none');
        }
    }

    if (secureOk) {
        navigator.serviceWorker.register(@json(asset('sw.js')), { scope: '/' }).catch(function () {});
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        if (installBtn) {
            installBtn.classList.remove('d-none');
        }
        showBanner('android');
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        hideBanner();
        try { localStorage.setItem(dismissKey, '1'); } catch (err) {}
    });

    if (installBtn) {
        installBtn.addEventListener('click', function () {
            if (!deferredPrompt) {
                return;
            }
            deferredPrompt.prompt();
            deferredPrompt.userChoice.finally(function () {
                deferredPrompt = null;
                hideBanner();
            });
        });
    }

    if (dismissBtn) {
        dismissBtn.addEventListener('click', function () {
            hideBanner();
            try { localStorage.setItem(dismissKey, '1'); } catch (err) {}
        });
    }

    // iOS has no beforeinstallprompt — show Add to Home Screen tip.
    setTimeout(function () {
        if (deferredPrompt || isStandalone() || !secureOk) {
            return;
        }
        if (isIos()) {
            showBanner('ios');
        }
    }, 2500);
})();
</script>
