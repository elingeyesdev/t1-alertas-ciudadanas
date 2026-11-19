<?php

namespace App\Services;

use App\Enums\StateReport;
use App\Models\Report;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Facades\DB;

class ReportVoting
{
    public function summary(Report $report, ?User $user = null): array
    {
        $votes = DB::table('report_votes')->where('report_id', $report->id);
        $counts = (clone $votes)->selectRaw('vote, count(*) as total')->groupBy('vote')->pluck('total', 'vote');
        $own = $user ? $votes->where('user_id', $user->id)->value('vote') : null;
        $reason = $user ? $this->ineligibility($report, $user) : null;

        return [
            'seen' => (int) ($counts['seen'] ?? 0),
            'false' => (int) ($counts['false'] ?? 0),
            'own_vote' => $own,
            'can_vote' => $user !== null && $reason === null,
            'reason' => $reason,
            'weight' => (int) $report->weight,
        ];
    }

    public function cast(int $reportId, User $user, string $vote): array
    {
        return DB::transaction(function () use ($reportId, $user, $vote) {
            $report = Report::withTrashed()->lockForUpdate()->findOrFail($reportId);
            $votes = DB::table('report_votes');
            $existing = (clone $votes)
                ->where('report_id', $reportId)->where('user_id', $user->id)->value('vote');

            if ($existing === $vote) {
                return $this->summary($report, $user);
            }

            $reason = $this->ineligibility($report, $user);
            abort_if($reason !== null, $reason === 'closed' ? 409 : 403, 'No puedes votar en este reporte.');

            $votes->upsert([
                'report_id' => $reportId, 'user_id' => $user->id, 'vote' => $vote,
            ], ['report_id', 'user_id'], ['vote']);
            $previousWeight = match ($existing) {
                'seen' => 1,
                'false' => -1,
                default => 0,
            };
            $nextWeight = $vote === 'seen' ? 1 : -1;
            $report->update(['weight' => max(0, $report->weight - $previousWeight + $nextWeight)]);

            return $this->summary($report, $user);
        });
    }

    private function ineligibility(Report $report, User $user): ?string
    {
        if (! $user->hasRole('usuario') || $user->id === $report->creator_id) {
            return 'not_eligible';
        }
        if ($report->trashed() || $report->verified || $report->status !== StateReport::Activo || $report->expires_at?->isPast()) {
            return 'closed';
        }
        if ($user->last_latitude === null || $user->last_longitude === null) {
            return 'missing_location';
        }

        $distance = Geo::distance($report->latitude, $report->longitude, $user->last_latitude, $user->last_longitude);

        return $distance <= config('alertas.notifications.nearby_radius_m') ? null : 'outside_radius';
    }
}
