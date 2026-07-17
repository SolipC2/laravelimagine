<?php

namespace SolipC2\LaravelImagine;

use Illuminate\Support\ServiceProvider;
use SolipC2\LaravelImagine\Contracts\VideoClient;

class ImagineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/imagine.php', 'imagine');

        $this->app->bind(VideoClient::class, function ($app) {
            if (config('imagine.fake_video', false)) {
                return new FakeXaiVideoClient;
            }

            return new XaiVideoClient(
                $app->make(\Illuminate\Http\Client\Factory::class),
                config('ai.providers.xai.key') ?? config('services.xai.key') ?? env('XAI_API_KEY'),
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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/imagine.php' => config_path('imagine.php'),
            ], 'imagine-config');
        }
    }
}
