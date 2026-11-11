<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConfiguracaoController extends Controller
{
    private const CAMPOS = ['whatsapp_suporte', 'whatsapp_grupo', 'instagram', 'legal_company', 'legal_cnpj', 'vocabulario'];

    public function edit(): View
    {
        return view('painel.configuracoes', ['valores' => collect(self::CAMPOS)->mapWithKeys(fn ($k) => [$k => Setting::get($k)])]);
    }

    public function update(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'whatsapp_suporte' => ['nullable', 'regex:/^\D*(\d\D*){10,11}$/'],
            'whatsapp_grupo' => ['nullable', 'url', 'starts_with:https://chat.whatsapp.com/', 'max:200'],
            'instagram' => ['nullable', 'regex:/^@?[A-Za-z0-9._]{1,30}$/'],
            'legal_company' => ['nullable', 'string', 'max:160'],
            'legal_cnpj' => ['nullable', 'regex:/^\D*(\d\D*){14}$/'],
            'vocabulario' => ['required', Rule::in(['numeros', 'titulos'])],
        ]);

        $dados['whatsapp_suporte'] = preg_replace('/\D/', '', (string) ($dados['whatsapp_suporte'] ?? '')) ?: null;
        $dados['instagram'] = ltrim((string) ($dados['instagram'] ?? ''), '@') ?: null;
        if (! empty($dados['legal_cnpj'])) {
            $dados['legal_cnpj'] = preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', preg_replace('/\D/', '', $dados['legal_cnpj']));
        }

        foreach (self::CAMPOS as $campo) {
            Setting::put($campo, $dados[$campo] ?? null);
        }

        Auditoria::registrar('configuracoes.alteradas', null, ['campos' => self::CAMPOS]);

        return back()->with('ok', 'Configurações salvas.');
    }
}
