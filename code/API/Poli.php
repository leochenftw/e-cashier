<?php
namespace Leochenftw\eCashier\API;

use SaltedHerring\Debugger;

class Poli
{
    public static function initiate($price, $ref, $order_class = null)
    {
        //Debugger::inspect($order);
        /*
        SaltedPayment::get_merchant_setting('MerchantHomepageURL');
        SaltedPayment::get_merchant_setting('SuccessURL');
        SaltedPayment::get_merchant_setting('FailureURL');
        SaltedPayment::get_merchant_setting('CancellationURL');
        SaltedPayment::get_merchant_setting('NotificationURL');
        */
        $gateway_endpoint   =   \Config::inst()->get('eCashier', 'API')['POLi'] . '/Initiate';
        $settings           =   \Config::inst()->get('eCashier', 'GatewaySettings')['POLi'];

        $cert_path          =   $settings['CERT'];
        $client_code        =   $settings['CLIENTCODE'];
        $auth_code          =   $settings['AUTHCODE'];
        $returnurl          =   \Director::absoluteBaseURL() . 'pg-payment/poli-complete';

        // "SuccessURL":"' . SaltedPayment::get_merchant_setting('SuccessURL') . '",
        // "FailureURL":"' . SaltedPayment::get_merchant_setting('FailureURL') . '",
        // "CancellationURL":"' . SaltedPayment::get_merchant_setting('CancellationURL') . '",
        // "NotificationURL":"' . SaltedPayment::get_merchant_setting('NotificationURL') . '"

        $json_builder       =   '{
                                    "Amount":"' . $price . '",
                                    "CurrencyCode":"NZD",
                                    "MerchantData": "' . (!empty($order_class) ? $order_class : \Config::inst()->get('eCashier', 'DefaultOrderClass')) . '",
                                    "MerchantReference":"' . $ref . '",
                                    "MerchantHomepageURL":"' . $returnurl . '",
                                    "SuccessURL":"' . $returnurl . '",
                                    "FailureURL":"' . $returnurl . '",
                                    "CancellationURL":"' . $returnurl . '",
                                    "NotificationURL":"' . $returnurl . '"
                                }';

        $auth               =   base64_encode($client_code . ':' . $auth_code);

        $header             =   [
                                    'Content-Type: application/json',
                                    'Authorization: Basic ' . $auth
                                ];

         $ch = curl_init($gateway_endpoint);

         //See the cURL documentation for more information: http://curl.haxx.se/docs/sslcerts.html
         //We recommend using this bundle: https://raw.githubusercontent.com/bagder/ca-bundle/master/ca-bundle.crt

         curl_setopt( $ch, CURLOPT_CAINFO, $cert_path);
         curl_setopt( $ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
         curl_setopt( $ch, CURLOPT_HTTPHEADER, $header);
         curl_setopt( $ch, CURLOPT_HEADER, 0);
         curl_setopt( $ch, CURLOPT_POST, 1);
         curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_builder);
         curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0);
         curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

         $response          =   curl_exec( $ch );

         curl_close ($ch);

         return $response;
    }

    public static function process($price, $ref, $order_class = null)
    {
        \SS_Log::log("POLi::::\n" . 'asking?', \SS_Log::ERR);
        $response           =   self::initiate($price, $ref, $order_class);
        return json_decode($response, true);;
    }

    public static function fetch($token)
    {
        //EhnCujLNQuGDeRigzZyOpWp3dxM0y29K

        /*$token = $_POST["Token"];
        if(is_null($token)) {
            $token = $_GET["token"];
        }*/

        //$token = 'EhnCujLNQuGDeRigzZyOpWp3dxM0y29K';

        $gateway_endpoint   =   \Config::inst()->get('eCashier', 'API')['POLi'];
        $settings           =   \Config::inst()->get('eCashier', 'GatewaySettings')['POLi'];

        $cert_path          =   $settings['CERT'];
        $client_code        =   $settings['CLIENTCODE'];
        $auth_code          =   $settings['AUTHCODE'];

        $auth               =   base64_encode($client_code . ':' . $auth_code);
        $header             =   ['Authorization: Basic '.$auth];

        $ch                 =   curl_init($gateway_endpoint . '?token=' . $token);
        $ch                 =   curl_init("$gateway_endpoint/GetTransaction?token=" . urlencode($token));

        //See the cURL documentation for more information: http://curl.haxx.se/docs/sslcerts.html
        //We recommend using this bundle: https://raw.githubusercontent.com/bagder/ca-bundle/master/ca-bundle.crt

        curl_setopt( $ch, CURLOPT_CAINFO, $cert_path );
        curl_setopt( $ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2 );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_POST, 0 );
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

        $response           =   curl_exec( $ch );

        curl_close( $ch );

        $json               =   json_decode( $response, true );

        return $json;
    }
}
