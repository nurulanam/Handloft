<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function member(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'status' => 'active']);
        $user->assignRole(Role::TeamMember->value);

        return $user;
    }

    private function taskWithFiles(User $owner, array $files): Task
    {
        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Ship it'], $owner, $owner);

        Livewire::actingAs($owner)->test('tasks.show', ['task' => $task])
            ->set('newAttachments', $files)
            ->call('addAttachments')
            ->assertHasNoErrors();

        return $task->fresh();
    }

    public function test_uploads_are_stored_privately_and_linked_through_the_protected_route(): void
    {
        $owner = $this->member('Karim');
        $task = $this->taskWithFiles($owner, [UploadedFile::fake()->image('shot.png')]);
        $attachment = $task->attachments()->firstOrFail();

        Storage::disk('local')->assertExists($attachment->path);
        Storage::disk('public')->assertMissing($attachment->path);

        $this->actingAs($owner)->get(route('tasks.show', $task))
            ->assertSee(route('attachments.show', ['kind' => 'task', 'id' => $attachment->id, 'name' => 'shot.png']), false)
            ->assertDontSee('/storage/task-attachments', false);
    }

    public function test_only_people_who_can_see_the_task_can_open_its_files(): void
    {
        $owner = $this->member('Karim');
        $task = $this->taskWithFiles($owner, [UploadedFile::fake()->image('shot.png')]);
        $url = route('attachments.show', ['kind' => 'task', 'id' => $task->attachments()->value('id')]);

        $this->app['auth']->forgetGuards();
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->member('Nadia'))->get($url)->assertForbidden();
        $this->actingAs($owner)->get($url)->assertOk();
    }

    public function test_only_safe_types_open_in_the_browser_and_nothing_can_run_scripts(): void
    {
        $owner = $this->member('Karim');
        $task = $this->taskWithFiles($owner, [
            UploadedFile::fake()->image('shot.png'),
            UploadedFile::fake()->createWithContent('notes.html', '<script>alert(1)</script>'),
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->create('spec.pdf', 20, 'application/pdf'),
        ]);
        $url = fn (string $name, array $extra = []) => route('attachments.show', ['kind' => 'task', 'id' => $task->attachments()->where('original_name', $name)->value('id')] + $extra);

        $this->actingAs($owner);

        $png = $this->get($url('shot.png'));
        $png->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline', $png->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $png->headers->get('Content-Security-Policy'));

        foreach (['notes.html', 'logo.svg'] as $risky) {
            $response = $this->get($url($risky));
            $response->assertOk()->assertHeader('Content-Type', 'application/octet-stream')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'), $risky.' must download');
        }

        $pdf = $this->get($url('spec.pdf'));
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline', $pdf->headers->get('Content-Disposition'));

        $this->assertStringStartsWith('attachment', $this->get($url('shot.png', ['download' => 1]))->headers->get('Content-Disposition'));
    }

    public function test_the_uploader_or_a_task_editor_can_remove_a_file_and_it_is_logged(): void
    {
        $owner = $this->member('Karim');
        $task = $this->taskWithFiles($owner, [UploadedFile::fake()->image('shot.png')]);
        $attachment = $task->attachments()->firstOrFail();

        // A manager can see every task, but didn't upload this file and can't edit this task's details.
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole(Role::Manager->value);
        Livewire::actingAs($manager)->test('tasks.show', ['task' => $task])->call('deleteAttachment', 'task', $attachment->id)->assertForbidden();

        Livewire::actingAs($owner)->test('tasks.show', ['task' => $task])
            ->call('deleteAttachment', 'task', $attachment->id)
            ->assertDispatched('notify');

        $this->assertModelMissing($attachment);
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertTrue($task->activities()->where('description', 'like', 'Attachment "shot.png" removed%')->exists());
    }

    public function test_files_uploaded_before_the_move_to_private_storage_still_open(): void
    {
        $owner = $this->member('Karim');
        $task = app(TaskWorkflowService::class)->createTask(['title' => 'Old one'], $owner, $owner);
        Storage::disk('public')->put('task-attachments/old.png', UploadedFile::fake()->image('old.png')->getContent());
        $attachment = TaskAttachment::create(['task_id' => $task->id, 'uploaded_by' => $owner->id, 'path' => 'task-attachments/old.png', 'original_name' => 'old.png', 'size' => 10]);

        $this->actingAs($owner)->get(route('attachments.show', ['kind' => 'task', 'id' => $attachment->id]))->assertOk();
    }

    public function test_files_added_while_creating_a_task_are_private_and_logged(): void
    {
        $owner = $this->member('Karim');

        Livewire::actingAs($owner)->test('tasks.create')
            ->set('title', 'With files')
            ->set('assigned_to', (string) $owner->id)
            ->set('attachments', [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->create('b.pdf', 5, 'application/pdf')])
            ->call('save')
            ->assertHasNoErrors();

        $task = Task::where('title', 'With files')->firstOrFail();
        $this->assertCount(2, $task->attachments);
        foreach ($task->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->path);
        }
        $this->assertTrue($task->activities()->where('description', 'like', '2 attachments added%')->exists());
    }
}
