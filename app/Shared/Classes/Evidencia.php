<?php

namespace App\Shared\Classes;

readonly class Evidencia
{
    public function __construct(
        // nombre original del archivo que se subio
        public ?string $filename = null,

        // extension del archivo
        public ?string $extension = null,

        // url completa del archivo (dominio + storage/app/public/...)
        public string $url,
    ) {}
}
