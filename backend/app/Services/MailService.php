<?php

namespace App\Services;

use App\Mail\AuthorityCredentialsMail;
use App\Mail\UserMessageMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class MailService
{
    /** Envía credenciales. Si falla solo se registra en el log. */
    public function sendUserCredentials(string $email, string $phone, string $plainPassword): void
    {
        try {
            Mail::to($email)->send(new AuthorityCredentialsMail($phone, $plainPassword));
            Log::info("Correo enviado correctamente a {$email}");
        } catch (Throwable $e) {
            Log::error("Error enviando correo a {$email}", ['error' => $e->getMessage()]);
        }
    }

    public function sendMailToUser(string $id, string $subject, string $content): array
    {
        $email = $this->authorityEmail($id, 'El usuario no tiene una dirección de correo configurada.');

        Mail::to($email)->send(new UserMessageMail($subject, $content));
        Log::info("Correo enviado correctamente a {$email}");

        return ['message' => "Correo enviado exitosamente a {$email}"];
    }

    public function resendCredentials(string $id): array
    {
        $email = $this->authorityEmail($id, 'El usuario no tiene un perfil de autoridad o un correo electrónico configurado.');
        $user = User::find($id);

        // Genera una contraseña temporal de 8 caracteres
        $tempPassword = Str::lower(Str::random(8));
        $user->password = $tempPassword;
        $user->save();

        $this->sendUserCredentials($email, $user->phone, $tempPassword);

        return ['message' => "Credenciales reenviadas con éxito al correo {$email}"];
    }

    private function authorityEmail(string $id, string $noEmailMessage): string
    {
        $user = User::with('authorityProfile')->find($id);
        if (! $user) {
            abort(404, "El usuario con ID {$id} no se encontró");
        }
        if (! $user->authorityProfile?->gmail) {
            abort(400, $noEmailMessage);
        }

        return $user->authorityProfile->gmail;
    }
}
