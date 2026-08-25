/*
| El selector de proyecto de la barra superior.
|
| El desplegable ya funciona sin esto: es un `<details>` con una lista de enlaces
| reales, y el teclado lo recorre con Tab. Lo que se agrega aquí es lo que una
| lista larga necesita para no ser un scroll a ciegas —filtrar por nombre o
| clave— y las dos cortesías que un `<details>` no trae: cerrarse con Esc y
| cerrarse cuando se hace clic afuera. Un panel abierto que se queda tapando
| media pantalla se siente roto aunque no lo esté.
|
| El filtro ignora acentos: quien busca «migracion» está buscando «Migración», y
| obligarlo a escribir la tilde es obligarlo a saber cómo lo capturó otro.
*/

function normalize(text) {
    return text
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function initProjectSwitcher() {
    const root = document.querySelector('[data-project-switcher]');

    if (!root) {
        return;
    }

    const filter = root.querySelector('[data-project-switcher-filter]');
    const empty = root.querySelector('[data-project-switcher-empty]');
    const items = Array.from(root.querySelectorAll('[data-project-switcher-item]')).map((element) => ({
        element,
        // Se normaliza una sola vez, al arrancar, y no en cada tecla.
        haystack: normalize(element.dataset.search ?? ''),
    }));

    function apply(term) {
        const needle = normalize(term.trim());
        let visible = 0;

        for (const item of items) {
            const matches = needle === '' || item.haystack.includes(needle);
            item.element.hidden = !matches;

            if (matches) {
                visible++;
            }
        }

        if (empty) {
            empty.hidden = visible > 0;
        }
    }

    if (filter) {
        filter.addEventListener('input', () => apply(filter.value));

        // Al abrir, el panel empieza limpio y con el cursor puesto: quien lo
        // abrió va a escribir el nombre, no a buscar el campo con el ratón.
        root.addEventListener('toggle', () => {
            if (!root.open) {
                return;
            }

            filter.value = '';
            apply('');
            filter.focus();
        });
    }

    root.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || !root.open) {
            return;
        }

        root.open = false;
        // El foco vuelve a donde estaba antes de abrir. Sin esto se queda en un
        // elemento que acaba de ocultarse y el siguiente Tab empieza de cero.
        root.querySelector('summary')?.focus();
    });

    document.addEventListener('click', (event) => {
        if (root.open && !root.contains(event.target)) {
            root.open = false;
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProjectSwitcher);
} else {
    initProjectSwitcher();
}
