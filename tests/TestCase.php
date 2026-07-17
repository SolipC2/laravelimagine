<?php

namespace SolipC2\LaravelImagine\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use SolipC2\LaravelImagine\ImagineServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ImagineServiceProvider::class,
            \Laravel\Ai\AiServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('imagine.fake_video', true);
        $app['config']->set('imagine.store_images', false);
        $app['config']->set('ai.default', 'xai');
        $app['config']->set('ai.default_for_images', 'xai');
        $app['config']->set('ai.providers.xai', [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
            'url' => 'https://api.x.ai/v1',
        ]);
    }
}
