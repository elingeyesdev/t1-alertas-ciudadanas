<?php

namespace App\Services;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Report;
use App\Models\User;

class CommentService
{
    public function create(int $reportId, array $data): CommentResource
    {
        $creator = User::find($data['creatorId']);
        if (! $creator) {
            abort(404, 'Usuario no encontrado');
        }
        $report = Report::find($reportId);
        if (! $report) {
            abort(404, 'Reporte no encontrado');
        }

        $comment = Comment::create([
            'text' => $data['text'],
            'report_id' => $report->id,
            'creator_id' => $creator->id,
        ]);
        $report->touch(); // actualiza updated_at del reporte (como hacía Nest)

        return new CommentResource($comment->load(['creator', 'parentComment']));
    }

    public function reply(int $parentCommentId, array $data): CommentResource
    {
        $parent = Comment::find($parentCommentId);
        if (! $parent) {
            abort(404, 'Comentario no encontrado');
        }
        if ($parent->parent_comment_id !== null) {
            abort(400, 'No se puede responder una respuesta');
        }
        $creator = User::find($data['creatorId']);
        if (! $creator) {
            abort(404, 'Usuario no encontrado');
        }

        $comment = Comment::create([
            'text' => $data['text'],
            'parent_comment_id' => $parent->id,
            'report_id' => $parent->report_id,
            'creator_id' => $creator->id,
        ]);
        Report::whereKey($parent->report_id)->update(['updated_at' => now()]);

        return new CommentResource($comment->load(['creator', 'parentComment']));
    }

    public function findByReport(int $reportId)
    {
        if (! Report::whereKey($reportId)->exists()) {
            abort(404, "El reporte con ID {$reportId} no se encontro");
        }

        return CommentResource::collection(
            Comment::with('creator')
                ->withCount('responses')
                ->where('report_id', $reportId)
                ->whereNull('parent_comment_id')
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function findReplies(int $commentId)
    {
        if (! Comment::whereKey($commentId)->exists()) {
            abort(404, "El comentario con ID {$commentId} no se encontro");
        }

        return CommentResource::collection(
            Comment::with(['creator', 'parentComment'])
                ->where('parent_comment_id', $commentId)
                ->orderByDesc('created_at')
                ->get()
        );
    }
}
