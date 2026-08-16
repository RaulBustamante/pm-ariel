@props(['term', 'definition' => null, 'example' => null])

@php
    // Glosario contextual. La definición sale de lang/*/glossary.php, así que un
    // término nuevo se agrega ahí y aparece en toda la aplicación sin tocar
    // ninguna pantalla.
    $text = $definition ?? __("glossary.{$term}");
    $sample = $example ?? __("glossary.{$term}_example");
    $hasExample = $sample !== "glossary.{$term}_example";
    $id = 'term-'.$term.'-'.\Illuminate\Support\Str::random(4);
@endphp

<span class="relative inline-block">
    {{-- Un `details` y no una ventana emergente con JavaScript: funciona con
         teclado, con lector de pantalla y al imprimir, sin código propio. --}}
    <details class="inline">
        <summary class="inline cursor-help list-none rounded-full border border-slate-300 px-1.5 text-xs font-medium text-slate-600 hover:border-blue-600 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600"
                 aria-describedby="{{ $id }}">
            <span aria-hidden="true">?</span>
            <span class="sr-only">{{ __('glossary.what_is', ['term' => __("glossary.{$term}_label")]) }}</span>
        </summary>

        <span id="{{ $id }}"
              class="absolute left-0 top-6 z-20 block w-72 rounded-md border border-slate-200 bg-white p-3 text-left text-xs font-normal leading-relaxed text-slate-700 shadow-lg">
            <span class="block font-semibold text-slate-900">{{ __("glossary.{$term}_label") }}</span>
            <span class="mt-1 block">{{ $text }}</span>
            @if ($hasExample)
                <span class="mt-2 block border-t border-slate-100 pt-2 text-slate-600">{{ $sample }}</span>
            @endif
        </span>
    </details>
</span>
