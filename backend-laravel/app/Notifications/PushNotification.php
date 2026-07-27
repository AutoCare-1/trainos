<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Genérica de propósito — o conteúdo vem inteiro da Rule que originou o disparo
 * (App\Notifications\Rules\NotificacaoCandidato). implements ShouldQueue: o envio
 * roda num job da fila (QUEUE_CONNECTION=database), nunca síncrono dentro do
 * comando notifications:process.
 */
class PushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $titulo,
        public readonly string $corpo,
        public readonly ?string $url,
    ) {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $mensagem = (new WebPushMessage)
            ->title($this->titulo)
            ->body($this->corpo)
            ->icon('/icon-192.png')
            ->badge('/icon-192.png');

        if ($this->url !== null) {
            $mensagem->data(['url' => $this->url]);
        }

        return $mensagem;
    }
}
