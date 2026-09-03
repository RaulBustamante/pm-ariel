@props(['project', 'showCode' => false])

{{--
| De qué proyecto es esta tarea, en una lista que mezcla varios.
|
| El inicio tiene dos bloques que cruzan proyectos —«Mi semana» y las
| actividades del equipo— y los dos decían solo el código: «GP-06». El código
| es correlativo, así que identificaba el proyecto sin nombrarlo, y obligaba a
| recordar de memoria una tabla de equivalencias para leer la propia lista de
| pendientes.
|
| **El nombre siempre visible, el color para reconocerlo antes de leerlo.** No
| es al revés: un punto de color sin leyenda no dice nada la primera vez, y un
| color que solo se entiende pasando el ratón no existe en un celular ni para
| quien navega con teclado.
|
| El nombre se corta con puntos suspensivos cuando no cabe —las tarjetas del
| inicio son angostas y los nombres miden veinte o treinta caracteres— y el
| `title` guarda el completo. Ahí el color deja de ser un adorno y hace trabajo
| real: «Implementacion de ISO» e «Implementacion de IA» se cortan idénticos, y
| el tono es lo único que los separa de un vistazo.
--}}

@if ($project)
    <span {{ $attributes->merge(['class' => 'flex min-w-0 items-center gap-1']) }}>
        {{-- Decorativo: lo que este punto significa ya está escrito al lado, y
             repetirlo para un lector de pantalla solo sería ruido. --}}
        <span aria-hidden="true"
              class="size-1.5 shrink-0 rounded-full"
              style="background-color: {{ $project->swatch() }}"></span>

        <span class="truncate" title="{{ $project->name }}@if ($project->code) · {{ $project->code }}@endif">
            {{ $project->name }}
        </span>

        @if ($showCode && $project->code)
            <span class="shrink-0 font-mono opacity-70">{{ $project->code }}</span>
        @endif
    </span>
@endif
