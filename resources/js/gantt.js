/*
| Arrastrar barras del Gantt.
|
| **Mejora progresiva, no requisito.** Sin JavaScript el Gantt se sigue leyendo,
| imprimiendo y navegando con teclado, y las fechas se editan en la vista Lista
| o en el detalle de la tarea. Este archivo solo agrega una forma más rápida de
| hacer lo mismo — nunca la única.
|
| Al soltar se envía un formulario normal: sin peticiones a mano, sin estado
| paralelo en el navegador y sin una pantalla que se quede diciendo algo distinto
| de lo que hay guardado.
*/

function initGantt() {
    const chart = document.querySelector('[data-gantt]');

    if (!chart) {
        return;
    }

    const pixelsPerDay = Number.parseFloat(chart.dataset.pixelsPerDay || '0');
    const form = document.querySelector('[data-gantt-move-form]');

    if (!pixelsPerDay || !form) {
        return;
    }

    const announce = document.querySelector('[data-gantt-live]');
    let drag = null;

    const daysMoved = (deltaX) => Math.round(deltaX / pixelsPerDay);

    chart.querySelectorAll('[data-task-bar]').forEach((bar) => {
        bar.addEventListener('pointerdown', (event) => {
            // Solo botón principal: el secundario abre el menú del navegador.
            if (event.button !== 0) {
                return;
            }

            drag = { bar, startX: event.clientX, id: bar.dataset.taskId };
            bar.setPointerCapture(event.pointerId);
            bar.classList.add('opacity-60');
        });

        bar.addEventListener('pointermove', (event) => {
            if (!drag || drag.bar !== bar) {
                return;
            }

            const days = daysMoved(event.clientX - drag.startX);

            // Se mueve el dibujo, no el dato: hasta soltar no se guarda nada.
            bar.setAttribute('transform', `translate(${days * pixelsPerDay}, 0)`);

            if (announce && days !== 0) {
                announce.textContent = `${bar.dataset.taskName}: ${days > 0 ? '+' : ''}${days}`;
            }
        });

        bar.addEventListener('pointerup', (event) => {
            if (!drag || drag.bar !== bar) {
                return;
            }

            const days = daysMoved(event.clientX - drag.startX);

            bar.classList.remove('opacity-60');
            bar.removeAttribute('transform');
            drag = null;

            if (days === 0) {
                return;
            }

            // Confirmación explícita: arrastrar sin querer es facilísimo, y
            // mover una tarea recalcula el proyecto entero.
            const message = form.dataset.confirm.replace(':days', String(days));

            if (!window.confirm(message)) {
                return;
            }

            form.querySelector('[name="task"]').value = drag?.id ?? bar.dataset.taskId;
            form.querySelector('[name="days"]').value = String(days);
            form.submit();
        });

        bar.addEventListener('pointercancel', () => {
            if (drag && drag.bar === bar) {
                bar.classList.remove('opacity-60');
                bar.removeAttribute('transform');
                drag = null;
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGantt);
} else {
    initGantt();
}
