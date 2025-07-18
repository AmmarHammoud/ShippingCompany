<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\SignUpRequest;
use App\Http\Requests\SignInRequest;
use App\Services\AuthService;
use App\Http\Responses\Response;
use Throwable;

class AuthController extends Controller
{
    private AuthService $userService;

    public function __construct(AuthService $userService)
    {
        $this->userService = $userService;
    }

    public function signUp(SignUpRequest $signUpRequest)
    {
        try {
            $data = $this->userService->signup($signUpRequest);
            return Response::success($data['message'], $data['user']);
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();
            return Response::error($message);
        }
    }

    public function signIn(SignInRequest $signInRequest)
    {
        try {
            $data = $this->userService->signin($signInRequest);
            return Response::success($data['message'], $data['user']);
        } catch (Throwable $th) {
            $message = $th->getMessage();
            return Response::error($message);
        }
    }
}
