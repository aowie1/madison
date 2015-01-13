<?php

class RemindersController extends BaseController {


	public function __construct(){
		parent::__construct();
	}

	/**
	 * Display the password reminder view.
	 *
	 * @return Response
	 */
	public function getRemind()
	{
		$data = array(
			'page_id'		=> 'dashboard',
			'page_title'	=> 'Dashboard'
		);

		return View::make('password.remind', $data);
	}

	/**
	 * Handle a POST request to remind a user of their password.
	 *
	 * @return Response
	 */
	public function postRemind()
	{
		switch ($response = Password::remind(Input::only('email'), function($message){
		    $message->subject('Madison Password Reset');
		})) {

			case Password::INVALID_USER:
				return Response::json($this->growlMessage(Lang::get($response)), 'error');

			case Password::REMINDER_SENT:
				return Response::json($this->growlMessage('Password change instructions have been sent to your email address.', 'warning'));
		}
	}

	/**
	 * Handle a POST request to reset a user's password.
	 *
	 * @return Response
	 */
	public function postReset()
	{
		$credentials = Input::only(
			'email', 'password', 'password_confirmation', 'token'
		);

		$response = Password::reset($credentials, function($user, $password)
		{
			$user->password = $password;

			$user->save();
		});

		switch ($response)
		{
			case Password::INVALID_PASSWORD:
			case Password::INVALID_TOKEN:
			case Password::INVALID_USER:
				return Response::json($this->growlMessage(Lang::get($response), 'error'));

			case Password::PASSWORD_RESET:
				return Response::json($this->growlMessage('Password changed successfully.', 'success'));
		}
	}


	public function getConfirmation()
	{
		$data = array(
			'page_id'		=> 'dashboard',
			'page_title'	=> 'Resend confirmation email'
		);

		return View::make('password.resend', $data);
	}

	/**
	 * Handle a POST request to remind a user of their password.
	 *
	 * @return Response
	 */
	public function postConfirmation()
	{
		// 3 error cases - user already confirmed, email does not exist, password not correct
		// (prevents people from brute-forcing email addresses to see who is registered)

		$email = Input::get('email');
		$password = Input::get('password');
		$user = User::where('email', $email)->first();
		
		if(!isset($user)){
			return Redirect::to('verification/remind')->with('error', 'That email was not registered.');
		}

		if(empty($user->token)) {
			return Redirect::to('user/login')->with('error', 'That user was already confirmed.');
		}

		if (!Hash::check($password, $user->password)) {
			return Redirect::to('verification/remind')->with('error', 'The password for that email is incorrect.');
		} 
		
		$token = $user->token;
		$email = $user->email;
		$fname = $user->fname;

		//Send email to user for email account verification
		Mail::queue('email.signup', array('token'=>$token), function ($message) use ($email, $fname) {
    		$message->subject('Welcome to the Madison Community');
    		$message->from('sayhello@opengovfoundation.org', 'Madison');
    		$message->to($email);
		});
			
		return Redirect::to('user/login')->with('message', 'An email has been sent to your email address.  Please follow the instructions in the email to confirm your email address before logging in.');

	}

}
