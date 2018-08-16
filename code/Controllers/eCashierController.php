<?php
use SaltedHerring\Debugger;
use SaltedHerring\Utilities;

class eCashierController extends ContentController
{
    protected function route($result)
    {
        user_error("Please implement route() on $this->class", E_USER_ERROR);
    }

    protected function route_data($state = 'Failed', $order_id = null)
    {
        return  [
                    'state'         =>  $state,
                    'order_id'      =>  $order_id
                ];
    }

    protected function handle_postback($data)
    {
        user_error("Please implement handle_postback() on $this->class", E_USER_ERROR);
    }

    protected function getOrder($merchant_reference)
    {
        $OrderClass                 =   Config::inst()->get('eCashier', 'DefaultOrderClass');
        return $OrderClass::get()->filter(['MerchantReference' => $merchant_reference])->first();
    }
}
