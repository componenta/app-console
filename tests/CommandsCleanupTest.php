<?php

declare(strict_types=1);

it('cleans only its commands artifact and supports a new build after repeated cleanup', function (): void {
    $root = sys_get_temp_dir() . '/componenta_clean_commands_' . bin2hex(random_bytes(8));
    mkdir($root, 0700);
    $file = $root . '/nested/map.php';
    file_put_contents($root . '/source.php', '<?php');
    $builder = new \Componenta\App\Console\Build\ConsoleCommandBuilder(new \Componenta\ClassFinder\ClassIterator([]), $file);

    try {
        $builder->clean();
        expect(is_dir($root . '/nested'))->toBeFalse();
        $builder->build();
        expect(is_file($file))->toBeTrue();
        file_put_contents($root . '/nested/unrelated.php', 'preserved');
        file_put_contents($file, '<?php throw new LogicException("Cleanup must not load an artifact.");');

        $builder->clean();
        $builder->clean();

        expect(file_exists($file))->toBeFalse()
            ->and(file_get_contents($root . '/nested/unrelated.php'))->toBe('preserved');
        $builder->build();
        expect(is_file($file))->toBeTrue();
    } finally {
        foreach ([$file, $root . '/nested/unrelated.php', $root . '/source.php'] as $path) {
            if (is_file($path)) { unlink($path); }
        }
        if (is_dir($root . '/nested')) { rmdir($root . '/nested'); }
        rmdir($root);
    }
});

it('fails without deleting a directory in place of its commands artifact', function (): void {
    $root = sys_get_temp_dir() . '/componenta_clean_failure_commands_' . bin2hex(random_bytes(8));
    $file = $root . '/map.php';
    mkdir($file, 0700, true);
    file_put_contents($file . '/keep.txt', 'preserved');
    $builder = new \Componenta\App\Console\Build\ConsoleCommandBuilder(new \Componenta\ClassFinder\ClassIterator([]), $file);

    try {
        expect(fn () => $builder->clean())->toThrow(RuntimeException::class, $file);
        expect(file_get_contents($file . '/keep.txt'))->toBe('preserved');
    } finally {
        unlink($file . '/keep.txt');
        rmdir($file);
        rmdir($root);
    }
});
