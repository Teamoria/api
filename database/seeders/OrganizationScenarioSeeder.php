<?php

namespace Database\Seeders;

use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizationScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $plans = $this->seedPlans();
        $companies = $this->seedCompanies();

        $this->seedUsers($companies);
        $this->seedBillingScenarios($plans, $companies);
    }

    /**
     * @return array{starter: Plan, business: Plan, enterprise: Plan}
     */
    private function seedPlans(): array
    {
        $planScenarios = [
            'starter' => [
                'name' => 'Starter',
                'description' => 'For small teams getting started.',
                'price_monthly' => 19,
                'price_yearly' => 190,
                'max_projects' => 5,
                'max_members' => 10,
                'max_storage_mb' => 5120,
                'has_ai_features' => false,
                'status' => 'active',
            ],
            'business' => [
                'name' => 'Business',
                'description' => 'For growing teams that need AI features.',
                'price_monthly' => 49,
                'price_yearly' => 490,
                'max_projects' => 25,
                'max_members' => 50,
                'max_storage_mb' => 51200,
                'has_ai_features' => true,
                'status' => 'active',
            ],
            'enterprise' => [
                'name' => 'Enterprise Legacy',
                'description' => 'An inactive legacy plan retained for billing history.',
                'price_monthly' => 199,
                'price_yearly' => 1990,
                'max_projects' => 500,
                'max_members' => 1000,
                'max_storage_mb' => 512000,
                'has_ai_features' => true,
                'status' => 'inactive',
            ],
        ];

        $plans = [];

        foreach ($planScenarios as $key => $scenario) {
            $plans[$key] = Plan::query()->updateOrCreate(
                ['name' => $scenario['name']],
                $scenario,
            );
        }

        /** @var array{starter: Plan, business: Plan, enterprise: Plan} $plans */
        return $plans;
    }

    /**
     * @return array{active: Company, suspended: Company, inactive: Company}
     */
    private function seedCompanies(): array
    {
        $companyScenarios = [
            'active' => [
                'name' => 'Teamoria Demo',
                'industry' => 'Technology',
                'website' => 'https://teamoria.test',
                'address' => 'Gaza, Palestine',
                'status' => CompanyStatus::ACTIVE,
            ],
            'suspended' => [
                'name' => 'Suspended Workspace',
                'industry' => 'Consulting',
                'website' => 'https://suspended.teamoria.test',
                'address' => 'Ramallah, Palestine',
                'status' => CompanyStatus::SUSPENDED,
            ],
            'inactive' => [
                'name' => 'Inactive Workspace',
                'industry' => 'Education',
                'website' => 'https://inactive.teamoria.test',
                'address' => 'Nablus, Palestine',
                'status' => CompanyStatus::INACTIVE,
            ],
        ];

        $companies = [];

        foreach ($companyScenarios as $key => $scenario) {
            $companies[$key] = Company::query()->updateOrCreate(
                ['name' => $scenario['name']],
                $scenario,
            );
        }

        /** @var array{active: Company, suspended: Company, inactive: Company} $companies */
        return $companies;
    }

    /**
     * @param  array{active: Company, suspended: Company, inactive: Company}  $companies
     */
    private function seedUsers(array $companies): void
    {
        $password = Hash::make('password');
        $userScenarios = [
            [
                'email' => 'ahmedalyazuri@gmail.com',
                'company_id' => null,
                'name' => 'System Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('1234568'),
                'role' => UserRole::ADMIN,
                'status' => UserStatus::ACTIVE,
                'timezone' => 'Asia/Jerusalem',
                'last_login_at' => now()->subMinutes(10),
            ],
            [
                'email' => 'owner@teamoria.test',
                'company_id' => $companies['active']->id,
                'name' => 'Demo Owner',
                'email_verified_at' => now(),
                'password' => $password,
                'role' => UserRole::COMPANY_OWNER,
                'status' => UserStatus::ACTIVE,
                'timezone' => 'Asia/Jerusalem',
                'last_login_at' => now()->subHour(),
            ],
            [
                'email' => 'manager@teamoria.test',
                'company_id' => $companies['active']->id,
                'name' => 'Demo Manager',
                'email_verified_at' => now(),
                'password' => $password,
                'role' => UserRole::COMPANY_MANAGER,
                'status' => UserStatus::ACTIVE,
                'timezone' => 'Asia/Jerusalem',
                'last_login_at' => now()->subHours(2),
            ],
            [
                'email' => 'member@teamoria.test',
                'company_id' => $companies['active']->id,
                'name' => 'Demo Member',
                'email_verified_at' => now(),
                'password' => $password,
                'role' => UserRole::COMPANY_MEMBER,
                'status' => UserStatus::ACTIVE,
                'timezone' => 'Asia/Jerusalem',
                'last_login_at' => now()->subDay(),
            ],
            [
                'email' => 'viewer@teamoria.test',
                'company_id' => $companies['active']->id,
                'name' => 'Project Viewer',
                'email_verified_at' => now(),
                'password' => $password,
                'role' => UserRole::COMPANY_MEMBER,
                'status' => UserStatus::ACTIVE,
                'timezone' => 'UTC',
            ],
            [
                'email' => 'pending@teamoria.test',
                'company_id' => $companies['active']->id,
                'name' => 'Pending Member',
                'email_verified_at' => null,
                'password' => $password,
                'role' => UserRole::COMPANY_MEMBER,
                'status' => UserStatus::PENDING,
                'timezone' => 'UTC',
            ],
            [
                'email' => 'suspended@teamoria.test',
                'company_id' => $companies['active']->id,
                'name' => 'Suspended Member',
                'email_verified_at' => now()->subMonth(),
                'password' => $password,
                'role' => UserRole::COMPANY_MEMBER,
                'status' => UserStatus::SUSPENDED,
                'timezone' => 'UTC',
            ],
            [
                'email' => 'inactive@teamoria.test',
                'company_id' => $companies['active']->id,
                'name' => 'Inactive Member',
                'email_verified_at' => now()->subMonths(2),
                'password' => $password,
                'role' => UserRole::COMPANY_MEMBER,
                'status' => UserStatus::INACTIVE,
                'timezone' => 'UTC',
            ],
            [
                'email' => 'owner@suspended.teamoria.test',
                'company_id' => $companies['suspended']->id,
                'name' => 'Suspended Workspace Owner',
                'email_verified_at' => now()->subYear(),
                'password' => $password,
                'role' => UserRole::COMPANY_OWNER,
                'status' => UserStatus::ACTIVE,
                'timezone' => 'UTC',
            ],
        ];

        foreach ($userScenarios as $scenario) {
            User::query()->updateOrCreate(
                ['email' => $scenario['email']],
                $scenario,
            );
        }
    }

    /**
     * @param  array{starter: Plan, business: Plan, enterprise: Plan}  $plans
     * @param  array{active: Company, suspended: Company, inactive: Company}  $companies
     */
    private function seedBillingScenarios(array $plans, array $companies): void
    {
        $activeSubscription = Subscription::query()->updateOrCreate(
            [
                'company_id' => $companies['active']->id,
                'plan_id' => $plans['business']->id,
            ],
            [
                'status' => 'active',
                'trial_ends_at' => now()->subMonths(2),
                'starts_at' => now()->subMonths(2),
                'ends_at' => now()->addMonths(10),
            ],
        );

        $pastDueSubscription = Subscription::query()->updateOrCreate(
            [
                'company_id' => $companies['suspended']->id,
                'plan_id' => $plans['starter']->id,
            ],
            [
                'status' => 'past_due',
                'trial_ends_at' => now()->subMonths(3),
                'starts_at' => now()->subMonths(3),
                'ends_at' => now()->subDays(5),
            ],
        );

        $cancelledSubscription = Subscription::query()->updateOrCreate(
            [
                'company_id' => $companies['inactive']->id,
                'plan_id' => $plans['enterprise']->id,
            ],
            [
                'status' => 'cancelled',
                'trial_ends_at' => null,
                'starts_at' => now()->subYear(),
                'ends_at' => now()->subMonth(),
            ],
        );

        $paymentScenarios = [
            [
                'reference_number' => 'DEMO-PAID-001',
                'subscription_id' => $activeSubscription->id,
                'company_id' => $companies['active']->id,
                'amount' => 49,
                'method' => 'credit_card',
                'status' => 'paid',
                'notes' => 'A successfully confirmed monthly payment.',
                'paid_at' => now()->subMonth(),
                'confirmed_at' => now()->subMonth()->addMinutes(2),
            ],
            [
                'reference_number' => 'DEMO-PENDING-001',
                'subscription_id' => $activeSubscription->id,
                'company_id' => $companies['active']->id,
                'amount' => 49,
                'method' => 'bank_transfer',
                'status' => 'pending',
                'notes' => 'A transfer awaiting confirmation.',
                'paid_at' => null,
                'confirmed_at' => null,
            ],
            [
                'reference_number' => 'DEMO-FAILED-001',
                'subscription_id' => $pastDueSubscription->id,
                'company_id' => $companies['suspended']->id,
                'amount' => 19,
                'method' => 'credit_card',
                'status' => 'failed',
                'notes' => 'The card was declined.',
                'paid_at' => null,
                'confirmed_at' => null,
            ],
            [
                'reference_number' => 'DEMO-REFUNDED-001',
                'subscription_id' => $cancelledSubscription->id,
                'company_id' => $companies['inactive']->id,
                'amount' => 199,
                'method' => 'bank_transfer',
                'status' => 'refunded',
                'notes' => 'The final payment was refunded after cancellation.',
                'paid_at' => now()->subMonths(2),
                'confirmed_at' => now()->subMonths(2)->addDay(),
            ],
        ];

        foreach ($paymentScenarios as $scenario) {
            Payment::query()->updateOrCreate(
                ['reference_number' => $scenario['reference_number']],
                $scenario,
            );
        }
    }
}
