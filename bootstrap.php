<?php

use App\Services\Hook;
use Blessing\Filter;

return function (Filter $filter) {
    $insert = function (array $rows, string $formRow): array {
        $at = array_search('Blessing\OAuthCore::providers', $rows, true);

        if ($at === false) {
            $form = array_search($formRow, $rows, true);
            $at = $form === false ? count($rows) : $form + 1;
        }

        array_splice($rows, $at, 0, ['Tacraft\TcsLogin::button']);

        return $rows;
    };

    $filter->add('auth_page_rows:login', function ($rows) use ($insert) {
        return $insert($rows, 'auth.rows.login.form');
    });

    $filter->add('auth_page_rows:register', function ($rows) use ($insert) {
        return $insert($rows, 'auth.rows.register.form');
    });

    try {
        Hook::addMenuItem('admin', 0, [
            'title' => 'Tacraft\\TcsLogin::general.config-title',
            'link' => '/admin/tcs-login',
            'icon' => 'fa-comments',
        ]);
    } catch (\Throwable $e) {
        report($e);
    }

    try {
        Hook::addRoute(function () {
            Route::prefix('auth/tcs')
                ->namespace('Tacraft\\TcsLogin\\Controllers')
                ->middleware(['web', 'guest'])
                ->group(function () {
                    Route::get('', 'TcsController@redirect');
                    Route::get('callback', 'TcsController@callback');
                });

            Route::prefix('admin/tcs-login')
                ->namespace('Tacraft\\TcsLogin\\Controllers')
                ->middleware(['web', 'auth', 'role:admin'])
                ->group(function () {
                    Route::any('', 'TcsAdminController@config');
                });
        });
    } catch (\Throwable $e) {
        report($e);
    }
};
