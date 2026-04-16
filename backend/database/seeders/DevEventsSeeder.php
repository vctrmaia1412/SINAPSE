<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DevEventsSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');

        $organizer1 = User::query()->create([
            'name' => 'Ana Organizadora',
            'email' => 'organizer1@devevents.test',
            'password' => $password,
            'role' => UserRole::Organizer,
        ]);

        $organizer2 = User::query()->create([
            'name' => 'Bruno Organizador',
            'email' => 'organizer2@devevents.test',
            'password' => $password,
            'role' => UserRole::Organizer,
        ]);

        $participantRows = [
            ['name' => 'Carla Mendes', 'email' => 'participant1@devevents.test'],
            ['name' => 'Daniel Costa', 'email' => 'participant2@devevents.test'],
            ['name' => 'Elena Ribeiro', 'email' => 'participant3@devevents.test'],
            ['name' => 'Felipe Araújo', 'email' => 'participant4@devevents.test'],
            ['name' => 'Gabriela Souza', 'email' => 'participant5@devevents.test'],
            ['name' => 'Henrique Lima', 'email' => 'participant6@devevents.test'],
            ['name' => 'Isabel Ferreira', 'email' => 'participant7@devevents.test'],
        ];

        $participants = collect($participantRows)->map(fn (array $row) => User::query()->create([
            'name' => $row['name'],
            'email' => $row['email'],
            'password' => $password,
            'role' => UserRole::Participant,
        ]));

        $p = static fn (int $i): User => $participants[$i - 1];

        $futureWorkshop = Event::query()->create([
            'organizer_id' => $organizer1->id,
            'title' => 'Workshop — Laravel 11, APIs e PostgreSQL',
            'description' => "Mão na massa: migrations, Eloquent, policies e transações.\n\nTrazer laptop com Docker ou ambiente PHP 8.2+. Coffee break incluso.",
            'starts_at' => now()->addDays(15)->setTime(14, 0),
            'ends_at' => now()->addDays(15)->setTime(18, 0),
            'capacity' => 40,
            'status' => EventStatus::Published,
            'metadata' => [
                'formato' => 'workshop',
                'nivel' => 'intermediário',
                'local' => 'Auditório remoto (link no e-mail de confirmação)',
            ],
        ]);

        $futureMeetup = Event::query()->create([
            'organizer_id' => $organizer2->id,
            'title' => 'Meetup DevEvents — Arquitetura de APIs REST',
            'description' => "Painel com cases reais: versionamento, erros consistentes, rate limit e autenticação.\n\nApós o painel: networking aberto (~30 min).",
            'starts_at' => now()->addDays(36)->setTime(19, 0),
            'ends_at' => now()->addDays(36)->setTime(21, 30),
            'capacity' => 80,
            'status' => EventStatus::Published,
            'metadata' => [
                'formato' => 'painel + Q&A',
                'local' => 'Hub coworking — centro',
            ],
        ]);

        $lightningEvent = Event::query()->create([
            'organizer_id' => $organizer1->id,
            'title' => 'Lightning talks — Ferramentas que uso todo dia',
            'description' => "Cinco apresentações de 10 minutos: CLI, debuggers, testes, CI e produtividade.\n\nIdeal para quem quer ideias rápidas sem compromisso de dia inteiro.",
            'starts_at' => now()->addDays(22)->setTime(18, 30),
            'ends_at' => now()->addDays(22)->setTime(20, 30),
            'capacity' => 50,
            'status' => EventStatus::Published,
            'metadata' => [
                'formato' => 'lightning talks',
                'vagas' => 'chegada 15 min antes',
            ],
        ]);

        $smallFutureEvent = Event::query()->create([
            'organizer_id' => $organizer1->id,
            'title' => 'Mesa redonda — Transição para liderança técnica',
            'description' => 'Grupo pequeno (3 vagas) para conversa franca sobre carreira, feedback e gestão de pares. Chatham House rules.',
            'starts_at' => now()->addDays(12)->setTime(10, 0),
            'ends_at' => now()->addDays(12)->setTime(12, 0),
            'capacity' => 3,
            'status' => EventStatus::Published,
            'metadata' => ['local' => 'Sala fechada — acesso por convite na inscrição'],
        ]);

        $cancelledFuture = Event::query()->create([
            'organizer_id' => $organizer1->id,
            'title' => 'Conferência (cancelada) — DevOps em produção',
            'description' => 'Programa de dia inteiro com palestrantes externos. Cancelada por indisponibilidade de agenda dos speakers.',
            'starts_at' => now()->addDays(70)->setTime(9, 0),
            'ends_at' => now()->addDays(70)->setTime(17, 0),
            'capacity' => 150,
            'status' => EventStatus::Cancelled,
            'metadata' => [
                'motivo_cancelamento' => 'Reposição de data em análise para o próximo trimestre',
            ],
        ]);

        $pastConference = Event::query()->create([
            'organizer_id' => $organizer1->id,
            'title' => 'Conferência — Clean Architecture na prática',
            'description' => 'Evento presencial já realizado. Gravações disponíveis apenas para participantes inscritos na época.',
            'starts_at' => now()->subMonths(4)->setTime(9, 0),
            'ends_at' => now()->subMonths(4)->setTime(18, 0),
            'capacity' => 200,
            'status' => EventStatus::Published,
            'metadata' => ['edicao' => 2025],
        ]);

        $pastMeetup = Event::query()->create([
            'organizer_id' => $organizer2->id,
            'title' => 'Meetup — Introdução ao React 19',
            'description' => 'Sessão introdutória com hooks, Server Components (visão geral) e exercícios guiados.',
            'starts_at' => now()->subMonths(2)->setTime(18, 0),
            'ends_at' => now()->subMonths(2)->setTime(20, 0),
            'capacity' => 60,
            'status' => EventStatus::Published,
            'metadata' => ['gravacao' => 'disponível internamente'],
        ]);

        $cancelledPast = Event::query()->create([
            'organizer_id' => $organizer2->id,
            'title' => 'Workshop (cancelado) — Kubernetes básico',
            'description' => 'Previsto para 2025; cancelado por baixa adesão na lista de espera.',
            'starts_at' => now()->subMonths(6)->setTime(14, 0),
            'ends_at' => now()->subMonths(6)->setTime(18, 0),
            'capacity' => 30,
            'status' => EventStatus::Cancelled,
            'metadata' => null,
        ]);

        $register = static function (
            Event $event,
            User $user,
            RegistrationStatus $status,
            ?Carbon $createdAt = null,
        ): void {
            EventRegistration::query()->create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'status' => $status,
                'created_at' => $createdAt ?? now(),
                'updated_at' => $createdAt ?? now(),
            ]);
        };

        // Workshop futuro: fila saudável + uma desistência
        foreach ([1 => 18, 2 => 12, 3 => 9] as $pi => $daysAgo) {
            $register($futureWorkshop, $p($pi), RegistrationStatus::Confirmed, now()->subDays($daysAgo));
        }
        $register($futureWorkshop, $p(4), RegistrationStatus::Cancelled, now()->subDays(4));

        // Meetup futuro (Bruno): boa ocupação
        foreach ([1 => 6, 2 => 5, 3 => 3, 5 => 1] as $pi => $daysAgo) {
            $register($futureMeetup, $p($pi), RegistrationStatus::Confirmed, now()->subDays($daysAgo));
        }

        // Lightning talks: público médio
        foreach ([2 => 4, 4 => 2, 6 => 1, 7 => 1] as $pi => $daysAgo) {
            $register($lightningEvent, $p($pi), RegistrationStatus::Confirmed, now()->subDays($daysAgo));
        }
        $register($lightningEvent, $p(3), RegistrationStatus::Cancelled, now()->subDays(2));

        // Mesa redonda: lotada (3/3)
        foreach ([1, 2, 3] as $pi) {
            $register($smallFutureEvent, $p($pi), RegistrationStatus::Confirmed, now()->subDays(8 - $pi));
        }

        // Conferência cancelada: inscrições feitas antes do cancelamento
        foreach ([1, 2, 5] as $pi) {
            $register($cancelledFuture, $p($pi), RegistrationStatus::Confirmed, now()->subWeeks(5));
        }
        $register($cancelledFuture, $p(4), RegistrationStatus::Cancelled, now()->subWeeks(2));

        // Conferência passada
        foreach ([1, 2, 3, 4, 5] as $pi) {
            $register($pastConference, $p($pi), RegistrationStatus::Confirmed, now()->subMonths(5));
        }

        // Meetup React passado: comparecimento + desistências
        foreach ([2, 3, 5] as $pi) {
            $register($pastMeetup, $p($pi), RegistrationStatus::Confirmed, now()->subMonths(2)->subDays(3));
        }
        $register($pastMeetup, $p(1), RegistrationStatus::Cancelled, now()->subMonths(2)->subDays(1));
        $register($pastMeetup, $p(6), RegistrationStatus::Cancelled, now()->subMonths(2)->subWeek());

        // Workshop K8s cancelado: poucas inscrições depois canceladas no fechamento
        $register($cancelledPast, $p(7), RegistrationStatus::Cancelled, now()->subMonths(7));
        $register($cancelledPast, $p(1), RegistrationStatus::Cancelled, now()->subMonths(7)->addDays(2));
    }
}
