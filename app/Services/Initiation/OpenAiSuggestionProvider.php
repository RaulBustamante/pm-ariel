<?php

declare(strict_types=1);

namespace App\Services\Initiation;

use App\Contracts\Initiation\SuggestsContent;
use App\Models\Project;
use App\Models\Stakeholder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Sugerencias redactadas por un modelo, con las plantillas debajo como red.
 *
 * Es un decorador, no un reemplazo: si no hay llave, si la petición falla, si
 * tarda demasiado o si vuelve algo que no se puede leer, responde la plantilla y
 * el usuario no se entera de que hubo un problema. Un asistente que se cae y
 * deja el formulario a medias es peor que uno que nunca existió.
 *
 * Nunca se llama solo. Cada método se dispara desde un botón que el usuario
 * oprime, y solo salen los campos de la lista blanca de `config/initiation.php`
 * — los datos de contacto de los interesados se quedan dentro.
 */
final class OpenAiSuggestionProvider implements SuggestsContent
{
    public function __construct(
        private readonly TemplateSuggestionProvider $fallback,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function suggestRisks(Project $project): array
    {
        $suggested = $this->ask($project, <<<'PROMPT'
        Propon riesgos realistas para este proyecto. Devuelve JSON:
        {"risks":[{"description":"...","cause":"...","effect":"...","category":"...","probability":1-5,"impact":1-5,"kind":"threat|opportunity"}]}
        Incluye al menos una oportunidad. Escribe en el idioma del proyecto.
        Se concreto: "el proveedor entrega tarde" no sirve; di que proveedor, de que, y por que.
        PROMPT, 'risks');

        return $suggested === [] ? $this->fallback->suggestRisks($project) : $suggested;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suggestStakeholders(Project $project): array
    {
        $suggested = $this->ask($project, <<<'PROMPT'
        Propon los interesados de este proyecto. Devuelve JSON:
        {"stakeholders":[{"name":"...","role_title":"...","organization":"...","power":1-5,"interest":1-5}]}
        Usa el puesto o el papel como nombre cuando no se sepa la persona
        (por ejemplo "Gerente de Compras"), nunca inventes nombres propios.
        PROMPT, 'stakeholders');

        return $suggested === [] ? $this->fallback->suggestStakeholders($project) : $suggested;
    }

    /**
     * @return list<string>
     */
    public function suggestDeliverables(Project $project): array
    {
        $suggested = $this->ask($project, <<<'PROMPT'
        Propon los entregables principales de este proyecto. Devuelve JSON:
        {"deliverables":["...","..."]}
        Entregables, no actividades: cosas que se pueden revisar y aprobar.
        PROMPT, 'deliverables');

        $strings = array_values(array_filter($suggested, is_string(...)));

        return $strings === [] ? $this->fallback->suggestDeliverables($project) : $strings;
    }

    /**
     * El cuadrante es aritmética y la estrategia sale de él. No hay nada que un
     * modelo pueda mejorar aquí, y sí mucho que puede empeorar.
     */
    public function suggestEngagementStrategy(Stakeholder $stakeholder): string
    {
        return $this->fallback->suggestEngagementStrategy($stakeholder);
    }

    public function suggestNarrative(Project $project, string $field): ?string
    {
        $label = __("initiation.field_{$field}");

        $result = $this->ask($project, <<<PROMPT
        Redacta un borrador del campo "{$label}" del acta constitutiva.
        Devuelve JSON: {"text":"..."}
        Dos o tres frases, en el idioma del proyecto, en voz llana y sin jerga.
        Usa unicamente lo que aparece en el contexto. Si el contexto no alcanza,
        devuelve {"text":""} en vez de inventar datos.
        PROMPT, 'text');

        $text = is_string($result) ? trim($result) : null;

        return $text !== null && $text !== ''
            ? $text
            : $this->fallback->suggestNarrative($project, $field);
    }

    public function isAvailable(Project $project): bool
    {
        return $this->configured() || $this->fallback->isAvailable($project);
    }

    private function configured(): bool
    {
        return (bool) config('initiation.ai.enabled') && filled(config('initiation.ai.key'));
    }

    /**
     * Devuelve la clave pedida del JSON, o `[]` / `null` si algo salió mal.
     *
     * @return mixed
     */
    private function ask(Project $project, string $instruction, string $key)
    {
        $empty = $key === 'text' ? null : [];

        if (! $this->configured() || ! $this->withinBudget($project)) {
            return $empty;
        }

        try {
            $response = Http::withToken((string) config('initiation.ai.key'))
                ->timeout((int) config('initiation.ai.timeout_seconds', 25))
                ->post((string) config('initiation.ai.endpoint'), [
                    'model' => (string) config('initiation.ai.model'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres un asistente de gestion de proyectos. Respondes solo JSON valido. '
                                .'No inventas datos que no esten en el contexto.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $instruction."\n\nContexto del proyecto:\n".$this->context($project),
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('Sugerencia de inicio rechazada por el proveedor.', [
                    'status' => $response->status(),
                    'project_id' => $project->id,
                ]);

                return $empty;
            }

            $content = $response->json('choices.0.message.content');

            if (! is_string($content)) {
                return $empty;
            }

            $decoded = json_decode($content, associative: true);

            if (! is_array($decoded) || ! array_key_exists($key, $decoded)) {
                return $empty;
            }

            $value = $decoded[$key];

            if ($key === 'text') {
                return is_string($value) ? $value : null;
            }

            return is_array($value)
                ? array_slice(array_values($value), 0, (int) config('initiation.ai.max_suggestions', 8))
                : $empty;
        } catch (ConnectionException) {
            // Sin internet o el proveedor no contesta. La plantilla responde.
            return $empty;
        } catch (Throwable $exception) {
            Log::warning('Falló la sugerencia de inicio.', [
                'project_id' => $project->id,
                'exception' => $exception->getMessage(),
            ]);

            return $empty;
        }
    }

    /**
     * ¿Le queda cuota a quien oprimió el botón?
     *
     * El tope se comprueba aquí y no en las rutas a propósito. Hay tres rutas de
     * sugerencia hoy y va a haber más; si el límite viviera en el archivo de
     * rutas, la cuarta nacería sin él y nadie lo notaría hasta el estado de
     * cuenta. Aquí es imposible llamar al proveedor sin pasar por el tope.
     *
     * Al agotarse no se lanza un error: devolver `false` hace que el decorador
     * responda con la plantilla, igual que cuando no hay internet. El usuario
     * ve un borrador razonable, no un formulario roto.
     */
    private function withinBudget(Project $project): bool
    {
        $user = auth()->id();

        // Sin sesión no hay a quién cobrarle la cuota —una orden de consola, una
        // prueba—, y tampoco hay un botón que alguien pueda mantener oprimido.
        if ($user === null) {
            return true;
        }

        $windows = [
            "initiation-ai:{$user}:minute" => [(int) config('initiation.ai.rate_limit.per_minute', 5), 60],
            "initiation-ai:{$user}:day" => [(int) config('initiation.ai.rate_limit.per_day', 100), 86400],
        ];

        foreach ($windows as $key => [$max, $seconds]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                Log::info('Sugerencia servida con plantilla: cuota de IA agotada.', [
                    'user_id' => $user,
                    'project_id' => $project->id,
                    'window_seconds' => $seconds,
                ]);

                return false;
            }
        }

        // Se marca sobre las dos ventanas solo cuando ambas dieron paso, para no
        // consumir la cuota diaria con intentos que la ventana del minuto ya
        // había rechazado.
        foreach ($windows as $key => [, $seconds]) {
            RateLimiter::hit($key, $seconds);
        }

        return true;
    }

    /**
     * Lo único que sale de la red de Ariel. La lista blanca vive en la
     * configuración para que se pueda auditar de un vistazo, sin leer código.
     */
    private function context(Project $project): string
    {
        $project->loadMissing('charter.template');
        $charter = $project->charter;

        $available = [
            'project.name' => $project->name,
            'project.description' => $project->description,
            'template.name' => $charter?->template?->name,
            'charter.problem_statement' => $charter?->problem_statement,
            'charter.opportunity' => $charter?->opportunity,
            'charter.expected_benefit' => $charter?->expected_benefit,
            'charter.objectives' => $charter?->objectives,
        ];

        /** @var list<string> $allowed */
        $allowed = config('initiation.ai_shared_fields', []);

        $lines = [];

        foreach ($allowed as $field) {
            $value = $available[$field] ?? null;

            if (filled($value)) {
                $lines[] = "{$field}: {$value}";
            }
        }

        return implode("\n", $lines);
    }
}
