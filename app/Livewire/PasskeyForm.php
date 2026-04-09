<?php

declare(strict_types=1);

namespace App\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View as ViewContract;
use Joaopaulolndev\FilamentEditProfile\Concerns\HasSort;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Support\Config;
use Throwable;

final class PasskeyForm extends Component implements HasActions, HasForms
{
    use HasSort;
    use InteractsWithActions;
    use InteractsWithForms;

    #[Validate('required|string|max:255')]
    public string $name = '';

    public $passkeys;

    protected static int $sort = 0;

    public function mount(): void
    {
        $this->passkeys = $this->currentUser()->passkeys;
    }

    public function currentUser(): Authenticatable&HasPasskeys
    {
        /** @var Authenticatable&HasPasskeys $user */
        $user = Auth::user();

        return $user;
    }

    public function validatePasskeyProperties(): void
    {
        $this->validate();

        $this->dispatch('passkeyPropertiesValidated', [
            'passkeyOptions' => json_decode($this->generatePasskeyOptions()),
        ]);
    }

    public function storePasskey(string $passkey): void
    {
        $storePasskeyAction = Config::getAction('store_passkey', StorePasskeyAction::class);

        try {
            $storePasskeyAction->execute(
                $this->currentUser(),
                $passkey,
                $this->previouslyGeneratedPasskeyOptions(),
                request()->getHost(),
                ['name' => $this->name]
            );

            Notification::make()
                ->title(__('filament-passkeys::passkeys.created_notification_title'))
                ->success()
                ->send();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'name' => __('passkeys::passkeys.error_something_went_wrong_generating_the_passkey'),
            ])->errorBag('passkeyForm');
        }

        $this->clearForm();
        $this->passkeys = $this->currentUser()->passkeys()->get();
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('passkeys::passkeys.delete'))
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn (array $arguments) => $this->deletePasskey($arguments['passkey']));
    }

    public function deletePasskey(int $passkeyId): void
    {
        $this->currentUser()->passkeys()->where('id', $passkeyId)->delete();

        Notification::make()
            ->title(__('filament-passkeys::passkeys.deleted_notification_title'))
            ->success()
            ->send();

        $this->passkeys = $this->currentUser()->passkeys()->get();
    }

    public function render(): ViewContract
    {
        return view('livewire.passkey-form');
    }

    private function clearForm(): void
    {
        $this->name = '';
    }

    private function generatePasskeyOptions(): string
    {
        $generatePassKeyOptionsAction = Config::getAction('generate_passkey_register_options', GeneratePasskeyRegisterOptionsAction::class);

        $options = $generatePassKeyOptionsAction->execute($this->currentUser());

        session()->put('passkey-registration-options', $options);

        return $options;
    }

    private function previouslyGeneratedPasskeyOptions(): ?string
    {
        return session()->pull('passkey-registration-options');
    }
}
