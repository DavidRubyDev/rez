<?php

declare(strict_types=1);

namespace Rez\Domain\Exception;

enum ErrorCode
{
    case ResourceNotFound;
    case ReservationNotFound;
    case NewsletterSubscriberNotFound;
    case EmailTemplateNotFound;
    case UserNotFound;
    case SessionNotFound;
    case ReservationConflict;
    case EmailAlreadyRegistered;
    case CannotDeleteSelf;
    case CannotDeleteLastAdmin;
    case FeatureDisabled;
    case InvalidToken;
    case InvalidCredentials;
    case InsufficientFunds;
    case InvalidParty;
    case InvalidReservationState;
    case InvalidSessionState;
    case InvalidTimeSlot;
    case ValidationError;
    case Forbidden;
    case DatabaseError;
    case RouteNotFound;
    case MethodNotAllowed;
    case InternalError;
}
