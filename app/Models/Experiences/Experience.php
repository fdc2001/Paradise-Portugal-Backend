<?php

namespace App\Models\Experiences;

use App\Casts\House\PriceCast;
use App\Models\Contracts\HasPoi;
use App\Models\Contracts\Interfaces\HasStaticMap;
use App\Models\Rating;
use App\Models\Settings\ExperiencePartner;
use App\Models\Settings\ExperienceService;
use App\Models\Settings\ExperienceType;
use App\Models\WishlistItems;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Experience extends Model implements HasMedia, HasStaticMap
{
    use HasTranslations, softDeletes, InteractsWithMedia, HasPoi;

    public $translatable = ['name', 'description', 'additional_info'];

    protected $fillable = [
        'experience_type_id',
        'experience_partner_id',
        'name',
        'description',
        'min_guests',
        'additional_info',
        'latitude',
        'longitude',
        'order',
    ];

    protected $appends = ['latitude', 'longitude'];

    protected static function booted(): void
    {
        static::creating(function (Experience $experience) {
            $maxOrder = self::where('experience_type_id', $experience->experience_type_id)->max('order');
            $experience->order = is_null($maxOrder) ? 1 : $maxOrder + 1;
        });
        static::updating(function (Experience $experience) {
            if ($experience->isDirty('experience_type_id')) {
                $maxOrder = self::where('experience_type_id', $experience->experience_type_id)->max('order');
                $experience->order = is_null($maxOrder) ? 1 : $maxOrder + 1;
            } elseif ($experience->isDirty('order')) {
                $experiences = self::where('experience_type_id', $experience->experience_type_id)
                    ->where('id', '!=', $experience->id)
                    ->orderBy('order')
                    ->get();

                $newOrder = 1;
                foreach ($experiences as $exp) {
                    if ($newOrder == $experience->order) $newOrder++;
                    $exp->order = $newOrder++;
                    $exp->saveQuietly();
                }
            }
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('experience_type_id')->orderBy('order');
    }

    public function experienceType(): BelongsTo
    {
        return $this->belongsTo(ExperienceType::class);
    }

    public function experiencePartner(): BelongsTo
    {
        return $this->belongsTo(ExperiencePartner::class);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('webp_format')
            ->format('webp');
    }

    public function getFeaturedImageLink(): ?string
    {
        return $this->getFirstMediaUrl(conversionName: 'webp_format');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(ExperienceService::class);
    }

    public function latitude():Attribute
    {
        return Attribute::make(get: fn() => $this->experiencePartner->latitude);
    }

    public function longitude():Attribute
    {
        return Attribute::make(get: fn() => $this->experiencePartner->longitude);
    }

    public function getExtraAttributes():array
    {
        return [
            'images' => $this->images,
            'description' => $this->description,
            'experienceType' => [
                'id' => $this->experienceType->id,
                'name' => $this->experienceType->name,
                'image' => $this->experienceType->getFirstMediaUrl('default', 'thumb'),
            ],
        ];
    }

    public function images(): Attribute
    {
        return Attribute::make(get: function (){
            return $this->getMedia()
                ->transform(fn(Media $media) => $media->getFullUrl('webp_format'))
                ->values()
                ->toArray();
        })->shouldCache();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ExperienceTicket::class);
    }

    public function availability(): HasMany
    {
        return $this->hasMany(ExperiencesAvailability::class);
    }

    public function averagePrice(): Attribute
    {
        return Attribute::make(get: function () {
            return $this->tickets()
                ->with('prices')
                ->get()
                ->flatMap(function ($ticket) {
                    return $ticket->prices;
                })->avg('price');
        })->shouldCache();
    }

    public function searchScore(): int
    {
        $score = \Cache::rememberForever('relevance_experience_'.$this->getKey(), function () {
            $score = 0;
            $score += round($this->ratings->avg('score') * 10);

            if ($this->created_at->gt(now()->subDays(30))) $score += 10;


            $score += min(WishlistItems::where('wishable_type', Experience::class)->where('wishable_id', $this->getKey())->count(), 20);

            return $score;
        });


        return intval($score);
    }

    public function ratings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function formatToList()
    {
        return [
            'rating' => number_format($this->ratings()->avg('score')??0, 2),
            ...$this->formatToMap(),
        ];
    }
}
