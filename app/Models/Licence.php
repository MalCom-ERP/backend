<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Licence extends Model
{
    use HasFactory;

    protected $fillable = [
        'boutique_id',
        'cle_licence',
        'duree_jours',
        'statut',
        'date_activation',
        'date_expiration',
        'created_by',
        'note',
    ];

    protected $casts = [
        'date_activation' => 'datetime',
        'date_expiration' => 'datetime',
        'duree_jours' => 'integer',
    ];

    protected static function booted()
    {
        static::saved(function ($licence) {
            if ($licence->boutique_id) {
                $boutique = Boutique::find($licence->boutique_id);
                $boutique?->recalculerLicence();
            }
        });

        static::deleted(function ($licence) {
            if ($licence->boutique_id) {
                $boutique = Boutique::find($licence->boutique_id);
                $boutique?->recalculerLicence();
            }
        });
    }

    public function boutique()
    {
        return $this->belongsTo(Boutique::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Génère une clé de licence unique et lisible au format MC-XXXX-XXXX-XXXX
     */
    public static function generateKey(): string
    {
        do {
            $part1 = strtoupper(Str::random(4));
            $part2 = strtoupper(Str::random(4));
            $part3 = strtoupper(Str::random(4));
            $key = "MC-{$part1}-{$part2}-{$part3}";
        } while (self::where('cle_licence', $key)->exists());

        return $key;
    }

    /**
     * Vérifie si la licence est échue
     */
    public function isExpired(): bool
    {
        if ($this->statut === 'expiree' || $this->statut === 'revoquee') {
            return true;
        }

        if ($this->duree_jours >= 90000) {
            return false; // Licence à vie / illimitée
        }

        if ($this->date_expiration && now()->greaterThan($this->date_expiration)) {
            return true;
        }

        return false;
    }
}
