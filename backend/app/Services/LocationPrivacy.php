<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * HU-29 Control de privacidad de la ubicación.
 * Si la persona lo desactiva, se borra su última ubicación guardada y el
 * sistema deja de guardar nuevas, así que no recibe avisos de cercanía.
 */
class LocationPrivacy
{
    public const NOTICE = 'Sin compartir tu ubicación no te llegan avisos de alertas cercanas, de ambulancias ni la sirena de emergencias. El botón SOS sigue funcionando: envía tu ubicación solo en ese momento.';

    public function status(User $user): array
    {
        return [
            'enabled' => (bool) $user->location_sharing,
            'has_saved_location' => $user->last_latitude !== null && $user->last_longitude !== null,
            'notice' => self::NOTICE,
        ];
    }

    public function set(User $user, bool $enabled): array
    {
        DB::transaction(function () use ($user, $enabled) {
            $changes = ['location_sharing' => $enabled];
            if (! $enabled) {
                // Al desactivar se borra la última ubicación guardada…
                $changes += ['last_latitude' => null, 'last_longitude' => null];
                // …y también la posición usada para los avisos de ambulancia.
                DB::table('ambulance_alert_locations')->where('user_id', $user->id)->delete();
            }
            $user->forceFill($changes)->save();
        });

        return $this->status($user->refresh());
    }

    public function allows(User $user): bool
    {
        return (bool) $user->location_sharing;
    }
}
