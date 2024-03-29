<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\LdapAd;
use Com\Tecnick\Barcode\Barcode;
use Google2FA;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Log;
use Redirect;

/**
 * This controller handles authentication for the user, including local
 * database users and LDAP users.
 *
 * @author [A. Gianotto] [<snipe@snipe.net>]
 * @version    v1.0
 */
class LoginController extends Controller
{

    use ThrottlesLogins;

    // This tells the auth controller to use username instead of email address
    protected $username = 'username';

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * @var LdapAd
     */
    protected $ldap;

    /**
     * Create a new authentication controller instance.
     *
     * @param LdapAd $ldap
     *
     * @return void
     */
    public function __construct(LdapAd $ldap)
    {
        parent::__construct();
        $this->middleware('guest', ['except' => ['logout','postTwoFactorAuth','getTwoFactorAuth','getTwoFactorEnroll']]);
        Session::put('backUrl', \URL::previous());
        $this->ldap = $ldap;
    }

    function showLoginForm(Request $request)
    {
        $this->loginViaRemoteUser($request);
        if (Auth::check()) {
            return redirect()->intended('dashboard');
        }

        if (Setting::getSettings()->login_common_disabled == "1") {
            return view('errors.403');
        }

        return view('auth.login');
    }

    /**
     * Log in a user by LDAP
     * 
     * @author Wes Hulette <jwhulette@gmail.com>
     * 
     * @since 5.0.0
     *
     * @param Request $request
     * 
     * @return User
     * 
     * @throws \Exception
     */
    private function loginViaLdap(Request $request): User
    {
        try {
            return $this->ldap->ldapLogin($request->input('username'), $request->input('password'));
        } catch (\Exception $ex) {
            LOG::debug("LDAP user login: " . $ex->getMessage());
            throw new \Exception($ex->getMessage());
        }
    }

    private function loginViaRemoteUser(Request $request)
    {
        $remote_user = $request->server('REMOTE_USER');
        if (Setting::getSettings()->login_remote_user_enabled == "1" && isset($remote_user) && !empty($remote_user)) {
            Log::debug("Authenticatiing via REMOTE_USER.");

            $pos = strpos($remote_user, '\\');
            if ($pos > 0) {
                $remote_user = substr($remote_user, $pos + 1);
            };
            
            try {
                $user = User::where('username', '=', $remote_user)->whereNull('deleted_at')->where('activated', '=', '1')->first();
                Log::debug("Remote user auth lookup complete");
                if(!is_null($user)) Auth::login($user, true);
            } catch(Exception $e) {
                Log::debug("There was an error authenticating the Remote user: " . $e->getMessage());
            }
        }
    }

    /**
     * Account sign in form processing.
     *
     * @return Redirect
     */
    public function login(Request $request)
    {
        if (Setting::getSettings()->login_common_disabled == "1") {
            return view('errors.403');
        }

        $validator = $this->validator(Input::all());

        if ($validator->fails()) {
            return redirect()->back()->withInput()->withErrors($validator);
        }

        $this->maxLoginAttempts = config('auth.throttle.max_attempts');
        $this->lockoutTime = config('auth.throttle.lockout_duration');

        if ($lockedOut = $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        $user = null;

        // Should we even check for LDAP users?
        if ($this->ldap->init()) {
            LOG::debug("LDAP is enabled.");
            try {
                LOG::debug("Attempting to log user in by LDAP authentication.");
                $user = $this->loginViaLdap($request);
                Auth::login($user, true);

            // If the user was unable to login via LDAP, log the error and let them fall through to
            // local authentication.
            } catch (\Exception $e) {
                Log::debug("There was an error authenticating the LDAP user: ".$e->getMessage());
            }
        }

        // If the user wasn't authenticated via LDAP, skip to local auth
        if (!$user) {
            Log::debug("Authenticating user against database.");
          // Try to log the user in
            if (!Auth::attempt(['username' => $request->input('username'), 'password' => $request->input('password'), 'activated' => 1], $request->input('remember'))) {

                if (!$lockedOut) {
                    $this->incrementLoginAttempts($request);
                }

                Log::debug("Local authentication failed.");
                return redirect()->back()->withInput()->with('error', trans('auth/message.account_not_found'));
            } else {

                  $this->clearLoginAttempts($request);
            }
        }

        if ($user = Auth::user()) {
            $user->last_login = Carbon::now();
            Log::debug('Last login:'.$user->last_login);
            $user->save();
        }
        // Redirect to the users page
        return redirect()->intended()->with('success', trans('auth/message.signin.success'));
    }


    /**
     * Two factor enrollment page
     *
     * @return Redirect
     */
    public function getTwoFactorEnroll()
    {

        // Make sure the user is logged in
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', trans('auth/general.login_prompt'));
        }


        $settings = Setting::getSettings();
        $user = Auth::user();

        // We wouldn't normally see this page if 2FA isn't enforced via the
        // \App\Http\Middleware\CheckForTwoFactor middleware AND if a device isn't enrolled,
        // but let's check check anyway in case there's a browser history or back button thing.
        // While you can access this page directly, enrolling a device when 2FA isn't enforced
        // won't cause any harm.

        if (($user->two_factor_secret!='') && ($user->two_factor_enrolled==1)) {
            return redirect()->route('two-factor')->with('error', trans('auth/message.two_factor.already_enrolled'));
        }

        $secret = Google2FA::generateSecretKey();
        $user->two_factor_secret = $secret;
        $user->save();

        $barcode = new Barcode();
        $barcode_obj =
            $barcode->getBarcodeObj(
                'QRCODE',
                sprintf(
                    'otpauth://totp/%s:%s?secret=%s&issuer=Snipe-IT&period=30',
                    urlencode($settings->site_name),
                    urlencode($user->username),
                    urlencode($secret)
                ),
                300,
                300,
                'black',
                [-2, -2, -2, -2]
            );

        return view('auth.two_factor_enroll')->with('barcode_obj', $barcode_obj);
    }


