<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('admin:create
    {--name= : Yöneticinin adı}
    {--email= : Giriş e-postası}
    {--password= : Şifre (verilmezse sorulur)}')]
#[Description('Yönetim paneline giriş yapabilecek bir yönetici oluşturur ya da mevcut kullanıcıyı yönetici yapar')]
class CreateAdminUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) ($this->option('email') ?? text('E-posta', required: true));
        $user = User::query()->where('email', $email)->first();

        $name = (string) ($this->option('name') ?? $user->name ?? text('Ad', required: true));
        $plainPassword = (string) ($this->option('password') ?? password('Şifre', required: true));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $plainPassword],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user ??= new User;

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => $plainPassword,
            'is_admin' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->components->info("Yönetici hazır: {$email}");

        return self::SUCCESS;
    }
}
