<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'boutique_id',
        'date',
        'fond_de_caisse',
        'total_especes_theorique',
        'total_especes_physique',
        'ecart_caisse',
        'billetage',
        'total_ventes',
        'total_depenses',
        'benefice_net',
        'total_mobile_money',
        'total_carte_bancaire',
        'total_credit',
        'total_recouvrement',
        'nombre_ventes',
        'nombre_depenses',
        'statut_cloture',
        'cloture_par_user_id',
        'notes_cloture',
        'pdf_path',
        'sent_at'
    ];

    protected $casts = [
        'date' => 'date',
        'fond_de_caisse' => 'float',
        'total_especes_theorique' => 'float',
        'total_especes_physique' => 'float',
        'ecart_caisse' => 'float',
        'billetage' => 'array',
        'total_ventes' => 'float',
        'total_depenses' => 'float',
        'benefice_net' => 'float',
        'total_mobile_money' => 'float',
        'total_carte_bancaire' => 'float',
        'total_credit' => 'float',
        'total_recouvrement' => 'float',
        'sent_at' => 'datetime'
    ];

    public function boutique()
    {
        return $this->belongsTo(Boutique::class);
    }

    public function cloturePar()
    {
        return $this->belongsTo(User::class, 'cloture_par_user_id');
    }

    public function scopeForBoutique($query, $boutiqueId)
    {
        return $query->where('boutique_id', $boutiqueId);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeRecent($query, $limit = 30)
    {
        return $query->orderBy('date', 'desc')->limit($limit);
    }

    public function getIsSentAttribute()
    {
        return !is_null($this->sent_at);
    }
}
