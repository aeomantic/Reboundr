<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $jobEvents = Auth::user()
            ->jobEvents()
            ->orderBy('event_datetime')
            ->get();

        return view('dashboard', [
            'jobEvents' => $jobEvents,
        ]);
    }
}