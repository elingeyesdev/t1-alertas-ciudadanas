<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCommentRequest;
use App\Http\Requests\CreateReportRequest;
use App\Http\Requests\ImageUploadRequest;
use App\Http\Requests\NearbyReportsRequest;
use App\Http\Requests\SimilarReportsRequest;
use App\Services\CommentService;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly CommentService $comments,
    ) {}

    /** POST /api/reports (multipart/form-data) */
    public function store(CreateReportRequest $request)
    {
        return $this->reports->create($request->validated(), $request->file('image'))
            ->response()->setStatusCode(201);
    }

    public function storeComment(CreateCommentRequest $request, int $id)
    {
        return $this->comments->create($id, $request->validated())->response()->setStatusCode(201);
    }

    public function uploadImage(ImageUploadRequest $request, int $id, string $userId)
    {
        return $this->reports->addImage($id, $userId, $request->file('image'))
            ->response()->setStatusCode(201);
    }

    public function verify(int $id)
    {
        return $this->reports->verifyReport($id);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'typeId' => ['nullable', 'integer'],
            'category' => ['nullable', 'string'],
            'status' => ['nullable', 'in:verified,pending'],
            'zone' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'includeDeleted' => ['nullable', 'in:true,false,1,0'],
        ]);

        return $this->reports->findAll($filters);
    }

    public function withDeleted()
    {
        return $this->reports->findAllWithDeleted();
    }

    public function nearby(NearbyReportsRequest $request)
    {
        return $this->reports->findNearby(
            (float) $request->query('latitude'),
            (float) $request->query('longitude'),
            (float) $request->query('radius'),
        );
    }

    public function similars(SimilarReportsRequest $request)
    {
        return $this->reports->findCoincidences(
            (float) $request->query('latitude'),
            (float) $request->query('longitude'),
            (int) $request->query('type'),
        );
    }

    public function byUser(string $userId)
    {
        return $this->reports->findByUserId($userId);
    }

    public function show(int $id)
    {
        return $this->reports->findOne($id);
    }

    public function comments(int $id)
    {
        return $this->comments->findByReport($id);
    }

    public function resolve(int $id)
    {
        return $this->reports->resolveReport($id);
    }

    public function reactivate(int $id)
    {
        return $this->reports->reactivateReport($id);
    }

    public function destroy(int $id)
    {
        return $this->reports->remove($id);
    }
}
