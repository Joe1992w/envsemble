<?php

declare(strict_types=1);

namespace JoeWare\Envsemble;

class EnvMergeResult
{
    public function __construct(
        private array $variables,
        private array $report
    ) {}

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getReport(): array
    {
        return $this->report;
    }

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
        return $this->report['deleted_keys_count'] ?? 0;
    }

    public function getModifiedKeysCount(): int
    {
        return $this->report['modified_keys_count'] ?? 0;
    }

    public function getAddedKeysCount(): int
    {
        return $this->report['added_keys_count'] ?? 0;
    }

    public function getDeletedKeys(): array
    {
        return $this->report['deleted_keys'] ?? [];
    }

    public function getModifiedKeys(): array
    {
        return $this->report['modified_keys'] ?? [];
    }

    public function getAddedKeys(): array
    {
        return $this->report['added_keys'] ?? [];
    }

    public function getPatchFilesCount(): int
    {
        return $this->report['patch_files_count'] ?? 0;
    }

    public function getBaseKeysCount(): int
    {
        return $this->report['base_keys_count'] ?? 0;
    }
}
