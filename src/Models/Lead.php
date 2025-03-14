<?php

namespace Visualbuilder\FilamentHubspot\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Lead
 *
 * @property int $id
 * @property string|null $salutation
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $website
 * @property string|null $company
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string $owner_type
 * @property int $owner_id
 * @property int|null $created_at
 * @property int|null $updated_at
 */
class Lead extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'salutation',
        'first_name',
        'last_name',
        'company',
        'email',
        'website',
        'owner_id',
        'owner_type',
        'job_title',
        'message',
    ];


    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->salutation, $this->first_name, $this->last_name])
            ->filter()
            ->implode(' ');
    }
}
