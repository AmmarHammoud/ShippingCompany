<?php

namespace App\Services;

use App\Models\Center;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SuperAdminService
{
    public static function create(array $data): User
    {
        $existing = User::where('center_id', $data['center_id'])
            ->where('role', 'center_manager')->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'This center already has a manager']
            );        }

        $manager = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => 'center_manager',
            'center_id' => $data['center_id'],
            'is_approved' => true,
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $manager->assignRole('center_manager');

        return $manager;
    }

    public static function update(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $user;
    }

    public static function delete(User $user): void
    {
        if ($user->role !== 'center_manager') {
            throw new \Exception("This user is not a center manager.");
        }

        $user->delete();
    }


    
    public function createCenter(array $data): Center
    {
        if (Center::where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Center with this name already exists.',
            ]);
        }

        if (
            Center::where('latitude', $data['latitude'])
            ->where('longitude', $data['longitude'])
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'coordinates' => 'A center with these coordinates already exists.',
            ]);
        }

        return Center::create($data);
    }

    public function updateCenter(int $id, array $data): Center
    {
        $center = Center::findOrFail($id);

        if (isset($data['latitude']) && isset($data['longitude'])) {
            $exists = Center::where('id', '!=', $id)
                ->where('latitude', $data['latitude'])
                ->where('longitude', $data['longitude'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'coordinates' => 'A center with these coordinates already exists.',
                ]);
            }
        }

        if (isset($data['name'])) {
            $nameExists = Center::where('id', '!=', $id)
                ->where('name', $data['name'])
                ->exists();

            if ($nameExists) {
                throw ValidationException::withMessages([
                    'name' => 'Another center with this name already exists.',
                ]);
            }
        }

        $center->update($data);
        return $center;
    }

    public function deleteCenter(int $id): bool
    {
        $center = Center::findOrFail($id);
        return $center->delete();
    }

}
