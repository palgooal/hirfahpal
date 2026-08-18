<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = ['name', 'native', 'code', 'flag', 'is_rtl', 'is_active'];

    public function translationValues()
    {
        return $this->hasMany(TranslationValue::class, 'language_id', 'id');
    }
}
