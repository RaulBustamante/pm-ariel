/*
| Marcar varias tareas de la lista para moverlas juntas.
|
| **Mejora progresiva, no requisito.** Sin JavaScript la selección múltiple ya
| funciona completa: son casillas normales y un `<select>` de destino, dentro de
| un formulario que se envía solo. Este archivo agrega las dos cosas que un
| navegador no da gratis —marcar todas de un tirón y saber cuántas van— y nada
| más. Por eso la casilla del encabezado nace con `hidden` en el HTML: si el
| archivo no carga, no queda a la vista una casilla que no obedece a nadie.
|
| No se toca el estado del servidor ni se envía nada a mano. Lo único que hace
| es mover las casillas que el usuario podría marcar una por una.
*/

function initTaskBulk() {
    const boxes = Array.from(document.querySelectorAll('[data-bulk-task]'));
    const all = document.querySelector('[data-bulk-all]');
    const count = document.querySelector('[data-bulk-count]');

    // Sin renglones que marcar no hay nada que coordinar. Pasa en un proyecto
    // vacío y a quien solo tiene permiso de ver: ahí las casillas no se dibujan.
    if (boxes.length === 0) {
        return;
    }

    const plural = count?.dataset.template ?? '';

    const refresh = () => {
        const marked = boxes.filter((box) => box.checked).length;

        if (all) {
            all.checked = marked === boxes.length;

            // Ni marcada ni vacía: algunas. Es el único estado de una casilla
            // que hay que pedir a mano, y sin él «marcar todas» miente cuando
            // el usuario ya destildó una.
            all.indeterminate = marked > 0 && marked < boxes.length;
        }

        if (count) {
            count.textContent = marked === 0 ? '' : plural.replace(':count', String(marked));
        }
    };

    if (all) {
        all.hidden = false;

        all.addEventListener('change', () => {
            boxes.forEach((box) => {
                box.checked = all.checked;
            });

            refresh();
        });
    }

    boxes.forEach((box) => {
        box.addEventListener('change', refresh);
    });

    // Marcar un rango con Shift, como en cualquier lista de archivos. Capturar
    // las subtareas de un paquete es marcar de la primera a la última, y
    // hacerlo clic por clic es exactamente el trabajo repetido del que esta
    // pantalla intenta salir.
    let lastClicked = null;

    boxes.forEach((box, index) => {
        box.addEventListener('click', (event) => {
            if (event.shiftKey && lastClicked !== null) {
                const [from, to] = [Math.min(lastClicked, index), Math.max(lastClicked, index)];

                for (let i = from; i <= to; i += 1) {
                    boxes[i].checked = box.checked;
                }

                refresh();
            }

            lastClicked = index;
        });
    });

    refresh();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTaskBulk);
} else {
    initTaskBulk();
}
