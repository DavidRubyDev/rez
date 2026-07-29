<?php

declare(strict_types=1);

namespace Rez\Infrastructure\Mapper;

use Rez\Domain\Exception\ErrorCode;

final class ErrorCodeMapper
{
    public function toString(ErrorCode $code): string
    {
        return match ($code) {
            ErrorCode::ResourceNotFound             => 'RESOURCE_NOT_FOUND',
            ErrorCode::ReservationNotFound           => 'RESERVATION_NOT_FOUND',
            ErrorCode::NewsletterSubscriberNotFound  => 'NEWSLETTER_SUBSCRIBER_NOT_FOUND',
            ErrorCode::EmailTemplateNotFound         => 'EMAIL_TEMPLATE_NOT_FOUND',
            ErrorCode::UserNotFound                  => 'USER_NOT_FOUND',
            ErrorCode::SessionNotFound               => 'SESSION_NOT_FOUND',
            ErrorCode::ReservationConflict           => 'RESERVATION_CONFLICT',
            ErrorCode::EmailAlreadyRegistered        => 'EMAIL_ALREADY_REGISTERED',
            ErrorCode::CannotDeleteSelf              => 'CANNOT_DELETE_SELF',
            ErrorCode::CannotDeleteLastAdmin         => 'CANNOT_DELETE_LAST_ADMIN',
            ErrorCode::FeatureDisabled               => 'FEATURE_DISABLED',
            ErrorCode::InvalidToken                  => 'INVALID_TOKEN',
            ErrorCode::InvalidCredentials             => 'INVALID_CREDENTIALS',
            ErrorCode::InsufficientFunds             => 'INSUFFICIENT_FUNDS',
            ErrorCode::InvalidParty                  => 'INVALID_PARTY',
            ErrorCode::InvalidReservationState       => 'INVALID_RESERVATION_STATE',
            ErrorCode::InvalidSessionState           => 'INVALID_SESSION_STATE',
            ErrorCode::InvalidTimeSlot               => 'INVALID_TIME_SLOT',
            ErrorCode::ValidationError               => 'VALIDATION_ERROR',
            ErrorCode::Forbidden                     => 'FORBIDDEN',
            ErrorCode::DatabaseError                 => 'DATABASE_ERROR',
            ErrorCode::RouteNotFound                 => 'ROUTE_NOT_FOUND',
            ErrorCode::MethodNotAllowed              => 'METHOD_NOT_ALLOWED',
            ErrorCode::InternalError                 => 'INTERNAL_ERROR',
        };
    }
}
