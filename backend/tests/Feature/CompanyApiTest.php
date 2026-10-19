<?php

namespace Tests\Feature;

use App\Models\Company;
use Laravel\Sanctum\Sanctum;

class CompanyApiTest extends ApiTestCase
{
    public function test_employee_can_add_list_update_and_delete_a_company(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/companies', [
            'name' => 'Acme Ltd', 'industry' => 'Software', 'website' => 'https://acme.example.com', 'is_current' => true,
        ])->assertCreated()->assertJsonPath('name', 'Acme Ltd')->assertJsonPath('is_current', true)->json('id');

        $this->getJson('/api/v1/companies')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $id);

        $this->putJson("/api/v1/companies/{$id}", [
            'name' => 'Acme Corp', 'is_current' => false, 'started_on' => '2022-01-10', 'ended_on' => '2024-06-30',
        ])->assertOk()->assertJsonPath('name', 'Acme Corp')->assertJsonPath('is_current', false)
            ->assertJsonPath('started_on', '2022-01-10')->assertJsonPath('ended_on', '2024-06-30');

        $this->deleteJson("/api/v1/companies/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('companies', ['id' => $id]);
    }

    public function test_company_name_is_required_and_website_must_be_a_url(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/companies', ['website' => 'not-a-url'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'website']);
    }

    public function test_a_past_employer_needs_start_and_end_dates(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/companies', ['name' => 'Old Co', 'is_current' => false])
            ->assertUnprocessable()->assertJsonValidationErrors(['started_on', 'ended_on']);

        // Omitting is_current entirely means "not current" too (an unticked form checkbox).
        $this->postJson('/api/v1/companies', ['name' => 'Old Co'])
            ->assertUnprocessable()->assertJsonValidationErrors(['started_on', 'ended_on']);

        $this->postJson('/api/v1/companies', ['name' => 'Old Co', 'started_on' => '2023-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('ended_on')->assertJsonMissingValidationErrors('started_on');
    }

    public function test_end_date_cannot_be_before_start_date_or_in_the_future(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/companies', ['name' => 'Co', 'started_on' => '2023-05-01', 'ended_on' => '2023-04-30'])
            ->assertUnprocessable()->assertJsonValidationErrors('ended_on');

        $this->postJson('/api/v1/companies', ['name' => 'Co', 'started_on' => '2023-05-01', 'ended_on' => '2026-12-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('ended_on');

        $this->postJson('/api/v1/companies', ['name' => 'Co', 'started_on' => '2023-05-01', 'ended_on' => '2023-05-01'])
            ->assertCreated(); // same day is fine
    }

    public function test_a_current_employer_needs_no_dates_and_never_keeps_an_end_date(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/companies', ['name' => 'Now Co', 'is_current' => true])
            ->assertCreated()->assertJsonPath('ended_on', null)->assertJsonPath('started_on', null);

        // A stray end date sent with is_current=true is discarded, not stored.
        $this->postJson('/api/v1/companies', [
            'name' => 'Now Co 2', 'is_current' => true, 'started_on' => '2024-01-01', 'ended_on' => '2024-12-01',
        ])->assertCreated()->assertJsonPath('ended_on', null)->assertJsonPath('started_on', '2024-01-01');
    }

    public function test_switching_back_to_current_clears_the_end_date(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/v1/companies', ['name' => 'Co', 'started_on' => '2022-01-01', 'ended_on' => '2023-01-01'])->json('id');

        $this->putJson("/api/v1/companies/{$id}", ['name' => 'Co', 'is_current' => true, 'started_on' => '2022-01-01'])
            ->assertOk()->assertJsonPath('is_current', true)->assertJsonPath('ended_on', null);
    }

    public function test_employees_only_see_and_touch_their_own_companies(): void
    {
        $mine = $this->makeUser();
        $theirs = $this->makeUser();
        $company = Company::create(['employee_id' => $theirs->employee->id, 'name' => 'Not Mine']);

        Sanctum::actingAs($mine);

        $this->getJson('/api/v1/companies')->assertOk()->assertJsonCount(0);
        $this->putJson("/api/v1/companies/{$company->id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->deleteJson("/api/v1/companies/{$company->id}")->assertForbidden();
        $this->assertSame('Not Mine', $company->fresh()->name);
    }
}
