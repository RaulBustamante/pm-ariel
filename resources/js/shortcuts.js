/*
| Atajos de teclado.
|
| **Nunca secuestran una tecla que el navegador o un campo ya usan.** Se ignoran
| mientras el foco está en un campo de texto — si escribir «g» en el nombre de
| una tarea saltara al Gantt, el atajo sería un sabotaje.
|
| Todos son de navegación, ninguno destruye nada. Un atajo que borra es un atajo
| que alguien va a oprimir sin querer.
*/

const NAV_KEYS = {
    d: 'dashboard',
    l: 'list',
    g: 'gantt',
    k: 'kanban',
    c: 'calendar',
    a: 'advisor',
};

function isTyping(target) {
    if (!target) {
        return false;
    }

    const tag = target.tagName;

    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable;
}

function initShortcuts() {
    const nav = document.querySelector('[data-shortcut-nav]');
    const sheet = document.querySelector('[data-shortcut-sheet]');

    document.addEventListener('keydown', (event) => {
        // Los modificadores son del navegador y del sistema, no nuestros.
        if (event.ctrlKey || event.metaKey || event.altKey || isTyping(event.target)) {
            return;
        }

        // La hoja de referencia: sin ella, un atajo es una función que solo
        // conoce quien la programó.
        if (event.key === '?' && sheet) {
            event.preventDefault();
            sheet.open = !sheet.open;

            if (sheet.open) {
                sheet.querySelector('summary')?.focus();
            }

            return;
        }

        if (event.key === 'Escape' && sheet?.open) {
            sheet.open = false;

            return;
        }

        const target = NAV_KEYS[event.key.toLowerCase()];

        if (!target || !nav) {
            return;
        }

        const link = nav.querySelector(`[data-shortcut-to="${target}"]`);

        if (link) {
            event.preventDefault();
            link.click();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initShortcuts);
} else {
    initShortcuts();
}
