<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\SmtpTestMail;
use App\Models\AppSetting;
use App\Models\User;
use App\Notifications\MailTemplatePreview;
use Database\Seeders\RoleAndAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndAdminSeeder::class);
    }

    public function test_super_admin_can_update_the_loading_screen_settings(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('show_loading_screen', false)
            ->set('loading_screen_seconds', 7)
            ->set('loading_screen_opacity', 25)
            ->set('loading_screen_blur', 32)
            ->call('saveLoadingScreen');

        $settings = AppSetting::current();

        $this->assertFalse($settings->show_loading_screen);
        $this->assertSame(7, $settings->loading_screen_seconds);
        $this->assertSame(25, $settings->loading_screen_opacity);
        $this->assertSame(32, $settings->loading_screen_blur);
    }

    public function test_loading_screen_duration_must_be_within_range(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('loading_screen_seconds', 15)
            ->call('saveLoadingScreen')
            ->assertHasErrors(['loading_screen_seconds' => 'max']);
    }

    public function test_loading_screen_opacity_and_blur_must_be_within_range(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('loading_screen_opacity', 150)
            ->set('loading_screen_blur', -5)
            ->call('saveLoadingScreen')
            ->assertHasErrors(['loading_screen_opacity' => 'max', 'loading_screen_blur' => 'min']);
    }

    public function test_super_admin_can_switch_the_loading_screen_animation_style(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('loading_screen_style', 'spinner')
            ->call('saveLoadingScreen')
            ->assertHasNoErrors();

        $this->assertSame('spinner', AppSetting::current()->loading_screen_style);
    }

    public function test_super_admin_can_switch_to_the_equalizer_bars_animation(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('loading_screen_style', 'bars')
            ->call('saveLoadingScreen')
            ->assertHasNoErrors();

        $this->assertSame('bars', AppSetting::current()->loading_screen_style);
    }

    public function test_super_admin_can_switch_to_the_handoff_animation_and_typing_hand_is_gone(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('loading_screen_style', 'handoff')
            ->call('saveLoadingScreen')
            ->assertHasNoErrors();

        $this->assertSame('handoff', AppSetting::current()->loading_screen_style);

        Livewire::actingAs($admin)->test('settings')->set('loading_screen_style', 'hand')->call('saveLoadingScreen')->assertHasErrors(['loading_screen_style']);

        $this->actingAs($admin)->get(route('settings', ['tab' => 'loading-screen']))
            ->assertSee('Handoff')
            ->assertSee('handoff-loader', false)
            ->assertDontSee('Typing Hand');
    }

    public function test_the_loading_screen_style_must_be_a_known_option(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('loading_screen_style', 'not-a-real-style')
            ->call('saveLoadingScreen')
            ->assertHasErrors(['loading_screen_style']);
    }

    public function test_default_settings_have_sensible_values(): void
    {
        $settings = AppSetting::current();

        $this->assertTrue($settings->show_loading_screen);
        $this->assertSame(3, $settings->loading_screen_seconds);
        $this->assertSame(10, $settings->loading_screen_opacity);
        $this->assertSame(64, $settings->loading_screen_blur);
        $this->assertSame('jampe', $settings->loading_screen_style);
    }

    public function test_team_member_gets_only_the_look_tab_and_none_of_the_app_settings(): void
    {
        AppSetting::current()->update(['mail_host' => 'smtp.secret-host.test']);

        $teamMember = User::factory()->create(['status' => 'active']);
        $teamMember->assignRole(Role::TeamMember->value);

        $this->actingAs($teamMember)
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('Visual style')
            ->assertDontSee('Loading Screen')
            ->assertDontSee('smtp.secret-host.test');

        Livewire::actingAs($teamMember)->test('settings')->call('saveSmtp')->assertForbidden();
    }

    public function test_login_flashes_loading_screen_data_when_enabled(): void
    {
        AppSetting::current()->update(['show_loading_screen' => true, 'loading_screen_seconds' => 5]);

        $user = User::factory()->create(['password' => bcrypt('secret123'), 'status' => 'active']);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login');

        $this->assertTrue(session('just_logged_in'));
        $this->assertSame(5, session('loading_screen_seconds'));
    }

    public function test_login_does_not_flash_loading_screen_data_when_disabled(): void
    {
        AppSetting::current()->update(['show_loading_screen' => false]);

        $user = User::factory()->create(['password' => bcrypt('secret123'), 'status' => 'active']);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login');

        $this->assertFalse(session('just_logged_in', false));
    }

    public function test_super_admin_can_save_smtp_settings(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('tab', 'smtp')
            ->set('mail_host', 'smtp.example.test')
            ->set('mail_port', '587')
            ->set('mail_username', 'mailer@example.test')
            ->set('mail_password', 'secret-password')
            ->set('mail_encryption', 'tls')
            ->set('mail_from_address', 'hello@example.test')
            ->set('mail_from_name', 'Example App')
            ->call('saveSmtp')
            ->assertHasNoErrors();

        $settings = AppSetting::current();

        $this->assertSame('smtp.example.test', $settings->mail_host);
        $this->assertSame(587, $settings->mail_port);
        $this->assertSame('mailer@example.test', $settings->mail_username);
        $this->assertSame('secret-password', $settings->mail_password);
        $this->assertSame('tls', $settings->mail_encryption);
        $this->assertSame('hello@example.test', $settings->mail_from_address);
        $this->assertSame('Example App', $settings->mail_from_name);
    }

    public function test_leaving_the_password_blank_keeps_the_previously_saved_one(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        AppSetting::current()->update([
            'mail_host' => 'smtp.example.test',
            'mail_port' => 587,
            'mail_password' => 'original-password',
        ]);

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('tab', 'smtp')
            ->set('mail_host', 'smtp.example.test')
            ->set('mail_port', '587')
            ->set('mail_password', '')
            ->call('saveSmtp');

        $this->assertSame('original-password', AppSetting::current()->mail_password);
    }

    public function test_the_saved_password_is_never_sent_back_to_the_browser(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        AppSetting::current()->update([
            'mail_host' => 'smtp.example.test',
            'mail_password' => 'super-secret',
        ]);

        Livewire::actingAs($admin)
            ->test('settings')
            ->assertSet('mail_password', '')
            ->assertDontSee('super-secret');
    }

    public function test_sending_a_test_email_uses_the_unsaved_form_values(): void
    {
        Mail::fake();

        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('tab', 'smtp')
            ->set('mail_host', 'smtp.example.test')
            ->set('mail_port', '587')
            ->set('mail_from_address', 'hello@example.test')
            ->set('test_email', 'someone@example.test')
            ->call('testSmtp')
            ->assertHasNoErrors();

        Mail::assertSent(SmtpTestMail::class, fn ($mail) => $mail->hasTo('someone@example.test'));

        // The test send must not have persisted anything.
        $this->assertNull(AppSetting::current()->mail_host);
    }

    public function test_a_failed_test_send_reports_the_error_without_crashing(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('tab', 'smtp')
            ->set('mail_host', 'smtp.invalid.test')
            ->set('mail_port', '587')
            ->set('test_email', 'someone@example.test')
            ->call('testSmtp')
            ->assertHasNoErrors();
    }

    public function test_super_admin_can_save_the_mail_template_colors(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('tab', 'mail-template')
            ->set('mail_brand_color', '#ff6600')
            ->set('mail_button_text_color', '#111111')
            ->set('mail_footer_note', 'Questions? Reply to this email.')
            ->call('saveMailTemplate')
            ->assertHasNoErrors();

        $settings = AppSetting::current();

        $this->assertSame('#ff6600', $settings->mail_brand_color);
        $this->assertSame('#111111', $settings->mail_button_text_color);
        $this->assertSame('Questions? Reply to this email.', $settings->mail_footer_note);
    }

    public function test_mail_template_colors_must_be_valid_hex(): void
    {
        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('tab', 'mail-template')
            ->set('mail_brand_color', 'not-a-color')
            ->call('saveMailTemplate')
            ->assertHasErrors(['mail_brand_color' => 'regex']);
    }

    public function test_sending_a_template_preview_saves_the_colors_and_emails_the_sample(): void
    {
        Notification::fake();

        $admin = User::where('email', 'admin@handloft.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('settings')
            ->set('tab', 'mail-template')
            ->set('mail_brand_color', '#ff6600')
            ->set('mail_button_text_color', '#111111')
            ->set('template_preview_email', 'preview@example.test')
            ->call('sendTemplatePreview')
            ->assertHasNoErrors();

        $this->assertSame('#ff6600', AppSetting::current()->mail_brand_color);

        Notification::assertSentOnDemand(
            MailTemplatePreview::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'preview@example.test'
        );
    }

    public function test_the_shared_mail_template_inlines_the_configured_brand_color(): void
    {
        AppSetting::current()->update([
            'mail_brand_color' => '#ff6600',
            'mail_button_text_color' => '#111111',
            'mail_footer_note' => 'Custom footer note here.',
        ]);

        $message = (new MailMessage)
            ->subject('Test')
            ->greeting('Hi there,')
            ->line('This is a test line.')
            ->action('View Thing', 'https://example.test');

        $html = (string) app(Markdown::class)->render('notifications::email', $message->toArray());

        $this->assertStringContainsString('background-color: #ff6600 !important', $html);
        $this->assertStringContainsString('color: #111111 !important', $html);
        $this->assertStringContainsString('Custom footer note here.', $html);
    }
}
