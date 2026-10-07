<?php

namespace Modules\Auth\Tests\Feature\Http\Requests;

use Illuminate\Support\Facades\Validator;
use Modules\Auth\Http\Requests\RegisterViaInviteRequest;
use Tests\TestCase;

class RegisterViaInviteRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new RegisterViaInviteRequest)->rules();
    }

    private function validPayload(): array
    {
        return [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'password' => 'Secret1!@#',
            'password_confirmation' => 'Secret1!@#',
            'dateOfBirth' => '1990-01-01',
            'gender' => 'male',
            'heightCm' => 175,
            'objective' => 'Lose weight',
            'fitnessLevel' => 'beginner',
        ];
    }

    public function test_valid_full_payload_passes(): void
    {
        $validator = Validator::make($this->validPayload(), $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_minimal_valid_payload_passes(): void
    {
        $data = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'password' => 'Secret1!@#',
            'password_confirmation' => 'Secret1!@#',
        ];

        $validator = Validator::make($data, $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_missing_first_name_fails(): void
    {
        $data = $this->validPayload();
        unset($data['firstName']);

        $validator = Validator::make($data, $this->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('firstName', $validator->errors()->toArray());
    }

    public function test_missing_last_name_fails(): void
    {
        $data = $this->validPayload();
        unset($data['lastName']);

        $validator = Validator::make($data, $this->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('lastName', $validator->errors()->toArray());
    }

    public function test_missing_password_fails(): void
    {
        $data = $this->validPayload();
        unset($data['password']);
        unset($data['password_confirmation']);

        $validator = Validator::make($data, $this->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_password_not_confirmed_fails(): void
    {
        $data = $this->validPayload();
        $data['password_confirmation'] = 'WrongConfirmation1!';

        $validator = Validator::make($data, $this->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_weak_password_fails(): void
    {
        $data = $this->validPayload();
        $data['password'] = 'password';
        $data['password_confirmation'] = 'password';

        $validator = Validator::make($data, $this->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    public function test_invalid_gender_fails(): void
    {
        $data = $this->validPayload();
        $data['gender'] = 'nonbinary';

        $validator = Validator::make($data, $this->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('gender', $validator->errors()->toArray());
    }

    public function test_invalid_fitness_level_fails(): void
    {
        $data = $this->validPayload();
        $data['fitnessLevel'] = 'expert';

        $validator = Validator::make($data, $this->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('fitnessLevel', $validator->errors()->toArray());
    }

    public function test_future_date_of_birth_fails(): void
    {
        $data = $this->validPayload();
        $data['dateOfBirth'] = now()->addYear()->format('Y-m-d');

        $validator = Validator::make($data, $this->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('dateOfBirth', $validator->errors()->toArray());
    }
}
