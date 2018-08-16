<?php

namespace Leochenftw\eCashier\API;

use SilverStripe\Control\Director;
use SaltedHerring\Debugger;

class DPS
{
    public static function initiate($amount, $ref)
    {
        $endpoint   =   \Config::inst()->get('eCashier', 'API')['DPS'];
        $settings   =   \Config::inst()->get('eCashier', 'GatewaySettings')['DPS'];
        $id         =   $settings['ID'];
        $key        =   $settings['Key'];
        $currency   =   \Config::inst()->get('eCashier', 'DefaultCurrency');
        $success    =   \Config::inst()->get('eCashier', 'MerchantSettings')['SuccessURL'];
        $fail       =   \Config::inst()->get('eCashier', 'MerchantSettings')['FailureURL'];

        $request    =   '<GenerateRequest>
                            <PxPayUserId>' . $id . '</PxPayUserId>
                            <PxPayKey>' . $key . '</PxPayKey>
                            <TxnType>Purchase</TxnType>
                            <AmountInput>' . $amount . '</AmountInput>
                            <CurrencyInput>' . $currency . '</CurrencyInput>
                            <MerchantReference>' . $ref . '</MerchantReference>
                            <UrlSuccess>' . \Director::absoluteBaseURL() . 'pg-payment/dps-complete</UrlSuccess>
                            <UrlFail>' . \Director::absoluteBaseURL() . 'pg-payment/dps-complete</UrlFail>
                        </GenerateRequest>';

        // Debugger::inspect($request);

        $ch         =   curl_init($endpoint);
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);

        $response   =   curl_exec( $ch );

        curl_close ($ch);

        return $response;
    }

    public static function process($amount, $ref)
    {
        \SS_Log::log("DPS::::\n" . 'asking?', \SS_Log::ERR);
        $response   =   self::initiate($amount, $ref);
        $xml        =   simplexml_load_string($response);
        $json       =   json_encode($xml);

        return json_decode($json,TRUE);
    }

    public static function fetch($token)
    {
        //00001100050427380b31937aaa42d259
        $endpoint   =   \Config::inst()->get('eCashier', 'API')['DPS'];
        $settings   =   \Config::inst()->get('eCashier', 'GatewaySettings')['DPS'];
        $id         =   $settings['ID'];
        $key        =   $settings['Key'];

        $request    =   '<ProcessResponse>
                            <PxPayUserId>' . $id . '</PxPayUserId>
                            <PxPayKey>' . $key . '</PxPayKey>
                            <Response>' . $token . '</Response>
                        </ProcessResponse>';

        $ch         =   curl_init($endpoint);
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0);

        $response   =   curl_exec( $ch );

        curl_close ($ch);

        $xml        =   simplexml_load_string($response);
        $json       =   json_encode($xml);

        return json_decode($json,TRUE);;

    }
}
