<?php

use App\Enums\BillingCycle;
use App\Enums\PlanStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function grantActiveSubscription(
    Company $company,
    bool $hasAiFeatures = true,
    int $maxProjects = 10,
    int $maxMembers = 10,
): Subscription {
    $plan = Plan::query()->create([
        'name' => fake()->unique()->words(2, true),
        'description' => 'Test subscription plan.',
        'price_monthly' => 10,
        'price_yearly' => 100,
        'max_projects' => $maxProjects,
        'max_members' => $maxMembers,
        'max_storage_mb' => 1024,
        'has_ai_features' => $hasAiFeatures,
        'status' => PlanStatus::ACTIVE,
    ]);

    return Subscription::query()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'billing_cycle' => BillingCycle::MONTHLY,
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);
}

function something()
{
    // ..
}
