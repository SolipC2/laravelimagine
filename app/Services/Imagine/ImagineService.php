<?php

namespace App\Services\Imagine;

use App\Services\Imagine\Contracts\VideoClient;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use RuntimeException;
use Throwable;

/**
 * Grok Imagine connector:
 * - Images: official laravel/ai XAI provider (grok-imagine-image)
 * - Video: app-level XaiVideoClient (laravel/ai has no video gateway yet)
 */
class ImagineService
{
    public function __construct(
        protected VideoClient $videoClient,
    ) {}

    /**
     * Generate an image via laravel/ai Image::of()->generate('xai').
     *
     * @param  array<string, mixed>  $options
     */
    public function generateImage(string $prompt, array $options = []): ImagineResult
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Image prompt must not be empty.');
        }

        if (! Image::isFaked() && ! $this->hasXaiKey()) {
            throw new RuntimeException('XAI_API_KEY is not configured (and image fakes are not active).');
        }

        $pending = Image::of($prompt);

        if (! empty($options['quality']) && method_exists($pending, 'quality')) {
            $pending = $pending->quality($options['quality']);
        }

        if (! empty($options['size']) && method_exists($pending, 'size')) {
            $pending = $pending->size($options['size']);
        }

        $response = $pending->generate('xai');
        $first = $response->firstImage();
        $b64 = $first->image;
        $mime = $first->mime();

        if (! filled($b64)) {
            throw new RuntimeException('Image generation returned empty base64 payload.');
        }

        $url = null;
        try {
            $binary = base64_decode($b64, true);
            if ($binary !== false) {
                $ext = str_contains($mime, 'png') ? 'png' : 'jpg';
                $path = 'imagine/'.uniqid('img_', true).'.'.$ext;
                Storage::disk('public')->put($path, $binary);
                $url = Storage::disk('public')->url($path);
            }
        } catch (Throwable) {
            // base64 still returned
        }

        return new ImagineResult(
            type: 'image',
            prompt: $prompt,
            url: $url,
            base64: $b64,
            mime: $mime,
            meta: [
                'model' => $response->meta->model ?? config('ai.providers.xai.models.image.default', 'grok-imagine-image'),
                'provider' => $response->meta->provider ?? 'xai',
                'via' => 'laravel/ai',
            ],
        );
    }

    /**
     * Generate a video via VideoClient (XaiVideoClient or FakeXaiVideoClient).
     *
     * @param  array<string, mixed>  $options
     */
    public function generateVideo(string $prompt, array $options = []): ImagineResult
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Video prompt must not be empty.');
        }

        if ($this->videoClient instanceof XaiVideoClient && ! $this->hasXaiKey() && ! config('imagine.fake_video')) {
            throw new RuntimeException('XAI_API_KEY is not configured (and video fakes are not active).');
        }

        return $this->videoClient->generate($prompt, $options);
    }

    /**
     * Chat UI entry: mode image|video.
     *
     * @param  array<string, mixed>  $options
     */
    public function generate(string $mode, string $prompt, array $options = []): ImagineResult
    {
        return match (strtolower($mode)) {
            'image' => $this->generateImage($prompt, $options),
            'video' => $this->generateVideo($prompt, $options),
            default => throw new RuntimeException("Unknown mode [{$mode}]; use image or video."),
        };
    }

    protected function hasXaiKey(): bool
    {
        return filled(config('ai.providers.xai.key'))
            || filled(config('services.xai.key'))
            || filled(env('XAI_API_KEY'));
    }
}
