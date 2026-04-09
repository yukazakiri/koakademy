<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\Faculty;
use App\Models\ShsStudent;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class AccountService
{
    /**
     * Create a new account with optional person linking
     */
    public function createAccount(array $accountData, ?Model $model = null): Account
    {
        return DB::transaction(function () use ($accountData, $model) {
            // Validate email uniqueness
            if (Account::query()->where('email', $accountData['email'])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'An account with this email already exists.',
                ]);
            }

            // Validate username uniqueness
            if (Account::query()->where('username', $accountData['username'])->exists()) {
                throw ValidationException::withMessages([
                    'username' => 'An account with this username already exists.',
                ]);
            }

            // Hash password if provided
            if (isset($accountData['password'])) {
                $accountData['password'] = Hash::make($accountData['password']);
            }

            // Set default values
            $accountData['is_active'] ??= true;
            $accountData['is_notification_active'] ??= true;

            // Link to person if provided
            if ($model instanceof Model) {
                // For Faculty, we don't use person_id due to UUID vs bigint issue
                if ($model instanceof Faculty) {
                    $accountData['person_type'] = $model::class;
                    $accountData['person_id'] = null; // Don't set person_id for Faculty
                } else {
                    $accountData['person_id'] = $this->getPersonId($model);
                    $accountData['person_type'] = $model::class;
                }

                $accountData['role'] = $this->determineRoleFromPerson($model);

                // Use person's email if not provided
                if (! isset($accountData['email']) && $model->email) {
                    $accountData['email'] = $model->email;
                }

                // Use person's name if not provided
                if (! isset($accountData['name'])) {
                    $accountData['name'] = $this->getPersonName($model);
                }
            }

            $account = Account::query()->create($accountData);

            Log::info('Account created successfully', [
                'account_id' => $account->id,
                'email' => $account->email,
                'linked_person' => $model instanceof Model ? $model::class : null,
            ]);

            return $account;
        });
    }

    /**
     * Link an account to a person (Student, Faculty, or ShsStudent)
     */
    public function linkAccountToPerson(Account $account, Model $model): Account
    {
        return DB::transaction(function () use ($account, $model) {
            // Validate that the person can be linked
            $this->validatePersonLinking($model);

            // Check if person is already linked to another account
            $existingAccount = $this->findAccountByPerson($model);
            if ($existingAccount && $existingAccount->id !== $account->id) {
                throw ValidationException::withMessages([
                    'person' => 'This person is already linked to another account.',
                ]);
            }

            // Update account with person information
            $updateData = [
                'person_type' => $model::class,
                'role' => $this->determineRoleFromPerson($model),
                'email' => $account->email ?: $model->email,
                'name' => $account->name ?: $this->getPersonName($model),
            ];

            // For Faculty, we don't use person_id due to UUID vs bigint issue
            $updateData['person_id'] = $model instanceof Faculty ? null : $this->getPersonId($model);

            $account->update($updateData);

            Log::info('Account linked to person', [
                'account_id' => $account->id,
                'person_type' => $model::class,
                'person_id' => $this->getPersonId($model),
            ]);

            return $account->fresh();
        });
    }

    /**
     * Unlink an account from its associated person
     */
    public function unlinkAccountFromPerson(Account $account): Account
    {
        return DB::transaction(function () use ($account) {
            if (! $account->person_id || ! $account->person_type) {
                throw ValidationException::withMessages([
                    'account' => 'Account is not linked to any person.',
                ]);
            }

            $oldPersonType = $account->person_type;
            $oldPersonId = $account->person_id;

            $account->update([
                'person_id' => null,
                'person_type' => null,
                'role' => 'guest', // Set to guest role when unlinked
            ]);

            Log::info('Account unlinked from person', [
                'account_id' => $account->id,
                'old_person_type' => $oldPersonType,
                'old_person_id' => $oldPersonId,
            ]);

            return $account->fresh();
        });
    }

    /**
     * Find accounts that are not linked to any person
     */
    public function getUnlinkedAccounts()
    {
        return Account::query()->whereNull('person_id')
            ->orWhereNull('person_type')
            ->get();
    }

    /**
     * Find students without accounts
     */
    public function getStudentsWithoutAccounts()
    {
        return Student::query()->whereDoesntHave('account')->get();
    }

    /**
     * Find faculties without accounts
     */
    public function getFacultiesWithoutAccounts()
    {
        // Since Faculty uses email matching, we need to check differently
        return Faculty::query()->whereNotIn('email', function ($query): void {
            $query->select('email')
                ->from('accounts')
                ->where('person_type', Faculty::class)
                ->whereNotNull('email');
        })->get();
    }

    /**
     * Find SHS students without accounts
     */
    public function getShsStudentsWithoutAccounts()
    {
        return ShsStudent::query()->whereDoesntHave('account')->get();
    }

    /**
     * Activate an account
     */
    public function activateAccount(Account $account): Account
    {
        $account->update(['is_active' => true]);

        Log::info('Account activated', ['account_id' => $account->id]);

        return $account->fresh();
    }

    /**
     * Deactivate an account
     */
    public function deactivateAccount(Account $account): Account
    {
        $account->update(['is_active' => false]);

        Log::info('Account deactivated', ['account_id' => $account->id]);

        return $account->fresh();
    }

    /**
     * Reset account password
     */
    public function resetPassword(Account $account, string $newPassword): Account
    {
        $account->update([
            'password' => Hash::make($newPassword),
        ]);

        Log::info('Account password reset', ['account_id' => $account->id]);

        return $account->fresh();
    }

    /**
     * Get person ID based on person type
     */
    private function getPersonId(Model $model): mixed
    {
        if ($model instanceof Student) {
            return $model->id;
        }

        if ($model instanceof Faculty) {
            return $model->id; // Faculty uses UUID
        }

        if ($model instanceof ShsStudent) {
            return $model->student_lrn; // SHS uses LRN
        }

        throw new InvalidArgumentException('Unsupported person type: '.$model::class);
    }

    /**
     * Get person name based on person type
     */
    private function getPersonName(Model $model): string
    {
        if ($model instanceof Student) {
            return mb_trim(sprintf('%s %s %s', $model->first_name, $model->middle_name, $model->last_name));
        }

        if ($model instanceof Faculty) {
            return $model->getFullNameAttribute();
        }

        if ($model instanceof ShsStudent) {
            return $model->fullname ?? 'Unknown';
        }

        return 'Unknown';
    }

    /**
     * Determine role from person type
     */
    private function determineRoleFromPerson(Model $model): string
    {
        if ($model instanceof Student || $model instanceof ShsStudent) {
            return 'student';
        }

        if ($model instanceof Faculty) {
            return 'faculty';
        }

        return 'guest';
    }

    /**
     * Validate that a person can be linked to an account
     */
    private function validatePersonLinking(Model $model): void
    {
        if (! ($model instanceof Student) &&
            ! ($model instanceof Faculty) &&
            ! ($model instanceof ShsStudent)) {
            throw new InvalidArgumentException('Person must be a Student, Faculty, or ShsStudent');
        }
    }

    /**
     * Find account by person
     */
    private function findAccountByPerson(Model $model): ?Account
    {
        $personType = $model::class;

        if ($model instanceof Faculty) {
            // For Faculty, match by email and person_type
            return Account::query()->where('email', $model->email)
                ->where('person_type', $personType)
                ->first();
        }

        // For other person types, use person_id
        $personId = $this->getPersonId($model);

        return Account::query()->where('person_id', $personId)
            ->where('person_type', $personType)
            ->first();
    }
}
