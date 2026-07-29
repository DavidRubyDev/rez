<?php

declare(strict_types=1);

namespace Rez\Tests\Domain\Exception;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rez\Domain\Exception\CannotDeleteLastAdminException;
use Rez\Domain\Exception\CannotDeleteSelfException;
use Rez\Domain\Exception\ConflictException;
use Rez\Domain\Exception\EmailAlreadyRegisteredException;
use Rez\Domain\Exception\EmailTemplateNotFoundException;
use Rez\Domain\Exception\ErrorCode;
use Rez\Domain\Exception\FeatureDisabledException;
use Rez\Domain\Exception\HasErrorCode;
use Rez\Domain\Exception\InsufficientFundsException;
use Rez\Domain\Exception\InvalidCredentialsException;
use Rez\Domain\Exception\InvalidPartyException;
use Rez\Domain\Exception\InvalidReservationStateException;
use Rez\Domain\Exception\InvalidSessionStateException;
use Rez\Domain\Exception\InvalidTimeSlotException;
use Rez\Domain\Exception\InvalidTokenException;
use Rez\Domain\Exception\NewsletterSubscriberNotFoundException;
use Rez\Domain\Exception\ReservationNotFoundException;
use Rez\Domain\Exception\ResourceNotFoundException;
use Rez\Domain\Exception\SessionNotFoundException;
use Rez\Domain\Exception\UserNotFoundException;
use Rez\Domain\Reservation\TimeSlot;
use Rez\Domain\Resource\Resource;
use Rez\Domain\Resource\ResourceId;
use Rez\Domain\Resource\ResourceType;
use Rez\Domain\Shared\Currency;
use Rez\Domain\Shared\Feature;

class ErrorCodeAssignmentTest extends TestCase
{
    public function testResourceNotFoundException(): void
    {
        $exception = new ResourceNotFoundException();
        $this->assertInstanceOf(HasErrorCode::class, $exception);
        $this->assertSame(ErrorCode::ResourceNotFound, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testReservationNotFoundException(): void
    {
        $exception = new ReservationNotFoundException();
        $this->assertSame(ErrorCode::ReservationNotFound, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testNewsletterSubscriberNotFoundException(): void
    {
        $exception = new NewsletterSubscriberNotFoundException('ada@example.com');
        $this->assertSame(ErrorCode::NewsletterSubscriberNotFound, $exception->errorCode());
        $this->assertSame(['email' => 'ada@example.com'], $exception->errorParams());
    }

    public function testEmailTemplateNotFoundException(): void
    {
        $exception = new EmailTemplateNotFoundException();
        $this->assertSame(ErrorCode::EmailTemplateNotFound, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testUserNotFoundException(): void
    {
        $exception = new UserNotFoundException('user-123');
        $this->assertSame(ErrorCode::UserNotFound, $exception->errorCode());
        $this->assertSame(['identifier' => 'user-123'], $exception->errorParams());
    }

    public function testSessionNotFoundException(): void
    {
        $exception = new SessionNotFoundException();
        $this->assertSame(ErrorCode::SessionNotFound, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testConflictException(): void
    {
        $slot = new TimeSlot(new DateTimeImmutable('2026-01-01 09:00'), new DateTimeImmutable('2026-01-01 10:00'));
        $resource = new Resource(ResourceId::generate(), ResourceType::fromString('table'), 'Table 1', 4);
        $exception = new ConflictException($slot, $resource);
        $this->assertSame(ErrorCode::ReservationConflict, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testEmailAlreadyRegisteredException(): void
    {
        $exception = new EmailAlreadyRegisteredException('ada@example.com');
        $this->assertSame(ErrorCode::EmailAlreadyRegistered, $exception->errorCode());
        $this->assertSame(['email' => 'ada@example.com'], $exception->errorParams());
    }

    public function testCannotDeleteSelfException(): void
    {
        $exception = new CannotDeleteSelfException();
        $this->assertSame(ErrorCode::CannotDeleteSelf, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testCannotDeleteLastAdminException(): void
    {
        $exception = new CannotDeleteLastAdminException();
        $this->assertSame(ErrorCode::CannotDeleteLastAdmin, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testFeatureDisabledException(): void
    {
        $exception = new FeatureDisabledException(Feature::Payments);
        $this->assertSame(ErrorCode::FeatureDisabled, $exception->errorCode());
        $this->assertSame(['feature' => 'Payments'], $exception->errorParams());
    }

    public function testInvalidTokenException(): void
    {
        $exception = new InvalidTokenException();
        $this->assertSame(ErrorCode::InvalidToken, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testInvalidCredentialsException(): void
    {
        $exception = new InvalidCredentialsException();
        $this->assertSame(ErrorCode::InvalidCredentials, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testInsufficientFundsException(): void
    {
        $exception = new InsufficientFundsException(100, 50, Currency::Usd);
        $this->assertSame(ErrorCode::InsufficientFunds, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testInvalidPartyException(): void
    {
        $exception = new InvalidPartyException('Party name must not be empty.');
        $this->assertSame(ErrorCode::InvalidParty, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testInvalidReservationStateException(): void
    {
        $exception = new InvalidReservationStateException('Reservation is already cancelled.');
        $this->assertSame(ErrorCode::InvalidReservationState, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testInvalidSessionStateException(): void
    {
        $exception = new InvalidSessionStateException('Session is already cancelled.');
        $this->assertSame(ErrorCode::InvalidSessionState, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }

    public function testInvalidTimeSlotException(): void
    {
        $exception = new InvalidTimeSlotException('Slot end must be after start.');
        $this->assertSame(ErrorCode::InvalidTimeSlot, $exception->errorCode());
        $this->assertSame([], $exception->errorParams());
    }
}
