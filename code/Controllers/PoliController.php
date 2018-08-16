<?php
use SaltedHerring\Debugger;
use SaltedHerring\Utilities;
use Leochenftw\eCashier\API\Poli;
class PoliController extends eCashierController
{
    public function index($request)
    {
        if (!$request->isPost()) {
            SS_Log::log('POLI:: get back', SS_Log::ERR);
            if ($token = $request->getVar('token')) {
                $result =   $this->handle_postback($token);
                return $this->route($result);
            }
        }

        SS_Log::log('POLI:: post back', SS_Log::ERR);

        $token = $request->postVar('Token');

        if (empty($token)) {
            $token = $request->getVar('token');
        }

        if (empty($token)) {
            return $this->httpError(400, 'Token is missing');
        }

        $this->handle_postback($token);
    }

    protected function route($result)
    {
        $state          =   $result['state'];
        $orderID        =   $result['order_id'];
        $url            =   [
                                'url'       =>  Config::inst()->get('eCashier', 'MerchantSettings')['MerchantHomepageURL'],
                                'state'     =>  strtolower($state)
                            ];

        $url            =   Utilities::LinkThis($url, 'order_id', $orderID);

        return $this->redirect($url);
    }

    protected function handle_postback($data)
    {
        $result         =   Poli::fetch($data);

        if ($Order = $this->getOrder($result['MerchantReference'])) {
            // Debugger::inspect($Order);
            if ($payments = $Order->Payments()) {
                $payment = $payments->filter(array('MerchantReference' => $result['MerchantReference'], 'TransacID' => $result['TransactionRefNo']))->first();
            }

            if ($Order->isOpen) {

                if (!empty($Order->RecursiveFrequency)) {
                    $today = date("Y-m-d 00:00:00");
                    // $Order->ValidUntil = date('Y-m-d', strtotime($today. ' + ' . $Order->RecursiveFrequency . ' days'));
                }

                if ($result['TransactionStatusCode'] == 'Completed') {
                    $Order->isOpen = false;
                    $Order->write();
                }
            }

            if (empty($payment)) {
                $payment                    =   new PoliPayment();
                $payment->MerchantReference =   $Order->MerchantReference;
                $payment->PaidByID          =   $Order->CustomerID;
                $payment->IP                =   $Order->PaidFromIP;
                $payment->ProxyIP           =   $Order->PaidFromProxyIP;
                $payment->Amount->Currency  =   $Order->Amount->Currency;
                $payment->Amount->Amount    =   $result['AmountPaid'];
                $payment->OrderID           =   $Order->ID;
                $payment->notify($result);
            }

            $Order->onPaymentUpdate($payment->Status);
            return $this->route_data($payment->Status, $Order->ID);
        }

        return $this->httpError(400, 'Order not found');
    }
}
