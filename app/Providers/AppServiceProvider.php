<?php

namespace App\Providers;

use App\Services\Imagine\Contracts\VideoClient;
use App\Services\Imagine\FakeXaiVideoClient;
use App\Services\Imagine\ImagineService;
use App\Services\Imagine\XaiVideoClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VideoClient::class, function ($app) {
            if (config('imagine.fake_video') || $app->environment('testing')) {
                return new FakeXaiVideoClient;
            }

            if (! filled(config('ai.providers.xai.key') ?? env('XAI_API_KEY'))) {
                // No key: still bind fake so UI can demo without 500 when IMAGINE_FAKE defaults on production...
                // Production without key uses real client and returns honest errors from ImagineService.
                return new XaiVideoClient($app['http']);
            }

            return new XaiVideoClient(
                $app['http'],
                config('ai.providers.xai.key') ?? env('XAI_API_KEY'),
                rtrim((string) config('imagine.video_base_url', 'https://api.x.ai/v1'), '/'),
                (string) config('imagine.video_model', 'grok-imagine-video'),
            );
        });

        $this->app->singleton(ImagineService::class, function ($app) {
            return new ImagineService($app->make(VideoClient::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
