<?php


namespace App\Actions\Auth;


use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Traits\ResponseTrait;
use Illuminate\Database\Eloquent\Attributes\UseResource;

class CreateLogin
{
    //
    use ResponseTrait;

    public function __invoke(array $data)
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            throw new \Exception('Invalid user');
        }
        if (!Hash::check($data['password'], $user->password)) {
            throw new \Exception('invalid password');
        }
        $data = new UserResource($user->load('tenant'), true);
        return $data;
    }
}
