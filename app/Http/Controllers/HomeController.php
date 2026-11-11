<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
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
            'demo' => $campaigns->flatten()->contains('is_demo', true),
        ]);
    }
}
