<?php

namespace Astrotomic\Pixpipe;

use Astrotomic\Pixpipe\Http\Controllers\ManipulateImageController;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\URL;
use League\Glide\Signatures\Signature;
use Stringable;

readonly class Image implements Stringable
{
    public function __construct(
        public string $disk,
        public string $path,
        public array $manipulations = [],
    ) {}

    public function url(array $manipulations = [], DateTimeInterface|DateInterval|null $expires = null): string
    {
        $parameters = array_merge($this->manipulations, $manipulations);

        if ($expires) {
            $parameters['e'] = $this->expiresAt($expires)?->timestamp;
        }

        return URL::action(ManipulateImageController::class, [
            'disk' => $this->disk,
            'path' => $this->path,
            ...$parameters,
            's' => $this->signature($parameters),
        ]);
    }

    protected function signature(array $parameters): string
    {
        return Container::getInstance()->make(Signature::class)->generateSignature(
            path: "{$this->disk}/{$this->path}",
            params: $parameters
        );
    }

    protected function expiresAt(DateTimeInterface|DateInterval|null $expires): ?CarbonImmutable
    {
        if ($expires === null) {
            return null;
        }

        if ($expires instanceof DateInterval) {
            $expires = Carbon::now()->add($expires);
        }

        return CarbonImmutable::instance($expires)->utc();
    }

    public function __toString(): string
    {
        return $this->url();
    }
}
