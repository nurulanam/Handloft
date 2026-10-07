<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Project;
use App\Models\User;
use App\Services\TaskWorkflowService;
use App\Support\Picker;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PickerSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    private function user(string $name, Role $role, array $attributes = []): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active', ...$attributes]);
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * @return list<string>
     */
    private function labels(array $options): array
    {
        return array_column($options, 'label');
    }

    public function test_people_are_searched_by_name_email_or_department_and_capped(): void
    {
        $manager = $this->user('Rahim Manager', Role::Manager, ['department' => 'Operations']);
        $this->user('Karim Hasan', Role::TeamMember, ['email' => 'karim@example.test', 'department' => 'Design']);
        User::factory()->count(30)->create();

        $component = Livewire::actingAs($manager)->test('tasks.create');

        $this->assertSame(['Karim Hasan'], $this->labels($component->instance()->pickerOptions('people', 'karim')));
        $this->assertSame(['Karim Hasan'], $this->labels($component->instance()->pickerOptions('people', 'design')));
        $this->assertSame(['Karim Hasan'], $this->labels($component->instance()->pickerOptions('people', 'karim@example')));
        $this->assertCount(Picker::LIMIT, $component->instance()->pickerOptions('people'));
        $this->assertSame([], $component->instance()->pickerOptions('people', '%'));
    }

    public function test_projects_are_searched_by_name(): void
    {
        $manager = $this->user('Rahim Manager', Role::Manager);
        Project::create(['name' => 'Website Relaunch', 'created_by' => $manager->id, 'status' => ProjectStatus::Active]);
        Project::create(['name' => 'Mobile App', 'created_by' => $manager->id, 'status' => ProjectStatus::Active]);

        $options = Livewire::actingAs($manager)->test('projects.form')->instance()->pickerOptions('projects', 'web');

        $this->assertSame(['Website Relaunch'], $this->labels($options));
        $this->assertSame('Active', $options[0]['hint']);
    }

    public function test_tasks_are_searched_by_key_or_title_without_the_task_itself_and_only_among_visible_ones(): void
    {
        $manager = $this->user('Rahim Manager', Role::Manager);
        $karim = $this->user('Karim Hasan', Role::TeamMember);
        $nadia = $this->user('Nadia Rahman', Role::TeamMember);

        $workflow = app(TaskWorkflowService::class);
        $mine = $workflow->createTask(['title' => 'Renew SSL certificate'], $manager, $karim);
        $other = $workflow->createTask(['title' => 'Renew domain'], $manager, $nadia);

        // Karim only sees tasks he's involved in.
        $karimsSearch = Livewire::actingAs($karim)->test('tasks.show', ['task' => $mine])->instance();
        $this->assertSame([], $karimsSearch->pickerOptions('tasks', 'renew', $mine->id));
        $this->assertSame([$mine->task_key.' Renew SSL certificate'], $this->labels($karimsSearch->pickerOptions('tasks', 'renew')));

        // A manager sees everything, by title, key or bare number, minus the excluded task.
        $managersSearch = Livewire::actingAs($manager)->test('tasks.show', ['task' => $mine])->instance();
        $this->assertSame([$other->task_key.' Renew domain'], $this->labels($managersSearch->pickerOptions('tasks', 'renew', $mine->id)));
        $this->assertSame([$other->task_key.' Renew domain'], $this->labels($managersSearch->pickerOptions('tasks', $other->task_key)));
        $this->assertSame([$other->task_key.' Renew domain'], $this->labels($managersSearch->pickerOptions('tasks', (string) $other->id, $mine->id)));
    }

    public function test_the_task_page_no_longer_loads_every_person_and_task(): void
    {
        $manager = $this->user('Rahim Manager', Role::Manager);
        $karim = $this->user('Karim Hasan', Role::TeamMember);
        $this->user('Someone Unrelated', Role::TeamMember);

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Renew SSL certificate'], $manager, $karim);

        $this->actingAs($manager)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertSee('Karim Hasan')
            ->assertDontSee('Someone Unrelated');
    }
}
