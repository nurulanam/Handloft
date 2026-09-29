<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TaskDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
        Storage::fake('public');
    }

    private function teamMember(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::TeamMember->value);

        return $user;
    }

    public function test_authorized_user_can_update_description_and_html_is_sanitized(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->set('description', '<p>Hello</p><script>alert(1)</script><img src="x" onerror="alert(2)">')
            ->call('saveDescription');

        $task->refresh();

        $this->assertStringContainsString('<p>Hello</p>', $task->description);
        $this->assertStringNotContainsString('<script>', $task->description);
        $this->assertStringNotContainsString('onerror', $task->description);
    }

    public function test_unrelated_user_cannot_even_view_the_task(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        $this->actingAs($hasan)
            ->get(route('tasks.show', $task))
            ->assertForbidden();
    }

    public function test_the_creator_can_view_but_only_the_assignee_or_reassign_permission_holder_edits_metadata(): void
    {
        $admin = User::where('email', 'admin@am2amdesk.test')->firstOrFail();
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        // Admin is neither creator nor assignee but holds reassign-task, so
        // updateMeta should still be allowed.
        Livewire::actingAs($admin)
            ->test('tasks.show', ['task' => $task])
            ->call('savePriority', 'low');

        $task->refresh();

        $this->assertSame('low', $task->priority->value);
    }

    public function test_the_assignee_can_update_the_reporter(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        // The reporter is task metadata like Project/Parent/Priority, not a
        // reassignment — so the assignee can correct it, not only someone
        // with reassign-task permission.
        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->call('saveReporter', $hasan->id)
            ->assertHasNoErrors();

        $this->assertSame($hasan->id, $task->fresh()->created_by);
    }

    public function test_an_unrelated_team_member_cannot_update_the_reporter(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');
        $hasan = $this->teamMember('Hasan');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit', 'qa_id' => $hasan->id], $rahim, $karim);

        Livewire::actingAs($hasan)
            ->test('tasks.show', ['task' => $task])
            ->call('saveReporter', $hasan->id)
            ->assertForbidden();

        $this->assertSame($rahim->id, $task->fresh()->created_by);
    }

    public function test_adding_a_comment_with_an_attachment_persists(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($karim)
            ->test('tasks.show', ['task' => $task])
            ->set('newComment', 'Looks good to me.')
            ->set('commentAttachments', [UploadedFile::fake()->create('notes.pdf', 100)])
            ->call('addComment');

        $comment = TaskComment::firstOrFail();

        $this->assertSame('Looks good to me.', $comment->body);
        $this->assertSame($karim->id, $comment->user_id);
        $this->assertSame(1, $comment->attachments()->count());
    }

    public function test_adding_a_subtask_links_it_to_the_parent(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $parent = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $parent])
            ->set('subtask_title', 'Check broken links')
            ->set('subtask_assigned_to', $karim->id)
            ->call('addSubtask');

        $parent->refresh();

        $this->assertSame(1, $parent->children()->count());
        $this->assertSame('Check broken links', $parent->children()->first()->title);
    }

    public function test_a_task_cannot_be_linked_as_its_own_parent(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->call('saveParent', $task->id)
            ->assertHasErrors('parent_value');

        $task->refresh();

        $this->assertNull($task->parent_task_id);
    }

    public function test_linking_a_valid_parent_task_persists(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $parent = $workflow->createTask(['title' => 'Website Redesign'], $rahim, $karim);
        $child = $workflow->createTask(['title' => 'Write copy'], $rahim, $karim);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $child])
            ->call('saveParent', $parent->id);

        $child->refresh();

        $this->assertSame($parent->id, $child->parent_task_id);
        $this->assertTrue($parent->children()->whereKey($child->id)->exists());
    }

    public function test_updating_priority_logs_an_activity_entry(): void
    {
        $rahim = $this->teamMember('Rahim');
        $karim = $this->teamMember('Karim');

        $workflow = app(TaskWorkflowService::class);
        $task = $workflow->createTask(['title' => 'Website Audit'], $rahim, $karim);

        Livewire::actingAs($rahim)
            ->test('tasks.show', ['task' => $task])
            ->call('savePriority', 'urgent');

        $task->refresh();

        $this->assertSame('urgent', $task->priority->value);
        $this->assertTrue($task->activities()->where('type', 'meta_updated')->exists());
    }
}
