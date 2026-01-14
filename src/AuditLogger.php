<?php

declare(strict_types=1);

namespace SSIPG\Auditable;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use JsonException;
use SSIPG\Auditable\Attributes\Auditable;
use SSIPG\Auditable\Contracts\AuditContextProvider;
use SSIPG\Auditable\Contracts\AuditFormatter;
use SSIPG\Auditable\Enums\AuditAction;

class AuditLogger
{
    /** @var list<string> Always exclude timestamp columns - the audit log has its own timestamp */
    protected const array EXCLUDED_COLUMNS = ['created_at', 'updated_at', 'deleted_at'];

    /** @param array{0: Model} $data */
    public function handleCreated(string $event, array $data): void
    {
        $model = $data[0];

        $this->log(AuditAction::Created, $model);
    }

    /** @param array{0: Model} $data */
    public function handleDeleted(string $event, array $data): void
    {
        $model = $data[0];
        $action = $this->isSoftDeleted($model) ? AuditAction::SoftDeleted : AuditAction::Deleted;

        $this->log($action, $model);
    }

    /** @param array{0: Model} $data */
    public function handleRestored(string $event, array $data): void
    {
        $model = $data[0];

        $this->log(AuditAction::Restored, $model);
    }

    /** @param array{0: Model} $data */
    public function handleUpdated(string $event, array $data): void
    {
        $model = $data[0];

        $this->log(AuditAction::Updated, $model);
    }

    /**
     * Cast a value according to the model's cast type.
     *
     * @throws JsonException
     */
    protected function castValue(mixed $value, string $castType): mixed
    {
        // Handle nullable values
        if ($value === null) {
            return null;
        }

        // Normalize cast type (handle 'boolean', 'bool', 'int', 'integer', etc.)
        $castType = strtolower($castType);

        return match ($castType) {
            'bool', 'boolean' => (bool) $value,
            'int', 'integer' => (int) $value,
            'float', 'double', 'real' => (float) $value,
            'string' => (string) $value,
            'array', 'json' => is_string($value)
                ? json_decode($value, true, 512, JSON_THROW_ON_ERROR)
                : $value,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function filterColumns(array $data, Auditable $attribute, bool $isUpdate = false): array
    {
        // Always exclude timestamp columns
        $data = array_diff_key($data, array_flip(self::EXCLUDED_COLUMNS));

        // Include only specified columns
        if ($attribute->columns !== null) {
            $data = array_intersect_key($data, array_flip($attribute->columns));
        }

        // Exclude specified columns
        if ($attribute->exclude) {
            $data = array_diff_key($data, array_flip($attribute->exclude));
        }

        // Redact specified columns
        foreach ($attribute->redact as $column) {
            if (array_key_exists($column, $data)) {
                $data[$column] = $isUpdate && $attribute->withOriginal
                    ? ['old' => '[REDACTED]', 'new' => '[REDACTED]']
                    : '[REDACTED]';
            }
        }

        return $data;
    }

    protected function getAuditableAttribute(Model $model): ?Auditable
    {
        static $cache = [];

        $class = $model::class;

        if (array_key_exists($class, $cache)) {
            return $cache[$class];
        }

        try {
            $reflection = new \ReflectionClass($model);
            $attributes = $reflection->getAttributes(Auditable::class);

            /** @var Auditable|null $instance */
            $instance = ($attributes[0] ?? null)?->newInstance();

            return $cache[$class] = $instance;
        } catch (Exception) {
            return $cache[$class] = null;
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    protected function getChanges(Model $model, AuditAction $action, Auditable $attribute): array
    {
        // Deleted/soft_deleted/restored - just log the ID
        if (in_array($action, [AuditAction::Deleted, AuditAction::SoftDeleted, AuditAction::Restored], true)) {
            return ['id' => $model->getKey()];
        }

        if ($action === AuditAction::Created) {
            return $this->filterColumns(
                $this->normalizeValues($model, $model->getAttributes()),
                $attribute
            );
        }

        // Updated - compare using cast values to filter false-positives (e.g. 0 vs false)
        $changes = [];
        $casts = $model->getCasts();

        foreach ($model->getChanges() as $key => $newValue) {
            $oldValue = $model->getOriginal($key);

            // Use model's casts to normalize comparison
            if (isset($casts[$key])) {
                $castOld = $this->castValue($oldValue, $casts[$key]);
                $castNew = $this->castValue($newValue, $casts[$key]);

                // Skip if values are equivalent after casting
                if ($castOld === $castNew) {
                    continue;
                }

                // Use cast values in the log for consistency
                $oldValue = $castOld;
                $newValue = $castNew;
            }

            if ($attribute->withOriginal) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            } else {
                $changes[$key] = $newValue;
            }
        }

        return $this->filterColumns($changes, $attribute, isUpdate: true);
    }

    /** @return list<AuditContextProvider> */
    protected function getContextProviders(): array
    {
        $providers = [];

        foreach (config('auditable.context_providers', []) as $providerClass) {
            $providers[] = app($providerClass);
        }

        return $providers;
    }

    protected function getFormatter(): AuditFormatter
    {
        $formatterClass = config('auditable.formatter', Formatters\JsonFormatter::class);

        return app($formatterClass);
    }

    protected function isSoftDeleted(Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true)
            && $model->getAttribute('deleted_at') !== null;
    }

    protected function log(AuditAction $action, Model $model): void
    {
        if (! $this->shouldLog()) {
            return;
        }

        $attribute = $this->getAuditableAttribute($model);

        if (! $attribute) {
            return;
        }

        // Check if this event type should be logged for this model
        if (! in_array($action, $attribute->events, true)) {
            return;
        }

        try {
            $changes = $this->getChanges($model, $action, $attribute);
        } catch (JsonException) {
            $changes = [];
        }

        // Skip if update had no tracked column changes
        if ($action === AuditAction::Updated && empty($changes)) {
            return;
        }

        $payload = [
            'action'    => $action->value,
            'context'   => app()->runningInConsole() ? 'cli' : 'web',
            'model'     => $model::class,
            'model_id'  => $model->getKey(),
            'user_id'   => auth()->id(),
            'ip'        => request()->ip(),
            'timestamp' => now()->toIso8601String(),
        ];

        // Collect context from all registered providers, then merge once
        $additionalContext = [];

        foreach ($this->getContextProviders() as $provider) {
            $additionalContext[] = $provider->getContext($model, $action);
        }

        if ($additionalContext) {
            $payload = array_merge($payload, ...$additionalContext);
        }

        $this->writeLog(array_merge($payload, ['changes' => $changes]));
    }

    /**
     * Normalize attribute values using model casts for created events.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    protected function normalizeValues(Model $model, array $attributes): array
    {
        $casts = $model->getCasts();

        foreach ($attributes as $key => $value) {
            if (isset($casts[$key])) {
                $attributes[$key] = $this->castValue($value, $casts[$key]);
            }
        }

        return $attributes;
    }

    protected function shouldLog(): bool
    {
        if (! config('auditable.enabled', false)) {
            return false;
        }

        $environments = config('auditable.environments', []);

        // Allow all environments if wildcard is set
        if ($environments === ['*']) {
            return true;
        }

        return app()->environment($environments);
    }

    /** @param array<string, mixed> $payload */
    protected function writeLog(array $payload): void
    {
        rescue(function () use ($payload) {
            $formatter = $this->getFormatter();
            $channel = config('auditable.channel', 'audit');

            Log::channel($channel)->info($formatter->format($payload));
        }, report: false);
    }
}
