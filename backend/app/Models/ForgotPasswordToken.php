<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForgotPasswordToken extends Model
{
    use HasFactory;

    protected $table = "password_reset_tokens";


    const UPDATED_AT = null;
   

    protected $fillable = [
        "number",
        "token"
    ];
}
