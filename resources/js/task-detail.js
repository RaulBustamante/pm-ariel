/*
| Doble clic para abrir el detalle de una tarea.
|
| **Mejora progresiva, no requisito.** Toda pantalla que trae este atajo trae
| también un enlace normal al detalle: el doble clic no existe para quien navega
| con teclado ni para un lector de pantalla, así que nunca puede ser la única
| puerta. Este archivo solo agrega la forma rápida de cruzarla.
|
| Vive aparte del tablero y de la lista porque las dos lo necesitan igual, y
| tenerlo dos veces garantizaba que una de las dos se quedara atrás.
*/

function initTaskDetailShortcut() {
    document.querySelectorAll('[data-task-url]').forEach((element) => {
        element.addEventListener('dblclick', (event) => {
            // Si el doble clic cayó sobre algo que ya hace lo suyo, no se toca.
            // En la lista esto es lo que importa: dentro de un campo de texto el
            // doble clic selecciona una palabra, y robárselo haría imposible
            // corregir el nombre de una tarea.
            if (event.target.closest('a, button, input, select, textarea, label, [contenteditable]')) {
                return;
            }

            window.location.href = element.dataset.taskUrl;
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTaskDetailShortcut);
} else {
    initTaskDetailShortcut();
}
