<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCommentRequest;
use App\Services\CommentService;

class CommentController extends Controller
{
    public function __construct(private readonly CommentService $comments) {}

    public function storeReply(CreateCommentRequest $request, int $id)
    {
        return $this->comments->reply($id, $request->validated())->response()->setStatusCode(201);
    }

    public function replies(int $id)
    {
        return $this->comments->findReplies($id);
    }
}
