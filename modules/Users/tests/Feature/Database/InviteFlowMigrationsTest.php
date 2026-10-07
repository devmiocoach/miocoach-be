<?php

namespace App\Modules\Users\Tests\Feature\Database;

use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InviteFlowMigrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    public function test_coach_invitations_token_accepts_strings_longer_than_64_chars(): void
    {
        $longToken = str_repeat('a', 400);

        $coachUserId = DB::table('users')->insertGetId([
            'name' => 'Coach User',
            'email' => 'coach@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coachId = DB::table('coaches')->insertGetId([
            'user_id' => $coachUserId,
            'slug' => 'test-coach-invite',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('coach_invitations')->insert([
            'coach_id' => $coachId,
            'email' => 'invited@example.com',
            'token' => $longToken,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DB::table('coach_invitations')->where('email', 'invited@example.com')->first();

        $this->assertNotNull($record);
        $this->assertEquals($longToken, $record->token);
        $this->assertEquals(400, strlen($record->token));
    }

    public function test_clients_table_has_objective_column_as_nullable_string(): void
    {
        $this->assertTrue(Schema::hasColumn('clients', 'objective'));

        $userId = DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'client-obj@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('clients')->insert([
            'user_id' => $userId,
            'objective' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DB::table('clients')->where('user_id', $userId)->first();
        $this->assertNull($record->objective);

        DB::table('clients')->where('user_id', $userId)->update(['objective' => 'Lose weight']);
        $updated = DB::table('clients')->where('user_id', $userId)->first();
        $this->assertEquals('Lose weight', $updated->objective);
    }

    public function test_clients_table_has_fitness_level_column_as_nullable_string(): void
    {
        $this->assertTrue(Schema::hasColumn('clients', 'fitness_level'));

        $userId = DB::table('users')->insertGetId([
            'name' => 'Test User 2',
            'email' => 'client-fit@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('clients')->insert([
            'user_id' => $userId,
            'fitness_level' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DB::table('clients')->where('user_id', $userId)->first();
        $this->assertNull($record->fitness_level);

        DB::table('clients')->where('user_id', $userId)->update(['fitness_level' => 'beginner']);
        $updated = DB::table('clients')->where('user_id', $userId)->first();
        $this->assertEquals('beginner', $updated->fitness_level);
    }
}
