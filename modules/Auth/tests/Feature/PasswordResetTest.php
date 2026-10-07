<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Modules\Auth\Actions\ForgotPasswordAction;
use Modules\Auth\Notifications\ResetPasswordNotification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_renders(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
    }

    public function test_reset_link_is_sent_for_registered_email(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');
    }

    /**
     * The status is rendered verbatim in the UI, so a missing translation file
     * leaks the raw "passwords.sent" key to the user.
     */
    public function test_reset_link_status_is_translated_in_every_locale(): void
    {
        $user = $this->createUser();

        foreach (['it', 'en', 'pt_BR'] as $locale) {
            $this->app->setLocale($locale);

            $response = $this->post(route('password.email'), [
                'email' => $user->email,
            ]);

            $status = $response->getSession()->get('status');

            $this->assertNotSame('passwords.sent', $status, "Missing passwords.sent translation for [{$locale}].");
        }
    }

    /**
     * The link must point at this application, not at the framework default
     * frontend URL (http://localhost:3000), and must hit a real route.
     */
    public function test_reset_link_points_to_the_application_reset_route(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $actionUrl = $notification->toMail($user)->actionUrl;

            $this->assertStringStartsWith(config('app.url'), $actionUrl);
            $this->assertStringContainsString('/auth/reset-password/', $actionUrl);
            $this->assertStringContainsString('email='.urlencode($user->email), $actionUrl);

            return true;
        });
    }

    /**
     * The API flow issues a JWT that only /api/v1/auth/reset-password can
     * validate, so its link must target the client app, not the web page.
     */
    public function test_api_reset_link_points_to_the_client_app(): void
    {
        config(['app.frontend_url' => 'https://app.miocoach.test']);

        Notification::fake();

        $user = $this->createUser();

        app(ForgotPasswordAction::class)->handle($user->email);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $actionUrl = $notification->toMail($user)->actionUrl;

            $this->assertStringStartsWith('https://app.miocoach.test/reset-password?token=eyJ', $actionUrl);
            $this->assertStringContainsString('email='.urlencode($user->email), $actionUrl);

            return true;
        });
    }

    public function test_reset_password_page_renders_with_valid_token(): void
    {
        $user = $this->createUser();
        $token = Password::createToken($user);

        $response = $this->get(route('password.reset', ['token' => $token]));

        $response->assertStatus(200);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = $this->createUser();
        $token = Password::createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->getAuthPassword()));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = $this->createUser();

        $response = $this->post(route('password.store'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_reset_password_validates_password_confirmation(): void
    {
        $user = $this->createUser();
        $token = Password::createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertInvalid('password');
    }
}
