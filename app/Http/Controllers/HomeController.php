<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Winner;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $campaigns = Campaign::published()
            ->with(['images', 'mainWinner'])
            ->latest('id')
            ->get()
            ->groupBy(fn (Campaign $c) => match ($c->status) {
                Campaign::ATIVA, Campaign::PAUSADA => 'ativas',
                Campaign::EM_BREVE => 'em_breve',
                default => 'encerradas',
            });

        return view('home', [
            'ativas' => $campaigns->get('ativas', collect()),
            'emBreve' => $campaigns->get('em_breve', collect()),
            'encerradas' => $campaigns->get('encerradas', collect()),
            'ganhadores' => Winner::with('campaign')->whereNotNull('published_at')->latest('awarded_on')->limit(8)->get(),
            'demo' => $campaigns->flatten()->contains('is_demo', true),
        ]);
    }
}
