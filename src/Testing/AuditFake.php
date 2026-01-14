<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use SSIPG\Auditable\AuditLogger;
use SSIPG\Auditable\Enums\AuditAction;

class AuditFake extends AuditLogger
{
    /** @var list<array<string, mixed>> */
    protected array $entries = [];

    /**
     * Assert that an audit entry was logged.
     *
     * @param  callable(array<string, mixed>): bool|null  $callback
     */
    public function assertLogged(AuditAction $action, ?callable $callback = null): void
    {
        $matching = $this->logged($action);

        Assert::assertNotEmpty(
            $matching,
            "Expected [$action->value] audit entry was not logged."
        );

        if ($callback) {
            $filtered = array_filter($matching, $callback);

            Assert::assertNotEmpty(
                $filtered,
                "Expected [$action->value] audit entry with matching callback was not logged."
            );
        }
    }

    /** Assert the total number of audit entries logged. */
    public function assertLoggedCount(int $count): void
    {
        Assert::assertCount($count, $this->entries);
    }

    /**
     * Assert that an audit entry was not logged.
     *
     * @param  callable(array<string, mixed>): bool|null  $callback
     */
    public function assertNotLogged(AuditAction $action, ?callable $callback = null): void
    {
        $matching = $this->logged($action);

        if ($callback) {
            $matching = array_filter($matching, $callback);
        }

        Assert::assertEmpty(
            $matching,
            "Unexpected [$action->value] audit entry was logged."
        );
    }

    /** Assert that no audit entries were logged. */
    public function assertNothingLogged(): void
    {
        Assert::assertEmpty($this->entries, 'Unexpected audit entries were logged.');
    }

    /** @param array{0: Model} $data */
    public function handleCreated(string $event, array $data): void
    {
        $this->capture(AuditAction::Created, $data[0]);
    }

    /** @param array{0: Model} $data */
    public function handleDeleted(string $event, array $data): void
    {
        $action = $this->isSoftDeleted($data[0])
            ? AuditAction::SoftDeleted
            : AuditAction::Deleted;

        $this->capture($action, $data[0]);
    }

    /** @param array{0: Model} $data */
    public function handleRestored(string $event, array $data): void
    {
        $this->capture(AuditAction::Restored, $data[0]);
    }

    /** @param array{0: Model} $data */
    public function handleUpdated(string $event, array $data): void
    {
        $this->capture(AuditAction::Updated, $data[0]);
    }

    /**
     * Get all logged entries, optionally filtered by action.
     *
     * @return list<array<string, mixed>>
     */
    public function logged(?AuditAction $action = null): array
    {
        if ($action === null) {
            return $this->entries;
        }

        return array_values(
            array_filter($this->entries, static fn (array $entry) => $entry['action'] === $action)
        );
    }

    protected function capture(AuditAction $action, Model $model): void
    {
        if (! $this->shouldLog()) {
            return;
        }

        $attribute = $this->getAuditableAttribute($model);

        if (! $attribute) {
            return;
        }

        if (! in_array($action, $attribute->events, true)) {
            return;
        }

        $changes = [];

        try {
            $changes = $this->getChanges($model, $action, $attribute);
        } catch (\JsonException) {
            // Ignore
        }

        if ($action === AuditAction::Updated && empty($changes)) {
            return;
        }

        $this->entries[] = [
            'action'   => $action,
            'model'    => $model::class,
            'model_id' => $model->getKey(),
            'changes'  => $changes,
        ];
    }

    protected function shouldLog(): bool
    {
        if (! config('auditable.enabled', false)) {
            return false;
        }

        $environments = config('auditable.environments', []);

        if ($environments === ['*']) {
            return true;
        }

        // Default to testing environment when none are configured
        if (empty($environments)) {
            $environments = ['testing'];
        }

        return app()->environment($environments);
    }
}
