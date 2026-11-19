<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrustContactRequest;
use App\Services\TrustCircle;
use Illuminate\Http\Request;

/** HU-25 Círculo de confianza. Cada persona solo maneja su propio círculo. */
class TrustCircleController extends Controller
{
    public function __construct(private readonly TrustCircle $circle) {}

    public function index(Request $request)
    {
        return response()->json($this->circle->overview($request->user()));
    }

    public function store(TrustContactRequest $request)
    {
        $contact = $this->circle->addManual($request->user(), $request->input('name'), $request->input('phone'));

        return response()->json($this->circle->contactData($contact), 201);
    }

    public function update(TrustContactRequest $request, int $id)
    {
        $contact = $this->circle->update($request->user(), $id, $request->validated());

        return response()->json($this->circle->contactData($contact));
    }

    public function destroy(Request $request, int $id)
    {
        $this->circle->remove($request->user(), $id);

        return response()->noContent();
    }

    public function invite(Request $request)
    {
        $invite = $this->circle->createInvite($request->user());

        return response()->json([
            'code' => $invite->code,
            'expires_at' => $invite->expires_at->toISOString(),
            'share_text' => "Únete a mi círculo de confianza en Avispáme: abre la app, entra a Círculo de confianza → «Tengo un código» y escribe {$invite->code}. Así te aviso con mi ubicación si activo el botón de pánico.",
        ], 201);
    }

    public function join(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:12']], ['code.required' => 'Escribe el código de invitación.']);
        $contact = $this->circle->join($request->user(), $request->input('code'));
        $contact->load('owner');

        return response()->json([
            'id' => $contact->id,
            'owner_name' => trim($contact->owner->first_name.' '.$contact->owner->last_name),
        ], 201);
    }

    public function leave(Request $request, int $id)
    {
        $this->circle->leave($request->user(), $id);

        return response()->noContent();
    }
}
