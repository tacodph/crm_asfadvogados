<?php

namespace App\Actions\Crm;

use App\Models\ConsentimentoContato;
use App\Models\Contato;
use Illuminate\Support\Facades\DB;

class SyncContatoConsentimentos
{
    /**
     * Persist the contact and upsert one consent row per finalidade.
     *
     * @param  array<string, mixed>  $contatoData
     * @param  list<array{finalidade_consentimento_id: int, status_consentimento_id: int, concedido_em: ?string, revogado_em: ?string}>  $consentimentos
     */
    public function __invoke(Contato $contato, array $contatoData, array $consentimentos): Contato
    {
        return DB::transaction(function () use ($contato, $contatoData, $consentimentos): Contato {
            $contato->fill($contatoData);
            $contato->save();

            foreach ($consentimentos as $linha) {
                ConsentimentoContato::query()->updateOrCreate(
                    [
                        'contato_id' => $contato->id,
                        'finalidade_consentimento_id' => $linha['finalidade_consentimento_id'],
                    ],
                    [
                        'status_consentimento_id' => $linha['status_consentimento_id'],
                        'concedido_em' => $linha['concedido_em'],
                        'revogado_em' => $linha['revogado_em'],
                    ],
                );
            }

            return $contato->refresh();
        });
    }
}
