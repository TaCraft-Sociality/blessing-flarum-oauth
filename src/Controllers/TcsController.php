<?php

namespace Tacraft\TcsLogin\Controllers;

use App\Models\User;
use Blessing\Filter;
use Carbon\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TcsController extends Controller
{
    public function redirect(Request $request)
    {
        $config = $this->config();

        if ($config['url'] === '' || $config['client_id'] === '' || $config['client_secret'] === '') {
            abort(500, trans('Tacraft\\TcsLogin::general.not-configured'));
        }

        $state = Str::random(40);
        $request->session()->put('tcs_login_state', $state);

        $query = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $this->callbackUrl(),
            'response_type' => 'code',
            'scope' => 'user.read',
            'state' => $state,
        ]);

        return redirect($config['url'].'/oauth/authorize?'.$query);
    }

    public function callback(Request $request, Dispatcher $dispatcher, Filter $filter)
    {
        $expected = (string) $request->session()->pull('tcs_login_state');
        $state = (string) $request->query('state');

        if ($expected === '' || ! hash_equals($expected, $state)) {
            abort(403, trans('Tacraft\\TcsLogin::general.state-failed'));
        }

        if ($request->query('error')) {
            abort(403, (string) $request->query('error_description', $request->query('error')));
        }

        $code = (string) $request->query('code');

        if ($code === '') {
            abort(403, trans('Tacraft\\TcsLogin::general.no-code'));
        }

        $config = $this->config();

        try {
            $tokenResponse = $this->http()->asForm()->acceptJson()->post($config['url'].'/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $this->callbackUrl(),
                'code' => $code,
            ]);
        } catch (Throwable $e) {
            Log::error('TCS login: token request failed', ['message' => $e->getMessage()]);
            abort(500, trans('Tacraft\\TcsLogin::general.http-failed', ['message' => $e->getMessage()]));
        }

        $accessToken = $tokenResponse->json('access_token');

        if (! $tokenResponse->successful() || ! is_string($accessToken) || $accessToken === '') {
            Log::error('TCS login: token exchange failed', [
                'status' => $tokenResponse->status(),
                'body' => mb_substr($tokenResponse->body(), 0, 2000),
            ]);

            abort(500, trans('Tacraft\\TcsLogin::general.token-failed', ['status' => $tokenResponse->status()]));
        }

        try {
            $infoResponse = $this->http()->withToken($accessToken)->acceptJson()->get($config['url'].'/api/user');
        } catch (Throwable $e) {
            Log::error('TCS login: userinfo request failed', ['message' => $e->getMessage()]);
            abort(500, trans('Tacraft\\TcsLogin::general.http-failed', ['message' => $e->getMessage()]));
        }

        if (! $infoResponse->successful()) {
            Log::error('TCS login: userinfo failed', [
                'status' => $infoResponse->status(),
                'body' => mb_substr($infoResponse->body(), 0, 2000),
            ]);

            abort(500, trans('Tacraft\\TcsLogin::general.userinfo-failed', ['status' => $infoResponse->status()]));
        }

        $info = $infoResponse->json();

        if (! is_array($info)) {
            abort(500, trans('Tacraft\\TcsLogin::general.invalid-response'));
        }

        $email = trim((string) ($info['email'] ?? ''));

        if ($email === '') {
            abort(500, trans('Tacraft\\TcsLogin::general.no-email'));
        }

        if (mb_strlen($email) > 100) {
            abort(500, trans('Tacraft\\TcsLogin::general.email-too-long'));
        }

        $confirmed = (bool) ($info['isEmailConfirmed'] ?? $info['is_email_confirmed'] ?? false);

        if (filter_var(option('tcs_login_require_verified', true), FILTER_VALIDATE_BOOLEAN) && ! $confirmed) {
            abort(403, trans('Tacraft\\TcsLogin::general.email-unverified'));
        }

        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

        if (! $user) {
            $nickname = trim((string) ($info['nickname'] ?? ''));

            if ($nickname === '') {
                $nickname = trim((string) ($info['username'] ?? ''));
            }

            if ($nickname === '') {
                $nickname = Str::before($email, '@');
            }

            $user = new User();
            $user->email = $email;
            $user->nickname = Str::limit($nickname, 50, '');
            $user->score = (int) option('user_initial_score');
            $user->avatar = 0;
            $user->password = '';
            $user->ip = (string) $filter->apply('client_ip', $request->ip() ?? '0.0.0.0');
            $user->permission = User::NORMAL;
            $user->register_at = Carbon::now();
            $user->last_sign_at = Carbon::now()->subDay();
            $user->verified = true;
            $user->save();

            $dispatcher->dispatch('auth.registration.completed', [$user]);
        }

        $dispatcher->dispatch('auth.login.ready', [$user]);
        Auth::login($user);
        $dispatcher->dispatch('auth.login.succeeded', [$user]);

        return redirect($request->session()->pull('last_requested_path', url('/user')));
    }

    protected function config(): array
    {
        return [
            'url' => rtrim(trim((string) option('tcs_login_url')), '/'),
            'client_id' => trim((string) option('tcs_login_client_id')),
            'client_secret' => trim((string) option('tcs_login_client_secret')),
        ];
    }

    protected function http()
    {
        return Http::withOptions([
            'verify' => filter_var(option('tcs_login_verify_ssl', true), FILTER_VALIDATE_BOOLEAN),
            'timeout' => 20,
        ]);
    }

    protected function callbackUrl(): string
    {
        return url('auth/tcs/callback');
    }
}
