<?php

namespace Swag\NuveiCheckout\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Nuvei help class. We use it for REST API calls and logs.
 * 
 * @author Nuvei
 */
class Nuvei
{
    const NUVEI_PARAMS_VALIDATION = [
        // deviceDetails
        'deviceType' => array(
            'length' => 10,
            'flag'    => FILTER_DEFAULT
        ),
        'deviceName' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        'deviceOS' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        'browser' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        // deviceDetails END

        // userDetails, shippingAddress, billingAddress
        'firstName' => array(
            'length' => 30,
            'flag'    => FILTER_DEFAULT
        ),
        'lastName' => array(
            'length' => 40,
            'flag'    => FILTER_DEFAULT
        ),
        'address' => array(
            'length' => 60,
            'flag'    => FILTER_DEFAULT
        ),
        'cell' => array(
            'length' => 18,
            'flag'    => FILTER_DEFAULT
        ),
        'phone' => array(
            'length' => 18,
            'flag'    => FILTER_DEFAULT
        ),
        'zip' => array(
            'length' => 10,
            'flag'    => FILTER_DEFAULT
        ),
        'city' => array(
            'length' => 30,
            'flag'    => FILTER_DEFAULT
        ),
        'country' => array(
            'length' => 20,
            'flag'    => FILTER_DEFAULT
        ),
        'state' => array(
            'length' => 2,
            'flag'    => FILTER_DEFAULT
        ),
        'county' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        // userDetails, shippingAddress, billingAddress END

        // specific for shippingAddress
        'shippingCounty' => array(
            'length' => 255,
            'flag'    => FILTER_DEFAULT
        ),
        'addressLine2' => array(
            'length' => 50,
            'flag'    => FILTER_DEFAULT
        ),
        'addressLine3' => array(
            'length' => 50,
            'flag'    => FILTER_DEFAULT
        ),
        // specific for shippingAddress END

        // urlDetails
        'successUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        'failureUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        'pendingUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        'notificationUrl' => array(
            'length' => 1000,
            'flag'    => FILTER_VALIDATE_URL
        ),
        // urlDetails END
    ];

    const NUVEI_PARAMS_VALIDATION_EMAIL = [
        'length'    => 79,
        'flag'      => FILTER_VALIDATE_EMAIL
    ];
    
    const NUVEI_POP_AUTO_CLOSE_URL  = 'https://cdn.safecharge.com/safecharge_resources/v1/websdk/autoclose.html';
    const NUVEI_ORDER_ACTIONS       = ['settle', 'void', 'refund'];
    const NUVEI_REFUND_PMS          = ['cc_card', 'apmgw_expresscheckout'];
    
    private $restApiIntUrl           = 'https://ppp-test.safecharge.com/ppp/api/v1/';
    private $restApiProdUrl          = 'https://secure.safecharge.com/ppp/api/v1/';
    private $saveLogs                = true;
    private $sandboxMode             = true;
    private $nuveiSourceApplication  = 'Shopwre_Plugin';
    private $traceId;
    private $systemConfigService;
    
    private $devices     = array('iphone', 'ipad', 'android', 'silk', 'blackberry', 'touch', 'linux', 'windows', 'mac');
    private $browsers    = array('ucbrowser', 'firefox', 'chrome', 'opera', 'msie', 'edge', 'safari', 'blackberry', 'trident');
    
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService  = $systemConfigService;
        $nuveiMode                  = $systemConfigService->get('SwagNuveiCheckout.config.nuveiMode');
        $this->sandboxMode          = 'live' == $nuveiMode ? false : true;
        $this->saveLogs             = (bool) $systemConfigService->get('SwagNuveiCheckout.config.nuveiSaveLogs');
    }

    /**
     * Prepare and save log.
     *
     * @param mixed $data       Data to save. Can be simple message also.
     * @param string $title     Title or description.
     * @param string $log_level Company Log level.
     *
     * @return void
     */
    public function createLog($data, $title = '', $log_level = 'TRACE')
    {
        if (!$this->saveLogs) {
            return;
        }
        
        $logsPath   = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) 
            . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR;
        
        if (!is_dir($logsPath)) {
            throw new \Exception('Logs path not found: ' . $logsPath);
        }
        
        $d          = $data;
        $string     = '';
        
        if (is_bool($data)) {
            $d = $data ? 'true' : 'false';
        } elseif (is_string($data) || is_numeric($data)) {
            $d = $data;
        } elseif ('' === $data) {
            $d = 'Data is Empty.';
        } elseif (is_array($data)) {
            // do not log accounts if on prod
            if (!$this->sandboxMode) {
                if (isset($data['userAccountDetails']) && is_array($data['userAccountDetails'])) {
                    $data['userAccountDetails'] = 'account details';
                }
                if (isset($data['userPaymentOption']) && is_array($data['userPaymentOption'])) {
                    $data['userPaymentOption'] = 'user payment options details';
                }
                if (isset($data['paymentOption']) && is_array($data['paymentOption'])) {
                    $data['paymentOption'] = 'payment options details';
                }
            }
            // do not log accounts if on prod

            if (!empty($data['paymentMethods']) && is_array($data['paymentMethods'])) {
                $data['paymentMethods'] = json_encode($data['paymentMethods']);
            }
            if (!empty($data['Response data']['paymentMethods'])
                && is_array($data['Response data']['paymentMethods'])
            ) {
                $data['Response data']['paymentMethods'] = json_encode($data['Response data']['paymentMethods']);
            }

            if (!empty($data['plans']) && is_array($data['plans'])) {
                $data['plans'] = json_encode($data['plans']);
            }

            $d = $this->sandboxMode ? json_encode($data, JSON_PRETTY_PRINT) : json_encode($data);
        } elseif (is_object($data)) {
            $d = $this->sandboxMode ? json_encode($data, JSON_PRETTY_PRINT) : json_encode($data);
        } else {
            $d = $this->sandboxMode ? json_encode($data, JSON_PRETTY_PRINT) : json_encode($data);
        }
        
        $tab            = '    ';
        $utimestamp     = microtime(true);
        $timestamp      = floor($utimestamp);
        $milliseconds   = round(($utimestamp - $timestamp) * 1000000);
        $record_time    = date('Y-m-d') . 'T' . date('H:i:s') . '.' . $milliseconds . date('P');
        
        if (!$this->traceId) {
            $this->traceId = bin2hex(random_bytes(16));
        }
        
        $source_file_name   = '';
        $member_name        = '';
        $source_line_number = '';
        
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        if (!empty($backtrace)) {
            if (!empty($backtrace[0]['file'])) {
                $file_path_arr  = explode(DIRECTORY_SEPARATOR, $backtrace[0]['file']);
                
                if (!empty($file_path_arr)) {
                    $source_file_name = end($file_path_arr) . '|';
                }
            }
            
//            if(!empty($backtrace[0]['function'])) {
//                $member_name = $backtrace[0]['function'] . '|';
//            }
            
            if (!empty($backtrace[0]['line'])) {
                $source_line_number = $backtrace[0]['line'] . $tab;
            }
        }
        
        $string .= $record_time . $tab
            . $log_level . $tab
            . $this->traceId . $tab
