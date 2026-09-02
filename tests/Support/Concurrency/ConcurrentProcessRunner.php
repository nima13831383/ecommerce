<?php

namespace Tests\Support\Concurrency;

use Symfony\Component\Process\Process;

class ConcurrentProcessRunner
{
    public function runSelfTest(): array
    {
        return $this->run('barrier_self_test');
    }

    public function run(string $operation, array $data = []): array
    {
        $b = sys_get_temp_dir().DIRECTORY_SEPARATOR.'mysql-concurrency-'.bin2hex(random_bytes(8));
        mkdir($b, 0700, true);
        $ps = [];
        try {
            foreach (['A', 'B'] as $n) {
                $p = new Process([PHP_BINARY, __DIR__.'/worker.php']);
                $workerData = $data['worker_data'][$n] ?? [];
                $sharedData = $data;
                unset($sharedData['worker_data']);
                $processOperation = $workerData['operation'] ?? $operation;
                unset($workerData['operation']);
                $processData = array_replace($sharedData, $workerData) + ['reference_id' => $n];
                $p->setInput(json_encode(['operation' => $processOperation, 'worker' => $n, 'barrier' => $b, 'data' => $processData], JSON_THROW_ON_ERROR));
                $p->setTimeout(15);
                $p->start();
                $ps[$n] = $p;
            } $d = microtime(true) + 10;
            while (! (file_exists("$b/A.ready") && file_exists("$b/B.ready"))) {
                if (microtime(true) >= $d) {
                    throw new \RuntimeException('Workers did not reach barrier.');
                }usleep(10000);
            } $alive = $ps['A']->isRunning() && $ps['B']->isRunning();
            file_put_contents("$b/release", 'go');
            foreach ($ps as $p) {
                $p->wait();
            }

            return ['pids' => ['A' => trim(file_get_contents("$b/A.ready")), 'B' => trim(file_get_contents("$b/B.ready"))], 'alive' => $alive, 'results' => array_map(fn ($p) => ['exit' => $p->getExitCode(), 'json' => json_decode(trim($p->getOutput()), true, 512, JSON_THROW_ON_ERROR)], $ps)];
        } finally {
            foreach ($ps as $p) {
                if ($p->isRunning()) {
                    $p->stop();
                }
            }foreach (glob("$b/*") ?: [] as $f) {
                unlink($f);
            }@rmdir($b);
        }
    }
}
