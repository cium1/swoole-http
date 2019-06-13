<?php
/**
 * User:    Yejia
 * Email:   ye91@foxmail.com
 */

namespace SwooleHttp;

use Illuminate\Console\Command;

class SwooleCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'swoole {action?}';

    /**
     * @var string
     */
    protected $description = 'swoole http server';

    /**
     *
     */
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'start':
                $this->start();
                break;
            case 'restart':
                $this->restart();
                break;
            case 'stop':
                $this->stop();
                break;
            case 'status':
                $this->status();
                break;
            case 'vendor:publish':
                $this->vendorPublish();
                break;
            default:
                $this->info('|start|restart|stop|status|vendor:publish|');
                break;
        }
    }

    /**
     *
     */
    protected function start()
    {
        if ($this->pid()) {
            $this->error('swoole http server is already running');
            exit(1);
        }
        $this->info('starting swoole http server...');
        app()->make(SwooleHttpServer::class)->run();
    }

    /**
     *
     */
    protected function restart()
    {
        $this->info('stopping swoole http server...');
        $pid = $this->signal(SIGTERM);
        $time = 0;
        while (posix_getpgid($pid)) {
            usleep(100000);
            $time++;
            if ($time > 50) {
                $this->error('timeout...');
                exit(1);
            }
        }
        $this->info('done');
        $this->start();
    }

    /**
     *
     */
    protected function stop()
    {
        $this->info('immediately stopping...');
        $this->signal(SIGTERM);
        $this->info('done');
    }

    /**
     *
     */
    protected function status()
    {
        $pid = $this->pid();
        if ($pid) {
            $this->info('swoole http server is running. master pid : ' . $pid);
        } else {
            $this->error('swoole http server is not running!');
        }
    }

    /**
     *
     */
    protected function vendorPublish()
    {
        $source = __DIR__ . '/../config/swoole.php';
        if (!is_dir(base_path('config'))) mkdir(base_path('config'));
        $dst = base_path('config/swoole.php');
        copy($source, $dst);
        $this->info('success');
    }

    /**
     * @return bool|int
     */
    protected function pid()
    {
        $pid_file = config('swoole.pid_file');
        if (file_exists($pid_file)) {
            $pid = intval(file_get_contents($pid_file));
            if (posix_getpgid($pid)) {
                return $pid;
            } else {
                unlink($pid_file);
            }
        }
        return false;
    }

    /**
     * @param $sig
     *
     * @return bool|int
     */
    protected function signal($sig)
    {
        $pid = $this->pid();
        if ($pid) {
            posix_kill($pid, $sig);
        } else {
            $this->error('swoole http is not running!');
            exit(1);
        }
        return $pid;
    }
}