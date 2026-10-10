<?php

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

test('independent SQLite writers assign unique SPK numbers and collapse concurrent retries', function (): void {
    $database = tempnam(sys_get_temp_dir(), 'erp-spk-concurrency-');
    $processes = [];
    $worker = base_path('tests/Fixtures/deskprint-worker.php');
    try {
        $prepare = new Process([PHP_BINARY, $worker, $database, 'prepare'], base_path(), timeout: 30);
        $prepare->mustRun();
        $fixture = json_decode($prepare->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $key = (string) Str::uuid();
        foreach ([$key, $key, (string) Str::uuid(), (string) Str::uuid(), (string) Str::uuid()] as $requestKey) {
            $process = new Process([PHP_BINARY, $worker, $database, 'order', json_encode([...$fixture, 'key' => $requestKey], JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        expect($results[0])->toBe($results[1]);
        expect(array_unique(array_column($results, 'number')))->toHaveCount(4);
        $processes = [];
        $key = (string) Str::uuid();
        for ($index = 0; $index < 4; $index++) {
            $process = new Process([PHP_BINARY, $worker, $database, 'item', json_encode([...$fixture, 'key' => $key, 'order' => $results[0]['id']], JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            expect($result['total'])->toBe('82000.00');
            expect($result['version'])->toBe(2);
        }
        $pdo = new PDO('sqlite:'.$database);
        expect((int) $pdo->query('SELECT COUNT(*) FROM print_orders')->fetchColumn())->toBe(4);
        expect((int) $pdo->query('SELECT COUNT(*) FROM print_order_items')->fetchColumn())->toBe(1);
        expect((int) $pdo->query('SELECT COUNT(*) FROM deskprint_requests')->fetchColumn())->toBe(5);
        $pdo = null;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        foreach ([$database, $database.'-wal', $database.'-shm', $database.'-journal'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
});
