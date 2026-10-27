<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CleanArchitectureBoundaryTest extends TestCase
{
    public function test_domain_layer_has_zero_framework_or_infrastructure_dependencies(): void
    {
        $domainPath = realpath(__DIR__ . '/../../packages/Identity/src/Domain');
        $this->assertNotFalse($domainPath, 'Domain path must exist');

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($domainPath));
        $forbiddenNamespaces = [
            'Illuminate\\',
            'FlashCommerce\\Identity\\Infrastructure',
            'FlashCommerce\\Identity\\Http',
        ];

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());

            foreach ($forbiddenNamespaces as $forbidden) {
                $this->assertStringNotContainsString(
                    'use ' . $forbidden,
                    $content,
                    "Architectural violation: File [{$file->getFilename()}] in Domain layer imports forbidden namespace [{$forbidden}]!"
                );
            }
        }
    }

    public function test_controllers_contain_zero_try_catch_blocks(): void
    {
        $packagesPath = realpath(__DIR__ . '/../../packages');
        $this->assertNotFalse($packagesPath, 'Packages path must exist');

        $packageDirs = glob($packagesPath . '/*', GLOB_ONLYDIR) ?: [];
        $scannedControllerCount = 0;

        foreach ($packageDirs as $packageDir) {
            $controllerPath = $packageDir . '/src/Http/Controllers';
            if (!is_dir($controllerPath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllerPath));

            foreach ($iterator as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $content = (string) file_get_contents($file->getPathname());
                $scannedControllerCount++;

                $this->assertStringNotContainsString(
                    'catch (',
                    $content,
                    "Architectural violation: Controller [{$file->getFilename()}] in [" . basename($packageDir) . "] contains a try-catch block! All domain exceptions must bubble to RFC 7807 global exception handler."
                );
            }
        }

        $this->assertGreaterThan(0, $scannedControllerCount, 'Expected at least one controller to be scanned across packages.');
    }
}
