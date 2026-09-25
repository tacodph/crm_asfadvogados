<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessarLeadEmailRecebido;
use App\Models\EmailLeadConta;
use App\Models\EmailLeadProcessado;
use App\Models\Funil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReprocessarLeadEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_missing_message_id_fails(): void
    {
        $this->artisan('leads:email-reprocessar inexistente@mail.gmail.com')->assertFailed();

        Queue::assertNothingPushed();
    }

    public function test_registro_without_payload_fails(): void
    {
        $registro = EmailLeadProcessado::factory()->comErro()->create([
            'message_id' => '<sem-payload@mail.gmail.com>',
            'payload_html' => null,
        ]);

        $this->artisan("leads:email-reprocessar {$registro->message_id}")->assertFailed();

        Queue::assertNothingPushed();
    }

    public function test_successful_reprocess_redispatches_the_job_with_the_saved_html(): void
    {
        $conta = EmailLeadConta::factory()->create(['funil_id' => Funil::factory()->create()->id]);

        $registro = EmailLeadProcessado::factory()->comErro()->create([
            'message_id' => '<falhou@mail.gmail.com>',
            'tenant_id' => $this->tenant->id,
            'email_lead_conta_id' => $conta->id,
            'payload_html' => '<table><tr><td>Nome</td><td>Fulano</td></tr></table>',
        ]);

        $this->artisan("leads:email-reprocessar {$registro->message_id}")->assertSuccessful();

        $registro->refresh();
        $this->assertSame('pendente', $registro->status);
        $this->assertNull($registro->erro);

        Queue::assertPushed(
            ProcessarLeadEmailRecebido::class,
            fn (ProcessarLeadEmailRecebido $job): bool => $job->messageId === '<falhou@mail.gmail.com>'
                && $job->emailLeadContaId === $conta->id
                && $job->tenantSlug === $this->tenant->slug,
        );
    }
}
