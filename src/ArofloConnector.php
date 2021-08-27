<?php

namespace glasswalllab\arofloconnector;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use glasswalllab\arofloconnector\Models\ApiLog;

class ArofloConnector
{
    const CONTENT_TYPE = 'application/x-www-form-urlencoded';
    const ACCEPT_TYPE = 'text/json';

    const LIMIT = 500;

    private $page = 2; //Set to 2 as the first call is already completed

    public function CallAroflo($endpoint, $method, $parameters)
    {  
        $zone = urlencode($endpoint);
        $postxml = urlencode($parameters);
        $postfields = "zone=".$zone."&postxml=".$postxml;
        $requestType = strtoupper($method);
        $authorisation = "uencoded=".urlencode(config('ArofloConnector.uencode'))."&pencoded=".urlencode(config('ArofloConnector.applicationkey'))."&orgEncoded=".urlencode(config('ArofloConnector.orgencode'));
        $accept = self::ACCEPT_TYPE;
        $contentType = self::CONTENT_TYPE;
        $urlPath = '';
        date_default_timezone_set('UTC');
        $afdatetimeutc = date("Y-m-d\TH:i:s.u\Z", time());
        
        $payload = array();
        array_push($payload,$requestType);
        array_push($payload,$urlPath);
        array_push($payload,$accept);
        array_push($payload,$authorisation);
        array_push($payload,$afdatetimeutc);
        array_push($payload,$postfields);
        $payloadString = implode('+',$payload);

        $hmac = hash_hmac('sha512',$payloadString,config('ArofloConnector.secret'));

        $url = config('ArofloConnector.baseUrl');

        $header = [
            'Authentication: HMAC '.$hmac,
            'Authorization: '.$authorisation,
            'Accept: '.$accept,
            'afdatetimeutc: '.$afdatetimeutc,
            'Content-Type: '.$contentType
        ];

        dd($header);
        
        $responses = [];
        $method = strtoupper($method);
        
        try
        {
            //POST or PUT request - contains parameter data, no pagination required - return array
            if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {

                $log = ApiLog::create([
                    'resource' => $url,
                    'method' => $method,
                    'request' => json_encode($postxml),
                ]);

                $baseCall = Http::withHeaders($this->getHeaders())->retry(3, 500)->acceptJson();

                switch($method) {
                    case 'POST':
                        $responses = $baseCall->post($url,$parameters)->body();
                    break;

                    case 'PUT':
                        $responses = $baseCall->put($url,$parameters)->body();
                    break;

                    case 'PATCH':
                        $responses = $baseCall->patch($url,$parameters)->body();
                    break;

                    case 'DELETE':
                        $responses = $baseCall->delete($url,$parameters)->body();
                    break;
                }
                
                $log->response = $responses;
                $log->save();

            //GET request - check for pagination - return array    
            } elseif($method === 'GET') {
                
                $pageLimitParams = array('page' => 1,'limit' => self::LIMIT);
                $requestParams = array_merge($parameters,$pageLimitParams);
                
                $log_first_call = ApiLog::create([
                    'resource' => $url,
                    'method' => $method,
                    'request' => json_encode($requestParams),
                ]);

                $response = Http::withHeaders($this->getHeaders())->retry(3, 500)->acceptJson()->get($url,$requestParams)->body();

                $log_first_call->response = $response;
                $log_first_call->save();

                $json = json_decode($response);

                //Check total items, returned with first call
                if(!is_null($json)) {
                    $total = $json->Total;

                    if(isset($total)) {
                        $responses[] = $response;
                        if($total > self::LIMIT)
                        {   
                            //Start for loop at 2, as page 1 has already been retrieved - ceil = rounds up to nearest whole number             
                            for($i=$this->page;$i<=(ceil($total/self::LIMIT)); $i++)
                            {
                                $pageLimitParams = array('page' => $i,'limit' => self::LIMIT);
                                $requestParams = array_merge($parameters,$pageLimitParams);

                                $this->page = $i;
                                
                                $log_additional_call = ApiLog::create([
                                    'resource' => $url,
                                    'method' => $method,
                                    'request' => json_encode($requestParams),
                                ]);

                                $response = Http::withHeaders($this->getHeaders())->retry(3, 500)->acceptJson()->get($url,$requestParams)->body();
                                $responses[] = $response;

                                $log_additional_call->response = $response;
                                $log_additional_call->save();
                            }
                        }
                    }
                }
            }
            
            //return results as an array
            return $responses;

        } catch (RequestException $e) {
            if ($e->getCode() === 503) {
                // API limit exceeded
                sleep(5);
                return $this->CallAroflo($endpoint,$method,$body);
            }
            return $e;
        }
    }
}