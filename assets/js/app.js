if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js').catch(() => {
            // Registratie is optioneel: de app werkt ook prima zonder.
        });
    });
}

document.querySelectorAll('.tab-switch').forEach((tabSwitch) => {
    const buttons = tabSwitch.querySelectorAll('.tab-btn');
    const panels = tabSwitch.parentElement.querySelectorAll('.tab-panel');

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            buttons.forEach((b) => b.classList.toggle('active', b === button));
            panels.forEach((panel) => {
                panel.hidden = panel.id !== button.dataset.tabTarget;
            });
        });
    });
});

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    });
});

document.querySelectorAll('tr[data-href]').forEach((row) => {
    row.addEventListener('click', () => {
        window.location.href = row.dataset.href;
    });
});

document.querySelectorAll('.fab-button[data-toggle-target]').forEach((button) => {
    const panel = document.getElementById(button.dataset.toggleTarget);
    if (!panel) {
        return;
    }

    const sync = () => {
        button.textContent = panel.hidden ? '+' : '×';
        button.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
    };
    sync();

    button.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        sync();
        if (!panel.hidden) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
