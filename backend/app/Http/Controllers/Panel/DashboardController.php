<?php

namespace App\Http\Controllers\Panel;

use App\Enums\StateReport;
use App\Enums\StateType;
use App\Models\Dispatch;
use App\Models\EmergencyStation;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends PanelController
{
    public function index(): View
    {
        $active = Report::where('status', StateReport::Activo);

        $stats = [
            'activas' => (clone $active)->count(),
            'sinVerificar' => (clone $active)->where('verified', false)->count(),
            'resueltas' => Report::withTrashed()->where('status', StateReport::Resuelto)->count(),
            'despachos' => Dispatch::where('state', StateType::EnCurso)->count(),
            'ciudadanos' => User::whereHas('role', fn ($q) => $q->where('name', 'usuario'))->count(),
            'autoridades' => User::whereHas('role', fn ($q) => $q->where('name', 'autoridad'))->count(),
            'estaciones' => EmergencyStation::count(),
        ];

        $latest = Report::withTrashed()->with('type')->latest()->limit(8)->get();

        $byType = Report::withTrashed()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('report_type_id, count(*) as total')
            ->groupBy('report_type_id')
            ->with('type')
            ->orderByDesc('total')
            ->get();

        return view('panel.index', [
            'user' => Auth::guard('web')->user(),
            'stats' => $stats,
            'latest' => $latest,
            'byType' => $byType,
        ]);
    }
}
