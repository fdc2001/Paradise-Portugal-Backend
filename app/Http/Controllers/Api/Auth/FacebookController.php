<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enum\LoginProviders;
use App\Http\Controllers\Auth\InteractsWithOauth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\Oauth\FacebookRequest;
use App\Http\Responses\Api\ApiErrorResponse;
use App\Http\Responses\Api\ApiSuccessResponse;
use App\Models\User;
use App\Services\Auth\LoginService;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class FacebookController extends Controller
{
    use InteractsWithOauth;
    public function __invoke(FacebookRequest $request)
    {
        try {
            $socialiteUser = Socialite::with('facebook')->userFromToken($request['accessToken']);
            $email = $socialiteUser->getEmail();

            if (User::where('email', $email)->exists()) {
                $user = $this->findOrValidateLoginProvider($socialiteUser->getId(), LoginProviders::FACEBOOK);
            } else {
                [$firstName, $lastName] = $this->extractNameParts($socialiteUser->getName());

                $user = $this->createUserFromSocialiteUser($socialiteUser->getId(), $firstName, $lastName, $email, $socialiteUser->getAvatar(), LoginProviders::FACEBOOK);
            }

            $deviceName = $request->get('deviceName');
            $loginService = new LoginService($user, $deviceName);

            return ApiSuccessResponse::make($loginService->login());
        } catch (\Exception $e) {
            return new ApiErrorResponse($e);
        }
    }
}
