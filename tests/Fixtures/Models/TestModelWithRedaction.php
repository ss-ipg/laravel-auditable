<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use SSIPG\Auditable\Attributes\Auditable;

#[Auditable(columns: ['name', 'password'], redact: ['password'])]
class TestModelWithRedaction extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'test_models';
}
