<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FeatureTest extends TestCase
{
    const EXIT_SUCCESS = 0;

    public function test_php_binary_version_resolution(): void
    {
        [$output, $exit] = $this->execute('php -v');
        $this->assertMatchesRegularExpression('/^PHP \d\.\d\.\d+ \(cli\).*/', $output);
        $this->assertSame(self::EXIT_SUCCESS, $exit);
    }

    public function test_pgsql_extension_can_be_loaded(): void
    {
        [$output, $exit] = $this->execute('php -dextension=pgsql --ri pgsql');
        $this->assertStringContainsString('PostgreSQL (libpq) Version => ', $output);
        $this->assertSame(self::EXIT_SUCCESS, $exit);
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    private function execute(string $command): array
    {
        $output = [];
        $exit = null;

        $version = $_ENV['DEW_PHP_VERSION'];

        if ($version === '') {
            $this->markTestSkipped('The environment variable "DEW_PHP_VERSION" is missing.');
        }

        $cmd = preg_replace(
            '/^php /', "docker run --rm dew/{$version} ", $command
        );

        exec($cmd, $output, $exit);

        return [
            implode("\n", $output),
            $exit,
        ];
    }
}
