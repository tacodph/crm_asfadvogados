<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fuso horário da agenda
    |--------------------------------------------------------------------------
    |
    | A aplicação grava timestamps em UTC (app.timezone), mas a data e a hora
    | das tarefas são digitadas no horário local do escritório. "Hoje",
    | "atrasada" e o horário dos lembretes de follow-up usam este fuso.
    |
    */

    'timezone' => env('CRM_TIMEZONE', 'America/Sao_Paulo'),

    /*
    |--------------------------------------------------------------------------
    | Lembretes de follow-up
    |--------------------------------------------------------------------------
    |
    | Antecedência (minutos) do lembrete de uma tarefa com hora marcada, e a
    | hora em que tarefas do dia sem hora marcada são lembradas.
    |
    */

    'lembretes' => [
        'antecedencia_minutos' => (int) env('CRM_LEMBRETE_ANTECEDENCIA', 15),
        'hora_sem_horario' => env('CRM_LEMBRETE_HORA_SEM_HORARIO', '08:00'),
    ],

];
