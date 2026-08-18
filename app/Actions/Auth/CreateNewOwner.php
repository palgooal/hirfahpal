<?php

namespace App\Actions\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\Admin;
use App\Models\Owner;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateNewOwner
{
    use PasswordValidationRules;

    public function create(array $input): Owner
    {
        Validator::make($input, [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:30',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (
                        User::where('phone', $value)->exists()
                        || Owner::where('phone', $value)->exists()
                        || Admin::where('phone', $value)->exists()
                    ) {
                        $fail('رقم الهاتف مستخدم مسبقًا.');
                    }
                },
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (
                        User::where('email', $value)->exists()
                        || Owner::where('email', $value)->exists()
                        || Admin::where('email', $value)->exists()
                    ) {
                        $fail('البريد الإلكتروني مستخدم مسبقًا.');
                    }
                },
            ],
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
        ], [
            'full_name.required' => 'الاسم الكامل مطلوب.',
            'full_name.string' => 'الاسم الكامل يجب أن يكون نصًا.',
            'full_name.max' => 'الاسم الكامل يجب ألا يزيد عن 255 حرفًا.',
            'phone.required' => 'رقم الهاتف مطلوب.',
            'phone.string' => 'رقم الهاتف يجب أن يكون نصًا.',
            'phone.max' => 'رقم الهاتف يجب ألا يزيد عن 30 خانة.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.string' => 'البريد الإلكتروني يجب أن يكون نصًا.',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.max' => 'البريد الإلكتروني يجب ألا يزيد عن 255 حرفًا.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.string' => 'كلمة المرور يجب أن تكون نصًا.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'terms.accepted' => 'يجب الموافقة على شروط الاستخدام.',
        ])->validate();

        return Owner::create([
            'name' => $input['full_name'],
            'phone' => $input['phone'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'status' => 'active',
        ]);
    }
}
