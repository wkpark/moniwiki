<?php

use PHPUnit\Framework\TestCase;

require_once MONIWIKI_TEST_ROOT.'/lib/HTTPClient.php';

final class HTTPClientRedirectTest extends TestCase
{
    public function testRedirectValidatorBlocksRedirectTarget(): void
    {
        list($process, $router) = $this->startRedirectServer();

        try {
            $http = new HTTPClient();
            $http->url_validator = function($url) {
                return false;
            };

            $result = $http->sendRequest($this->serverUrl.'/redirect', array(), 'GET');

            $this->assertFalse($result);
            $this->assertSame('Redirect URL is not allowed', $http->error);
        } finally {
            $this->stopServer($process, $router);
        }
    }

    public function testRedirectValidatorAllowsRedirectTarget(): void
    {
        list($process, $router) = $this->startRedirectServer();

        try {
            $http = new HTTPClient();
            $http->url_validator = function($url) {
                return true;
            };

            $result = $http->sendRequest($this->serverUrl.'/redirect', array(), 'GET');

            $this->assertTrue($result);
            $this->assertSame(200, (int) $http->status);
            $this->assertSame('target', $http->resp_body);
        } finally {
            $this->stopServer($process, $router);
        }
    }

    private string $serverUrl = '';

    private function startRedirectServer(): array
    {
        $port = $this->pickPort();
        $this->serverUrl = 'http://127.0.0.1:'.$port;
        $router = tempnam(sys_get_temp_dir(), 'moniwiki-router-').'.php';
        $script = str_replace('__PORT__', (string) $port, <<<'PHP'
<?php
if ($_SERVER['REQUEST_URI'] === '/redirect') {
    header('Location: http://127.0.0.1:__PORT__/target', true, 302);
    return;
}

header('Content-Type: text/plain');
echo 'target';
PHP
        );
        file_put_contents($router, $script);

        $process = proc_open(
            array(PHP_BINARY, '-S', '127.0.0.1:'.$port, $router),
            array(
                1 => array('file', sys_get_temp_dir().'/moniwiki-phpunit-server.log', 'a'),
                2 => array('file', sys_get_temp_dir().'/moniwiki-phpunit-server.log', 'a'),
            ),
            $pipes
        );

        if (!is_resource($process)) {
            $this->fail('Failed to start PHP test server');
        }

        $this->waitForServer($port, $process, $router);

        return array($process, $router);
    }

    private function stopServer($process, string $router): void
    {
        if (is_resource($process)) {
            $status = proc_get_status($process);
            if (!empty($status['running'])) {
                proc_terminate($process);
            }
            proc_close($process);
        }
        if (file_exists($router)) {
            unlink($router);
        }
    }

    private function pickPort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$socket) {
            $this->fail('Failed to allocate test port: '.$errstr);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr(strrchr($name, ':'), 1);
    }

    private function waitForServer(int $port, $process, string $router): void
    {
        for ($i = 0; $i < 20; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($socket) {
                fclose($socket);
                return;
            }
            usleep(100000);
        }

        $this->stopServer($process, $router);
        $this->fail('PHP test server did not start');
    }
}
