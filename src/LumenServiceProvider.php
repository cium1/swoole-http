<?php
/**
 * User:    Yejia
 * Email:   ye91@foxmail.com
 */

namespace SwooleHttp;


use Illuminate\Support\ServiceProvider;

class LumenServiceProvider extends ServiceProvider
{
    public function register()
    {
        // command
        $this->commands([
            SwooleCommand::class,
        ]);

        // config
        $this->mergeConfigFrom(__DIR__ . '/../config/swoole.php', 'swoole');

        $this->app->singleton(SwooleHttpServer::class, function ($app) {
            $config = $app['config']->get('swoole');
            return new SwooleHttpServer($config);
        });
    }
}