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
        // Default binding for DI/tests. HTTP chat always builds its own client
        // in ChatController so Live (fake:false) is never trapped on a fake singleton.
        $this->app->bind(VideoClient::class, function ($app) {
            if ($app->environment('testing') && config('imagine.fake_video', true)) {
                return new FakeXaiVideoClient;
            }

            return new XaiVideoClient(
                $app->make(\Illuminate\Http\Client\Factory::class),
                config('ai.providers.xai.key') ?? env('XAI_API_KEY'),
                rtrim((string) config('imagine.video_base_url', 'https://api.x.ai/v1'), '/'),
                (string) config('imagine.video_model', 'grok-imagine-video'),
            );
        });

        $this->app->bind(ImagineService::class, function ($app) {
            return new ImagineService($app->make(VideoClient::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
