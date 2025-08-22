<?php

namespace Astrotomic\Pixpipe\Http\Responses;

use Carbon\CarbonImmutable;
use League\Flysystem\FilesystemOperator;
use League\Glide\Responses\ResponseFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class SymfonyResponseFactory implements ResponseFactoryInterface
{
    public function __construct(
        protected ?Request $request = null
    ) {}

    /**
     * Create the response.
     *
     * @param  FilesystemOperator  $cache  The cache file system.
     * @param  string  $path  The cached file path.
     * @return StreamedResponse The response object.
     */
    public function create(FilesystemOperator $cache, $path): StreamedResponse
    {
        $stream = $cache->readStream($path);

        $response = new StreamedResponse;
        $response->headers->set('Content-Type', $cache->mimeType($path));
        $response->headers->set('Content-Length', $cache->fileSize($path));
        $response->setImmutable();
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setExpires(CarbonImmutable::now()->addYear()->endOfDay());

        if ($this->request) {
            $response->setLastModified(CarbonImmutable::createFromTimestampUTC($cache->lastModified($path)));
            $response->isNotModified($this->request);
        }

        $response->setCallback(function () use ($stream) {
            if (ftell($stream) !== 0) {
                rewind($stream);
            }
            fpassthru($stream);
            fclose($stream);
        });

        return $response;
    }
}
