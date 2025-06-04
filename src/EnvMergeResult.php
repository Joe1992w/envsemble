<?php

declare(strict_types=1);

namespace JoeWare\Envsemble;

class EnvMergeResult
{
    /**
     * @param  array<string, array{value: string, comment?: string|null, source?: string|null}>  $variables
     * @param array{
     *     base_file: string,
     *     patch_files: array<int, string>,
     *     base_keys_count: int,
     *     patch_files_count: int,
     *     final_keys_count: int,
     *     deleted_keys: array<int, string>,
     *     modified_keys: array<int, string>,
     *     added_keys: array<int, string>,
     *     deleted_keys_count: int,
     *     modified_keys_count: int,
     *     added_keys_count: int
     * } $report
     */
    public function __construct(
        private array $variables,
        private array $report
    ) {}

    /**
     * @return array<string, array{value: string, comment?: string|null, source?: string|null}>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @return array{
     *     base_file: string,
     *     patch_files: array<int, string>,
     *     base_keys_count: int,
     *     patch_files_count: int,
     *     final_keys_count: int,
     *     deleted_keys: array<int, string>,
     *     modified_keys: array<int, string>,
     *     added_keys: array<int, string>,
     *     deleted_keys_count: int,
     *     modified_keys_count: int,
     *     added_keys_count: int
     * }
     */
    public function getReport(): array
    {
        return $this->report;
    }

    /**
     * @return array{value: string, comment?: string|null, source?: string|null}|null
     */
    public function getVariable(string $key): ?array
    {
        return $this->variables[$key] ?? null;
    }

    public function hasVariable(string $key): bool
    {
        return isset($this->variables[$key]);
    }

    public function getVariableValue(string $key): ?string
    {
        return $this->variables[$key]['value'] ?? null;
    }

    public function getVariableSource(string $key): ?string
    {
        return $this->variables[$key]['source'] ?? null;
    }

    public function getKeysCount(): int
    {
        return count($this->variables);
    }

    public function getDeletedKeysCount(): int
    {
        return $this->report['deleted_keys_count'];
    }

    public function getModifiedKeysCount(): int
    {
        return $this->report['modified_keys_count'];
    }

    public function getAddedKeysCount(): int
    {
        return $this->report['added_keys_count'];
    }

    /**
     * @return array<int, string>
     */
    public function getDeletedKeys(): array
    {
        return $this->report['deleted_keys'];
    }

    /**
     * @return array<int, string>
     */
    public function getModifiedKeys(): array
    {
        return $this->report['modified_keys'];
    }

    /**
     * @return array<int, string>
     */
    public function getAddedKeys(): array
    {
        return $this->report['added_keys'];
    }

    public function getPatchFilesCount(): int
    {
        return $this->report['patch_files_count'];
    }

    public function getBaseKeysCount(): int
    {
        return $this->report['base_keys_count'];
    }
}
