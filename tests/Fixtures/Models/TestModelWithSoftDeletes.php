<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use SSIPG\Auditable\Attributes\Auditable;

#[Auditable]
class TestModelWithSoftDeletes extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $table = 'test_models_with_soft_deletes';
}
