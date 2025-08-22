<?php

namespace Astrotomic\Pixpipe\Http\Middlewares;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Http\Request;
use League\Glide\Signatures\Signature;
use League\Glide\Signatures\SignatureException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

readonly class ValidateGlideSignature
{
    public function __construct(
        protected Signature $signature,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $disk = $request->route('disk');
        $path = $request->route('path');

        $base = "{$disk}/{$path}";

        try {
            $this->signature->validateRequest($base, $request->query());
        } catch (SignatureException $e) {
            throw new HttpException(
                statusCode: Response::HTTP_FORBIDDEN,
                message: $e->getMessage(),
                previous: $e,
            );
        }

        if ($request->query->has('e')) {
            try {
                $expiresAt = CarbonImmutable::createFromTimestampUTC($request->query('e'));
            } catch (InvalidFormatException $e) {
                throw new HttpException(
                    statusCode: Response::HTTP_GONE,
                    message: $e->getMessage(),
                    previous: $e,
                );
            }

            if ($expiresAt->isPast()) {
                throw new HttpException(
                    statusCode: Response::HTTP_GONE,
                    message: 'Signature is expired.',
                );
            }
        }

        return $next($request);
    }
}
