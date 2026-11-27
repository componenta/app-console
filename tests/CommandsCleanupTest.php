<?php

declare(strict_types=1);

use Componenta\App\Build\ApplicationBuildOrchestrator;
use Componenta\App\Build\BuildStore;
use Componenta\App\Console\Build\ConsoleCommandBuilder;
use Componenta\ClassFinder\ClassIterator;

it('publishes an empty command map and retains it for pinned workers after clean', function (): void {
    $root = sys_get_temp_dir() . '/componenta_clean_commands_' . bin2hex(random_bytes(8));
    $store = new BuildStore($root);
    $orchestrator = new ApplicationBuildOrchestrator([new ConsoleCommandBuilder(new ClassIterator([]))], $store);

    try {
        $orchestrator->clean();
        expect(is_dir($root))->toBeFalse();
        $prepared = $orchestrator->build();
        file_put_contents($root . '/unrelated.txt', 'preserved');
        $orchestrator->clean();
        $orchestrator->clean();

        expect($store->load())->toBeNull()
            ->and($prepared->read(ConsoleCommandBuilder::ARTIFACT))->toBe([])
            ->and(file_get_contents($root . '/unrelated.txt'))->toBe('preserved')
            ->and($orchestrator->build()->generation)->not->toBe($prepared->generation);
    } finally {
        if (is_dir($root)) {
            $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($entries as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
            rmdir($root);
        }
    }
});
