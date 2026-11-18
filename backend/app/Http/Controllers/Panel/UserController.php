<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ProfileType;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthorityProfileService;
use App\Services\MailService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Módulo "Usuarios": ciudadanos, autoridades y administradores. */
class UserController extends PanelController
{
    public function __construct(
        private readonly UserService $users,
        private readonly AuthorityProfileService $profiles,
        private readonly MailService $mail,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', Rule::in(['usuario', 'autoridad', 'admin'])],
        ]);

        $users = User::with(['role', 'authorityProfile'])
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('first_name', 'ilike', "%{$s}%")
                ->orWhere('last_name', 'ilike', "%{$s}%")
                ->orWhere('phone', 'ilike', "%{$s}%")))
            ->when($filters['rol'] ?? null, fn ($q, $r) => $q->whereHas('role', fn ($w) => $w->where('name', $r)))
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        $counts = User::join('roles', 'roles.id', '=', 'users.role_id')
            ->selectRaw('roles.name, count(*) as total')->groupBy('roles.name')->pluck('total', 'name');

        return view('panel.usuarios.index', [
            'users' => $users,
            'filters' => $filters,
            'stats' => [
                'total' => $counts->sum(),
                'ciudadanos' => $counts['usuario'] ?? 0,
                'autoridades' => $counts['autoridad'] ?? 0,
                'admins' => $counts['admin'] ?? 0,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('panel.usuarios.form', [
            'editUser' => null,
            'defaultRole' => $request->query('rol', 'autoridad'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isAuthority = $request->input('role') === 'autoridad';
        $data = $request->validate([
            'role' => ['required', Rule::in(['usuario', 'autoridad', 'admin'])],
            ...$this->personRules(),
            'password' => ['required', 'string', 'min:6', 'max:20', 'regex:/^\S+$/'],
            ...($isAuthority ? $this->profileRules(true) : []),
        ], $this->messages());

        $data['roleId'] = Role::where('name', $data['role'])->value('id');

        return $this->run(
            fn () => $isAuthority ? $this->users->createAuthUser($data) : $this->users->create($data),
            $isAuthority ? 'Autoridad creada. Se enviaron las credenciales a su correo.' : 'Usuario creado.',
            route('panel.usuarios.index')
        );
    }

    public function edit(string $id): View
    {
        return view('panel.usuarios.form', [
            'editUser' => User::with(['role', 'authorityProfile'])->findOrFail($id),
            'defaultRole' => null,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $user = User::with(['role', 'authorityProfile'])->findOrFail($id);
        $data = $request->validate([
            ...$this->personRules($user->id),
            ...($user->authorityProfile ? $this->profileRules(false) : []),
        ], $this->messages());

        return $this->run(function () use ($user, $data) {
            DB::transaction(function () use ($user, $data) {
                $this->users->update($user->id, collect($data)->only(['first_name', 'last_name', 'phone'])->all());
                if ($user->authorityProfile) {
                    $this->profiles->updateByUserId($user->id, collect($data)->only(['ci', 'gmail', 'profile_type'])->all());
                }
            });
        }, 'Datos actualizados.', route('panel.usuarios.index'));
    }

    public function destroy(string $id): RedirectResponse
    {
        if ($id === Auth::guard('web')->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        return $this->run(fn () => $this->users->remove($id), 'Usuario eliminado.');
    }

    public function sendMail(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:5000'],
        ], ['subject.required' => 'Escribe el asunto.', 'content.required' => 'Escribe el mensaje.']);

        return $this->run(fn () => $this->mail->sendMailToUser($id, $data['subject'], $data['content']), 'Correo enviado.');
    }

    public function resendCredentials(string $id): RedirectResponse
    {
        return $this->run(fn () => $this->mail->resendCredentials($id), 'Se generó una contraseña nueva y se envió por correo.');
    }

    private function personRules(?string $ignoreId = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits:8', Rule::unique('users', 'phone')->ignore($ignoreId)],
        ];
    }

    private function profileRules(bool $required): array
    {
        $req = $required ? 'required' : 'sometimes';

        return [
            'ci' => [$req, 'string', 'max:255'],
            'gmail' => [$req, 'email', 'max:255'],
            'profile_type' => [$req, Rule::enum(ProfileType::class)],
        ];
    }

    private function messages(): array
    {
        return [
            'phone.digits' => 'El teléfono debe tener exactamente 8 dígitos.',
            'phone.unique' => 'Ya hay un usuario registrado con ese teléfono.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
            'password.max' => 'La contraseña no debe pasar de 20 caracteres.',
            'password.regex' => 'La contraseña no debe tener espacios.',
            'gmail.email' => 'El correo no es válido.',
            'profile_type.enum' => 'Elige policía, bombero o paramédico.',
        ];
    }
}
