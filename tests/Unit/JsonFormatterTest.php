<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Unit;

use SSIPG\Auditable\Formatters\JsonFormatter;
use SSIPG\Auditable\Tests\TestCase;

class JsonFormatterTest extends TestCase
{
    public function test_json(): void
    {
        $formatter = new JsonFormatter;

        $payload = [
            'action'   => 'created',
            'model'    => 'App\\Models\\User',
            'model_id' => 1,
            'changes'  => ['name' => 'John'],
        ];

        $result = $formatter->format($payload);

        $this->assertJson($result);
        $this->assertSame($payload, json_decode($result, true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_nested_arrays(): void
    {
        $formatter = new JsonFormatter;

        $payload = [
            'changes' => [
                'settings' => [
                    'theme'  => 'dark',
                    'nested' => ['deep' => 'value'],
                ],
            ],
        ];

        $result = $formatter->format($payload);

        $this->assertJson($result);
        $this->assertSame($payload, json_decode($result, true, 512, JSON_THROW_ON_ERROR));
    }

    public function test_unicode(): void
    {
        $formatter = new JsonFormatter;

        $payload = [
            'changes' => ['message' => 'こんにちは世界'],
        ];

        $result = $formatter->format($payload);
        $this->assertJson($result);

        $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('こんにちは世界', $decoded['changes']['message']);
    }
}
