<?php

namespace App\Models;

//use App\BoardCategorie;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RaonBoardNotice extends Model
{
    use HasFactory;

    protected $primaryKey = 'idx';
//    public function categories()
//    {
//        return $this->hasOne(BoardCategorie::class);
//    }
}
