<?php

namespace App\Http\Controllers\API;

use Carbon\Carbon;
use App\Models\Otp;
use App\Models\User;
use App\Rules\NewUser;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ForgotPasswordToken;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserAuthController extends Controller
{

    // registering new user

    // body : [name , number , password , c_password]
    public function register(Request $request){

        $validation = Validator::make($request->all(),[
            "name"=>"required | max:255 | min:3 | ",
            "number"=>["required" , "max:10" , "min:10" ,new NewUser],
            "password"=>"required | min:8  | max:25" ,
            "c_password"=>"required | same:password"
        ]);

        if($validation->fails()){
            $response = [
                "status"=>"failed",
                "error"=> $validation->errors()
            ];
            return response()->json($response,422);
        }


        // validation completed now logic

        // create a new user 
        $input = $request->all();
        $input["password"] = bcrypt($input["password"]);

        $user = User::create($input);
        // genrate a new otp
        $genratedOtp = rand(1000,9999);
        $otp = new Otp;
        $otp->value = $genratedOtp;
        $otp->type = "profile";
        $user->otp()->save($otp); 

        // send the otp 

            // temp:saving otp to logs
            Log::info("your otp is ".$genratedOtp);

        // responde the user with ok status 

        $response["status"] = "ok";
        $response["message"] = "user registerd successfully";
        $response["location"] = "/api/confirm_otp";

        return response()->json($response,302);

    }


    // body : number , otp
    public function confirmOtp(Request $request){
        $validation = Validator::make($request->all(),[
            "number" =>"required | max : 10 | min : 10",
            "otp"=>"required | max : 4 | min : 4"
        ]);

        if($validation->fails()){
            $response = [
                "status"=>"failed",
                "error"=> $validation->errors()
            ];
            return response()->json($response,422);
        }

        // find the otp according to the number

        
        $user = User::where("number",$request->number)->first();
        // return $user->otp()->orderBy("created_at",'desc')->first()->created_at;
        $savedOTP = $user->otp()->orderBy("created_at",'desc')->first();
        $otpValidation = $savedOTP->validOrNot($request->otp);
        
        if(!$otpValidation["status"]){
                    $response = ["status"=>"failed",
                        "error"=> $otpValidation["message"]
                    ];
                    return response()->json($response,410);
        }

        $response = [
            "status"=>"ok",
            "message"=> "otp verified succesfully"
        ];
        
        if($savedOTP->type == "forgot"){
            $response["forgotToken"] = Str::random(15);
            // create a new forgot token in table
            $newResetToken  = new ForgotPasswordToken;
            $newResetToken->token = Hash::make($response["forgotToken"]);
            $user->passwordResetToken()->save($newResetToken);
        }

        // time is ok and otp is valid
        // remove all the otp from db 
        $user->otp()->delete();
        // add the number verified at time
        $user->number_verified_at = Carbon::now();
        $user->save();
        return response()->json($response,200);
    }


    // body : number , password
    public function login(Request $request){
        $validation = Validator::make($request->all(),[
            "number"=>"required | max:10 | min:10",
            "password"=>"required | min :8  | max:25"
        ]);

        if($validation->fails()){
            $response = [
                "status"=>"failed",
                "error"=> $validation->errors()
            ];
            return response()->json($response,422);
        }

       if( Auth::attempt(["number"=>$request->number,"password"=>$request->password])){
        $user = Auth::user();
            $success["token"]= $user->createToken("MyApp")->plainTextToken;
            $success["user"] = $user->makeHidden("password");
            $response = [
                "status"=>"ok",
                'message'=>"user login succesfully",
                'data'=>$success
            ];
            return response()->json($response,200);
       }
        $response = ["status"=>"failed","message"=>"invalid number or password"];
        return response()->json($response);
       
       
    }

    // body : number
    public function resendOtp(Request $request){
        // validation the input data
        $validation = Validator::make($request->all(),[
            "number"=>"required | max:10 | min:10"
        ]);

        // find the user
        $latestOtp = User::whereNumber($request->number)->firstOrFail()->otp()->orderBy("created_at","desc")->first();
        if(!$latestOtp->exists()){
            $response = ["status"=>"failed",
            "message"=>"kindly genrate a new otp first"];
            return response()->json($response,400);
        }

        // resend the otp to the number
        Log::info("your otp is ".$latestOtp->value);
        // add new date and time 
        $latestOtp->created_at = Carbon::now();
        $latestOtp->save();
        // if the above code not work then try this
        // $latestOtp->update(["created_at"=>Carbon::now()]);
        
        $response = ["status"=>"ok",
        "message"=>"otp resend to the number successfully"
        ];
        return response()->json($response,200);
    }

    // body : number
    public function forgotPassword(Request $request){
        $validation  = Validator::make($request->all(),[
            "number"=>"required | max:10 | min:10"
        ]);
        
        if($validation->fails()){
            $response = [
                "status"=>"failed",
                "error"=> $validation->errors()
            ];
            return response()->json($response,422);
        }

        // find the user from number

        $user = User::whereNumber($request->number)->firstOrFail();
        $genratedOtp = rand(1000,9999);
        $otp = new Otp;
        $otp->value = $genratedOtp;
        $otp->type = "forgot";
        $user->otp()->save($otp);
        // send otp to the number
        Log::info("your otp for resetting password is ". $genratedOtp);
        
        $response = [
            "status"=>"ok",
            "message"=>"verify its you by entering the otp"
        ];
        return response()->json($response,200);
    }

    // body : number , password , c_password , reset_token
    public function resetPassword(Request $request){

        // kindly add the middleware to the route

        $validation = Validator::make($request->all(),[
            "number"=>"required | min:10 | max:10 ",
            "password"=>"required",
            "c_password"=>"required | same:password",
            "reset_token"=>"required | min:15 | max:15"
        ]);

        if($validation->fails()){
            $response = [
                "status"=>"failed",
                "error"=> $validation->errors() 
            ];
            return response()->json($response,422);
        }

        $user = User::where("number",$request->number)->firstOrFail();
        $userForgotToken = $user->passwordResetToken()->orderBy("created_at",'desc')->first();

        if($userForgotToken->exists() && Hash::check( $request->reset_token ,$userForgotToken->token)){
            // now check the expiration time
            $checkExpiration = Carbon::parse($userForgotToken->created_at)->addMinutes(5)->gt(Carbon::now());
            if(!$checkExpiration){
                // token expired 
                // remove all the token and send back a error response
                $userForgotToken->delete();
                $response = ["status"=>"failed",
                "message"=>"time expired for resetting password",
                "redirect"=>"login"
            ];
            return response()->json($response,410);
            }

            // every thing is valid change the password

            $user->password = bcrypt($request->password);
            $user->save();

            $response = [
                "status"=>"ok",
                "message"=>"password updated successfully kindly login by new password",
            ];

            return response()->json($response,200);

        }else{
            // if toke is not present 
            $response = ["status"=>"failed",
                "message"=>"not valid reset password token",
                "redirect"=>"login"
            ];
            return response()->json($response,410);
        }
 


    }
}
