(function () {
    'use strict';
    const config = JSON.parse(document.getElementById('salepro-mobile-config').textContent);
    const text = config.strings;
    let prompt;
    const installed = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    const buttons = () => Array.from(document.querySelectorAll('.sp-install'));
    if (!buttons().length) {
        const tools = document.createElement('div');
        tools.className = 'sp-desktop-tools sp-auth-install';
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'sp-install'; button.hidden = true;
        button.textContent = text.install;
        tools.append(button); document.body.append(tools);
    }
    const notice = (message, action) => {
        const box = document.createElement('div');
        box.className = 'sp-notice';
        box.setAttribute('role', 'status');
        const content = document.createElement('span');
        content.textContent = message;
        box.append(content);
        if (action) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = text.update_action;
            button.addEventListener('click', action);
            box.append(button);
        }
        document.body.append(box);
    };
    addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        prompt = event;
        if (!installed()) buttons().forEach(button => button.hidden = false);
    });
    addEventListener('appinstalled', () => { prompt = null; buttons().forEach(button => button.hidden = true); });
    buttons().forEach(button => button.addEventListener('click', async () => {
        if (prompt) {
            const current = prompt;
            prompt = null;
            await current.prompt();
            await current.userChoice;
            buttons().forEach(item => item.hidden = true);
        } else notice(text.ios_install);
    }));
    if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !installed()) buttons().forEach(button => button.hidden = false);
    if (!('serviceWorker' in navigator) || !isSecureContext) return;
    navigator.serviceWorker.register('/service-worker.js?build=' + config.build, {scope: '/', updateViaCache: 'none'})
        .then(registration => {
            let offeredWorker;
            const offerUpdate = () => {
                if (!registration.waiting || !navigator.serviceWorker.controller || offeredWorker === registration.waiting) return;
                offeredWorker = registration.waiting;
                notice(text.update, () => {
                    if (document.querySelector('form[data-sp-dirty="true"],form[data-sp-pending="true"]')) {
                        notice(text.unsaved);
                        return;
                    }
                    navigator.serviceWorker.addEventListener('controllerchange', () => location.reload(), {once: true});
                    registration.waiting.postMessage({type: 'ACTIVATE_UPDATE'});
                });
            };
            offerUpdate();
            registration.addEventListener('updatefound', () => {
                const worker = registration.installing;
                if (worker) worker.addEventListener('statechange', () => { if (worker.state === 'installed') offerUpdate(); });
            });
        }).catch(error => console.warn('SalePro installation support could not start:', error.message));
}());
