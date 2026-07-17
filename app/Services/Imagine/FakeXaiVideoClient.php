<?php

namespace App\Services\Imagine;

use App\Services\Imagine\Contracts\VideoClient;
use Closure;

class FakeXaiVideoClient implements VideoClient
{
    /**
     * @param  Closure(string, array):ImagineResult|array<int, ImagineResult|string>|null  $responses
     */
    public function __construct(
        protected Closure|array|null $responses = null,
    ) {}

    public function generate(string $prompt, array $options = []): ImagineResult
    {
        if ($this->responses instanceof Closure) {
            $result = ($this->responses)($prompt, $options);

            return $result instanceof ImagineResult
                ? $result
                : new ImagineResult(
                    type: 'video',
                    prompt: $prompt,
                    url: is_string($result) ? $result : 'https://example.test/fake-video.mp4',
                    mime: 'video/mp4',
                    meta: ['fake' => true, 'model' => 'grok-imagine-video'],
                );
        }

        if (is_array($this->responses) && count($this->responses) > 0) {
            $next = array_shift($this->responses);
            if ($next instanceof ImagineResult) {
                return $next;
            }

            return new ImagineResult(
                type: 'video',
                prompt: $prompt,
                url: is_string($next) ? $next : 'https://example.test/fake-video.mp4',
                mime: 'video/mp4',
                meta: ['fake' => true, 'model' => 'grok-imagine-video'],
            );
        }

        // Default durable fake: a data URI is awkward for video; use a stable example URL
        // plus meta so tests can assert non-empty media reference.
        return new ImagineResult(
            type: 'video',
            prompt: $prompt,
            url: 'https://example.test/imagine/fake-'.substr(sha1($prompt), 0, 12).'.mp4',
            mime: 'video/mp4',
            meta: [
                'fake' => true,
                'model' => $options['model'] ?? 'grok-imagine-video',
                'duration' => $options['duration'] ?? 6,
            ],
        );
    }
}
