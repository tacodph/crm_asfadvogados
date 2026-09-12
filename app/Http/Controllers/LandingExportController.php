<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Inertia\Inertia;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Tela logada /landing-export: pré-visualiza e baixa a landing pública
 * (Welcome.vue) já compilada em HTML estático. O build fica em
 * `public/landing/` e é gerado por `npm run build:landing` (roda junto com
 * `npm run build`). Aqui só empacotamos essa pasta num .zip.
 */
class LandingExportController extends Controller
{
    private const BUILD_DIR = 'landing';

    public function index(): \Inertia\Response
    {
        return Inertia::render('crm/LandingExport', [
            'disponivel' => is_file(public_path(self::BUILD_DIR.'/index.html')),
            'previewUrl' => '/'.self::BUILD_DIR.'/index.html',
        ]);
    }

    public function download(): BinaryFileResponse|Response
    {
        $dir = public_path(self::BUILD_DIR);

        if (! is_file($dir.'/index.html')) {
            return response(
                'Landing ainda não compilada. Rode "npm run build:landing" e tente de novo.',
                Response::HTTP_CONFLICT,
            );
        }

        $zipPath = storage_path('app/landing-'.now()->format('Ymd-His').'.zip');

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (Finder::create()->files()->in($dir) as $file) {
            $zip->addFile($file->getRealPath(), $file->getRelativePathname());
        }

        $zip->close();

        return response()->download($zipPath, 'antessala-landing.zip')->deleteFileAfterSend();
    }
}
