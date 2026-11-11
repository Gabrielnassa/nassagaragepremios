<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;

class PainelUsuario extends Command
{
    protected $signature = 'painel:usuario {email} {--nome=} {--papel=dono} {--senha=} {--zerar-2fa} {--desativar}';

    protected $description = 'Cria ou atualiza um usuário do painel';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('E-mail inválido.');

            return self::FAILURE;
        }

        if (! isset(AdminUser::PAPEIS[$this->option('papel')])) {
            $this->error('Papel inválido. Use dono, operador ou leitura.');

            return self::FAILURE;
        }

        $user = AdminUser::firstOrNew(['email' => $email]);
        $new = ! $user->exists;

        if ($this->option('desativar')) {
            if ($new) {
                $this->error('Usuário não encontrado.');

                return self::FAILURE;
            }
            $user->update(['active' => false]);
            $this->info('Usuário desativado.');

            return self::SUCCESS;
        }

        $password = $this->option('senha');
        if ($new || $password !== null) {
            $password ??= $this->secret('Senha (mínimo de 10 caracteres)');
            if (strlen((string) $password) < 10) {
                $this->error('A senha precisa ter pelo menos 10 caracteres.');

                return self::FAILURE;
            }
            $user->password = $password;
        }

        $user->name = $this->option('nome') ?? $user->name ?? strstr($email, '@', true);
        $user->role = $this->option('papel');
        $user->active = true;

        if ($this->option('zerar-2fa')) {
            $user->totp_secret = null;
            $user->totp_confirmed_at = null;
        }

        $user->save();
        $this->info($new ? 'Usuário criado. O segundo fator é configurado no primeiro acesso.' : 'Usuário atualizado.');

        return self::SUCCESS;
    }
}
