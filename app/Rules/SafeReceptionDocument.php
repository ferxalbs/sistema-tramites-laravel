<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class SafeReceptionDocument implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        if ($value->getSize() === 0) {
            $fail('El documento no puede estar vacío.');

            return;
        }

        $originalName = trim($value->getClientOriginalName());
        $filename = basename(str_replace('\\', '/', $originalName));

        if ($filename === '' || strlen($filename) > 255 || str_contains($filename, "\0")
            || preg_match('/(?:^|\.)(?:php\d*|phtml|phar|js|html?|exe|bat|cmd|com|sh)(?:\.|$)/i', $filename) === 1) {
            $fail('El nombre del documento contiene una extensión peligrosa o no es válido.');

            return;
        }

        $allowedMimes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
        ];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $path = $value->getRealPath();
        $actualMime = is_string($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;

        if (! isset($allowedMimes[$extension]) || $actualMime !== $allowedMimes[$extension]) {
            $fail('El contenido real del documento no coincide con su extensión.');
        }
    }
}
