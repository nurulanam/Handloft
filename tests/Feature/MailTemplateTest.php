<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AppSetting;
use App\Models\User;
use App\Notifications\MailTemplatePreview;
use App\Support\MailTheme;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@handloft.test')->firstOrFail();
    }

    private function renderSample(): string
    {
        return (string) (new MailTemplatePreview)->toMail($this->admin())->render();
    }

    public function test_contrast_and_the_suggested_button_text(): void
    {
        $this->assertSame(21.0, MailTheme::contrast('#000000', '#ffffff'));
        $this->assertSame('#ffffff', MailTheme::suggestedText('#10512a'));
        $this->assertSame('#18181b', MailTheme::suggestedText('#bfef1e'));

        foreach (MailTheme::PRESETS as $preset) {
            $this->assertGreaterThanOrEqual(4.5, MailTheme::contrast($preset['brand'], $preset['text']), $preset['name'].' preset must be readable');
        }
    }

    public function test_a_preset_fills_both_colours_and_the_layout_is_saved_and_used_by_real_emails(): void
    {
        Livewire::actingAs($this->admin())->test('settings')
            ->set('tab', 'mail-template')
            ->call('applyMailPreset', 'lime')
            ->assertSet('mail_brand_color', '#bfef1e')
            ->assertSet('mail_button_text_color', '#10512a')
            ->set('mail_layout', 'minimal')
            ->set('mail_footer_note', 'Questions? Reply to this email.')
            ->call('saveMailTemplate')
            ->assertHasNoErrors();

        $settings = AppSetting::current()->fresh();
        $this->assertSame('minimal', $settings->mail_layout);
        $this->assertSame('#bfef1e', $settings->mail_brand_color);

        $html = $this->renderSample();
        $this->assertStringContainsString('#bfef1e', $html);
        $this->assertStringContainsString('Questions? Reply to this email.', $html);
        $this->assertStringContainsString('Redesign the onboarding emails</strong>', $html, 'Markdown is rendered');

        Livewire::actingAs($this->admin())->test('settings')->set('mail_layout', 'fancy')->call('saveMailTemplate')->assertHasErrors(['mail_layout']);
    }

    public function test_each_layout_renders(): void
    {
        foreach (array_keys(MailTheme::LAYOUTS) as $layout) {
            $html = MailTheme::preview(['layout' => $layout, 'brand' => '#6b21a8', 'text' => '#ffffff', 'footer' => null], fn () => $this->renderSample());

            $this->assertStringContainsString('#6b21a8', $html, $layout);
            $this->assertStringContainsString('View task', $html, $layout);
            $this->assertStringNotContainsString('# Hi there', $html, $layout.' Markdown is rendered');
        }

        // After a preview, real emails use the saved settings again.
        $this->assertStringNotContainsString('#6b21a8', $this->renderSample());
    }

    public function test_the_live_preview_page_is_for_super_admins_and_checks_its_input(): void
    {
        $query = ['layout' => 'modern', 'brand' => '#0b4f6c', 'text' => '#ffffff', 'footer' => 'Hello'];

        $this->actingAs($this->admin())->get(route('settings.mail-preview', $query))
            ->assertOk()
            ->assertSee('#0b4f6c', false)
            ->assertSee('Redesign the onboarding emails</strong>', false)
            ->assertDontSee('if BLOCK', false);

        $this->actingAs($this->admin())->get(route('settings.mail-preview', ['brand' => 'red'] + $query))->assertSessionHasErrors('brand');

        $member = User::factory()->create(['status' => 'active']);
        $member->assignRole(Role::TeamMember->value);
        $this->actingAs($member)->get(route('settings.mail-preview', $query))->assertForbidden();

        $this->actingAs($this->admin())->get(route('settings', ['tab' => 'mail-template']))
            ->assertSee(e(route('settings.mail-preview', ['layout' => 'modern', 'brand' => '#10512a', 'text' => '#ffffff', 'footer' => ''])), false);
    }
}
