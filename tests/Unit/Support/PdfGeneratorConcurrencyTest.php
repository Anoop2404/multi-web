<?php

namespace Tests\Unit\Support;

use App\Support\PdfGenerator;
use Tests\TestCase;

/**
 * PdfGenerator::renderEach() against a REAL HTTP server, not Http::fake() — faked
 * responses resolve instantly, so the fake-based tests in PdfGeneratorTest passed while
 * production renders were in fact going out one at a time (each Http::async() request
 * had its own Guzzle handler, only driven while that one request was waited on). A slow
 * converter stand-in makes the difference measurable: 6 documents at 0.5 s each take
 * ~3 s one after another, ~1 s with 3 in flight.
 */
class PdfGeneratorConcurrencyTest extends TestCase
{
    private const RENDER_SECONDS = 0.5;

    public function test_render_each_really_overlaps_requests_against_a_real_http_server(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('Needs pcntl/posix to run a local slow converter.');
        }

        $listener = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (! $listener) {
            $this->markTestSkipped("Could not open a local socket: {$errstr}");
        }
        $address = stream_socket_get_name($listener, false);

        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->markTestSkipped('Could not fork a local slow converter.');
        }
        if ($pid === 0) {
            $this->serveSlowly($listener); // never returns
        }
        fclose($listener);

        try {
            config(['services.pdf_converter.url' => "http://{$address}/generate-pdf", 'services.pdf_converter.concurrency' => 3]);

            $documents = [];
            foreach (range(1, 6) as $i) {
                $documents["doc{$i}"] = ['html' => "<p>{$i}</p>"];
            }

            $startedAt = microtime(true);
            $results = PdfGenerator::renderMany($documents);
            $elapsed = microtime(true) - $startedAt;

            $this->assertSame(array_fill_keys(array_keys($documents), '%PDF-slow'), $results);
            $this->assertLessThan(2.2, $elapsed, sprintf('6 x %.1f s documents took %.2f s — requests are not overlapping (one after another would be ~3 s).', self::RENDER_SECONDS, $elapsed));
        } finally {
            posix_kill($pid, SIGKILL);
            pcntl_waitpid($pid, $status);
        }
    }

    /**
     * Child process: a minimal non-blocking HTTP server that answers every POST after
     * RENDER_SECONDS, handling any number of connections at once — so overlapping client
     * requests finish together and sequential ones don't. Killed by the parent (SIGKILL,
     * so no PHPUnit/Laravel shutdown code runs in the fork); gives up on its own after 20 s.
     *
     * @param  resource  $listener
     */
    private function serveSlowly($listener): never
    {
        stream_set_blocking($listener, false);
        $clients = [];
        $giveUpAt = microtime(true) + 20;

        while (microtime(true) < $giveUpAt) {
            $read = [$listener];
            foreach ($clients as $client) {
                if ($client['respond_at'] === null) {
                    $read[] = $client['socket'];
                }
            }
            $write = $except = null;
            @stream_select($read, $write, $except, 0, 20000);

            foreach ($read as $socket) {
                if ($socket === $listener) {
                    if ($connection = @stream_socket_accept($listener, 0)) {
                        stream_set_blocking($connection, false);
                        $clients[(int) $connection] = ['socket' => $connection, 'buffer' => '', 'respond_at' => null];
                    }

                    continue;
                }

                $id = (int) $socket;
                $chunk = fread($socket, 65536);
                if ($chunk === '' || $chunk === false) {
                    if (feof($socket)) {
                        fclose($socket);
                        unset($clients[$id]);
                    }

                    continue;
                }

                $clients[$id]['buffer'] .= $chunk;
                $headerEnd = strpos($clients[$id]['buffer'], "\r\n\r\n");
                if ($headerEnd !== false) {
                    preg_match('/Content-Length:\s*(\d+)/i', substr($clients[$id]['buffer'], 0, $headerEnd), $length);
                    if (strlen($clients[$id]['buffer']) >= $headerEnd + 4 + (int) ($length[1] ?? 0)) {
                        $clients[$id]['respond_at'] = microtime(true) + self::RENDER_SECONDS;
                    }
                }
            }

            foreach ($clients as $id => $client) {
                if ($client['respond_at'] !== null && microtime(true) >= $client['respond_at']) {
                    $body = '%PDF-slow';
                    @fwrite($client['socket'], "HTTP/1.1 200 OK\r\nContent-Type: application/pdf\r\nContent-Length: ".strlen($body)."\r\nConnection: close\r\n\r\n".$body);
                    fclose($client['socket']);
                    unset($clients[$id]);
                }
            }
        }

        posix_kill(getmypid(), SIGKILL);
        exit(1);
    }
}
