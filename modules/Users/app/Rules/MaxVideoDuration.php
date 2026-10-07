<?php

namespace App\Modules\Users\Rules;

use Closure;
use FFMpeg\FFProbe;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class MaxVideoDuration implements ValidationRule
{
    public function __construct(private int $maxSeconds) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        try {
            $ffprobe = FFProbe::create([
                'ffprobe.binaries' => config('media-library.ffprobe_path', '/usr/bin/ffprobe'),
            ]);

            $duration = (float) $ffprobe
                ->format($value->getPathname())
                ->get('duration');

            if ($duration > $this->maxSeconds) {
                $fail("Il video deve durare massimo {$this->maxSeconds} secondi (durata: ".(int) ceil($duration).'s).');
            }
        } catch (\Exception) {
            $fail('Impossibile determinare la durata del video.');
        }
    }
}
