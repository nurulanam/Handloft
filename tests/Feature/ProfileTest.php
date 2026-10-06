<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    private function teamMember(array $attributes = []): User
    {
        $user = User::factory()->create(['name' => 'Karim Uddin', 'status' => 'active', 'password' => 'old-password', ...$attributes]);
        $user->assignRole(Role::TeamMember->value);

        return $user;
    }

    public function test_any_signed_in_user_can_open_their_profile(): void
    {
        $karim = $this->teamMember(['department' => 'Design']);

        $this->actingAs($karim)->get(route('profile'))
            ->assertOk()
            ->assertSee('My profile')
            ->assertSee('Team Member')
            ->assertSee('Design')
            ->assertSee('Managed by an admin');
    }

    public function test_the_account_menu_links_to_the_profile(): void
    {
        $this->actingAs($this->teamMember())->get(route('dashboard'))
            ->assertSee(route('profile'))
            ->assertSee('My profile');
    }

    public function test_a_user_can_update_their_personal_details(): void
    {
        $karim = $this->teamMember();

        Livewire::actingAs($karim)->test('profile')
            ->set('name', 'Karim Hasan')
            ->set('email', 'karim.hasan@example.com')
            ->set('phone', '+8801700000000')
            ->call('saveDetails')
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Your profile was updated.', type: 'success');

        $karim->refresh();
        $this->assertSame('Karim Hasan', $karim->name);
        $this->assertSame('karim.hasan@example.com', $karim->email);
        $this->assertSame('+8801700000000', $karim->phone);
    }

    public function test_the_email_must_stay_unique(): void
    {
        $karim = $this->teamMember();
        $taken = User::factory()->create(['email' => 'taken@example.com']);

        Livewire::actingAs($karim)->test('profile')
            ->set('email', $taken->email)
            ->call('saveDetails')
            ->assertHasErrors(['email' => 'unique']);
    }

    public function test_the_profile_offers_no_way_to_change_role_or_status(): void
    {
        $karim = $this->teamMember();
        $component = Livewire::actingAs($karim)->test('profile');

        foreach (['role', 'status', 'department', 'user_id', 'joining_date'] as $adminOnly) {
            $this->assertFalse(property_exists($component->instance(), $adminOnly), "{$adminOnly} should not be editable here");
        }

        $component->call('saveDetails');

        $this->assertTrue($karim->fresh()->hasRole(Role::TeamMember->value));
        $this->assertSame('active', $karim->fresh()->status);
    }

    public function test_changing_the_password_requires_the_current_one(): void
    {
        $karim = $this->teamMember();

        Livewire::actingAs($karim)->test('profile')
            ->set('current_password', 'wrong-password')
            ->set('password', 'brand-new-secret')
            ->set('password_confirmation', 'brand-new-secret')
            ->call('savePassword')
            ->assertHasErrors(['current_password']);

        $this->assertTrue(Hash::check('old-password', $karim->fresh()->password));

        Livewire::actingAs($karim)->test('profile')
            ->set('current_password', 'old-password')
            ->set('password', 'brand-new-secret')
            ->set('password_confirmation', 'brand-new-secret')
            ->call('savePassword')
            ->assertHasNoErrors()
            ->assertSet('current_password', '')
            ->assertDispatched('notify', message: 'Your password was changed.', type: 'success');

        $this->assertTrue(Hash::check('brand-new-secret', $karim->fresh()->password));
    }

    public function test_the_new_password_must_differ_and_be_confirmed(): void
    {
        $karim = $this->teamMember();

        Livewire::actingAs($karim)->test('profile')
            ->set('current_password', 'old-password')
            ->set('password', 'old-password')
            ->set('password_confirmation', 'old-password')
            ->call('savePassword')
            ->assertHasErrors(['password' => 'different']);

        Livewire::actingAs($karim)->test('profile')
            ->set('current_password', 'old-password')
            ->set('password', 'brand-new-secret')
            ->set('password_confirmation', 'something-else')
            ->call('savePassword')
            ->assertHasErrors(['password' => 'confirmed']);
    }

    public function test_a_non_image_file_is_rejected_as_a_photo(): void
    {
        Storage::fake('public');
        $karim = $this->teamMember();

        Livewire::actingAs($karim)->test('profile')
            ->set('photo', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
            ->assertHasErrors(['photo']);

        $this->assertNull($karim->fresh()->profile_photo);
    }

    public function test_a_user_can_upload_and_replace_their_photo(): void
    {
        Storage::fake('public');
        $karim = $this->teamMember();

        Livewire::actingAs($karim)->test('profile')
            ->set('photo', UploadedFile::fake()->image('me.jpg'))
            ->assertHasNoErrors()
            ->assertDispatched('notify', message: 'Profile photo updated.', type: 'success');

        $first = $karim->fresh()->profile_photo;
        Storage::disk('public')->assertExists($first);

        Livewire::actingAs($karim)->test('profile')
            ->set('photo', UploadedFile::fake()->image('me-again.jpg'));

        $second = $karim->fresh()->profile_photo;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }
}
