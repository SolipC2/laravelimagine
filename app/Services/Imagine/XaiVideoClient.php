<?php

namespace App\Services\Imagine;

use App\Services\Imagine\Contracts\VideoClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin xAI Imagine video client (text-to-video / optional image-to-video).
 *
 * laravel/ai ships XaiImageGateway for images but no video gateway yet.
 * REST: POST https://api.x.ai/v1/videos/generations then poll GET /v1/videos/{id}
 */
class XaiVideoClient implements VideoClient
{
    public function __construct(
        protected HttpFactory $http,
        protected ?string $apiKey = null,
        protected string $baseUrl = 'https://api.x.ai/v1',
        protected string $model = 'grok-imagine-video',
        protected int $pollSeconds = 90,
    ) {
        $this->apiKey = $apiKey ?? config('services.xai.key') ?? env('XAI_API_KEY');
    }

    public function generate(string $prompt, array $options = []): ImagineResult
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Video prompt must not be empty.');
        }

        if (! filled($this->apiKey)) {
            throw new RuntimeException('XAI_API_KEY is not configured.');
        }

        $payload = array_filter([
            'model' => $options['model'] ?? $this->model,
            'prompt' => $prompt,
            'duration' => $options['duration'] ?? 6,
            'aspect_ratio' => $options['aspect_ratio'] ?? '16:9',
            'resolution' => $options['resolution'] ?? '480p',
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($options['image_url'])) {
            $payload['image'] = ['url' => $options['image_url']];
        }

        $start = $this->client()
            ->timeout(60)
            ->post($this->baseUrl.'/videos/generations', $payload);

        if ($start->failed()) {
            throw new RuntimeException('xAI video start failed: '.$start->body());
        }

        $body = $start->json() ?? [];
        $requestId = $body['request_id'] ?? $body['id'] ?? null;
        $url = data_get($body, 'video.url') ?? data_get($body, 'url');

        if (filled($url)) {
            return new ImagineResult(
                type: 'video',
                prompt: $prompt,
                url: $url,
                mime: 'video/mp4',
                meta: ['model' => $payload['model'], 'raw_keys' => array_keys($body)],
            );
        }

        if (! filled($requestId)) {
            throw new RuntimeException('xAI video response missing request_id and url: '.$start->body());
        }

        $deadline = time() + $this->pollSeconds;
        while (time() < $deadline) {
            sleep(2);
            $poll = $this->client()->timeout(30)->get($this->baseUrl.'/videos/'.$requestId);
            if ($poll->failed()) {
                throw new RuntimeException('xAI video poll failed: '.$poll->body());
            }
            $data = $poll->json() ?? [];
            $status = strtolower((string) ($data['status'] ?? ''));
            $url = data_get($data, 'video.url') ?? data_get($data, 'url');
            if (filled($url) || in_array($status, ['done', 'completed', 'succeeded', 'success'], true)) {
                if (! filled($url)) {
                    throw new RuntimeException('xAI video completed without URL: '.$poll->body());
                }

                return new ImagineResult(
                    type: 'video',
                    prompt: $prompt,
                    url: $url,
                    mime: 'video/mp4',
                    meta: [
                        'model' => $payload['model'],
                        'request_id' => $requestId,
                        'status' => $status,
                    ],
                );
            }
            if (in_array($status, ['failed', 'error', 'cancelled'], true)) {
                throw new RuntimeException('xAI video generation failed: '.$poll->body());
            }
        }

        throw new RuntimeException('xAI video generation timed out waiting for request '.$requestId);
    }

    protected function client(): PendingRequest
    {
        return $this->http->withToken((string) $this->apiKey)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['User-Agent' => 'laravelimagine/1.0']);
    }
}
