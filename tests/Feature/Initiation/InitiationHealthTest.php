<?php

declare(strict_types=1);

namespace Tests\Feature\Initiation;

use App\Models\Project;
use App\Models\ProjectCharter;
use App\Models\Risk;
use App\Models\Role;
use App\Models\Stakeholder;
use App\Models\User;
use App\Support\Initiation\Finding;
use App\Support\Initiation\InitiationHealth;
use App\Support\Initiation\InitiationStep;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El semáforo es la única opinión del sistema sobre si el inicio está sano.
 * Si se equivoca, tres pantallas y un PDF se equivocan con él.
 */
final class InitiationHealthTest extends TestCase
{
    use RefreshDatabase;

    private InitiationHealth $health;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->health = app(InitiationHealth::class);
    }

    private function project(): Project
    {
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $project = Project::query()->create([
            'code' => 'T-'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Proyecto',
            'owner_id' => $owner->id,
        ]);

        ProjectCharter::query()->create([
            'project_id' => $project->id,
            'current_step' => InitiationStep::Justification->value,
            'completed_steps' => [],
        ]);

        return $project->refresh();
    }

    /** Llena todo lo obligatorio, para poder quitar una cosa a la vez. */
    private function complete(Project $project): Project
    {
        $sponsor = User::factory()->create();

        $project->charter->update([
            'problem_statement' => 'Duele algo.',
            'expected_benefit' => 'Dejaría de doler.',
            'alignment' => 'Objetivo de eficiencia.',
            'objectives' => 'Que deje de doler.',
            'deliverables' => 'Una cosa entregada.',
            'success_criteria' => 'Ya no duele en marzo.',
            'sponsor_id' => $sponsor->id,
        ]);

        Stakeholder::query()->create([
            'project_id' => $project->id,
            'name' => 'Director',
            'power' => 5,
            'interest' => 5,
            'engagement_strategy' => 'Reportarle cada semana.',
        ]);

        foreach (range(1, 3) as $index) {
            Risk::query()->create([
                'project_id' => $project->id,
                'code' => sprintf('R-%02d', $index),
                'description' => "Riesgo {$index}",
                'probability' => 1,
                'impact' => 2,
                'owner_id' => $sponsor->id,
            ]);
        }

        return $project->refresh();
    }

    #[Test]
    public function an_empty_project_is_red_and_at_zero(): void
    {
        $project = $this->project();

        $this->assertSame('red', $this->health->light($project));
        $this->assertSame(0, $this->health->completion($project));
        $this->assertFalse($this->health->isComplete($project));
    }

    #[Test]
    public function a_fully_filled_project_is_green_and_complete(): void
    {
        $project = $this->complete($this->project());

        $this->assertSame('green', $this->health->light($project));
        $this->assertSame(100, $this->health->completion($project));
        $this->assertTrue($this->health->isComplete($project));
        $this->assertSame([], $this->health->findings($project));
    }

    /**
     * Ámbar y rojo no son lo mismo: uno se puede presentar y el otro no. Si el
     * semáforo los confundiera, el usuario aprendería a ignorarlo.
     */
    #[Test]
    public function a_missing_optional_field_is_amber_and_not_red(): void
    {
        $project = $this->complete($this->project());
        $project->charter->update(['alignment' => null]);

        $this->assertSame('amber', $this->health->light($project->refresh()));
        $this->assertTrue($this->health->isComplete($project));
        $this->assertSame(100, $this->health->completion($project));
    }

    #[Test]
    public function every_finding_explains_why_it_matters(): void
    {
        $findings = $this->health->findings($this->project());

        $this->assertNotEmpty($findings);

        foreach ($findings as $finding) {
            $this->assertNotSame('', trim($finding->message));
            $this->assertNotSame('', trim($finding->why));
            // Una clave sin traducir se ve como "initiation.algo": eso llega a la
            // pantalla y al PDF, así que se detiene aquí.
            $this->assertStringNotContainsString('initiation.', $finding->why);
        }
    }

    #[Test]
    public function a_high_risk_without_a_response_blocks(): void
    {
        $project = $this->complete($this->project());

        Risk::query()->create([
            'project_id' => $project->id,
            'code' => 'R-09',
            'description' => 'Se cae todo',
            'probability' => 5,
            'impact' => 5,
        ]);

        $findings = $this->health->findingsFor($project->refresh(), InitiationStep::Risks);

        $blocking = array_filter($findings, fn (Finding $f): bool => $f->isBlocking());

        $this->assertNotEmpty($blocking);
        $this->assertSame('red', $this->health->light($project));
    }

    #[Test]
    public function a_low_risk_without_a_response_does_not_block(): void
    {
        $project = $this->complete($this->project());

        Risk::query()->create([
            'project_id' => $project->id,
            'code' => 'R-09',
            'description' => 'Nimiedad',
            'probability' => 1,
            'impact' => 1,
        ]);

        $this->assertTrue($this->health->isComplete($project->refresh()));
    }

    #[Test]
    public function a_closed_risk_no_longer_demands_a_response(): void
    {
        $project = $this->complete($this->project());

        Risk::query()->create([
            'project_id' => $project->id,
            'code' => 'R-09',
            'description' => 'Ya pasó y se cerró',
            'probability' => 5,
            'impact' => 5,
            'status' => Risk::STATUS_CLOSED,
        ]);

        $this->assertTrue($this->health->isComplete($project->refresh()));
    }

    #[Test]
    public function fewer_than_three_risks_blocks(): void
    {
        $project = $this->complete($this->project());
        $project->risks()->limit(2)->get()->each->delete();

        $this->assertFalse($this->health->isComplete($project->refresh()));
    }
}
