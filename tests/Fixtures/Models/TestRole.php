<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TestRole extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'test_roles';

    /** @return BelongsToMany<TestUser, $this, TestUserRole, 'pivot'> */
    public function users(): BelongsToMany
    {
        return $this
            ->belongsToMany(TestUser::class, 'test_user_roles', 'role_id', 'user_id')
            ->using(TestUserRole::class)
            ->withPivot('assigned_by');
    }
}
