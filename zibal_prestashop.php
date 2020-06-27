<?php
/**
 * @package    zibal_prestashop payment module
 * @author     Yahya Kangi
 * @copyright  2020  Zibal.ir
 * @link       https://docs.zibal.ir
 * @version    1.1
 */
if (!defined('_PS_VERSION_'))
	exit ;
class zibal_prestashop extends PaymentModule {

	private $_html = '';
	private $_postErrors = array();

	public function __construct() {

		$this->name = 'zibal_prestashop';
		$this->tab = 'payments_gateways';
		$this->version = '1.1';
		$this->author = 'Yahya Kangi';
		$this->currencies = true;
		$this->currencies_mode = 'radio';
		parent::__construct();
		$this->displayName = $this->l('درگاه پرداخت زیبال');
		$this->description = $this->l('درگاه پرداخت زیبال');
		$this->confirmUninstall = $this->l('ماژول درگاه پرداخت حذف شود؟');
		if (!sizeof(Currency::checkPaymentCurrencies($this->id)))
			$this->warning = $this->l('هیچ ارزی برای استفاده وجود ندارد.');
		$config = Configuration::getMultiple(array('zibal_prestashop_API'));
		if (!isset($config['zibal_prestashop_API']))
			$this->warning = $this->l('لطفا کد درگاه (مرچنت) را وارد نمایید.');

	}

	public function install() {
		if (!parent::install() || !Configuration::updateValue('zibal_prestashop_API', '') || !Configuration::updateValue('zibal_prestashop_LOGO', '') || !Configuration::updateValue('zibal_prestashop_HASH_KEY', $this->hash_key()) || !$this->registerHook('payment') || !$this->registerHook('paymentReturn'))
			return false;
		else
			return true;
	}

	public function uninstall() {
		if (!Configuration::deleteByName('zibal_prestashop_API') || !Configuration::deleteByName('zibal_prestashop_LOGO') || !Configuration::deleteByName('zibal_prestashop_HASH_KEY') || !parent::uninstall())
			return false;
		else
			return true;
	}

	public function hash_key() {
		$en = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
		$one = rand(1, 26);
		$two = rand(1, 26);
		$three = rand(1, 26);
		return $hash = $en[$one] . rand(0, 9) . rand(0, 9) . $en[$two] . $en[$tree] . rand(0, 9) . rand(10, 99);
	}

	public function getContent() {

		if (Tools::isSubmit('zibal_prestashop_setting')) {

			Configuration::updateValue('zibal_prestashop_API', $_POST['zibal_API']);
			Configuration::updateValue('zibal_prestashop_LOGO', $_POST['zibal_LOGO']);
			$this->_html .= '<div class="conf confirm">' . $this->l('Settings updated') . '</div>';
		}

		$this->_generateForm();
		return $this->_html;
	}

	private function _generateForm() {
		$this->_html .= '<div align="center"><form action="' . $_SERVER['REQUEST_URI'] . '" method="post">';
		$this->_html .= $this->l(' کد درگاه پرداخت (مرچنت) را وارد نمایید ') . '<br/><br/>';
		$this->_html .= '<input type="text" name="zibal_API" value="' . Configuration::get('zibal_prestashop_API') . '" ><br/><br/>';
		$this->_html .= '<input type="submit" name="zibal_prestashop_setting"';
		$this->_html .= 'value="' . $this->l('ذخیره') . '" class="button" />';
		$this->_html .= '</form><br/></div>';
	}

	public function do_payment($cart) {

		if (extension_loaded('curl')) {

			$ZibalPin = Configuration::get('zibal_prestashop_API');
			$amount = floatval(number_format($cart ->getOrderTotal(true, 3), 2, '.', ''));
			$callbackUrl = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__ . 'modules/zibal_prestashop/zibal.php?do=call_back&id=' . $cart ->id . '&amount=' . $amount;
			$orderId = $cart ->id;
			$txt = 'پرداخت سفارش شماره: ' . $cart ->id;

			$currency_id = $cart->id_currency;

            foreach(Currency::getCurrencies() as $key => $currency){
                if ($currency['id_currency'] == $currency_id){
                    $currency_iso_code = $currency['iso_code'];
                }
            }

            if ($currency_iso_code != 'IRR'){
                $rial_amount = $amount * 10;
			}

			$params = array(
				'merchant' =>  $ZibalPin,
				'amount' => $rial_amount,
				'description' => $txt,
				'callbackUrl' => $callbackUrl
			);

			$result = $this->postToZibal('request', $params);
			$result = (array)$result;

			$hash = Configuration::get('zibal_prestashop_HASH');
			$_SESSION['order' . $orderId] = md5($orderId . $amount . $hash);
			if (isset($result) && isset($result['result']) && $result['result'] == 100) {
				echo $this->success($this->l('در حال ارجاع به درگاه پرداخت ...'));
				echo '<script>window.location=("https://gateway.zibal.ir/start/' . $result['trackId'] . '");</script>';
			} elseif (isset($result) && isset($result['result']) && $result['result'] != 100) {
				echo $this->error($this->l('مشکلی در پرداخت وجود دارد.') . ' (' . $this->resultCodes($result['result']) . ')');
			} else {
				echo $this->error($this->l('مشکلی در پرداخت وجود دارد.'));
			}
		} else {
			echo $this->error('تابع cURL در سرور فعال نمی باشد');
		}
	}

	public function error($str) {
		return '<div class="alert error">' . $str . '</div>';
	}

	public function success($str) {
		echo '<div class="conf confirm">' . $str . '</div>';
	}

	public function hookPayment($params) {
		global $smarty;
		$smarty ->assign('zibal_prestashop_logo', Configuration::get('zibal_prestashop_LOGO'));
		if ($this->active)
			return $this->display(__FILE__, 'zibalpayment.tpl');
	}

	public function hookPaymentReturn($params) {
		if ($this->active)
			return $this->display(__FILE__, 'zibalconfirmation.tpl');
	}

	public function postToZibal($path, $parameters)
    {
        $url ='https://gateway.zibal.ir/v1/'.$path;
       
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response  = curl_exec($ch);
        curl_close($ch);
        return json_decode($response);
	}
	
	private function resultCodes($error)
        {
            $response = '';
            switch ($error) {

                case '100':
                    $response = 'با موفقیت تایید شد.';
                    break;

                case '102':
                    $response = 'merchant یافت نشد.';
                    break;

                case '103':
                    $response = 'merchant غیرفعال';
                    break;

                case '104':
                    $response = 'merchant نامعتبر';
                    break;

                case '201':
                    $response = 'قبلا تایید شده.';
                    break;

                case '105':
                    $response = 'amount بایستی بزرگتر از 1,000 ریال باشد.';
                    break;

                case '106':
                    $response = 'callbackUrl نامعتبر می‌باشد. (شروع با http و یا https';
                    break;

                case '113':
                    $response = 'amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است.';
                    break;

                case '202':
                    $response = 'سفارش پرداخت نشده یا ناموفق بوده است.';
                    break;

                case '203':
                    $response = 'trackId نامعتبر می‌باشد.';
                    break;
            }

            return $response;
        }

}

// End of: zibal_prestashop.php
?>
