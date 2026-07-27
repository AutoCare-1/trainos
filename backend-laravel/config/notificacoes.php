<?php

return [
    // Hora do dia (0-23, fuso do servidor) a partir da qual "sem treinar hoje" pode disparar.
    'hora_sem_treinar_hoje' => (int) env('NOTIF_HORA_SEM_TREINAR_HOJE', 18),

    // Limiares de dias sem treinar (tom crescente) — sem_treinar_dias.
    'dias_sem_treinar' => [3, 7, 14],

    // Marcos de tempo desde o cadastro (meses) — marco_tempo_treinando.
    'meses_marco_tempo_treinando' => [1, 3, 6, 12],

    // Mínimo de treinos concluídos na semana (segunda-domingo) pra disparar parabens_fim_semana.
    'treinos_minimos_parabens_fim_semana' => (int) env('NOTIF_TREINOS_MIN_PARABENS', 3),

    // Horas sem abrir o chat até considerar "mensagem não lida" / "sem resposta".
    'horas_mensagem_nao_lida' => (int) env('NOTIF_HORAS_MENSAGEM_NAO_LIDA', 4),
    'horas_mensagem_sem_resposta' => (int) env('NOTIF_HORAS_MENSAGEM_SEM_RESPOSTA', 12),

    // Dias sem atualizar medidas corporais até avaliacao_pendente.
    'dias_avaliacao_pendente' => (int) env('NOTIF_DIAS_AVALIACAO_PENDENTE', 30),

    // Depois de cruzar o limiar acima, de quantos em quantos dias o lembrete repete
    // (enquanto continuar pendente) — nunca todo dia.
    'dias_lembrete_avaliacao_pendente' => (int) env('NOTIF_DIAS_LEMBRETE_AVALIACAO_PENDENTE', 15),

    // Janela de antecedência (horas) pra desafio_terminando.
    'horas_desafio_terminando' => (int) env('NOTIF_HORAS_DESAFIO_TERMINANDO', 48),
];