    /**
     * Two factor code form page
     *
     * @return Redirect
     */
    public function getTwoFactorAuth()
    {
        // Check that the user is logged in
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', trans('auth/general.login_prompt'));
        }

        $user = Auth::user();

        // Check whether there is a device enrolled.
        // This *should* be handled via the \App\Http\Middleware\CheckForTwoFactor middleware
        // but we're just making sure (in case someone edited the database directly, etc)
        if (($user->two_factor_secret=='') || ($user->two_factor_enrolled!=1)) {
            return redirect()->route('two-factor-enroll');
        }

        return view('auth.two_factor');
    }

    /**
     * Two factor code submission
     *
     * @param Request $request
     *
     * @return Redirect
     */
    public function postTwoFactorAuth(Request $request)
    {

        if (!Auth::check()) {
            return redirect()->route('login')->with('error', trans('auth/general.login_prompt'));
        }

        if (!$request->has('two_factor_secret')) {
            return redirect()->route('two-factor')->with('error', trans('auth/message.two_factor.code_required'));
        }

        if (!$request->has('two_factor_secret')) {
            return redirect()->route('two-factor')->with('error', 'Two-factor code is required.');
        }

        $user = Auth::user();
        $secret = $request->input('two_factor_secret');

        if (Google2FA::verifyKey($user->two_factor_secret, $secret)) {
            $user->two_factor_enrolled = 1;
            $user->save();
            $request->session()->put('2fa_authed', 'true');
            return redirect()->route('home')->with('success', 'You are logged in!');
        }

        return redirect()->route('two-factor')->with('error', trans('auth/message.two_factor.invalid_code'));


    }


    /**
     * Logout page.
     *
     * @param Request $request
     *
     * @return Redirect
     */
    public function logout(Request $request)
    {
        $request->session()->forget('2fa_authed');

        Auth::logout();

        $settings = Setting::getSettings();
        $customLogoutUrl = $settings->login_remote_user_custom_logout_url ;
        if ($settings->login_remote_user_enabled == '1' && $customLogoutUrl != '') {
            return redirect()->away($customLogoutUrl);
        }

        return redirect()->route('login')->with('success',  trans('auth/general.logout.success'));
    }


    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'username' => 'required',
            'password' => 'required',
        ]);
    }


    public function username()
    {
        return 'username';
    }

    /**
     * Redirect the user after determining they are locked out.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendLockoutResponse(Request $request)
    {
        $seconds = $this->limiter()->availableIn(
            $this->throttleKey($request)
        );

        $minutes = round($seconds / 60);

        $message = \Lang::get('auth/message.throttle', ['minutes' => $minutes]);

        return redirect()->back()
            ->withInput($request->only($this->username(), 'remember'))
            ->withErrors([$this->username() => $message]);
    }


    /**
     * Override the lockout time and duration
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function hasTooManyLoginAttempts(Request $request)
    {
        $lockoutTime = config('auth.throttle.lockout_duration');
        $maxLoginAttempts = config('auth.throttle.max_attempts');

        return $this->limiter()->tooManyAttempts(
            $this->throttleKey($request),
            $maxLoginAttempts,
            $lockoutTime
        );
    }

    public function legacyAuthRedirect() {
        return redirect()->route('login');
    }

    public function redirectTo()
    {
        return Session::get('backUrl') ? Session::get('backUrl') :   $this->redirectTo;
    }

}
