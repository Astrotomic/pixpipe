<?php

namespace Astrotomic\Pixpipe;

use Astrotomic\Pixpipe\Http\Responses\SymfonyResponseFactory;
use Astrotomic\Pixpipe\Manipulators\Size;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Laravel\Facades\Image;
use InvalidArgumentException;
use League\Glide\Api\Api;
use League\Glide\Manipulators\Background;
use League\Glide\Manipulators\Blur;
use League\Glide\Manipulators\Border;
use League\Glide\Manipulators\Brightness;
use League\Glide\Manipulators\Contrast;
use League\Glide\Manipulators\Crop;
use League\Glide\Manipulators\Filter;
use League\Glide\Manipulators\Flip;
use League\Glide\Manipulators\Gamma;
use League\Glide\Manipulators\Orientation;
use League\Glide\Manipulators\Pixelate;
use League\Glide\Manipulators\Sharpen;
use League\Glide\Manipulators\Watermark;
use League\Glide\Responses\ResponseFactoryInterface;
use League\Glide\Server;
use League\Glide\Signatures\Signature;

class PixpipeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            Orientation::class,
            Crop::class,
            Size::class,
            Brightness::class,
            Contrast::class,
            Gamma::class,
            Sharpen::class,
            Filter::class,
            Flip::class,
            Blur::class,
            Pixelate::class,
            Watermark::class,
            Background::class,
            Border::class,
        ], 'glide-manipulators');

        $this->app->singleton(Signature::class, function (Application $app): Signature {
            return new Signature(
                signKey: $app->make('config')->get('app.key'),
            );
        });

        $this->app->singleton(Api::class, function (Application $app): Api {
            return new Api(
                imageManager: $app->make(Image::BINDING),
                manipulators: [...$app->tagged('glide-manipulators')],
            );
        });

        $this->app->scoped(ResponseFactoryInterface::class, SymfonyResponseFactory::class);

        $this->app->scoped(Server::class, function (Application $app): Server {
            $server = new Server(
                source: Storage::disk()->getDriver(),
                cache: Storage::disk('glide_cache')->getDriver(),
                api: $app->make(Api::class),
            );

            $server->setResponseFactory($this->app->make(SymfonyResponseFactory::class));

            return $server;
        });
    }

    public function boot(): void
    {
        try {
            Storage::disk('glide_cache');
        } catch (InvalidArgumentException) {
            Storage::set('glide_cache', Storage::createLocalDriver([
                'driver' => 'local',
                'root' => $this->app->storagePath('app/glide_cache'),
                'serve' => false,
                'throw' => false,
                'report' => false,
            ], 'glide_cache'));
        }
    }
}
