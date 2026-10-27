<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PhpToken;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CleanArchitectureBoundaryTest extends TestCase
{
    /**
     * Invariant: Controllers must NEVER swallow exceptions with try-catch blocks.
     * All domain and application exceptions must bubble to RFC 7807 global exception handler.
     * Verified via PHP Tokenizer (immune to comments, docblocks, strings, and whitespace tricks).
     */
    public function test_controllers_across_all_packages_contain_zero_try_catch_blocks(): void
    {
        $packagesPath = realpath(__DIR__ . '/../../packages');
        $this->assertNotFalse($packagesPath, 'Packages directory must exist.');

        $packageDirs = glob($packagesPath . '/*', GLOB_ONLYDIR) ?: [];
        $scannedCount = 0;

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

                $scannedCount++;
                $content = (string) file_get_contents($file->getPathname());
                $tokens = PhpToken::tokenize($content);

                $catchTokens = array_filter(
                    $tokens,
                    static fn (PhpToken $token): bool => $token->is(T_CATCH)
                );

                $violations = [];
                foreach ($catchTokens as $token) {
                    $violations[] = "line {$token->line}";
                }

                $this->assertEmpty(
                    $violations,
                    "Architectural Violation: Controller [{$file->getFilename()}] in [" . basename($packageDir) . "] contains try-catch at " . implode(', ', $violations) . "! Domain exceptions must bubble to RFC 7807 handler."
                );
            }
        }

        $this->assertGreaterThan(0, $scannedCount, 'Expected at least one controller to be scanned across packages.');
    }

    /**
     * Invariant: Strict DDD Domain layers (Identity, etc.) must have zero framework
     * (Illuminate) or Infrastructure/Http dependencies, whether via 'use' imports or inline FQCNs.
     */
    public function test_strict_ddd_domain_layers_have_zero_framework_or_infrastructure_dependencies(): void
    {
        $strictDddPackages = ['Identity'];

        $packagesRoot = realpath(__DIR__ . '/../../packages');
        $this->assertNotFalse($packagesRoot, 'Packages directory must exist.');

        foreach ($strictDddPackages as $pkgName) {
            $domainPath = $packagesRoot . '/' . $pkgName . '/src/Domain';
            if (!is_dir($domainPath)) {
                continue;
            }

            $forbiddenPrefixes = [
                'Illuminate\\',
                '\\Illuminate\\',
                "FlashCommerce\\{$pkgName}\\Infrastructure\\",
                "\\FlashCommerce\\{$pkgName}\\Infrastructure\\",
                "FlashCommerce\\{$pkgName}\\Http\\",
                "\\FlashCommerce\\{$pkgName}\\Http\\",
            ];

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($domainPath));

            foreach ($iterator as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $content = (string) file_get_contents($file->getPathname());
                $tokens = PhpToken::tokenize($content);

                $violations = [];

                foreach ($tokens as $token) {
                    if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                        $tokenText = $token->text;
                        foreach ($forbiddenPrefixes as $prefix) {
                            if (str_starts_with($tokenText, $prefix)) {
                                $violations[] = "[{$tokenText}] at line {$token->line}";
                            }
                        }
                    }
                }

                $this->assertEmpty(
                    $violations,
                    "Architectural Violation: File [{$file->getFilename()}] in [{$pkgName}/Domain] imports or uses forbidden namespace: " . implode('; ', $violations)
                );
            }
        }
    }

    /**
     * Invariant: Catalog Domain layer must never depend on HTTP or Infrastructure layers.
     */
    public function test_catalog_domain_layer_does_not_depend_on_http_or_infrastructure(): void
    {
        $catalogDomainPath = realpath(__DIR__ . '/../../packages/Catalog/src/Domain');
        $this->assertNotFalse($catalogDomainPath, 'Catalog Domain path must exist.');

        $forbiddenPrefixes = [
            'FlashCommerce\\Catalog\\Http\\',
            '\\FlashCommerce\\Catalog\\Http\\',
            'FlashCommerce\\Catalog\\Infrastructure\\',
            '\\FlashCommerce\\Catalog\\Infrastructure\\',
        ];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($catalogDomainPath));

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            $tokens = PhpToken::tokenize($content);

            $violations = [];

            foreach ($tokens as $token) {
                if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                    $tokenText = $token->text;
                    foreach ($forbiddenPrefixes as $prefix) {
                        if (str_starts_with($tokenText, $prefix)) {
                            $violations[] = "[{$tokenText}] at line {$token->line}";
                        }
                    }
                }
            }

            $this->assertEmpty(
                $violations,
                "Architectural Violation: Catalog Domain file [{$file->getFilename()}] depends on forbidden layer: " . implode('; ', $violations)
            );
        }
    }

    /**
     * Invariant: Cross-Package Boundary Isolation.
     * Packages must never import internal Infrastructure or Http layers of other packages.
     */
    public function test_cross_package_boundaries_prevent_internal_infrastructure_leakage(): void
    {
        $packagesPath = realpath(__DIR__ . '/../../packages');
        $this->assertNotFalse($packagesPath, 'Packages directory must exist.');

        $packageDirs = glob($packagesPath . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($packageDirs as $sourceDir) {
            $sourcePackage = basename($sourceDir);
            $sourceSrc = $sourceDir . '/src';
            if (!is_dir($sourceSrc)) {
                continue;
            }

            $forbiddenCrossPrefixes = [];
            foreach ($packageDirs as $targetDir) {
                $targetPackage = basename($targetDir);
                if ($targetPackage === $sourcePackage) {
                    continue;
                }

                $forbiddenCrossPrefixes[] = "FlashCommerce\\{$targetPackage}\\Infrastructure\\";
                $forbiddenCrossPrefixes[] = "\\FlashCommerce\\{$targetPackage}\\Infrastructure\\";
                $forbiddenCrossPrefixes[] = "FlashCommerce\\{$targetPackage}\\Http\\";
                $forbiddenCrossPrefixes[] = "\\FlashCommerce\\{$targetPackage}\\Http\\";
            }

            if (empty($forbiddenCrossPrefixes)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceSrc));

            foreach ($iterator as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $content = (string) file_get_contents($file->getPathname());
                $tokens = PhpToken::tokenize($content);

                $violations = [];
                foreach ($tokens as $token) {
                    if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                        $text = $token->text;
                        foreach ($forbiddenCrossPrefixes as $prefix) {
                            if (str_starts_with($text, $prefix)) {
                                $violations[] = "[{$text}] at line {$token->line}";
                            }
                        }
                    }
                }

                $this->assertEmpty(
                    $violations,
                    "Modular Monolith Violation: [{$sourcePackage}/{$file->getFilename()}] leaks internal implementation of another package: " . implode('; ', $violations)
                );
            }
        }
    }

    /**
     * Invariant: Canary verification that PHP Tokenizer correctly discriminates
     * actual code tokens from comments, strings, and whitespace tricks.
     */
    public function test_tokenizer_canary_correctly_differentiates_code_from_comments(): void
    {
        $codeSnippet = <<<'PHP'
        <?php
        // Note: do not catch (Exception $e) here
        /* catch (Throwable $t) */
        $stringWithCatch = "catch (QueryException $e)";
        try {
            $foo = 1;
        } catch(\RuntimeException $e) {
            $bar = 2;
        }
        PHP;

        $tokens = PhpToken::tokenize($codeSnippet);
        $catchTokens = array_values(array_filter(
            $tokens,
            static fn (PhpToken $t): bool => $t->is(T_CATCH)
        ));

        $this->assertCount(1, $catchTokens, 'Tokenizer canary must detect exactly 1 real T_CATCH token.');
        $this->assertSame(7, $catchTokens[0]->line, 'Expected T_CATCH to be detected on line 7.');
    }
}
