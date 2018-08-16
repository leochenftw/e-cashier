<?php
use SaltedHerring\Grid;
use Leochenftw\eCashier\API\Poli;
/**
 * Description
 *
 * @package silverstripe
 * @subpackage mysite
 */
class PGOrder extends DataObject
{
    /**
     * Database fields
     * @var array
     */
    private static $db = [
        'isOpen'                =>  'Boolean', //auto fill
        'Amount'                =>  'Money', //Currency part is auto fill
        'MerchantReference'     =>  'Varchar(64)', //auto fill
        'MerchantSession'       =>  'Varchar(64)', //auto fill
        'PaidFromIP'            =>  'Varchar', //auto fill
        'PaidFromProxyIP'       =>  'Varchar', //auto fill
        'PayDate'               =>  'Date',
        'RecursiveFrequency'    =>  'Int',
        'ValidUntil'            =>  'Date'
    ];

    /**
     * Default sort ordering
     * @var string
     */
    private static $default_sort = [
        'ID'                    =>  'DESC'
    ];

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'Customer'              =>  'Member', //auto fill
        // 'WillbePaidByCard'      =>  'StoredCreditcard'
    ];

    /**
     * Defines summary fields commonly used in table columns
     * as a quick overview of the data for this dataobject
     * @var array
     */
    private static $summary_fields = [
        'getStatus'             =>  'Open / Close',
        'PayDateDisplay'        =>  'Pay date',
        'Amount'                =>  'Amount',
        'OutstandingBalance'    =>  'Outstanding Balance',
        'getCustomerFullName'   =>  'Customer'
    ];

    /**
     * CMS Fields
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields                 =   parent::getCMSFields();

        $fields->addFieldsToTab(
            'Root.MerchantInfo',
            [
                LiteralField::create(
                    'isOpen',
                    '<div id="Form_ItemEditForm_isOpen_Holder" class="field readonly text">
                        <label class="left" for="Form_ItemEditForm_isOpen">Paid?</label>
                        <div class="middleColumn">
                            <span id="Form_ItemEditForm_isOpen" class="readonly text">' . ($this->isOpen ? 'No' : 'Yes') . '</span>
                        </div>
                    </div>'
                ),
                $fields->fieldByName('Root.Main.Amount'),
                $fields->fieldByName('Root.Main.MerchantReference'),
                $fields->fieldByName('Root.Main.MerchantSession'),
                $fields->fieldByName('Root.Main.PaidFromIP'),
                $fields->fieldByName('Root.Main.PaidFromProxyIP'),
                $fields->fieldByName('Root.Main.PayDate'),
                $fields->fieldByName('Root.Main.RecursiveFrequency'),
                $fields->fieldByName('Root.Main.ValidUntil')
            ]
        );

        if ($member = Member::currentUser()) {
            if ($this->exists()) {
                $fields->addFieldToTab(
                    'Root.Payments',
                    $member->inGroup('administrators') ?
                    Grid::make('Payments', 'Payments', $this->Payments(), false) :
                    Grid::make('Payments', 'Payments', $this->Payments(), false, 'GridFieldConfig_RecordViewer')
                );
            }
        }

        return $fields;
    }

    public function onBeforeWrite() {
        parent::onBeforeWrite();
        if (empty($this->MerchantReference)) {
            $created = new DateTime('NOW');
            $timestamp = $created->format('YmdHisu');
            $this->MerchantReference = strtolower(sha1(md5($timestamp.'-'.session_id())));
        }
    }

    public function getCustomerFullName()
    {
        if ($this->Customer()->exists()) {
            return $this->Customer()->FirstName . ' ' . $this->Customer()->Surname;
        }

        return '- visitor -';
    }

    /**
     * Event handler called before deleting from the database.
     */
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        if ($payments = $this->Payments()) {
            foreach ($payments as $payment) {
                $payment->delete();
            }
        }
    }

    public function populateDefaults()
    {
        parent::populateDefaults();
        $this->isOpen               =   true;
        $this->Amount->Currency     =   Config::inst()->get('eCashier', 'DefaultCurrency');
        $this->CustomerID           =   Member::currentUserID();
        $created                    =   new DateTime('NOW');
        $timestamp                  =   $created->format('YmdHisu');
        $this->MerchantReference    =   strtolower(sha1(md5($timestamp.'-'.session_id())));
    }

    public function AjaxPay($provider)
    {
        switch (strtolower($provider))
        {
            case 'poli':
                $result             =   Poli::process($this->Amount->Amount, $this->MerchantReference);

                if (!empty($result['Success']) && !empty($result['NavigateURL'])) {
                    $pay_link       =   $result['NavigateURL'];
                } elseif (!empty($result['ErrorCode']) && !empty($result['ErrorMessage'])) {
                    SS_Log::log("POLi::::\n" . serialize($result), SS_Log::ERR);
                }

                break;

            case 'paystation':

                // $pay_link           =   Paystation::process($this->Amount->Amount, $this->MerchantReference, $this->MerchantSession, $setup_future_payment);
                // if (empty($pay_link)) {
                //     SS_Log::log("Paystation::::\n" . serialize($pay_link), SS_Log::ERR);
                // }

                break;

            default:

                break;
        }

        return !empty($pay_link) ? $pay_link : null;
    }

    public function Pay($payment_method, $setup_future_payment = false)
    {
        $this->setClientIP();
        $this->MerchantSession = sha1(session_id() . '-' . round(microtime(true) * 1000));
        $pay_link = null;

        switch (strtolower($payment_method)) {

            case 'poli':
                if ($setup_future_payment) {
                    return $this->httpError(400, 'POLi does not support future payment');
                }
                $result = Poli::process($this->Amount->Amount, $this->MerchantReference);
                if (!empty($result['Success']) && !empty($result['NavigateURL'])) {
                    $pay_link = $result['NavigateURL'];
                } elseif (!empty($result['ErrorCode']) && !empty($result['ErrorMessage'])) {
                    SS_Log::log("POLi::::\n" . serialize($result), SS_Log::ERR);
                }

                break;

            case 'paystation':

                $pay_link = Paystation::process($this->Amount->Amount, $this->MerchantReference, $this->MerchantSession, $setup_future_payment);
                if (empty($pay_link)) {
                    SS_Log::log("Paystation::::\n" . serialize($pay_link), SS_Log::ERR);
                }

                break;

            default:

                break;
        }

        if (!empty($pay_link)) {
            $this->write();
            if ($controller = Controller::curr()) {
                if ($controller->request->isAjax()) {
                    return $pay_link;
                }
                return $controller->redirect($pay_link);
            }

            return true;
        }

        return Controller::curr()->httpError(400, 'Payment gateway error');
    }

    public function onPaymentUpdate($success)
    {
        user_error("Please implement onPaymentUpdate() on $this->class", E_USER_ERROR);
    }

    public function onValidDurationRunsOut()
    {
        user_error("Please implement onValidDurationRunsOut() on $this->class", E_USER_ERROR);
    }

    /**
     * Set the IP address of the user to this payment record.
     * This isn't perfect - IP addresses can be hidden fairly easily.
     */
    protected function setClientIP()
    {
        $proxy = null;
        $ip = null;

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = null;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $proxy = $ip;
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        // Only set the IP and ProxyIP if none currently set
        if (!$this->PaidFromIP) {
            $this->PaidFromIP = $ip;
        }
        if (!$this->PaidFromProxyIP) {
            $this->PaidFromProxyIP = $proxy;
        }
    }

    public function isFuturePayment()
    {
        if (empty($this->PayDate)) {
            return false;
        }

        $now = new DateTime('NOW');
        $paydate = new DateTime($this->PayDate);

        return $now < $paydate;
    }

    public function getStatus()
    {
        return $this->isOpen ? 'Open' : 'Close';
    }

    public function getSuccessPayment()
    {
        if ($payments = $this->Payments())
        {
            return $payments->filter(array('Status' =>  'Success'))->first();
        }

        return null;
    }

    public function OutstandingBalance()
    {
        $amount =   $this->Amount->Amount;

        if ($payments = $this->Payments())
        {
            if ($payment = $payments->filter(['Status' => 'Success'])->first()) {
                return '$' . number_format($amount - $payment->Amount->Amount, 2, '.', ',');
            }
        }

        return '$' . number_format($amount, 2, '.', ',');;;
    }

    public function PayDateDisplay()
    {
        if ($this->isOpen) {
            return '- not yet paid -';
        }

        return !empty($this->PayDate) ? $this->PayDate : $this->Created;
    }

    //static functions

    public static function prepare_order()
    {
        $OrderClass =   Config::inst()->get('eCashier', 'DefaultOrderClass');
        $Order      =   $OrderClass::get()->filter(array('isOpen' => true))->where('PayDate IS NULL')->first();

        return !empty($Order) ? $Order : $OrderClass::create()->write();

    }
}
