<?php
use SaltedHerring\Debugger;
use Leochenf\eCashier\API\Poli;
class DPSPayment extends BasePaymentModel
{
    /**
     * Database fields
     * @var array
     */
    protected static $db = [
        'AmountSettlement'      =>  'Decimal',
        'AuthCode'              =>  'Varchar(32)',
        'CardName'              =>  'Varchar(32)',
        'CardNumber'            =>  'Varchar(32)',
        'DateExpiry'            =>  'Varchar(8)',
        'DpsTxnRef'             =>  'Varchar(128)',
        'CardHolderName'        =>  'Varchar(128)',
        'TxnMac'                =>  'Varchar(16)',
        'DateSettlement'        =>  'Varchar(8)',
        'ReCo'                  =>  'Varchar(8)'
    ];

    public function notify($data)
    {
        $arr                    =   static::$db;

        foreach ($arr as $key => $value)
        {
            if (!empty($data[$key])) {
                $this->$key     =   $data[$key];
            }
            // SS_Log::log($key . '::' . $this->$key, SS_Log::WARN);
        }
        
        $this->IP               =   $data['ClientInfo'];
        $this->TransacID        =   $data['TxnId'];
        $this->Status           =   $data['Success'] == '1' ? 'Success' : 'Failure';

        $this->write();
    }
}
/*
    TransacID
    Status
    Amount
    Message
    IP
    ProxyIP
    ExceptionError
    MerchantReference
    MerchantSession
    [AmountSettlement] => 100.00
    [AuthCode] => 170630
    [CardName] => Visa
    [CardNumber] => 411111........11
    [DateExpiry] => 0819
    [DpsTxnRef] => 0000000b048efa3b
    [Success] => 1
    [ResponseText] => APPROVED
    [CardHolderName] => TEST
    [CurrencySettlement] => NZD
    [ClientInfo] => 222.153.52.185
    [TxnId] => P0F281AEE2968AA1
*/
