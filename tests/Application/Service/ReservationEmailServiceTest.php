<?php

declare(strict_types=1);

namespace Rez\Tests\Application\Service;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rez\Application\Port\MailerInterface;
use Rez\Application\Service\ReservationEmailService;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\ReservationSettings;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Shared\CancellationToken;

class ReservationEmailServiceTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private ReservationEmailService $service;
    private Reservation $reservation;
    private CancellationToken $token;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new ReservationEmailService($this->mailer, $this->logger);

        $id             = ReservationId::generate();
        $this->reservation = Reservation::create(
            $id,
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(
                new DateTimeImmutable('2024-01-15 10:00:00'),
                new DateTimeImmutable('2024-01-15 11:00:00'),
            ),
            new Party('John Doe', 'john@example.com', 2, null),
        );
        $this->token = CancellationToken::generate($id, 'secret');
    }

    private function settings(
        bool $autoSendCreated = true,
        bool $autoSendConfirmed = true,
        bool $autoSendCancelled = true,
    ): ReservationSettings {
        return new ReservationSettings(false, $autoSendCreated, $autoSendConfirmed, $autoSendCancelled);
    }

    public function testSendCreatedIfEnabledSkipsWhenDisabled(): void
    {
        $this->mailer->expects($this->never())->method('sendReservationCreatedEmail');

        $this->service->sendCreatedIfEnabled($this->reservation, $this->token, $this->settings(autoSendCreated: false));
    }

    public function testSendCreatedIfEnabledCallsMailerWhenEnabled(): void
    {
        $this->mailer->expects($this->once())
            ->method('sendReservationCreatedEmail')
            ->with($this->reservation, $this->token);

        $this->service->sendCreatedIfEnabled($this->reservation, $this->token, $this->settings(autoSendCreated: true));
    }

    public function testSendCreatedIfEnabledLogsAndSwallowsMailerException(): void
    {
        $this->mailer->method('sendReservationCreatedEmail')->willThrowException(new \RuntimeException('SMTP error'));
        $this->logger->expects($this->once())->method('error');

        $this->service->sendCreatedIfEnabled($this->reservation, $this->token, $this->settings(autoSendCreated: true));
    }

    public function testSendConfirmedIfEnabledSkipsWhenDisabled(): void
    {
        $this->mailer->expects($this->never())->method('sendReservationConfirmedEmail');

        $this->service->sendConfirmedIfEnabled($this->reservation, $this->token, $this->settings(autoSendConfirmed: false));
    }

    public function testSendConfirmedIfEnabledCallsMailerWhenEnabled(): void
    {
        $this->mailer->expects($this->once())
            ->method('sendReservationConfirmedEmail')
            ->with($this->reservation, $this->token);

        $this->service->sendConfirmedIfEnabled($this->reservation, $this->token, $this->settings(autoSendConfirmed: true));
    }

    public function testSendConfirmedIfEnabledLogsAndSwallowsMailerException(): void
    {
        $this->mailer->method('sendReservationConfirmedEmail')->willThrowException(new \RuntimeException('SMTP error'));
        $this->logger->expects($this->once())->method('error');

        $this->service->sendConfirmedIfEnabled($this->reservation, $this->token, $this->settings(autoSendConfirmed: true));
    }

    public function testSendCancelledIfEnabledSkipsWhenDisabled(): void
    {
        $this->mailer->expects($this->never())->method('sendReservationCancelledEmail');

        $this->service->sendCancelledIfEnabled($this->reservation, $this->settings(autoSendCancelled: false));
    }

    public function testSendCancelledIfEnabledCallsMailerWhenEnabled(): void
    {
        $this->mailer->expects($this->once())
            ->method('sendReservationCancelledEmail')
            ->with($this->reservation);

        $this->service->sendCancelledIfEnabled($this->reservation, $this->settings(autoSendCancelled: true));
    }

    public function testSendCancelledIfEnabledLogsAndSwallowsMailerException(): void
    {
        $this->mailer->method('sendReservationCancelledEmail')->willThrowException(new \RuntimeException('SMTP error'));
        $this->logger->expects($this->once())->method('error');

        $this->service->sendCancelledIfEnabled($this->reservation, $this->settings(autoSendCancelled: true));
    }
}
