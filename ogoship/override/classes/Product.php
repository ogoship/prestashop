<?php 
Class Product extends ProductCore
{
	public $export_to_ogoship = 0;
     public function __construct($id_product = null, $full = false, $id_lang = null, $id_shop = null, Context $context = null)
    {
        self::$definition['fields']['export_to_ogoship'] = array('type' => self::TYPE_BOOL);
        parent::__construct($id_product, $full, $id_lang, $id_shop, $context);
		if(!isset($_POST['export_to_ogoship'])){
			$_POST['export_to_ogoship'] = 0;
		}
    }
}	
?>