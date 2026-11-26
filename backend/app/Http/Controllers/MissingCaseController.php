<?php

namespace App\Http\Controllers;

use App\Http\Requests\MissingCaseContributionRequest;
use App\Http\Requests\MissingCaseRequest;
use App\Http\Resources\MissingCaseContributionResource;
use App\Http\Resources\MissingCaseResource;
use App\Models\MissingCase;
use App\Services\MissingCases;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MissingCaseController extends Controller
{
    public function __construct(private readonly MissingCases $cases) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['kind' => ['sometimes', Rule::in(array_keys(MissingCase::KINDS))]]);
        $cases = MissingCase::visibleOnWall()
            ->when($filters['kind'] ?? null, fn ($query, $kind) => $query->where('kind', $kind))
            ->latest('published_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return MissingCaseResource::collection($cases);
    }

    public function store(MissingCaseRequest $request): JsonResponse
    {
        $case = $this->cases->create($request->validated(), $request->user());

        return response()->json(['id' => $case->id, 'status' => $case->status], 201);
    }

    public function map(): JsonResponse
    {
        return response()->json(MissingCase::onMap()->orderBy('id')
            ->get(['id', 'name', 'kind', 'status', 'latitude', 'longitude']));
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        return MissingCaseResource::collection(
            MissingCase::where('submitted_by', $request->user()->id)->latest('id')->paginate(15)
        );
    }

    public function contributions(int $id): AnonymousResourceCollection
    {
        $case = MissingCase::visibleOnWall()->findOrFail($id);

        return MissingCaseContributionResource::collection($case->contributions()
            ->with('author:id,first_name')->orderByDesc('id')->paginate(15));
    }

    public function storeContribution(MissingCaseContributionRequest $request, int $id): JsonResponse
    {
        $case = MissingCase::visibleOnWall()->findOrFail($id);
        $item = $this->cases->contribute($case, $request->validated(), $request->user());

        return (new MissingCaseContributionResource($item))->response()->setStatusCode(201);
    }

    public function contributionPhoto(int $id, int $contribution, int $index): StreamedResponse
    {
        $case = MissingCase::visibleOnWall()->findOrFail($id);

        return $this->cases->photo($case->contributions()->findOrFail($contribution), $index);
    }

    public function contributionVideo(int $id, int $contribution): BinaryFileResponse
    {
        $case = MissingCase::visibleOnWall()->findOrFail($id);

        return $this->cases->video($case->contributions()->findOrFail($contribution));
    }

    public function show(Request $request, int $id): MissingCaseResource
    {
        $case = MissingCase::visibleTo($request->user())->findOrFail($id);
        $media = $case->contributions()
            ->where(fn ($query) => $query->whereRaw('json_array_length(photos) > 0')->orWhereNotNull('video_path'))
            ->latest('id')->get(['id', 'photos', 'video_path']);

        return new MissingCaseResource($case->setRelation('mediaContributions', $media));
    }

    public function photo(Request $request, int $id, int $index): StreamedResponse
    {
        return $this->cases->photo(MissingCase::visibleTo($request->user())->findOrFail($id), $index);
    }
}
