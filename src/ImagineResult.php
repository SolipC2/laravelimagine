<?php

namespace SolipC2\LaravelImagine;

final class ImagineResult
{
    /**
     * @param  'image'|'video'  $type
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $type,
        public readonly string $prompt,
        public readonly ?string $url = null,
        public readonly ?string $base64 = null,
        public readonly ?string $mime = null,
        public readonly array $meta = [],
    ) {}

    public function hasMedia(): bool
    {
        return filled($this->url) || filled($this->base64);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'prompt' => $this->prompt,
            'url' => $this->url,
            'base64' => $this->base64,
            'mime' => $this->mime,
            'meta' => $this->meta,
        ], fn ($v) => $v !== null && $v !== []);
    }
}
