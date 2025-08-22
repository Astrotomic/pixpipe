<?php

namespace Astrotomic\Pixpipe\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\Glide\Filesystem\FileNotFoundException;
use League\Glide\Server;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

readonly class ManipulateImageController
{
    public function __invoke(Request $request, string $disk, string $path, Server $glide): StreamedResponse|RedirectResponse
    {
        try {
            $glide->setSource(Storage::disk($disk)->getDriver());
        } catch (InvalidArgumentException $e) {
            throw new HttpException(
                statusCode: Response::HTTP_NOT_FOUND,
                message: $e->getMessage(),
                previous: $e,
            );
        }

        try {
            return $glide->getImageResponse($path, $request->query());
        } catch (FileNotFoundException $e) {
            throw new HttpException(
                statusCode: Response::HTTP_NOT_FOUND,
                message: $e->getMessage(),
                previous: $e,
            );
        }
    }
}
