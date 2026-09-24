<?php

namespace App\Helpers;

class ApiCodes
{
    const SUCCESS = 200;

    const ACCEPTED = 202;

    const BAD_REQUEST = 400;

    const RESOURCE_NOT_FOUND = 404;

    const FORBIDDEN = 403;

    const RESOURCE_CAN_NOT_BE_MODIFIED = 5;

    const UNAUTHENTICATED = 401;

    const METHOD_NOT_ALLOWED = 405;

    const INTERNAL_SERVER_ERROR = 500;

    const SERVICE_UNAVAILABLE = 503;

    const TEMPORARY_REDIRECT = 307;

    const UNPROCESSABLE_ENTITY = 422;

    const TOKEN_EXPIRED = 419;

    public static function getSuccessMessage(): string
    {
        return __('messages.success.the_action_was_completed_successfully');
    }

    public static function getResourceNotFoundMessage(): string
    {
        return __('messages.common.no_data_found');
    }

    public static function getGeneralErrorMessage(): string
    {
        return __('messages.common.something_wrong_happened_please_try_again_or_contact_administrator');
    }

    public static function getModelSoftDeletedMessage(): string
    {
        return __('messages.common.there_is_a_deleted_entity_with_this_name');
    }

    public static function getForbiddenErrorMessage(): string
    {
        return __('messages.authz.you_are_not_allowed_to_perform_this_action');
    }

    public static function getWrongCredentialsMessage(): string
    {
        return __('messages.auth.the_credentials_entered_are_incorrect');
    }

    public static function getRecordExistsErrorMessage(): string
    {
        return __('messages.common.this_record_exists_in_the_database');
    }

    public static function getIsExpiredMessage(): string
    {
        return __('messages.auth.the_token_has_expired');
    }
}
