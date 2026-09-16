<?php

namespace App\Console\Commands;

use App\Models\Professional;
use Illuminate\Console\Command;

/**
 * Dá (ou tira) o acesso ao CRM interno (/admin) de um personal, pelo e-mail.
 *
 * Existe por causa de um círculo fechado descoberto em produção em 15/09/2026:
 * a única rota que promove alguém (POST /admin/admins) está atrás do middleware
 * `admin`, e nenhum seeder ou comando setava `is_admin`. Com o banco de
 * produção nascendo sem nenhum admin, o CRM ficava inalcançável — e o banco só
 * existe na rede interna do Railway, o mesmo beco que travou a poda da
 * biblioteca em 08/09 (ver o comentário em .railway/railway.ts).
 *
 * Fora do círculo: este comando roda pelo shell do container, ou uma vez como
 * preDeployCommand. Não entra no entrypoint de propósito — promoção de admin
 * não é coisa que deva acontecer sozinha a cada deploy.
 */
class PromoverAdmin extends Command
{
    protected $signature = 'usuarios:promover-admin
        {email : E-mail do personal que vai receber (ou perder) o acesso ao /admin}
        {--revogar : Em vez de promover, tira o acesso}';

    protected $description = 'Concede ou revoga o acesso ao CRM interno (/admin) de um personal, pelo e-mail.';

    public function handle(): int
    {
        $email = trim($this->argument('email'));
        $revogar = (bool) $this->option('revogar');

        $personal = Professional::where('email', $email)->first();

        if (! $personal) {
            // Erro explícito em vez de "0 linhas afetadas": o modo de falha mais
            // provável aqui é e-mail com typo, e um update silencioso deixaria
            // quem rodou achando que promoveu.
            $this->error("Nenhum personal com o e-mail {$email}.");

            return self::FAILURE;
        }

        $alvo = ! $revogar;

        if ((bool) $personal->is_admin === $alvo) {
            $this->info($alvo
                ? "{$email} já era admin. Nada a fazer."
                : "{$email} já não era admin. Nada a fazer.");

            return self::SUCCESS;
        }

        $personal->is_admin = $alvo;
        $personal->save();

        $this->info($alvo
            ? "{$email} agora é admin — o /admin abre no próximo login."
            : "{$email} não é mais admin.");

        $total = Professional::where('is_admin', true)->count();

        // Avisa, mas não impede: pode ser revogação intencional de uma conta
        // antiga. O que não pode é descobrir que zerou só quando o CRM sumir.
        if ($total === 0) {
            $this->warn('ATENÇÃO: não sobrou nenhum admin. O /admin ficou inalcançável pelo app — só este comando reverte.');
        } else {
            $this->line("Admins agora: {$total}.");
        }

        return self::SUCCESS;
    }
}
