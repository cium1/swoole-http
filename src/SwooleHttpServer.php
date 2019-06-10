<?php
/**
 * User:    Yejia
 * Email:   ye91@foxmail.com
 */

namespace SwooleHttp;

use Laravel\Lumen\Application;
use Laravel\Lumen\Exceptions\Handler;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SwooleHttpServer
{
    /**
     * @var
     */
    protected $config;

    /**
     * @var \swoole_http_server
     */
    protected $server;

    /**
     * @var
     */
    protected $heartBeatInternal;

    /**
     * @var
     */
    protected $app;

    /**
     * SwooleHttpServer constructor.
     *
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->heartBeatInternal = $this->config['heart_beat_internal'];
        $this->server = new Server($this->config['host'], $this->config['port']);
    }

    /**
     *
     */
    public function run()
    {
        unset($this->config['host'], $this->config['port'], $this->config['heart_beat_internal']);

        if (SWOOLE_VERSION >= '2.0') {
            $this->config['enable_coroutine'] = false;
        }

        $this->server->set($this->config);

        $this->server->on('workerStart', [$this, 'onWorkerStart']);
        $this->server->on('request', [$this, 'onRequest']);

        $this->server->start();
    }

    public function onWorkerStart(Server $server, $workerId)
    {
        $this->app = Application::getInstance();

//        $server->tick($this->heartBeatInternal * 1000, function () {
//            $this->app->make('heartBeat');
//        });
    }

    public function onRequest(Request $request, Response $response)
    {
        $header = [];
        foreach ($request->header as $key => $value) {
            $header[str_replace('-', '_', $key)] = $value;
        }
        $request->server = array_change_key_case(array_merge($request->server, $header), CASE_UPPER);

        ob_start();

        $get = isset($request->get) ? $request->get : [];
        $post = isset($request->post) ? $request->post : [];
        $cookie = isset($request->cookie) ? $request->cookie : [];
        $server = isset($request->server) ? $request->server : [];
        $header = isset($request->header) ? $request->header : [];
        $files = isset($request->files) ? $request->files : [];
        $content = $request->rawContent() ?: null;

        $illuminateRequest = new \Illuminate\Http\Request($get, $post, $header, $cookie, $files, $server, $content);

        try {
            $illuminateResponse = $this->app->handle($illuminateRequest);
            $content = $illuminateResponse->getContent();
            if (strlen($content) === 0 && ob_get_length() > 0) {
                $illuminateResponse->setContent(ob_get_contents());
            }
        } catch (\Exception $exception) {
            $illuminateResponse = (new Handler())->render($illuminateRequest, $exception);
        }
        ob_end_clean();

        // status
        $response->status($illuminateResponse->getStatusCode());
        foreach ($illuminateResponse->headers->allPreserveCase() as $name => $values) {
            foreach ($values as $value) {
                $response->header($name, $value);
            }
        }
        // cookies
        foreach ($illuminateResponse->headers->getCookies() as $cookie) {
            $response->cookie($cookie->getName(), urlencode($cookie->getValue()), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly());
        }
        // content
        if ($illuminateResponse instanceof BinaryFileResponse) {
            $realPath = realpath($illuminateResponse->getFile()->getPathname());
            $response->sendfile($realPath);
        } else {
            $content = $illuminateResponse->getContent();
            $response->end($content);
        }
    }
}