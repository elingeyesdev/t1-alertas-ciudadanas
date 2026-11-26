<?php

namespace App\Http\Controllers\Panel;

use App\Http\Requests\MissingCaseRequest;
use App\Models\MissingCase;
use App\Services\MissingCases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MissingCaseController extends PanelController
{
    public function __construct(private readonly MissingCases $cases) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'kind' => ['nullable', Rule::in(array_keys(MissingCase::KINDS))],
            'status' => ['nullable', Rule::in(array_keys(MissingCase::STATUSES))],
        ]);
        $cases = MissingCase::query()
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()->orderByDesc('id')->paginate(15)->withQueryString();

        return view('panel.desaparecidos.index', compact('cases', 'filters'));
    }

    public function create(): View
    {
        return view('panel.desaparecidos.create');
    }

    public function store(MissingCaseRequest $request): RedirectResponse
    {
        $case = $this->cases->create($request->validated(), $request->user(), publish: true);

        return redirect()->route('panel.desaparecidos.show', $case)->with('success', 'Caso publicado.');
    }

    public function show(MissingCase $case): View
    {
        return view('panel.desaparecidos.show', [
            'case' => $case->load(['submitter', 'publisher']),
            'contributions' => $case->contributions()->with('author')->orderByDesc('id')->paginate(15),
        ]);
    }

    public function approve(MissingCaseRequest $request, MissingCase $case): RedirectResponse
    {
        return $this->run(fn () => $this->cases->approve($case, $request->validated(), $request->user()), 'Sugerencia publicada.');
    }

    public function reject(MissingCase $case): RedirectResponse
    {
        return $this->run(fn () => $this->cases->reject($case), 'Sugerencia rechazada.');
    }

    public function found(MissingCase $case): RedirectResponse
    {
        return $this->run(fn () => $this->cases->markFound($case), 'Caso marcado como encontrado.');
    }

    public function showPhoto(MissingCase $case, int $index): View
    {
        abort_unless(isset($case->photos[$index]), 404);

        return view('panel.desaparecidos.photo', [
            'case' => $case,
            'imageUrl' => route('panel.desaparecidos.photo-image', [$case, $index]),
            'title' => 'Foto del caso',
        ]);
    }

    public function photo(MissingCase $case, int $index): StreamedResponse
    {
        return $this->cases->photo($case, $index);
    }

    public function showContributionPhoto(MissingCase $case, int $contribution, int $index): View
    {
        $item = $case->contributions()->findOrFail($contribution);
        abort_unless(isset($item->photos[$index]), 404);

        return view('panel.desaparecidos.photo', [
            'case' => $case,
            'imageUrl' => route('panel.desaparecidos.contribution-photo-image', [$case, $item, $index]),
            'title' => 'Foto del aporte',
        ]);
    }

    public function contributionPhoto(MissingCase $case, int $contribution, int $index): StreamedResponse
    {
        return $this->cases->photo($case->contributions()->findOrFail($contribution), $index);
    }

    public function contributionVideo(MissingCase $case, int $contribution): BinaryFileResponse
    {
        return $this->cases->video($case->contributions()->findOrFail($contribution));
    }
}
