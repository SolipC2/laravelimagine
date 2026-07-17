<?php

namespace App\Services\Imagine\Contracts;

use App\Services\Imagine\ImagineResult;

interface VideoClient
{
    /**
     * Generate a video from a text prompt (and optional reference image URL).
     *
     * @param  array<string, mixed>  $options  duration, aspect_ratio, resolution, image_url
     */
    public function generate(string $prompt, array $options = []): ImagineResult;
}
