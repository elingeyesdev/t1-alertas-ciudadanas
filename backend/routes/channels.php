<?php

/*
| Canales de WebSocket (Laravel Reverb).
| "reports" y "trackings" son canales PÚBLICOS, por eso no necesitan
| autorización aquí. Si en el futuro se usan canales privados, se
| registran con Broadcast::channel('nombre', fn ($user) => ...).
*/
