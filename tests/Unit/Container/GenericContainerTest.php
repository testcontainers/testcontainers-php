<?php

declare(strict_types=1);

namespace Testcontainers\Tests\Unit\Container;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Testcontainers\Container\GenericContainer;

/**
 * @covers \Testcontainers\Container\GenericContainer
 */
class GenericContainerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidImageNames(): array
    {
        return [
            'uppercase repository' => ['MyImage:1.0', 'MyImage is not a valid Docker image name (in MyImage:1.0)'],
            'invalid tag' => ['nginx:-bad', '-bad is not a valid image versioning identifier (in nginx:-bad)'],
            'invalid registry port' => [
                'localhost:99999/app:1.0',
                'localhost:99999 is not a valid registry address (in localhost:99999/app:1.0)',
            ],
        ];
    }

    /**
     * The image name is validated before the Docker client is used, so no daemon is needed here.
     *
     * @dataProvider invalidImageNames
     */
    public function testRejectsInvalidImageName(string $image, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new GenericContainer($image);
    }
}
