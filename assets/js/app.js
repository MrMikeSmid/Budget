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

// Zoekveld dat kindelementen met een data-filter-attribuut in het
// target-element toont/verbergt op basis van of hun data-filter-waarde de
// getypte tekst bevat. Gebruikt door het icoon-rooster op de
// icoon-koppelpagina (150+ opties, te veel om zonder zoeken te scannen).
document.querySelectorAll('input[data-filter-target]').forEach((input) => {
    const target = document.getElementById(input.dataset.filterTarget);
    if (!target) {
        return;
    }

    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        target.querySelectorAll('[data-filter]').forEach((el) => {
            el.hidden = query !== '' && !el.dataset.filter.includes(query);
        });
    });
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
