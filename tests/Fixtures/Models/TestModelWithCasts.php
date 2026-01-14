<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use SSIPG\Auditable\Attributes\Auditable;

/**
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 */
#[Auditable]
class TestModelWithCasts extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'test_models_with_casts';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings'  => 'array',
        ];
    }
}
