<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * VideoProcessor::obterDuracaoVideo (ffprobe) rodava FORA do try/catch em
 * PortalFormaController::store — um vídeo corrompido/inválido derrubava a
 * rota com 500 cru, e o arquivo já movido pra public/uploads/form-videos
 * ficava órfão no disco (o @unlink só rodava no branch de duração fora do
 * intervalo 3-15s, não no de exceção).
 */
class PortalFormaVideoCorrompidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_corrompido_devolve_erro_amigavel_e_limpa_o_arquivo(): void
    {
        Process::fake([
            'ffprobe*' => Process::result(errorOutput: 'moov atom not found', exitCode: 1),
        ]);

        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);
        $exercise = Exercise::firstOrCreate(['name' => 'Agachamento'], ['muscle_group' => 'pernas']);

        // TestingFile::create() gera bytes aleatórios (sem magic bytes reais),
        // então a validação mimetypes:video/* (que checa o conteúdo, não só a
        // extensão) rejeitaria antes de chegar no código que este teste quer
        // exercitar. Monta um MP4 minimamente válido pra passar da validação —
        // "corrompido" o suficiente pro ffprobe (aqui, fake) recusar, mas com
        // magic bytes reais de vídeo.
        $caminhoTmp = tempnam(sys_get_temp_dir(), 'video').'.mp4';
        file_put_contents($caminhoTmp, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 100));
        $video = new UploadedFile($caminhoTmp, 'video.mp4', 'video/mp4', null, true);

        $response = $this->post("/portal/{$student->invite_token}/forma", [
            'video' => $video,
            'exercise_id' => $exercise->id,
            'exercise_name' => 'Agachamento',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('form_correction_videos', 0);

        // O arquivo não deveria sobrar em disco depois da falha.
        $arquivosOrfaos = glob(public_path('uploads/form-videos/*'));
        $this->assertEmpty($arquivosOrfaos, 'arquivo de vídeo corrompido deveria ter sido apagado');
    }
}
