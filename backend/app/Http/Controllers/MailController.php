<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMailRequest;
use App\Services\MailService;

class MailController extends Controller
{
    public function __construct(private readonly MailService $mail) {}

    public function send(SendMailRequest $request, string $id)
    {
        return response()->json($this->mail->sendMailToUser($id, $request->input('subject'), $request->input('content')), 201);
    }

    public function resendCredentials(string $id)
    {
        return response()->json($this->mail->resendCredentials($id), 201);
    }
}
