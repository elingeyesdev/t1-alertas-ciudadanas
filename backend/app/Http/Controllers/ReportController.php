<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCommentRequest;
use App\Http\Requests\CreateReportRequest;
use App\Http\Requests\ImageUploadRequest;
use App\Http\Requests\NearbyReportsRequest;
use App\Http\Requests\SimilarReportsRequest;
use App\Models\Report;
use App\Services\CaseTracking;
use App\Services\CommentService;
use App\Services\ReportService;
use App\Services\ReportVoting;
use App\Support\Dt;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly CommentService $comments,
    ) {}

    /** POST /api/reports (multipart/form-data) */
    public function store(CreateReportRequest $request)
    {
        // El creador es siempre el usuario del token; un "userId" enviado por el cliente se ignora.
        $data = ['userId' => $request->user()->id] + $request->validated();

        return $this->reports->create($data, $request->file('image'))
            ->response()->setStatusCode(201);
    }

    public function storeComment(CreateCommentRequest $request, int $id)
    {
        $data = ['creatorId' => $request->user()->id] + $request->validated();

        return $this->comments->create($id, $data)->response()->setStatusCode(201);
    }

    /**
     * POST /api/reports/{id}/images. La ruta antigua .../images/{userId} sigue
     * funcionando para no romper la app, pero ese id se ignora: la foto queda a
     * nombre del usuario del token.
     */
    public function uploadImage(ImageUploadRequest $request, int $id)
    {
        return $this->reports->addImage($id, $request->user()->id, $request->file('image'))
            ->response()->setStatusCode(201);
    }

    public function verify(int $id)
    {
        return $this->reports->verifyReport($id);
    }

    public function uploadVideo(Request $request, int $id)
    {
        $request->validate(['video' => ['required', 'file', 'mimetypes:video/mp4', 'max:20480']]);
        $report = Report::findOrFail($id);
        $path = $request->file('video')->storeAs('report-videos', Str::uuid().'.mp4', 'public');
        abort_if($path === false, 500, 'No se pudo guardar el video');

        try {
            $videoId = DB::table('report_videos')->insertGetId([
                'report_id' => $report->id,
                'uploaded_by' => $request->user()->id,
                'path' => $path,
                'uploaded_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return response()->json(['id' => $videoId, 'type' => 'video', 'url' => '/storage/'.$path], 201);
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

    public function vote(Request $request, int $id, ReportVoting $voting)
    {
        $data = $request->validate(['vote' => ['required', 'string', 'in:seen,false']]);

        return response()->json($voting->cast($id, $request->user(), $data['vote']));
    }

    public function show(Request $request, int $id, ReportVoting $voting)
    {
        $detail = $this->reports->findOne($id);
        $report = $detail->resource;
        $report->load('dispatches.destination', 'dispatches.attendedBy.authorityProfile');

        $photos = $report->images->map(fn ($image) => [
            'id' => $image->id,
            'type' => 'image',
            'url' => str_starts_with($image->cloudinary_id ?? '', 'local:')
                ? '/storage/'.substr($image->cloudinary_id, 6) : $image->url,
            'uploaded_at' => Dt::iso($image->uploaded_at),
        ]);
        $videos = DB::table('report_videos')->where('report_id', $id)->get()
            ->map(fn ($video) => [
                'id' => $video->id,
                'type' => 'video',
                'url' => '/storage/'.$video->path,
                'uploaded_at' => Dt::iso(Carbon::parse($video->uploaded_at)),
            ]);
        $assignments = $report->dispatches->map(fn ($dispatch) => [
            'type' => $dispatch->attendedBy?->authorityProfile?->profile_type?->value
                ?? $dispatch->destination?->installation_type?->value,
            'unit' => $dispatch->destination?->name,
            'state' => $dispatch->state?->value,
        ])->values();
        $report->unsetRelation('dispatches');

        return response()->json($detail->resolve(request()) + [
            'evidence' => $photos->concat($videos)->sortBy(['uploaded_at', 'type', 'id'])->values(),
            'assignments' => $assignments,
            'voting' => $voting->summary($report, $request->user()),
        ]);
    }

    /**
     * GET /api/reports/{id}/case — HU-26 seguimiento del caso.
     * Solo quien creó el reporte (o un administrador) ve la línea de tiempo
     * y el detalle de las unidades despachadas.
     */
    public function caseTracking(Request $request, int $id, CaseTracking $cases)
    {
        $report = Report::withTrashed()->find($id);
        if (! $report) {
            abort(404, 'Reporte no encontrado');
        }
        $user = $request->user();
        abort_unless($report->creator_id === $user->id || $user->hasRole('admin'), 403, 'Solo quien creó el reporte puede ver su seguimiento');

        return response()->json($cases->summary($report));
    }

    public function comments(int $id)
    {
        return $this->comments->findByReport($id);
    }

    public function resolve(Request $request, int $id)
    {
        $this->ensureCreatorOrStaff($request, $id);

        return $this->reports->resolveReport($id);
    }

    public function reactivate(Request $request, int $id)
    {
        $this->ensureCreatorOrStaff($request, $id);

        return $this->reports->reactivateReport($id);
    }

    public function destroy(Request $request, int $id)
    {
        $this->ensureCreatorOrStaff($request, $id);

        return $this->reports->remove($id);
    }

    /**
     * Resolver, reactivar o eliminar: solo quien creó el reporte, una autoridad
     * o un administrador. Si el reporte no existe, el servicio responde 404.
     */
    private function ensureCreatorOrStaff(Request $request, int $id): void
    {
        $report = Report::withTrashed()->find($id, ['id', 'creator_id']);
        if (! $report || $this->isStaff($request->user())) {
            return;
        }

        abort_unless($report->creator_id === $request->user()->id, 403, 'Solo quien creó el reporte, una autoridad o un administrador puede hacerlo');
    }
}
