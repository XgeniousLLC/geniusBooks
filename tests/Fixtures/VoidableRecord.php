<?php

namespace Tests\Fixtures;

use App\Models\Concerns\Voidable;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal model used to exercise the Voidable convention in isolation.
 */
class VoidableRecord extends Model
{
    use Voidable;

    protected $guarded = [];

    protected $table = 'voidable_records';
}
