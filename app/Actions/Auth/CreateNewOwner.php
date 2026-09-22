<?php

namespace App\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class CreateNewOwner
{
    public function __construct(private readonly CreateAccountUser $createAccountUser)
    {
    }

    public function create(array $input): Authenticatable
    {
        return $this->createAccountUser->create('customer', $input);
    }
}
