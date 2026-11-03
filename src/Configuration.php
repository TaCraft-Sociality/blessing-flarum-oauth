<?php

namespace Tacraft\TcsLogin;

use App\Services\OptionForm;
use Option;

class Configuration
{
    public function render()
    {
        $form = Option::form('tcs_login', trans('Tacraft\\TcsLogin::general.config-title'), function (OptionForm $form) {
            $form->text('tcs_login_url', trans('Tacraft\\TcsLogin::general.url'))
                ->hint(trans('Tacraft\\TcsLogin::general.url-hint'));

            $form->text('tcs_login_client_id', trans('Tacraft\\TcsLogin::general.client-id'))
                ->hint(trans('Tacraft\\TcsLogin::general.client-id-hint'));

            $form->text('tcs_login_client_secret', trans('Tacraft\\TcsLogin::general.client-secret'))
                ->hint(trans('Tacraft\\TcsLogin::general.client-secret-hint'));

            $form->checkbox('tcs_login_require_verified', trans('Tacraft\\TcsLogin::general.require-verified'))
                ->value(option('tcs_login_require_verified', true))
                ->hint(trans('Tacraft\\TcsLogin::general.require-verified-hint'));

            $form->checkbox('tcs_login_verify_ssl', trans('Tacraft\\TcsLogin::general.verify-ssl'))
                ->value(option('tcs_login_verify_ssl', true))
                ->hint(trans('Tacraft\\TcsLogin::general.verify-ssl-hint'));
        })->handle();

        if (request()->isMethod('post')) {
            @unlink(storage_path('options.php'));
        }

        return view('Tacraft\\TcsLogin::config', ['form' => $form]);
    }
}
