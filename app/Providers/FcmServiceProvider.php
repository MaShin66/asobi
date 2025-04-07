<?php

namespace App\Providers;

use App\Services\EloquentFcmDeviceRepository;
use App\Services\FcmDeviceRepository;
use App\Services\FcmHandler;
use App\Services\FcmService;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class FcmServiceProvider extends ServiceProvider
{
    public function register()
    {
//        if ($_SERVER['REMOTE_ADDR'] === '221.148.221.127') {
//            $this->bindFcmDeviceRepository();
//
//            $this->app->singleton(FcmHandler::class, function ($app) {
//                return new FcmHandler(
//                    new GuzzleClient([
//                        'base_uri' => 'https://fcm.googleapis.com',
//                    ]),
//                    $app->make('App\Services\FcmDeviceRepository'),
//                    $app->make(LoggerInterface::class)
//                );
//            });
//
//            $this->app->singleton(FcmService::class, function ($app) {
//                return new FcmService(
//                    new GuzzleClient(),
//                    $app->make('config'),
//                    $app->make(LoggerInterface::class),
//                    $app->make(FcmHandler::class)
//                );
//            });
//        } else {
            $this->bindFcmDeviceRepository();
            $this->bindFcmHandler();
//        }
    }

    private function bindFcmDeviceRepository()
    {
        $this->app->bind(FcmDeviceRepository::class, EloquentFcmDeviceRepository::class);
    }

    private function bindFcmHandler()
    {
        $this->app->bind(FcmHandler::class, function (Application $app) {
            $config = $app->make(ConfigRepository::class)->get('fcm');

            $httpClient = new GuzzleClient([
                'base_uri' => $config['server_send_url'],
                'headers' => [
                    'Authorization' => "key={$config['server_key']}",
                    'Content-Type' => 'application/json',
                    'project_id' => $config['sender_id'],
                ],
                'timeout' => $config['timeout'],
            ]);

            $deviceRepo = $app->make(FcmDeviceRepository::class);
            $logger = $app->make(LoggerInterface::class);

            return new FcmHandler($httpClient, $deviceRepo, $logger);
        });
    }
}
