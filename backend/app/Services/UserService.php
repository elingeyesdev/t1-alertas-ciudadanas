<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        private readonly AuthorityProfileService $profiles,
        private readonly MailService $mail,
    ) {}

    public function create(array $data): User
    {
        $role = Role::find($data['roleId']);
        if (! $role) {
            abort(404, 'Rol no encontrado');
        }

        if (User::withTrashed()->where('phone', $data['phone'])->exists()) {
            abort(400, "Ya hay un usuario registrado con el numero \"{$data['phone']}\"");
        }

        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'password' => $data['password'], // se hashea automáticamente (cast "hashed")
            'role_id' => $role->id,
        ]);
    }

    public function createAuthUser(array $data): UserResource
    {
        $user = DB::transaction(function () use ($data) {
            $user = $this->create($data);
            $this->profiles->create($user, [
                'ci' => $data['ci'],
                'gmail' => $data['gmail'],
                'profile_type' => $data['profile_type'],
            ]);

            return $user;
        });

        // Si el correo falla solo se registra en el log (igual que en Nest)
        $this->mail->sendUserCredentials($data['gmail'], $data['phone'], $data['password']);

        return new UserResource($user->load(['role', 'authorityProfile']));
    }

    public function findAll()
    {
        return UserResource::collection(User::with(['role', 'authorityProfile'])->get());
    }

    public function findOne(string $id): UserResource
    {
        return new UserResource($this->findOrFail($id));
    }

    public function findByPhone(string $phone): ?User
    {
        return User::with(['role', 'authorityProfile'])->where('phone', $phone)->first();
    }

    public function update(string $id, array $data): UserResource
    {
        $user = $this->findOrFail($id);
        $user->fill($data)->save();

        return new UserResource($user);
    }

    public function updateFcmToken(string $id, ?string $fcmToken): array
    {
        $user = User::find($id);
        if (! $user) {
            abort(404, "El user con ID {$id} no se encontró");
        }

        // Un token FCM pertenece a un solo dispositivo: se quita de otros usuarios
        if ($fcmToken) {
            User::where('fcm_token', $fcmToken)->where('id', '!=', $id)->update(['fcm_token' => null]);
        }

        $user->forceFill(['fcm_token' => $fcmToken])->save();

        return ['message' => 'Token FCM actualizado correctamente'];
    }

    public function updateLocation(string $id, float $latitude, float $longitude): array
    {
        $user = User::find($id);
        if (! $user) {
            abort(404, "El user con ID {$id} no se encontró");
        }

        $user->forceFill(['last_latitude' => $latitude, 'last_longitude' => $longitude])->save();

        return ['message' => 'Ubicación actualizada correctamente'];
    }

    public function updatePassword(string $id, string $current, string $new): array
    {
        $user = User::find($id);
        if (! $user) {
            abort(404, "El user con ID {$id} no se encontro");
        }

        if (! Hash::check($current, $user->password)) {
            abort(400, 'La contraseña actual es incorrecta');
        }

        $user->password = $new;
        $user->save();

        return ['message' => 'contrasena actualizada correctamente'];
    }

    public function remove(string $id): array
    {
        $user = User::find($id);
        if (! $user) {
            abort(404, "El user con ID {$id} no se encontro");
        }
        $user->delete(); // soft delete

        return ['message' => 'Usuario eliminado correctamente'];
    }

    private function findOrFail(string $id): User
    {
        $user = User::with(['role', 'authorityProfile'])->find($id);
        if (! $user) {
            abort(404, "El user con ID {$id} no se encontro");
        }

        return $user;
    }
}
