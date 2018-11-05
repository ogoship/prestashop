<?php
if (!defined('_PS_VERSION_'))
  exit;
require_once 'lib/API.php'; 
class Ogoship extends Module 
{
  public function __construct()
  {
    $this->name = 'ogoship';
    $this->tab = 'other_modules';
    $this->version = '1.0.2';
    $this->author = 'Ogoship';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_); 
    $this->bootstrap = true;
 	
	$this->merchantID = Configuration::get('MODULEOGOSHIP_MERCHANT_ID');
	$this->secretToken = Configuration::get('MODULEOGOSHIP_SECRET_TOKEN');
	$this->api = new NettivarastoAPI($this->merchantID, $this->secretToken);
		
    parent::__construct();
 
    $this->displayName = $this->l('OGOship');
    $this->description = $this->l('OGOship Module.');
 
    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
  }
  
  public function install()
  {
    try {
	$sql = array();
    $sql[0] = "ALTER TABLE `"._DB_PREFIX_."product` ADD `export_to_ogoship` INT NOT NULL DEFAULT '0'";
    $sql[1] = "ALTER TABLE `"._DB_PREFIX_."orders` ADD `ogoship_status` TINYINT(2) NOT NULL DEFAULT '0'";
	foreach ($sql as $query) {
		if (Db::getInstance()->execute($query) == false) {
            echo 'Warning: column creation failed';
		}
	}
    } catch(Exception $e){}
			
    if (!parent::install() ||
        !$this->registerHook('displayOverrideTemplate')
        )
        return false;
    return true;
  }
  
  	public function uninstall()
	{
		if (!parent::uninstall())
			return false;
		return true;
	}
	
	public function hookDisplayOverrideTemplate($params){
	/* if (isset($params['controller']->php_self) && $params['controller']->php_self == 'category'){
		 return dirname(__FILE__).'/view.tpl';
	 }*/	 
	}
	
  public function getContent(){
  	$html = '';
	if(Tools::isSubmit('submitUpdate')){
		Configuration::updateValue('MODULEOGOSHIP_MERCHANT_ID', Tools::getValue('merchant_id'));
		Configuration::updateValue('MODULEOGOSHIP_SECRET_TOKEN', Tools::getValue('secret_token'));
		Configuration::updateValue('MODULEOGOSHIP_DENY_EXPORT', Tools::getValue('deny_product_export'));
		foreach($_POST['shipping_code'] as $key=>$value){
			$strField	=	'MODULEOGOSHIP_SHIPPING_CODE_'.$key;
			Configuration::updateValue($strField, $value);
		}
		$html .= $this->displayConfirmation($this->l('Settings Updated'));
	}
	if(Tools::isSubmit('SubmitExportAllProducts')){
			$html .=$this->export_all_products();
	}
	if(Tools::isSubmit('SubmitUpdateOrdersAndProducts')){
		$html .=$this->get_latest_changes();
	}
	if(Configuration::get("MODULEOGOSHIP_DENY_EXPORT")==1){ $strChecked = "checked";}else{$strChecked="";}
	
	$strCarriers	=	Carrier::getCarriers(Configuration::get('PS_LANG_DEFAULT'));
	$strCarrierString = '';
	$strCarrierString .= '<br><label class="control-label col-lg-10"><h3>Shipping Code</h3></label><br><br>';
	foreach($strCarriers as $value){
		$strCarrierName		=	$value['id_carrier'];
		$strShippingCode	=	'MODULEOGOSHIP_SHIPPING_CODE_'.$strCarrierName;
		$strShippingField	=	'shipping_code['.$strCarrierName.']';
		$strCarrierString .= '<label class="control-label col-lg-4">'.$value['name'].' shipping code</label>
			<div class="col-lg-6">
				<input type="text" name="'.$strShippingField.'" value="'.Configuration::get($strShippingCode).'">
			</div><br><br>';
	}
	$html .= '
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post" class="defaulForm form-hotizontal">
		<div class="panel">
			<div class="panel-heading">'.$this->l('Ogoship Settings').'</div>
		<div class="form-group">
			<label class="control-label col-lg-4">'.$this->l('Merchant ID').'</label>
			<div class="col-lg-6">
				<input type="text" name="merchant_id" value="'.Configuration::get('MODULEOGOSHIP_MERCHANT_ID').'">
			</div><br><br>
			<label class="control-label col-lg-4">'.$this->l('Secret Token').'</label>
			<div class="col-lg-6">
				<input type="text" name="secret_token" value="'.Configuration::get('MODULEOGOSHIP_SECRET_TOKEN').'">
			</div><br><br><br><br>
			<label class="control-label col-lg-10"><h3>Export Product and Update Orders and Product</h3></label><br><br>
			<label class="control-label col-lg-4">'.$this->l('Export Product').'</label>
			<div class="col-lg-6">
				Click <input type="submit" name="SubmitExportAllProducts" value="'.$this->l('Here').'" class="btn btn-default"> to export all products to Ogoship
			</div><br><br>
			<label class="control-label col-lg-4">'.$this->l('Update Orders and Products').'</label>
			<div class="col-lg-6">
				Click <input type="submit" name="SubmitUpdateOrdersAndProducts" value="'.$this->l('Here').'" class="btn btn-default"> to update product and order info from Ogoship.
			</div><br><br>'.$strCarrierString.'
			<label class="control-label col-lg-4">'.$this->l('Deny product export').'</label>
			<div class="col-lg-6">
				 <input type="checkbox" name="deny_product_export" id="deny_product_export" value="1" '.$strChecked.'/> Deny product export
			</div><br><br>
			<label class="control-label col-lg-4"></label>
			<div class="col-lg-6">
				<input type="submit" name="submitUpdate" value="'.$this->l('Save').'" class="btn btn-default">
			</div>
		</div>
		</form>
	';	
	return $html;
  }
  
  public function export_all_products(){
	$id_lang = Configuration::get('PS_LANG_DEFAULT');
	if(Configuration::get("MODULEOGOSHIP_DENY_EXPORT")==1){
		  $html = $this->displayError($this->l('Error: Export product has been denied'));
		  return $html;
	}
	$productObj = new Product();
	$products = $productObj->getProducts($id_lang, 0, 0, 'id_product', 'DESC' );
	foreach($products as $key=>$value){
		if($value['export_to_ogoship']==0){
			$image = Image::getCover($value['id_product']);
			$imagePath = Link::getImageLink($value->link_rewrite, $image['id_image'], 'home_default');
			$url = $this->context->link->getProductLink($value['id_product']);
			$currency_iso_code = $this->context->currency->iso_code;
		
			if(!empty($value['reference'])){
				$product_array = array(
					'Code' => $value['reference'],
					'Name' => $value['name'],
					'Description' => strip_tags($value['description']),
					'InfoUrl' => $url,
					'SalesPrice' => $value['price'],
					'Weight'=> $value['weight'],
					'Height'=> $value['height'],
					'Width'=> $value['width'],
					'Depth'=> $value['depth'],
					'VatPercentage'=> '',
					'PictureUrl'=>$imagePath,
					'Currency' => $currency_iso_code
				  );
				$NV_products['Products']['Product'][] = $product_array;
			}
			$product_array = '';
			$PictureUrl = '';
			$variations = ''; 
		}		
	}
	$response = $this->api->updateAllProducts($NV_products);
	if ( $response ) {
	  if ( ! ( (string)$response['Response']['Info']['@Success'] == 'true' ) ) {
		 $strError = $response['Response']['Info']['@Error'];
		 $html = $this->displayError($strError);
	  } else {
		$html = $this->displayConfirmation($this->l('Product export completed.'));
	  }
	}
	
	return $html;
  }
  
  public function get_latest_changes() {
  	$latest = $this->api->latestChanges($latestProducts, $latestOrders);
	if($latestOrders) {
		foreach($latestOrders as $latestOrder) {
			$strOrderId = $latestOrder->getReference();
			$query_get_id= "SELECT id_order FROM `"._DB_PREFIX_."orders` WHERE reference='".$strOrderId."'";
			if ($row = Db::getInstance()->getRow($query_get_id))
				$strOrderIdByRef	=  $row['id_order'];
			if(isset($strOrderIdByRef)){
				$order_details = array();
				$order = new Order((int)$strOrderIdByRef);
				$customer= new Customer((int)$order->id_customer);
				$id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $order->id);
				if (!$id_customer_thread) {
					$customer_thread = new CustomerThread();
					$customer_thread->id_contact = 0;
					$customer_thread->id_customer = (int)$order->id_customer;
					$customer_thread->id_shop = (int)$this->context->shop->id;
					$customer_thread->id_order = (int)$order->id;
					$customer_thread->id_lang = (int)$this->context->language->id;
					$customer_thread->email = $customer->email;
					$customer_thread->status = 'open';
					$customer_thread->token = Tools::passwdGen(12);
					$customer_thread->add();
				} else {
					$customer_thread = new CustomerThread((int)$id_customer_thread);
				}
				
				$customer_message = new CustomerMessage();
				$customer_message->id_customer_thread = $customer_thread->id;
				$customer_message->id_employee = (int)$this->context->employee->id;
				
				switch ( $latestOrder->getStatus() ) {	
					 case  'SHIPPED': 
					 	 $history = new OrderHistory();
						 $history->changeIdOrderState(4, $order);
						 $strMessage	=	'Ogoship change of status to SHIPPED.Tracking Number '.$latestOrder->getTrackingNumber();
						$strTotal	=	$this->checkDuplicateMessage($customer_message->id_customer_thread,$strMessage);
						if($strTotal==0){
							$customer_message->message = $strMessage;
							$customer_message->add();
						}
						
						$query_order= "UPDATE `"._DB_PREFIX_."orders` SET shipping_number='".$latestOrder->getTrackingNumber()."' WHERE id_order='".$strOrderIdByRef."'";
						if (!Db::getInstance()->execute($query_order))
						die('error!');
						
						$query_carrier= "UPDATE `"._DB_PREFIX_."order_carrier` SET tracking_number='".$latestOrder->getTrackingNumber()."' WHERE id_order='".$strOrderIdByRef."'";
						if (!Db::getInstance()->execute($query_carrier))
						die('error!');
                        break;
                    case  'CANCELLED':
						$strMessage	=	'Ogoship change of status to CANCELLED';
						$strTotal	=	$this->checkDuplicateMessage($customer_message->id_customer_thread,$strMessage);
						if($strTotal==0){
							$customer_message->message = $strMessage;
							$customer_message->add();
						}
                        break;
                    case  'COLLECTING':
						$strMessage = 'Ogoship change of status to COLLECTING.';
						$strTotal	=	$this->checkDuplicateMessage($customer_message->id_customer_thread,$strMessage);
						if($strTotal==0){
							$customer_message->message = $strMessage;
							$customer_message->add();
						}
                        break;
                    case  'PENDING':
						$strMessage = 'Ogoship change of status to PENDING.';
						$strTotal	=	$this->checkDuplicateMessage($customer_message->id_customer_thread,$strMessage);
						if($strTotal==0){
							$customer_message->message = $strMessage;
							$customer_message->add();
						}
                        break;
                    case  'RESERVED':
						$strMessage = 'Ogoship change of status to RESERVED.';
						$strTotal	=	$this->checkDuplicateMessage($customer_message->id_customer_thread,$strMessage);
						if($strTotal==0){
							$customer_message->message = $strMessage;
							$customer_message->add();
						}
                        break;
				}
			}
		}
	}	
	
	if($latestProducts) {
		 foreach($latestProducts as $latestProduct) {
			$this->updateProductStockStatus($latestProduct->getCode(),$latestProduct->getStock());
		 }
	}		 
	
	 $html = $this->displayConfirmation($this->l('Product and order data updated from Ogoship.'));
	 return $html;	
  }
  
  public function checkDuplicateMessage($id_customer_thread,$strMessage){
	$sql = 'SELECT * FROM '._DB_PREFIX_.'customer_message WHERE	id_customer_thread='.$id_customer_thread.' AND message LIKE "'.$strMessage.'"';
	$result = Db::getInstance()->query($sql);
	$total = Db::getInstance()->numRows();
	return $total;
  }
  
  public function updateProductStockStatus($strSku,	$strQuantity){
	$sql = 'SELECT id_product FROM '._DB_PREFIX_.'product WHERE	reference="'.$strSku.'"';
	$result = Db::getInstance()->query($sql);
	$total = Db::getInstance()->numRows();
	if($total!=0){
		$row = Db::getInstance()->getRow($sql);
		$UpdateSql	=	'UPDATE '._DB_PREFIX_.'stock_available SET quantity='.$strQuantity.' WHERE id_product='.$row['id_product'].' AND id_product_attribute=0';
		Db::getInstance()->query($UpdateSql);
	
	}
  }
  
  public function getAllOrders(){	  
		$sql = 'SELECT id_order FROM `'._DB_PREFIX_.'orders` WHERE ogoship_status = 0 ORDER BY id_order DESC';
		$result = Db::getInstance()->query($sql);
		$total = Db::getInstance()->numRows();

		if($total!=0){
			$row = Db::getInstance()->executeS($sql);
			
			for($k=0; $k<count($row); $k++){
				$orderId = $row[$k]['id_order'];
				$_GET['id_order'] = $orderId;
				$order = new Order(Tools::getValue('id_order'));		
				$this->save_all_orders_to_ogoship($order);						
			}
		}
	}
  
	public function save_all_orders_to_ogoship($order){		
		$reference = Order::generateReference();
		
		$qry = 'SELECT `reference` FROM `'._DB_PREFIX_.'orders` WHERE `id_order` = '.(int)Tools::getValue('id_order');
		$order_reference = Db::getInstance()->getValue($qry);			
		$order_api = new NettivarastoAPI_Order($this->api,$order_reference);
		$order_details = $order->getOrderDetailList();

		$strOrderId = '';
		$strOrderId = (int)Tools::getValue('id_order');		
		
		//if($_SERVER['REMOTE_ADDR']=='122.164.252.246'){
			//print $strOrderId;
			//print '<pre>';
			//print_r($order_details);
			//exit;
		//}
		
		$index = 0;
		$strCarriers = Carrier::getCarriers(Configuration::get('PS_LANG_DEFAULT'));
		$id_order_carrier = Db::getInstance()->getValue('
				SELECT `id_carrier`
				FROM `'._DB_PREFIX_.'order_carrier`
				WHERE `id_order` = '.(int)Tools::getValue('id_order'));
				
		$strShippingCode = 'MODULEOGOSHIP_SHIPPING_CODE_'.$id_order_carrier;
		$strOrderShippingCode = Configuration::get($strShippingCode);
		
		$siteUrl = '';
		foreach($order_details as $key=>$value){			
			// Order Invoice URL
			$orderSql = 'SELECT secure_key FROM '._DB_PREFIX_.'orders WHERE id_order='.$value['id_order'];
			$orderRow = Db::getInstance()->getRow($orderSql);
			$getSiteUrl = Tools::getHttpHost(true).__PS_BASE_URI__;
			$siteUrl = $getSiteUrl.'index.php?controller=pdf-invoice&id_order='.$value['id_order'].'&secure_key='.$orderRow['secure_key'];
			// Order Invoice URL
			
			$sql = 'SELECT export_to_ogoship FROM '._DB_PREFIX_.'product WHERE id_product='.$value['product_id'];
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
			$strMessage	= $message[0]['message'];
		} else {
			$strMessage	=	'';
		}
		
		// if($id_order_carrier=='39'){
		// 	$username = "EOQEEH6VDE7PHQZVOS4XL9ZKH73V4TYA";
		// 	$password = "EOQEEH6VDE7PHQZVOS4XL9ZKH73V4TYA";
			
		// 	$getSiteUrl = Tools::getHttpHost(true).__PS_BASE_URI__;
			
		// 	$url = $getSiteUrl.'api/orders/'.$strOrderId;
			
		// 	$context = stream_context_create(array (
		// 		'http' => array (
		// 			'header' => 'Authorization: Basic ' . base64_encode("$username:$password")
		// 		)
		// 	));
			
		// 	$data = file_get_contents($url, false, $context);
		// 	$xml = simplexml_load_string($data); 
		// 	$strPupCode	= (string)$xml->order->associations->pickup_locations->pickup_location->pup_code;
		// 	$strPupCode = str_replace("<![CDATA[","",$strPupCode);
		// 	$strPupCode = str_replace("]]>","",$strPupCode);
		// } else {
		 	$strPupCode	= '';
		// }		
		
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
		
		if(!empty($strPupCode)){
			$order_api->setPickUpPointCode($strPupCode);
		}
		
		/*$order_api->setDocumentType('receipt');*/
		$order_api->setDocumentURL('1',$siteUrl);		

        if ( $order_api->save() ) {
			$this->confirmations[] = 'Order successfully transferred to Ogoship.';
			$UpdateSql = 'UPDATE `'._DB_PREFIX_.'orders` SET ogoship_status = 1 WHERE id_order='.(int)$strOrderId.'';
			Db::getInstance()->query($UpdateSql);
			return;
        } else {
			$this->errors[] = Tools::displayError('Error: '.$this->api->getLastError().'');	  
			return; 
        }  
	}
}