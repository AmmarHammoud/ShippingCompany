<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Center;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'super_admin',
            'center_manager',
            'driver',
            'client'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // 2. الصلاحيات
        $permissions = [
            // Super Admin
            'manage_all_users',
            'manage_center_managers',
            'manage_centers',
            'manage_roles_permissions',
            'view_all_reports',
            'manage_policies',
            'manage_settings',
            'view_all_shipments',

            // Center Manager
            'approve_driver',
            'manage_local_drivers',
            'assign_shipments',
            'view_center_shipments',
            'view_center_reports',
            'mark_driver_unavailable',
            'update_local_shipment_status',




            // Driver
            'view_assigned_shipments',
            'update_shipment_status',
            'upload_proof_of_delivery',
            'accept_or_reject_shipment',
            'receive_notifications',

            // Client
            'create_shipment_request',
            'track_shipment',
            'cancel_shipment',
            'rate_delivery',
            'view_own_shipments',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        Role::findByName('super_admin')->givePermissionTo(Permission::all());

        Role::findByName('center_manager')->givePermissionTo([
            'approve_driver',
            'manage_local_drivers',
            'assign_shipments',
            'view_center_shipments',
            'view_center_reports',
            'mark_driver_unavailable',
            'update_local_shipment_status',
        ]);

        Role::findByName('driver')->givePermissionTo([
            'view_assigned_shipments',
            'update_shipment_status',
            'upload_proof_of_delivery',
            'accept_or_reject_shipment',
            'receive_notifications',
        ]);

        Role::findByName('client')->givePermissionTo([
            'create_shipment_request',
            'track_shipment',
            'cancel_shipment',
            'rate_delivery',
            'view_own_shipments',
        ]);

        $admin = User::firstOrCreate([
            'email' => 'admin@gmail.com',
        ], [
            'name' => 'System Admin',
            'password' => bcrypt('admin123'),
            'role' => 'super_admin',
            'is_approved' => true,
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole('super_admin');

        $provinces = [
            'Damascus',
            'Rural Damascus',
            'Aleppo',
            'Homs',
            'Hama',
            'Latakia',
            'Tartous',
            'Idlib',
            'Deir ez-Zor',
            'Hasakah',
            'Raqqa',
            'Daraa',
            'As-Suwayda',
            'Quneitra'
        ];
        foreach ($provinces as $province) {
            $center = Center::where('name', $province)->first();

            if (! $center) {
                $this->command->warn("⚠️ المركز '{$province}' غير موجود. تأكد من تشغيل CenterSeeder أولاً.");
                continue;
            }

            $email = Str::slug($province, '_') . '_manager@gmail.com';

            $manager = User::firstOrCreate([
                'email' => $email,
            ], [
                'name' => 'Manager of ' . $province,
                'password' => bcrypt('manager123'),
                'role' => 'center_manager',
                'center_id' => $center->id,
                'is_approved' => true,
                'active' => true,
                'email_verified_at' => now(),
            ]);

            $manager->assignRole('center_manager');
        }
    }
}
