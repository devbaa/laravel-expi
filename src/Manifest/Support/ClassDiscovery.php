<?php

declare(strict_types=1);

namespace Boralp\LaravelExpi\Manifest\Support;

use Symfony\Component\Finder\Finder;

/**
 * Discovers concrete, autoloadable classes under a directory by reading each
 * file's `namespace` + `class` declaration.
 *
 * It deliberately never executes the scanned files itself — it parses the
 * fully-qualified name out of the source and lets the autoloader resolve it via
 * {@see class_exists()}. That keeps discovery free of side effects beyond what
 * a normal autoload of the class would do.
 */
final class ClassDiscovery
{
    /**
     * @return list<class-string>
     */
    public function in(string $absolutePath): array
    {
        if (! is_dir($absolutePath)) {
            return [];
        }

        $classes = [];

        $files = Finder::create()
            ->files()
            ->in($absolutePath)
            ->name('*.php')
            ->sortByName();

        foreach ($files as $file) {
            $path = $file->getRealPath();

            if ($path === false) {
                continue;
            }

            $code = file_get_contents($path);

            if ($code === false) {
                continue;
            }

            if (preg_match('/^\s*namespace\s+([^;]+);/m', $code, $namespace) !== 1) {
                continue;
            }

            if (preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $code, $class) !== 1) {
                continue;
            }

            /** @var class-string $fqcn */
            $fqcn = trim($namespace[1]).'\\'.$class[1];

            if (class_exists($fqcn)) {
                $classes[] = $fqcn;
            }
        }

        return $classes;
    }
}
