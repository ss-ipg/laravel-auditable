<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TestUser extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'test_users';

    /** @return BelongsToMany<TestRole, $this, TestUserRole, 'pivot'> */
    public function roles(): BelongsToMany
    {
        return $this
            ->belongsToMany(TestRole::class, 'test_user_roles', 'user_id', 'role_id')
            ->using(TestUserRole::class)
            ->withPivot('assigned_by');
    }
}
