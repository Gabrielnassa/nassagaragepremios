<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Campaign extends Model
{
    public const RASCUNHO = 'rascunho';

    public const EM_BREVE = 'em_breve';

    public const ATIVA = 'ativa';

    public const PAUSADA = 'pausada';

    public const ENCERRADA = 'encerrada';

    public const APURADA = 'apurada';

    public const MODALIDADES = [
        'capitalizacao' => 'Título de capitalização — filantropia premiável',
        'sorteio_filantropico' => 'Sorteio filantrópico',
        'promocao_comercial' => 'Promoção comercial',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['instant_salt'];

    protected function casts(): array
    {
        return [
            'packages' => 'array',
            'starts_at' => 'datetime',
            'draw_at' => 'datetime',
            'drawn_at' => 'datetime',
            'instant_revealed_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(CampaignImage::class)->orderBy('position');
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(Prize::class)->orderBy('position');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function instantPrizes(): HasMany
    {
        return $this->hasMany(InstantPrize::class)->orderBy('number');
    }

    public function timedPromos(): HasMany
    {
        return $this->hasMany(TimedPromo::class);
    }

    public function mainWinner(): HasOne
    {
        return $this->hasOne(Winner::class)->where('type', 'principal')->whereNotNull('published_at');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', '!=', self::RASCUNHO);
    }

    public function remaining(): int
    {
        return max(0, $this->total_numbers - $this->sold_count - $this->reserved_count);
    }

    public function percentSold(): float
    {
        if ($this->total_numbers === 0) {
            return 0.0;
        }

        return floor($this->sold_count / $this->total_numbers * 10000) / 100;
    }

    public function formatNumber(int $number): string
    {
        return str_pad((string) $number, $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Estado exibido ao público, derivado do status e do estoque.
     */
    public function publicState(): string
    {
        return match ($this->status) {
            self::EM_BREVE => 'em_breve',
            self::PAUSADA => 'pausada',
            self::ENCERRADA => 'aguardando',
            self::APURADA => 'concluida',
            self::ATIVA => $this->sold_count >= $this->total_numbers ? 'esgotada' : 'ativa',
            default => 'indisponivel',
        };
    }

    public function isOnSale(): bool
    {
        return $this->status === self::ATIVA && $this->remaining() > 0;
    }

    public function maxPurchasable(): int
    {
        return min($this->max_per_order, $this->remaining());
    }

    public function shortcuts(): array
    {
        $packages = $this->packages ?: [
            ['qty' => 1], ['qty' => 2, 'featured' => true], ['qty' => 3],
            ['qty' => 5], ['qty' => 10], ['qty' => 20],
        ];

        return array_map(fn ($p) => ['qty' => (int) $p['qty'], 'featured' => ! empty($p['featured'])], $packages);
    }

    public function legalMissing(): array
    {
        $required = [
            'legal_company' => 'Razão social do responsável',
            'legal_cnpj' => 'CNPJ do responsável',
            'legal_modality' => 'Modalidade',
            'legal_process' => 'Número do processo ou certificado',
            'legal_regulation_path' => 'Regulamento em PDF',
            'draw_criterion' => 'Critério de apuração',
            'delivery_deadline' => 'Prazo de entrega do prêmio',
        ];

        if ($this->legal_modality === 'capitalizacao') {
            $required['legal_capitalization_company'] = 'Sociedade de capitalização';
        }
        if (in_array($this->legal_modality, ['capitalizacao', 'sorteio_filantropico'], true)) {
            $required['legal_beneficiary'] = 'Entidade beneficiada';
        }

        return array_values(array_filter($required, fn ($label, $field) => blank($this->{$field}), ARRAY_FILTER_USE_BOTH));
    }

    public function formattedCnpj(): ?string
    {
        if (! $this->legal_cnpj || strlen($this->legal_cnpj) !== 14) {
            return $this->legal_cnpj;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $this->legal_cnpj);
    }
}
