<?php
/*
 * Copyright 2015 Stormpath, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Stormpath\Laravel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Factory as Validator;
use Stormpath\Laravel\Http\Traits\AuthenticatesUser;

class RegisterController extends Controller
{
    use AuthenticatesUser;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var Validator
     */
    private $validator;


    /**
     * LoginController constructor.
     * @param Request $request
     * @param Validator $validator
     */
    public function __construct(Request $request, Validator $validator)
    {
        $this->request = $request;
        $this->validator = $validator;
    }

    public function getRegister()
    {
        $status = $this->request->get('status');

        return view( config('stormpath.web.register.view'), compact('status') );
    }

    public function postRegister()
    {
        $validator = $this->registerValidator();

        if($validator->fails()) {
            return redirect()
                ->to(config('stormpath.web.register.uri'))
                ->withErrors($validator)
                ->withInput();
        }
        try {
            $registerFields = $this->setRegisterFields();

            $account = \Stormpath\Resource\Account::instantiate($registerFields);

            $application = app('stormpath.application');

            $account = $application->createAccount($account);

            if(config('stormpath.web.verifyEmail.enabled') == true) {
                return redirect()
                    ->route('stormpath.login', ['status'=>'unverified']);
            }

            if(config('stormpath.web.register.autoAuthorize') == false) {
                return redirect()
                    ->route('stormpath.login', ['status'=>'created']);
            }

            $login = isset($registerFields['username']) ? $registerFields['username'] : null;
            $login = isset($registerFields['email']) ? $registerFields['email'] : $login;

            $result = $this->authenticate($login, $registerFields['password']);

            return redirect()
                ->to(config('stormpath.web.register.nextUri'))
                ->withCookies(
                    [
                        config('stormpath.web.accessTokenCookie.name') =>
                            cookie(
                                config('stormpath.web.accessTokenCookie.name'),
                                $result->getAccessTokenString(),
                                $result->getExpiresIn(),
                                config('stormpath.web.accessTokenCookie.path'),
                                config('stormpath.web.accessTokenCookie.domain'),
                                config('stormpath.web.accessTokenCookie.secure'),
                                config('stormpath.web.accessTokenCookie.httpOnly')
                            ),
                        config('stormpath.web.refreshTokenCookie.name') =>
                            cookie(
                                config('stormpath.web.refreshTokenCookie.name'),
                                $result->getRefreshTokenString(),
                                $result->getExpiresIn(),
                                config('stormpath.web.refreshTokenCookie.path'),
                                config('stormpath.web.refreshTokenCookie.domain'),
                                config('stormpath.web.refreshTokenCookie.secure'),
                                config('stormpath.web.refreshTokenCookie.httpOnly')
                            )
                    ]
                );


        } catch(\Stormpath\Resource\ResourceError $re) {
            return redirect()
                ->to(config('stormpath.web.register.uri'))
                ->withErrors(['errors'=>[$re->getMessage()]])
                ->withInput();
        }

    }

    private function registerValidator()
    {
        $rules = [];
        $messages = [];

        $registerField = config('stormpath.web.register.form.fields');

        foreach($registerField as $field) {
            if($field['required'] == true) {
                $rules[$field['name']] = 'required';
            }
        }

        $messages[config('stormpath.web.register.form.fields.username.name').'.required'] = 'Username is required.';
        $messages[config('stormpath.web.register.form.fields.givenName.name').'.required'] = 'Given name is required.';
        $messages[config('stormpath.web.register.form.fields.middleName.name').'.required'] = 'Middle name is required.';
        $messages[config('stormpath.web.register.form.fields.surname.name').'.required'] = 'Surname is required.';
        $messages[config('stormpath.web.register.form.fields.email.name').'.required'] = 'Email is required.';
        $messages[config('stormpath.web.register.form.fields.password.name').'.required'] = 'Password is required.';
        $messages[config('stormpath.web.register.form.fields.passwordConfirm.name').'.required'] = 'Password confirmation is required.';


        if( config('stormpath.web.register.form.fields.passwordConfirm.required') ) {
            $rules['password'] = 'required|confirmed';
            $messages['password.confirmed'] = 'Passwords are not the same.';
        }

        $validator = $this->validator->make(
            $this->request->all(),
            $rules,
            $messages
        );


        return $validator;
    }

    private function setRegisterFields()
    {
        $registerArray = [];
        $registerFields = config('stormpath.web.register.form.fields');
        foreach($registerFields as $spfield=>$field) {
            if($field['required'] == true) {
                $registerArray[$spfield] = $this->request->get($field['name']);
            }
        }

        return $registerArray;
    }
}