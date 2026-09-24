<?php

namespace App\Managers\Auth;

use App\Helpers\ApiCodes;
use App\Models\User;
use App\Traits\HasMessages;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use stdClass;
use Throwable;

class AuthManager
{
    use HasMessages;

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, authorization: stdClass}|false
     */
    public function login(array $credentials): array|false
    {
        try {
            $user = User::query()->where('email', $credentials['email'])->first();

            if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
                $this->throwExceptionError(new AuthorizationException(ApiCodes::getWrongCredentialsMessage(), 401));
            }

            $authorization = $this->getAuthenticationJwt([
                'email' => $user->email,
                'password' => $credentials['password'],
            ]);

            return [
                'user' => $user->loadMissing('role'),
                'authorization' => $authorization,
            ];
        } catch (AuthorizationException $e) {
            $this->setError($e->getMessage(), ApiCodes::UNAUTHENTICATED);

            return false;
        } catch (Throwable $e) {
            Log::error($e);
            $this->setError(ApiCodes::getGeneralErrorMessage(), ApiCodes::SERVICE_UNAVAILABLE);

            return false;
        }
    }

    public function logout(User $user): bool
    {
        try {
            $token = $user->token();
            if ($token !== null) {
                $token->revoke();
            }

            return true;
        } catch (Throwable $e) {
            Log::error($e);

            return false;
        }
    }

    /**
     * @param  array{refresh_token: string}  $data
     */
    public function refreshToken(array $data): stdClass|false
    {
        try {
            $tokenRequest = Request::create('/oauth/token', 'POST', [
                'grant_type' => 'refresh_token',
                'client_id' => getenv('PASSPORT_CLIENT_ID'),
                'client_secret' => getenv('PASSPORT_CLIENT_SECRET'),
                'refresh_token' => $data['refresh_token'],
            ]);

            $result = json_decode(app()->handle($tokenRequest)->getContent(), false, 512, JSON_THROW_ON_ERROR);

            if (isset($result->error)) {
                $this->setError('messages.auth.refresh_token_is_invalid', ApiCodes::UNAUTHENTICATED);

                return false;
            }

            return $result;
        } catch (Throwable $e) {
            Log::error($e);
            $this->setError('messages.auth.refresh_token_is_invalid', ApiCodes::UNAUTHENTICATED);

            return false;
        }
    }

    /**
     * @param  array{current_password: string, new_password: string}  $data
     */
    public function changePassword(User $user, array $data): User
    {
        if (! Hash::check($data['current_password'], $user->password)) {
            $this->throwValidationError('current_password', 'messages.auth.the_current_password_is_incorrect');
        }

        $user->update(['password' => $data['new_password']]);

        return $user->refresh()->loadMissing('role');
    }

    public function userInfo(User $user): User
    {
        return $user->loadMissing('role');
    }

    /**
     * Issue tokens via the Passport password grant.
     *
     * @param  array{email: string, password: string}  $validated
     */
    public function getAuthenticationJwt(array $validated): stdClass
    {
        $tokenRequest = Request::create('/oauth/token', 'POST', [
            'grant_type' => 'password',
            'client_id' => getenv('PASSPORT_CLIENT_ID'),
            'client_secret' => getenv('PASSPORT_CLIENT_SECRET'),
            'username' => $validated['email'],
            'password' => $validated['password'],
            'scope' => '*',
        ]);

        $result = json_decode(app()->handle($tokenRequest)->getContent(), false, 512, JSON_THROW_ON_ERROR);

        if (isset($result->error)) {
            $this->throwExceptionError(new Exception('Passport client id or secret are either missing or invalid.'));
        }

        return $result;
    }
}
