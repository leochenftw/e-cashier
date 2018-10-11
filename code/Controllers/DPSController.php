<?php
use SaltedHerring\Debugger;
use SaltedHerring\Utilities;
use Leochenftw\eCashier\API\DPS;
class DPSController extends eCashierController
{
    public function index($request)
    {
        if (!$request->isPost()) {
            SS_Log::log('DPS:: get back', SS_Log::ERR);
            if ($token = $request->getVar('result')) {
                $result = $this->handle_postback($token);
                return $this->route($result);
            }
        }

        SS_Log::log('DPS:: post back', SS_Log::ERR);

        $token = $request->postVar('result');

        if (empty($token)) {
            $token = $request->getVar('result');
        }

        if (empty($token)) {
            return $this->httpError(400, 'Token is missing');
        }

        $this->handle_postback($token);
    }

    protected function route($result)
    {
        $state                              =   $result['state'];
        $orderID                            =   $result['order_id'];
        $url                                =   [
                                                    'url'       =>  Config::inst()->get('eCashier', 'MerchantSettings')['SuccessURL'],
                                                    'state'     =>  strtolower($state)
                                                ];

        $url                                =   Utilities::LinkThis($url, 'order_id', $orderID);

        return $this->redirect($url);
    }

    protected function handle_postback($data)
    {
        $result             =   DPS::fetch($data);

        if ($Order = $this->getOrder($result['MerchantReference'])) {
            // Debugger::inspect($Order);
            if ($payments = $Order->Payments()->exists()) {
                $payment                    =   $Order->Payments()->filter(['MerchantReference' => $result['MerchantReference']])->first();
            }

            if (empty($payment)) {
                $payment                    =   new DPSPayment();
                $payment->MerchantReference =   $Order->MerchantReference;
                $payment->PaidByID          =   $Order->CustomerID;
                $payment->IP                =   $Order->PaidFromIP;
                $payment->ProxyIP           =   $Order->PaidFromProxyIP;
                $payment->Amount->Currency  =   $Order->Amount->Currency;
                $payment->Amount->Amount    =   $result['AmountSettlement'];
                $payment->OrderID           =   $Order->ID;
            }

            $payment->notify($result);

            $Order->onPaymentUpdate($payment->Status);
            return $this->route_data($payment->Status, $Order->ID);
        }

        return $this->httpError(400, 'Order not found');
    }
}
