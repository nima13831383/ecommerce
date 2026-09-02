<?php

use Tests\Concerns\EnsuresMySqlTestDatabase;
use Tests\Support\Concurrency\ConcurrentProcessRunner;

uses(EnsuresMySqlTestDatabase::class);
it('runs two independent Laravel processes through a deterministic barrier', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $r = app(ConcurrentProcessRunner::class)->runSelfTest();
    expect($r['alive'])->toBeTrue()->and($r['pids']['A'])->not->toBe('')->and($r['pids']['B'])->not->toBe('')->and($r['results']['A']['exit'])->toBe(0)->and($r['results']['B']['exit'])->toBe(0)->and($r['results']['A']['json']['ok'])->toBeTrue()->and($r['results']['B']['json']['ok'])->toBeTrue();
});
