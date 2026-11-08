<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
        $this->admin = User::role(Role::SuperAdmin->value)->firstOrFail();
    }

    public function test_a_project_can_be_created_with_an_existing_client(): void
    {
        $client = Client::factory()->create(['name' => 'Acme']);

        Livewire::actingAs($this->admin)->test('projects.form')
            ->set('name', 'Website')
            ->set('client_id', (string) $client->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($client->id, Project::where('name', 'Website')->firstOrFail()->client_id);
    }

    public function test_typing_a_new_client_creates_it_and_reuses_it_regardless_of_case(): void
    {
        Livewire::actingAs($this->admin)->test('projects.form')->set('name', 'One')->set('new_client', 'Globex Corp')->call('save');
        Livewire::actingAs($this->admin)->test('projects.form')->set('name', 'Two')->set('new_client', '  globex   corp ')->call('save');

        $this->assertSame(1, Client::count());
        $this->assertSame(Client::first()->id, Project::where('name', 'Two')->firstOrFail()->client_id);
    }

    public function test_the_task_list_filters_by_client_across_their_projects(): void
    {
        $acme = Client::factory()->create(['name' => 'Acme']);
        $globex = Client::factory()->create(['name' => 'Globex']);
        $acmeSite = Project::factory()->create(['client_id' => $acme->id]);
        $acmeApp = Project::factory()->create(['client_id' => $acme->id]);
        $globexSite = Project::factory()->create(['client_id' => $globex->id]);

        Task::factory()->create(['title' => 'Acme homepage', 'project_id' => $acmeSite->id]);
        Task::factory()->create(['title' => 'Acme mobile app', 'project_id' => $acmeApp->id]);
        Task::factory()->create(['title' => 'Globex homepage', 'project_id' => $globexSite->id]);
        Task::factory()->create(['title' => 'Internal chore', 'project_id' => null]);

        Livewire::actingAs($this->admin)->test('tasks.index', ['view' => 'list'])
            ->set('client_id', (string) $acme->id)
            ->assertSee('Acme homepage')
            ->assertSee('Acme mobile app')
            ->assertDontSee('Globex homepage')
            ->assertDontSee('Internal chore')
            ->set('client_id', '')
            ->assertSee('Globex homepage')
            ->assertSee('Internal chore');
    }

    public function test_the_board_filters_by_client_too(): void
    {
        $acme = Client::factory()->create();
        Task::factory()->create(['title' => 'Acme card', 'project_id' => Project::factory()->create(['client_id' => $acme->id])->id]);
        Task::factory()->create(['title' => 'Other card', 'project_id' => Project::factory()->create()->id]);

        Livewire::actingAs($this->admin)->test('tasks.index', ['view' => 'board'])
            ->set('client_id', (string) $acme->id)
            ->assertSee('Acme card')
            ->assertDontSee('Other card');
    }
}
