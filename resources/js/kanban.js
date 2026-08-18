/*
| Arrastrar tarjetas del tablero, y abrir el detalle con doble clic.
|
| **Mejora progresiva, no requisito.** Sin JavaScript el tablero sigue completo:
| cada tarjeta trae sus botones para moverla a las otras dos columnas, el título
| es un enlace normal al detalle, y todo eso funciona con teclado y con lector de
| pantalla. Este archivo solo agrega una forma más rápida de hacer lo mismo —
| nunca la única. Por eso los botones no se esconden al cargar.
|
| Al soltar se envía un formulario normal, igual que en el Gantt: sin peticiones
| a mano, sin estado paralelo en el navegador y sin una pantalla que se quede
| diciendo algo distinto de lo que hay guardado.
*/

function initKanban() {
    const form = document.querySelector('[data-kanban-move-form]');
    const cards = document.querySelectorAll('[data-kanban-card]');

    if (cards.length === 0) {
        return;
    }

    // El doble clic vive aparte del arrastre: no necesita el formulario, así que
    // funciona incluso para quien solo puede ver el tablero.
    document.querySelectorAll('[data-task-url]').forEach((card) => {
        card.addEventListener('dblclick', (event) => {
            // Si el doble clic cayó sobre el enlace o sobre un botón, ese
            // elemento ya hace lo suyo y duplicarlo abriría dos veces.
            if (event.target.closest('a, button, input')) {
                return;
            }

            window.location.href = card.dataset.taskUrl;
        });
    });

    if (!form) {
        return;
    }

    const columns = document.querySelectorAll('[data-kanban-column]');
    const announce = document.querySelector('[data-kanban-live]');

    let dragging = null;

    const clearTargets = () => {
        columns.forEach((column) => column.classList.remove('ring-2', 'ring-hud-500', 'rounded-md'));
    };

    cards.forEach((card) => {
        card.setAttribute('draggable', 'true');
        card.classList.add('cursor-grab');

        card.addEventListener('dragstart', (event) => {
            dragging = card;
            card.classList.add('opacity-50');

            // Algunos navegadores no arrancan el arrastre sin datos asociados.
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.taskId);
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('opacity-50');
            clearTargets();
            dragging = null;
        });
    });

    columns.forEach((column) => {
        column.addEventListener('dragover', (event) => {
            if (!dragging) {
                return;
            }

            // Sin esto el navegador rechaza la zona y nunca llega el `drop`.
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            column.classList.add('ring-2', 'ring-hud-500', 'rounded-md');
        });

        column.addEventListener('dragleave', (event) => {
            // `dragleave` también salta al pasar por encima de una tarjeta de
            // adentro; sin esta comprobación la columna parpadea sin parar.
            if (!column.contains(event.relatedTarget)) {
                column.classList.remove('ring-2', 'ring-hud-500', 'rounded-md');
            }
        });

        column.addEventListener('drop', (event) => {
            event.preventDefault();
            clearTargets();

            if (!dragging) {
                return;
            }

            const card = dragging;
            const target = column.dataset.kanbanColumn;
            const origin = card.closest('[data-kanban-column]');

            dragging = null;
            card.classList.remove('opacity-50');

            // Soltar en la misma columna no es un cambio. Enviarlo igual gastaría
            // un recálculo del proyecto entero para dejar todo como estaba.
            if (origin === column) {
                return;
            }

            if (announce) {
                announce.textContent = `${card.dataset.taskName} → ${target}`;
            }

            // El molde trae `__TASK__` donde va el identificador. Un `0` de
            // relleno no serviria: la direccion sigue con `/move` despues, asi
            // que no hay forma de reconocerlo por la posicion.
            form.setAttribute('action', form.dataset.actionTemplate.replace('__TASK__', card.dataset.taskId));
            form.querySelector('[name="column"]').value = target;
            form.submit();
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKanban);
} else {
    initKanban();
}
