<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResearchCoordinatorController extends Controller
{
    public function index(Request $request): View
    {
        $coordinator = $request->user();

        $members = User::query()
            ->select(['id', 'name', 'email', 'avatar'])
            ->when(
                $coordinator->college,
                fn ($query) => $query->where('college', $coordinator->college)->whereKeyNot($coordinator->getKey()),
                fn ($query) => $query->whereKey(-1),
            )
            ->orderBy('name')
            ->paginate(20);

        return view('research_coordinator.dashboard', [
            'coordinator' => $coordinator,
            'members' => $members,
        ]);
    }
}
