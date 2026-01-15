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

    /** @var list<class-string<Model>>|null */
    protected ?array $fakeModels = null;

    /** @param list<class-string<Model>>|null $fakeModels */
    public function __construct(?array $fakeModels = null)
    {
        $this->fakeModels = $fakeModels;
    }

    /**
     * Get all captured audit entries.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->entries;
    }

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

    /** Clear all captured audit entries. */
    public function clear(): void
    {
        $this->entries = [];
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
     * Get logged entries, optionally filtered by action and/or model.
     *
     * @param  class-string<Model>|null  $model
     * @return list<array<string, mixed>>
     */
    public function logged(?AuditAction $action = null, ?string $model = null): array
    {
        $entries = $this->entries;

        if ($action !== null) {
            $entries = array_filter($entries, static fn (array $entry) => $entry['action'] === $action->value);
        }

        if ($model !== null) {
            $entries = array_filter($entries, static fn (array $entry) => $entry['model'] === $model);
        }

        return array_values($entries);
    }

    protected function capture(AuditAction $action, Model $model): void
    {
        if (! $this->shouldLog()) {
            return;
        }

        if ($this->fakeModels !== null && ! in_array($model::class, $this->fakeModels, true)) {
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

        $this->entries[] = $this->buildPayload($action, $model, $changes);
    }
}
