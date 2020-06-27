<?php
/**
 * @package    zibal_prestashop payment module
 * @author     Yahya Kangi
 * @copyright  2020  Zibal.ir
 * @link       https://docs.zibal.ir
 * @version    1.1
 */
@session_start();
if (isset($_GET['do'])) {
	include (dirname(__FILE__) . '/../../config/config.inc.php');
	include (dirname(__FILE__) . '/../../header.php');
	include_once (dirname(__FILE__) . '/zibal_prestashop.php');
	$zibal_prestashop = new zibal_prestashop;
	
	if ($_GET['do'] == 'payment') {
		$zibal_prestashop -> do_payment($cart);
	} else {

		if (isset($_GET['id']) && isset($_GET['success']) && isset($_GET['trackId']) && isset($_GET['status']) && $_GET['status'] == 2) {
			$orderId = $_GET['id'];
			// $amount = $_GET['amount'];
			$amount = floatval(number_format($cart ->getOrderTotal(true, 3), 2, '.', ''));
			$currency_id = $cart->id_currency;

			foreach(Currency::getCurrencies() as $key => $currency){
				if ($currency['id_currency'] == $currency_id){
					$currency_iso_code = $currency['iso_code'];
				}
			}

			if ($currency_iso_code != 'IRR'){
				$rial_amount = $amount * 10;
			}
			if (isset($_SESSION['order' . $orderId])) {
				$hash = Configuration::get('zibal_prestashop_HASH');
				$hash = md5($orderId . $amount . $hash);

				if ($hash == $_SESSION['order' . $orderId]) {
					$api = Configuration::get('zibal_prestashop_API');

					if (extension_loaded('curl')) {

						$params = array(
							'merchant' => $api ,
							'trackId' => $_GET["trackId"],
						);
						$result = postToZibal('verify', $params);

						$result = (array)$result;

						if (isset($result['result']) && $result['result'] == 100 && $result['amount'] == $rial_amount) {
							error_reporting(E_ALL);
							if(isset($_GET['refNumber']) && $_GET['refNumber' != Null]){
								$refNumber = $_GET['refNumber'];
							}else {
								$refNumber = 'test payment';
							}
							$zibal_prestashop -> validateOrder($orderId, _PS_OS_PAYMENT_, $amount, $zibal_prestashop -> displayName, "سفارش تایید شده / کد رهگیری {$refNumber}", array(), $cookie -> id_currency);
							$_SESSION['order' . $orderId] = '';
							Tools::redirect('history.php');
						} else {
							echo $zibal_prestashop -> error($zibal_prestashop -> l('مشکلی در پرداخت وجود دارد. ') . ' (' . resultCodes($result['result']) . ')<br/>' . $zibal_prestashop -> l('کد خطا') . ' : ' . $result['result']);
						}
					} else {
						echo $this->error('تابع cURL در سرور فعال نمی باشد');
					}

				} else {
					echo $zibal_prestashop -> error($zibal_prestashop -> l('مشکلی در پرداخت وجود دارد. ' . statusCodes($_GET['status'])));
				}
			} else {
				echo $zibal_prestashop -> error($zibal_prestashop -> l('مشکلی در پرداخت وجود دارد. ' . statusCodes($_GET['status'])));
			}
		} else {
			echo $zibal_prestashop -> error($zibal_prestashop -> l('مشکلی در پرداخت وجود دارد. ' . statusCodes($_GET['status'])));
		}
	}
	include_once (dirname(__FILE__) . '/../../footer.php');
} else {
	_403();
}
function _403() {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

function postToZibal($path, $parameters)
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

function resultCodes($error)
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
			$response = 'callbackUrl نامعتبر می‌باشد. (شروع با http و یا https)';
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

function statusCodes($error)
{
	$response = '';
	switch ($error) {

		case '-1':
			$response = 'در انتظار پردخت';
			break;

		case '-2':
			$response = 'خطای داخلی';
			break;

		case '1':
			$response = 'پرداخت شده - تاییدشده';
			break;

		case '2':
			$response = 'پرداخت شده - تاییدنشده';
			break;

		case '3':
			$response = 'لغوشده توسط کاربر';
			break;

		case '4':
			$response = 'شماره کارت نامعتبر می‌باشد.';
			break;

		case '5':
			$response = '‌موجودی حساب کافی نمی‌باشد.';
			break;

		case '6':
			$response = 'رمز واردشده اشتباه می‌باشد.';
			break;

		case '7':
			$response = '‌تعداد درخواست‌ها بیش از حد مجاز می‌باشد.';
			break;

		case '8':
			$response = '‌تعداد پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد.';
			break;
		
		case '9':
			$response = 'مبلغ پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد.';
			break;

		case '10':
			$response = 'صادرکننده‌ی کارت نامعتبر می‌باشد.';
			break;

		case '11':
			$response = '‌خطای سوییچ';
			break;

		case '12':
			$response = 'کارت قابل دسترسی نمی‌باشد.';
			break;
	}

	return $response;
}