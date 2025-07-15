<?php

namespace App\Http\Controllers;

use App\Http\Requests\CodeRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Responses\Response;
use App\Mail\SendPasswordResetCode;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    private PasswordResetService $passwordResetService;

    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }

    public function forgotPassword(ResetPasswordRequest $request)
    {
        try {
            $data = $this->passwordResetService->forgotPassword($request);
            return Response()->json(['message' => $data['message']], 200);
        } catch (\Throwable $throwable) {
            return Response::error($throwable->getMessage(), 422);
        }
    }

    public function checkCode(CodeRequest $request)
    {
        try {
            $data = $this->passwordResetService->checkCode($request);
            return Response()->json(['message' => $data['message']], 200);
        } catch (\Throwable $throwable) {
            return Response::error($throwable->getMessage(), 422);
        }
    }
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $data = $this->passwordResetService->resetPassword($request);
            return Response()->json(['message' => $data['message']], 200);
        } catch (\Throwable $throwable) {
            return Response::error($throwable->getMessage(), 422);
        }
    }
}
