<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\TimedPromo;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function show(Campaign $campaign): View
    {
        abort_if($campaign->status === Campaign::RASCUNHO, 404);

        $campaign->load(['images', 'prizes', 'mainWinner', 'instantPrizes.ticket.order.customer']);
        $promo = $this->runningPromo($campaign);

        return view('campanha.show', [
            'campaign' => $campaign,
            'state' => $campaign->publicState(),
            'promo' => $promo,
            'leader' => $promo?->leader(),
            'demo' => $campaign->is_demo,
        ]);
    }

    public function live(Campaign $campaign): JsonResponse
    {
        abort_if($campaign->status === Campaign::RASCUNHO, 404);

        $promo = $this->runningPromo($campaign);
        $leader = $promo?->leader();

        return response()->json([
            'percent' => $campaign->percentSold(),
            'percent_label' => number_format($campaign->percentSold(), 2, ',', '.').'%',
            'remaining' => $campaign->remaining(),
            'remaining_label' => termo('restantes', ['n' => number_format($campaign->remaining(), 0, ',', '.')]),
            'max' => $campaign->isOnSale() ? $campaign->maxPurchasable() : 0,
            'leader' => $leader ? [
                'value' => $leader['value'],
                'name' => $leader['name'],
                'location' => $leader['location'],
                'ago' => $leader['at']->diffForHumans(),
            ] : null,
        ])->header('Cache-Control', 'no-store');
    }

    private function runningPromo(Campaign $campaign): ?TimedPromo
    {
        if ($campaign->status !== Campaign::ATIVA) {
            return null;
        }

        return $campaign->timedPromos()
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->oldest('ends_at')
            ->first()
            ?->setRelation('campaign', $campaign);
    }
}
