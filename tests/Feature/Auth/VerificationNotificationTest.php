<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class VerificationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_sends_verification_notification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // Fortify's "already sent" response is a plain back(); with no
        // Referer header it falls back to the current request's own root,
        // which 404s on a tenant subdomain (nothing is routed at "/" there)
        // — a real browser always sends one for same-origin navigation, so
        // set it explicitly to get the same deterministic behavior here.
        $this->actingAs($user)
            ->from($this->tenantUrl('dashboard'))
            ->post($this->tenantUrl('verification.send'))
            ->assertRedirect($this->tenantUrl('dashboard'));

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_does_not_send_verification_notification_if_email_is_verified(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('verification.send'))
            ->assertRedirect($this->tenantUrl('dashboard'));

        Notification::assertNothingSent();
    }
}
