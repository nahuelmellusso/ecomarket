<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Mail\UserRegistered;
use Exception;
use App\Models\Coupon;
use Illuminate\Support\Facades\Crypt;
use  Illuminate\Support\Facades\Mail;
use PhpParser\Node\Stmt\TryCatch;

class RegisterController extends Controller
{
    use RegistersUsers;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * The user has been registered.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    protected function registered(Request $request, User $user)
    {

         // crea el cupon de promo
         Coupon::create(['email' => $user->email, 'code' => 'ecosale','amount'=> 10,'type' => 'ONE_USE']);

        if ($user instanceof MustVerifyEmail) {
            $user->sendEmailVerificationNotification();

            return response()->json(['status' => trans('verification.sent')]);
        }
        

        try{
            $link = url('/newsletter/' . Crypt::encryptString($user->email));
            Mail::to($user->email)->send(new UserRegistered($link));
        }catch(Exception $e){   
            logger($e->getMessage());
        }
        

        return response()->json($user);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        
        return Validator::make($data, [
            'name' => 'required|max:255',
            'phone' => 'required|max:255',
            'address' => 'max:255',
            'birth_date' => 'required|date',
            'location_id' => 'required',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'birth_date' => $data['birth_date'],
            'location_id' => $data['location_id'],
            'address' => $data['address'],
            'password' => bcrypt($data['password']),
        ]);

       
    }
}
