package com.tuempresa.appalertas.app_alertas;

import io.flutter.embedding.android.FlutterActivity;
import io.flutter.embedding.engine.FlutterEngine;
import io.flutter.plugin.common.MethodChannel;
import android.media.AudioAttributes;
import android.media.MediaPlayer;

public class MainActivity extends FlutterActivity {
    private MediaPlayer proximitySiren;

    @Override
    public void configureFlutterEngine(FlutterEngine engine) {
        super.configureFlutterEngine(engine);
        new MethodChannel(engine.getDartExecutor().getBinaryMessenger(), "alertas/ambulance_audio")
            .setMethodCallHandler((call, result) -> {
                if (!"play".equals(call.method)) {
                    result.notImplemented();
                    return;
                }
                try {
                    if (proximitySiren == null) {
                        AudioAttributes attributes = new AudioAttributes.Builder()
                            .setUsage(AudioAttributes.USAGE_ALARM)
                            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                            .build();
                        proximitySiren = MediaPlayer.create(this, R.raw.ambulance_proximity_siren, attributes, 0);
                        if (proximitySiren == null) {
                            result.success(false);
                            return;
                        }
                        proximitySiren.setOnCompletionListener(player -> releaseSiren());
                        proximitySiren.setOnErrorListener((player, what, extra) -> {
                            releaseSiren();
                            return true;
                        });
                        proximitySiren.setVolume(1.0f, 1.0f);
                        proximitySiren.start();
                    }
                    result.success(true);
                } catch (RuntimeException error) {
                    releaseSiren();
                    result.success(false);
                }
            });
    }

    private void releaseSiren() {
        if (proximitySiren != null) {
            proximitySiren.release();
            proximitySiren = null;
        }
    }

    @Override
    protected void onDestroy() {
        releaseSiren();
        super.onDestroy();
    }
}
