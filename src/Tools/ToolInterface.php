<?php

declare(strict_types=1);

namespace McpEmail\Tools;

interface ToolInterface
{
    public function name(): string;

    /** @return array{title: string, description: string, inputSchema: array} */
    public function definition(): array;

    /**
     * @param array<string, mixed> $args
     * @return array{content: array<int, array{type: string, text: string}>, isError?: bool}
     */
    public function call(array $args): array;
}
