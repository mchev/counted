<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'domain',
        'tracking_id',
        'description',
        'is_active',
    ];

    protected $appends = [
        'tracking_script',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($site) {
            if (empty($site->tracking_id)) {
                $site->tracking_id = Str::random(16);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function getTrackingScriptAttribute(): string
    {
        $baseUrl = config('app.url');
        $scriptUrl = $baseUrl.'/analytics.js';

        return "<script>
(function() {
    var script = document.createElement('script');
    script.async = true;
    script.src = '{$scriptUrl}';
    script.setAttribute('data-site-id', '{$this->tracking_id}');
    var entry = document.getElementsByTagName('script')[0];
    entry.parentNode.insertBefore(script, entry);
})();
</script>";
    }
}
