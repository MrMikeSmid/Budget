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

// Een select waarvan de opties (optioneel) "data-linked" en andere
// data-* velden dragen: bij wijzigen wordt een target-element getoond/
// verborgen op basis van data-linked, en worden velden met een
// data-sync-field-attribuut binnen hetzelfde formulier gevuld vanuit de
// gelijknamige data-* van de gekozen optie (bijv. option[data-budgeted]
// vult input[data-sync-field="budgeted"]). Gebruikt op de kasstroompagina
// om begroot/status/terugkerend van een gekoppelde last/inkomst te tonen
// zodra die als bron gekozen wordt.
document.querySelectorAll('select[data-sync-target]').forEach((select) => {
    const target = document.getElementById(select.dataset.syncTarget);
    const form = select.closest('form');
    if (!form) {
        return;
    }

    const sync = () => {
        const option = select.selectedOptions[0];
        const show = !!(option && option.dataset.linked === '1');
        if (target) {
            target.hidden = !show;
        }
        form.querySelectorAll('[data-sync-field]').forEach((field) => {
            const value = option ? option.dataset[field.dataset.syncField] : undefined;
            if (value === undefined) {
                return;
            }
            if (field.type === 'checkbox') {
                field.checked = value === '1';
            } else {
                field.value = value;
            }
            field.dispatchEvent(new Event('change'));
        });
    };
    select.addEventListener('change', sync);
    sync();
});

document.querySelectorAll('tr[data-href]').forEach((row) => {
    row.addEventListener('click', () => {
        window.location.href = row.dataset.href;
    });
});

// Generieke toggle-knop: klappen een target-element open/dicht. De
// ronde "+"-FAB (add-form-panel) wisselt daarbij ook zijn "+"/"×"-teken;
// overige knoppen (bijv. "Filteren") laten hun eigen tekst met rust.
document.querySelectorAll('[data-toggle-target]').forEach((button) => {
    const panel = document.getElementById(button.dataset.toggleTarget);
    if (!panel) {
        return;
    }

    const isFab = button.classList.contains('fab-button');
    const sync = () => {
        if (isFab) {
            button.textContent = panel.hidden ? '+' : '×';
        }
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

// Toon/verberg-knop voor een password-veld (bijv. de Gemini API key), zodat
// je 'm kunt controleren zonder 'm permanent zichtbaar te maken.
document.querySelectorAll('[data-toggle-password]').forEach((button) => {
    const input = document.getElementById(button.dataset.togglePassword);
    if (!input) {
        return;
    }

    button.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Verbergen' : 'Tonen';
    });
});

// AI-advieskaart op het dashboard: haalt het advies asynchroon op (i.p.v.
// server-side gerenderd, zoals de rest van de app) omdat een Gemini-call
// een paar seconden kan duren — vandaar de laad-indicator. Cachet server-
// side per periode; de "↻"-knop dwingt een nieuwe aanroep af.
(() => {
    const card = document.getElementById('ai-advice-card');
    if (!card) {
        return;
    }

    const body = document.getElementById('ai-advice-body');
    const refreshButton = document.getElementById('ai-advice-refresh');
    const periodId = card.dataset.periodId;

    const renderLoading = () => {
        body.innerHTML = '';
        const p = document.createElement('p');
        p.className = 'text-muted';
        p.textContent = 'Advies wordt opgehaald…';
        body.appendChild(p);
    };

    const renderResult = (data) => {
        body.innerHTML = '';
        const p = document.createElement('p');
        if (data && data.ok) {
            p.textContent = data.text;
        } else {
            p.className = 'advice-error';
            p.textContent = (data && data.error) || 'Er ging iets mis bij het ophalen van het advies.';
        }
        body.appendChild(p);
    };

    const load = (refresh) => {
        renderLoading();
        const params = new URLSearchParams({ page: 'ai-advies', period: periodId });
        if (refresh) {
            params.set('refresh', '1');
        }
        fetch('index.php?' + params.toString())
            .then((response) => response.json())
            .then(renderResult)
            .catch(() => renderResult({ ok: false, error: 'Er ging iets mis bij het ophalen van het advies.' }));
    };

    load(false);

    if (refreshButton) {
        refreshButton.addEventListener('click', () => load(true));
    }
})();
