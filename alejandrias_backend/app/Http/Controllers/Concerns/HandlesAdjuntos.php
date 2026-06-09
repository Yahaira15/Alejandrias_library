<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait HandlesAdjuntos
{
    private function validarAdjuntos(Request $request): void
    {
        $request->validate([
            'adjuntos' => 'nullable|array|max:5',
            'adjuntos.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar',
        ]);
    }

    private function guardarAdjuntos(Request $request, string $carpeta): array
    {
        if (!$request->hasFile('adjuntos')) {
            return [];
        }

        $adjuntos = [];

        foreach ($request->file('adjuntos') as $archivo) {
            if (!$archivo->isValid()) {
                continue;
            }

            $path = $archivo->store($carpeta, 'public');
            $mime = $archivo->getMimeType() ?: $archivo->getClientMimeType() ?: 'application/octet-stream';

            $adjuntos[] = [
                'nombre' => $archivo->getClientOriginalName(),
                'path' => $path,
                'url' => '/storage/' . ltrim($path, '/'),
                'mime' => $mime,
                'tamano' => $archivo->getSize(),
                'es_imagen' => str_starts_with($mime, 'image/'),
            ];
        }

        return $adjuntos;
    }
}
