<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
// use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\User;
use App\Notifications;
use Carbon\Carbon;

class ExpenseTest extends TestCase
{
    use DatabaseMigrations;
    use WithFaker;

    public function test_protected_expense_routes()
    {
        $response = $this->getJson(route('expenses.index'));
        $response->assertStatus(401);

        $response = $this->postJson(route('expenses.store'));
        $response->assertStatus(401);

        $response = $this->getJson(route('expenses.show', 1));
        $response->assertStatus(401);

        $response = $this->putJson(route('expenses.update', 1));
        $response->assertStatus(401);

        $response = $this->deleteJson(route('expenses.destroy', 1));
        $response->assertStatus(401);
    }

    public function test_get_all_expenses()
    {
        $user = User::factory()->create();

        $expense = $user->expenses()->create([
            'description' => $this->faker->text(191),
            'date' => $this->faker->date('Y-m-d', 'now'),
            'value' => $this->faker->randomNumber(6)
        ]);

        $anotherUser = User::factory()->create();

        $anotherExpense = $anotherUser->expenses()->create([
            'description' => $this->faker->text(191),
            'date' => $this->faker->date('Y-m-d', 'now'),
            'value' => $this->faker->randomNumber(6)
        ]);

        $response = $this->actingAs($user)->getJson(route('expenses.index'));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'description' => $expense->description,
            'date' => $expense->date->format('Y-m-d'),
            'value' => $expense->value / 100.0,
        ]);
        // Test that the other user's expense is not returned
        $response->assertJsonMissing([
            'description' => $anotherExpense->description,
            'date' => $anotherExpense->date->format('Y-m-d'),
            'value' => $anotherExpense->value / 100.0,
        ]);
    }

    public function test_get_expense()
    {
        $user = User::factory()->create();

        $expense = $user->expenses()->create([
            'description' => $this->faker->text(191),
            'date' => $this->faker->date('Y-m-d', 'now'),
            'value' => $this->faker->randomNumber(6)
        ]);

        $anotherUser = User::factory()->create();

        $response = $this->actingAs($anotherUser)->getJson(route('expenses.show', $expense->id));

        $response->assertStatus(403);

        $response = $this->actingAs($user)->getJson(route('expenses.show', $expense->id));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'description' => $expense->description,
            'date' => $expense->date->format('Y-m-d'),
            'value' => $expense->value / 100.0,
        ]);
    }

    public function test_create_expense()
    {
        Notification::fake();

        $user = User::factory()->create();

        $expenseData = [
            'description' => $this->faker->text(191),
            'date' => $this->faker->date('Y-m-d', 'now'),
            'value' => $this->faker->randomNumber(6)
        ];

        $response = $this->actingAs($user)->postJson(route('expenses.store'), $expenseData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', [
            'description' => $expenseData['description'],
            'date' => Carbon::parse($expenseData['date']),
            'value' => $expenseData['value'],
            'user_id' => $user->id,
        ]);
        Notification::assertSentTo($user, Notifications\ExpenseCreated::class);

        // Test input validation
        $badExpenseData = [
            'description' => $this->faker->text(192), // Description greater than 191 characters
            'date' => $this->faker->dateTimeBetween('now', '+2 years')->format('Y-m-d'), // Date on future
            'value' => $this->faker->randomFloat('2', -9999, 0), // Negative value
        ];

        $response = $this->actingAs($user)->postJson(route('expenses.store'), $badExpenseData);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('expenses', $badExpenseData);
    }

    public function test_update_expense()
    {
        $user = User::factory()->create();


        $expense = $user->expenses()->create([
            'description' => $this->faker->text(191),
            'date' => $this->faker->date('Y-m-d', 'now'),
            'value' => $this->faker->randomNumber(6)
        ]);

        // Generate expense data
        $expenseData = [
            'description' => $this->faker->text(191),
            'date' => $this->faker->date('Y-m-d', 'now'),
            'value' => $this->faker->randomNumber(6)
        ];

        $anotherUser = User::factory()->create();

        $response = $this->actingAs($anotherUser)->putJson(route('expenses.update', $expense->id), $expenseData);

        $response->assertStatus(403);

        $response = $this->actingAs($user)->putJson(route('expenses.update', $expense->id), $expenseData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('expenses', [
            'description' => $expenseData['description'],
            'date' => Carbon::parse($expenseData['date']),
            'value' => $expenseData['value'],
            'user_id' => $user->id,
        ]);

        // Test input validation
        $badExpenseData = [
            'description' => $this->faker->text(192), // Description greater than 191 characters
            'date' => $this->faker->dateTimeBetween('now', '+2 years')->format('Y-m-d'), // Date on future
            'value' => $this->faker->randomFloat('2', -9999, 0), // Negative value
        ];

        $response = $this->actingAs($user)->putJson(route('expenses.update', $expense->id), $badExpenseData);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('expenses', $badExpenseData);
    }

    public function test_delete_expense()
    {
        // Create a user
        $user = User::factory()->create();

        // Create an expense
        $expense = $user->expenses()->create([
            'description' => $this->faker->text(191),
            'date' => $this->faker->date('Y-m-d', 'now'),
            'value' => $this->faker->randomFloat(2, 0, 1000)
        ]);

        $anotherUser = User::factory()->create();

        $response = $this->actingAs($anotherUser)->deleteJson(route('expenses.destroy', $expense->id));

        $response->assertStatus(403);

        $response = $this->actingAs($user)->deleteJson(route('expenses.destroy', $expense->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('expenses', $expense->toArray());
    }
}
