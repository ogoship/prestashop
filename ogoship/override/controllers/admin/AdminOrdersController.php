<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email 
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/
/**
 * @property Order $object
 */
require_once _PS_MODULE_DIR_.'ogoship/lib/API.php'; 
class AdminOrdersController extends AdminOrdersControllerCore
{
    /*
    * module: ogoship
    * date: 2017-08-16 09:03:42
    * version: 1.0.0
    */
    public $toolbar_title;
    /*
    * module: ogoship
    * date: 2017-08-16 09:03:42
    * version: 1.0.0
    */
    protected $statuses_array = array();
    /*
    * module: ogoship
    * date: 2017-08-16 09:03:42
    * version: 1.0.0
    */
    public function __construct()
    {
		$this->merchantID = Configuration::get('MODULEOGOSHIP_MERCHANT_ID');
		$this->secretToken = Configuration::get('MODULEOGOSHIP_SECRET_TOKEN');
		$this->api = new NettivarastoAPI($this->merchantID, $this->secretToken);
        parent::__construct();
    }
	
    /*
    * module: ogoship
    * date: 2017-08-16 09:03:42
    * version: 1.0.0
    */
    public function save_order_to_ogoship($order){
		$reference = Order::generateReference();
		$order_reference = Db::getInstance()->getValue('
				SELECT `reference`
				FROM `'._DB_PREFIX_.'orders`
				WHERE `id_order` = '.(int)Tools::getValue('id_order'));
		$order_api = new NettivarastoAPI_Order($this->api,$order_reference);
		$order_details = $order->getOrderDetailList();
		$index = 0;
		$strCarriers	=	Carrier::getCarriers(Configuration::get('PS_LANG_DEFAULT'));
		$id_order_carrier = Db::getInstance()->getValue('
				SELECT `id_carrier`
				FROM `'._DB_PREFIX_.'order_carrier`
				WHERE `id_order` = '.(int)Tools::getValue('id_order'));
				
		$strShippingCode	=	'MODULEOGOSHIP_SHIPPING_CODE_'.$id_order_carrier;
				
		$strOrderShippingCode = Configuration::get($strShippingCode);
		
		foreach($order_details as $key=>$value){
			$sql = 'SELECT export_to_ogoship FROM '._DB_PREFIX_.'product WHERE	id_product='.$value['product_id'];
			$result = Db::getInstance()->query($sql);
			$row = Db::getInstance()->getRow($sql);
			if($row['export_to_ogoship']==0){
				$order_api->setOrderLineCode( $index, ($value['product_reference']) );
				$order_api->setOrderLineQuantity( $index, ($value['product_quantity']));
				$index++;
			}
		}
		$shipping_address = new Address(intval($order->id_address_delivery));
		$customer= new Customer((int)$order->id_customer);
		$message = CustomerMessage::getMessagesByOrderId((int)Tools::getValue('id_order'), false);
		if(isset($message[0])){
			$strMessage	=	$message[0]['message'];
		}else{
			$strMessage	=	'';
		}
		$countrySql = 'SELECT iso_code FROM '._DB_PREFIX_.'country WHERE id_country='.$shipping_address->id_country;
		$countryRow = Db::getInstance()->getRow($countrySql);
		$countryIsoCode = $countryRow['iso_code'];
					
		$order_api->setPriceTotal(round($order->getOrdersTotalPaid(), 2));
		$order_api->setCustomerName($shipping_address->firstname.' '.$shipping_address->lastname);
		$order_api->setCustomerAddress1($shipping_address->address1);
		$order_api->setCustomerAddress2($shipping_address->address2);
		$order_api->setCustomerCity($shipping_address->city);
		//$order_api->setCustomerCountry($shipping_address->country);
		$order_api->setCustomerCountry($countryIsoCode);
		$order_api->setCustomerCompany($shipping_address->company);
		$order_api->setCustomerEmail($customer->email);
		$order_api->setCustomerPhone($shipping_address->phone_mobile);
		$order_api->setCustomerZip($shipping_address->postcode);
		$order_api->setComments($strMessage);	
		
		$order_api->setShipping($strOrderShippingCode);
      if ( $order_api->save() ) {
		 $this->confirmations[] = 'Order successfully transferred to Ogoship.';
          return;
      }
      else {
		  $this->errors[] = Tools::displayError('Error: '.$this->api->getLastError().'');
      return; 
      }  
	}
    /*
    * module: ogoship
    * date: 2017-08-16 09:03:42
    * version: 1.0.0
    */
    public function renderView()
    {
        $order = new Order(Tools::getValue('id_order'));
        if (!Validate::isLoadedObject($order)) {
            $this->errors[] = Tools::displayError('The order cannot be found within your database.');
        }
		if(Tools::isSubmit('submitSendOrder')){
			$this->save_order_to_ogoship($order);
		}
		
		if (Module::isInstalled('ogoship')){
			$ogoship = '1';	
		}else{
			$ogoship = '0';	
		}
		$this->context->smarty->assign(array(
			'ogoship' => $ogoship
		));
		
        return parent::renderView();
    }
}
