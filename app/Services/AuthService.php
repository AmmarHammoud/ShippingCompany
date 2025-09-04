<?php

namespace App\Services;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use App\Http\Responses\Response;
use App\Mail\DeleteUserMail;
use App\Mail\VerificationCodeMail;
use App\Notifications\Notice;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Kreait\Firebase\JWT\Contract\Token;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;


class AuthService {
    public function signup(Request $request): array
    {

        $request->validated();
        //$image = $this->fileUploader->storeFile($request, 'image');
        $user = User::query()->create([
            'name' => $request->name,
            'email' => $request['email'],
            'phone' => $request['phone'],
            'password' => Hash::make($request['password'])
        ]);
        return $this->userCreation($request['role'], $user);
    }

    public function userCreation($role1, \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Builder $user): array
    {
        if ($role1 != 'center_manager') {// create the verification code right here
            if($role1 != 'client') $role1 = 'client';
            VerificationCode::query()->where('email', $user['email'])->delete();
            $verification_code = mt_rand(100000, 999999);
            $data = [
                'email' => $user['email'],
                'verification_code' => $verification_code
            ];
            VerificationCode::create($data);
            Mail::to($user['email'])->send(new VerificationCodeMail($verification_code));
        }

        $role = Role::query()->where('name', $role1)->first();
        $user->assignRole($role);
        $permissions = $role->permissions()->pluck('name')->toArray();
        $user->givePermissionTo($permissions);
        $user->load('roles', 'permissions');
        $user = User::query()->where('email', $user['email'])->first();

        $user = $this->appendRolesAndPermissions($user);

        if ($role1 != 'center_manager') $message = __('messages.registration_successful');
        else $message = 'Center manager created.';
        return ['user' => $user, 'message' => $message];
    }

    public function signin($request)
    {
        $user = User::query()->where('email', $request['email'])->first();
        if (is_null($user)) {
            return ['user' => [], 'message' => __('messages.not_signed_up_yet'), 'status' => 404];
        }

        if (!Auth::attempt($request->only('email', 'password'))) {
            return ['user' => [], 'message' => __('messages.invalid_credentials'), 'status' => 401];
        }

        if (is_null($user['email_verified_at'])) {
            throw new Exception(__('messages.email_not_confirmed'), 403);
        }

        $user = $this->appendRolesAndPermissions($user);
        $user['token'] = $user->createToken('Auth token')->plainTextToken;
        //$user['fcm_token'] = $request->fcm_token;

        return ['user' => $user, 'message' => __('messages.signin_successful'), 'status' => 200];
    }

    public function signout(): array
    {
        $user = Auth::user();

        if (is_null($user)) {
            return ['message' => __('messages.invalid_token'), 'status' => 401];
        }

        Auth::user()->currentAccessToken()->delete();
        return ['message' => __('messages.signout_successful')];
    }

    public function updateProfile($request)
    {
        $user = Auth::user();
        if (isset($request['name'])) $user['name'] = $request['name'];
        $user->save();

        if (isset($request['password'])) {
            $request->validate([
                'old_password' => 'required',
                'password' => 'required|confirmed'
            ]);
            $matching = Hash::check($request->old_password, Auth::user()->getAuthPassword());
            if (!$matching) {
                throw new \Exception(__('messages.old_password_mismatch'));
            }
            $user['password'] = Hash::make($request->password);
        }

        $user->save();
        return ['message' => __('messages.profile_updated_successfully'), 'profile' => $user];
    }

     public function deleteAccount()
    {
        User::find(Auth::id())->delete();
        return ['message' => __('messages.account_deleted_successfully')];
    }

    public function deleteUser($user_id)
    {
        $user = User::find($user_id);
        if (!$user) throw new \Exception(__('messages.user_not_found'), 404);
        if (!Auth::user()->hasRole('superAdmin'))
            throw new \Exception(__('messages.prohibited_delete_admin'), 422);
        Mail::to($user['email'])->send(new DeleteUserMail($user['name']));
        $user->delete();
        return ['message' => __('messages.user_deleted_successfully')];
    }

     public function appendRolesAndPermissions($user)
    {
        $roles = [];
        foreach ($user->roles as $role) {
            $roles[] = $role->name;
        }
        unset($user['roles']);
        $user['roles'] = $roles;

        $permissions = [];
        foreach ($user->permissions as $permission) {
            $permissions[] = $permission->name;
        }
        unset($user['permissions']);
        $user['permissions'] = $permissions;

        return $user;
    }

}
