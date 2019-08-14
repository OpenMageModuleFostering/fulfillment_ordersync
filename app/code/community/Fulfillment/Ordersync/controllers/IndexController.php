<?php 

class Fulfillment_Ordersync_IndexController extends Mage_Core_Controller_Front_Action{
	/**
 	 * default action
 	 * @access public
 	 * @return void
 	 * @author Pankaj Pareek
 	 */
 	public function indexAction(){
		
		$status = 1;	// 1 = Enabled, 0 = Disabled
	
		$apiUsername = Mage::getStoreConfig('ordersync/display_settings/username');
		$apiPassword = Mage::getStoreConfig('ordersync/display_settings/password');	
		$this->loadLayout();  
		$this->renderLayout();	
		
		$parms = $this->getRequest()->getParams();
		$ssfUsername = $parms['ssfUsername'];
		$ssfPassword = $parms['ssfPassword'];
		$ssfAPICall = $parms['ssfAPICall'];
		
		
	   if($status == 1 && !empty($ssfUsername) && !empty($ssfPassword) && $ssfUsername == $apiUsername && $ssfPassword == $apiPassword){
		// Download list of orders
		 if(isset($ssfAPICall) && $ssfAPICall == "importCSV"){
		   
		    $collection = Mage::getResourceModel('sales/order_collection')->addAttributeToSelect('*');
			$collection->addFieldToFilter('status', 'processing');
			
			$csvstring = "";
			
			foreach ($collection as $col) {
				
				$_shippingAddress = $col->getShippingAddress();
				$id = $col->getId();
				
				$basetotal = $col->getBaseSubtotal();
				$baseshippingamount = $col->getBaseShippingAmount();
				$basegrandtotal = $col->getBaseGrandTotal();
				$baseshippingaaxamount = $col->getBaseShippingTaxAmount();
				//echo $customerid = $col->getCustomerId();
				
				$order_id = $col->getIncrementId();
				$createdate = $col->getCreatedAt();
				$created_date = strtotime($createdate);
				  $updateddate = $col->getUpdatedAt();
				$updated_date = strtotime($updateddate);
				$get_state = $col->getState();
				$get_status = $col->getStatus();
				$customer_name = $_shippingAddress->getFirstname()." ".$_shippingAddress->getLastname();
				$address1 = $_shippingAddress->getStreet1(); 
				$address2 = $_shippingAddress->getStreet2(); 
				$city = $_shippingAddress->getCity(); 
				$state = $_shippingAddress->getRegion(); 
				$country_code = $_shippingAddress->getCountry_id(); 
				$country = Mage::app()->getLocale()->getCountryTranslation($country_code);
				$customer_email = $_shippingAddress->getEmail();				
			    $customerNote = $col->getCustomerNote();
				$telephone = $_shippingAddress->getTelephone();
				
				
				$products = array();
				$items = $col->getAllVisibleItems();
				foreach($items as $i) {
					$product_it['pid'] =  $i->getProductId();
					$product_it['sku'] =  $i->getSku();
					$product_it['qty'] =  $i->getQtyOrdered();
					$product_it['price'] =  $i->getPrice();
					$product_it['name'] =  $i->getName();
					$product_it['order_id'] =  $order_id;
					
					$products[] = $product_it;
				}
   
   				$str = serialize($products);
    		   $products_str = urlencode($str);

				$csvv = "order_id=".$this->_csvSafe($order_id).",".
						"get_status=".$this->_csvSafe($get_status).",".
						"created_date=".$this->_csvSafe($created_date).",".
						"updated_date=".$this->_csvSafe($updated_date).",".
						"get_state=".$this->_csvSafe($get_state).",".
						"address1=".$this->_csvSafe($address1).",".
						"address2=".$this->_csvSafe($address2).",".
						"city=".$this->_csvSafe($city).",".
						"state=".$this->_csvSafe($state).",".
						"country=".$this->_csvSafe($country).",".
						"customer_email=".$this->_csvSafe($customer_email).",".
						"customer_name=".$this->_csvSafe($customer_name).",".
						"basetotal=".$this->_csvSafe($basetotal).",".
						"baseshippingamount=".$this->_csvSafe($baseshippingamount).",".
						"basegrandtotal=".$this->_csvSafe($basegrandtotal).",".
						"baseshippingaaxamount=".$this->_csvSafe($baseshippingaaxamount).",".						
						"customerNote=".$this->_csvSafe($customerNote).",".	
						"telephone=".$this->_csvSafe($telephone).",".	
					 	"products=".$products_str;
					 
				if(isset($csvstring) && $csvstring!="")
				 {
				   $csvstring = $csvstring."\r\n".$csvv;
				 
				 } else {
				   $csvstring = $csvv;
				 }
			 }  	 
	 
		      echo $csvstring; 		
		  }	
		  
		  
		 //----- Generate shipment and updae order ----------------------- 
		 if(isset($ssfAPICall) && $ssfAPICall == "importShipped"){	 
		 
			$ssfData = $parms['ssfData'];
			$ssfStock = $parms['ssfStock'];			
			$orders = unserialize(base64_decode($ssfData));
			$prds_stocks = unserialize(base64_decode($ssfStock));
						
			
		   if(count($orders))
		    {	
			foreach($orders as $oval)
			 {
			   $orderno = $oval['orderId'];
			   $tracking = $oval['trackingNumber'];
			   $shipDate = $oval['shipDate'];
			
			 $this->_processOrder($orderno, $tracking);
			
			}
			echo(1);
		  }	else {
		    echo(0);
		  }
		  
		  
		  if(count($prds_stocks))
		    {	
			foreach($prds_stocks as $pval)
			 {
			   $sku = $pval['sku'];
			   $stock = $pval['stock'];
			   $name = $pval['name'];
			
			  $this->_productStockUpdate($sku, $stock);
			
			}
			echo(1);
		  }	else {
		    echo(0);
		  }
		  
		 
		 }  
		 
		 
		//---------Get Prodcuts to import in to sytem ------------------------- 		
		
		 if(isset($ssfAPICall) && $ssfAPICall == "productList"){		
		
			$collection = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect('*'); 

			$productlist = array();
			foreach($collection as $product) {
				
				$productItem['pid'] =  $product->getId();
				$productItem['name'] =  $product->getName();
				$productItem['price'] =  $product->getPrice();
				$productItem['sku'] =  $product->getSku();
				$productItem['weight'] =  $product->getWeight();
				$productItem['qty'] =  (int)Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId())->getQty();
				$productlist[] = $productItem;

			}				 		
			
			$str = serialize($productlist);
    		echo $strenc = urlencode($str);
	
		 }
	   //----------------------------------
	   	
		
	   } else {
		echo("-1");
		exit;
	   }
	
