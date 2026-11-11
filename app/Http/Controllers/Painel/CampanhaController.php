<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Payments;
use App\Support\Auditoria;
use App\Support\Imagem;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CampanhaController extends Controller
{
    public const TRANSICOES = [
        Campaign::RASCUNHO => [Campaign::EM_BREVE, Campaign::ATIVA],
        Campaign::EM_BREVE => [Campaign::ATIVA, Campaign::RASCUNHO],
        Campaign::ATIVA => [Campaign::PAUSADA, Campaign::ENCERRADA],
        Campaign::PAUSADA => [Campaign::ATIVA, Campaign::ENCERRADA],
        Campaign::ENCERRADA => [],
        Campaign::APURADA => [],
    ];

    public const ROTULOS = [
        Campaign::RASCUNHO => 'Rascunho',
        Campaign::EM_BREVE => 'Em breve',
        Campaign::ATIVA => 'Ativa',
        Campaign::PAUSADA => 'Pausada',
        Campaign::ENCERRADA => 'Encerrada',
        Campaign::APURADA => 'Apurada',
    ];

    public function index(): View
    {
        return view('painel.campanhas.index', ['campanhas' => Campaign::latest('id')->get()]);
    }

    public function create(): View
    {
        return view('painel.campanhas.form', ['campanha' => new Campaign(['min_per_order' => 1, 'max_per_order' => 100, 'reservation_minutes' => 15])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $campanha = new Campaign(['status' => Campaign::RASCUNHO]);
        $this->salvar($request, $campanha);
        Auditoria::registrar('campanha.criada', $campanha);

        return redirect()->route('painel.campanhas.edit', $campanha)->with('ok', 'Campanha criada como rascunho.');
    }

    public function edit(Campaign $campanha): View
    {
        return view('painel.campanhas.form', ['campanha' => $campanha->load(['images', 'prizes'])]);
    }

    public function update(Request $request, Campaign $campanha): RedirectResponse
    {
        $this->salvar($request, $campanha);
        Auditoria::registrar('campanha.alterada', $campanha, ['campos' => array_keys($campanha->getChanges())]);

        return redirect()->route('painel.campanhas.edit', $campanha)->with('ok', 'Alterações salvas.');
    }

    public function status(Request $request, Campaign $campanha, Payments $payments): RedirectResponse
    {
        $novo = (string) $request->input('status');

        if (! in_array($novo, self::TRANSICOES[$campanha->status] ?? [], true)) {
            return back()->with('erro', 'Essa mudança de status não é permitida a partir de "'.self::ROTULOS[$campanha->status].'".');
        }

        if ($novo === Campaign::ATIVA) {
            $faltando = $campanha->legalMissing();
            if ($faltando) {
                return back()->with('erro', 'Para ativar, preencha: '.implode(', ', $faltando).'.');
            }
            if ($campanha->images()->doesntExist() || $campanha->prizes()->doesntExist()) {
                return back()->with('erro', 'Para ativar, a campanha precisa de pelo menos uma foto e um prêmio.');
            }
        }

        $anterior = $campanha->status;
        $campanha->update(['status' => $novo]);

        $liberados = $novo === Campaign::ENCERRADA ? $payments->releaseAll($campanha, 'Vendas encerradas') : 0;
        Auditoria::registrar('campanha.status', $campanha, ['de' => $anterior, 'para' => $novo, 'pedidos_liberados' => $liberados]);

        return back()->with('ok', 'Status alterado para "'.self::ROTULOS[$novo].'".');
    }

    private function salvar(Request $request, Campaign $campanha): void
    {
        $travada = $campanha->exists && ! in_array($campanha->status, [Campaign::RASCUNHO, Campaign::EM_BREVE], true);

        $dados = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('campaigns', 'slug')->ignore($campanha->id)],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => [$travada ? 'nullable' : 'required', 'regex:/^\d{1,6}([.,]\d{1,2})?$/'],
            'total_numbers' => [$travada ? 'nullable' : 'required', 'integer', 'min:10', 'max:10000000'],
            'min_per_order' => ['required', 'integer', 'min:1', 'max:10000'],
            'max_per_order' => ['required', 'integer', 'gte:min_per_order', 'max:10000'],
            'reservation_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'packages' => ['nullable', 'array', 'max:6'],
            'packages.*' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'featured' => ['nullable', 'integer'],
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'draw_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'draw_criterion' => ['nullable', 'string', 'max:2000'],
            'delivery_deadline' => ['nullable', 'string', 'max:200'],
            'legal_company' => ['nullable', 'string', 'max:160'],
            'legal_cnpj' => ['nullable', 'string', 'max:18'],
            'legal_modality' => ['nullable', Rule::in(array_keys(Campaign::MODALIDADES))],
            'legal_process' => ['nullable', 'string', 'max:80'],
            'legal_capitalization_company' => ['nullable', 'string', 'max:160'],
            'legal_beneficiary' => ['nullable', 'string', 'max:160'],
            'regulation' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'photos' => ['nullable', 'array', 'max:12'],
            'photos.*' => ['image', 'mimes:jpeg,png,webp', 'max:8192'],
            'photo_position' => ['nullable', 'array'],
            'photo_position.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'photo_alt' => ['nullable', 'array'],
            'photo_alt.*' => ['nullable', 'string', 'max:160'],
            'photo_remove' => ['nullable', 'array'],
            'prize_title' => ['nullable', 'array', 'max:20'],
            'prize_title.*' => ['nullable', 'string', 'max:160'],
            'prize_description' => ['nullable', 'array'],
            'prize_description.*' => ['nullable', 'string', 'max:300'],
        ]);

        $cnpj = preg_replace('/\D/', '', (string) ($dados['legal_cnpj'] ?? ''));
        if ($cnpj !== '' && strlen($cnpj) !== 14) {
            throw ValidationException::withMessages(['legal_cnpj' => 'O CNPJ precisa ter 14 dígitos.']);
        }

        $pacotes = [];
        foreach ($dados['packages'] ?? [] as $i => $qty) {
            if ($qty) {
                $pacotes[] = ['qty' => (int) $qty, 'featured' => (int) ($dados['featured'] ?? -1) === $i];
            }
        }

        $campanha->fill([
            'title' => $dados['title'],
            'slug' => ($dados['slug'] ?? null) ?: $this->slug($dados['title'], $campanha),
            'subtitle' => $dados['subtitle'] ?? null,
            'description' => $dados['description'] ?? null,
            'min_per_order' => $dados['min_per_order'],
            'max_per_order' => $dados['max_per_order'],
            'reservation_minutes' => $dados['reservation_minutes'],
            'packages' => $pacotes ?: null,
            'starts_at' => $this->data($dados['starts_at'] ?? null),
            'draw_at' => $this->data($dados['draw_at'] ?? null),
            'draw_criterion' => $dados['draw_criterion'] ?? null,
            'delivery_deadline' => $dados['delivery_deadline'] ?? null,
            'legal_company' => $dados['legal_company'] ?? null,
            'legal_cnpj' => $cnpj ?: null,
            'legal_modality' => $dados['legal_modality'] ?? null,
            'legal_process' => $dados['legal_process'] ?? null,
            'legal_capitalization_company' => $dados['legal_capitalization_company'] ?? null,
            'legal_beneficiary' => $dados['legal_beneficiary'] ?? null,
        ]);

        if (! $travada) {
            $campanha->price_cents = (int) round((float) str_replace(',', '.', $dados['price']) * 100);
            $campanha->total_numbers = (int) $dados['total_numbers'];
            $campanha->digits = strlen((string) ($campanha->total_numbers - 1));

            if ($campanha->price_cents < 1) {
                throw ValidationException::withMessages(['price' => 'O preço precisa ser maior que zero.']);
            }
        }

        $campanha->save();

        if ($request->hasFile('regulation')) {
            if ($campanha->legal_regulation_path) {
                Storage::disk('public')->delete($campanha->legal_regulation_path);
            }
            $campanha->update(['legal_regulation_path' => $request->file('regulation')->storeAs("campanhas/{$campanha->id}", 'regulamento-'.bin2hex(random_bytes(6)).'.pdf', 'public')]);
        }

        $this->fotos($request, $campanha, $dados);
        $this->premios($campanha, $dados);
    }

    private function fotos(Request $request, Campaign $campanha, array $dados): void
    {
        foreach ($campanha->images()->get() as $image) {
            if (isset($dados['photo_remove'][$image->id])) {
                Storage::disk('public')->delete($image->path);
                $image->delete();

                continue;
            }

            $image->update([
                'position' => (int) ($dados['photo_position'][$image->id] ?? $image->position),
                'alt' => $dados['photo_alt'][$image->id] ?? $image->alt,
            ]);
        }

        $next = (int) $campanha->images()->max('position') + 1;
        foreach ($request->file('photos', []) as $file) {
            $saved = Imagem::salvar($file, "campanhas/{$campanha->id}");
            $campanha->images()->create($saved + ['position' => $next++, 'alt' => $campanha->title]);
        }
    }

    private function premios(Campaign $campanha, array $dados): void
    {
        if (! array_key_exists('prize_title', $dados)) {
            return;
        }

        $campanha->prizes()->delete();
        $position = 1;

        foreach ($dados['prize_title'] as $i => $title) {
            if (filled($title)) {
                $campanha->prizes()->create([
                    'position' => $position++,
                    'title' => $title,
                    'description' => $dados['prize_description'][$i] ?? null,
                ]);
            }
        }
    }

    private function slug(string $title, Campaign $campanha): string
    {
        $base = Str::slug($title) ?: 'campanha';
        $slug = $base;
        $n = 2;

        while (Campaign::where('slug', $slug)->where('id', '!=', $campanha->id ?? 0)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    private function data(?string $value): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, 'America/Sao_Paulo')->utc() : null;
    }
}
