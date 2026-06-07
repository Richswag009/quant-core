<?php

namespace App\Http\Controllers;

use App\Actions\Auth\CreateLogin;
use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;

class AuthController extends Controller
{


    public function login(LoginRequest $request, CreateLogin $createLogin)
    {
        try {
            $validated = $request->validated();
            $data = $createLogin($validated);

            return $this->okResponse('login successfully', $data);
        } catch (\Exception $th) {
            return $this->serverErrorResponse($th->getMessage());
        }
    }

    /**
     * Logout user (Revoke the token)
     *
     * @return JsonResponse [string] message
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return $this->okResponse('Successfully logged out');
    }

    public function user(Request $request)
    {
        $user = $request->user();

        $data = new UserResource($user->load('tenant'));

        return $this->okResponse('fetched user successfully', $data);
    }
}