	  echo("-1");
	  exit;	
	}
	
	
	function _csvSafe($string){
		return str_replace('"', '\"', $string);
	}
	

//---------function to update the product stock --------

	function _productStockUpdate($sku, $qty){
		
		$connection = Mage::getSingleton('core/resource')->getConnection('core_read');
		$connectionW = Mage::getSingleton('core/resource')->getConnection('core_write');
        
		$product = Mage::getModel('catalog/product')->loadByAttribute('sku',$sku);
		if($product)
		 {
		$stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
		$stockItemData = $stockItem->getData();
		if (empty($stockItemData)) {
		
			// Create the initial stock item object
			$stockItem->setData('manage_stock',1);
			$stockItem->setData('is_in_stock',$qty ? 1 : 0);
			$stockItem->setData('use_config_manage_stock', 0);
			$stockItem->setData('stock_id',1);
			$stockItem->setData('product_id',$product->getId());
			$stockItem->setData('qty',0);
			$stockItem->save();
		
			// Init the object again after it has been saved so we get the full object
			$stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product->getId());
		}
		
		// Set the quantity
		$stockItem->setData('is_in_stock',$qty ? 1 : 0);
		$stockItem->setData('qty',$qty);
		$stockItem->save();
		$product->save();
		
	   }	

    }	
	
//---------function to generate shipment and add tracking number --------

	function _processOrder($ORDERIDPP, $order_trackingno) {
		
		$connection = Mage::getSingleton('core/resource')->getConnection('core_read');
		$connectionW = Mage::getSingleton('core/resource')->getConnection('core_write');
	
	
		$orders = array();
		
		$odrid = $ORDERIDPP;
		$orders[$ORDERIDPP] = array($order_trackingno, 'Custom');
		
	
	
		foreach($orders as $orderno => $tinfo) {
		 
			$order = Mage::getModel('sales/order');  //Load the orders Model, so we can fetch and verify the order number
		 
			if($order->loadByIncrementId($orderno)){ 
			
				$convertor = Mage::getModel('sales/convert_order'); //Load the convert_order model, this allows us to 'convert' the order to a shipment (it can also do credit memos.)
				$shipment = $convertor->toShipment($order); //Tells the convertor we want to convert it to a shipment
				
				if($shipment) {  //This  foreach simply gets all the items, and adds them to our shipment.
					
					foreach ($order->getAllItems() as $orderItem) {
						if (!$orderItem->getQtyToShip()) {
							continue;
						}
						if ($orderItem->getIsVirtual()) {
							continue;
						}
						$item = $convertor->itemToShipmentItem($orderItem); //Prepares to add the item
						$qty = $orderItem->getQtyToShip();
						$item->setQty($qty); //Sets the quantity of that item to be shipped. Line above we set the quantity we wanted to ship
						$shipment->addItem($item); //Adds the item
					}
								
					$data = array();
					$data['carrier_code'] = 'custom';  //This needs to be custom, it allows us to enter a courier's company name in the 'title' section.
					$data['title'] = $tinfo[1];  //Couriers company name
					$data['number'] = $order_trackingno;//$tinfo[0];  //The tracking number
					
					$track = Mage::getModel('sales/order_shipment_track')->addData($data); 
					
					$shipment->addTrack($track);  //Adds the tracking to the shipment
					$shipment->register();
					$shipment->addComment(null, true); //Adds any comments if there are any. Includes the comment in the email if required.
					$shipment->setEmailSent(true);  //This marks the shipment as customer notified, otherwise it doesn't.
					$shipment->getOrder()->setIsInProcess(true);
					
					try {
						//Now we try to save the 'transaction', just to confirm everything has worked without the hitch.
						$transactionSave = Mage::getModel('core/resource_transaction')
														->addObject($shipment)
														->addObject($shipment->getOrder())
														->save();
					} catch (Exception $e) {
						//print_r($e); //Prints out any exceptions that have been thrown up, hopefully none!
						continue;
					}
				
					$shipment->sendEmail(true, ''); //Finally, Send email customer
					 
				}
			
			 }
						 
		  }
	   } 
	
	
	
	
}