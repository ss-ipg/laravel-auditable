<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Unit;

use SSIPG\Auditable\Enums\AuditAction;
use SSIPG\Auditable\Facades\Audit;
use SSIPG\Auditable\Tests\Fixtures\Models\TestModel;
use SSIPG\Auditable\Tests\Fixtures\Models\TestModelWithCasts;
use SSIPG\Auditable\Tests\Fixtures\Models\TestModelWithColumns;
use SSIPG\Auditable\Tests\Fixtures\Models\TestModelWithEventsFilter;
use SSIPG\Auditable\Tests\Fixtures\Models\TestModelWithExclude;
use SSIPG\Auditable\Tests\Fixtures\Models\TestModelWithoutOriginal;
use SSIPG\Auditable\Tests\Fixtures\Models\TestModelWithRedaction;
use SSIPG\Auditable\Tests\Fixtures\Models\TestModelWithSoftDeletes;
use SSIPG\Auditable\Tests\Fixtures\Models\TestRole;
use SSIPG\Auditable\Tests\Fixtures\Models\TestUser;
use SSIPG\Auditable\Tests\Fixtures\Models\TestUserRole;
use SSIPG\Auditable\Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Audit::fake();
    }

    public function test_boolean_cast_normalization(): void
    {
        TestModelWithCasts::create(['name' => 'John', 'is_active' => true]);

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn ($entry) => $entry['changes']['is_active'] === true
        );
    }

    public function test_boolean_cast_prevents_false_positive(): void
    {
        // Create with boolean true, then "update" with integer 1 (equivalent after cast)
        $model = TestModelWithCasts::create(['name' => 'John', 'is_active' => true]);

        // Simulate what might happen with form input - integer instead of boolean
        /** @phpstan-ignore assign.propertyType */
        $model->is_active = 1;
        $model->save();

        // Should only have the Created log, no Updated (since 1 === true after cast)
        Audit::assertLoggedCount(1);
        Audit::assertNotLogged(AuditAction::Updated);
    }

    public function test_clear(): void
    {
        TestModel::create(['name' => 'John']);

        Audit::assertLoggedCount(1);
        Audit::clear();
        Audit::assertNothingLogged();

        TestModel::create(['name' => 'Jane']);

        Audit::assertLoggedCount(1);
    }

    public function test_columns_option(): void
    {
        TestModelWithColumns::create([
            'name'   => 'John',
            'email'  => 'john@example.com',
            'status' => 'active',
        ]);

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn ($entry) => isset($entry['changes']['name'], $entry['changes']['email'])
                && ! isset($entry['changes']['status'])
        );
    }

    public function test_created_event(): void
    {
        TestModel::create([
            'name'   => 'John Doe',
            'email'  => 'john@example.com',
            'status' => 'active',
        ]);

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn ($entry) => $entry['model'] === TestModel::class
                && $entry['changes']['name'] === 'John Doe'
                && $entry['changes']['email'] === 'john@example.com'
                && $entry['changes']['status'] === 'active'
        );
    }

    public function test_deleted_event(): void
    {
        $model = TestModel::create(['name' => 'John']);
        $model->delete();

        Audit::assertLogged(
            action: AuditAction::Deleted,
            callback: static fn ($entry) => $entry['changes']['id'] === $model->id
        );
    }

    public function test_disabled_config(): void
    {
        config(['auditable.enabled' => false]);

        TestModel::create(['name' => 'Test']);

        Audit::assertNothingLogged();
    }

    public function test_environments_config(): void
    {
        config(['auditable.environments' => ['production']]);

        TestModel::create(['name' => 'Test']);

        Audit::assertNothingLogged();
    }

    public function test_events_filter_option(): void
    {
        $model = TestModelWithEventsFilter::create(['name' => 'John']);
        $model->update(['name' => 'Jane']);
        $model->delete();

        Audit::assertLogged(AuditAction::Created);
        Audit::assertLogged(AuditAction::Deleted);
        Audit::assertNotLogged(AuditAction::Updated);
    }

    public function test_exclude_option(): void
    {
        TestModelWithExclude::create([
            'name'     => 'John',
            'email'    => 'john@example.com',
            'password' => 'secret',
        ]);

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn ($entry) => isset($entry['changes']['name'], $entry['changes']['email'])
                && ! isset($entry['changes']['password'])
        );
    }

    public function test_fake_all(): void
    {
        Audit::fake();

        TestModel::create(['name' => 'John']);
        TestModelWithColumns::create(['name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active']);
        TestModelWithRedaction::create(['name' => 'Bob', 'password' => 'secret']);

        Audit::assertLoggedCount(3);
    }

    public function test_fake_multiple_models(): void
    {
        Audit::fake([TestModel::class, TestModelWithColumns::class]);

        TestModel::create(['name' => 'John']);
        TestModelWithColumns::create(['name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active']);
        TestModelWithRedaction::create(['name' => 'Bob', 'password' => 'secret']);

        Audit::assertLoggedCount(2);

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn (array $entry) => $entry['model'] === TestModel::class
        );

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn (array $entry) => $entry['model'] === TestModelWithColumns::class
        );
    }

    public function test_fake_single_model(): void
    {
        Audit::fake(TestModel::class);

        TestModel::create(['name' => 'John']);
        TestModelWithColumns::create(['name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active']);

        Audit::assertLoggedCount(1);

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn (array $entry) => $entry['model'] === TestModel::class
        );
    }

    public function test_hard_delete_on_soft_delete_model(): void
    {
        $model = TestModelWithSoftDeletes::create(['name' => 'John']);
        $model->forceDelete();

        Audit::assertLogged(AuditAction::Deleted);
        Audit::assertNotLogged(AuditAction::SoftDeleted);
    }

    public function test_json_cast_normalization(): void
    {
        $settings = ['theme' => 'dark', 'notifications' => true];

        TestModelWithCasts::create(['name' => 'John', 'settings' => $settings]);

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn ($entry) => $entry['changes']['settings'] === $settings
        );
    }

    public function test_json_cast_prevents_false_positive(): void
    {
        $settings = ['theme' => 'dark'];

        $model = TestModelWithCasts::create(['name' => 'John', 'settings' => $settings]);
        $model->settings = ['theme' => 'dark'];
        $model->save();

        // Should only have the Created log, no Updated (same value after decode)
        Audit::assertLoggedCount(1);
        Audit::assertNotLogged(AuditAction::Updated);
    }

    public function test_logged(): void
    {
        $model = TestModel::create(['name' => 'John']);
        $model->update(['name' => 'Jane']);

        TestModelWithColumns::create(['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'active']);

        // No filters - all entries
        $this->assertCount(3, Audit::logged());

        // Filter by model only
        $this->assertCount(2, Audit::logged(model: TestModel::class));
        $this->assertCount(1, Audit::logged(model: TestModelWithColumns::class));

        // Filter by action only
        $this->assertCount(2, Audit::logged(action: AuditAction::Created));
        $this->assertCount(1, Audit::logged(action: AuditAction::Updated));

        // Filter by both
        $this->assertCount(1, Audit::logged(action: AuditAction::Created, model: TestModel::class));
        $this->assertCount(1, Audit::logged(action: AuditAction::Updated, model: TestModel::class));
        $this->assertCount(0, Audit::logged(action: AuditAction::Updated, model: TestModelWithColumns::class));
    }

    public function test_multiple_models_with_different_configs(): void
    {
        // Create models with different attribute configurations
        TestModel::create(['name' => 'John', 'email' => 'john@example.com']);
        TestModelWithColumns::create(['name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active']);
        TestModelWithRedaction::create(['name' => 'Bob', 'password' => 'secret123']);

        // Verify each model logged according to its own config
        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn (array $entry) => $entry['model'] === TestModel::class
                && $entry['changes']['name'] === 'John'
                && $entry['changes']['email'] === 'john@example.com'
        );

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn (array $entry) => $entry['model'] === TestModelWithColumns::class
                && $entry['changes']['name'] === 'Jane'
                && $entry['changes']['email'] === 'jane@example.com'
                && ! isset($entry['changes']['status']) // excluded via config
        );

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn (array $entry) => $entry['model'] === TestModelWithRedaction::class
                && $entry['changes']['name'] === 'Bob'
                && $entry['changes']['password'] === '[REDACTED]'
        );

        Audit::assertLoggedCount(3);
    }

    public function test_pivot_model_auditing(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $role = TestRole::create(['name' => 'Admin']);

        // Attach creates a pivot record
        $user->roles()->attach($role->id, ['assigned_by' => 'System']);

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn (array $entry) => $entry['model'] === TestUserRole::class
                && $entry['changes']['user_id'] === $user->id
                && $entry['changes']['role_id'] === $role->id
                && $entry['changes']['assigned_by'] === 'System'
        );
    }

    public function test_pivot_model_detach(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $role = TestRole::create(['name' => 'Admin']);

        $user->roles()->attach($role->id);
        $user->roles()->detach($role->id);

        Audit::assertLogged(
            action: AuditAction::Deleted,
            callback: static fn (array $entry) => $entry['model'] === TestUserRole::class
        );
    }

    public function test_redact_on_update(): void
    {
        $model = TestModelWithRedaction::create([
            'name'     => 'John',
            'password' => 'secret-old',
        ]);

        $model->update(['password' => 'secret-new']);

        Audit::assertLogged(
            action: AuditAction::Updated,
            callback: static fn ($entry) => $entry['changes']['password']['old'] === '[REDACTED]'
                && $entry['changes']['password']['new'] === '[REDACTED]'
        );
    }

    public function test_redact_option(): void
    {
        TestModelWithRedaction::create([
            'name'     => 'John',
            'password' => 'Secret-123!',
        ]);

        Audit::assertLogged(
            action: AuditAction::Created,
            callback: static fn ($entry) => $entry['changes']['name'] === 'John'
                && $entry['changes']['password'] === '[REDACTED]'
        );
    }

    public function test_restored_event(): void
    {
        $model = TestModelWithSoftDeletes::create(['name' => 'John']);
        $model->delete();
        $model->restore();

        Audit::assertLogged(
            action: AuditAction::Restored,
            callback: static fn ($entry) => $entry['changes']['id'] === $model->id
        );
    }

    public function test_soft_deleted_event(): void
    {
        $model = TestModelWithSoftDeletes::create(['name' => 'John']);
        $model->delete();

        Audit::assertLogged(
            action: AuditAction::SoftDeleted,
            callback: static fn ($entry) => $entry['changes']['id'] === $model->id
        );

        Audit::assertNotLogged(AuditAction::Deleted);
    }

    public function test_untracked_columns_skipped(): void
    {
        $model = TestModelWithColumns::create(['name' => 'John', 'status' => 'active']);
        $model->update(['status' => 'inactive']);

        Audit::assertLoggedCount(1);
        Audit::assertNotLogged(AuditAction::Updated);
    }

    public function test_updated_event(): void
    {
        $model = TestModel::create(['name' => 'John', 'email' => 'john@example.com']);
        $model->update(['name' => 'Jane']);

        Audit::assertLoggedCount(2);

        Audit::assertLogged(
            action: AuditAction::Updated,
            callback: static fn ($entry) => $entry['changes']['name']['old'] === 'John'
                && $entry['changes']['name']['new'] === 'Jane'
                && ! isset($entry['changes']['email'])
        );
    }

    public function test_wildcard_environments(): void
    {
        config(['auditable.environments' => ['*']]);

        TestModel::create(['name' => 'Test']);

        Audit::assertLogged(AuditAction::Created);
    }

    public function test_with_original_false(): void
    {
        $model = TestModelWithoutOriginal::create(['name' => 'John']);
        $model->update(['name' => 'Jane']);

        Audit::assertLogged(
            action: AuditAction::Updated,
            callback: static fn ($entry) => $entry['changes']['name'] === 'Jane'
        );
    }
}
