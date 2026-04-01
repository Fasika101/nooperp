<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class EditProfilePage extends EditProfile
{
    use HasPageShield;

    protected Width|string|null $maxWidth = Width::TwoExtraLarge;

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel(false)
            ->model($this->getUser())
            ->operation('edit')
            ->statePath('data');
    }

    protected function getAvatarFormComponent(): Component
    {
        return FileUpload::make('avatar_url')
            ->label(__('Profile photo'))
            ->helperText(__('You can save after changing only this — no need to fill password or other fields.'))
            ->avatar()
            ->disk('public')
            ->directory('avatars')
            ->visibility('public')
            ->maxSize(2048)
            ->nullable()
            ->imageEditor();
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/edit-profile.form.password.label'))
            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.password.validation_attribute'))
            ->helperText(__('Leave blank to keep your current password. To change it, fill new password, confirmation, and below.'))
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->rule(Password::default())
            ->showAllValidationMessages()
            ->autocomplete('new-password')
            ->dehydrated(fn ($state): bool => filled($state))
            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return TextInput::make('passwordConfirmation')
            ->label(__('filament-panels::auth/pages/edit-profile.form.password_confirmation.label'))
            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.password_confirmation.validation_attribute'))
            ->helperText(__('Only required when setting a new password above.'))
            ->password()
            ->autocomplete('new-password')
            ->revealable(filament()->arePasswordsRevealable())
            ->required(fn (Get $get): bool => filled($get('password')))
            ->visible(fn (Get $get): bool => filled($get('password')))
            ->dehydrated(false);
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        $mustVerifyIdentity = fn (Get $get): bool => filled($get('password'))
            || ($get('email') !== $this->getUser()->getAttributeValue('email'));

        return TextInput::make('currentPassword')
            ->label(__('filament-panels::auth/pages/edit-profile.form.current_password.label'))
            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.current_password.validation_attribute'))
            ->belowContent(__('filament-panels::auth/pages/edit-profile.form.current_password.below_content'))
            ->helperText(__('Required when you change password or email. Not needed when you only update your photo or name.'))
            ->password()
            ->autocomplete('current-password')
            ->currentPassword(guard: Filament::getAuthGuard())
            ->revealable(filament()->arePasswordsRevealable())
            ->required($mustVerifyIdentity)
            ->visible($mustVerifyIdentity)
            ->dehydrated(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Profile photo'))
                    ->description(__('Change your picture only. Click Save — other sections are optional.'))
                    ->schema([
                        $this->getAvatarFormComponent(),
                    ])
                    ->columns(1)
                    ->compact(),

                Section::make(__('Account details'))
                    ->description(__('Name and email. Save when you change these; current password is only required if you change your email.'))
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                    ])
                    ->columns(1)
                    ->compact(),

                Section::make(__('Change password'))
                    ->description(__('Optional. Leave the password fields empty to keep your current password. When setting a new password, enter confirmation and your current password.'))
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ])
                    ->columns(1)
                    ->compact(),
            ]);
    }

    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::Center;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = $this->getUser();
        $oldPath = $user->getAttributeValue('avatar_url');
        $newPath = $data['avatar_url'] ?? null;

        if (filled($oldPath) && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return parent::mutateFormDataBeforeSave($data);
    }
}
