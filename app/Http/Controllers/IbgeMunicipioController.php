<?php

namespace App\Http\Controllers;

use App\Models\IbgeEstado;
use App\Models\IbgeMunicipio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IbgeMunicipioController extends Controller
{
    /**
     * List municipalities for a given IBGE state (by estado_id or UF sigla).
     */
    public function index(Request $request): JsonResponse
    {
        $estadoId = $request->integer('estado_id') ?: null;
        $sigla = strtoupper($request->string('uf')->toString());

        if ($estadoId === null && $sigla === '') {
            return response()->json(['municipios' => []]);
        }

        if ($estadoId === null) {
            $estadoId = IbgeEstado::query()
                ->whereRaw('UPPER(txt_sigla_uf) = ?', [$sigla])
                ->value('id');
        }

        if ($estadoId === null) {
            return response()->json(['municipios' => []]);
        }

        $municipios = IbgeMunicipio::query()
            ->where('estado_id', $estadoId)
            ->whereNotNull('txt_nome_municipios')
            ->orderBy('txt_nome_municipios')
            ->get(['id', 'txt_nome_municipios', 'estado_id'])
            ->map(fn (IbgeMunicipio $municipio): array => [
                'id' => $municipio->id,
                'nome' => $municipio->txt_nome_municipios,
                'estado_id' => $municipio->estado_id,
            ])
            ->values()
            ->all();

        return response()->json(['municipios' => $municipios]);
    }
}
