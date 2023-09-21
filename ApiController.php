<?php
namespace App\Controller;

use RestApi\Controller\ApiController;
use RestApi\Utility\JwtToken;
use Cake\Event\Event;
use Cake\Controller\Component\AuthComponent;
use Cake\Auth\DefaultPasswordHasher;
use Cake\Mailer\Email;
use Cake\Routing\Router;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use App\Form\ContactForm;
use Cake\Datasource\ConnectionManager;
use Cartalyst\Stripe\Stripe;
use Cake\I18n\FrozenTime;
use Twilio\Rest\Client;
// use App\Controller\Exception;


class ApiController extends ApiController
{
    public function initialize() {
        parent::initialize();
        $this->loadComponent('RequestHandler');
    }

    /**
     * bar method
     *
     */
    public function bar()
    {
      // your action logic
      $this->request->allowMethod('post');
      $this->httpStatusCode = 200;

      // Set the response
      $this->apiResponse['you_response'] = 'your response data--'.$uname;
      
    }

  public function testTwilioSms($phone,$mess) 
	{
		$sid = 'djdjdjdj';
		$token = 'eieieie';
		$client = new Client($sid, $token);
		$fphone ="8888777";
		$msg = $client->messages->create($phone,array('from' => $fphone,'body' => $mess));
	
		$this->response->body($msg);
		return $this->response;
	}
  

  /**
   * Login method
   *
   * @return void
   */
  public function login() {
    $this->request->allowMethod('post');
       
    $this->loadComponent('Auth', [
        'authenticate' => [
            'Form' => [
                'fields' => [
                    'username' => 'email',
                    'password' => 'password'
                ]
            ],
        ]
    ]);


    $user = '';
    if($this->request->is('POST')){
      $user = $this->Auth->identify($this->request->data);
    }
    if (!empty($user)) {
      // update device token
      $device_token = $this->request->getData('device_token');
      $device_type = $this->request->getData('device_type');
      $conn = ConnectionManager::get('default');
      $set_device_token = $user['device_token'];
      $set_device_type = $user['device_type'];
      if(!empty($device_token)) {
        $stmt_up = $conn->execute("UPDATE users SET device_token = '".$device_token."', device_type = '".$device_type."' WHERE id = ".$user['id']);
        $set_device_token = $device_token;
        $set_device_type = $device_type;
      }
      //
      // Log file
      $log  = "device_token: ".$this->request->getData('device_token').'device type: '.$this->request->getData('device_type').' - '.date("F j, Y, g:i a")."User: ".$this->request->getData('email').PHP_EOL."-------------------------".PHP_EOL;
      //Save string to log, use FILE_APPEND to append.
      file_put_contents(WWW_ROOT . 'logfile.log', $log, FILE_APPEND);

      $this->httpStatusCode = 200;
      $this->apiResponse['message'] = 'Login successfully';

      $this->apiResponse['id'] = $user['id'];
      $this->apiResponse['full_name'] = $user['full_name'];
      $this->apiResponse['role'] = $user['rol'];
      $this->apiResponse['images'] = $user['images'];
      $this->apiResponse['city'] = $user['city'];
      $this->apiResponse['phone'] = $user['phone'];
      $this->apiResponse['experience'] = $user['experience'];

      $this->apiResponse['address_of_office'] = $user['address_of_office'];
      $this->apiResponse['spoken_languages_primary'] = $user['spoken_languages_primary'];
      $this->apiResponse['spoken_languages_secondary'] = $user['spoken_languages_secondary'];
      $this->apiResponse['zip'] = $user['zip'];
      $this->apiResponse['state'] = $user['state'];
      $this->apiResponse['status'] = $user['status'];
      $this->apiResponse['account_freez_status'] = $user['account_freez_status'];
      $this->apiResponse['description'] = $user['description'];
      $this->apiResponse['license_number'] = $user['license_number'];
      $this->apiResponse['user_rating'] = $this->getUserRatingApi($user['id']);
      $this->apiResponse['device_token'] = $set_device_token;
      $this->apiResponse['device_type'] = $set_device_type;
      // $this->apiResponse['notifaction_log'] = $status;

      // $this->apiResponse['data'] = $user;
    } else {
      $this->httpStatusCode = 200;
      $this->apiResponse['error'] = 'Invalid email or password.';
      // $this->apiResponse['status'] = 'NOK';
    }
    
  }

  /**
   * [deactivateYourAccount description]
   * @return [type] [description]
   */
  public function deactivateYourAccount() {
    $this->request->allowMethod('post');
    $user_id = $this->request->getData('user_id');
    $status = $this->request->getData('status');
    $conn = ConnectionManager::get('default');
    try{
      $stmt_up = $conn->execute("UPDATE users SET user_activity_status = '".$status."' WHERE id = ".$user_id);

      $this->httpStatusCode = 200;
      if($status == 'yes') {
        $this->apiResponse['message'] = 'Account activated successfully.';
      } else {
        $this->apiResponse['message'] = 'Account deactivated successfully.';
      }
    }
    catch (Exception $e) {
      $this->httpStatusCode = 200;
      $this->apiResponse['message'] = 'Account not deactivated.';
      $this->apiResponse['status'] = 'NOK';
    }

  }

  public function completeWithRating() {
    $this->request->allowMethod('post');
    $rate = $this->request->getData('rate');
    $invite_id = $this->request->getData('invite_id');
    $comment = $this->request->getData('comment');
    $conn = ConnectionManager::get('default');
    try{
      $stmt_up = $conn->execute("UPDATE invites SET rate = '".$rate."', comment = '".$comment."' WHERE id = ".$invite_id);
      $this->httpStatusCode = 200;
      $this->apiResponse['message'] = 'Completed successfully.';
    }
    catch(Exception $e) {
      $this->httpStatusCode = 200;
      $this->apiResponse['message'] = 'Not completed.';
      $this->apiResponse['status'] = 'NOK';
    }
  }

  /**
   * @param $http2ch          the curl connection
   * @param $http2_server     the Apple server url
   * @param $apple_cert       the path to the certificate
   * @param $app_bundle_id    the app bundle id
   * @param $message          the payload to send (JSON)
   * @param $token            the token of the device
   * @return mixed            the status code
   */
  function sendHTTP2Push($http2ch, $http2_server, $apple_cert, $app_bundle_id, $message, $token) {
   
      // url (endpoint)
      $url = "{url}";
      $http2ch = "{header}";
   
      // other curl options
      curl_setopt_array($http2ch, array(
          CURLOPT_URL => $url,
          CURLOPT_PORT => 443,
          CURLOPT_HTTPHEADER => $headers,
          CURLOPT_POST => TRUE,
          CURLOPT_POSTFIELDS => $message,
          CURLOPT_RETURNTRANSFER => TRUE,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_SSLCERT => $cert,
          CURLOPT_HEADER => 1
      ));
   
      $result = curl_exec($http2ch);

      // get response
      $status = curl_getinfo($http2ch, CURLINFO_HTTP_CODE);
   
      return $status;
  }
}