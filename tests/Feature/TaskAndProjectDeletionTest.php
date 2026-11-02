<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTimeLog;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TaskAndProjectDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
        // Attachments live on the private disk; fake both so nothing touches real storage.
        Storage::fake('local');
        Storage::fake('public');
    }

    private function admin(): User
    {
        return User::where('email', 'admin@handloft.test')->firstOrFail();
    }

    private function teamMember(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::TeamMember->value);

        return $user;
    }

    private function manager(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::Manager->value);

        return $user;
    }

    /* ------------------------------------------------------------------ */
    /* Tasks */
    /* ------------------------------------------------------------------ */

    public function test_a_super_admin_can_delete_a_task_and_it_is_only_soft_deleted(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);
        $task->timeLogs()->create(['user_id' => $karim->id, 'logged_date' => today(), 'hours' => 2]);

        Livewire::actingAs($admin)
            ->test('tasks.show', ['task' => $task])
            ->set('delete_reason', 'Raised in error')
            ->call('deleteTask')
            ->assertRedirect(route('tasks.index'));

        // The row is still in the database, with everything attached to it.
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('task_time_logs', ['task_id' => $task->id, 'hours' => 2]);
        $this->assertDatabaseHas('task_assignments', ['task_id' => $task->id]);

        // …but it no longer appears in the app.
        $this->assertNull(Task::find($task->id));
        $this->assertSame(0, Task::query()->count());
    }

    public function test_deleting_a_task_requires_a_reason(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($admin)
            ->test('tasks.show', ['task' => $task])
            ->call('deleteTask')
            ->assertHasErrors('reason');

        $this->assertNotSoftDeleted($task);
    }

    public function test_deleting_a_task_also_soft_deletes_its_subtasks_and_comments(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);
        $subtask = app(TaskWorkflowService::class)->createTask(['title' => 'Check links', 'parent_task_id' => $task->id], $rahim, $karim);
        $comment = $task->comments()->create(['user_id' => $karim->id, 'body' => 'On it.']);

        Livewire::actingAs($admin)
            ->test('tasks.show', ['task' => $task])
            ->set('delete_reason', 'Duplicate')
            ->call('deleteTask');

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertSoftDeleted('tasks', ['id' => $subtask->id]);
        $this->assertSoftDeleted('task_comments', ['id' => $comment->id]);
    }

    public function test_deleting_a_task_leaves_its_activity_timeline_in_the_database(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);
        $activityCount = $task->activities()->count();

        Livewire::actingAs($admin)
            ->test('tasks.show', ['task' => $task])
            ->set('delete_reason', 'Not needed')
            ->call('deleteTask');

        // Created, Assigned, and the "Task deleted" entry, all still there for the audit trail.
        $this->assertSame($activityCount + 1, $task->activities()->count());
        $this->assertTrue($task->activities()->where('description', 'like', '%reason: Not needed%')->exists());
    }

    public function test_deleting_a_task_stops_any_running_work_timer(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($karim)->test('tasks.show', ['task' => $task])->call('startTimer');

        $this->assertDatabaseCount('task_timers', 1);

        Livewire::actingAs($admin)
            ->test('tasks.show', ['task' => $task])
            ->set('delete_reason', 'Dropped')
            ->call('deleteTask');

        $this->assertDatabaseCount('task_timers', 0);
    }

    public function test_a_team_member_cannot_delete_a_task_they_can_edit(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('deleteTask')
            ->assertForbidden();

        $this->assertNotSoftDeleted($task);
    }

    public function test_the_delete_button_is_only_shown_to_super_admins(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($admin)->test('tasks.show', ['task' => $task])->assertSee('Delete task');
        Livewire::actingAs($karim)->test('tasks.show', ['task' => $task])->assertDontSee('Delete task');
    }

    public function test_a_deleted_task_is_hidden_from_the_task_list(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($admin)
            ->test('tasks.show', ['task' => $task])
            ->set('delete_reason', 'Dropped')
            ->call('deleteTask');

        $this->actingAs($admin)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertDontSee('Website Audit');
    }

    /* ------------------------------------------------------------------ */
    /* Task title */
    /* ------------------------------------------------------------------ */

    public function test_the_assignee_can_rename_a_task_inline(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('startEditTitle')
            ->assertSet('editingTitle', true)
            ->assertSet('titleValue', 'Website Audit')
            ->set('titleValue', 'Website access audit')
            ->call('saveTitle')
            ->assertSet('editingTitle', false);

        $this->assertSame('Website access audit', $task->fresh()->title);
        $this->assertTrue($task->activities()->where('description', 'like', 'Title changed to%')->exists());
    }

    public function test_a_task_title_cannot_be_cleared(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('startEditTitle')
            ->set('titleValue', '')
            ->call('saveTitle')
            ->assertHasErrors('titleValue');

        $this->assertSame('Website Audit', $task->fresh()->title);
    }

    public function test_an_unrelated_team_member_cannot_rename_a_task(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');

        // Hasan can see this task (he reviews it) but it isn't his to edit.
        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit', 'qa_id' => $hasan->id], $rahim, $karim);

        Livewire::actingAs($hasan)
            ->test('tasks.show', ['task' => $task])
            ->call('startEditTitle')
            ->assertForbidden();

        $this->assertSame('Website Audit', $task->fresh()->title);
    }

    /* ------------------------------------------------------------------ */
    /* Comments */
    /* ------------------------------------------------------------------ */

    public function test_the_author_can_edit_their_own_comment(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);
        $comment = $task->comments()->create(['user_id' => $karim->id, 'body' => 'On it.']);

        // A beat later, so the edit lands in a different second than the original.
        $this->travel(1)->minutes();

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('startEditComment', $comment->id)
            ->assertSet('editingCommentId', $comment->id)
            ->set('editingCommentBody', 'On it — starting now.')
            ->call('saveComment')
            ->assertSet('editingCommentId', null);

        $this->assertSame('On it — starting now.', $comment->fresh()->body);
        $this->assertTrue($comment->fresh()->isEdited());
    }

    public function test_someone_else_cannot_edit_a_comment(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);
        $comment = $task->comments()->create(['user_id' => $karim->id, 'body' => 'On it.']);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->call('startEditComment', $comment->id)
            ->assertForbidden();

        $this->assertSame('On it.', $comment->fresh()->body);
    }

    public function test_a_comment_cannot_be_edited_to_be_empty(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);
        $comment = $task->comments()->create(['user_id' => $karim->id, 'body' => 'On it.']);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('startEditComment', $comment->id)
            ->set('editingCommentBody', '')
            ->call('saveComment')
            ->assertHasErrors('editingCommentBody');

        $this->assertSame('On it.', $comment->fresh()->body);
    }

    public function test_the_author_can_delete_their_comment_and_it_is_only_soft_deleted(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);
        $comment = $task->comments()->create(['user_id' => $karim->id, 'body' => 'On it.']);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('deleteComment', $comment->id);

        $this->assertSoftDeleted('task_comments', ['id' => $comment->id]);
        $this->assertSame(0, $task->comments()->count());
    }

    public function test_someone_else_cannot_delete_a_comment(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);
        $comment = $task->comments()->create(['user_id' => $karim->id, 'body' => 'On it.']);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->call('deleteComment', $comment->id)
            ->assertForbidden();

        $this->assertNotSoftDeleted($comment);
    }

    public function test_a_comment_from_another_task_cannot_be_reached_by_id(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);
        $other = $workflow->createTask(['title' => 'Other task'], $rahim, $karim);

        $foreign = $other->comments()->create(['user_id' => $karim->id, 'body' => 'Elsewhere.']);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('deleteComment', $foreign->id)
            ->assertStatus(404);

        $this->assertNotSoftDeleted($foreign);
    }

    public function test_a_comment_attachment_file_survives_deleting_the_comment(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('newComment', 'Notes attached.')
            ->set('commentAttachments', [UploadedFile::fake()->image('notes.png')])
            ->call('addComment');

        $comment = $task->comments()->firstOrFail();
        $attachment = $comment->attachments()->firstOrFail();

        Storage::disk('local')->assertExists($attachment->path);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('deleteComment', $comment->id);

        // The comment is hidden and the file is no longer servable, but nothing was deleted.
        $this->assertSoftDeleted('task_comments', ['id' => $comment->id]);
        $this->assertDatabaseHas('task_comment_attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($karim)
            ->get(route('attachments.show', ['kind' => 'comment', 'id' => $attachment->id]))
            ->assertNotFound();
    }

    /* ------------------------------------------------------------------ */
    /* Projects */
    /* ------------------------------------------------------------------ */

    public function test_a_super_admin_can_delete_a_project_and_it_is_only_soft_deleted(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['name' => 'Website Relaunch', 'created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('projects.show', ['project' => $project])
            ->assertSee('title="Delete project"', false)
            ->set('delete_reason', 'Cancelled by the client')
            ->call('deleteProject')
            ->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->assertNull(Project::find($project->id));
    }

    public function test_deleting_a_project_keeps_its_tasks_but_detaches_them(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $project = Project::factory()->create(['name' => 'Website Relaunch', 'created_by' => $admin->id]);
        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit', 'project_id' => $project->id], $rahim, $karim);

        Livewire::actingAs($admin)
            ->test('projects.show', ['project' => $project])
            ->set('delete_reason', 'Shelved')
            ->call('deleteProject');

        $task->refresh();

        // The task survives, it just no longer belongs to the deleted project.
        $this->assertNotSoftDeleted($task);
        $this->assertNull($task->project_id);
        $this->assertTrue($task->activities()->where('description', 'like', '%Shelved%')->exists());
    }

    public function test_deleting_a_project_requires_a_reason(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('projects.show', ['project' => $project])
            ->call('deleteProject')
            ->assertHasErrors('reason');

        $this->assertNotSoftDeleted($project);
    }

    public function test_a_manager_cannot_delete_a_project(): void
    {
        $manager = $this->manager('Rahim');
        $project = Project::factory()->create(['created_by' => $manager->id, 'status' => ProjectStatus::Active]);

        Livewire::actingAs($manager)
            ->test('projects.show', ['project' => $project])
            ->assertDontSee('title="Delete project"', false)
            ->call('deleteProject')
            ->assertForbidden();

        $this->assertNotSoftDeleted($project);
    }

    public function test_a_deleted_project_is_hidden_from_the_project_list(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['name' => 'Website Relaunch', 'created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('projects.show', ['project' => $project])
            ->set('delete_reason', 'Shelved')
            ->call('deleteProject');

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertDontSee('Website Relaunch');
    }

    public function test_a_deleted_projects_page_is_no_longer_reachable(): void
    {
        $admin = $this->admin();
        $project = Project::factory()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('projects.show', ['project' => $project])
            ->set('delete_reason', 'Shelved')
            ->call('deleteProject');

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertNotFound();
    }

    public function test_a_deleted_tasks_page_is_no_longer_reachable(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($admin)
            ->test('tasks.show', ['task' => $task])
            ->set('delete_reason', 'Dropped')
            ->call('deleteTask');

        $this->actingAs($admin)
            ->get(route('tasks.show', $task))
            ->assertNotFound();
    }

    public function test_project_statistics_ignore_deleted_tasks(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $project = Project::factory()->create(['created_by' => $admin->id]);

        app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit', 'project_id' => $project->id], $rahim, $karim);
        $doomed = app(TaskWorkflowService::class)->createTask(['title' => 'Extra task', 'project_id' => $project->id], $rahim, $karim);

        Livewire::actingAs($admin)
            ->test('tasks.show', ['task' => $doomed])
            ->set('delete_reason', 'Dropped')
            ->call('deleteTask');

        Livewire::actingAs($admin)
            ->test('projects.show', ['project' => $project])
            ->assertViewHas('total', 1);
    }

    public function test_a_task_can_still_be_marked_done_after_its_project_is_deleted(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $project = Project::factory()->create(['created_by' => $admin->id]);
        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit', 'project_id' => $project->id], $rahim, $karim);

        Livewire::actingAs($admin)
            ->test('projects.show', ['project' => $project])
            ->set('delete_reason', 'Shelved')
            ->call('deleteProject');

        $task->refresh();
        $task->update(['status' => TaskStatus::ReadyToDeploy]);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->call('saveStatus', TaskStatus::Done->value);

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
    }

    public function test_the_projects_list_progress_ignores_a_deleted_projects_tasks(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $project = Project::factory()->create(['name' => 'Kept project', 'created_by' => $admin->id]);
        $doomedProject = Project::factory()->create(['name' => 'Doomed project', 'created_by' => $admin->id]);

        app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit', 'project_id' => $project->id], $rahim, $karim);

        Livewire::actingAs($admin)
            ->test('projects.show', ['project' => $doomedProject])
            ->set('delete_reason', 'Shelved')
            ->call('deleteProject');

        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Kept project')
            ->assertDontSee('Doomed project');
    }

    public function test_deleting_a_project_leaves_every_database_row_in_place(): void
    {
        $admin = $this->admin();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $project = Project::factory()->create(['created_by' => $admin->id]);
        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Website Audit', 'project_id' => $project->id], $rahim, $karim);
        TaskTimeLog::create(['task_id' => $task->id, 'user_id' => $karim->id, 'logged_date' => today(), 'hours' => 3]);

        Livewire::actingAs($admin)
            ->test('projects.show', ['project' => $project])
            ->set('delete_reason', 'Shelved')
            ->call('deleteProject');

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('task_assignments', ['task_id' => $task->id]);
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id]);
        $this->assertDatabaseHas('task_time_logs', ['task_id' => $task->id, 'hours' => 3]);
    }
}
