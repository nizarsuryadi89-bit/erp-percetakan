<?php

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

test('independent cashiers issue once serialize overpay collapse retries and append one reversal', function (): void {
    $database = tempnam(sys_get_temp_dir(), 'erp-cashier-concurrency-');
    $processes = [];
    $worker = base_path('tests/Fixtures/cashier-worker.php');
    $run = function (string $mode, array $payload) use ($database, $worker, &$processes): Process {
        $process = new Process([PHP_BINARY, $worker, $database, $mode, json_encode($payload, JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
        $process->start();
        $processes[] = $process;

        return $process;
    };
    $finish = function (array $group): array {
        $results = [];
        foreach ($group as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    };
    try {
        $prepare = new Process([PHP_BINARY, $worker, $database, 'prepare'], base_path(), timeout: 30);
        $prepare->mustRun();
        $fixture = json_decode($prepare->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $key = (string) Str::uuid();
        $group = [];
        foreach ([$key, $key, (string) Str::uuid(), (string) Str::uuid()] as $current) {
            $group[] = $run('issue', [...$fixture, 'key' => $current]);
        }
        $results = $finish($group);
        expect(array_unique(array_column($results, 'invoice')))->toHaveCount(1);
        $invoice = $results[0]['invoice'];
        $results = $finish([$run('pay', [...$fixture, 'invoice' => $invoice, 'key' => (string) Str::uuid(), 'amount' => '50000']), $run('pay', [...$fixture, 'invoice' => $invoice, 'user' => $fixture['cashier'], 'key' => (string) Str::uuid(), 'amount' => '50000'])]);
        expect(count(array_filter($results, fn ($result) => isset($result['rejected']))))->toBe(1);
        $key = (string) Str::uuid();
        $payload = [...$fixture, 'invoice' => $invoice, 'key' => $key, 'amount' => '32000'];
        $results = $finish([$run('pay', $payload), $run('pay', $payload), $run('pay', $payload)]);
        foreach ($results as $result) {
            expect($result['summary']['paid'])->toBe('82000.00');
            expect($result['summary']['balance'])->toBe('0.00');
        }
        $pdo = new PDO('sqlite:'.$database);
        $payment = (int) $pdo->query("SELECT id FROM invoice_payments WHERE amount='32000.00'")->fetchColumn();
        expect((int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn())->toBe(1);
        expect((int) $pdo->query('SELECT COUNT(*) FROM invoice_payments')->fetchColumn())->toBe(2);
        $key = (string) Str::uuid();
        $payload = [...$fixture, 'invoice' => $invoice, 'payment' => $payment, 'key' => $key];
        $results = $finish([$run('reverse', $payload), $run('reverse', $payload), $run('reverse', $payload)]);
        foreach ($results as $result) {
            expect($result['summary']['paid'])->toBe('50000.00');
            expect($result['summary']['balance'])->toBe('32000.00');
        }
        expect((int) $pdo->query('SELECT COUNT(*) FROM invoice_payments')->fetchColumn())->toBe(3);
        expect((int) $pdo->query('SELECT COUNT(*) FROM cashier_requests')->fetchColumn())->toBe(6);
        $result = $finish([$run('cancel', [...$fixture, 'key' => (string) Str::uuid()])])[0];
        expect($result['rejected'])->toBeTrue();
        expect($pdo->query('SELECT status FROM print_orders')->fetchColumn())->toBe('confirmed');
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
test('invoice issue racing cancellation leaves no active invoice for a cancelled SPK', function (): void {
    $database = tempnam(sys_get_temp_dir(), 'erp-cashier-concurrency-');
    $processes = [];
    $worker = base_path('tests/Fixtures/cashier-worker.php');
    try {
        $prepare = new Process([PHP_BINARY, $worker, $database, 'prepare'], base_path(), timeout: 30);
        $prepare->mustRun();
        $fixture = json_decode($prepare->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        foreach (['issue', 'cancel'] as $mode) {
            $process = new Process([PHP_BINARY, $worker, $database, $mode, json_encode([...$fixture, 'key' => (string) Str::uuid()], JSON_THROW_ON_ERROR)], base_path(), timeout: 30);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        expect(count(array_filter($results, fn ($result) => isset($result['rejected']))))->toBe(1);
        $pdo = new PDO('sqlite:'.$database);
        $status = $pdo->query('SELECT status FROM print_orders')->fetchColumn();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
        expect(($status === 'confirmed' && $count === 1) || ($status === 'cancelled' && $count === 0))->toBeTrue();
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