//            . 'Checkout ' . $this->config->getSourcePlatformField() . '|'
            . $source_file_name
            . $member_name
            . $source_line_number;
        
        if (!empty($title)) {
            if (is_string($title)) {
                $string .= $title . $tab;
            } else {
                if ($this->sandboxMode) {
                    $string .= "\r\n" . json_encode($title, JSON_PRETTY_PRINT) . "\r\n";
                } else {
                    $string .= json_encode($title) . $tab;
                }
            }
        }

        $string         .= $d . "\r\n\r\n";
        $log_file_name  = 'nuvei-' . date('Y-m-d');
        
        try {
//            switch ($this->config->isDebugEnabled(true)) {
//                case 3: // save log file per days
//                    $log_file_name = 'Nuvei-' . date('Y-m-d');
//                    break;
//                
//                case 2: // save single log file
//                    $log_file_name = 'Nuvei';
//                    break;
//                
//                case 1: // save both files
//                    $log_file_name = 'Nuvei';
//                    $this->saveFile($logsPath, date('Y-m-d') . '.log', $string, FILE_APPEND);
//                    break;
//                
//                default:
//                    return;
//            }
            
            return file_put_contents($logsPath . $log_file_name . '.log', $string, FILE_APPEND);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * Call the REST API and get response.
     * 
     * @param string $method        The endpoint method we call.
     * @param array $method_params  The request params.
     * @param array $checsum_params The hash params.
     */
    public function callRestApi($method, array $method_params, array $checsum_params)
    {
        if(empty($method)) {
            $msg = 'callRestApi Error - the passed method can not be empty.';
            
			$this->createLog($msg);
			return [
                'status'    => 'ERROR',
                'message'   => $msg,
            ];
		}
		
        $method_params = $this->validateParameters($method_params);
        // on error return the it
        if (isset($method_params['status'])) {
            return $method_params;
        }
        
        $endpoint       = $this->getEndPointBase() . $method . '.do';
        $time           = date('YmdHis', time());
        $json_path      = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR;
        $json_data      = json_decode(file_get_contents($json_path . 'composer.json'), true);
        $webMasterId    = 'ShopWare 6; Plugin v' . $json_data['version'];
        $site_url       = $this->getSiteUrl();
        
        
        // set here some of the mandatory parameters
        $params = array_merge(
            [
                'merchantId'        => $this->systemConfigService->get('SwagNuveiCheckout.config.nuveiMerchantId'),
                'merchantSiteId'    => $this->systemConfigService->get('SwagNuveiCheckout.config.nuveiMerchantSiteId'),
                'clientRequestId'   => $time . '_' . uniqid(),
                
                'timeStamp'         => $time,
                'deviceDetails'     => $this->getDeviceDetails(),
                'webMasterId'       => $webMasterId,
                'sourceApplication' => $this->nuveiSourceApplication,
                'url'               => $site_url . '/nuvei_dmn/', // a custom parameter for the checksum
            ],
            $method_params
        );
        
        // add few more params
        $params['merchantDetails']['customField1']  = $webMasterId;
        $params['urlDetails']['notificationUrl']    = $params['url'];
        $params['urlDetails']['backUrl']            = $site_url . '//checkout/confirm';
        
        // add more urlDetails if need them
        if ((bool) $this->systemConfigService->get('SwagNuveiCheckout.config.nuveiAutoCloseApmPopup')
            && in_array($method, ['openOrder', 'updateOrder'])
        ) {
            $params['urlDetails']['successUrl']
                = $params['urlDetails']['failureUrl']
                = $params['urlDetails']['pendingUrl']
                = self::NUVEI_POP_AUTO_CLOSE_URL;
        }
        
        // calculate the checksum
        $concat = '';
        
        foreach($checsum_params as $key) {
            if(!isset($params[$key])) {
                $msg = 'Error - Missing a mandatory parameter for the Checksum.';
                
                $this->createLog(
                    array(
                        'request url'   => $endpoint,
                        'params'        => $params,
                        'missing key'   => $key,
                    ),
                    $msg
                );
                
                return array(
                    'status'    => 'ERROR',
                    'message'   => $msg,
                );
            }
            
            $concat .= $params[$key];
        }
        
        $params['checksum'] = hash(
            $this->systemConfigService->get('SwagNuveiCheckout.config.nuveiHash'),
            $concat . $this->systemConfigService->get('SwagNuveiCheckout.config.nuveiSecretKey')
        );
        // /calculate the checksum
        
        unset($params['url']);
        
        $json_post = json_encode($params);
        
        try {
            $header =  array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_post),
            );
            
            // create cURL post
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $resp = curl_exec($ch);
			curl_close($ch);
            
            $resp_arr = json_decode($resp, true);
            
            $this->createLog(
                [
                    'Request URL'       => $endpoint,
                    'Request params'    => $params,
                    'Request response'  => $resp_arr,
                ],
                'Request/Response data'
            );
			
            if(empty($resp_arr) || !$resp) {
                return array(
                    'status'    => 'ERROR',
                    'message'   => 'Response error.'
                );
            }
			
			return $resp_arr;
        }
        catch(Exception $e) {
            $this->createLog(
                [
                    'Request URL'       => $endpoint,
                    'Request params'    => $params,
                    'Exception'         => $e->getMessage()
                ],
                'Request/Response data'
            );
            
			return array(
				'status'    => 'ERROR',
				'message'   => 'Exception ERROR when call REST API: ' . $e->getMessage()
			);
        }
    }
    
    /**
     * We get 'Status' or 'status', so check for both.
     * 
     * @param array $params Optional array with data to search into.
     * @return string
     */
    public function getRequestStatus(array $params = []): string
    {
        if(!empty($params)) {
            if(isset($params['Status'])) {
                return $params['Status'];
            }

            if(isset($params['status'])) {
                return $params['status'];
            }
        }
        
        if(isset($_REQUEST['Status'])) {
            return $_REQUEST['Status'];
        }

        if(isset($_REQUEST['status'])) {
            return $_REQUEST['status'];
        }
        
        return '';
    }
    
    /**
     * Get browser and device based on HTTP_USER_AGENT.
     * 
     * @return array $device_details
     */
    private function getDeviceDetails()
    {
        $device_details = array(
			'deviceType'    => 'UNKNOWN', // DESKTOP, SMARTPHONE, TABLET, TV, and UNKNOWN
			'deviceName'    => 'UNKNOWN',
			'deviceOS'      => 'UNKNOWN',
			'browser'       => 'UNKNOWN',
			'ipAddress'     => '0.0.0.0',
		);
		
		if(empty($_SERVER['HTTP_USER_AGENT'])) {
			$device_details['Warning'] = 'User Agent is empty.';
			return $device_details;
		}
		
		$user_agent = strtolower(filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_STRING));
		
		if (empty($user_agent)) {
			$device_details['Warning'] = 'Probably the merchant Server has problems with PHP filter_var function!';
			return $device_details;
		}
		
		$device_details['deviceName'] = $user_agent;
		

        foreach ($this->devices as $d) {
            if (strstr($user_agent, $d) !== false) {
                if(in_array($d, array('linux', 'windows', 'macintosh'), true)) {
                    $device_details['deviceType'] = 'DESKTOP';
                } else if('mobile' === $d) {
                    $device_details['deviceType'] = 'SMARTPHONE';
                } else if('tablet' === $d) {
                    $device_details['deviceType'] = 'TABLET';
                } else {
                    $device_details['deviceType'] = 'TV';
                }

                break;
            }
        }

        if (!empty($this->devices) && is_array($this->devices)) {
            foreach ($this->devices as $d) {
                if (strstr($user_agent, $d) !== false) {
                    $device_details['deviceOS'] = $d;
                    break;
                }
            }
		}

        foreach ($this->browsers as $b) {
            if (strstr($user_agent, $b) !== false) {
                $device_details['browser'] = $b;
                break;
            }
        }

        // get ip
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip_address = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
		}
		if (empty($ip_address) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip_address = filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP);
		}
		if (empty($ip_address) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip_address = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP);
		}
		if (!empty($ip_address)) {
			$device_details['ipAddress'] = (string) $ip_address;
		}
            
        return $device_details;
    }
    
    /**
     * Try to get Site URL.
     * 
     * @return string
     */
    private function getSiteUrl()
    {
        $url = '';
        
        if (!empty($_SERVER['APP_URL']) && filter_var($_SERVER['APP_URL'], FILTER_VALIDATE_URL)) {
            return $_SERVER['APP_URL'];
        }
        
        if (empty($_SERVER['HTTP_HOST'])) {
            return $url;
        }
        
        $url = $_SERVER['HTTP_HOST'] . '/';
        
        if (!empty($_SERVER['HTTPS']) && 'on' == strtolower($_SERVER['HTTPS'])) {
            $url = 'https://' . $url;
        }
        elseif (!empty($_SERVER['REQUEST_SCHEME']) && 'https' == strtolower($_SERVER['REQUEST_SCHEME'])) {
            $url = 'https://' . $url;
        }
        
        return $url;
    }
    
    /**
	 * Get the URL to the endpoint, without the method name, based on the site mode.
	 * 
	 * @return string
	 */
	private function getEndPointBase()
    {
		if ($this->sandboxMode) {
			return $this->restApiIntUrl;
		}
		
		return $this->restApiProdUrl;
	}
    
    /**
	 * Validate some of the parameters in the request by predefined criteria.
	 * 
	 * @param array $params
	 * @return array
	 */
	private function validateParameters($params)
    {
		// directly check the mails
		if (isset($params['billingAddress']['email'])) {
			if (!filter_var($params['billingAddress']['email'], self::NUVEI_PARAMS_VALIDATION_EMAIL['flag'])) {
				$msg = 'The parameter Billing Address Email is not valid.';
                
                $this->createLog($msg);
                
				return array(
					'status'    => 'ERROR',
					'message'   => $msg,
				);
			}
			
			if (strlen($params['billingAddress']['email']) > self::NUVEI_PARAMS_VALIDATION_EMAIL['length']) {
				$msg = 'The parameter Billing Address Email must be maximum '
                    . self::NUVEI_PARAMS_VALIDATION_EMAIL['length'] . ' symbols.';
                
                $this->createLog($msg);
                
                return array(
					'status' => 'ERROR',
					'message' => $msg
				);
			}
		}
		
		if (isset($params['shippingAddress']['email'])) {
			if (!filter_var($params['shippingAddress']['email'], self::NUVEI_PARAMS_VALIDATION_EMAIL['flag'])) {
				$msg = 'The parameter Shipping Address Email is not valid.';
                
                $this->createLog($msg);
                
                return array(
					'status' => 'ERROR',
					'message' => $msg
				);
			}
			
			if (strlen($params['shippingAddress']['email']) > self::NUVEI_PARAMS_VALIDATION_EMAIL['length']) {
				$msg = 'The parameter Shipping Address Email must be maximum '
                    . self::NUVEI_PARAMS_VALIDATION_EMAIL['length'] . ' symbols.';
                
                $this->createLog($msg);
                
                return array(
					'status' => 'ERROR',
					'message' => $msg
				);
			}
		}
		// directly check the mails END
		
		foreach ($params as $key1 => $val1) {
			if (!is_array($val1) && !empty($val1) && array_key_exists($key1, self::NUVEI_PARAMS_VALIDATION)) {
				$new_val = $val1;
				
				if (mb_strlen($val1) > self::NUVEI_PARAMS_VALIDATION[$key1]['length']) {
					$new_val = mb_substr($val1, 0, self::NUVEI_PARAMS_VALIDATION[$key1]['length']);
				}
				
				$params[$key1] = filter_var($new_val, self::NUVEI_PARAMS_VALIDATION[$key1]['flag']);
				
				if (!$params[$key1]) {
					$params[$key1] = 'The value is not valid.';
				}
			} elseif (is_array($val1) && !empty($val1)) {
				foreach ($val1 as $key2 => $val2) {
					if (!is_array($val2) && !empty($val2) && array_key_exists($key2, self::NUVEI_PARAMS_VALIDATION)) {
						$new_val = $val2;

						if (mb_strlen($val2) > self::NUVEI_PARAMS_VALIDATION[$key2]['length']) {
							$new_val = mb_substr($val2, 0, self::NUVEI_PARAMS_VALIDATION[$key2]['length']);
						}

						$params[$key1][$key2] = filter_var($new_val, self::NUVEI_PARAMS_VALIDATION[$key2]['flag']);
						
						if (!$params[$key1][$key2]) {
							$params[$key1][$key2] = 'The value is not valid.';
						}
					}
				}
			}
		}
		
		return $params;
	}
    
}
