<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Otp extends Model
{
    use HasFactory;

    // this should be in minutes
    public $TIME_LIMIT = 5;


    public $timestamps = false;

    const UPDATED_AT = null;


    public function validOrNot($enteredOtp){
        if(Carbon::parse($this->genrated_at)->addMinutes($this->TIME_LIMIT)->gt(Carbon::now())){
            // otp is valid
            if($this->value == $enteredOtp){
                return ["status"=>true];
            }else{
                // otp is not same as enterd otp
            return["status"=>false,"message"=>"entered invalid otp try again"];
            }
        }else{
            // otp time expired
            return["status"=>false,"message"=>"otp expired kindly click resend"];
        }
    }
}
