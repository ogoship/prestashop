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
    $this->version = '1.0.0';
    $this->author = 'Mohamed Nawas';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_); 
    $this->bootstrap = true;
 	
	$this->merchantID = Configuration::get('MODULEOGOSHIP_MERCHANT_ID');
	$this->secretToken = Configuration::get('MODULEOGOSHIP_SECRET_TOKEN');
	$this->api = new NettivarastoAPI($this->merchantID, $this->secretToken);
		
    parent::__construct();
 
    $this->displayName = $this->l('Ogoship');
    $this->description = $this->l('Ogoship Module.');
 
    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
  }
  
 public function install()
{
	$sql = array();
	
	$sql[] = "ALTER TABLE `"._DB_PREFIX_."product` ADD `export_to_ogoship` INT NOT NULL DEFAULT '0' AFTER `pack_stock_type`";
	foreach ($sql as $query) {
		if (Db::getInstance()->execute($query) == false) {
			return false;
		}
	}
			
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
}