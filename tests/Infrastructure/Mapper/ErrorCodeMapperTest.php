<?php

declare(strict_types=1);

namespace Rez\Tests\Infrastructure\Mapper;

use PHPUnit\Framework\TestCase;
use Rez\Domain\Exception\ErrorCode;
use Rez\Infrastructure\Mapper\ErrorCodeMapper;

class ErrorCodeMapperTest extends TestCase
{
    private ErrorCodeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ErrorCodeMapper();
    }

    public function testResourceNotFoundMapsToString(): void
    {
        $this->assertSame('RESOURCE_NOT_FOUND', $this->mapper->toString(ErrorCode::ResourceNotFound));
    }

    public function testReservationNotFoundMapsToString(): void
    {
        $this->assertSame('RESERVATION_NOT_FOUND', $this->mapper->toString(ErrorCode::ReservationNotFound));
    }

    public function testNewsletterSubscriberNotFoundMapsToString(): void
    {
        $this->assertSame(
            'NEWSLETTER_SUBSCRIBER_NOT_FOUND',
            $this->mapper->toString(ErrorCode::NewsletterSubscriberNotFound)
        );
    }

    public function testEmailTemplateNotFoundMapsToString(): void
    {
        $this->assertSame('EMAIL_TEMPLATE_NOT_FOUND', $this->mapper->toString(ErrorCode::EmailTemplateNotFound));
    }

    public function testUserNotFoundMapsToString(): void
    {
        $this->assertSame('USER_NOT_FOUND', $this->mapper->toString(ErrorCode::UserNotFound));
    }

    public function testSessionNotFoundMapsToString(): void
    {
        $this->assertSame('SESSION_NOT_FOUND', $this->mapper->toString(ErrorCode::SessionNotFound));
    }

    public function testReservationConflictMapsToString(): void
    {
        $this->assertSame('RESERVATION_CONFLICT', $this->mapper->toString(ErrorCode::ReservationConflict));
    }

    public function testEmailAlreadyRegisteredMapsToString(): void
    {
        $this->assertSame('EMAIL_ALREADY_REGISTERED', $this->mapper->toString(ErrorCode::EmailAlreadyRegistered));
    }

    public function testCannotDeleteSelfMapsToString(): void
    {
        $this->assertSame('CANNOT_DELETE_SELF', $this->mapper->toString(ErrorCode::CannotDeleteSelf));
    }

    public function testCannotDeleteLastAdminMapsToString(): void
    {
        $this->assertSame('CANNOT_DELETE_LAST_ADMIN', $this->mapper->toString(ErrorCode::CannotDeleteLastAdmin));
    }

    public function testFeatureDisabledMapsToString(): void
    {
        $this->assertSame('FEATURE_DISABLED', $this->mapper->toString(ErrorCode::FeatureDisabled));
    }

    public function testInvalidTokenMapsToString(): void
    {
        $this->assertSame('INVALID_TOKEN', $this->mapper->toString(ErrorCode::InvalidToken));
    }

    public function testInvalidCredentialsMapsToString(): void
    {
        $this->assertSame('INVALID_CREDENTIALS', $this->mapper->toString(ErrorCode::InvalidCredentials));
    }

    public function testInsufficientFundsMapsToString(): void
    {
        $this->assertSame('INSUFFICIENT_FUNDS', $this->mapper->toString(ErrorCode::InsufficientFunds));
    }

    public function testInvalidPartyMapsToString(): void
    {
        $this->assertSame('INVALID_PARTY', $this->mapper->toString(ErrorCode::InvalidParty));
    }

    public function testInvalidReservationStateMapsToString(): void
    {
        $this->assertSame('INVALID_RESERVATION_STATE', $this->mapper->toString(ErrorCode::InvalidReservationState));
    }

    public function testInvalidSessionStateMapsToString(): void
    {
        $this->assertSame('INVALID_SESSION_STATE', $this->mapper->toString(ErrorCode::InvalidSessionState));
    }

    public function testInvalidTimeSlotMapsToString(): void
    {
        $this->assertSame('INVALID_TIME_SLOT', $this->mapper->toString(ErrorCode::InvalidTimeSlot));
    }

    public function testValidationErrorMapsToString(): void
    {
        $this->assertSame('VALIDATION_ERROR', $this->mapper->toString(ErrorCode::ValidationError));
    }

    public function testForbiddenMapsToString(): void
    {
        $this->assertSame('FORBIDDEN', $this->mapper->toString(ErrorCode::Forbidden));
    }

    public function testDatabaseErrorMapsToString(): void
    {
        $this->assertSame('DATABASE_ERROR', $this->mapper->toString(ErrorCode::DatabaseError));
    }

    public function testRouteNotFoundMapsToString(): void
    {
        $this->assertSame('ROUTE_NOT_FOUND', $this->mapper->toString(ErrorCode::RouteNotFound));
    }

    public function testMethodNotAllowedMapsToString(): void
    {
        $this->assertSame('METHOD_NOT_ALLOWED', $this->mapper->toString(ErrorCode::MethodNotAllowed));
    }

    public function testInternalErrorMapsToString(): void
    {
        $this->assertSame('INTERNAL_ERROR', $this->mapper->toString(ErrorCode::InternalError));
    }
}
