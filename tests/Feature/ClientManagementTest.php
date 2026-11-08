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

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    private function admin(): User
    {
        return User::role(Role::SuperAdmin->value)->firstOrFail();
    }

    private function member(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole(Role::TeamMember->value);

        return $user;
    }

    public function test_the_clients_page_lists_clients_with_project_and_task_counts(): void
    {
        $client = Client::factory()->create(['name' => 'Acme']);
        $project = Project::factory()->create(['client_id' => $client->id]);
        Task::factory()->count(2)->create(['project_id' => $project->id]);

        $this->actingAs($this->admin())->get(route('clients.index'))
            ->assertOk()->assertSee('Acme')->assertSee('1 project')->assertSee('2 tasks');
    }

    public function test_a_manager_can_add_rename_and_remove_a_client_keeping_its_projects(): void
    {
        $admin = $this->admin();

        $component = Livewire::actingAs($admin)->test('clients.index')
            ->set('newName', 'Initech')->call('add')->assertHasNoErrors();
        $client = Client::where('name', 'Initech')->firstOrFail();

        $component->call('startEdit', $client->id)->set('editingName', 'Initech Ltd')->call('saveEdit')->assertHasNoErrors();
        $this->assertSame('Initech Ltd', $client->fresh()->name);

        $project = Project::factory()->create(['client_id' => $client->id]);
        $component->call('delete', $client->id);

        $this->assertSoftDeleted($client);
        $this->assertNull($project->fresh()->client_id);
    }

    public function test_renaming_to_an_existing_name_is_refused(): void
    {
        Client::factory()->create(['name' => 'Acme']);
        $other = Client::factory()->create(['name' => 'Globex']);

        Livewire::actingAs($this->admin())->test('clients.index')
            ->call('startEdit', $other->id)->set('editingName', 'Acme')->call('saveEdit')
            ->assertHasErrors('editingName');
    }

    public function test_someone_without_project_management_can_look_but_not_change_clients(): void
    {
        $client = Client::factory()->create(['name' => 'Acme']);

        $this->actingAs($this->member())->get(route('clients.index'))->assertOk()->assertSee('Acme');

        Livewire::actingAs($this->member())->test('clients.index')
            ->set('newName', 'Sneaky')->call('add')->assertForbidden();
        Livewire::actingAs($this->member())->test('clients.index')->call('delete', $client->id)->assertForbidden();

        $this->assertNull(Client::where('name', 'Sneaky')->first());
        $this->assertNotSoftDeleted($client);
    }
}
