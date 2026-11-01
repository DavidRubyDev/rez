<?php

declare(strict_types=1);

namespace Rez\Tests\Application\UseCase\Reservation\MarkReservationPaid;

use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rez\Application\Exception\DatabaseException;
use Rez\Application\Port\ReservationRepositoryInterface;
use Rez\Application\UseCase\Reservation\MarkReservationPaid\MarkReservationPaidRequest;
use Rez\Application\UseCase\Reservation\MarkReservationPaid\MarkReservationPaidUseCase;
use Rez\Domain\Exception\DomainException;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Payment\PaymentMethod;
use Rez\Domain\Payment\PaymentStatus;
use Rez\Domain\Reservation\Party;
use Rez\Domain\Reservation\Reservation;
use Rez\Domain\Reservation\ReservationId;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceIdCollection;
use Rez\Domain\Shared\Currency;
use Rez\Domain\Shared\Money;

class MarkReservationPaidUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $reservationRepository;
    private MarkReservationPaidUseCase $useCase;
    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(ReservationRepositoryInterface::class);
        $this->useCase                = new MarkReservationPaidUseCase($this->reservationRepository);

        $this->reservation = Reservation::create(
            ReservationId::generate(),
            ResourceIdCollection::fromArray([ResourceId::generate()]),
            new TimeSlot(new DateTimeImmutable('2024-01-15 10:00:00'), new DateTimeImmutable('2024-01-15 11:00:00')),
            new Party('John Doe', 'john@example.com', 2, null),
        );
    }

    private function request(ReservationId $reservationId): MarkReservationPaidRequest
    {
        return new MarkReservationPaidRequest($reservationId, new Money(15000, Currency::Czk), PaymentMethod::CashAtCashier);
    }

    public function testRepositoryDatabaseExceptionPropagates(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new DatabaseException('pdo error'));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Failed to load reservation.');

        $this->useCase->execute($this->request(ReservationId::generate()));
    }

    public function testNotFoundThrowsReservationNotFoundException(): void
    {
        $this->reservationRepository
            ->method('findById')
            ->willThrowException(new ReservationNotFoundException());

        $this->expectException(ReservationNotFoundException::class);

        $this->useCase->execute($this->request(ReservationId::generate()));
    }

    public function testAlreadyPaidThrowsDomainException(): void
    {
        $paid = $this->reservation->markPaid(new Money(15000, Currency::Czk), PaymentMethod::CashAtCashier);
        $this->reservationRepository->method('findById')->willReturn($paid);

        $this->expectException(DomainException::class);

        $this->useCase->execute($this->request($paid->id));
    }

    public function testSuccessSaveCalledWithPaidReservation(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);

        $this->reservationRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (Reservation $r) => $r->paymentStatus === PaymentStatus::Paid && $r->paidAt !== null
            ));

        $this->useCase->execute($this->request($this->reservation->id));
    }

    public function testSuccessResponseHasPaidStatusAndRecordedAmountAndMethod(): void
    {
        $this->reservationRepository->method('findById')->willReturn($this->reservation);
        $amount = new Money(15000, Currency::Czk);

        $response = $this->useCase->execute(new MarkReservationPaidRequest($this->reservation->id, $amount, PaymentMethod::CashAtCashier));

        $this->assertSame(PaymentStatus::Paid, $response->reservation->paymentStatus);
        $this->assertSame(PaymentMethod::CashAtCashier, $response->reservation->paymentMethod);
        $this->assertNotNull($response->reservation->amountDue);
        $this->assertTrue($amount->equals($response->reservation->amountDue));
    }
}
