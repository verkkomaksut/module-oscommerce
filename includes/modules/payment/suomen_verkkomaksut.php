<?php
/*
 * Release info: Released under the GNU General Public License
 * 
 * Version: 1.4 (13.11.2009)
 * Author: Suomen Verkkomaksut Oy
 * 
 * History:
 * ------------------------------------
 * Version: 1.4 (13.11.2009)
 * - Supports interface version 5.1
 *
 * Version: 1.3 (12.8.2009)
 * - LowOrderFee calculation fixed
 * 
 * Version: 1.2 (5.6.2009)
 * - Charsets fixed
 * - Sort orders fixed
 * 
 * Version 1.1:
 * Function before_process fixed
 * 
 */

  class suomen_verkkomaksut {
    var $code, $title, $description, $enabled;

	// class constructor
    function suomen_verkkomaksut() {
      global $order;

      $this->code = 'suomen_verkkomaksut';
      $this->title = MODULE_PAYMENT_SV_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_SV_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_SV_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_SV_STATUS == 'True') ? true : false);

      if ((int)MODULE_PAYMENT_SV_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_SV_ORDER_STATUS_ID;
      }

      if (is_object($order)) $this->update_status();

      $this->form_action_url = 'https://ssl.verkkomaksut.fi/payment.svm';

    }

	// class methods
    function update_status() {
      global $order;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_SV_ZONE > 0) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_SV_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } else if ($check['zone_id'] == $order->delivery['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

    function javascript_validation() {
      return false;
    }
    

    function selection() {
      return array('id' => $this->code,
                   'module' => $this->title);
    }

    function pre_confirmation_check() {
      return false;
    }

    function confirmation() {
      return false;
    }

    function process_button() {
		global $order, $currencies, $currency, $customer_id, $products, $shipping, $total;

		$items = sizeof($order->products);
		
		if($shipping['cost'] AND $shipping['cost'] != 0)
		{
			$items++;
		}
		
		if(MODULE_ORDER_TOTAL_LOWORDERFEE_STATUS == 'true' AND ($order->info['subtotal']) < MODULE_ORDER_TOTAL_LOWORDERFEE_ORDER_UNDER)
		{
			$items++;
		}
		
		if(DISPLAY_PRICE_WITH_TAX == "true")
		{
			$include_vat = 1;
		}
		else
		{
			$include_vat = 0;
		}
			
       	$params = array(
			"MERCHANT_ID" => MODULE_PAYMENT_SV_KAUPPIAAN_ID,
			"ORDER_NUMBER" => $_SESSION['customer_id'] . '-' . date('Ymdhis'),
			"REFERENCE_NUMBER" => "",
			"ORDER_DESCRIPTION" => "",
			"CURRENCY" => $currency,
			"RETURN_ADDRESS" => tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL'),
			"CANCEL_ADDRESS" => tep_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'),
			"NOTIFY_ADDRESS" => "",
			"TYPE" => "5.1",
			"CULTURE" => "fi_FI",
			"CONTACT_TELNO" => "",
			"CONTACT_CELLNO" => $order->customer['telephone'],
			"CONTACT_EMAIL" => $order->customer['email_address'],
			"CONTACT_FIRSTNAME" => $order->customer['firstname'],
			"CONTACT_LASTNAME" => $order->customer['lastname'],
          	"CONTACT_COMPANY" => $order->customer['company'],
			"CONTACT_ADDR_STREET" => $order->customer['street_address'],
			"CONTACT_ADDR_ZIP" => $order->customer['postcode'],
			"CONTACT_ADDR_CITY" => $order->customer['city'],
			"CONTACT_ADDR_COUNTRY" => $order->customer['country']['iso_code_2'],
       		"INCLUDE_VAT" => $include_vat,
       		"ITEMS" => $items
		);
			
		$i = 0;
    	for ($i=0, $n=sizeof($order->products); $i<$n; $i++)
    	{
			$params["ITEM_TITLE[$i]"] = $order->products[$i]['name'];
			$params["ITEM_NO[$i]"] = $order->products[$i]['id'];
			$params["ITEM_AMOUNT[$i]"] = $order->products[$i]['qty'];
			
			if(DISPLAY_PRICE_WITH_TAX == "true")
			{
				$params["ITEM_PRICE[$i]"] = number_format($order->products[$i]['final_price'] + tep_calculate_tax($order->products[$i]['final_price'], $order->products[$i]['tax']), 2, ".", "");
			}
			else
			{
				$params["ITEM_PRICE[$i]"] = $order->products[$i]['final_price'];
			}
			
			$params["ITEM_TAX[$i]"] = $order->products[$i]['tax'];
			$params["ITEM_DISCOUNT[$i]"] = 0;
			$params["ITEM_TYPE[$i]"] = 1;			
		}
		

    	if(MODULE_ORDER_TOTAL_LOWORDERFEE_STATUS == 'true' AND $order->info['subtotal'] < MODULE_ORDER_TOTAL_LOWORDERFEE_ORDER_UNDER)
		{
			$loworderfeetax = tep_get_tax_rate(MODULE_ORDER_TOTAL_LOWORDERFEE_TAX_CLASS, $order->delivery['country']['id'], $order->delivery['zone_id']);
			$params["ITEM_TITLE[$i]"] = MODULE_ORDER_TOTAL_LOWORDERFEE_TITLE;
			$params["ITEM_NO[$i]"] = $i;
			$params["ITEM_AMOUNT[$i]"] = 1;
			
			if(DISPLAY_PRICE_WITH_TAX == "true")
			{
				$params["ITEM_PRICE[$i]"] = number_format(MODULE_ORDER_TOTAL_LOWORDERFEE_FEE + tep_calculate_tax(MODULE_ORDER_TOTAL_LOWORDERFEE_FEE, $loworderfeetax), 2, ".", "");
			}
			else
			{
				$params["ITEM_PRICE[$i]"] = MODULE_ORDER_TOTAL_LOWORDERFEE_FEE;
			}
			
			$params["ITEM_TAX[$i]"] = $loworderfeetax;
			$params["ITEM_DISCOUNT[$i]"] = 0;
			$params["ITEM_TYPE[$i]"] = 2;
			$i++;
		}
		
		if($shipping['cost'] AND $shipping['cost'] != 0)
		{
			// Loads tax info for shipping
			$module 		= substr($GLOBALS['shipping']['id'], 0, strpos($GLOBALS['shipping']['id'], '_'));
			$shipping_tax 	= 0;
			
			if ( $module )
			{
				if (tep_not_null($order->info['shipping_method'])) {
			        if ($GLOBALS[$module]->tax_class > 0) {
			          $shipping_tax = tep_get_tax_rate($GLOBALS[$module]->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
			        }
		      	}
			}
		
			$params["ITEM_TITLE[$i]"] = $order->info['shipping_method'];
			$params["ITEM_NO[$i]"] = $i;
			$params["ITEM_AMOUNT[$i]"] = 1;
		
			if(DISPLAY_PRICE_WITH_TAX == "true")
			{
				$params["ITEM_PRICE[$i]"] = number_format($shipping['cost'] + tep_calculate_tax($shipping['cost'], $shipping_tax), 2, ".", "");
			}
			else
			{
				$params["ITEM_PRICE[$i]"] = $shipping['cost'];
			}
			
			$params["ITEM_TAX[$i]"] = $shipping_tax;
			$params["ITEM_DISCOUNT[$i]"] = 0;
			$params["ITEM_TYPE[$i]"] = 2;
		}
		
		// 5.1 interface | replace	

		foreach( $params as $key => $value )
		{
			if($key == "RETURN_ADDRESS" || $key == "CANCEL_ADDRESS" || $key == "NOTIFY_ADDRESS")
			{
				$params[$key] = str_replace("|","%7C",$value);	
			}
			else
			{
				$params[$key] = str_replace("|","",$value);
			}
		}	
		
	    
		$auth_array = array();
		foreach( $params as $key => $value )
		{
			$auth_array[] = $value;
		}
		
		$auth_string = MODULE_PAYMENT_SV_AVAIN . "|" . implode( "|", $auth_array );

		$auth_md5_string = strtoupper( md5( $auth_string ) );
		
		foreach( $params as $key => $value )
		{
			$process_button_string .= "<input type=\"hidden\" name=\"{$key}\" value=\"".htmlentities($value, ENT_COMPAT, "UTF-8")."\" />\n";
		}
		$process_button_string .= "<input type=\"hidden\" name=\"AUTHCODE\" value=\"".$auth_md5_string."\" />\n";

      	return $process_button_string;
    }

   function before_process() {
   		$maksupalvelu['checking_get']         = $_GET['RETURN_AUTHCODE'];
		$maksupalvelu['checking_should_be']   = strtoupper(md5($_GET['ORDER_NUMBER'] . "|" .$_GET['TIMESTAMP'] . "|" .$_GET['PAID'] .  "|" .$_GET['METHOD'] . "|" . MODULE_PAYMENT_SV_AVAIN));
		
		if($maksupalvelu['checking_should_be'] != $_GET['RETURN_AUTHCODE']) {
			 tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT));
		}
		return false;
    }

    function after_process() {
      $maksupalvelu['checking_get']         = $_GET['RETURN_AUTHCODE'];
      $maksupalvelu['checking_should_be']   = strtoupper(md5($_GET['ORDER_NUMBER'] . "|" .$_GET['TIMESTAMP'] . "|" .$_GET['PAID'] . "|" .$_GET['METHOD'] . "|" . MODULE_PAYMENT_SV_AVAIN));
      if($maksupalvelu['checking_should_be'] == $_GET['RETURN_AUTHCODE']) {
        $_SESSION['cart']->reset(true);
        unset($_SESSION['cart']);
        $_SESSION['order_created'] = '';
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
      } else {
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT));
      }
    }

    function get_error() {
      return false;
    }

    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_SV_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Ota Maksupalvelu k&auml;ytt&ouml;&ouml;n', 'MODULE_PAYMENT_SV_STATUS', 'True', 'Haluatko hyv&auml;ksy&auml; maksutavan?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'Ei\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Maksumoduulin voimassaoloalue', 'MODULE_PAYMENT_SV_ZONE', '0', 'Jos alue on valittu, voit k&auml;ytt&auml;&auml; maksutapaa vain kyseisell&auml; alueella', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Kauppiastunnus', 'MODULE_PAYMENT_SV_KAUPPIAAN_ID', '', 'Kauppiastunnus', '12', '3', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Kauppiasvarmenne', 'MODULE_PAYMENT_SV_AVAIN', '', 'Kauppiasvarmenne', '12', '4', now())"); 
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('N&auml;ytt&ouml;j&auml;rjestys', 'MODULE_PAYMENT_SV_SORT_ORDER', '0', 'N&auml;ytt&ouml;j&auml;rjestys', '6', '5', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Tilauksen tila', 'MODULE_PAYMENT_SV_ORDER_STATUS_ID', '0', 'Tila joka asetetaan maksutavan suorituksen j&auml;lkeen', '6', '6', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
   }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      	return array(
			'MODULE_PAYMENT_SV_STATUS', 
			'MODULE_PAYMENT_SV_ZONE', 
			'MODULE_PAYMENT_SV_KAUPPIAAN_ID', 
			'MODULE_PAYMENT_SV_AVAIN',
			'MODULE_PAYMENT_SV_ORDER_STATUS_ID', 
			'MODULE_PAYMENT_SV_SORT_ORDER'
		);
    }
  }
?>
